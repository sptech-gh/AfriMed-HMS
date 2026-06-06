<?php defined('BASEPATH') OR exit('No direct script access allowed');

class ShadowWriteCollector
{
	protected static $writes = array();

	public static function record($entry)
	{
		if (!is_array($entry)) {
			return;
		}
		self::$writes[] = $entry;
	}

	public static function flush()
	{
		$data = self::$writes;
		self::$writes = array();
		return $data;
	}
}
