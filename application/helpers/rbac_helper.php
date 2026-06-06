<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * HMS Role-Based Access Control (RBAC) Helper
 *
 * Canonical role keys:
 *   admin, doctor, nurse, laboratory, sonographer, pharmacist, cashier
 *
 * Works with the existing user_roles.module field and users.user_role FK.
 * Session keys used: user_role (role_id), rbac_module, rbac_role_name
 */

/* ------------------------------------------------------------------ */
/*  ROLE KEY NORMALISATION                                            */
/* ------------------------------------------------------------------ */

if (!function_exists('_rbac_normalise_module')) {
    /**
     * Map the raw module string (from user_roles.module) to a canonical key.
     * @param  string $module  Raw module value
     * @param  int    $roleId  Numeric role_id (fallback)
     * @return string Canonical role key
     */
    function _rbac_normalise_module($module, $roleId = 0) {
        $module = strtolower(trim((string)$module));
        $roleId = (int)$roleId;

        // Normalise common variants
        if ($module === 'super admin') { $module = 'super_admin'; }

        // Map to canonical keys
        $map = array(
            'super_admin'    => 'admin',
            'administrator'  => 'admin',
            'doctor'         => 'doctor',
            'doctor_module'  => 'doctor',
            'doctor module'  => 'doctor',
            'nurse'          => 'nurse',
            'procedure_unit' => 'procedure_unit',
            'procedure unit' => 'procedure_unit',
            'laboratory'     => 'laboratory',
            'lab'            => 'laboratory',
            'labs'           => 'laboratory',
            'lab_module'     => 'laboratory',
            'lab module'     => 'laboratory',
            'laboratorist'   => 'laboratory',
            'lab technician' => 'laboratory',
            'sonography'     => 'sonographer',
            'sonographer'    => 'sonographer',
            'radiographer'   => 'sonographer',
            'pharmacy'       => 'pharmacist',
            'pharmacist'     => 'pharmacist',
            'dispenser'      => 'pharmacist',
            'dispensary'     => 'pharmacist',
            'receptionist'   => 'receptionist',
            'reception'      => 'receptionist',
            'cashier'        => 'cashier',
            'billing'        => 'cashier',
            'billing / cashier' => 'cashier',
            'billing/cashier' => 'cashier',
        );

        if (isset($map[$module])) {
            return $map[$module];
        }

        // Fallback: check by known role_id (based on actual user_roles table)
        $idMap = array(
            1  => 'admin',      // Super Admin
            2  => 'admin',      // Administrator and CEO
            3  => 'receptionist',
            5  => 'doctor',     // Doctor (was incorrectly mapped to cashier)
            6  => 'cashier',    // Billing / Cashier
            7  => 'nurse',
            10 => 'pharmacist',
            11 => 'laboratory',
            12 => 'sonographer',
        );
        if ($roleId > 0 && isset($idMap[$roleId])) {
            return $idMap[$roleId];
        }

        // Unknown — return sanitised module string
        return ($module !== '') ? $module : 'unknown';
    }
}

/* ------------------------------------------------------------------ */
/*  SESSION HELPERS                                                   */
/* ------------------------------------------------------------------ */

if (!function_exists('get_role_key')) {
    /**
     * Get the canonical role key for the currently logged-in user.
     * Reads from session cache (rbac_module) first; falls back to DB.
     * @return string  e.g. 'admin', 'doctor', 'nurse', …
     */
    function get_role_key() {
        $CI =& get_instance();

        // Fast path: cached in session during login
        $cached = $CI->session->userdata('rbac_module');
        // Only use cache if it's a known valid role key
        $validRoles = array('admin', 'doctor', 'nurse', 'procedure_unit', 'laboratory', 'sonographer', 'pharmacist', 'cashier', 'receptionist', 'radiology', 'radiologist');
        if ($cached !== NULL && $cached !== FALSE && $cached !== '' && in_array($cached, $validRoles, true)) {
            return (string)$cached;
        }

        // Slow path: derive from userInfo (already loaded by General::variable)
        $module = '';
        $roleId = (int)$CI->session->userdata('user_role');

        if (isset($CI->data['userInfo']) && is_object($CI->data['userInfo'])) {
            $u = $CI->data['userInfo'];
            $module = isset($u->module) ? (string)$u->module : '';
            $roleId = isset($u->user_role) ? (int)$u->user_role : $roleId;
        } elseif (isset($CI->general_model) && $CI->session->userdata('username')) {
            $u = $CI->general_model->getUserLoggedIn($CI->session->userdata('username'));
            if ($u) {
                $module = isset($u->module) ? (string)$u->module : '';
                $roleId = isset($u->user_role) ? (int)$u->user_role : $roleId;
            }
        }

        $key = _rbac_normalise_module($module, $roleId);

        // Cache for remainder of session
        $CI->session->set_userdata('rbac_module', $key);

        return $key;
    }
}

