<?php
class install extends moon_com {

	function onload() {   //paruosiam formas

		//$this->myTable=$this->table('Rooms');
	}

	function events($event,$par) {
		if  ($event) {
			$this->set_var('do', $event);
		}
		$this->use_page('Common');
	}


	function main($vars) {
		$do = empty($vars['do']) ? '':$vars['do'];
        switch ($do) {

            case 'dbclean':
				$res = $this->dbClean() . '<br/>The End.';
				break;


			default:
				$res = '';
		}
		$t=&$this->load_template();
		$win=&moon::shared('admin');
		$win->active('sys.errors404');

		return '<h2>Tools...</h2><div style="clear:both">
		<ol>
		   <li><a href="'.$this->linkas('#dbclean').'">Cleanup database</a></li>
		</ol></div>' . $res;


	}



//importuoja kambarius
	function dbClean() {
    	$m = $this->db->array_query("show tables");

		$starts = array('w4b', 'jos_');
		$garbage = array();

		$s = '';
		foreach ($m as $d) {
			$table = $d[0];
			if (strpos($table, 'wtrink_') === 0) {
				continue;
			}

			$rename = FALSE;
			foreach ($starts as $v) {
				if (strpos($table, $v) === 0) {
					$rename = TRUE;
					break;
				}
			}
			if (!$rename && in_array($table, $garbage)) {
				$rename = TRUE;
			}
			if ($rename) {
				$this->db->query("RENAME TABLE $table TO wtrink_{$table}");
				//$this->db->query("DROP TABLE $table");
                $s .= $table . ' <i style="color:red">renamed</i><br/>';
			}
			else {
				$s .= $table . '<br/>';
			}
		}
		return $s;

	}

}

?>