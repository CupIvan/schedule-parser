#!/usr/bin/php
<?php

require_once 'parser.class.php';

$a = ['law_msu', 'mpei', 'voenmeh', 'mephist'];
foreach ($a as $institute)
{
	chdir($institute);
	require_once 'parser.class.php';

	$rasp = new $institute();
	$rasp->update();

	passthru('gzip -k --force schedule.json');

	break; // for test only first insitute
}
