<?php

class stats extends moon_com {

	function events($event, $par) {

		switch ($event) {
			case 'remote':
				$r = $this->getStatsForRemote($par[0]);
				$page = moon::page();
				$page->set_local('transporter', $r);
				break;
		}
		$this->use_page("Common", "");
	}
	
	function main($vars) {
		$p = &moon::page();
	    $t = &$this->load_template();
		$win = &moon::shared("admin");
		$win->active($this->my("fullname"));
		$win->subMenu();
		
		$main = array('items'=>'','years'=>'');

		$dat = $this->getStats();
		$years = array_keys($dat);
		sort($years);
        foreach ($years as $y) {
			$main['years'].="<th class=\"tar\"><span>$y</span></th>";
		}
        $locale=&moon::locale();
		$months = $locale->months_names("m");
		$d=array();
		for ($m=1;$m<13;$m++) {
			$td='';
			$t0='<td align="left">'.$months[$m].'</td>';
			foreach ($years as $y) {
            	$td.= isset($dat[$y][$m]) ? "<td>".$dat[$y][$m]."</td>":'<td style="color:#f8f8f6;">0</td>';
			}
			$d['style']="td".(($m % 2) +1).'p';
			$d['row']=$t0.$td.$t0;
			$main['items'].=$t->parse('row',$d);
		}
		$res = $t->parse("main", $main);
		return $res;
	}


	//---//

	function getStats() {
		$sql =
		"SELECT
			DATE_FORMAT(created, '%Y-%m') AS `year_month`,
			COUNT(*) AS `registered`
		FROM `".$this->table('Users')."`
		WHERE status = 1
		GROUP BY `year_month`";

		$result = array();
		$dbResult = $this->db->query($sql);
		while ($row = $this->db->fetch_row_assoc($dbResult)) {
			list($year, $month) = explode("-", $row["year_month"]);
			$result[$year][(int)$month] = $row["registered"];
		}
		return $result;
	}


}

?>