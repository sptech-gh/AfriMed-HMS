<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// DISABLED: Facebook integration is not in use. This controller is kept only
// for historical reference. All requests return 403 to prevent misuse.
class Fb extends CI_Controller {

    function __construct() {
        parent::__construct();
        show_error('This feature is not available.', 403);
    }

    function index() {
        show_error('This feature is not available.', 403);
    }
}