#!/usr/bin/php
<?php

require_once 'parser.class.php';

//$s = 'voenmeh'; chdir($s);
//require_once 'parser.class.php';

$s = 'mephist'; chdir($s);
require_once 'parser.class.php';

$rasp = new $s();
$rasp->update();

passthru('gzip -k schedule.json');
