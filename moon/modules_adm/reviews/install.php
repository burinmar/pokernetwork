<?php
class install extends moon_com {

	function onload() {   //paruosiam formas

		$this->myTable=$this->table('Rooms');
		$this->oldDB = is_dev() ? 'pokerworks_old_frpoker2113' : 'frpoker2113';
		$this->newDB = is_dev() ? 'pokerworks_fr' : 'frpoker2113b';
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
			case 'tables':
				$res = $this->createTables();
				break;

            case 'data':
				$res = $this->importData(TRUE) . '<br/>The End.';
				break;

            case 'convert' :
				$res = $this->convertContent() . '<br/>The End.';
				break;

			case 'recompile' :
				$res = $this->recompile() . '<br/>The End.';
				break;
			default:
				$res = '';
		}
		$p=&moon::page();
		$loc=&moon::locale();
		$t=&$this->load_template();
		$win=&moon::shared('admin');
		$win->active('reviews.reviews');
		$folders = array(
			//$this->get_dir('dirRooms'),
			//$this->get_dir('dirDeposit'),
			$this->get_dir('dirAttachments'),
			//$this->get_dir('dirGallery'),
			//$this->get_dir('dirTeam'),
			);
		foreach ($folders as $k=>$v) {
			if (is_writable($v)) {
				$folders[$k] = '<strike style="color:#999">'.$v.'</strike>';
			}
		}
		$isTables = $this->tablesExists();
		$f1 = glob($this->get_dir('dirAttachments').'*.*');
		//$f2 = glob(_W_DIR_ . 'reviews/attachments/*.*');
        $isFiles = count($f1)  ? TRUE : FALSE;
		//foreach(glob($this->get_dir('dirAttachments').'*.*') as $file) unlink($file);
		//$isFiles =
		return '<h2>Install Reviews</h2><div style="clear:both">
		<ol>
			<li>Create writable folders:<ul style="list-style:none"><li>'.implode('</li><li>',$folders).'</li></ul></li>
			<li>
			'.
			(!$isTables ? '<a href="'.$this->linkas('#tables').'">Create tables</a>':'Create tables (done)').
			'</li>
			<li>
			'.
			($this->importData(FALSE) ? '<a href="'.$this->linkas('#data').'">Import data</a>':'Import data (done)').
			'</li>
			<li>' . (!$this->importData(FALSE) ? '<a href="' . $this->linkas('#convert') . '">Convert texts</a>' : 'Install data first...') . '</li>
			<li>' . (!$this->importData(FALSE) ? '<a href="' . $this->linkas('#recompile') . '">Recompile texts</a>' : 'Install data first...') . '</li>
			<li>Sync data from adm.pokernews.com to this site</li>

		</ol></div>' . $res;


	}

	/* Instaliavimas **/

	function makeTxt($html)
	{
		$s = str_replace(
			array('<b>','</b>','<i>','</i>','<u>','</u>','<li>','</ul>','<br />','</li>',"\r",'<strong>','</strong>',),
			array('[B]','[/B]','[I]','[/I]','[U]','[/U]','[*]','[/LIST]',"\n\n",'','','[B]','[/B]'),
			$html);
        $s = preg_replace('/<a([\s]+)href="([^"]+)"?([^>]+)>(.*?)<\/a>/sm',
						'[URL="$2"]$4[/URL]',		$s);
        $s = preg_replace('/<ul([^>]*)>/sm',
						"[LIST]\n",		$s);
        $s = preg_replace('/[\n]{3,}/sm',
						"\n\n",		$s);
		if (strpos($s,'[LIST]') !== FALSE && !strpos($s,'[/LIST]')) {
			$s .= '[/LIST]';
		}
		return $s;
	}



function tablesExists()
{
	$m = $this->db->single_query("show tables like 'rw2_reviews'");
	return (count($m) ? TRUE : FALSE);
}

function createTables()
{
	if ($this->tablesExists()) {
		//jei egzistuoja
		return 'Tables already exists!';
	}

	$s='';
	$sqlArr = array();


    $sqlArr['rw2_trackers'] = "
    CREATE TABLE `rw2_trackers` (
		`parent_id` INT(11) NOT NULL,
		`alias` VARCHAR(50) NOT NULL,
		`uri` VARCHAR(200) DEFAULT NULL,
		`uri_download` VARCHAR(200) DEFAULT NULL,
		`bonus_code` VARCHAR(60) DEFAULT NULL,
		`iframe` TINYINT(1) NOT NULL DEFAULT '1',
		PRIMARY KEY  (`parent_id`,`alias`)
		)
	";


	foreach($sqlArr as $k=>$v) {
		$r = $this->db->query("DROP TABLE IF EXISTS `$k`");
	}
	foreach($sqlArr as $k=>$v) {
		$r = $this->db->query($v);
		$s.= "SQL#".$k.' - '.($r ? 'OK': 'Error '.mysql_error()).'<br/>';
	}
	return "<br/><br/><p>MYSQL</p> $s <p>Completed</p>";

}

