<?php
/**
 * Парсер сайта МЭИ(НИУ) для программы "Расписания ВУЗов"
 * @url http://raspisaniye-vuzov.ru
 * @author CupIvan <mail@cupivan.ru>
 * @date 11.01.14
 */

define('HOST', 'http://www.mpei.ru');

ob_start();
update();
$st = ob_get_clean();
$st = str_replace("'", '"', $st);
$st = preg_replace("#,(\s*[\]}])#s", '$1', $st);
echo $st;

/** скачивание страницы */
function page($url)
{
	if (!is_dir('cache')) mkdir('cache');
	$f = './cache/'.md5($url).'.html';
	if (file_exists($f)) return file_get_contents($f);
	$page = file_get_contents(HOST.$url);
	$page = iconv('cp1251', 'utf-8', $page);
	if ($page)
		file_put_contents($f, $page);
	return $page;
}

/** получение списка институтов */
function update()
{
	$page = page('/AU/au_toc.asp?hiet_id=4&personmode=leafonly&showhie=foremployee&showhie=forstudent&showhie=forgradstudent');
	if (preg_match_all('#href="(.[^"]+)"><img.{0,200}title="(.+?) \[(.+?)\]-\(Институт#su', $page, $m, PREG_SET_ORDER))
	{
		echo "{'timestamp': ".time().", 'faculties':[\n";
		foreach ($m as $a)
		{
			$x = array();
			$x['url']   = str_replace('&amp;', '&', preg_replace('#\s#', '', $a[1]));
			$x['title'] = $a[2];
			$x['abbr']  = $a[3];

			if (strpos($x['title'], '- архив')) continue;
			parse_uni($x);
		}
		echo "]}\n";
	}
}

/** парсер страницы института */
function parse_uni($uni, $only_groups = false)
{
	$page = page($uni['url']);

	ob_start();

	if (preg_match_all('#href="([^"]+).{0,100}RPlus.gif.{0,100}title="(\d курс|Радио|Электро|Менеджм|Эконом)#s', $page, $m, PREG_SET_ORDER))
	foreach ($m as $a)
	{
		$a['url'] = str_replace('&amp;', '&', preg_replace('#\s#', '', $a[1]));
		$a['title'] = $a[2];

		if (preg_match('/^\D/', $a['title'])) parse_uni($a, true);
		else parse_course($a);
	}

	if ($st = ob_get_clean())
	{
		if ($only_groups) { echo $st; return; }
		echo "{'faculty_name': '".$uni['abbr'].' ('.$uni['title'].")',\n'groups':[\n$st\n]},\n";
	}
}

/** парсер страницы курса: 1 курс .. 6 курс */
function parse_course($a)
{
	$page = page($a['url']);

	if (preg_match_all('#>(?<title>[^<]+).{0,40}'
		.'href="(?<url1>/AU/TimeTable[^"]+).{0,200}?(?<type1>[a-z]+).GIF'
		.'(.{0,300}href="(?<url2>/AU/TimeTable[^"]+).{0,200}?(?<type2>[a-z]+).GIF)?'.
		'#si', $page, $m, PREG_SET_ORDER))
	{
		foreach ($m as $a)
		{
			$a['url']  = $a['url1'];
			$a['type'] = $a['type1'];
			parse_table_ex($a);
			if (isset($a['url2']))
			{
				$a['url']  = $a['url2'];
				$a['type'] = $a['type2'];
				parse_table_ex($a);
			}
		}
	}
}

/** запуск парсера страницы с расписанием */
function parse_table_ex($a)
{
	ob_start();
	if ($a['type'] == 'studyTableDef') parse_table($a);
	if ($a['type'] == 'examTableDef')  parse_exam($a);
	$data = ob_get_clean();
	if (!$data) return false;

	echo "\t{'group_name': '".$a['title']."', 'days':[\n";
	echo $data;
	echo "\t]},\n";
}

