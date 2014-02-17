<?php
mb_internal_encoding('utf-8');

class parser
{
	private $faculties = [];

	const T_PRACT = 0; // практическое занятие (по-умолчанию)
	const T_LAB   = 1; // лабораторная работа
	const T_LEC   = 2; // лекция
	const T_SEM   = 4; // семинар
	const T_CONS  = 4; // консультация
	const T_OUT   = 5; // внеучебное занятие
	const T_ZACH  = 6; // зачет
	const T_EXAM  = 7; // экзамен

	public function update()
	{
		$this->prepare();
		$this->makeJson();
	}

	protected function download($url)
	{
		$dir = './cache/';
		if (!file_exists($dir)) mkdir($dir);

		$url   = $this->domain.$url;
		$md5   = md5($url);
		$fname = $dir.$md5;
		if (file_exists($fname) && time() - filemtime($fname) < 3600*24*14)
			return file_get_contents($fname);

		echo "Download: $url\n";
		$page = file_get_contents($url);
		if (stripos($page, 'windows-1251'))
			$page = iconv('cp1251', 'utf-8', $page);
		if ($page)
			file_put_contents($fname, $page);
		return $page;
	}

	protected function addSubject($a)
	{
		$f = @$a['faculty'];
		$g = @$a['group'];
		$w = @$a['weekday']; if (!$w) $w = 1;
		if (empty($this->faculties[$f]))         $this->faculties[$f]         = [];
		if (empty($this->faculties[$f][$g]))     $this->faculties[$f][$g]     = [];
		if (empty($this->faculties[$f][$g][$w])) $this->faculties[$f][$g][$w] = [];

		$this->faculties[$f][$g][$w][] = $a;
	}

	protected function makeJson()
	{
		$st = '';
		$st = "{\n\t'timestamp': ".time().",\n\t'institute_name': '".$this->encode($this->institute)."',\n".
			$this->genFaculties()."}\n";
		$st = preg_replace('#,(\s*]|})#s', '$1', $st);
		$st = preg_replace('#\'#s', '"', $st);
		$st = urldecode($st);
		file_put_contents('schedule.json', $st);
	}

	protected function genFaculties()
	{
		$st = ''; $offset = "\t";
		foreach ($this->faculties as $name => $groups)
		{
			$st .= "$offset{\n";
			$st .= "$offset\t'faculty_name': '".$this->encode($name)."',\n";
			$st .= $this->genGroups($groups);
			$st .= "$offset},\n";
		}
		return $st ? "$offset'faculties': [\n".$st."$offset]\n" : '';
	}

	protected function genGroups($groups)
	{
		$st = ''; $offset = "\t\t";
		foreach ($groups as $name => $days)
		{
			$st .= "$offset{\n";
			$st .= "$offset\t'group_name': '".$this->encode($name)."',\n";
			$st .= $this->genDays($days);
			$st .= "$offset},\n";
		}
		return $st ? "$offset'groups': [\n".$st."$offset]\n" : '';
	}

	protected function genDays($days)
	{
		$st = ''; $offset = "\t\t\t";
		foreach ($days as $day => $a)
		{
			$st .= "$offset{\n";
			$st .= "$offset\t'weekday': $day,\n";
			$st .= $this->genLessons($a);
			$st .= "$offset},\n";
		}
		return $st ? "$offset'days': [\n".$st."$offset]\n" : '';
	}

	protected function genLessons($lessons)
	{
		$st = ''; $offset = "\t\t\t\t";
		foreach ($lessons as $a)
		{
			if (!empty($a['from']))
			{
				if (empty($a['to'])) $a['to'] = $a['from'] + 1.5*3600; // стандартная длительность пары 1:30
				$a['time_start'] = date('H:i', $a['from']);
				$a['time_end']   = date('H:i', $a['to']);
				if ($a['from'] > 3600*24)
				{
					$a['date_start'] = date('d.m.Y', $a['from']);
					if (!empty($a['to']))
					$a['date_end']   = date('d.m.Y', $a['to']);
				}
			}

			if (!empty($a['date']))
			{
				$a['date_start'] = $a['date'];
				$a['date_end']   = $a['date'];
			}

			$x = [];
			if (!empty($a['teacher'])) $a['teachers'] = [$a['teacher']];
			if (!empty($a['teachers']))
			foreach ($a['teachers'] as $k => $v)
			if ($v)
				$x[] = ['teacher_name'=>urlencode($v)];
			$a['teachers'] = $x;

			$x = [];
			if (!empty($a['room'])) $a['rooms'] = [$a['room']];
			if (!empty($a['rooms']))
			foreach ($a['rooms'] as $k => $v)
			if ($v)
				$x[] = ['auditory_name'=>$this->encode($v), 'auditory_address'=>NULL];
			$a['auditories'] = $x;

			$a['subject'] = $this->encode($a['subject']);
			$a['type']    = (int)$a['type'];

			$default = array_fill_keys(explode(',', 'type,time_start,time_end,parity,'
				.'date_start,date_end,dates,auditories,subject,teachers'), NULL);

			$a  = array_merge($default, $a);
			$a  = array_intersect_key($a, $default);
			$st .= "$offset\t".json_encode($a).",\n";
		}
		return $st ? "$offset'lessons': [\n".$st."$offset]\n" : '';
	}
	/** кодирование строк */
	private function encode($st)
	{
		return urlencode(str_replace(["\\", "\n", '"'], ["\\\\", "\\n", '\\"'], $st));
	}
}