function importData($realImport = FALSE) {
	//check if exist
	$m = $this->db->single_query("select count(*) FROM `rw2_reviews`");
	$empty = empty($m[0]);
    if (!$realImport) {
    	return $empty;
    }
    if (!$empty) {
		//jei egzistuoja
		return 'Data already imported!';
	}

	//old data
	$db = $this->db();
	$db->select_db($this->oldDB);
    $old = $db->array_query_assoc('SELECT * FROM rooms WHERE visible=1');

    // new database
	$db->select_db($this->newDB);
	$rooms = $db->array_query('SELECT old_id,id FROM rw2_rooms WHERE old_id>0', TRUE);

	$res = '';
	$now = time();
	$table = 'rw2_reviews';
	foreach ($old as $d) {
		if (isset($rooms[$d['room_id']])) {
			$roomID = $rooms[$d['room_id']];
		}
		else {
			$res .= "<br/>Room not found: " . $d['room_name'] .  "\n";
			continue;
		}
		//Review
		$ins = array('room_id'=>$roomID, 'recompile'=>1,'updated'=>$now);
		//Review: summary
		$ins['page_id'] = 1;
		$ins['meta_title'] = $d['title'];
		$ins['meta_keywords'] = $d['keywords'];
		$ins['meta_description'] = $d['meta'];
		$ins['content_html'] = '<h2>Overview</h2> ' . $d['full_description'] ."\n\n". $d['review'];
        if (strlen($ins['content_html'])>10) {
			$db->insert($ins, $table);
		}
		//Review: bonus
		$ins['page_id'] = 2;
		$ins['meta_title'] = $d['bonus_title'];
		$ins['meta_keywords'] = $d['bonus_keywords'];
		$ins['meta_description'] = $d['bonus_meta'];
		$ins['content_html'] = $d['bonus_content'];
        if (strlen($ins['content_html'])>10) {
			$db->insert($ins, $table);
		}
		//Review: install
		$ins['page_id'] = 3;
		$ins['meta_title'] = $d['installation_title'];
		$ins['meta_keywords'] = $d['installation_keywords'];
		$ins['meta_description'] = $d['installation_meta'];
		$ins['content_html'] = $d['installation_content'];
        if (strlen($ins['content_html'])>10) {
			$db->insert($ins, $table);
		}
		//Review: tournaments
		$ins['page_id'] = 4;
		$ins['meta_title'] = $d['tournaments_title'];
		$ins['meta_keywords'] = $d['tournaments_keywords'];
		$ins['meta_description'] = $d['tournaments_meta'];
		$ins['content_html'] = $d['tournaments_content'];
        if (strlen($ins['content_html'])>10) {
			$db->insert($ins, $table);
		}

		//kiti duomenys
		$ins = array();
		$ins['bonus_terms'] = $this->makeTxt($d['bonus_terms']);
		$ins['bonus_text'] = $d['bonus'];
		$db->update($ins, 'rw2_rooms',$roomID);

	}

	return $res;
}


function convertContent() {
    	//return;
    	set_time_limit(6000);
		$this->f = & moon :: file();
		$limit = 10;
		do {

	    	$sql = 'SELECT room_id,page_id, content_html  FROM rw2_reviews WHERE content IS NULL LIMIT ' . $limit;
			$r = $this->db->query($sql);
			$end = TRUE;
			while ($a = $this->db->fetch_row_assoc($r)) {
				$end = FALSE;
				$this->db->update(array('content'=>$this->html2text($a['content_html'], $a['room_id'], $a['page_id'])), 'rw2_reviews', array('room_id'=>$a['room_id'],'page_id'=>$a['page_id']));
			}
			echo '.';
			flush();
			//$end = TRUE;
        } while (!$end);
		/*foreach ($this->attachments as $v) {
			$this->db->insert($v, 'spaudai_attachments');
		}*/

    }

	function attachment($v) {
		$attachmentID = $this->db->insert($v, 'rw2_attachments', TRUE);
		return $attachmentID;
	}

