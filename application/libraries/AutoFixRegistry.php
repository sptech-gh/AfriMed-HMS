<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/AutoFixHandlerInterface.php';

class AutoFixRegistry
{
    private $map = array();

    public function __construct(array $map = array())
    {
        if (!empty($map)) {
            $this->map = $map;
        }
    }

    public function register($action_key, $handler_class)
    {
        $k = strtolower(trim((string)$action_key));
        if ($k === '') {
            return;
        }
        $this->map[$k] = (string)$handler_class;
    }

    public function resolve_handler_class($action_key)
    {
        $k = strtolower(trim((string)$action_key));
        if ($k === '' || !array_key_exists($k, $this->map)) {
            return null;
        }
        return (string)$this->map[$k];
    }

    public function is_allowed($action_key)
    {
        $k = strtolower(trim((string)$action_key));
        return $k !== '' && array_key_exists($k, $this->map);
    }
}
