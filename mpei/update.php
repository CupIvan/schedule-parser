<?php
/**
 * Парсер сайта МЭИ(ТУ) для программы "Расписания ВУЗов"
 * @url http://raspisaniye-vuzov.ru
 * @author CupIvan <mail@cupivan.ru>
 * @date 08.10.13
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
		echo "{'faculty_name': '".$uni['title']."',\n'groups':[\n$st\n]},\n";
	}
}

/** парсер страницы курса: 1 курс .. 6 курс */
function parse_course($a)
{
	$page = page($a['url']);

	if (preg_match_all('#>([^<]+).{0,40}href="(/AU/TimeTable[^"]+)#s', $page, $m, PREG_SET_ORDER))
	{
		foreach ($m as $a)
		{
			$a['url']   = $a[2];
			$a['title'] = $a[1];
			parse_table($a);
		}
	}
}

/** парсер страницы с расписанием */
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

		$res  = parse_cell($a[2]);
		$res += $pair;

		unset($res['name']);

		if (!empty($res['teacher']))
		$res['teachers'] = array(array('teacher_name' => $res['teacher']));
		unset($res['teacher']);

		if (!empty($res['room']))
		$res['auditories'] = array(array('auditory_name' => $res['room'], 'auditory_address' => NULL));
		unset($res['room']);

		switch (@$res['type'])
		{
			case 'практ.':       $res['type'] = 0; break;
			case 'лаб.':         $res['type'] = 1; break;
			case 'Лекция':       $res['type'] = 2; break;
			case 'Семинар':      $res['type'] = 3; break;
			case 'Консультация': $res['type'] = 4; break;
			case 'Экзамен':      $res['type'] = 7; break;
			default: $res['type'] = 0;
		}

		$day  = floor($gx / 2) + 1;
		$res['parity'] = ($sx == 2) ? 0 : ($gx%2 + 1); // 1 - чётные недели, 2 - нечётные

		if (empty($listByDays[$day])) $listByDays[$day] = array();
		array_push($listByDays[$day], $res);
	}

	if (!$listByDays) return false;

	echo "\t{'group_name': '".$group['title']."', 'days':[\n";

	foreach ($listByDays as $day => $a)
	{
		echo "\t\t{'weekday': $day, 'lessons': ";
		echo json_encode($a);
		echo "},\n";
	}
	echo "\t]},\n";
}

/** парсер ячейки расписания */
function parse_cell($st)
{
	$res = array();
	if (preg_match('#ttStudyName">([^<]+)#s',            $st, $m)) $res['subject'] = $m[1];
	if (preg_match('#ttStudyKindName">([^<]+)#s',        $st, $m)) $res['type']    = $m[1];
	if (preg_match('#ttAuditorium">.+?">([^<]+)#s',      $st, $m)) $res['room']    = $m[1];
	if (preg_match('#ttLectureLink".+?title="([^"]+)#s', $st, $m)) $res['teacher'] = $m[1];
	return $res;
}
