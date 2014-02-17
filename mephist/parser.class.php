<?php
/**
 * @author CupIvan <mail@cupivan.ru>
 * @date 11.02.14
 */

class mephist extends parser
{
	protected $institute = 'Национальный исследовательский ядерный университет «МИФИ»';
	protected $domain    = 'http://timetable.mephist.ru';

	public function update()
	{
		$page = $this->download('/');

		if (preg_match('#groups-select1.+?</select>#is', $page, $m))
		if (preg_match_all('#value=.(\d+)#', $m[0], $m))
		foreach ($m[1] as $i => $groupId)
		{
			$start = date('U', mktime(0, 0, 0, date('m'), 1, 2014));
			$end   = $start + 3600*24*30*1; // на 1 месяц
			$page  = $this->download("/getEvents.php?groupId=$groupId&start=$start&end=$end");
			$this->parseGroup($page);
		}
		$this->makeJson();
	}

	private function parseGroup($st)
	{
		$json = json_decode($st, true);
		if (!$json) return false;

		$types = [
			'ЛР'  => self::T_LAB,
			'П/С' => self::T_PRACT,
			'Лек' => self::T_LEC,
		];
		$fac   = [
			'А' => 'Автоматики и электроники',
			'К' => 'Кибернетики и информационной безопасности',
			'Б' => 'Кибернетики и информационной безопасности',
			'Р' => 'Кибернетики и информационной безопасности',
			'В' => 'Очно-заочного обучения',
			'Е' => 'Высший физический колледж',
			'Т' => 'Экспериментальной и теоретической физики',
			'У' => 'Управления и экономики высоких технологий',
			'Ф' => 'Физико-технический',
			'С' => 'Высшая школа физиков им. Н.Г. Басова',
		];

		foreach ($json as $a)
		{
			$teachers  = $a['teachers'];
			$teachers  = preg_replace('#([А-Я])( |$)#u', '$1.$2', $teachers); // Булгакова Я П. -> Булгакова Я.П.
			$teachers  = preg_replace('#\.(\S)#',        '. $1',  $teachers); // Абов Ю.Г. -> Абов Ю. Г.
			$teachers  = explode(':', $teachers);
			$rooms     = explode(':', $a['auditories']);
			$a['type'] = $types[$a['type']];
			$a['from'] = $a['start'];
			$a['to']   = $a['end'];
			foreach (explode(':', $a['groups']) as $group)
			{
				$a['group']      = $group;
				$a['faculty']    = $fac[mb_substr($group, 0, 1)];
				$a['subject']    = $a['title'];
				$a['teachers']   = $teachers;
				$a['rooms']      = $rooms;
				$this->addSubject($a);
			}
		}
	}
}
