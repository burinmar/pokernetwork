<?php
class shared_rss extends moon_com {

function shared_rss($path)
{
	$moon=&moon::engine();
	$this->tpl=&$moon->load_template(
		$path.'shared_rss.htm',
		$moon->ini('dir.multilang').'shared/shared_rss.txt'
		);
	$p = & moon::page();
	$this->homeURL =  $p->home_url();

	$this->init();
}

function init()
{
	$this->feed = array();
	$this->feedType = false;
	$this->items = array();
	$this->url = '';
}

//***************************************
//           --- MAIN METHODS ---
//***************************************

// returns feed content from cache or FALSE
function feed($feedURL, $feedType='rss',$cacheOn=true)
{
	$this->url = $feedURL;
	$this->feedType = in_array($feedType, array('rss','atom')) ? $feedType : 'rss';

	$this->cache = & moon::cache();
	$this->cache->on($cacheOn);
	$cname = $this->feedType . '_' . md5($feedURL);
	$c = $this->cache->get($cname);
	return $c;
}

// sets feed info
function info($feedInfo)
{
	$this->feed = is_array($feedInfo) ? $feedInfo : array();
	$this->feed += array(
		'title' => '',
		'description' => '',
		'url:page' => '',
		'author' => ''
	);
}

// sets one feed item
function item($a)
{
	if (!is_array($a)) $a = array();
	$this->items[] = $a + array(
		'title' => '',
		'url' => '',
		'url:comments' => '',
		'created' => '',
		'updated' => '',
		'author' => '',
		'summary' => '',
		'content' => '',
		'summary:html' => '',
		'content:html' => ''
	);
}

//sends feed header
function header()
{
    /*if ($this->feedType === 'atom') {
		//header('Content-type: application/atom+xml');
		header('Content-type: text/xml');
	} else {
		header('Content-type: text/xml');
	}*/
	header('Content-type: text/xml; charset=utf-8');
}

// generates feed content
function content()
{
	//klaida, nes nera feed informacijos
	if ($this->feedType === false || !count($this->feed)) {
		return '';
	}
	$loc=&moon::locale();
	$t = & $this->tpl;

	$m = $this->feed;
	if (!isset($m['url:feed'])) $m['url:feed'] = $this->url;
	foreach ($m as $k=>$v) $m[$k] = htmlspecialchars($v);

	if ($this->feedType === 'atom') {
		$tpl='atom';
		$timeFormat = defined('DATE_ATOM') ? DATE_ATOM : 'Y-m-d\TH:i\Z';
	} else {
		$tpl='rss';
		$timeFormat= defined('DATE_RSS') ? DATE_RSS : 'r';//'D, d M Y H:i:s \G\M\T';
	}

	$m['sarasas']='';
	if (count($dat = $this->items)) {
	//******* SARASAS **********
		foreach ($dat as $d) {
			if ($d['updated']) $d['updated']=gmdate($timeFormat, $d['updated']) ;
			if ($d['created']) $d['created']=gmdate($timeFormat, $d['created']) ;
			$d['title']=htmlspecialchars($d['title']);
			$d['url']=htmlspecialchars($d['url']);
			$d['author']=htmlspecialchars($d['author']);
			$d['url:comments']=htmlspecialchars($d['url:comments']);
			if ($d['summary:html']) {
				$d['summary:html']=htmlspecialchars($this->fixContent($d['summary:html']));
			} else {
				$d['summary']=htmlspecialchars($d['summary']);
			}
			if ($d['content:html']) {
				$d['content:html']=htmlspecialchars($this->fixContent($d['content:html']));
			} else {
				$d['content']=htmlspecialchars($d['content']);
			}
			$m['sarasas'].=$t->parse($tpl.'_item',$d);
		}
	}
	$m['updated'] = date($timeFormat);

	$res = $t->parse($tpl.'_main',$m);
	$this->cache->save($res, '10m');
	return $res;
}

//***************************************
//           --- DB ir KITA ---
//***************************************


// htmliniam kontente pakoreguoja linkus
function fixContent($s)
{
	if (strpos($s,' onclick')) $s=preg_replace('/\sonclick="([^"]*)"/','',$s);
	$s=str_replace(' src="/',' src="' . $this->homeURL, $s);
	$s=str_replace(' href="/',' href="' . $this->homeURL, $s);
	return $s;
}

}
?>