if (!function_exists('get_role_id')) {
    /**
     * Get the numeric role_id from session.
     * @return int
     */
    function get_role_id() {
        $CI =& get_instance();
        return (int)$CI->session->userdata('user_role');
    }
}

/* ------------------------------------------------------------------ */
/*  ACCESS-CHECK FUNCTIONS                                            */
/* ------------------------------------------------------------------ */

if (!function_exists('has_role')) {
    /**
     * Check if the current user has one of the given role(s).
     * Admin always passes. Checks both base role and dynamic privileges.
     *
     * @param  string|array $roles  Single role key or array of keys
     * @return bool
     */
    function has_role($roles) {
        $current = get_role_key();

        // Admin has access to everything
        if ($current === 'admin') {
            return true;
        }

        if (is_string($roles)) {
            $roles = array($roles);
        }

        // Check base role first
        if (in_array($current, $roles, true)) {
            return true;
        }

        // Check dynamic privileges from database (real-time)
        $CI =& get_instance();
        $user_id = $CI->session->userdata('user_id');
        if ($user_id) {
            if (!isset($CI->governance_model)) {
                $CI->load->model('app/governance_model');
            }
            $dynRoles = $CI->governance_model->get_dynamic_role_keys($user_id);
            if (is_array($dynRoles) && count($dynRoles) > 0) {
                foreach ($roles as $r) {
                    if (in_array($r, $dynRoles, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

if (!function_exists('is_admin_role')) {
    /**
     * Convenience: is the current user an administrator?
     * @return bool
     */
    function is_admin_role() {
        return (get_role_key() === 'admin');
    }
}

if (!function_exists('require_role')) {
    /**
     * Gate-keep a controller action: redirect to access_denied if the
     * current user does NOT hold one of the specified roles.
     * Admin is always allowed.
     *
     * Usage in a controller method:
     *   require_role('doctor');
     *   require_role(array('doctor', 'nurse'));
     *
     * @param  string|array $roles
     * @return void  (redirects + exit on failure)
     */
    function require_role($roles) {
        $current = get_role_key();
        log_message('debug', 'RBAC_REQUIRE_ROLE current='.$current.' required='.json_encode($roles));
        if (!has_role($roles)) {
            $CI =& get_instance();
            log_message('debug', 'RBAC_ACCESS_DENIED current='.$current.' required='.json_encode($roles));
            $CI->session->set_flashdata('message',
                '<div class="alert alert-danger"><i class="fa fa-ban"></i> Access denied. You do not have permission to view this page.</div>');
            redirect(base_url() . 'app/dashboard');
            exit;
        }
    }
}

if (!function_exists('require_logged_in')) {
    /**
     * Redirect to login if user is not authenticated.
     * @return void
     */
    function require_logged_in() {
        $CI =& get_instance();
        if (!$CI->session->userdata('is_logged_in')) {
            redirect(base_url() . 'login');
            exit;
        }
    }
}

/* ------------------------------------------------------------------ */
/*  ROLE METADATA (for UI / dropdowns)                                */
/* ------------------------------------------------------------------ */

if (!function_exists('rbac_role_list')) {
    /**
     * Return the list of canonical roles with labels and icons.
     * @return array
     */
    function rbac_role_list() {
        return array(
            'admin'        => array('label' => 'Administrator',            'icon' => 'fa-shield'),
            'doctor'       => array('label' => 'Doctor',                   'icon' => 'fa-user-md'),
            'nurse'        => array('label' => 'Nurse',                    'icon' => 'fa-plus-square'),
            'procedure_unit' => array('label' => 'Procedure Unit',         'icon' => 'fa-scissors'),
            'receptionist' => array('label' => 'Receptionist',              'icon' => 'fa-desktop'),
            'laboratory'   => array('label' => 'Laboratory',               'icon' => 'fa-flask'),
            'sonographer'  => array('label' => 'Sonographer / Radiographer','icon' => 'fa-heartbeat'),
            'radiology'    => array('label' => 'Radiology',                'icon' => 'fa-stethoscope'),
            'radiologist'  => array('label' => 'Radiologist',              'icon' => 'fa-user-md'),
            'pharmacist'   => array('label' => 'Pharmacist',               'icon' => 'fa-medkit'),
            'cashier'      => array('label' => 'Cashier / Billing',        'icon' => 'fa-money'),
        );
    }
}

if (!function_exists('rbac_role_label')) {
    /**
     * Human-readable label for a canonical role key.
     * @param  string|null $key  NULL = current user
     * @return string
     */
    function rbac_role_label($key = null) {
        if ($key === null) { $key = get_role_key(); }
        $list = rbac_role_list();
        return isset($list[$key]) ? $list[$key]['label'] : ucfirst($key);
    }
}

/* ------------------------------------------------------------------ */
/*  DYNAMIC PRIVILEGE REFRESH SYSTEM                                   */
/* ------------------------------------------------------------------ */

if (!function_exists('refresh_user_privileges')) {
    /**
     * Reload the current user's dynamic privileges from the database.
     * Call this when privileges may have changed (e.g., after admin grants).
     * Updates session with fresh privilege data.
     * @return array  The refreshed dynamic role keys
     */
    function refresh_user_privileges() {
        $CI =& get_instance();
        $user_id = $CI->session->userdata('user_id');
        
        if (!$user_id) {
            return array();
        }

        // Load governance model if not loaded
        if (!isset($CI->governance_model)) {
            $CI->load->model('app/governance_model');
        }

        // Get fresh privilege data from DB
        $dynRoleKeys = $CI->governance_model->get_dynamic_role_keys($user_id);
        $privilegeNames = $CI->governance_model->get_user_privilege_names($user_id);

        // Update session with fresh data
        $CI->session->set_userdata(array(
            'dynamic_role_keys'      => $dynRoleKeys,
            'user_privilege_names'   => $privilegeNames,
            'privileges_loaded_at'   => date('Y-m-d H:i:s')
        ));

        log_message('info', 'RBAC_PRIVILEGES_REFRESHED user_id='.$user_id.' roles='.json_encode($dynRoleKeys));

        return $dynRoleKeys;
    }
}

if (!function_exists('check_privilege_refresh_needed')) {
    /**
     * Check if the current user's privileges need to be refreshed.
     * Should be called on every request (e.g., in General controller).
     * Automatically refreshes if needed.
     * @return bool  True if refresh was performed
     */
    function check_privilege_refresh_needed() {
        $CI =& get_instance();
        $user_id = $CI->session->userdata('user_id');
        
        if (!$user_id || !$CI->session->userdata('is_logged_in')) {
            return false;
        }

        // Load governance model if not loaded
        if (!isset($CI->governance_model)) {
            $CI->load->model('app/governance_model');
        }

        $loadedAt = $CI->session->userdata('privileges_loaded_at');
        
        // Check if refresh is needed
        if ($CI->governance_model->needs_privilege_refresh($user_id, $loadedAt)) {
            refresh_user_privileges();
            log_message('info', 'RBAC_AUTO_REFRESH user_id='.$user_id.' triggered by privilege change');
            return true;
        }

        return false;
    }
}

if (!function_exists('has_privilege')) {
    /**
     * Check if the current user has a specific privilege.
     * Always checks database to ensure real-time accuracy.
     * @param  string $privilege_name  The privilege to check (e.g., 'cashier_access')
     * @return bool
     */
    function has_privilege($privilege_name) {
        $CI =& get_instance();
        
        // Admin has all privileges
        if (get_role_key() === 'admin') {
            return true;
        }

        $user_id = $CI->session->userdata('user_id');
        if (!$user_id) {
            return false;
        }

        // Always check database for real-time privilege status
        // This ensures newly granted privileges work immediately
        if (!isset($CI->governance_model)) {
            $CI->load->model('app/governance_model');
        }

        return $CI->governance_model->user_has_privilege($user_id, $privilege_name);
    }
}

if (!function_exists('require_privilege')) {
    /**
     * Gate-keep a controller action: redirect if user lacks the privilege.
     * Admin always passes.
     * @param  string $privilege_name
     * @return void  (redirects + exit on failure)
     */
    function require_privilege($privilege_name) {
        if (!has_privilege($privilege_name)) {
            $CI =& get_instance();
            log_message('info', 'RBAC_PRIVILEGE_DENIED user_id='.$CI->session->userdata('user_id').' required='.$privilege_name);
            $CI->session->set_flashdata('message',
                '<div class="alert alert-danger"><i class="fa fa-ban"></i> Access denied. You do not have the required privilege.</div>');
            redirect(base_url() . 'app/dashboard');
            exit;
        }
    }
}

if (!function_exists('get_user_privileges')) {
    /**
     * Get all privilege names for the current user.
     * @return array
     */
    function get_user_privileges() {
        $CI =& get_instance();
        
        // Check session cache first
        $privileges = $CI->session->userdata('user_privilege_names');
        if (is_array($privileges)) {
            return $privileges;
        }

        // Fallback: load from database
        $user_id = $CI->session->userdata('user_id');
        if (!$user_id) {
            return array();
        }

        if (!isset($CI->governance_model)) {
            $CI->load->model('app/governance_model');
        }

        return $CI->governance_model->get_user_privilege_names($user_id);
    }
}

if (!function_exists('get_effective_roles')) {
    /**
     * Get all effective roles for the current user (base role + dynamic privileges).
     * @return array  Array of canonical role keys
     */
    function get_effective_roles() {
        $roles = array(get_role_key());
        
        $CI =& get_instance();
        $dynRoles = $CI->session->userdata('dynamic_role_keys');
        if (is_array($dynRoles)) {
            $roles = array_unique(array_merge($roles, $dynRoles));
        }

        return $roles;
    }
}
