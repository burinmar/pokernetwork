<?php
class dictionary extends moon_com {

function events($event, $par)
{
	$page = &moon::page();
//	$seg = $page->uri_segments();
	
	if(isset($par[0]) && $par[0]) {
		$this->set_var('term', urldecode($par[0]));
	}

	switch($event) {
		case 'xml_term_of_the_day':
			header('Content-Type: text/xml; charset=UTF-8');
			moon_close(); echo $this->xmlTermOfTheDay(); die();
		case 'term':
			$a = $this->getRandomTerm();
			header('Content-Type: text/plain; charset=UTF-8');
			$homeURL = $page->home_url();
			$now = gmdate('md');
			$r = $this->db->array_query('SELECT name, description_html, usg_html, uri FROM ' . $this->table('Dictionary') . ' ORDER BY CRC32(concat(name, '. $now . ')) LIMIT 2');
			echo str_replace('|', '&#124', trim(strip_tags($r[0][0]))) . '|' . str_replace(array('^','|'), array('&#94','&#124'), trim(strip_tags($r[0][1]))) . ($r[0][2] ? '^' . str_replace(array('^','|'), array('&#94','&#124'), trim(strip_tags($r[0][2]))) : '') . '|' . str_replace('|', '&#124', trim(strip_tags($r[1][0]))) . '|' . $r[1][3];
			moon_close(); die();
		case 'ajax-suggest': 
			if(!(isset($_GET['s']) || $_GET['s']=trim($_GET['s']))) {
				print ''; die;
			}
			$suggestions = $this->findSuggestion($_GET['s']);
			$items = '';
			foreach($suggestions as $k=>$v) {
				$uri = $this->linkas('#',$v['uri']);
				$items .= '<li><a class="item" href="'.$uri.'">'.$v['name'].'</a></li>';
			}
			print '<ul class="suggestion-list">'.$items.'</ul>';

			die;
			break;
		default:
			break;
	}

	$this->use_page('Dictionary');
}
function properties()
{
	$vars = array();
	$vars['term'] = '';
	return $vars;
}
function main($vars)
{
	$page = &moon::page();
	$t = &$this->load_template();
	$page->js('/js/ajaxSuggestions.js');
	$page->css('/css/terms.css');

	$sitemap = & moon :: shared('sitemap');
	$pageInfo = $sitemap->getPage();

	$main = array();
	$main['title'] = $pageInfo['meta_title'];
	$main['uri'] = $pageInfo['uri'];
	$d = explode('/', $pageInfo['uri']);
	foreach ($d as $k=>$v) {
		$d[$k] = urlencode($v);
	}
	$main['ajaxuri'] = implode('/', $d);
	$main['goNextTerm'] = '';
	$main['goPrevTerm'] = '';
	if($vars['term']) {
		$term = $this->getItemByUri($vars['term']);
		if($term) {
			$m = array();
			$main['title'] = $term['name'];
			$m['description'] =($term['description_html'])?$term['description_html']:'';
			$m['usage'] = $term['usg_html'];
			$main['info'] = $t->parse('term_info', $m);

			$page->title($term['name']. ' | ' .$sitemap->getTitle());
			$sitemap->breadcrumb(array(''=>$term['name']));
			
			$nextItem = $this->getNextItem($term['name']);
			if($nextItem) {
				$main['goNextTerm'] = $this->linkas('#').$nextItem['uri'].'.htm';
				$main['nextTerm'] = $nextItem['name'];
			}
			
			$prevItem = $this->getPrevItem($term['name']);
			if($prevItem) {
				$main['goPrevTerm'] = $this->linkas('#').$prevItem['uri'].'.htm';
				$main['prevTerm'] = $prevItem['name'];
			}
			
		}else{
			//not found
			$main['info'] = 'not found';
		}
	}else{
		$main['info'] = $pageInfo['content_html'];
	}
	
	$main['abc'] = '';
	$lettersArr = $this->getLetters();
	foreach($lettersArr as $letter) {
		$main['abc'] .= $t->parse('letter', array('letter'=> strtoupper($letter['letter'])));
	}
	
	$main['boxItems'] = '';
//	$main['jsPokerTerms'] = '';
	$termsArr = $this->getAllTermsList();	//crazy isn't it O_o
	$tItems = '';
	$currLetter = strtoupper(mb_substr($termsArr[0]['name'],0,1,'UTF-8'));
	$cntTerms = count($termsArr);
	foreach($termsArr as $k=>$term) {
		if($currLetter != strtoupper(mb_substr($term['name'],0,1,'UTF-8')) ) {
			$box = array();
			$box['letter'] = $currLetter;
			$box['items'] = $tItems;
			$main['boxItems'] .= $t->parse('term_box',$box);

			$tItems = '';
			$currLetter = strtoupper(mb_substr($term['name'], 0, 1,'UTF-8'));
		}
		$m = array();
		$m['title'] = $term['name'];
		$m['goTerm'] = $this->linkas('#').$term['uri'].'.htm';
		$tItems .= $t->parse('term_item',$m);
//		$main['jsPokerTerms'] .= 'pokerTerms["'.$term['uri'].'"]= "'.addslashes($term['name']).'";';
		
	}
	if($tItems) {
		$box = array();
		$box['letter'] = $currLetter;
		$box['items'] = $tItems;
		$main['boxItems'] .= $t->parse('term_box',$box);
	}

	$page->meta('robots', 'index,follow');

	return $t->parse('main', $main);
}

function xmlTermOfTheDay()
{
	$isLocal = is_local_version();
	$oCache = &moon::cache();
	$oCache->on(!$isLocal);
	$result = $oCache->get('xml_term_of_the_day');
	if ($result) {
		return $result;
	}

	$randomTerm = $this->getRandomTerm();
	$xmlWriter = new moon_xml_write;
	$xmlWriter->encoding('utf-8');
	$xmlWriter->open_xml();
	$xmlWriter->start_node('pokernews');
	foreach ($randomTerm as $nodeName => $nodeValue) {
		$xmlWriter->node($nodeName, '', $nodeValue);
	}
	$xmlWriter->end_node('pokernews');
	$xml = $xmlWriter->close_xml();
	$oCache->save($xml, '30m');

	return $xml;
}

function getRandomTerm()
{
	$page = &moon::page();
	$homeURL = $page->home_url();
	$now = gmdate('md');
	$dbResult = $this->db->array_query_assoc('SELECT id, name, description_html, usg_html, uri FROM ' . $this->table('Dictionary') . ' ORDER BY CRC32(concat(name, '. $now . ')) LIMIT 2');
	$result = array();
	$u = $homeURL . ltrim($this->linkas('#'), '/');
	foreach ($dbResult as $key => $term) {
		if ($key == 0) {
			$result['id1'] = $term['id'];
			$result['term'] = $term['name'];
			$result['description'] = trim(strip_tags($term['description_html']));
			$result['usage'] = trim(strip_tags($term['usg_html']));
			$result['url'] = $u;
			$result['url1'] = $u . $term['uri'] . '.htm';
		} else {
			$result['id2'] = $term['id'];
			$result['term2'] = $term['name'];
			$result['url2'] = $u . $term['uri'] . '.htm';
		}
	}
	return $result;
}

function getItemByUri($uri)
{
	$sql = 'SELECT name, description_html, usg_html
		FROM ' . $this->table('Dictionary') . '
		WHERE uri = \'' . $this->db->escape(htmlspecialchars($uri)) . '\'';
	$result = $this->db->single_query_assoc($sql);
	return (!empty($result)) ? $result : FALSE;
}

function getNextItem($term) {
	$sql = "SELECT name, uri FROM " . $this->table('Dictionary') . " WHERE name > '".$this->db->escape($term)."' ORDER BY name ASC LIMIT 1";
	$result = $this->db->single_query_assoc($sql);
	return $result;
}

function getPrevItem($term) {
	$sql = "SELECT name, uri FROM " . $this->table('Dictionary') . " WHERE name < '".$this->db->escape($term)."' ORDER BY name DESC LIMIT 1";
	$result = $this->db->single_query_assoc($sql);
	return $result;
}

function getLetters() {
	// LEFT(name,1)        substring(name from 1 for 1)
	$sql = 'SELECT DISTINCT LEFT(name,1) as letter
		FROM ' . $this->table('Dictionary') . '
		ORDER BY letter ASC';
	$result = $this->db->array_query_assoc($sql);
	return $result;
}

function getAllTermsList() {
	$sql = 'SELECT name, uri
		FROM ' . $this->table('Dictionary') . '
		ORDER BY name ASC';
	$result = $this->db->array_query_assoc($sql);
	return $result;
}

function findSuggestion($s) {
	$sql = "SELECT name, uri
		FROM " . $this->table('Dictionary') . " 
		WHERE name LIKE ('%". $this->db->escape($s)."%') ORDER BY name ASC LIMIT 0,12 ";
	return $this->db->array_query_assoc($sql);
}


}

/**********
CREATE TABLE `dictionary` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `uri` VARCHAR(50) NOT NULL DEFAULT '',
  `name` VARCHAR(50) NOT NULL DEFAULT '',
  `description` TEXT,
  `description_html` TEXT,
  `usg` TEXT,
  `usg_html` TEXT,
      
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `uri` (`uri`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8;



INSERT INTO dictionary (id, uri, NAME, description, description_html, usg, usg_html)
SELECT id, uri_new, NAME, description_raw, description_html, usg_raw, usg_html FROM dictionary_en;

*/
?>