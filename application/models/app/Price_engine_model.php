<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Price_engine_model
 *
 * Single Source of Truth for ALL pricing decisions in HMS.
 *
 * Resolution order (deterministic):
 *   1. NHIS coverage (if payer_type = NHIS AND item flagged is_nhis_covered = 1)
 *      -> use nhis_charge_amount / nhis_price; fall back to base if zero/missing.
 *   2. Company / Insurance pricing_percentage (when payer_type in {INSURANCE, COMPANY}
 *      and the linked insurance company has a non-zero pricing_percentage).
 *      -> base * (1 + pct/100)
 *   3. Catalog base rate (charge_amount / nPrice / cash_price).
 *
 * The engine never trusts client-submitted prices. Callers may supply
 * a 'submitted_unit_price' field to allow the engine to detect tampering
 * (logged via billing_audit_log, never silently honored).
 *
 * NOTHING in this class writes to invoice tables or receipts. It is pure
 * resolution + audit metadata.
 */
class Price_engine_model extends CI_Model
{
    const SOURCE_NHIS_TARIFF       = 'NHIS_TARIFF';
    const SOURCE_COMPANY_ADJUSTED  = 'COMPANY_ADJUSTED';
    const SOURCE_CATALOG           = 'CATALOG';
    const SOURCE_OVERRIDE_BLOCKED  = 'OVERRIDE_BLOCKED';

    const ITEM_DRUG     = 'DRUG';
    const ITEM_SERVICE  = 'SERVICE';
    const ITEM_LAB_TEST = 'LAB_TEST';
    const ITEM_IMAGING  = 'IMAGING';
    const ITEM_ROOM     = 'ROOM';

