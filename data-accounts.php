<?php

// Required files
require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/models/twitter.php';
require_once __DIR__ . '/includes/functions.php';

$start = microtime(true);

$t = new DateTime('NOW');
$t->modify('+1 Hour');

echo "-- Starting at " . $t->format('Y-m-d\TH:i:s.u') . "\n\n";

if (isset($argv[1]) && isset($argv[2])) {
	getHashtagDataFromAccounts($argv[1], $argv[2]);
} else {
	getHashtagDataFromAccounts();
}

$t = new DateTime('NOW');
$t->modify('+1 Hour');

$end = microtime(true) - $start;

echo "\n\n-- Done in $end seconds, at " . $t->format('Y-m-d\TH:i:s.u') . "\n";