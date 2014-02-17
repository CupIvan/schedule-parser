#!/usr/bin/php
<?php

require_once 'parser.class.php';

$s = 'voenmeh'; chdir($s);
require_once 'parser.class.php';

$rasp = new $s();
$rasp->update();
