<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Loader extends CI_Loader
{
	public function database($params = '', $return = FALSE, $query_builder = NULL)
	{
		$CI =& get_instance();

		if ($return === FALSE && $query_builder === NULL && isset($CI->db) && is_object($CI->db) && ! empty($CI->db->conn_id))
		{
			return FALSE;
		}

		if (file_exists(APPPATH.'database/DB.php'))
		{
			require_once(APPPATH.'database/DB.php');
		}
		else
		{
			require_once(BASEPATH.'database/DB.php');
		}

		if ($return === TRUE)
		{
			return DB($params, $query_builder);
		}

		$CI->db = '';
		$CI->db =& DB($params, $query_builder);
		return $this;
	}
}
