<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Test Catalog Controller
 * 
 * Admin interface for managing GHS/NHIS-compliant laboratory and sonography tests.
 * 
 * @author HMS Development Team
 * @version 1.0.0
 */
class Test_catalog extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('app/Ghana_test_catalog_model');
        $this->load->model('app/Patient_model');
        $this->load->helper(['url', 'form']);
        $this->load->library(['session', 'form_validation']);
        
        // Check admin access
        if (!$this->session->userdata('user_id')) {
            redirect('login');
        }
        
        $this->data = [
            'base_url' => base_url(),
            'page_title' => 'Test Catalog'
        ];
    }
    
    /**
     * Check if user is admin
     */
    private function _require_admin()
    {
        $role = strtolower($this->session->userdata('role') ?? '');
        if (!in_array($role, ['admin', 'superadmin', 'administrator'])) {
            $this->session->set_flashdata('error', 'Admin access required');
            redirect('app/dashboard');
        }
    }
    
    /* ================================================================== */
    /*  LABORATORY TESTS                                                  */
    /* ================================================================== */
    
    /**
     * Laboratory Tests List
     */
    public function lab_tests()
    {
        $this->_require_admin();
        
        $this->data['page_title'] = 'Laboratory Test Catalog';
        $this->data['tests'] = $this->Ghana_test_catalog_model->get_lab_tests(null, null, true);
        $this->data['categories'] = $this->Ghana_test_catalog_model->get_lab_categories();
        $this->data['stats'] = $this->Ghana_test_catalog_model->get_test_statistics();
        $this->data['message'] = $this->session->flashdata('message');
        
        $this->load->view('app/test_catalog/lab_tests', $this->data);
    }
    
    /**
     * Add Laboratory Test
     */
    public function add_lab_test()
    {
        $this->_require_admin();
        
        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('test_code', 'Test Code', 'required|trim');
            $this->form_validation->set_rules('test_name', 'Test Name', 'required|trim');
            $this->form_validation->set_rules('category', 'Category', 'required|trim');
            
            if ($this->form_validation->run()) {
                $data = [
                    'test_code' => $this->input->post('test_code'),
                    'test_name' => $this->input->post('test_name'),
                    'category' => $this->input->post('category'),
                    'specimen_type' => $this->input->post('specimen_type'),
                    'price' => floatval($this->input->post('price')),
                    'nhis_code' => $this->input->post('nhis_code'),
                    'nhis_price' => floatval($this->input->post('nhis_price')),
                    'is_nhis_covered' => $this->input->post('is_nhis_covered') ? 1 : 0,
                    'turnaround_time' => $this->input->post('turnaround_time'),
                    'requires_fasting' => $this->input->post('requires_fasting') ? 1 : 0,
                    'special_instructions' => $this->input->post('special_instructions'),
                    'particular_id' => $this->input->post('particular_id') ? (int)$this->input->post('particular_id') : null,
                    'created_by' => $this->session->userdata('user_id')
                ];
                
                $result = $this->Ghana_test_catalog_model->add_lab_test($data);
                
                if ($result['success']) {
                    $this->session->set_flashdata('message', '<div class="alert alert-success">Laboratory test added successfully!</div>');
                } else {
                    $this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to add test: ' . ($result['error']['message'] ?? 'Unknown error') . '</div>');
                }
                redirect('app/test_catalog/lab_tests');
            }
        }
        
        $this->data['page_title'] = 'Add Laboratory Test';
        $this->data['categories'] = $this->Ghana_test_catalog_model->get_lab_categories();
        $this->load->view('app/test_catalog/lab_test_form', $this->data);
    }
    
    /**
     * Edit Laboratory Test
     */
    public function edit_lab_test($test_id)
    {
        $this->_require_admin();
        
        $test = $this->Ghana_test_catalog_model->get_lab_test($test_id);
        if (!$test) {
            $this->session->set_flashdata('message', '<div class="alert alert-danger">Test not found</div>');
            redirect('app/test_catalog/lab_tests');
        }
        
        if ($this->input->method() === 'post') {
            $data = [
                'test_code' => $this->input->post('test_code'),
                'test_name' => $this->input->post('test_name'),
                'category' => $this->input->post('category'),
                'specimen_type' => $this->input->post('specimen_type'),
                'price' => floatval($this->input->post('price')),
                'nhis_code' => $this->input->post('nhis_code'),
                'nhis_price' => floatval($this->input->post('nhis_price')),
                'is_nhis_covered' => $this->input->post('is_nhis_covered') ? 1 : 0,
                'turnaround_time' => $this->input->post('turnaround_time'),
                'requires_fasting' => $this->input->post('requires_fasting') ? 1 : 0,
                'special_instructions' => $this->input->post('special_instructions'),
                'particular_id' => $this->input->post('particular_id') ? (int)$this->input->post('particular_id') : null,
                'is_active' => $this->input->post('is_active') ? 1 : 0
            ];
            
            $result = $this->Ghana_test_catalog_model->update_lab_test($test_id, $data);
            
            if ($result['success']) {
                $this->session->set_flashdata('message', '<div class="alert alert-success">Laboratory test updated successfully!</div>');
            } else {
                $this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to update test</div>');
            }
            redirect('app/test_catalog/lab_tests');
        }
        
        $this->data['page_title'] = 'Edit Laboratory Test';
        $this->data['test'] = $test;
        $this->data['categories'] = $this->Ghana_test_catalog_model->get_lab_categories();
        $this->load->view('app/test_catalog/lab_test_form', $this->data);
    }
    
    /**
     * Toggle Lab Test Status (AJAX)
     */
    public function toggle_lab_test()
    {
        header('Content-Type: application/json');
        
        $test_id = $this->input->post('test_id');
        $is_active = $this->input->post('is_active') ? 1 : 0;
        
        $result = $this->Ghana_test_catalog_model->update_lab_test($test_id, ['is_active' => $is_active]);
        
        echo json_encode($result);
    }
    
    /* ================================================================== */
    /*  SONOGRAPHY TESTS                                                  */
    /* ================================================================== */
    
    /**
     * Sonography Tests List
     */
    public function sonography_tests()
    {
        $this->_require_admin();
        
        $this->data['page_title'] = 'Sonography Test Catalog';
        $this->data['tests'] = $this->Ghana_test_catalog_model->get_sonography_tests(null, null, true);
        $this->data['categories'] = $this->Ghana_test_catalog_model->get_sonography_categories();
        $this->data['stats'] = $this->Ghana_test_catalog_model->get_test_statistics();
        $this->data['message'] = $this->session->flashdata('message');
        
        $this->load->view('app/test_catalog/sonography_tests', $this->data);
    }
    
    /**
     * Add Sonography Test
     */
    public function add_sonography_test()
    {
        $this->_require_admin();
        
        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('test_code', 'Test Code', 'required|trim');
            $this->form_validation->set_rules('test_name', 'Test Name', 'required|trim');
            $this->form_validation->set_rules('category', 'Category', 'required|trim');
            
            if ($this->form_validation->run()) {
                $data = [
                    'test_code' => $this->input->post('test_code'),
                    'test_name' => $this->input->post('test_name'),
                    'category' => $this->input->post('category'),
                    'body_part' => $this->input->post('body_part'),
                    'price' => floatval($this->input->post('price')),
                    'nhis_code' => $this->input->post('nhis_code'),
                    'nhis_price' => floatval($this->input->post('nhis_price')),
                    'is_nhis_covered' => $this->input->post('is_nhis_covered') ? 1 : 0,
                    'preparation' => $this->input->post('preparation'),
                    'particular_id' => $this->input->post('particular_id') ? (int)$this->input->post('particular_id') : null,
                    'created_by' => $this->session->userdata('user_id')
                ];
                
                $result = $this->Ghana_test_catalog_model->add_sonography_test($data);
                
                if ($result['success']) {
                    $this->session->set_flashdata('message', '<div class="alert alert-success">Sonography test added successfully!</div>');
                } else {
                    $this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to add test</div>');
                }
                redirect('app/test_catalog/sonography_tests');
            }
        }
        
        $this->data['page_title'] = 'Add Sonography Test';
        $this->data['categories'] = $this->Ghana_test_catalog_model->get_sonography_categories();
        $this->load->view('app/test_catalog/sonography_test_form', $this->data);
    }
    
    /**
     * Edit Sonography Test
     */
    public function edit_sonography_test($test_id)
    {
        $this->_require_admin();
        
        $test = $this->Ghana_test_catalog_model->get_sonography_test($test_id);
        if (!$test) {
            $this->session->set_flashdata('message', '<div class="alert alert-danger">Test not found</div>');
            redirect('app/test_catalog/sonography_tests');
        }
        
        if ($this->input->method() === 'post') {
            $data = [
                'test_code' => $this->input->post('test_code'),
                'test_name' => $this->input->post('test_name'),
                'category' => $this->input->post('category'),
                'body_part' => $this->input->post('body_part'),
                'price' => floatval($this->input->post('price')),
                'nhis_code' => $this->input->post('nhis_code'),
                'nhis_price' => floatval($this->input->post('nhis_price')),
                'is_nhis_covered' => $this->input->post('is_nhis_covered') ? 1 : 0,
                'preparation' => $this->input->post('preparation'),
                'particular_id' => $this->input->post('particular_id') ? (int)$this->input->post('particular_id') : null,
                'is_active' => $this->input->post('is_active') ? 1 : 0
            ];
            
            $result = $this->Ghana_test_catalog_model->update_sonography_test($test_id, $data);
            
            if ($result['success']) {
                $this->session->set_flashdata('message', '<div class="alert alert-success">Sonography test updated successfully!</div>');
            } else {
                $this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to update test</div>');
            }
            redirect('app/test_catalog/sonography_tests');
        }
        
        $this->data['page_title'] = 'Edit Sonography Test';
        $this->data['test'] = $test;
        $this->data['categories'] = $this->Ghana_test_catalog_model->get_sonography_categories();
        $this->load->view('app/test_catalog/sonography_test_form', $this->data);
    }
    
    /**
     * Toggle Sonography Test Status (AJAX)
     */
    public function toggle_sonography_test()
    {
        header('Content-Type: application/json');
        
        $test_id = $this->input->post('test_id');
        $is_active = $this->input->post('is_active') ? 1 : 0;
        
        $result = $this->Ghana_test_catalog_model->update_sonography_test($test_id, ['is_active' => $is_active]);
        
        echo json_encode($result);
    }
    
    /* ================================================================== */
    /*  AJAX ENDPOINTS FOR DOCTOR UI                                      */
    /* ================================================================== */
    
    /**
     * Search Lab Tests (AJAX)
     */
    public function search_lab_json()
    {
        header('Content-Type: application/json');
        $term = trim($this->input->get('term'));
        $results = $this->Ghana_test_catalog_model->search_lab_tests($term);
        echo json_encode($results);
    }
    
    /**
     * Search Sonography Tests (AJAX)
     */
    public function search_sonography_json()
    {
        header('Content-Type: application/json');
        $term = trim($this->input->get('term'));
        $results = $this->Ghana_test_catalog_model->search_sonography_tests($term);
        echo json_encode($results);
    }
    
    /**
     * Get Lab Test Details (AJAX)
     */
    public function get_lab_test_json($test_id)
    {
        header('Content-Type: application/json');
        $test = $this->Ghana_test_catalog_model->get_lab_test($test_id);
        echo json_encode($test ?: ['error' => 'Test not found']);
    }
    
    /**
     * Get Sonography Test Details (AJAX)
     */
    public function get_sonography_test_json($test_id)
    {
        header('Content-Type: application/json');
        $test = $this->Ghana_test_catalog_model->get_sonography_test($test_id);
        echo json_encode($test ?: ['error' => 'Test not found']);
    }
}
