#!/usr/bin/php
<?php

passthru('find ./cache/* -mtime +7 -delete');
passthru('php ./update.php > mpei.json');

$x = file_get_contents('mpei.json');
$x = str_replace("'", '"', $x);
$res = json_decode($x);

if (!$res)
	echo 'ERROR: '.json_last_error_msg()."\n";
else
	echo "OK\n";
