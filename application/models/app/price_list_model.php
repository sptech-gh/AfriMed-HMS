<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Price List Model
 * Unified access to ALL billing prices across the system
 */
class Price_list_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

	private function _lab_group_names()
	{
		return array('HAEMATOLOGY', 'BIOCHEMISTRY', 'CLINICAL PATHOLOGY', 'MICROBIOLOGY',
			'SEROLOGY', 'SPECIAL TESTS', 'HISTOPATHOLOGY', 'TRANSFUSION MEDICINE');
	}

	private function _resolve_or_create_lab_group_id()
	{
		if (!$this->db->table_exists('bill_group_name')) {
			return null;
		}
		$lab_groups = $this->_lab_group_names();
		$this->db->select('group_id, group_name');
		$this->db->where('InActive', 0);
		$this->db->where_in('group_name', $lab_groups);
		$this->db->order_by('group_id', 'ASC');
		$row = $this->db->get('bill_group_name', 1)->row();
		if ($row && isset($row->group_id)) {
			return (int)$row->group_id;
		}
		return null;
	}

    /**
     * Get all prices from all sources
     */
    public function get_all_prices($category = 'all', $search = '')
    {
        $prices = array();

        // 1. Services from bill_particular
        if ($category === 'all' || $category === 'services') {
            $services = $this->_get_service_prices($search);
            $prices = array_merge($prices, $services);
        }

        // 2. Medicines from medicine_drug_name
        if ($category === 'all' || $category === 'medicines') {
            $medicines = $this->_get_medicine_prices($search);
            $prices = array_merge($prices, $medicines);
        }

        // 3. Rooms from room_master
        if ($category === 'all' || $category === 'rooms') {
            $rooms = $this->_get_room_prices($search);
            $prices = array_merge($prices, $rooms);
        }

        // 4. Sonography from sonography_items
        if ($category === 'all' || $category === 'sonography') {
            $sonography = $this->_get_sonography_prices($search);
            $prices = array_merge($prices, $sonography);
        }

        // 5. Lab Tests from ghs_lab_tests
        if ($category === 'all' || $category === 'laboratory') {
            $labs = $this->_get_lab_test_prices($search);
            $prices = array_merge($prices, $labs);
        }

        // Sort by category then name
        usort($prices, function($a, $b) {
            $cat = strcmp($a->category_name, $b->category_name);
            if ($cat !== 0) return $cat;
            return strcmp($a->item_name, $b->item_name);
        });

        return $prices;
    }

    /**
     * Get service prices from bill_particular
     */
    private function _get_service_prices($search = '')
    {
		$lab_groups = $this->_lab_group_names();
        $this->db->select("
            bp.particular_id as item_id,
            'service' as item_type,
            bp.particular_name as item_name,
            bp.particular_desc as description,
            COALESCE(bg.group_name, 'Uncategorized') as category_name,
            bp.charge_amount as cash_price,
            COALESCE(bp.nhis_charge_amount, 0) as nhis_price,
            COALESCE(bp.is_nhis_covered, 0) as is_nhis_covered
        ");
        $this->db->from('bill_particular bp');
		$this->db->join('bill_group_name bg', 'bg.group_id = bp.group_id AND bg.InActive = 0', 'left');
        $this->db->where('bp.InActive', 0);
		// Exclude lab test groups from Services to avoid duplicates and category confusion.
		$this->db->group_start();
		$this->db->where_not_in('bg.group_name', $lab_groups);
		$this->db->or_where('bg.group_name IS NULL', null, false);
		$this->db->group_end();
        
        if ($search) {
            $this->db->group_start();
            $this->db->like('bp.particular_name', $search);
            $this->db->or_like('bg.group_name', $search);
            $this->db->group_end();
        }
        
        $this->db->order_by('bg.group_name, bp.particular_name');
        return $this->db->get()->result();
    }

    /**
     * Get medicine prices from medicine_drug_name
     */
    private function _get_medicine_prices($search = '')
    {
        $this->db->select("
            d.drug_id as item_id,
            'medicine' as item_type,
            d.drug_name as item_name,
            d.drug_desc as description,
            COALESCE(c.med_category_name, 'Medicines') as category_name,
            COALESCE(d.nPrice, 0) as cash_price,
            COALESCE(d.nhis_price, 0) as nhis_price,
            COALESCE(d.is_nhis_covered, 0) as is_nhis_covered
        ");
        $this->db->from('medicine_drug_name d');
        $this->db->join('medicine_category c', 'c.cat_id = d.med_cat_id', 'left');
        $this->db->where('d.InActive', 0);
        
        if ($search) {
            $this->db->group_start();
            $this->db->like('d.drug_name', $search);
            if ($this->_column_exists('medicine_drug_name', 'generic_name')) {
                $this->db->or_like('d.generic_name', $search);
            }
            $this->db->or_like('c.med_category_name', $search);
            $this->db->group_end();
        }
        
        $this->db->order_by('c.med_category_name, d.drug_name');
        return $this->db->get()->result();
    }

    /**
     * Get room prices from room_master
     */
    private function _get_room_prices($search = '')
    {
        $this->db->select("
            r.room_master_id as item_id,
            'room' as item_type,
            r.room_name as item_name,
            CONCAT('Floor: ', COALESCE(f.floor_name, 'N/A')) as description,
            COALESCE(c.category_name, 'Rooms') as category_name,
            COALESCE(r.room_rates, 0) as cash_price,
            COALESCE(r.room_rates, 0) as nhis_price,
            0 as is_nhis_covered
        ");
        $this->db->from('room_master r');
        $this->db->join('room_category c', 'c.category_id = r.category_id', 'left');
        $this->db->join('floor f', 'f.floor_id = r.floor', 'left');
        $this->db->where('r.InActive', 0);
        
        if ($search) {
            $this->db->group_start();
            $this->db->like('r.room_name', $search);
            $this->db->or_like('c.category_name', $search);
            $this->db->group_end();
        }
        
        $this->db->order_by('c.category_name, r.room_name');
        return $this->db->get()->result();
    }

    /**
     * Get sonography prices
     */
    private function _get_sonography_prices($search = '')
    {
        if (!$this->db->table_exists('sonography_items')) {
            return array();
        }

        // Check which columns exist in sonography_items
        $fields = $this->db->list_fields('sonography_items');
        $has_bill_particular_id = in_array('bill_particular_id', $fields);
        $has_charge_amount = in_array('charge_amount', $fields);
        $has_nhis_rate = in_array('nhis_rate', $fields);
        $has_nhis_flag = in_array('is_nhis_covered', $fields);
        $cash_expr = '0';
        if ($has_bill_particular_id && $has_charge_amount) {
            $cash_expr = "(CASE WHEN (s.bill_particular_id IS NOT NULL AND s.bill_particular_id > 0) THEN COALESCE(bp.charge_amount, 0) ELSE COALESCE(s.charge_amount, 0) END)";
        } elseif ($has_bill_particular_id) {
            $cash_expr = "COALESCE(bp.charge_amount, 0)";
        } elseif ($has_charge_amount) {
            $cash_expr = "COALESCE(s.charge_amount, 0)";
        }
        
        $this->db->select("
            s.item_id as item_id,
            'sonography' as item_type,
            s.item_name as item_name,
            '' as description,
            'Sonography' as category_name,
            " . $cash_expr . " as cash_price,
            " . ($has_nhis_rate ? "COALESCE(s.nhis_rate, 0)" : "0") . " as nhis_price,
            " . ($has_nhis_flag ? "COALESCE(s.is_nhis_covered, 0)" : "0") . " as is_nhis_covered
        ");
        $this->db->from('sonography_items s');
        if ($has_bill_particular_id) {
            $this->db->join('bill_particular bp', 'bp.particular_id = s.bill_particular_id', 'left');
        }
        $this->db->where('s.InActive', 0);
        
        if ($search) {
            $this->db->like('s.item_name', $search);
        }
        
        $this->db->order_by('s.item_name');
        return $this->db->get()->result();
    }

    /**
     * Get lab test prices from ghs_lab_tests
     */
    private function _get_lab_test_prices($search = '')
    {
		if (!$this->db->table_exists('bill_particular') || !$this->db->table_exists('bill_group_name')) {
			return array();
		}

		$lab_groups = $this->_lab_group_names();
		$this->db->select("bp.particular_id as item_id,
			'lab_test' as item_type,
			bp.particular_name as item_name,
			'' as description,
			COALESCE(bgn.group_name, 'Laboratory') as category_name,
			COALESCE(bp.charge_amount, 0) as cash_price,
			COALESCE(bp.nhis_charge_amount, 0) as nhis_price,
			COALESCE(bp.is_nhis_covered, 0) as is_nhis_covered", false);
		$this->db->from('bill_particular bp');
		$this->db->join('bill_group_name bgn', 'bgn.group_id = bp.group_id', 'left');
		$this->db->where('bp.InActive', 0);
		$this->db->where('bgn.InActive', 0);
		$this->db->where_in('bgn.group_name', $lab_groups);

		if ($search) {
			$this->db->group_start();
			$this->db->like('bp.particular_name', $search);
			$this->db->or_like('bgn.group_name', $search);
			$this->db->group_end();
		}

		$this->db->order_by('bgn.group_name, bp.particular_name');
		return $this->db->get()->result();
    }

    /**
     * Get available categories
     */
    public function get_categories()
    {
        return array(
            'all' => 'All Categories',
            'services' => 'Services & Procedures',
            'medicines' => 'Medicines & Drugs',
            'laboratory' => 'Laboratory Tests',
            'sonography' => 'Sonography/Imaging',
            'rooms' => 'Room Charges'
        );
    }

    /**
     * Get price summary statistics
     */
    public function get_price_summary()
    {
        $summary = array(
            'total_items' => 0,
            'services' => 0,
            'medicines' => 0,
            'rooms' => 0,
            'sonography' => 0,
            'laboratory' => 0,
            'nhis_covered' => 0,
            'zero_price' => 0
        );

		// Count services (exclude laboratory bill_particular entries)
		$summary['services'] = 0;
		if ($this->db->table_exists('bill_particular') && $this->db->table_exists('bill_group_name')) {
			$lab_groups = $this->_lab_group_names();
			$this->db->from('bill_particular bp');
			$this->db->join('bill_group_name bg', 'bg.group_id = bp.group_id AND bg.InActive = 0', 'left');
			$this->db->where('bp.InActive', 0);
			$this->db->group_start();
			$this->db->where_not_in('bg.group_name', $lab_groups);
			$this->db->or_where('bg.group_name IS NULL', null, false);
			$this->db->group_end();
			$summary['services'] = (int)$this->db->count_all_results();
		} elseif ($this->db->table_exists('bill_particular')) {
			$summary['services'] = (int)$this->db->where('InActive', 0)->count_all_results('bill_particular');
		}
        
        // Count medicines
        $summary['medicines'] = $this->db->where('InActive', 0)->count_all_results('medicine_drug_name');
        
        // Count rooms
        $summary['rooms'] = $this->db->where('InActive', 0)->count_all_results('room_master');
        
        // Count sonography
        if ($this->db->table_exists('sonography_items')) {
            $summary['sonography'] = $this->db->where('InActive', 0)->count_all_results('sonography_items');
        }
        
        // Count lab tests
		if ($this->db->table_exists('bill_particular') && $this->db->table_exists('bill_group_name')) {
			$lab_groups = $this->_lab_group_names();
			$this->db->from('bill_particular bp');
			$this->db->join('bill_group_name bg', 'bg.group_id = bp.group_id', 'inner');
			$this->db->where('bp.InActive', 0);
			$this->db->where('bg.InActive', 0);
			$this->db->where_in('bg.group_name', $lab_groups);
			$summary['laboratory'] = (int)$this->db->count_all_results();
		}

		$summary['total_items'] = $summary['services'] + $summary['medicines'] + $summary['rooms'] +
							   $summary['sonography'] + $summary['laboratory'];

        // Count NHIS covered items
        $nhis_services = $this->db->where('InActive', 0)->where('is_nhis_covered', 1)->count_all_results('bill_particular');
        $nhis_medicines = 0;
        if ($this->_column_exists('medicine_drug_name', 'is_nhis_covered')) {
            $nhis_medicines = $this->db->where('InActive', 0)->where('is_nhis_covered', 1)->count_all_results('medicine_drug_name');
        }
        $summary['nhis_covered'] = $nhis_services + $nhis_medicines;

        // Count zero price items
        $zero_services = $this->db->where('InActive', 0)->where('charge_amount', 0)->count_all_results('bill_particular');
        $zero_medicines = $this->db->where('InActive', 0)->where('nPrice', 0)->count_all_results('medicine_drug_name');
        $summary['zero_price'] = $zero_services + $zero_medicines;

        return $summary;
    }

    /**
     * Update a single price
     */
    public function update_price($item_type, $item_id, $cash_price = null, $nhis_price = null)
    {
        if (function_exists('is_admin_role') && !is_admin_role()) {
            return array('success' => false, 'affected' => 0);
        }
        $data = array();
        $affected_total = 0;
        $success = true;
        
        switch ($item_type) {
            case 'service':
                if ($cash_price !== null) $data['charge_amount'] = $cash_price;
                if ($nhis_price !== null) $data['nhis_charge_amount'] = $nhis_price;
                if (!empty($data)) {
                    $this->db->where('particular_id', $item_id)->update('bill_particular', $data);
                    $affected_total += $this->db->affected_rows();
                }
                break;
                
            case 'medicine':
                if ($cash_price !== null) $data['nPrice'] = $cash_price;
                if ($nhis_price !== null && $this->_column_exists('medicine_drug_name', 'nhis_price')) {
                    $data['nhis_price'] = $nhis_price;
                }
                if (!empty($data)) {
                    $this->db->where('drug_id', $item_id)->update('medicine_drug_name', $data);
                    $affected_total += $this->db->affected_rows();
                }
                break;
                
            case 'room':
                if ($cash_price !== null) $data['room_rates'] = $cash_price;
                if (!empty($data)) {
                    $this->db->where('room_master_id', $item_id)->update('room_master', $data);
                    $affected_total += $this->db->affected_rows();
                }
                break;
                
            case 'sonography':
                $hasSonoCharge = $this->_column_exists('sonography_items', 'charge_amount');
                $hasSonoBpId = $this->_column_exists('sonography_items', 'bill_particular_id');
                if ($cash_price !== null) {
                    if ($hasSonoCharge) {
                        $this->db->where('item_id', $item_id)->update('sonography_items', array('charge_amount' => $cash_price));
                        $affected_total += $this->db->affected_rows();
                    } elseif ($hasSonoBpId) {
                        $si = $this->db->select('bill_particular_id')->get_where('sonography_items', array('item_id' => $item_id))->row();
                        $bpId = ($si && isset($si->bill_particular_id)) ? (int)$si->bill_particular_id : 0;
                        if ($bpId > 0) {
                            $this->db->where('particular_id', $bpId)->update('bill_particular', array('charge_amount' => $cash_price));
                            $affected_total += $this->db->affected_rows();
                        } else {
                            $success = false;
                        }
                    } else {
                        $success = false;
                    }
                }
                if ($nhis_price !== null && $this->_column_exists('sonography_items', 'nhis_rate')) {
                    $this->db->where('item_id', $item_id)->update('sonography_items', array('nhis_rate' => $nhis_price));
                    $affected_total += $this->db->affected_rows();
                }
                break;

            case 'lab_test':
                if ($cash_price !== null && $this->db->table_exists('bill_particular')) {
                    $this->db->where('particular_id', $item_id)->update('bill_particular', array('charge_amount' => $cash_price));
                    $affected_total += $this->db->affected_rows();
                }
                if ($nhis_price !== null && $this->db->table_exists('bill_particular') && $this->_column_exists('bill_particular', 'nhis_charge_amount')) {
                    $this->db->where('particular_id', $item_id)->update('bill_particular', array('nhis_charge_amount' => $nhis_price));
                    $affected_total += $this->db->affected_rows();
                }
                break;
        }

        return array('success' => (bool)$success, 'affected' => (int)$affected_total);
    }

    /**
     * Apply percentage adjustment to prices
     */
    public function apply_percentage_adjustment($category, $adjustment_type, $percentage, $price_type)
    {
        if (function_exists('is_admin_role') && !is_admin_role()) {
            return 0;
        }
        $multiplier = $adjustment_type === 'increase' 
            ? (1 + ($percentage / 100)) 
            : (1 - ($percentage / 100));
        
        $count = 0;

        // Apply to services
        if ($category === 'all' || $category === 'services') {
            if ($price_type === 'cash' || $price_type === 'both') {
                $this->db->query("UPDATE bill_particular SET charge_amount = ROUND(charge_amount * ?, 2) WHERE InActive = 0", array($multiplier));
                $count += $this->db->affected_rows();
            }
            if ($price_type === 'nhis' || $price_type === 'both') {
                $this->db->query("UPDATE bill_particular SET nhis_charge_amount = ROUND(nhis_charge_amount * ?, 2) WHERE InActive = 0 AND is_nhis_covered = 1", array($multiplier));
                $count += $this->db->affected_rows();
            }
        }

        // Apply to medicines
        if ($category === 'all' || $category === 'medicines') {
            if ($price_type === 'cash' || $price_type === 'both') {
                $this->db->query("UPDATE medicine_drug_name SET nPrice = ROUND(nPrice * ?, 2) WHERE InActive = 0", array($multiplier));
                $count += $this->db->affected_rows();
            }
            if (($price_type === 'nhis' || $price_type === 'both') && $this->_column_exists('medicine_drug_name', 'nhis_price')) {
                $this->db->query("UPDATE medicine_drug_name SET nhis_price = ROUND(nhis_price * ?, 2) WHERE InActive = 0 AND is_nhis_covered = 1", array($multiplier));
                $count += $this->db->affected_rows();
            }
        }

        // Apply to rooms
        if ($category === 'all' || $category === 'rooms') {
            if ($price_type === 'cash' || $price_type === 'both') {
                $this->db->query("UPDATE room_master SET room_rates = ROUND(room_rates * ?, 2) WHERE InActive = 0", array($multiplier));
                $count += $this->db->affected_rows();
            }
        }

        // Apply to sonography (cash price is in bill_particular linked via bill_particular_id)
        if (($category === 'all' || $category === 'sonography') && $this->db->table_exists('sonography_items')) {
            if (($price_type === 'cash' || $price_type === 'both') && $this->_column_exists('sonography_items', 'charge_amount')) {
                $this->db->query("UPDATE sonography_items SET charge_amount = ROUND(charge_amount * ?, 2) WHERE InActive = 0", array($multiplier));
                $count += $this->db->affected_rows();
            }
            if (($price_type === 'cash' || $price_type === 'both') && $this->_column_exists('sonography_items', 'bill_particular_id')) {
                $this->db->query("UPDATE bill_particular bp 
                    INNER JOIN sonography_items si ON si.bill_particular_id = bp.particular_id 
                    SET bp.charge_amount = ROUND(bp.charge_amount * ?, 2) 
                    WHERE bp.InActive = 0", array($multiplier));
                $count += $this->db->affected_rows();
            }
        }

        return $count;
    }

    /**
     * Check if column exists
     */
    private function _column_exists($table, $column)
    {
        if (!$this->db->table_exists($table)) return false;
        $fields = $this->db->list_fields($table);
        return in_array($column, $fields);
    }

	public function import_hebrew_lab_prices($user_id = null)
	{
		if (function_exists('is_admin_role') && !is_admin_role()) {
			return array('success' => false, 'inserted' => 0, 'skipped_existing' => 0, 'error' => 'Admin only');
		}
		if (!$this->db->table_exists('bill_particular')) {
			return array('success' => false, 'inserted' => 0, 'skipped_existing' => 0, 'error' => 'bill_particular table not found');
		}
		$group_id = $this->_resolve_or_create_lab_group_id();
		if ($group_id === null || $group_id <= 0) {
			return array('success' => false, 'inserted' => 0, 'skipped_existing' => 0, 'error' => 'No laboratory groups found in bill_group_name');
		}

		$items = array(
			array('DF for Malaria Parasites', 40.00),
			array('Calcium (Ca)', 350.03),
			array('2 Hour Post Prandial Blood Glucose', 120.00),
			array('Absolute Eosinophil Count (AEC)', 30.00),
			array('Alanine Aminotransferase (ALT)', 40.00),
			array('Alpha Amylase (serum/urine)', 65.00),
			array('Alpha Fetoprotein (AFP)', 85.00),
			array('Anti streptolysin test', 100.00),
			array('AP-Prothrombin Time', 50.00),
			array('Arterial Blood Gas (ABG)', 65.00),
			array('Anti-Streptolysin O (ASO) titre', 50.00),
			array('Aspartate Aminotransferase (AST)', 30.00),
			array('Blood for C/S', 150.00),
			array('Blood Grouping', 35.00),
			array('Blood Grouping & Antibody Screen', 45.00),
			array('Blood Parasites', 45.00),
			array('Blood Sugar PP', 20.00),
			array('Blood Urea', 55.00),
			array('Blood Urea & Electrolytes', 120.00),
			array('Bue & Creatinine', 125.00),
			array('C Reactive Protein', 80.00),
			array('Chloride', 45.00),
			array('Factor VIII', 30.00),
			array('Fasting Blood Sugar/Random Blood Sugar', 30.00),
			array('Follicle Stimulating Hormone (FSH)', 66.00),
			array('FT3', 85.55),
			array('FT4', 66.04),
			array('Full Blood Count FBC (Auto) & Film Comment', 100.00),
			array('Full Blood Count FBC (Auto) (Automation)', 55.00),
			array('Full Blood Count with Film Comment (Manual)', 80.00),
			array('Fungal Cultures', 100.00),
			array('Gamma Glutamyl Transferase (GGT)', 66.00),
			array('Glucose-6-Phosphate Dehydrogenase (G6PD)', 45.00),
			array('Glycosylated Haemoglobin (HBA1C)', 185.00),
			array('Haemoglobin Electrophoresis', 100.00),
			array('Haemoglobin Estimation (HB)', 35.00),
			array('HDL - Cholesterol', 75.00),
			array('Helicobacter Pylori Test', 80.00),
			array('Hematocrit', 20.22),
			array('Hepatitis B Surface Antigen (HBSAG) HBV', 54.00),
			array('Hepatitis B Virus Profile (HBV Profile)', 200.00),
			array('Hepatitis C screening', 75.00),
			array('High Vaginal Swab for C/S', 150.00),
			array('High Vaginal Swab Routine Examination', 50.00),
			array('Human Immunodeficiency Virus (HIV) Confirmation', 40.00),
			array('Human Immunodeficiency Virus (HIV) Screening', 45.00),
			array('LDL - Cholesterol', 44.00),
			array('LFT', 140.00),
			array('Lipid Profile', 150.00),
			array('Lupus Erythematosus Cell (Le Cell)', 17.23),
			array('Luteinizing Hormone (LH)', 55.00),
			array('Magnesium', 65.00),
			array('Malaria Card Test (Upak assay/rapid card)', 45.00),
			array('Sodium (Na+)', 35.00),
			array('Oral Glucose Tolerance Test (OGTT)', 85.00),
			array('Pancreatic Amylase', 85.00),
			array('Phosphorus', 85.00),
			array('Plasma Cortisol', 85.00),
			array('Platelet Count', 20.00),
			array('Potassium', 55.00),
			array('Pregnancy Test', 40.00),
			array('Progesterone (Prog)', 75.00),
			array('Prostate Specific Antigen (PSA)', 85.00),
			array('Prothrombin Time', 65.00),
			array('Renal Function Test', 125.00),
			array('Reticulocyte Count (Retics)', 35.00),
			array('RH Typing', 15.00),
			array('Rheumatoid Factor', 75.00),
			array('Routine Blood Examination', 45.00),
			array('Routine Urine Examination', 45.00),
			array('Semen Analysis', 180.00),
			array('Serum Adrenocorticotropic Hormone (ACTH)', 85.00),
			array('Serum Albumin', 45.00),
			array('Serum Alkaline Phosphatase (ALP)', 45.00),
			array('Serum Beta-Human Chorionic Gonadotropin (HCG)', 65.00)
		);

		$inserted = 0;
		$skipped = 0;
		foreach ($items as $it) {
			$name = isset($it[0]) ? trim((string)$it[0]) : '';
			$price = isset($it[1]) ? (float)$it[1] : 0.0;
			if ($name === '') continue;

			$exists = $this->db->query(
				"SELECT particular_id FROM bill_particular WHERE InActive = 0 AND LOWER(TRIM(particular_name)) = LOWER(TRIM(?)) LIMIT 1",
				array($name)
			)->row();
			if ($exists && isset($exists->particular_id)) {
				$skipped++;
				continue;
			}
			$data = array(
				'group_id' => (int)$group_id,
				'particular_name' => strtoupper($name),
				'particular_desc' => '',
				'charge_amount' => round($price, 2),
				'InActive' => 0
			);
			if ($this->_column_exists('bill_particular', 'is_nhis_covered')) {
				$data['is_nhis_covered'] = 0;
				if ($this->_column_exists('bill_particular', 'nhis_charge_amount')) {
					$data['nhis_charge_amount'] = 0;
				}
			}
			$this->db->insert('bill_particular', $data);
			if ($this->db->affected_rows() > 0) {
				$inserted++;
			}
		}
		return array('success' => true, 'inserted' => $inserted, 'skipped_existing' => $skipped, 'error' => null);
	}
}