/** парсер страницы с расписанием занятий */
function parse_table($group)
{
	$page = page($group['url']);

	$page = substr($page, strpos($page, 'class="SchedGrid'));
	$page = substr($page, 0, strpos($page, '</table'));
	$page = substr($page, strpos($page, '1 пара') - 100);

	$g = array(); $gx = $sx = 0; $pair = array(); $day = 0;

	$listByDays = array(); $dsz = array();

	if (preg_match_all('#<td(.*?)>(.+?)</td>#s', $page, $m, PREG_SET_ORDER))
	foreach ($m as $a)
	{
		$gx += $sx;
		while (!empty($dsz[$gx])) $gx += 2;

		$sx = $sy = 1;
		if (preg_match('#colspan="(\d+)#', $a[1], $_)) $sx = $_[1];
		if (preg_match('#rowspan="(\d+)#', $a[1], $_)) $sy = $_[1];

		// пропускаем лишние ячейки
		if (strpos($a[2], 'День самостоятельных занятий'))
		{
			$dsz[$gx] = true;
			continue;
		}
		if (strpos($a[2], 'Обед')) continue;
		if (strlen($a[2]) < 10) continue;

		if (preg_match_all('#ttIntervalHoursLabel">([\d:]+)#', $a[2], $m))
		{
			$pair['date_start'] = NULL;
			$pair['date_end']   = NULL;
			$pair['dates']      = NULL;
			$pair['name'] = '';
			$pair['time_start'] = $m[1][0];
			$pair['time_end']   = $m[1][1];
			if (preg_match('#ttIntervalNameLabel"><nobr>([^<]+)#', $a[2], $m))
				$pair['name'] = $m[1];
			$gx = 0; $sx = 0;
			continue;
		}

		$res = parse_cell($a[2]);
		$day  = floor($gx / 2) + 1;

		for ($i = 0; $i < count($res); $i++)
		{
			$res[$i] += $pair;
			update_cell($res[$i], $gx, $sx);
			if (empty($listByDays[$day])) $listByDays[$day] = array();
			array_push($listByDays[$day], $res[$i]);
		}
	}

	if (!$listByDays) return false;

	foreach ($listByDays as $day => $a)
	{
		echo "\t\t{'weekday': $day, 'lessons': ";
		echo json_encode($a);
		echo "},\n";
	}
}

/** парсер ячейки расписания */
function parse_cell($st)
{
	$res = array();
	$a = explode('ttElement">', $st);
	for ($i = 1; $i < count($a); $i++)
	{
		if (preg_match('#ttStudyName">([^<]+)#s',            $st, $m)) $res[$i-1]['subject'] = $m[1];
		if (preg_match('#ttStudyKindName">([^<]+)#s',        $st, $m)) $res[$i-1]['type']    = $m[1];
		else $res[$i-1]['type'] = 'Лекция';
		if (preg_match('#ttAuditorium">(.+?)</span>#s',      $st, $m)) $res[$i-1]['room']    = strip_tags($m[1]);
		if (preg_match('#ttLectureLink".+?title="([^"]+)#s', $st, $m)) $res[$i-1]['teacher'] = $m[1];
		if (preg_match('#ttRemark">(.+?)</span>#s',          $st, $m)) $res[$i-1]['comment'] = strip_tags($m[1]);
	}
	return $res;
}

/** парсер страницы с расписанием экзаменов */
function parse_exam($group)
{
	$page = page($group['url']);
	$page = preg_replace('#.+<table(.+?)</table>.+#s', '$1', $page);
	if (preg_match_all(
		'#'
		.'.+?ttWeekDate[^<]+<nobr>(?<time>\d.+?)</nobr>'
		.'.+?ttStudyName">(?<subject>[^<]+)'
		.'.+?KindName">(?<type>[^<]+)'
		.'.+?ttLectureLink.+?title="(?<teacher>[^"]+)'
		.'.+?ttAuditorium.+?">(?<room>[^<]+)'
		.'#s',
		$page, $m, PREG_SET_ORDER))
	{
		$st = '';
		foreach ($m as $i => $v)
		{
			$t = strtotime($m[$i]['time']);
			if ($t < time()) continue;
			$a = array();
			$a['date_start'] = date('d.m.Y', $t);
			$a['time_start'] = date('H:i', $t);
			$a += array_intersect_key($m[$i], array_fill_keys(['subject','type','teacher','room'], 1));
			update_cell($a, 0, 0);
			$st .= "\n".json_encode($a).',';
		}
		if ($st)
		echo "\t\t{'weekday': 1, 'lessons': [$st\n]},\n";
	}
}

/** модификация полей ячейки с учётом API расписания */
function update_cell(&$a, $gx, $sx)
{
	unset($a['name']);

	if (!empty($a['comment']) && isset($a['subject'])) { $a['subject'] .= ' ('.$a['comment'].')'; unset($a['comment']); }

	if (!empty($a['teacher']))
	$a['teachers'] = array(array('teacher_name' => $a['teacher']));
	unset($a['teacher']);

	if (!empty($a['room']))
	$a['auditories'] = array(array('auditory_name' => $a['room'], 'auditory_address' => NULL));
	unset($a['room']);

	switch (@$a['type'])
	{
		case 'практ.':       $a['type'] = 0; break;
		case 'лаб.':         $a['type'] = 1; break;
		case 'Лекция':       $a['type'] = 2; break;
		case 'Семинар':      $a['type'] = 3; break;
		case 'Консультация': $a['type'] = 4; break;
		case 'Экзамен':      $a['type'] = 7; break;
		default: $a['type'] = 0;
	}

	$a['parity'] = ($sx == 2) ? 0 : ($gx%2 + 1); // 1 - чётные недели, 2 - нечётные
}
