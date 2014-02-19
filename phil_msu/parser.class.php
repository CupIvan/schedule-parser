<?php
/**
 * @author CupIvan <mail@cupivan.ru>
 * @date 18.02.14
 */

class phil_msu extends parser
{
	protected $institute = 'МГУ им. М.В. Ломоносова (филфак)';
	protected $domain    = 'http://cacs.philos.msu.ru';

	public function prepare()
	{
		$page = $this->download('/');
		$this->look1column($page);
	}

	/** перебираем факультеты */
	private function look1column($page)
	{
		if (preg_match('#id=TblSELVZ.+?</table>#s', $page, $m))
		if (preg_match_all('#(\?f=\d+)>(.+?)</a>#s', $m[0], $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$this->faculty = 'Философский факультет ('.strip_tags(str_replace(' ФФ', '', $a[2])).')';
			$this->look2column($this->download('/'.$a[1]));
		}
	}

	/** перебираем направления */
	private function look2column($page)
	{
		if (preg_match('#Направления/программы.+?</table>#su', $page, $m))
		if (preg_match_all('#(\?sp=\d+)#s', $m[0], $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$this->look3column($this->download('/'.$a[1]));
		}
	}

	/** перебираем года набора */
	private function look3column($page)
	{
		if (preg_match('#name=.yr.+?</select>#s', $page, $m))
		if (preg_match_all('#<option value=(\d+)#s', $m[0], $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$this->look4column($this->download('/?yr='.$a[1]));
		}
	}

	/** перебираем группы */
	private function look4column($page)
	{
		if (preg_match('#id=TblSELVZ.+?</table>#s', $page, $m))
		if (preg_match_all('#(\?gr=\d+).*?>(.+?)</a>#s', $m[0], $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$this->group = strip_tags($a[2]);
			$this->parseGroup($this->download('/'.$a[1]));
		}
	}

	/** парсим страницу с расписанием */
	private function parseGroup($page)
	{
		if (!preg_match("#'MnuAcs'.+?</select>.+?(<table.+?)<!--END#s", $page, $m)) return;
		if (preg_match_all('#<table.+?</table>#s', $m[1], $m, PREG_SET_ORDER))
		foreach ($m as $a)
			$this->parseCell($a[0]);
	}

	/** парсим ячейку таблицы */
	private function parseCell($st)
	{
		$time1 = ['09:00', '10:45', '12:55', '14:40', '16:25', '18:00', '19:40'];
		$time2 = ['10:30', '12:15', '14:25', '16:10', '17:55', '19:30', '21:10'];
		if (preg_match('#>(\d+\.\d+\.\d+)</td>#s', $st, $m)) $date = $m[1];

		if (preg_match_all('#<td.+?</td>#s', $st, $m, PREG_SET_ORDER))
		foreach ($m as $i => $a)
		if (preg_match_all('#LESS'
			.'.+?title="(?<type>.+?) по \'(?<subject>.+?)\''
			.'.+?<br><b>(?<room>.*?)</b>'
			.'.+?<br>(?<teacher>.*?)</div>'
			.'#s', $a[0], $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			switch ($a['type'])
			{
				case 'Лекция':               $a['type'] = parser::T_LEC;   break;
				case 'Контактные часы':      $a['type'] = parser::T_PRACT; break;
				case 'Семинар':              $a['type'] = parser::T_SEM;   break;
				case 'Практическое занятие': $a['type'] = parser::T_PRACT; break;
				default: $this->error('Unknown type: '.$a['type']);
			}
			$a['date'] = $date;
			$a['time_start'] = $time1[$i-1]; // COMMENT: $i-1, т.к. первая ячейка с датой
			$a['time_end']   = $time2[$i-1];
			$a['faculty']    = $this->faculty;
			$a['group']      = $this->group;
			$this->addSubject($a);
		}
	}
}