    private $_price_cache = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('schema_guard');
        if (!schema_already_run('price_engine_audit_columns')) {
            $this->_ensure_audit_columns();
            mark_schema_run('price_engine_audit_columns');
        }
        if (!schema_already_run('price_engine_pricing_provenance_columns')) {
            $this->_ensure_pricing_provenance_columns();
            mark_schema_run('price_engine_pricing_provenance_columns');
        }
    }

    /**
     * Idempotent migration: ensure audit columns exist on SSOT and legacy line tables.
     * Safe to call repeatedly; SHOW COLUMNS short-circuits on existing columns.
     */
    private function _ensure_audit_columns()
    {
        $prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
        if ($prev !== null) { $this->db->db_debug = false; }

        if ($this->db->table_exists('billing_transactions')) {
            if (!$this->_col_exists('billing_transactions', 'price_source')) {
                $this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `price_source` VARCHAR(30) DEFAULT NULL");
            }
            if (!$this->_col_exists('billing_transactions', 'pricing_pct')) {
                $this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `pricing_pct` DECIMAL(5,2) DEFAULT 0.00");
            }
            if (!$this->_col_exists('billing_transactions', 'original_unit_price')) {
                $this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `original_unit_price` DECIMAL(18,2) DEFAULT NULL");
            }
        }

        if ($this->db->table_exists('iop_billing_t')) {
            if (!$this->_col_exists('iop_billing_t', 'price_source')) {
                $this->db->query("ALTER TABLE `iop_billing_t` ADD COLUMN `price_source` VARCHAR(30) DEFAULT NULL");
            }
            if (!$this->_col_exists('iop_billing_t', 'pricing_pct')) {
                $this->db->query("ALTER TABLE `iop_billing_t` ADD COLUMN `pricing_pct` DECIMAL(5,2) DEFAULT 0.00");
            }
            if (!$this->_col_exists('iop_billing_t', 'original_unit_price')) {
                $this->db->query("ALTER TABLE `iop_billing_t` ADD COLUMN `original_unit_price` DECIMAL(18,2) DEFAULT NULL");
            }
        }

        if ($prev !== null) { $this->db->db_debug = $prev; }
    }

    private function _ensure_pricing_provenance_columns()
    {
        $prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
        if ($prev !== null) { $this->db->db_debug = false; }

        foreach (array('billing_transactions', 'iop_billing_t') as $table) {
            if (!$this->db->table_exists($table)) { continue; }
            if (!$this->_col_exists($table, 'pricing_source_id')) {
                $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `pricing_source_id` VARCHAR(64) DEFAULT NULL");
            }
            if (!$this->_col_exists($table, 'resolved_drug_id')) {
                $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `resolved_drug_id` INT(11) DEFAULT NULL");
            }
            if (!$this->_col_exists($table, 'resolved_stock_id')) {
                $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `resolved_stock_id` INT(11) DEFAULT NULL");
            }
            if (!$this->_col_exists($table, 'substitution_flag')) {
                $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `substitution_flag` TINYINT(1) NOT NULL DEFAULT 0");
            }
        }

        if ($prev !== null) { $this->db->db_debug = $prev; }
    }

    private function _col_exists($table, $col)
    {
        $q = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $this->db->escape($col));
        return ($q && $q->num_rows() > 0);
    }

    /* ============================================================
     * PUBLIC API
     * ============================================================ */

    /**
     * Resolve the canonical price for a single charge.
     *
     * @param array $ctx
     *   Required:
     *     item_type   string  one of ITEM_* constants (or legacy strings; will be normalized)
     *     item_id     int     catalog primary key (particular_id, drug_id, etc.)
     *   Optional:
     *     patient_no  string  used to derive payer_type and company_id
     *     payer_type  string  CASH | NHIS | INSURANCE | COMPANY (auto-derived from patient_no if absent)
     *     quantity    float   default 1
     *     submitted_unit_price float  client-posted price (for tamper detection only)
     *     context     array   free-form metadata for audit (source_module, source_ref, etc.)
     *
     * @return array {
     *   ok               bool
     *   error            string|null
     *   item_type        string
     *   item_id          int
     *   item_name        string
     *   quantity         float
     *   unit_price       float    (final, authoritative)
     *   gross            float    (= quantity * unit_price)
     *   discount         float    (0 in step 1; reserved for future rules)
     *   tax              float    (0 in step 1)
     *   net              float    (= gross - discount + tax)
     *   payer_type       string
     *   nhis_covered     bool
     *   company_id       int|null
     *   pricing_pct      float
     *   price_source     string   one of SOURCE_* constants
     *   original_unit_price float (catalog base price BEFORE adjustments)
     *   audit            array    structured audit trail
     *   tamper_detected  bool     true when submitted_unit_price diverges from authoritative
     * }
     */
    public function resolve(array $ctx)
    {
        $item_type = isset($ctx['item_type']) ? strtoupper(trim((string)$ctx['item_type'])) : '';
        $item_id   = isset($ctx['item_id'])   ? (int)$ctx['item_id'] : 0;
        $quantity  = isset($ctx['quantity'])  ? (float)$ctx['quantity'] : 1.0;
        if ($quantity <= 0) { $quantity = 1.0; }
        $patient_no = isset($ctx['patient_no']) ? trim((string)$ctx['patient_no']) : '';
        $submitted  = isset($ctx['submitted_unit_price']) ? (float)$ctx['submitted_unit_price'] : null;
        $require_positive_price = !empty($ctx['require_positive_price']);
        $allow_zero_price = !empty($ctx['allow_zero_price']) || !empty($ctx['explicitly_free']);

        if ($item_type === '' || $item_id <= 0) {
            return $this->_error('item_type and item_id are required', $ctx);
        }

        // Normalize legacy item_type aliases
        $item_type = $this->_normalize_item_type($item_type);

        // 1. Resolve payer_type
        $payer_type = isset($ctx['payer_type']) ? strtoupper(trim((string)$ctx['payer_type'])) : '';
        if ($payer_type === '' && $patient_no !== '') {
            $this->load->model('app/billing_model');
            if (method_exists($this->billing_model, 'determine_payer_type')) {
                $payer_type = strtoupper(trim((string)$this->billing_model->determine_payer_type($patient_no)));
            }
        }
        if (!in_array($payer_type, array('CASH', 'NHIS', 'INSURANCE', 'COMPANY'), true)) {
            $payer_type = 'CASH';
        }

        $cache_key = implode('|', array($item_type, $item_id, $payer_type, $patient_no));
        if (isset($this->_price_cache[$cache_key])) {
            $priced = $this->_price_cache[$cache_key];
            $catalog_base = (float)$priced['catalog_base'];
            $item_name = (string)$priced['item_name'];
            $unit_price = (float)$priced['unit_price'];
            $price_source = (string)$priced['price_source'];
            $nhis_covered = (bool)$priced['nhis_covered'];
            $company_id = $priced['company_id'];
            $pricing_pct = (float)$priced['pricing_pct'];
        } else {
            // 2. Look up catalog row (item_name + base price + nhis flags)
            $lookup = $this->_lookup_catalog($item_type, $item_id);
            if (!$lookup['ok']) {
                return $this->_error($lookup['error'], $ctx);
            }
            $catalog_base = (float)$lookup['base_price'];
            $item_name    = (string)$lookup['item_name'];
            $is_nhis_covered = !empty($lookup['is_nhis_covered']);
            $nhis_rate    = isset($lookup['nhis_price']) ? (float)$lookup['nhis_price'] : 0.0;

            $unit_price   = $catalog_base;
            $price_source = self::SOURCE_CATALOG;
            $nhis_covered = false;
            $company_id   = null;
            $pricing_pct  = 0.0;

            // 3. NHIS path (highest priority)
            if ($payer_type === 'NHIS' && $is_nhis_covered) {
                $unit_price   = ($nhis_rate > 0) ? $nhis_rate : $catalog_base;
                $price_source = self::SOURCE_NHIS_TARIFF;
                $nhis_covered = true;
            }
            // 4. Company / Insurance pricing_percentage
            elseif ($payer_type === 'COMPANY' || $payer_type === 'INSURANCE') {
                $cinfo = $this->_resolve_company_pricing($patient_no);
                $company_id  = $cinfo['company_id'];
                $pricing_pct = (float)$cinfo['pricing_pct'];
                if ($pricing_pct != 0.0) {
                    $unit_price   = $catalog_base * (1 + ($pricing_pct / 100.0));
                    $price_source = self::SOURCE_COMPANY_ADJUSTED;
                }
            }

            $this->_price_cache[$cache_key] = array(
                'catalog_base' => $catalog_base,
                'item_name' => $item_name,
                'unit_price' => $unit_price,
                'price_source' => $price_source,
                'nhis_covered' => $nhis_covered,
                'company_id' => $company_id,
                'pricing_pct' => $pricing_pct
            );
        }

        // Round to 2dp
        $unit_price = round($unit_price, 2);
        if ($require_positive_price && !$allow_zero_price && $unit_price <= 0.009) {
            return $this->_error('PRICE_NOT_FOUND', $ctx);
        }
        $gross      = round($quantity * $unit_price, 2);
        $discount   = 0.0;
        $tax        = 0.0;
        $net        = round($gross - $discount + $tax, 2);

        // 5. Tamper detection (non-blocking in Step 1)
        $tamper_detected = false;
        if ($submitted !== null) {
            if (abs($submitted - $unit_price) > 0.01) {
                $tamper_detected = true;
                $this->_log_tamper($ctx, $item_type, $item_id, $item_name, $submitted, $unit_price, $price_source);
            }
        }

        return array(
            'ok'                  => true,
            'error'               => null,
            'item_type'           => $item_type,
            'item_id'             => $item_id,
            'item_name'           => $item_name,
            'quantity'            => $quantity,
            'unit_price'          => $unit_price,
            'gross'               => $gross,
            'discount'            => $discount,
            'tax'                 => $tax,
            'net'                 => $net,
            'payer_type'          => $payer_type,
            'nhis_covered'        => $nhis_covered,
            'company_id'          => $company_id,
            'pricing_pct'         => $pricing_pct,
            'price_source'        => $price_source,
            'source'              => $price_source,
            'source_id'           => (string)$item_id,
            'pricing_source_id'   => (string)$item_id,
            'resolved_drug_id'    => ($item_type === self::ITEM_DRUG) ? (int)$item_id : null,
            'resolved_stock_id'   => null,
            'original_unit_price' => round($catalog_base, 2),
            'audit'               => array(
                'resolved_at' => date('Y-m-d H:i:s'),
                'engine'      => 'Price_engine_model@v1',
            ),
            'tamper_detected'     => $tamper_detected,
        );
    }

    /**
     * Legacy NHIS-or-catalog resolver.
     *
     * Used by `billing_model::getNhisServiceRate` / `getNhisDrugRate`
     * (and any other legacy AJAX endpoint) that historically returned
     * the catalog rate unless the patient is on NHIS and the item is
     * NHIS-covered. Company `pricing_percentage` is intentionally NOT
     * applied here — that consolidation is Step 9 and will be made
     * by switching call sites to `resolve()` directly. Until then,
     * this helper preserves the legacy contract precisely.
     *
     * @param string $item_type   ITEM_DRUG / ITEM_SERVICE / etc.
     * @param int    $item_id     catalog primary key
     * @param string|null $patient_no  if null/blank, returns catalog
     * @return array {
     *   ok:           bool,
     *   item_name:    string,
     *   catalog_base: float,
     *   effective:    float,   (catalog_base unless NHIS-covered for an NHIS patient)
     *   nhis_covered: bool,
     *   raw:          stdClass|null  (catalog row, for legacy callers that need other fields)
     * }
     */
    public function resolve_legacy_rate($item_type, $item_id, $patient_no = null)
    {
        $item_type = $this->_normalize_item_type(strtoupper(trim((string)$item_type)));
        $item_id   = (int)$item_id;
        if ($item_id <= 0) {
            return array('ok' => false, 'item_name' => null, 'catalog_base' => 0.0,
                         'effective' => 0.0, 'nhis_covered' => false, 'raw' => null);
        }

        // Fetch raw catalog row (legacy callers expect specific fields).
        $this->load->model('app/billing_model');
        if ($item_type === self::ITEM_DRUG) {
            $this->billing_model->ensure_nhis_drug_columns();
            $select = 'drug_id,drug_name,nPrice,nStock,re_order_level,is_nhis_covered,nhis_price,cash_price';
            if ($this->db->field_exists('expiry_date', 'medicine_drug_name')) {
                $select .= ',expiry_date';
            }
            $this->db->select($select, false);
            $raw = $this->db->get_where('medicine_drug_name', array('drug_id' => $item_id))->row();
        } else {
            $this->billing_model->ensure_nhis_service_columns();
            $this->db->select('particular_id,particular_name,charge_amount,is_nhis_covered,nhis_charge_amount', false);
            $raw = $this->db->get_where('bill_particular', array('particular_id' => $item_id))->row();
        }
        if (!$raw) {
            return array('ok' => false, 'item_name' => null, 'catalog_base' => 0.0,
                         'effective' => 0.0, 'nhis_covered' => false, 'raw' => null);
        }

        // Resolve effective rate via engine, but force CASH when patient is not NHIS
        // so company pricing is not applied here (legacy contract).
        $payer_type = 'CASH';
        $patient_no = ($patient_no !== null) ? trim((string)$patient_no) : '';
        if ($patient_no !== '') {
            $derived = strtoupper(trim((string)$this->billing_model->determine_payer_type($patient_no)));
            if ($derived === 'NHIS') {
                $payer_type = 'NHIS';
            }
        }

        $res = $this->resolve(array(
            'item_type'  => $item_type,
            'item_id'    => $item_id,
            'patient_no' => $patient_no,
            'payer_type' => $payer_type,
            'quantity'   => 1,
        ));

        if ($item_type === self::ITEM_DRUG) {
            $catalog_base = (float)$raw->nPrice;
            if ($catalog_base <= 0 && isset($raw->cash_price)) {
                $catalog_base = (float)$raw->cash_price;
            }
            $name = (string)$raw->drug_name;
        } else {
            $catalog_base = (float)$raw->charge_amount;
            $name = (string)$raw->particular_name;
        }

        return array(
            'ok'           => !empty($res['ok']),
            'item_name'    => $name,
            'catalog_base' => round($catalog_base, 2),
            'effective'    => !empty($res['ok']) ? (float)$res['unit_price'] : $catalog_base,
            'nhis_covered' => !empty($res['nhis_covered']),
            'raw'          => $raw,
        );
    }

    /**
     * Convenience: apply company pricing to a base amount only.
     * Used by legacy callers (insurance_company_model, pharmacy_model,
     * Billing_master_model) so they no longer carry their own copies.
     *
     * Returns:
     *   base_amount, adjusted_amount, percentage_applied, difference, company_id
     */
    public function apply_company_pricing($base_amount, $company_id)
    {
        $base = (float)$base_amount;
        $pct  = $this->_get_pricing_pct_for_company((int)$company_id);
        $adj  = round($base * (1 + ($pct / 100.0)), 2);
        return array(
            'base_amount'        => $base,
            'adjusted_amount'    => $adj,
            'percentage_applied' => (float)$pct,
            'difference'         => round($adj - $base, 2),
            'company_id'         => (int)$company_id,
        );
    }

    /* ============================================================
     * INTERNAL HELPERS
     * ============================================================ */

    private function _normalize_item_type($t)
    {
        $map = array(
            'PARTICULAR' => self::ITEM_SERVICE,
            'BILL'       => self::ITEM_SERVICE,
            'SONOGRAPHY' => self::ITEM_IMAGING,
            'LAB'        => self::ITEM_LAB_TEST,
            'LABORATORY' => self::ITEM_LAB_TEST,
            'PHARMACY'   => self::ITEM_DRUG,
            'MEDICATION' => self::ITEM_DRUG,
        );
        return isset($map[$t]) ? $map[$t] : $t;
    }

    private function _lookup_catalog($item_type, $item_id)
    {
        $this->load->model('app/billing_model');

        if ($item_type === self::ITEM_DRUG) {
            $this->billing_model->ensure_nhis_drug_columns();
            $select = 'drug_id, drug_name, nPrice, cash_price, is_nhis_covered, nhis_price';
            $this->db->select($select, false);
            $row = $this->db->get_where('medicine_drug_name', array('drug_id' => $item_id))->row();
            if (!$row) {
                return array('ok' => false, 'error' => 'Drug not found: ' . $item_id);
            }
            $base = (float)$row->nPrice;
            if ($base <= 0 && isset($row->cash_price)) { $base = (float)$row->cash_price; }
            return array(
                'ok'              => true,
                'item_name'       => (string)$row->drug_name,
                'base_price'      => $base,
                'is_nhis_covered' => isset($row->is_nhis_covered) ? (int)$row->is_nhis_covered : 0,
                'nhis_price'      => isset($row->nhis_price) ? (float)$row->nhis_price : 0.0,
            );
        }

        if ($item_type === self::ITEM_LAB_TEST) {
            $this->billing_model->ensure_nhis_service_columns();
            $bp_id = 0;
            $catalog_name = null;
            $catalog = null;
            if ($this->db->table_exists('ghs_lab_tests')) {
                $this->db->select('test_name, particular_id, is_nhis_covered, nhis_price, price', false);
                $catalog = $this->db->get_where('ghs_lab_tests', array('test_id' => $item_id, 'is_active' => 1, 'InActive' => 0))->row();
                if ($catalog) {
                    $catalog_name = isset($catalog->test_name) ? (string)$catalog->test_name : null;
                    $bp_id = isset($catalog->particular_id) ? (int)$catalog->particular_id : 0;
                }
            }
            if ($bp_id <= 0) {
                $bp_id = (int)$item_id;
            }

            if ($this->db->table_exists('bill_particular') && $bp_id > 0) {
                $this->db->select('particular_id, particular_name, charge_amount, is_nhis_covered, nhis_charge_amount', false);
                $bp = $this->db->get_where('bill_particular', array('particular_id' => $bp_id, 'InActive' => 0))->row();
                if ($bp) {
                    $name = $catalog_name;
                    if ($name === null || $name === '') {
                        $name = isset($bp->particular_name) ? (string)$bp->particular_name : ('Lab Test #' . $item_id);
                    }
                    return array(
                        'ok'              => true,
                        'item_name'       => $name,
                        'base_price'      => isset($bp->charge_amount) ? (float)$bp->charge_amount : 0.0,
                        'is_nhis_covered' => isset($bp->is_nhis_covered) ? (int)$bp->is_nhis_covered : 0,
                        'nhis_price'      => isset($bp->nhis_charge_amount) ? (float)$bp->nhis_charge_amount : 0.0,
                    );
                }
            }

            // Backward-compatible fallback: use catalog pricing only when bill_particular is missing/unlinked.
            if ($this->db->table_exists('ghs_lab_tests')) {
                if (!$catalog) {
                    $this->db->select('test_name, price, nhis_price, is_nhis_covered', false);
                    $catalog = $this->db->get_where('ghs_lab_tests', array('particular_id' => $item_id, 'is_active' => 1, 'InActive' => 0))->row();
                }
                if ($catalog) {
                    log_message('warning', 'Price_engine_model: fallback to ghs_lab_tests pricing for lab item_id ' . (int)$item_id);
                    return array(
                        'ok'              => true,
                        'item_name'       => isset($catalog->test_name) ? (string)$catalog->test_name : ('Lab Test #' . $item_id),
                        'base_price'      => isset($catalog->price) ? (float)$catalog->price : 0.0,
                        'is_nhis_covered' => isset($catalog->is_nhis_covered) ? (int)$catalog->is_nhis_covered : 0,
                        'nhis_price'      => isset($catalog->nhis_price) ? (float)$catalog->nhis_price : 0.0,
                    );
                }
            }
        }

        if ($item_type === 'RADIOLOGY' && $this->db->table_exists('radiology_test_master')) {
            $this->db->select('test_name, price, nhis_price, is_nhis_covered, status, InActive', false);
            $row = $this->db->get_where('radiology_test_master', array('id' => $item_id, 'InActive' => 0))->row();
            if ($row) {
                $st = isset($row->status) ? strtolower(trim((string)$row->status)) : 'active';
                if ($st !== 'inactive') {
                    return array(
                        'ok'              => true,
                        'item_name'       => isset($row->test_name) ? (string)$row->test_name : ('Radiology #' . $item_id),
                        'base_price'      => isset($row->price) ? (float)$row->price : 0.0,
                        'is_nhis_covered' => isset($row->is_nhis_covered) ? (int)$row->is_nhis_covered : 0,
                        'nhis_price'      => isset($row->nhis_price) ? (float)$row->nhis_price : 0.0,
                    );
                }
            }
        }

        if ($item_type === self::ITEM_IMAGING && $this->db->table_exists('ghs_sonography_tests')) {
            $this->db->select('test_name, price, nhis_price, is_nhis_covered', false);
            $row = $this->db->get_where('ghs_sonography_tests', array('test_id' => $item_id, 'is_active' => 1, 'InActive' => 0))->row();
            if (!$row) {
                $this->db->select('test_name, price, nhis_price, is_nhis_covered', false);
                $row = $this->db->get_where('ghs_sonography_tests', array('particular_id' => $item_id, 'is_active' => 1, 'InActive' => 0))->row();
            }
            if ($row) {
                return array(
                    'ok'              => true,
                    'item_name'       => isset($row->test_name) ? (string)$row->test_name : ('Sonography #' . $item_id),
                    'base_price'      => isset($row->price) ? (float)$row->price : 0.0,
                    'is_nhis_covered' => isset($row->is_nhis_covered) ? (int)$row->is_nhis_covered : 0,
                    'nhis_price'      => isset($row->nhis_price) ? (float)$row->nhis_price : 0.0,
                );
            }
        }

        if ($item_type === self::ITEM_IMAGING && $this->db->table_exists('radiology_test_master')) {
            $this->db->select('test_name, price, nhis_price, is_nhis_covered, status, InActive', false);
            $row = $this->db->get_where('radiology_test_master', array('id' => $item_id, 'InActive' => 0))->row();
            if ($row) {
                $st = isset($row->status) ? strtolower(trim((string)$row->status)) : 'active';
                if ($st !== 'inactive') {
                    return array(
                        'ok'              => true,
                        'item_name'       => isset($row->test_name) ? (string)$row->test_name : ('Radiology #' . $item_id),
                        'base_price'      => isset($row->price) ? (float)$row->price : 0.0,
                        'is_nhis_covered' => isset($row->is_nhis_covered) ? (int)$row->is_nhis_covered : 0,
                        'nhis_price'      => isset($row->nhis_price) ? (float)$row->nhis_price : 0.0,
                    );
                }
            }
        }

        // SERVICE / LAB_TEST / IMAGING / ROOM all live in bill_particular for the legacy path
        $this->billing_model->ensure_nhis_service_columns();
        $this->db->select('particular_id, particular_name, charge_amount, is_nhis_covered, nhis_charge_amount', false);
        $row = $this->db->get_where('bill_particular', array('particular_id' => $item_id))->row();
        if (!$row) {
            return array('ok' => false, 'error' => 'Service item not found: ' . $item_id);
        }
        return array(
            'ok'              => true,
            'item_name'       => (string)$row->particular_name,
            'base_price'      => (float)$row->charge_amount,
            'is_nhis_covered' => isset($row->is_nhis_covered) ? (int)$row->is_nhis_covered : 0,
            'nhis_price'      => isset($row->nhis_charge_amount) ? (float)$row->nhis_charge_amount : 0.0,
        );
    }

    private function _resolve_company_pricing($patient_no)
    {
        $out = array('company_id' => null, 'pricing_pct' => 0.0);
        if ($patient_no === '') { return $out; }
        if (!$this->db->table_exists('patient_personal_info') || !$this->db->table_exists('insurance_comp')) {
            return $out;
        }
        $this->db->select('ppi.Insurance_comp, ic.pricing_percentage');
        $this->db->from('patient_personal_info ppi');
        $this->db->join('insurance_comp ic', 'ic.in_com_id = ppi.Insurance_comp', 'left');
        $this->db->where('ppi.patient_no', $patient_no);
        $this->db->where('ppi.InActive', 0);
        $row = $this->db->get()->row();
        if ($row && isset($row->Insurance_comp)) {
            $out['company_id']  = (int)$row->Insurance_comp;
            $out['pricing_pct'] = isset($row->pricing_percentage) ? (float)$row->pricing_percentage : 0.0;
        }
        return $out;
    }

    private function _get_pricing_pct_for_company($company_id)
    {
        if ($company_id <= 0) { return 0.0; }
        if (!$this->db->table_exists('insurance_comp')) { return 0.0; }
        $this->db->select('pricing_percentage');
        $row = $this->db->get_where('insurance_comp', array('in_com_id' => $company_id))->row();
        if (!$row || !isset($row->pricing_percentage)) { return 0.0; }
        return (float)$row->pricing_percentage;
    }

    private function _error($message, array $ctx)
    {
        return array(
            'ok'                  => false,
            'error'               => $message,
            'item_type'           => isset($ctx['item_type']) ? $ctx['item_type'] : null,
            'item_id'             => isset($ctx['item_id']) ? (int)$ctx['item_id'] : 0,
            'item_name'           => null,
            'quantity'            => isset($ctx['quantity']) ? (float)$ctx['quantity'] : 1.0,
            'unit_price'          => 0.0,
            'gross'               => 0.0,
            'discount'            => 0.0,
            'tax'                 => 0.0,
            'net'                 => 0.0,
            'payer_type'          => isset($ctx['payer_type']) ? $ctx['payer_type'] : 'CASH',
            'nhis_covered'        => false,
            'company_id'          => null,
            'pricing_pct'         => 0.0,
            'price_source'        => null,
            'source'              => null,
            'source_id'           => null,
            'pricing_source_id'   => null,
            'resolved_drug_id'    => null,
            'resolved_stock_id'   => null,
            'original_unit_price' => 0.0,
            'audit'               => array(),
            'tamper_detected'     => false,
        );
    }

    private function _log_tamper(array $ctx, $item_type, $item_id, $item_name, $submitted, $authoritative, $price_source)
    {
        // Best-effort. Never breaks the request.
        if (!$this->db->table_exists('billing_audit_log')) { return; }
        $payload = array(
            'item_type'         => $item_type,
            'item_id'           => $item_id,
            'item_name'         => $item_name,
            'submitted'         => $submitted,
            'authoritative'     => $authoritative,
            'price_source'      => $price_source,
            'context'           => isset($ctx['context']) ? $ctx['context'] : null,
        );
        $row = array(
            'table_name'   => 'price_engine',
            'record_id'    => (string)$item_id,
            'action'       => 'PRICE_TAMPER_DETECTED',
            'field_name'   => 'unit_price',
            'old_value'    => (string)$submitted,
            'new_value'    => (string)$authoritative,
            'patient_no'   => isset($ctx['patient_no']) ? (string)$ctx['patient_no'] : null,
            'encounter_id' => isset($ctx['encounter_id']) ? (string)$ctx['encounter_id'] : null,
            'performed_by' => isset($ctx['user_id']) ? (string)$ctx['user_id'] : null,
            'performed_at' => date('Y-m-d H:i:s'),
            'ip_address'   => $this->input ? (string)$this->input->ip_address() : null,
            'user_agent'   => $this->input ? (string)$this->input->user_agent() : null,
        );
        $prev = $this->db->db_debug; $this->db->db_debug = false;
        $this->db->insert('billing_audit_log', $row);
        $this->db->db_debug = $prev;
        // Also append a JSON breadcrumb if column exists
        if ($this->db->field_exists('details', 'billing_reconciliation_log')) {
            $prev = $this->db->db_debug; $this->db->db_debug = false;
            $this->db->insert('billing_reconciliation_log', array(
                'recon_date'   => date('Y-m-d'),
                'department'   => 'PRICING',
                'issue_type'   => 'PRICE_TAMPER_DETECTED',
                'record_ref'   => $item_type . ':' . $item_id,
                'patient_no'   => isset($ctx['patient_no']) ? (string)$ctx['patient_no'] : null,
                'encounter_id' => isset($ctx['encounter_id']) ? (string)$ctx['encounter_id'] : null,
                'details'      => json_encode($payload),
                'resolved'     => 0,
                'created_at'   => date('Y-m-d H:i:s'),
            ));
            $this->db->db_debug = $prev;
        }
    }
}
