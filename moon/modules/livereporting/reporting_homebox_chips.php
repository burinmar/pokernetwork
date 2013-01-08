<?php
/**
 * @package livereporting
 */
/**
 * @package livereporting
 */
class reporting_homebox_chips extends moon_com
{
	function main()
	{
		moon::error('reporting_homebox_chips is still used, apparently');
		$lrep = $this->object('livereporting');
		$eventID = is_dev() ? 737 : 722;
		$dayID = $lrep->instEventModel('_src_event_core')->getDaysDefaultId($eventID);
		$chips = $lrep->instEventModel('_src_event_chips')->getLastTodayChips($eventID, $dayID);
		$res = '';

		if (count($chips)) {
			$tpl = $this->load_template();
			$m = array('chips'=>'');
			$d = array();
			foreach ($chips as $i=>$v) {
				if ($i>9) {
					break;
				}
				$d['nr'] = $i + 1;
				$d['chips'] = number_format($v['chips']);
				$d['imgc'] = $v['chipsc']<0 ? 'neg' : 'pos';
				$d['chipsc'] = $v['chipsc'] ? number_format(abs((int)$v['chipsc'])) : '';
				$d['name'] = htmlspecialchars($v['name']);
				$d['sponsor'] = $v['sponsor_id'] == 53 ? 1 : 0;
				$m['chips'] .= $tpl->parse('chips', $d);
			}
			$res = $tpl->parse('main', $m);
		}

		//print_r($chips);die('***');
		return $res;
	}
}