function html2text($s, $parentID, $layer) {
        static $wasImg = array();
		$this->debug(__LINE__);
    	$nobr = array();
		$this->debug(__LINE__);
        $s = str_replace(
			array("\r","\n"),
			'',
			$s);
		$s = str_ireplace(
			array('<BR>','<BR />','<BR/>', '<BR.', '<BER>','<.BR>','<BR'),
			"\n",
			$s);
		$s = str_replace('h3>',"h2>",	$s);
		$s = str_replace('</p>',"\n\n",	$s);
		$s = str_replace('</div>',"\n\n",	$s);
		$this->debug(__LINE__);
		$s = preg_replace("/<(div|p)([^>]*)>/m", "\n\n", $s);
        $this->debug(__LINE__);
		if ($layer == 3) {
			$s = preg_replace("~<(tr|td|tbody|table|/tr|/td|/tbody|/table)([^>]*)>~m", "", $s);
			$s = preg_replace("~<table([^>]*)>~m", "", $s);
		}

		//--tables
		preg_match_all('/<table(.*)<\/table>?/smi',$s.' ',$d);
		//--$m=array_unique($d[0]);
		//--rsort($m,SORT_STRING);

		foreach ($d[0] as $k=>$v) {
			//-- echo htmlspecialchars($d[2][$k]),'<br>';
			$aid = $this->attachment( array('parent_id'=>$parentID, 'layer'=>$layer, 'content_type'=>2, 'comment' => $v));
			$s = str_replace($v, "\n\n{id:" . $aid . "}\n\n", $s);
		}
        $this->debug(__LINE__);


		$s = str_replace('href= ','href=',	$s);
		$s = str_replace(
			array('<b>','</b>','<i>','</i>','<em>','</em>','<li>','</ul>','</ol>','<br />','</li>','BR>', '<h2>', '</h2>'),
			array('[B]','[/B]','[I]','[/I]','[I]','[/I]','[*]',"\n[/LIST]\n\n","\n[/LIST]\n\n","\n\n",'',"\n\n", "\n\n[H]","[/H]\n\n"),
			$s);
        if(strpos($s, '<')!==FALSE) {
			$s = str_ireplace(
			array('<b>','</b>','<i>','</i>','<u>','</u>','<li>','</ul>','</ol>','<br />','</li>','<strong>','</strong>','<sup>','</sup>'),
			array('[B]','[/B]','[I]','[/I]','[U]','[/U]','[*]',"\n[/LIST]\n\n","\n[/LIST]\n\n","\n\n",'','[B]','[/B]','[SUP]','[/SUP]'),
			$s);
		}
		$this->debug(__LINE__);
        $s = preg_replace('/<a([\s]+)href="mailto\:([^"]+)"?([^>]+)>(.*?)<\/a>/smi',
						'$4',		$s);
		$this->debug(__LINE__);
        $s = preg_replace('/<a([\s]+)href="([^"]+)"?([^>]+)>(.*?)<\/a>/smi',
						'[URL="$2"]$4[/URL]',		$s);
		$this->debug(__LINE__);
        $s = preg_replace('/<ul([^>]*)>/sm',
						"[LIST]\n",		$s);
		$this->debug(__LINE__);
		$s = preg_replace('/<ol([^>]*)>/sm',
						"[LIST=1]\n",		$s);
		$this->debug(__LINE__);
		if (strrpos($s,'[LIST]') !== FALSE && !strpos($s,'[/LIST]',strrpos($s,'[LIST]')+1)) {
			$s .= '[/LIST]';
		}
		$this->debug(__LINE__);
        // -- $s = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $s);
    	// -- $s = preg_replace('~&#([0-9]+);~e', 'chr("\\1")', $s);
		if (strpos($s, '&')!==FALSE) {
    		$s = html_entity_decode ($s,ENT_QUOTES, 'UTF-8');
		}
        $this->debug(__LINE__);
		/* --- $s = str_replace(array('&scaron;', '&Scaron;', '&nbsp;', '&bdquo;', '&ldquo;', '&quot;', '&ndash;', '&rdquo;', '&hellip;', '&lsquo;', '&rsquo;', '&euro;', '&amp;', '&pound;', '&ordm;', '&gt;', '&lt;', '<o:p>', '</o:p>', '&middot;', '?', '&bull;', '&mdash;'),
		array('ð', 'Ð', ' ', '„', '“', '"', '–', '”', '…', '‘', '’', '€', '&', '£', '?', '>', '<','','', '·', '', '•', '—'), $s);*/
		$s = str_replace('&quot;','"',$s);

		//img
		preg_match_all('/<img([\s]+)src="([^"]+)"?([^>]+)>/sm',$s.' ',$d);
		// -- $m=array_unique($d[0]);
		// -- rsort($m,SORT_STRING);
        $this->debug(__LINE__);
		foreach ($d[0] as $k=>$v) {
            $url = trim($d[2][$k]);
			//if ($url == 'http://pokerworks.com/gallery/Image/partypoker/50free_review.png') {
			if ($url == 'http://hr.pokerworks.com/gallery/Image/partypoker/hr_2.png') {
				$s = str_replace($v, '', $s);
				continue;
			}
			if (strpos($url,'http://pokerworks.com/') ===0) {
				$url = 'http://pokerworks.com/beta/' . substr($url,22);
			}
			if (isset($wasImg[$url])) {
				$wasImg[$url]++;
                if ($wasImg[$url]>3) echo $url, ' no ',$wasImg[$url], '<br />';
			}
			else $wasImg[$url]=1;
			$name = uniqid('') . '.' . $this->f->file_ext($url);
			$dir = 'w/rw-attachments/';
			$f = & $this->f;
			//echo $url, "<br/>";
			//continue;
			if ($f->is_url_content($url,$dir . $name, 15)) {
				$fName=$f->strip_extension();
				$fExt=$f->file_ext();
				$fileO=$dir.$f->file_name();
				$img = &moon::shared('img');
				$newO = $newT = $newP = NULL;
				if ($f->has_extension('jpg,jpeg,gif,png')) {
					$newO=$f->file_info();
					//thumbnail
					$fileT=$dir.$fName.'_.'.$fExt;
					list($w,$h)=array(800, 1000);
					if ($newO && $img->resize($f, $fileT, $w, $h) && $f->is_file($fileT)) $newT=$f->file_info();
				}
				if ($newO) {
					$comm = $d[3][$k];
					if (preg_match('/title="(.*?)"/',$comm, $a)) {
						$comm = $a[1];
					}
					elseif (preg_match('/alt="(.*?)"/',$comm, $a)) {
						$comm = $a[1];
					}
					else {
						$comm = '';
					}
					$aid = $this->attachment( array('parent_id'=>$parentID, 'layer'=>$layer, 'content_type'=>0, 'file' => $newO, 'thumbnail'=>$newT, 'comment'=>$comm));
					$s = str_replace($v, "\n\n{id:" . $aid . "}\n\n", $s);
				}
			}
			else {
				echo "Not found in $parentID : $url<br/>\n";
			}
		}
		// istatom visus urlus
		// -- foreach ($m as $k=>$v) $txt=str_replace('<<url'.$k.'>>',$v,$txt);
        $this->debug(__LINE__);

		$s = preg_replace('/<span class=["]?strong["]?>(.*?)<\/span>/sm', '[B]\1[/B]', $s);

	   /*	$s = preg_replace('/<span class=["]?subtitle["]?>(.*?)<\/span>/sm', '[H]\1[/H]', $s);
		$this->debug(__LINE__);
		$s = preg_replace('/<span class=["]?black7["]?>(.*?)<\/span>/sm', '[I]\1[/I]', $s);
		$this->debug(__LINE__);
		$s = preg_replace("/<hr([^>]+)>/m", '', $s);*/
		$s = preg_replace("/<font([^>]+)>/m", '', $s);
		$s = str_replace(array("</font>", '<span>', '</span>', '<blockquote>', '</blockquote>'), '', $s);
		$this->debug(__LINE__);
		$s=str_replace("\r",'',$s);
		$this->debug(__LINE__);
		$s=preg_replace('/\n(\s){1,}\n/sm',"\n",$s);//pasalinti gale esancius tarpus
		$this->debug(__LINE__);
		$s=preg_replace('/(\n){3,}/ms',"\n\n",$s);//pasalinti didelius tarpus
		$this->debug(__LINE__);
		// -- $s=preg_replace('/(^|\s+|[^a-zA-Z0-9])"/ms',"$1„",$s);//
		// -- $s=preg_replace('/"($|\s+|[^a-zA-Z0-9])/ms',"“$1",$s);//
		return trim($s);
	}


	function recompile() {
		$rtf = $this->object('rtf');
		set_time_limit(6000);
		$this->f = & moon :: file();
		$limit = 10;
		do {

	    	$sql = 'SELECT room_id,page_id, content  FROM rw2_reviews WHERE recompile=1 LIMIT ' . $limit;
			$r = $this->db->query($sql);
			$end = TRUE;
			while ($a = $this->db->fetch_row_assoc($r)) {
				$end = FALSE;
				$rtf->setInstance( $this->get_var('rtf{reviews}') . ':' . $a['page_id'] );
				list(,$html) = $rtf->parseText($a['room_id'],$a['content']);
				$this->db->update(array('content_html'=>$html, 'recompile'=>0), 'rw2_reviews', array('room_id'=>$a['room_id'],'page_id'=>$a['page_id']));
			}
			echo '.';
			flush();
			//$end = TRUE;
        } while (!$end);
	}


	function debug($l) {
		return;
		static $t = null;
		$n = $this->mtime();
		if (!is_null($t)) {
			echo $l ,': ', sprintf('%.4f', $n-$t), "<br/>\n";
		}
		$t = $n;
	}

	function mtime() {
		list($usec, $sec) = explode(" ", microtime());
		return ((float) $usec + (float) $sec);
	}


}

?>