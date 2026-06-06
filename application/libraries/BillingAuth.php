<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * BillingAuth Library
 * Role-Based Billing Permissions System
 * 
 * @author HMS Enterprise Architect
 * @version 1.0
 */
class BillingAuth
{
    protected $CI;
    protected $permissions = null;
    protected $user_role = null;
    
    // Permission constants
    const PERM_CREATE_INVOICE = 'can_create_invoice';
    const PERM_EDIT_INVOICE = 'can_edit_invoice';
    const PERM_DELETE_INVOICE = 'can_delete_invoice';
    const PERM_COLLECT_PAYMENT = 'can_collect_payment';
    const PERM_REFUND = 'can_refund';
    const PERM_VIEW_REPORTS = 'can_view_reports';
    const PERM_RECONCILE = 'can_reconcile';
    const PERM_APPROVE_DISCOUNT = 'can_approve_discount';
    const PERM_VIEW_AUDIT = 'can_view_audit';
    const PERM_MANAGE_SETTINGS = 'can_manage_settings';
    
    // Role constants
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_FINANCE_MANAGER = 'finance_manager';
    const ROLE_CASHIER = 'cashier';
    const ROLE_AUDITOR = 'auditor';
    const ROLE_DEPARTMENT_USER = 'department_user';
    
    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->ensure_schema();
        $this->load_permissions();
    }
    
    /**
     * Ensure billing_permissions table exists with default data
     */
    private function ensure_schema()
    {
        // Check if table exists
        if (!$this->CI->db->table_exists('billing_permissions')) {
            $sql = "CREATE TABLE `billing_permissions` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `role_key` VARCHAR(50) NOT NULL,
                `role_name` VARCHAR(100) NOT NULL,
                `can_create_invoice` TINYINT(1) DEFAULT 0,
                `can_edit_invoice` TINYINT(1) DEFAULT 0,
                `can_delete_invoice` TINYINT(1) DEFAULT 0,
                `can_collect_payment` TINYINT(1) DEFAULT 0,
                `can_refund` TINYINT(1) DEFAULT 0,
                `can_view_reports` TINYINT(1) DEFAULT 0,
                `can_reconcile` TINYINT(1) DEFAULT 0,
                `can_approve_discount` TINYINT(1) DEFAULT 0,
                `can_view_audit` TINYINT(1) DEFAULT 0,
                `can_manage_settings` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `role_key` (`role_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->CI->db->query($sql);
            
            // Seed default permissions
            $this->seed_default_permissions();
        }
        
        // Create user_billing_roles table for mapping users to billing roles
        if (!$this->CI->db->table_exists('user_billing_roles')) {
            $sql = "CREATE TABLE `user_billing_roles` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) NOT NULL,
                `billing_role_key` VARCHAR(50) NOT NULL,
                `assigned_by` INT(11) NULL,
                `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `user_role` (`user_id`, `billing_role_key`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->CI->db->query($sql);
        }
    }
    
    /**
     * Seed default billing permissions
     */
    private function seed_default_permissions()
    {
        $defaults = [
            // Super Admin - Full access
            [
                'role_key' => self::ROLE_SUPER_ADMIN,
                'role_name' => 'Super Admin',
                'can_create_invoice' => 1,
                'can_edit_invoice' => 1,
                'can_delete_invoice' => 1,
                'can_collect_payment' => 1,
                'can_refund' => 1,
                'can_view_reports' => 1,
                'can_reconcile' => 1,
                'can_approve_discount' => 1,
                'can_view_audit' => 1,
                'can_manage_settings' => 1
            ],
            // Finance Manager - Most access except delete
            [
                'role_key' => self::ROLE_FINANCE_MANAGER,
                'role_name' => 'Finance Manager',
                'can_create_invoice' => 1,
                'can_edit_invoice' => 1,
                'can_delete_invoice' => 0,
                'can_collect_payment' => 1,
                'can_refund' => 1,
                'can_view_reports' => 1,
                'can_reconcile' => 1,
                'can_approve_discount' => 1,
                'can_view_audit' => 1,
                'can_manage_settings' => 0
            ],
            // Cashier - Create invoices, collect payments
            [
                'role_key' => self::ROLE_CASHIER,
                'role_name' => 'Cashier',
                'can_create_invoice' => 1,
                'can_edit_invoice' => 0,
                'can_delete_invoice' => 0,
                'can_collect_payment' => 1,
                'can_refund' => 0,
                'can_view_reports' => 0,
                'can_reconcile' => 0,
                'can_approve_discount' => 0,
                'can_view_audit' => 0,
                'can_manage_settings' => 0
            ],
            // Auditor - View only
            [
                'role_key' => self::ROLE_AUDITOR,
                'role_name' => 'Auditor',
                'can_create_invoice' => 0,
                'can_edit_invoice' => 0,
                'can_delete_invoice' => 0,
                'can_collect_payment' => 0,
                'can_refund' => 0,
                'can_view_reports' => 1,
                'can_reconcile' => 1,
                'can_approve_discount' => 0,
                'can_view_audit' => 1,
                'can_manage_settings' => 0
            ],
            // Department User - Limited access
            [
                'role_key' => self::ROLE_DEPARTMENT_USER,
                'role_name' => 'Department User',
                'can_create_invoice' => 0,
                'can_edit_invoice' => 0,
                'can_delete_invoice' => 0,
                'can_collect_payment' => 0,
                'can_refund' => 0,
                'can_view_reports' => 0,
                'can_reconcile' => 0,
                'can_approve_discount' => 0,
                'can_view_audit' => 0,
                'can_manage_settings' => 0
            ]
        ];
        
        foreach ($defaults as $perm) {
            $this->CI->db->insert('billing_permissions', $perm);
        }
    }
    
    /**
     * Load current user's billing permissions
     */
    private function load_permissions()
    {
        $user_id = $this->CI->session->userdata('user_id');
        if (!$user_id) {
            $this->permissions = [];
            return;
        }
        
        // Get user's billing role
        $this->CI->db->where('user_id', $user_id);
        $user_role = $this->CI->db->get('user_billing_roles')->row();
        
        if ($user_role) {
            $this->user_role = $user_role->billing_role_key;
            
            // Get permissions for this role
            $this->CI->db->where('role_key', $user_role->billing_role_key);
            $perms = $this->CI->db->get('billing_permissions')->row();
            
            if ($perms) {
                $this->permissions = (array)$perms;
                return;
            }
        }
        
        // Fallback: Check if user is system admin (Super Admin module)
        $userInfo = $this->CI->general_model->getUserLoggedIn($this->CI->session->userdata('username'));
        if ($userInfo && isset($userInfo->module)) {
            $module = strtolower(trim((string)$userInfo->module));
            if ($module === 'super admin' || $module === 'administrator') {
                $this->user_role = self::ROLE_SUPER_ADMIN;
                $this->CI->db->where('role_key', self::ROLE_SUPER_ADMIN);
                $perms = $this->CI->db->get('billing_permissions')->row();
                if ($perms) {
                    $this->permissions = (array)$perms;
                    return;
                }
            }
            
            // Map system roles to billing roles
            if ($module === 'cashier' || $module === 'billing') {
                $this->user_role = self::ROLE_CASHIER;
            } elseif ($module === 'accountant' || $module === 'finance') {
                $this->user_role = self::ROLE_FINANCE_MANAGER;
            } else {
                $this->user_role = self::ROLE_DEPARTMENT_USER;
            }
            
            $this->CI->db->where('role_key', $this->user_role);
            $perms = $this->CI->db->get('billing_permissions')->row();
            if ($perms) {
                $this->permissions = (array)$perms;
                return;
            }
        }
        
        // Default: No permissions
        $this->permissions = [];
    }
    
    /**
     * Check if current user has a specific permission
     * 
     * @param string $permission Permission key (e.g., 'collect_payment')
     * @return bool
     */
    public function check($permission)
    {
        // Normalize permission key
        $key = 'can_' . str_replace('can_', '', $permission);
        
        if (empty($this->permissions)) {
            return false;
        }
        
        return isset($this->permissions[$key]) && (int)$this->permissions[$key] === 1;
    }
    
    /**
     * Require a permission - redirect if not authorized
     * 
     * @param string $permission Permission key
     * @param string $redirect_url URL to redirect to if not authorized
     */
    public function require_permission($permission, $redirect_url = null)
    {
        if (!$this->check($permission)) {
            if ($redirect_url) {
                $this->CI->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect($redirect_url);
            } else {
                show_error('Access Denied: You do not have permission to perform this action.', 403);
            }
        }
    }
    
    /**
     * Check permission and return JSON error for AJAX requests
     * 
     * @param string $permission Permission key
     * @return bool True if authorized, exits with JSON error if not
     */
    public function check_ajax($permission)
    {
        if (!$this->check($permission)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Permission denied: ' . $permission
            ]);
            exit;
        }
        return true;
    }
    
    /**
     * Get current user's billing role
     * 
     * @return string|null
     */
    public function get_role()
    {
        return $this->user_role;
    }
    
    /**
     * Get current user's permissions array
     * 
     * @return array
     */
    public function get_permissions()
    {
        return $this->permissions;
    }
    
    /**
     * Get all billing roles
     * 
     * @return array
     */
    public function get_all_roles()
    {
        return $this->CI->db->get('billing_permissions')->result();
    }
    
    /**
     * Assign billing role to user
     * 
     * @param int $user_id
     * @param string $role_key
     * @return bool
     */
    public function assign_role($user_id, $role_key)
    {
        // Remove existing role
        $this->CI->db->where('user_id', $user_id);
        $this->CI->db->delete('user_billing_roles');
        
        // Assign new role
        return $this->CI->db->insert('user_billing_roles', [
            'user_id' => $user_id,
            'billing_role_key' => $role_key,
            'assigned_by' => $this->CI->session->userdata('user_id')
        ]);
    }
    
    /**
     * Update role permissions
     * 
     * @param string $role_key
     * @param array $permissions
     * @return bool
     */
    public function update_role_permissions($role_key, $permissions)
    {
        $this->CI->db->where('role_key', $role_key);
        return $this->CI->db->update('billing_permissions', $permissions);
    }
    
    /**
     * Check if user is Super Admin
     * 
     * @return bool
     */
    public function is_super_admin()
    {
        return $this->user_role === self::ROLE_SUPER_ADMIN;
    }
    
    /**
     * Check if user is Finance Manager or higher
     * 
     * @return bool
     */
    public function is_finance_manager()
    {
        return in_array($this->user_role, [self::ROLE_SUPER_ADMIN, self::ROLE_FINANCE_MANAGER]);
    }
    
    /**
     * Check if user is Cashier or higher
     * 
     * @return bool
     */
    public function is_cashier()
    {
        return in_array($this->user_role, [self::ROLE_SUPER_ADMIN, self::ROLE_FINANCE_MANAGER, self::ROLE_CASHIER]);
    }
}
