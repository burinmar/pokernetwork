<?php


class page404 extends moon_com {


	function main($vars = array()) {
		$this->forget();
		// kad nelogintu klaidos á failà, kas jau ir taip bus loginama db
		$err = & moon :: error();
		if ($err->count_errors('n')) {
			foreach ($err->err_msgs as $i=>$msg){
                if (strpos($msg, 'N;Nepavyko perduoti veiksmo') === 0) {
                	unset($err->err_msgs[$i]);
					$err->kiek_n--;
                }
			}
		}
		// gal turime kur redirektinti?
		//$this->redirect();
		header("HTTP/1.0 404 Not Found", TRUE, 404);
		$p = & moon :: page();
		$u = & moon :: user();
		$t = & $this->load_template();
		$info = $t->explode_ini('info');
		$p->title($info['title']);
		$p->set_local('nobanners', 1);
		$this->log404();
		$a = array();
		$a['uri'] = htmlspecialchars($_SERVER['REQUEST_URI']);
		$a['uri_short'] = substr($_SERVER['REQUEST_URI'], 0, 50);
		$a['uri_short'] = strlen($_SERVER['REQUEST_URI']) > 50 ? $a['uri_short'] . '...' : $a['uri_short'];
		$a['home_url'] = $p->home_url();
		return $t->parse("main", $a);
	}


	function log404() {
		$a['date'] = time();
		$a['uri'] = isset ($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['REQUEST_URI'];
		$a['referer'] = isset ($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		$a['agent'] = isset ($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$u = & moon :: user();
		$a['ip'] = $u->get_ip();
		$this->db->insert_query($a, $this->table('Errors404'));
	}


	function redirect() {
		$uri = (isset ($_SERVER['REQUEST_URI'])) ? urldecode($_SERVER['REQUEST_URI']) : '';
		list($uri) = explode('?', $uri);
		if (!in_array($uri, array('/', '/register/js.php', '/live-reporting/ajax.batch.js', '/banners/get-data.php', '/banners/track-views.php'))) {
			$eUri = $this->db->escape($uri);
			$sql = "
				SELECT uri_to	FROM pages_redirects
				WHERE
					uri_from = '$eUri' OR
					uri_from = CONCAT(LEFT('$eUri', LENGTH(uri_from)-1),'*')
				ORDER BY LENGTH(uri_from) ASC LIMIT 1
			";
			$a = $this->db->single_query($sql);
            if (!empty($a[0])) {
            	$page = & moon :: page();
            	$page->redirect($a[0], 301);
            }
		}
	}


}

?>