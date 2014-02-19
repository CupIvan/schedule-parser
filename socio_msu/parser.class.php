<?php
/**
 * @author CupIvan <mail@cupivan.ru>
 * @date 18.02.14
 */

require_once '../phil_msu/parser.class.php';
class socio_msu extends phil_msu
{
	protected $institute = 'МГУ им. М.В. Ломоносова (соцфак)';
	protected $domain    = 'http://cacs.socio.msu.ru';

	public function addSubject($a)
	{
		$a['faculty'] = str_replace('Философский', 'Социологический', $a['faculty']);
		parent::addSubject($a);
	}
}
