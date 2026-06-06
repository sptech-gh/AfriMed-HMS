<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ShadowGovernanceSemantics
{
	public static function normalizeStatus($status)
	{
		$status = strtoupper(trim((string)$status));
		if ($status === 'PROVEN') {
			return 'PASS';
		}
		if ($status === 'OK') {
			return 'PASS';
		}
		return $status;
	}

	public static function statusRank($status)
	{
		$status = self::normalizeStatus($status);
		if ($status === 'UNPROVABLE') {
			return 4;
		}
		if ($status === 'VIOLATION') {
			return 3;
		}
		if ($status === 'ERROR') {
			return 2;
		}
		if ($status === 'PASS') {
			return 1;
		}
		return null;
	}

	public static function worstResult($results, $invalid_code = 'INVALID_RESULT')
	{
		if (!is_array($results) || empty($results)) {
			return array('status' => 'UNPROVABLE', 'code' => 'NO_RESULTS');
		}

		$worst = null;
		$worstRank = null;
		foreach ($results as $r) {
			if (!is_array($r) || !isset($r['status'])) {
				return array('status' => 'UNPROVABLE', 'code' => $invalid_code);
			}
			$normStatus = self::normalizeStatus($r['status']);
			$rank = self::statusRank($normStatus);
			if ($rank === null) {
				return array('status' => 'UNPROVABLE', 'code' => 'UNKNOWN_STATUS', 'data' => array('status' => (string)$r['status']));
			}
			if ($worstRank === null || $rank > $worstRank) {
				$r['status'] = $normStatus;
				$worst = $r;
				$worstRank = $rank;
			}
		}

		return $worst;
	}

	public static function effectiveGovernanceStatus($parity, $proof)
	{
		$parityStatus = (is_array($parity) && isset($parity['status'])) ? self::normalizeStatus($parity['status']) : '';
		if ($parityStatus === '') {
			return 'UNPROVABLE';
		}
		if ($parityStatus !== 'PASS') {
			return $parityStatus;
		}

		$proofStatus = (is_array($proof) && isset($proof['status'])) ? self::normalizeStatus($proof['status']) : '';
		if ($proofStatus === '') {
			return 'UNPROVABLE';
		}
		return $proofStatus;
	}

	public static function severityRank($severity)
	{
		$severity = strtoupper(trim((string)$severity));
		if ($severity === 'CRITICAL') {
			return 5;
		}
		if ($severity === 'HIGH') {
			return 4;
		}
		if ($severity === 'MEDIUM') {
			return 3;
		}
		if ($severity === 'LOW') {
			return 2;
		}
		if ($severity === 'WARNING') {
			return 2;
		}
		if ($severity === 'INFO') {
			return 1;
		}
		return null;
	}

	public static function maxSeverity($a, $b)
	{
		$ar = self::severityRank($a);
		$br = self::severityRank($b);
		if ($ar === null) {
			return $b;
		}
		if ($br === null) {
			return $a;
		}
		return ($ar >= $br) ? $a : $b;
	}

	public static function meetsSeverityThreshold($severity, $threshold)
	{
		$sr = self::severityRank($severity);
		$tr = self::severityRank($threshold);
		if ($sr === null || $tr === null) {
			return false;
		}
		return $sr >= $tr;
	}
}
