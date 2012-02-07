<?php

class import extends moon_com {


function events($event, $par) {

	switch($event) {
		case 'deposit':
			$r='';
            if (!empty($par['deposits-url']) && isset($par['deposits']) && is_array($par['deposits']))
				$r.=$this->import_deposits($par['deposits-url'],$par['deposits']);
			if (isset($par['deposits_rooms']) && is_array($par['deposits_rooms']))
				$r.=$this->import_deposits_rooms($par['deposits_rooms']);
			if ($r==='') $r='Nothing to import?';
            break;
        case 'games':
			$r='';
            if (isset($par['games']) && is_array($par['games']))
				$r.=$this->import_games($par['games']);
			if (isset($par['games_rooms']) && is_array($par['games_rooms']))
				$r.=$this->import_games_rooms($par['games_rooms']);
			if ($r==='') $r='Nothing to import?';
            break;
        case 'rooms':
			$r='';
			if (isset($par['deposits_rooms']) && is_array($par['deposits_rooms']))
				$r.=$this->import_deposits_rooms($par['deposits_rooms']);
			if (isset($par['games_rooms']) && is_array($par['games_rooms']))
				$r.=$this->import_games_rooms($par['games_rooms']);
			if (isset($par['rooms']) && is_array($par['rooms']))
				$r.=$this->import_rooms($par['rooms']);
			if (isset($par['trackers']) && is_array($par['trackers']))
				$r.=$this->import_trackers($par['trackers']);
			if (!empty($par['gallery-url']) && isset($par['gallery']) && is_array($par['gallery'])) {
				$r.=$this->import_gallery($par['gallery-url'],$par['gallery']);
			}
			if ($r==='') $r='Nothing to import?';
            break;

	}
	$p=&moon::page();
	$p->set_local('transporter',$r);
}


//***************************************
//           --- IMA KITI KOMPONENTAI ---
//***************************************

function import_deposits($url,$a)
{
	if ($kiek=count($a)) {
		$ins=array();
		$table=$this->table('Deposits');

		$is=$this->db->array_query_assoc("SELECT id,img,img_big,updated,hide FROM $table", 'id');

        $f = &moon::file();
		$dir = $this->get_dir('dirDeposit');
		foreach ($a as $v) {
			$id = $v['id'];
            $ins = array();
			$ins['id']=$v['id'];
            $ins['name']=$v['name'];
			$ins['uri'] = (string)$v['alias'];
			$ins['img'] = $v['img'];
			$ins['img_big'] = $v['img_big'];
			$ins['homepage_url'] = (string)$v['homepage_url'];
			$ins['tracker_url'] = (string)$v['url'];
			$ins['currencies'] = (string)$v['currencies'];
			/*if (!empty($v['img'])) {
                if (!$f->is_url_content($url . $v['img'], $dir . $v['img'])) {
					//jei neisaugojo, nekeiciam
					//continue;
				}
			}
			if (!empty($v['img_big'])) {
                if (!$f->is_url_content($url . $v['img_big'], $dir . $v['img_big'])) {
					//jei neisaugojo, nekeiciam
					//continue;
				}

			}*/
			if (isset($is[$id])) {
                /*if ($is[$id]['img'] !='' && $is[$id]['img']!=$v['img']) {
                	//trinam senas
					if ($f->is_file($dir . $is[$id]['img'])) {
						$f->delete();
					}
				}
				if ($is[$id]['img_big'] !='' && $is[$id]['img_big']!=$v['img_big']) {
					//trinam senas
					if ($f->is_file($dir . $is[$id]['img_big'])) {
						$f->delete();
					}
				}*/
				if ($is[$id]['hide']) {
					$ins['hide'] = 1;
				}
				//unset($is[$id]);
			}
			if ($v['is_deleted'] || $v['hide']) {
				$ins['hide'] = 2;
			}
			if (isset($is[$id])) {
				unset($is[$id]);
				$this->db->update($ins,$table, $id);
			}
			else {
				$ins['sort_order'] = 300;
				$this->db->insert($ins,$table);
			}
		}
        //dabar panaikinam siuksles
		if (count($is)) {
            /*foreach ($is as $id=>$v) {
				$delImg = $v['img'];
                if ($delImg && $f->is_file($dir . $delImg)) {
					$f->delete();
				}
				$delImg = $v['img_big'];
                if ($delImg && $f->is_file($dir . $delImg)) {
					$f->delete();
				}
			}*/
			$this->db->query("
				DELETE FROM $table
				WHERE id IN ('" . implode("', '", array_keys($is)) . "')"
				);
		}
	}
	return "Deposits: $kiek<br/>";
}

function import_deposits_rooms($a)
{
	if ($kiek=count($a)) {
		$ins=array();
		$table=$this->table('DepositsRooms');
	    $this->db->query("TRUNCATE TABLE $table");
		foreach ($a as $roomID=>$v) {
			$dIDs = explode(',', $v);
			$roomID = (int) $roomID;
			foreach ($dIDs as $dID) {
				$ins[] = "($roomID," . intval($dID) . ")";
			}

		}
		if (count($ins)) {
			$sql = 'INSERT INTO ' . $table . ' (room_id, deposit_id) VALUES ' . implode(', ', $ins);
			$this->db->query($sql);
		}

	}
	return "Deposits-Rooms: $kiek<br/>";
}


function import_games($a)
{
	if ($kiek=count($a)) {
		$ins=array();
		$table=$this->table('Games');

        $is=$this->db->array_query("SELECT id,hide FROM $table", 0);

		$f = &moon::file();
		$imgDir = $this->get_dir('dirGames');

        foreach ($a as $v) {
        	$ins = array();
            $ins['title']=$v['name'];
			$ins['uri']=$v['alias'];
			if ($v['img']) {
				$ins['img']=basename($v['img']);
				/*if (!file_exists($imgDir.$ins['img'])) {
                	if (!$f->is_url_content($v['img'],$imgDir.$ins['img'])) {
                		unset($ins['img']);
                	}
				}*/
			}
			if ($v['ico']) {
				$ins['ico']=basename($v['ico']);
				/*if (!file_exists($imgDir.$ins['ico'])) {
                	if (!$f->is_url_content($v['ico'],$imgDir.$ins['ico'])) {
                		unset($ins['ico']);
                	}
				}*/
			}
			if ($v['ico_big']) {
				$ins['ico_big']=basename($v['ico_big']);
				/*if (!file_exists($imgDir.$ins['ico_big'])) {
                	if (!$f->is_url_content($v['ico_big'],$imgDir.$ins['ico_big'])) {
                		unset($ins['ico_big']);
                	}
				}*/
			}
			if (isset($is[$v['id']])) {
				unset($is[$v['id']]);
            	$this->db->update($ins,$table,array('id'=>$v['id']));
			} else {
                $ins['id']=$v['id'];
				$ins['hide'] = 1;
				$ins['created'] = $ins['updated'] = time();
                $this->db->insert($ins,$table);
			}
		}
		if (count($is)) {
        	$this->db->update(array('hide'=>2),$table,'id IN ('.implode(',', array_keys($is)).')');
		}
	}
	return "Games: $kiek<br/>";
}

function import_games_rooms($a)
{
	if ($kiek=count($a)) {
		$ins=array();
		$table=$this->table('GamesRooms');
	    $this->db->query("TRUNCATE TABLE $table");
		foreach ($a as $v) {
			$ins['game_id']=$v['game_id'];
			$ins['room_id']=$v['room_id'];
			$this->db->insert($ins,$table);
		}
	}
	return "Games-Rooms: $kiek<br/>";
}


function import_rooms($a)
{
	$inf=array(0,0,0,0,0,0,0,0,0,0);
	if ($kiek=count($a)) {
		$ins=array();
		$table=$this->table('Rooms');

		$b=$this->db->array_query("SELECT id FROM $table");
		$roomIDs = $is = array();
		foreach ($b as $v) {
			$roomIDs[]=$v[0];
			$is[$v[0]] = $v;
		}

		$f=new moon_file();
		//$imgDir = $this->get_dir('dirLogo');
		//$hasLocalIco = in_array(_SITE_ID_, array('com'));
		foreach ($a as $v) {
			$ins=array();
			$id=$v['id'];
			$ins['name']=$v['name'];
			$ins['software_os']=$v['software_os'];
			$ins['is_hidden']=$v['is_hidden'];
			$ins['alias']=$v['alias'];
			$ins['old_id']=$v['old_id'];
			//$ins['download_iframe']=$v['download_iframe'];
			$friendly = (int) $v['local_ico'];
			switch (_SITE_ID_) {
				case 'com' :
					$ins['us_friendly'] = 1 & $friendly ? 1 : 0;
					break;

				case 'fr' :
					$ins['us_friendly'] = 2 & $friendly ? 1 : 0;
					break;

				case 'it' :
					//pas it visi friendly
					$ins['us_friendly'] = 1;
					break;

				default:
					$ins['us_friendly'] = 0;

			}
			$ins['bonus_code']=$v['bonus_code'];
			$ins['marketing_code']=$v['marketing_code'];
			$ins['tournaments_xml_name']=$v['tournaments_xml_name'];
			$ins['currency']=$v['currency'];
			$ins['min_deposit']=$v['min_deposit'];
			$ins['suggested_deposit']=$v['suggested_deposit'];
			$ins['time_limit_to_qualify']=$v['time_limit_to_qualify'];
			$ins['rake_requirements']=$v['rake_requirements'];
			$ins['bonus_int']=$v['bonus_int'];
			$ins['editors_rating']=$v['editors_rating'];
			$ins['has_landing_page']=$v['has_landing_page'];
			$ins['bonus_percent']=$v['bonus_percent'];
			$ins['url']=$v['url'];
			$ins['established']=$v['established'];
			$ins['auditor']=$v['auditor'];
			$ins['network']=$v['network'];
			$ins['email']=$v['email'];
			$ins['bonuses']=$v['bonuses'];
			//$ins['is_cookie_clr_dep']=$v['is_cookie_clr_dep'];
			if ($v['logo']) {
				$ins['logo']=basename($v['logo']);
			}
			if (!empty($v['logo_dark'])) {
				$ins['logo_dark']=basename($v['logo_dark']);
			}
			if ($v['favicon']) {
				$ins['favicon']=basename($v['favicon']);
			}
			if ($v['logo_big']) {
				$ins['logo_big']=basename($v['logo_big']);
			}
			if ($v['logo_filled']) {
				$ins['logo_filled']=basename($v['logo_filled']);
			}
            //$ins['room_id']=$v['room_id'];
			if (isset($is[$id])) {
				$this->db->update($ins,$table, array('id'=>$id));
				$inf[0]++;
				unset($is[$id]);
			}
			else {
				$ins['id']=$id;
				$ins['sort_1'] = 300;
				$this->db->insert($ins,$table);
				$inf[1]++;
			}
		}
		if (count($is)) {
			$this->db->update(array('is_hidden'=>1), $table, 'id in ('.implode(', ', array_keys($is)).')');
		}
	}

	return "Rooms: updated {$inf[0]}, inserted {$inf[1]}<br/>";
}

function import_trackers($a)
{
	if ($kiek=count($a)) {
		$ins=array();
		$table=$this->table('Trackers');

        $this->db->query("TRUNCATE TABLE $table");
		foreach ($a as $v) {
            $ins['parent_id']=$v['parent_id'];
			$ins['alias']=$v['alias'];
			$ins['uri']=$v['uri'];
			$ins['uri_download']=$v['uri_download'];
			$ins['bonus_code']=$v['bonus_code'];
			$ins['iframe']=$v['iframe'];
            $this->db->insert($ins,$table);
		}
	}
	return "Trackers: $kiek<br/>";
}

function import_gallery($url,$a)
{
    if ($kiek=count($a)) {
		$ins=array();
		$table=$this->table('RoomsGallery');
		//$dir = $this->get_dir('dirGallery');

		$dat = $this->db->array_query_assoc("SELECT id,img,updated FROM $table");
		$is = array();
		foreach ($dat as $v) {
			//if (file_exists($dir . $v['img']))
			$is[$v['id']] = $v;
		}

		//$f = &moon::file();
		foreach ($a as $v) {
			$id = $v['id'];
			if (isset($is[$id]) && $is[$id]['updated']==$v['updated']) {
				//nepasikeites
				unset($is[$id]);
				continue;
			}
			$ins['id']=$v['id'];
            $ins['room_id']=$v['room_id'];
			$ins['img']=$v['img'];
			$ins['img_info']=$v['img_info'];
			$ins['alt']=$v['alt'];
			$ins['updated']=$v['updated'];
			$delImg = false;
            if (isset($is[$id])) {
                if ($is[$id]['img']!=$v['img']) {
                	//trinam senas
					$delImg = $is[$id]['img'];
				} else {
					//img atsisiusti nereikia
					$v['img'] = '';
				}
				unset($is[$id]);
			}
			/*if (!empty($v['img'])) {
				$orig = substr_replace($v['img'],'_',13,0);
                if (
					!$f->is_url_content($url . $v['img'], $dir . $v['img']) ||
					!$f->is_url_content($url . $orig, $dir . $orig)
				) {
					//jei neisaugojo, nekeiciam
					continue;
				}

			}*/
			/*if ($delImg) {
                if ($f->is_file($dir . $delImg)) {
					//gaunam failo pav. pagrindine dali ir extensiona
					$orig = substr_replace($delImg,'_',13,0);
					//trinamas pagrindinis img
					$f->delete();
					//dabar dar trinam originalu img
					if ($f->is_file($dir.$orig)) $f->delete();
				}
			}*/
            $this->db->replace($ins,$table);
		}
		//dabar panaikinam siuksles
		if (count($is)) {
			/*foreach ($is as $id=>$v) {
				$delImg = $v['img'];
                if ($f->is_file($dir . $delImg)) {
					//gaunam failo pav. pagrindine dali ir extensiona
					$orig = substr_replace($delImg,'_',13,0);
					//trinamas pagrindinis img
					$f->delete();
					//dabar dar trinam originalu img
					if ($f->is_file($dir.$orig)) $f->delete();
				}
			}*/
			$this->db->query("
				DELETE FROM $table
				WHERE id IN ('" . implode("', '", array_keys($is)) . "')"
				);
		}
	}
	return "Gallery: $kiek<br/>";
}

}
?>