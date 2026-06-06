<?php

if (!defined('BASEPATH')) {
	define('BASEPATH', __DIR__);
}

$root = dirname(__DIR__);
$sem = $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . 'ShadowGovernanceSemantics.php';
if (!file_exists($sem)) {
	fwrite(STDERR, "[fail] ShadowGovernanceSemantics.php not found at {$sem}\n");
	exit(2);
}
require_once($sem);

$failures = 0;

$assertSame = function ($name, $expected, $actual) use (&$failures) {
	if ($expected === $actual) {
		echo "[ok] {$name}\n";
		return;
	}
	$failures++;
	echo "[fail] {$name} expected=" . json_encode($expected) . " actual=" . json_encode($actual) . "\n";
};

$worstStatus = function ($items) {
	$r = ShadowGovernanceSemantics::worstResult($items);
	return is_array($r) && isset($r['status']) ? (string)$r['status'] : '';
};

$assertSame('UNPROVABLE + VIOLATION -> UNPROVABLE', 'UNPROVABLE', $worstStatus(array(
	array('status' => 'VIOLATION', 'code' => 'X'),
	array('status' => 'UNPROVABLE', 'code' => 'Y'),
)));

$assertSame('VIOLATION + ERROR -> VIOLATION', 'VIOLATION', $worstStatus(array(
	array('status' => 'ERROR', 'code' => 'X'),
	array('status' => 'VIOLATION', 'code' => 'Y'),
)));

$assertSame('UNPROVABLE + PASS -> UNPROVABLE', 'UNPROVABLE', $worstStatus(array(
	array('status' => 'PASS'),
	array('status' => 'UNPROVABLE', 'code' => 'X'),
)));

$assertSame('ERROR + PASS -> ERROR', 'ERROR', $worstStatus(array(
	array('status' => 'PASS'),
	array('status' => 'ERROR', 'code' => 'X'),
)));

$assertSame('PROVEN normalizes to PASS', 'PASS', $worstStatus(array(
	array('status' => 'PROVEN'),
	array('status' => 'PASS'),
)));

$assertSame('effective: parity UNPROVABLE dominates', 'UNPROVABLE', ShadowGovernanceSemantics::effectiveGovernanceStatus(
	array('status' => 'UNPROVABLE'),
	array('status' => 'PASS')
));

$assertSame('effective: parity PASS + proof PASS -> PASS', 'PASS', ShadowGovernanceSemantics::effectiveGovernanceStatus(
	array('status' => 'PASS'),
	array('status' => 'PASS')
));

$assertSame('effective: parity PASS + proof missing -> UNPROVABLE', 'UNPROVABLE', ShadowGovernanceSemantics::effectiveGovernanceStatus(
	array('status' => 'PASS'),
	null
));

if ($failures > 0) {
	echo "[result] FAIL failures={$failures}\n";
	exit(1);
}

echo "[result] PASS\n";
exit(0);
