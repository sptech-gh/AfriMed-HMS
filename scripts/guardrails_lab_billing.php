<?php

declare(strict_types=1);

$root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
if ($root === false) {
	fwrite(STDERR, "[guardrails_lab_billing][error] cannot_resolve_project_root\n");
	exit(2);
}

$scanDirs = array(
	$root . DIRECTORY_SEPARATOR . 'application',
);

$excludePrefixes = array(
	realpath($root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'logs'),
);
$excludePrefixes = array_values(array_filter($excludePrefixes, function ($p) {
	return is_string($p) && $p !== '';
}));

$allowedBillingTransactionsWriters = array(
	realpath($root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'billing_transaction_model.php'),
	realpath($root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Billing_facade_model.php'),
	// Baseline allowlist: pre-existing non-canonical write that must be burned down.
	realpath($root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'laboratory_model.php'),
);
$allowedBillingTransactionsWriters = array_values(array_filter($allowedBillingTransactionsWriters, function ($p) {
	return is_string($p) && $p !== '';
}));

$allowedBillingDispositionsWriters = array(
	realpath($root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'billing_disposition_model.php'),
);
$allowedBillingDispositionsWriters = array_values(array_filter($allowedBillingDispositionsWriters, function ($p) {
	return is_string($p) && $p !== '';
}));

$controllerRoots = array(
	realpath($root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'controllers'),
);
$controllerRoots = array_values(array_filter($controllerRoots, function ($p) {
	return is_string($p) && $p !== '';
}));

$rules = array(
	array(
		'id' => 'CTRL_WRITE_BILLING_TRANSACTIONS',
		'description' => 'Controllers must not write billing_transactions',
		'scope' => 'controllers_only',
		'needles' => array(
			"insert('billing_transactions'",
			"insert(\"billing_transactions\"",
			"update('billing_transactions'",
			"update(\"billing_transactions\"",
			"delete('billing_transactions'",
			"delete(\"billing_transactions\"",
			"INSERT INTO billing_transactions",
			"UPDATE billing_transactions",
			"DELETE FROM billing_transactions",
		),
	),
	array(
		'id' => 'CTRL_WRITE_BILLING_DISPOSITIONS',
		'description' => 'Controllers must not write billing_dispositions',
		'scope' => 'controllers_only',
		'needles' => array(
			"insert('billing_dispositions'",
			"insert(\"billing_dispositions\"",
			"update('billing_dispositions'",
			"update(\"billing_dispositions\"",
			"delete('billing_dispositions'",
			"delete(\"billing_dispositions\"",
			"INSERT INTO billing_dispositions",
			"UPDATE billing_dispositions",
			"DELETE FROM billing_dispositions",
		),
	),
	array(
		'id' => 'NONCANON_WRITE_BILLING_TRANSACTIONS',
		'description' => 'Writes to billing_transactions must be confined to canonical writers',
		'scope' => 'all_php',
		'needles' => array(
			"insert('billing_transactions'",
			"insert(\"billing_transactions\"",
			"update('billing_transactions'",
			"update(\"billing_transactions\"",
			"delete('billing_transactions'",
			"delete(\"billing_transactions\"",
			"INSERT INTO billing_transactions",
			"UPDATE billing_transactions",
			"DELETE FROM billing_transactions",
		),
		'allowed_files' => $allowedBillingTransactionsWriters,
	),
	array(
		'id' => 'NONCANON_WRITE_BILLING_DISPOSITIONS',
		'description' => 'Writes to billing_dispositions must be confined to billing_disposition_model.php',
		'scope' => 'all_php',
		'needles' => array(
			"insert('billing_dispositions'",
			"insert(\"billing_dispositions\"",
			"update('billing_dispositions'",
			"update(\"billing_dispositions\"",
			"delete('billing_dispositions'",
			"delete(\"billing_dispositions\"",
			"INSERT INTO billing_dispositions",
			"UPDATE billing_dispositions",
			"DELETE FROM billing_dispositions",
		),
		'allowed_files' => $allowedBillingDispositionsWriters,
	),
);

function is_under_dir(string $file, string $dir): bool
{
	$dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
	return strpos($file, $dir) === 0;
}

function read_lines(string $path): array
{
	$raw = @file($path);
	if (!is_array($raw)) {
		return array();
	}
	return $raw;
}

function find_needle_lines(array $lines, string $needle): array
{
	$out = array();
	foreach ($lines as $i => $line) {
		if (strpos($line, $needle) !== false) {
			$out[] = $i + 1;
		}
	}
	return $out;
}

$phpFiles = array();
foreach ($scanDirs as $dir) {
	if (!is_dir($dir)) {
		continue;
	}
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
	foreach ($it as $f) {
		$path = $f->getPathname();
		if (substr($path, -4) !== '.php') {
			continue;
		}
		$rp = realpath($path);
		if (is_string($rp) && $rp !== '') {
			$skip = false;
			foreach ($excludePrefixes as $ex) {
				$exPrefix = rtrim($ex, '/\\') . DIRECTORY_SEPARATOR;
				if (strpos($rp, $exPrefix) === 0) {
					$skip = true;
					break;
				}
			}
			if ($skip) {
				continue;
			}
			$phpFiles[] = $rp;
		}
	}
}
$phpFiles = array_values(array_unique($phpFiles));

$controllerFiles = array();
foreach ($phpFiles as $p) {
	foreach ($controllerRoots as $cr) {
		if ($cr !== '' && is_under_dir($p, $cr)) {
			$controllerFiles[] = $p;
			break;
		}
	}
}
$controllerFiles = array_values(array_unique($controllerFiles));

$violations = array();

foreach ($rules as $rule) {
	$scope = isset($rule['scope']) ? (string)$rule['scope'] : '';
	$needles = isset($rule['needles']) && is_array($rule['needles']) ? $rule['needles'] : array();
	$allowed = isset($rule['allowed_files']) && is_array($rule['allowed_files']) ? $rule['allowed_files'] : null;
	$filesToScan = ($scope === 'controllers_only') ? $controllerFiles : $phpFiles;

	foreach ($filesToScan as $file) {
		if (is_array($allowed) && !in_array($file, $allowed, true)) {
			// continue scanning normally
		} elseif (is_array($allowed)) {
			// file is allowed for this rule
			continue;
		}

		$lines = read_lines($file);
		if (empty($lines)) {
			continue;
		}

		foreach ($needles as $needle) {
			$hits = find_needle_lines($lines, (string)$needle);
			if (!empty($hits)) {
				$violations[] = array(
					'rule' => (string)$rule['id'],
					'description' => (string)$rule['description'],
					'file' => $file,
					'needle' => (string)$needle,
					'lines' => $hits,
				);
			}
		}
	}
}

if (!empty($violations)) {
	echo "[guardrails_lab_billing] FAIL violations=" . count($violations) . "\n";
	foreach ($violations as $v) {
		$lines = isset($v['lines']) && is_array($v['lines']) ? implode(',', $v['lines']) : '';
		echo "- rule={$v['rule']} file={$v['file']} lines={$lines} needle=" . json_encode($v['needle']) . "\n";
	}
	@file_put_contents($root . DIRECTORY_SEPARATOR . 'guardrails_lab_billing_violations.json', json_encode($violations, JSON_PRETTY_PRINT));
	exit(1);
}

echo "[guardrails_lab_billing] PASS\n";
exit(0);
