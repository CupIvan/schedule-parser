<?php

mb_internal_encoding('utf-8');

include_once 'excel_reader2.php';

ob_start();

echo "{'timestamp': ".time().", 'faculties':[\n";
foreach(scandir('./cache/') as $fname)
if (strpos($fname, '.xls'))
{
	if     (strpos($fname, 'Agro')   !== false) $faculty = 'Агрономический факультет';
	elseif (strpos($fname, 'PAE')    !== false) $faculty = 'Факультет почвоведения, агрохимии и экологии';
	elseif (strpos($fname, 'SAD')    !== false) $faculty = 'Факультет садоводства и ландшафтной архитектуры';
	elseif (strpos($fname, 'ZOO')    !== false) $faculty = 'Зооинженерный факультет';
	elseif (strpos($fname, 'Ekon')   !== false) $faculty = 'Экономический факультет';
	elseif (strpos($fname, 'UchFin') !== false) $faculty = 'Учетно-финансовый факультет';
	elseif (strpos($fname, 'GumPed') !== false) $faculty = 'Гуманитарно-педагогический факультет';
	elseif (strpos($fname, 'TEX')    !== false) $faculty = 'Технологический факультет';
	else { echo "Unknow faculty: $fname!\n"; continue; }

	echo "\n{'faculty_name': '$faculty', 'date_start': null, 'date_end': null, 'groups': [\n";

	$g_xls = new Spreadsheet_Excel_Reader("cache/$fname");
	$sheets = count($g_xls->sheets);
	for ($i = 0; $i < $sheets; $i++)
	{
		$a = parse_sheet($g_xls, $g_sheet = $i);
		$data = parse_file($a);
		draw_groups($data);
	}
	echo "]},\n";
}
echo "]}\n";

$st = ob_get_clean();
$st = str_replace("'", '"', $st);
$st = preg_replace('#,(\s*\])#', '$1', $st);
echo $st;

// -------------------------------------------------

function xls_rowspan($row, $col)
{
	$x = $GLOBALS['g_xls']->rowspan($row, $col, $GLOBALS['g_sheet']);
	return $x;
}

function parse_sheet($xls, $sheet)
{
	$a = array();
	$rows = $xls->rowcount($sheet);
	$cols = $xls->colcount($sheet);

	$max_row = 0; $max_col = 0;

	for ($row = 1; $row <= $rows; $row++)
	{
		$a[$row] = array();
		for ($col = 1; $col <= $cols; $col++)
		{
			$a[$row][$col] = preg_replace("#\s+#", ' ', $xls->value($row, $col, $sheet));
			if ($a[$row][$col])
			{
				if ($max_col < $col) $max_col = $col;
				if ($max_row < $row) $max_row = $row;
			}
		}
		$cols = $max_col + 3;
	}
	return $a;
}

function draw_groups($data)
{
	foreach ($data as $group => $a_)
	{
		echo "\t{'group_name': '$group', 'days': [\n";
		foreach ($a_ as $day => $b_)
		{
			echo "\t\t{'weekday': $day, 'lessons': [";
			foreach ($b_ as $time => $c_)
			{
				$time = explode('-', str_replace('.', ':', $time));
				foreach ($c_ as $a)
				{
					$a['time_start'] = $time[0];
					$a['time_end']   = $time[1];

					$a['date_start'] = $a['date_end'] = $a['dates'] = NULL;
					if (empty($a['teachers'])) $a['teachers'] = NULL;

					$x = $a['auditories']; $a['auditories'] = array();
					foreach ($x as $v)
					if ($v)
						array_push($a['auditories'], array('auditory_name' => $v, 'auditory_address' => NULL));
					if (empty($a['auditories'])) $a['auditories'] = NULL;

					$x = $a['teachers']; $a['teachers'] = array();
					foreach ($x as $v)
					if ($v)
						array_push($a['teachers'], array('teacher_name' => $v));
					if (empty($a['teachers'])) $a['teachers'] = NULL;

					if (empty($a['parity'])) $a['parity'] = 0;

					echo json_encode($a).',';
				}
			}
			echo "\t]},\n";
		}
		echo "\t]},\n";
	}
}

function parse_file($f)
{
	$faculty = '';
	$course  = '';
	$group   = '?';
	$day = 0;

	$facOffset = array(); $list = array();

	foreach ($f as $i => $a)
	{
		switch (trim(@$a[1]))
		{
			case 'ПОНЕДЕЛЬНИК': $day = 1; break;
			case 'ВТОРНИК':     $day = 2; break;
			case 'СРЕДА':       $day = 3; break;
			case 'ЧЕТВЕРГ':     $day = 4; break;
			case 'ПЯТНИЦА':     $day = 5; break;
		}

		foreach ($a as $j => $st)
		{
			if (strpos($st, 'Часы'))        { $groupOffset = array(); }
			if (preg_match('#\d-\d#', $st)) { $timeOffset  = array(); }

			if ($st == 'Часы') $groupOffset[$j] = trim($a[$j+1]);
			if (!empty($groupOffset[$j])) $group = $groupOffset[$j];

			if (!$day) continue;

			if (!isset($list[$group]))       $list[$group]       = array();
			if (!isset($list[$group][$day])) $list[$group][$day] = array();

			if (preg_match('#(\d+\.\d+)-(\d+\.\d+)#', $st, $m))
			{
				$time = $m[0];
				if ($s = find_subject($i, $j, $f))
					$list[$group][$day][$time] = $s;
			}
		}
	}
	return $list;
}

function find_subject($i, $j, $a)
{
	$res = array();
	$s = find_subject_week($i, $j, 1, $a); if ($s) array_push($res, $s);
	$s = find_subject_week($i, $j, 2, $a); if ($s) array_push($res, $s);
	return $res;
}

function find_subject_week($i, $j, $parity, $a)
{
	$res = array();
	if ($parity == 2) $i += 2;
	if (!$st = @$a[$i][$j+1]) return NULL;

	if (xls_rowspan($i, $j+1) == 2) // объединено 2 ячейки - занятие только по конкретной неделе
		$res['parity'] = $parity;
	$DD = isset($res['parity']) ? 1 : 2; // размер ячейки с аудиторией

	if ($st != ($st_ = str_replace('пр.',  '', $st))) { $st = $st_; $res['type'] = 0; }
	if ($st != ($st_ = str_replace('лаб.', '', $st))) { $st = $st_; $res['type'] = 1; }
	if ($st != ($st_ = str_replace('лек.', '', $st))) { $st = $st_; $res['type'] = 2; }
	$st = str_replace('По выбору: ', '', $st);

	$r1 = trim(@$a[$i]    [$j+3]);
	$r2 = trim(@$a[$i+$DD][$j+3]);
	if ($r1 == 'СК') $r1 = 'Спортивный комплекс';

	$t1 = fio(@$a[$i]    [$j+2]);
	$t2 = fio(@$a[$i+$DD][$j+2]);

	$r = array($r1);
	if ($r2 && $r1 != $r2) $r[1] = $r2;

	$t = array($t1);
	if ($t2 && $t1 != $t2) $t[1] = $t2;

	$res['subject']    = $st;
	$res['auditories'] = $r;
	$res['teachers']   = $t;

	return $res;
}

function fio($st)
{
	$st = mb_strtolower(trim($st));
	$st = preg_replace_callback('#^.|.\.#u', function($x){ return mb_strtoupper($x[0]); }, $st);
	return $st;
}
