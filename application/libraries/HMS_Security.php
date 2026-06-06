<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * HMS Security Library - Centralized security functions
 */
class HMS_Security {
    
    protected $CI;
    
    public function __construct(){
        $this->CI =& get_instance();
    }
    
    public function hash_password($password){
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    public function verify_password($password, $hash){
        if (password_verify($password, $hash)) {
            return true;
        }
        if (strlen($hash) === 32 && $hash === md5($password)) {
            return true;
        }
        return false;
    }
    
    public function needs_rehash($hash){
        if (strlen($hash) === 32) {
            return true;
        }
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    public function xss_clean($data){
        if (is_array($data)) {
            return array_map(array($this, 'xss_clean'), $data);
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    public function generate_token($length = 32){
        return bin2hex(random_bytes($length / 2));
    }
    
    public function log_security_event($event_type, $details = array()){
        $this->_ensure_security_log_table();
        $user_id = $this->CI->session->userdata('user_id');
        $this->CI->db->insert('security_audit_log', array(
            'event_type'   => $event_type,
            'user_id'      => $user_id ? $user_id : null,
            'username'     => $this->CI->session->userdata('username'),
            'ip_address'   => $this->CI->input->ip_address(),
            'user_agent'   => substr($this->CI->input->user_agent(), 0, 255),
            'details'      => json_encode($details),
            'created_at'   => date('Y-m-d H:i:s')
        ));
    }
    
    private function _ensure_security_log_table(){
        $this->CI->db->query("CREATE TABLE IF NOT EXISTS `security_audit_log` (
            `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `event_type` varchar(50) NOT NULL,
            `user_id` varchar(25) DEFAULT NULL,
            `username` varchar(100) DEFAULT NULL,
            `ip_address` varchar(45) NOT NULL,
            `user_agent` varchar(255) DEFAULT NULL,
            `details` text,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`log_id`),
            KEY `idx_event_type` (`event_type`),
            KEY `idx_user` (`user_id`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
