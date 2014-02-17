<?php
/**
 * @author CupIvan <mail@cupivan.ru>
 * @date 10.02.14
 */

class voenmeh extends parser
{
	protected $institute = 'БГТУ «ВОЕНМЕХ» им. Д.Ф. Устинова';
	protected $domain    = 'http://voenmeh.ru';

	public function prepare()
	{
		$page = $this->download('/students/timetable/TimetableGroup.xml');

		$xml = new SimpleXmlElement($page);
		foreach ($xml->Group as $xmlGroup)
			$this->parseGroup($xmlGroup);
	}

	private function parseGroup($xmlGroup)
	{
		$group = (string)$xmlGroup->attributes()->Number;

		$a = [
			'А' => 'Ракетно-космической техники',
			'Р' => 'Международного промышленного менеджмента и коммуникации',
			'И' => 'Информационные и управляющие системы',
			'К' => 'Энергетического машиностроения',
			'Н' => 'Мехатроника и управление',
			'Е' => 'Оружие и системы вооружения',
		];
		$faculty = @$a[mb_substr($group, 0, 1)];
		if (!$faculty) $faculty = 'Остальные группы';

		if ($xmlGroup->Days)
		foreach ($xmlGroup->Days->Day as $xmlDay)
		foreach ($xmlDay->GroupLessons->Lesson as $xmlLesson)
		{
			$teachers = [];
			if ($xmlLesson->Lecturers)
			foreach ($xmlLesson->Lecturers->Lecturer as $lecturer)
				$teachers[] = (string)$lecturer->ShortName;

			$a = [
				'Понедельник' => 1,
				'Вторник'     => 2,
				'Среда'       => 3,
				'Четверг'     => 4,
				'Пятница'     => 5,
				'Суббота'     => 6,
			];
			$day = $a[(string)$xmlLesson->DayTitle];

			$time_str = explode(' ', (string)$xmlLesson->Time)[0];

			$type = 0;
			$subject = (string)$xmlLesson->Discipline;
			$a = [
				'пр '  => self::T_PRACT,
				'лек ' => self::T_LEC,
				'лаб ' => self::T_LAB,
			];
			foreach ($a as $k => $v)
			if (strpos($subject, $k) === 0) { $type = $v; $subject = substr($subject, strlen($k)); }

			$this->addSubject([
				'faculty'    => $faculty,
				'group'      => $group,
				'weekday'    => $day,
				'subject'    => $subject,
				'teachers'   => $teachers,
				'time_start' => date('H:i', strtotime($time_str)),
				'time_end'   => date('H:i', strtotime($time_str) + 1.5*3600),
				'parity'     => (int)$xmlLesson->WeekCode,
				'type'       => $type,
				'rooms'      => [preg_replace('/;\s*$/', '', (string)$xmlLesson->Classroom)],
			]);
		}
	}
}
