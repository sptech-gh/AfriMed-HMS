<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'controllers/General.php');

/**
 * Ebilling — Redirect-only stub.
 *
 * The "Enterprise Billing" layer (eb_transactions / eb_invoices /
 * eb_payments / eb_service_gates / eb_adjustments / eb_events_log)
 * was quarantined on 2026-04-26 as part of the SSOT consolidation
 * (Phase 4, Step 2). Its supporting models live under
 * `application/models/app/_deprecated/` with .disabled extensions.
 *
 * All previously routed actions (/app/ebilling/*) now redirect to
 * the canonical Unified Billing dashboard. Sidebar links continue
 * to function. The original controller is preserved as
 * `Ebilling.php.bak.20260426` for audit reference.
 */
class Ebilling extends General
{
    public function __construct()
    {
        parent::__construct();
        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
    }

    public function _remap($method, $params = array())
    {
        // Any /app/ebilling/* URL goes to legacy cashier payment collection.
        $this->session->set_flashdata(
            'info',
            'Enterprise Billing has been consolidated. Redirecting to Cashier.'
        );
        redirect('app/cashier/payments');
    }
}
