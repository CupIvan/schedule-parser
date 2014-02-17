<?php
/**
 * @author CupIvan <mail@cupivan.ru>
 * @date 11.02.14
 */

class mpei extends parser
{
	protected $institute = 'Национальный исследовательский университет «МЭИ»';
	protected $domain    = 'http://www.mpei.ru';

	private $uni   = '';
	private $group = '';

	public function prepare()
	{
		$page = $this->download('/AU/au_toc.asp?hiet_id=4&personmode=leafonly&showhie=foremployee&showhie=forstudent&showhie=forgradstudent');

		if (preg_match_all('#href="(.[^"]+)"><img.{0,200}title="(.+?) \[(.+?)\]-\(Институт#su', $page, $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$x = array();
			$x['url']   = str_replace('&amp;', '&', preg_replace('#\s#', '', $a[1]));
			$x['title'] = $a[2];
			$x['abbr']  = $a[3];

			$this->uni = $x['abbr'].' ('.$x['title'].')';

			if (strpos($x['title'], '- архив')) continue;
			$this->parse_uni($x);
		}
	}

	/** парсер страницы института */
	private function parse_uni($uni, $only_groups = false)
	{
		$page = $this->download($uni['url']);

		if (preg_match_all('#href="([^"]+).{0,100}RPlus.gif.{0,100}title="(\d курс|Радио|Электро|Менеджм|Эконом)#s', $page, $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$a['url']   = str_replace('&amp;', '&', preg_replace('#\s#', '', $a[1]));
			$a['title'] = $a[2];

			if (preg_match('/^\D/', $a['title'])) $this->parse_uni($a, true);
			else $this->parse_course($a);
		}
	}
	/** парсер страницы курса: 1 курс .. 6 курс */
	private function parse_course($a)
	{
		$page = $this->download($a['url']);

		if (preg_match_all('#>(?<title>[^<]+).{0,40}'
			.'href="(?<url1>/AU/TimeTable[^"]+).{0,200}?(?<type1>[a-z]+).GIF'
			.'(.{0,300}href="(?<url2>/AU/TimeTable[^"]+).{0,200}?(?<type2>[a-z]+).GIF)?'.
			'#si', $page, $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$this->group = $a['title'];

			$a['url']  = $a['url1'];
			$a['type'] = $a['type1'];
			$this->parse_table_ex($a);
			if (isset($a['url2']))
			{
				$a['url']  = $a['url2'];
				$a['type'] = $a['type2'];
				$this->parse_table_ex($a);
			}
		}
	}

	/** запуск парсера страницы с расписанием */
	private function parse_table_ex($a)
	{
		if ($a['type'] == 'studyTableDef') $this->parse_table($a);
		if ($a['type'] == 'examTableDef')  $this->parse_exam($a);
	}

	/** парсер страницы с расписанием занятий */
	private function parse_table($group)
	{
		$page = $this->download($group['url']);

		$page = substr($page,    strpos($page, 'class="SchedGrid'));
		$page = substr($page, 0, strpos($page, '</table'));
		$page = substr($page,    strpos($page, '1 пара') - 100);

		$g = array(); $gx = $sx = 0; $day = 0;
		$pair = [
			'faculty' => $this->uni,
			'group'   => $this->group,
		];

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
				$pair['time_start'] = $m[1][0];
				$pair['time_end']   = $m[1][1];
				if (preg_match('#ttIntervalNameLabel"><nobr>([^<]+)#', $a[2], $m))
					$pair['subject'] = $m[1];
				$gx = 0; $sx = 0;
				continue;
			}

			$res = $this->parse_cell($a[2]);
			$pair['weekday'] = floor($gx / 2) + 1;

			for ($i = 0; $i < count($res); $i++)
			{
				$res[$i] += $pair;
				$this->update_cell($res[$i], $gx, $sx);
				$this->addSubject($res[$i]);
			}
		}
	}

	/** парсер ячейки расписания */
	private function parse_cell($st)
	{
		$res = array();
		$a = explode('ttElement">', $st);
		for ($i = 1; $i < count($a); $i++)
		{
			if (preg_match('#ttStudyName">([^<]+)#s',            $a[$i], $m)) $res[$i-1]['subject'] = $m[1];
			if (preg_match('#ttStudyKindName">([^<]+)#s',        $a[$i], $m)) $res[$i-1]['type']    = $m[1];
			else $res[$i-1]['type'] = 'Лекция';
			if (preg_match('#ttAuditorium">(.+?)</span>#s',      $a[$i], $m)) $res[$i-1]['room']    = strip_tags($m[1]);
			if (preg_match('#ttLectureLink".+?title="([^"]+)#s', $a[$i], $m)) $res[$i-1]['teacher'] = $m[1];
			if (preg_match('#ttRemark">(.+?)</span>#s',          $a[$i], $m)) $res[$i-1]['comment'] = strip_tags($m[1]);
		}
		return $res;
	}

	/** парсер страницы с расписанием экзаменов */
	private function parse_exam($group)
	{
		$page = $this->download($group['url']);
		$page = preg_replace('#.+<table(.+?)</table>.+#s', '$1', $page);
		if (preg_match_all(
			'#'
			.'.+?ttWeekDate[^<]+<nobr>(?<time>\d.+?)</nobr>'
			.'.+?ttStudyName">(?<subject>[^<]+)'
			.'.+?KindName">(?<type>[^<]+)'
			.'.+?ttLectureLink.+?title="(?<teachers>[^"]+)'
			.'.+?ttAuditorium.+?">(?<rooms>[^<]+)'
			.'#s',
			$page, $m, PREG_SET_ORDER))
		{
			$st = '';
			foreach ($m as $i => $v)
			{
				$t = strtotime($m[$i]['time']);
				if ($t < time()) continue;
				$a = [
					'faculty' => $this->uni,
					'group'   => $this->group,
					'weekday' => 1,
					'from'    => date('d.m.Y', $t),
				];
				$this->update_cell($a, 0, 0);
				$this->addSubject($a);
			}
		}
	}

	/** модификация полей ячейки с учётом API расписания */
	private function update_cell(&$a, $gx, $sx)
	{
		if (!empty($a['comment']) && isset($a['subject']))
			$a['subject'] .= ' ('.trim($a['comment']).')';

		switch (@$a['type'])
		{
			case 'практ.':       $a['type'] = self::T_PRACT; break;
			case 'лаб.':         $a['type'] = self::T_LAB;   break;
			case 'Лекция':       $a['type'] = self::T_LEC;   break;
			case 'Семинар':      $a['type'] = self::T_SEM;   break;
			case 'Консультация': $a['type'] = self::T_CONS;  break;
			case 'Экзамен':      $a['type'] = self::T_EXAM;  break;
			default: $a['type'] = 0;
		}

		$a['parity'] = ($sx == 2) ? 0 : ($gx%2 + 1); // 1 - чётные недели, 2 - нечётные
	}
}
