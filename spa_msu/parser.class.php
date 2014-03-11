<?php
/**
 * @author CupIvan <mail@cupivan.ru>
 * @date 28.02.14
 */

require_once '../law_msu/parser.class.php';
class spa_msu extends law_msu
{
	protected $institute = 'МГУ им. М.В. Ломоносова (госуправ)';
	protected $domain    = 'http://cacs.spa.msu.ru';

	public function addSubject($a)
	{
		$a['faculty'] = str_replace('Юридический', 'Государственного управления', $a['faculty']);
		parent::addSubject($a);
	}
}
