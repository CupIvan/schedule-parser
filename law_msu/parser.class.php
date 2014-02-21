<?php
/**
 * @author CupIvan <mail@cupivan.ru>
 * @date 12.02.14
 */

class law_msu extends parser
{
	protected $institute = 'МГУ им. М.В. Ломоносова (юрфак)';
	protected $domain    = 'http://cacs.law.msu.ru';

	public function prepare()
	{
		$page = $this->download('/ttablestd/index/1');
		$this->look1column($page);
	}

	/** перебираем факультеты */
	private function look1column($page)
	{
		if (preg_match('#col1st.+?<li.+?</div>#s', $page, $m))
		if (preg_match_all('#(/ttablest[^"]+)">([^<]+)#s', $m[0], $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$this->faculty = 'Юридический факультет ('.$a[2].')';
			$this->faculty = str_replace(' юридического факультета', '', $this->faculty);
			$this->look2column($this->download($a[1]));
		}
	}

	/** перебираем специальности */
	private function look2column($page)
	{
		if (preg_match('#col2st.+?<li.+?</div>#s', $page, $m))
		if (preg_match_all('#(/ttablest[^"]+)">([^<]+)#s', $m[0], $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$this->look3column($this->download($a[1]));
		}
	}

	/** перебираем курсы */
	private function look3column($page)
	{
		if (preg_match('#col3st.+?<li.+?</div>#s', $page, $m))
		if (preg_match_all('#(/ttablest[^"]+)">([^<]+)#s', $m[0], $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$this->look4column($this->download($a[1]));
		}
	}

	/** перебираем группы */
	private function look4column($page)
	{
		if (preg_match('#col4st.+?<li.+?</div>#s', $page, $m))
		if (preg_match_all('#(/ttablest[^"]+)">([^<]+)#s', $m[0], $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$this->group = $a[2];
			$this->parseGroup($this->download($a[1]));
		}
	}

	/** парсим страницу с расписанием */
	private function parseGroup($page)
	{

		// находим время пар
		$time = []; $timeCk = 0;
		if (preg_match_all('#blockdaybodygr'
			.'.+?title="(?<time>[\d:-]+)"'
			.'#s', $page, $m, PREG_SET_ORDER))
		foreach ($m as $a)
			$time[] = explode('-', $a['time']);

		// парсим дни
		if (preg_match_all('#class="block">'
			.'.+?blockhead[^>](.+?)(?<date>[\d.]+)'
			.'.+?blockbody.+?>(?<data>.+?)</div>'
			.'.+?</div>#s', $page, $m, PREG_SET_ORDER))
		foreach ($m as $a)
		{
			$date = $a['date'];
			if (preg_match('#<p>.+?>(?<subject>.+?)<'
				.'.*?\[(?<type>.+?)</font>'
				.'.*?ауд\.(?<room>.+?)</font>'
				.'.*?<br/>(?<teachers>.+?)<br/>'
				.'#s', $a['data'], $m))
			{
				switch (strip_tags($m['type']))
				{
					case 'Лк':  $m['type'] = self::T_LEC;   break;
					case 'Пз':  $m['type'] = self::T_PRACT; break;
					case 'Лб':  $m['type'] = self::T_LAB;   break;
					case 'Сем': $m['type'] = self::T_SEM;   break;
					default:
						echo "Unknown type: ".$m['type']."\n";
				}

				if (preg_match('/Вирт. гр.:.+\)\s*(.+?)\s*$/', $m['teachers'], $m_))
				{
					$m['subject'] .= ' ('.$m_[1].')';
					$m['teachers'] = '';
				}

				$m = array_merge($m, [
					'time_start' => $time[$timeCk][0],
					'time_end'   => $time[$timeCk][1],
					'room'       => trim(strip_tags($m['room'])),
					'teachers'   => explode(', ', $m['teachers']),
					'date'       => $date,
					'faculty'    => $this->faculty,
					'group'      => $this->group,
				]);
				$this->addSubject($m);
			}

			if (++$timeCk >= count($time)) $timeCk = 0;
		}
	}
}
