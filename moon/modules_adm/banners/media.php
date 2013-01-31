<?php
class media extends moon_com
{
	function onload()
	{
		$this->formItem = &$this->form('item');
		$this->formItem->names('id', 'title', 'type', 'url', 'img_alt');
		$this->env = $this->get_var('env');
	}
        
	function events($event, $par)
	{
		if (isset($_POST['files-upload'])) {
			$this->uploadFile();exit;
		}
		
		$id = isset($par[0]) ? intval($par[0]) : 0;
		if ($id) {
			if ($id) {
				if (count($values = $this->getItem($id))) {
					$this->formItem->fill($values);
				} else {
					$this->set_var('error', '404');
				}
			}
		}
		
		$page = moon::page();
		$err = $page->get_global($this->my('fullname') . '.error');
		if (!empty($err)) {
			$this->set_var('error', $err);
			$page->set_global($this->my('fullname') . '.error', '');
		}
		
		/*$msg = $page->get_global($this->my('fullname') . '.success');
		if (!empty($msg)) {
			$page->alert($msg, 'ok');
			$page->set_global($this->my('fullname') . '.success', '');
		}*/
		
		switch ($event) {
			case 'save':
				if ($id = $this->save()) {
					if (isset($_POST['return']) ) {
						$this->redirect('#', $id);
					} else {
						$this->redirect('banners-banners');
					}
				} //else {}
				break;
			case 'remove-media': // ads to undefined
				if (isset($par[0]) && isset($par[1])) {
					$bannerId = intval($par[0]);
					$id = intval($par[1]);
					$this->removeItemMedia($id);
					$this->redirect('#', $bannerId);
				} else {
					$page = moon::page();
					$page->page404();
				}
				break;
			case 'remove-flashxml': // ads to undefined
				if (isset($par[0]) && isset($par[1])) {
					$bannerId = intval($par[0]);
					$id = intval($par[1]);
					$this->removeItemFlashXml($id);
					$this->redirect('#', $bannerId);
				} else {
					$page = moon::page();
					$page->page404();
				}
				break;
			case 'delete': // physically deletes media file
				if (isset($par[0]) && isset($par[1])) {
					$bannerId = intval($par[0]);
					$id = intval($par[1]);
					$this->deleteItemMedia($bannerId, $id);
					
					$this->redirect('#', $bannerId);
				} else {
					$page = moon::page();
					$page->page404();
				}
				break;
			default:
				break;
		}
		$this->use_page('Common');
	}
        
	function properties()
	{
		$vars = array();
		$vars['view'] = 'list';
		$vars['error'] = false;
		return $vars;
	}
        
	function main($vars)
	{
		$env = $this->env;
		
		$page = &moon::page();
		$tpl = $this->load_template();
		$win = &moon::shared('admin');
		$win->active($this->my('module') . (($env) ? '.'.$env : '.banners'));
		$pageTitle = $win->current_info('title');
		$page->title($pageTitle);
		$page->css('/css/banners.media.css');
		
		$info = $tpl->parse_array('info');
		$err = ($vars['error'] !== false) ? $vars['error'] : false;
		
		$form = $this->formItem;
		$bannerId = $form->get('id');
		$mediaType = $form->get('type');
		$title = $bannerId ? $info['titleEdit'] . ' :: ' . $form->get('title') : $info['titleNew'];
		$imgSrc = $this->get_var('srcBanners');
		$videoSrc = 'http://www.pokernetwork.' . (is_dev() ? 'dev' : 'com') . '/w/ads/';
		$optSites = $this->getAvailableSites($bannerId, $mediaType);
		$allSiteIds = $this->getAllSiteIds();
		
		$main = array(
			'id' => $bannerId,
			'showNav' => $bannerId ? true : false,
			'url.settings' => $this->linkas((($env) ? $env : 'banners') . '#edit', $bannerId),
			'url.media' => $this->linkas('#', $bannerId),
			'error' => ($err !== FALSE) ? $info['error' . $err] : '',
			'event' => $this->my('fullname') . '#save',
			
			'goBack' => $this->linkas((($env) ? $env : 'banners') . '#'),
			'pageTitle' => $pageTitle,
			'formTitle' => htmlspecialchars($form->get('title')),
			'media' => $mediaType == 'media',
			'flashXml' => $mediaType == 'flashXml',
			'refresh' => $page->refresh_field(),
			'enableSwfUpload' => $mediaType == 'media' || $mediaType == 'video',
			
			'sessionId' => session_id()
		) + $form->html_values();
		$user = moon::user();
		$swfKey = $user->id() ? $this->object('sys.login_object')->autologin_code($user->id(), $user->get('email')) : '';
		$main['swfkey'] = $tpl->ready_js($swfKey);
		
		$listSites = '';
		$listUndefined = '';
		
		switch ($mediaType) {
			case 'media':
			case 'video':
				$page->js('/js/swfupload/swfupload.js');
				$page->js('/js/swfupload/swfupload.queue.js');
				$page->js('/js/swfupload/swfupload.handlers.js');
				$page->js('/js/swfupload/swfupload.fileprogress.js');
				$page->js('/js/modules_adm/banners.swfupload.js');
				$page->js('/js/modules_adm/banners.media.js');
				
				if ($mediaType === 'video') {
					// pn video player
					$page->js('http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js');
					$page->js('http://www.pokernetwork.com/js/pnplayer.js');
					$page->css('http://www.pokernetwork.com/css/pnplayer.css');
				}

				$items = $this->getItems($bannerId, $mediaType);
				$lastId = (isset($items[count($items) - 1])) ? $items[count($items) - 1]['id'] : 0;
				foreach ($items as $item) {
					$d = array(
						'site_name' => isset($allSiteIds[$item['site_id']]) ? $allSiteIds[$item['site_id']] : '',
						'site_id' => $item['site_id'],
						'path' => ($mediaType == 'media' ? $imgSrc : $videoSrc) . $item['filename'],
						'image' => $item['media_type'] == 'image',
						'flash' => $item['media_type'] == 'flash',
						'html' => $item['media_type'] == 'html',
						'video' => $item['media_type'] == 'video',
						'url' => ($item['url']) ? $item['url'] : $form->get('url'),
						'alternative' => ($item['alternative']) ? $item['alternative'] : $form->get('img_alt'),
						'is_hidden' => ($item['is_hidden']) ? '1" checked="checked' : '',
						'goRemove' => $this->linkas('#remove-media', $bannerId . '.' . $item['id']),
						'last' => $item['id'] == $lastId
					) + $item;
					$listSites .= $tpl->parse('itemsSites', $d);
				}
				
				$itemsUndefined = $this->getItemsUndefined($bannerId, $mediaType);
				$lastId = (isset($items[count($items) - 1])) ? $items[count($items) - 1]['id'] : 0;
				foreach ($itemsUndefined as $item) {
					$d = array(
						'path' => ($mediaType == 'media' ? $imgSrc : $videoSrc) . $item['filename'],
						'image' => $item['media_type'] == 'image',
						'flash' => $item['media_type'] == 'flash',
						'html' => $item['media_type'] == 'html',
						'video' => $item['media_type'] == 'video',
						'last' => $item['id'] == $lastId,
						'optSites' => $form->options('site_id_tmp', $optSites),
						'goDelete' => $this->linkas('#delete', $bannerId . '.' . $item['id']),
					) + $item;
					$listUndefined .= $tpl->parse('itemsUndefined', $d);
				}
				break;
			case 'html':
				
				$sites = $this->getAvailableSites(0, $mediaType);
				$items = $this->getItems($bannerId, $mediaType);
				$itemsHTML = array();
				foreach ($items as $item) {
					$itemsHTML[$item['site_id']] = $item;
				}
				
				$lastId = (isset($sites[count($sites) - 1])) ? key($sites[count($sites) - 1]) : '';
				foreach ($sites as $siteId => $siteName) {
					$item = isset($itemsHTML[$siteId]) ? $itemsHTML[$siteId] : array();
					$d = array(
						'id' => isset($item['id']) ? $item['id'] : 0,
						'html' => true,//$item['media_type'] == 'html',
						'alternative' => isset($item['alternative']) ? $item['alternative'] : '',
						'is_hidden' => (empty($item['alternative']) || !empty($item['is_hidden'])) ? '1" checked="checked' : '',
						'last' => isset($item['site_id']) ? $item['site_id'] == $lastId : false,
						'site_id' => $siteId,
						'site_name' => $siteName
					);
					$listSites .= $tpl->parse('itemsSites', $d);
				}
				break;
			case 'flashXml':
				$page->js('/js/modules_adm/banners.media.js');
				
				$items = $this->getItems($bannerId, $mediaType);
				
				$lastId = (isset($items[count($items) - 1])) ? $items[count($items) - 1]['id'] : 0;
				foreach ($items as $item) {
					$text = '';
					$fontSize = '';
					if ($item['alternative']) {
						$data = unserialize($item['alternative']);
						if (empty($data[0]) || empty($data[1])) continue;
						
						$fontSize = $data[0];
						$text = str_replace('|', "\n", $data[1]);
						
					} else {
						//continue;
					}
					$d = array(
						'site_name' => isset($allSiteIds[$item['site_id']]) ? $allSiteIds[$item['site_id']] : '',
						'site_id' => $item['site_id'],
						'path' => $imgSrc . $item['filename'],
						'url' => ($item['url']) ? $item['url'] : $form->get('url'),
						'flashXml' => $item['media_type'] == 'flashXml',
						'fontSize' => $fontSize,
						'text' => $text,
						'params' => (isset($data) && count($data) > 1) ? 'f=' . $data[0] . '&t=' . urlencode($data[1]) : '',
						'is_hidden' => ($item['is_hidden']) ? '1" checked="checked"' : '',
						'goRemove' => $this->linkas('#remove-flashxml', $bannerId . '.' . $item['id']),
						'last' => $item['id'] == $lastId
					) + $item;
					$listSites .= $tpl->parse('itemsSites', $d);
				}
				break;
			default:
				break;
		}
		
		$main['enableHideMedia'] = $mediaType != 'html';
		$main['itemsSites'] = $listSites;
		$main['itemsUndefined'] = $listUndefined;
		$main['showForm'] = ($listSites !== '' || $listUndefined !== '' || $mediaType == 'flashXml');
		return $tpl->parse('main', $main);
	}
        
	function getItem($id)
	{
		$sql = 'SELECT *
			FROM ' . $this->table('Banners') . '
			WHERE	id = ' . intval($id);
		return $this->db->single_query_assoc($sql);
	}
	
	function getItems($bannerId, $mediaType)
	{
		$where = array();
		$where[] = 'WHERE m.banner_id = ' . $bannerId;
		$where[] = 'm.site_id != 0';
		if ($mediaType == 'media') {
			$where[] = 'm.media_type IN ("image", "flash")';
		} elseif ($mediaType == 'flashXml') {
			$where[] = 'm.media_type = "flashXml"';
		} elseif ($mediaType == 'video') {
			$where[] = 'm.media_type = "video"';
		} else {
			$where[] = 'm.media_type = "html"';
		}
		
		$limitedToSites = $this->get_var('limitedToSites');
		$isLimitedAccess = count($limitedToSites) > 0;
		if ($isLimitedAccess) {
			$where[] = 'm.site_id IN (' . implode(',', array_keys($limitedToSites)) . ')';
		}
		
		$sqlWhere = implode(' AND ', $where);
		
		$m = $this->db->single_query("show tables like '".$this->table('Servers')."'");
		$exist = (count($m) ? true : false);
		if ($exist) {
			$sql = 'SELECT m.id,m.site_id,m.filename,m.media_type,m.media_width,m.media_height,m.alternative,m.url,m.is_hidden
			FROM ' . $this->table('BannersMedia') . ' m LEFT JOIN ' . $this->table('Servers') . ' s ON m.site_id=s.id ' . 
			$sqlWhere .
			' ORDER BY s.site_id ASC';
		} else {
			$sql = 'SELECT m.id,m.site_id,m.filename,m.media_type,m.media_width,m.media_height,m.alternative,m.url,m.is_hidden
				FROM ' . $this->table('BannersMedia') . ' m ' . 
				$sqlWhere;
		}
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}
	
	function getItemsUndefined($bannerId, $mediaType)
	{
		$where = array();
		$where[] = 'WHERE banner_id = ' . $bannerId;
		$where[] = '(site_id  = 0)';

		if ($mediaType == 'media') {
			$where[] = 'media_type IN ("image", "flash")';
		} elseif($mediaType == 'video') {
			$where[] = 'media_type = "video"';
		} else {
			$where[] = 'media_type = "html"';
		}

		$sqlWhere = implode(' AND ', $where);
		$sql = 'SELECT id,site_id,filename,media_type,media_width,media_height,alternative,url
			FROM ' . $this->table('BannersMedia') . ' ' . $sqlWhere;
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}
	
	function getAvailableSites($bannerId, $mediaType)
	{
		$limitedToSites = $this->get_var('limitedToSites');
		$isLimitedAccess = count($limitedToSites) > 0;
		if ($isLimitedAccess) {
			return $limitedToSites;
		}
		$items = array();
		$m = $this->db->single_query("show tables like '".$this->table('Servers')."'");
		$exist = (count($m) ? true : false);
		if ($exist) {
			$category = ($this->env == 'casino') ? 3 : 1;
			$where = array();
			$where[] = 'WHERE banner_id = ' . $bannerId;

			if ($mediaType == 'media') {
				$where[] = 'media_type IN ("image", "flash")';
			} elseif($mediaType == 'video') {
				$where[] = 'media_type = "video"';
			} else {
				$where[] = 'media_type = "html"';
			}

			$where[] = 'site_id > 0';
			$sqlWhere = implode(' AND ', $where);
			
			$sql = 'SELECT id, site_id
				FROM ' . $this->table('Servers') . '
				WHERE	server_disabled = 0 AND
				     	category = ' . $category . 
				     	(($bannerId)
				     		? '
				     		AND id NOT IN (
				     		SELECT distinct(site_id)
				     		FROM ' . $this->table('BannersMedia') . ' ' . $sqlWhere . ')'
				     		: '') .
				     	' ORDER BY category,site_id';
			$result = $this->db->array_query_assoc($sql, 'id');
			
			foreach ($result as $item) {
				$items[$item['id']] = $item['site_id'];
			}
			return $items;
		}
		foreach ($this->get_var('sitesNames') as $item) {
			$items[$item['id']] = $item['site_id'];
		}
		return $items;
	}
	
	function save()
	{
		$form = &$this->formItem;
		$postData = $_POST;
		$form->fill($postData);
		$values = $form->get_values();
		
		// Filtering
		$data = array();
		$data = $values;
		$bannerId = $data['id'] = intval($values['id']);
		$mediaType = $values['type'];
		
		// if was refresh skip other steps and return
		if ($form->was_refresh()) {
			return $bannerId;
		}
		
		$urls = (!empty($_POST['url']) && is_array($_POST['url'])) ? $_POST['url'] : array();
		$alts = (!empty($_POST['alternative']) && is_array($_POST['alternative'])) ? $_POST['alternative'] : array();
		$hidden = (!empty($_POST['is_hidden']) && is_array($_POST['is_hidden'])) ? $_POST['is_hidden'] : array();
		$siteIds = (!empty($_POST['site_id']) && is_array($_POST['site_id'])) ? $_POST['site_id'] : array();
		$sizes = (!empty($_POST['sizes']) && is_array($_POST['sizes'])) ? $_POST['sizes'] : array();
		$errorMsg = 0;
		
		if ($mediaType == 'media' || $mediaType == 'video') {
			// Validation
			// 1. site id repeatedness
			$sitesSet = array_diff($siteIds, array(''));
			if (count($sitesSet) <> count(array_unique($sitesSet))) {
				$errorMsg = 1;
			}
			
			if ($errorMsg) {
				$this->set_var('error', $errorMsg);
				return FALSE;
			}
			
			// update assighed items
			foreach ($urls as $id => $url) {
				$upd = array(
					'url' => $url,
					'is_hidden' => (isset($hidden[$id])) ? 1 : 0
				);
				if (isset($alts[$id])) $upd['alternative'] = trim($alts[$id]);
				$this->db->update($upd,$this->table('BannersMedia'), array('id' => $id));
			}
			
			// get default banner group sizes
			$defaultSizes = '';
			$res = $this->db->single_query_assoc('
				SELECT media_width,media_height
				FROM ' . $this->table('Banners') . '
				WHERE id = ' . $bannerId);
			if (!empty($res['media_width']) && !empty($res['media_height'])) {
				$defaultSizes = $res['media_width'] . 'x' . $res['media_height'];
			}
			
			// assign site ids to unasigned
			foreach ($siteIds as $id => $siteId) {
				if (!$siteId) continue;
				// check against default sizes
				if (isset($sizes[$id]) && $sizes[$id] != $defaultSizes) {
					$page = moon::page();
					$page->set_global($this->my('fullname') . '.error', 2);
					continue;
				}
				
				// make existing site assigned null
				$upd = array(
					'site_id' => $siteId
				);
				$this->db->update($upd,$this->table('BannersMedia'), array('id' => $id));
			}
		} elseif ($mediaType == 'html') {
			$sites = (!empty($_POST['sites']) && is_array($_POST['sites'])) ? $_POST['sites'] : array();
			
			// update assighed items
			foreach ($alts as $siteId => $alt) {
				$id = (isset($sites[$siteId])) ? $sites[$siteId] : 0;
				$alternative = isset($alts[$siteId]) ? trim($alts[$siteId]) : '';
				if (!$alternative && !$id) continue; // skip if empty
				
				$ins = array(
					'site_id' => $siteId,
					'banner_id' => $bannerId,
					'media_type' => 'html',
					'alternative' => $alternative,
					'is_hidden' => (isset($hidden[$siteId])) || $alternative = '' ? 1 : 0
				);
				if ($id) $ins['id'] = $id;
				$this->db->replace($ins,$this->table('BannersMedia'));
			}
		} elseif ($mediaType == 'flashXml') {
			
			$sites = (!empty($_POST['sites']) && is_array($_POST['sites'])) ? $_POST['sites'] : array();
			
			$texts = (!empty($_POST['text']) && is_array($_POST['text'])) ? $_POST['text'] : array();
			$fontSizes = (!empty($_POST['font_size']) && is_array($_POST['font_size'])) ? $_POST['font_size'] : array();
			
			// update assigned items
			foreach ($fontSizes as $siteId => $fontSize) {
				$id = (isset($sites[$siteId])) ? $sites[$siteId] : 0;
				if (!$id || !isset($texts[$siteId]) || !$fontSize) continue; // skip if empty
				
				$fontSize = intval($fontSize);
				$text = str_replace(array("\r", "\r\n", "\n"), '|', $texts[$siteId]);
				$text = trim(preg_replace('/[|]+/', '|', $text), '|"\'');
			       
				if (!$fontSize || !$text) continue;
				
				$alternative = serialize(array($fontSize, $text));
				
				$upd = array(
					'url' => isset($urls[$id]) ? trim($urls[$id]) : '',
					'alternative' => $alternative,
					'is_hidden' => (isset($hidden[$siteId])) || $alternative = '' ? 1 : 0
				);
				$this->db->update($upd,$this->table('BannersMedia'), array('id' => $id));
			}
			
			
			// FILE UPLOADS
			if (empty($fontSizes)) {
				$f = new moon_file;
				if (!$f->is_upload('swf',$e)) {
					$this->set_var('error', 3);
					return FALSE;
				}
				if (!$f->is_upload('csv',$e)) {
					$this->set_var('error', 4);
					return FALSE;
				}
			}
			
			$sites = array();
			
			// swf file
			$filename = '';
			$newFileData = $this->saveSwf($bannerId, 'swf', $errorMsg);
			if (!$errorMsg && !empty($newFileData)) {
				$ins = array(
					'media_type' => 'flashXml',
					'media_width' => $newFileData['media_width'],
					'media_height' => $newFileData['media_height'],
					'filename' => $newFileData['filename'],
					'created' => time()
				);
				
				if (isset($newFileData['replaced'])) {
					$this->db->update($ins, $this->table('BannersMedia'), array('banner_id' => $bannerId));
				} else {
					// new item - insert
					$ins['banner_id'] = $bannerId;
					
					$sites = $this->getAvailableSites($bannerId, $mediaType);
					foreach ($sites as $siteId => $siteName) {
						$ins['site_id'] = $siteId;
						$ins['is_hidden'] = 1;
						$this->db->insert($ins, $this->table('BannersMedia'));
					}
				}
				
			} elseif($errorMsg) {
				$this->set_var('error', '_swf_' . $errorMsg);
				return FALSE;
			}
			
			// csv file
			$translationsData = $this->handleCsvUpload($errorMsg);
			if (!$errorMsg && !empty($translationsData)) {
				$sites = $this->getAllSiteIds();
				foreach ($translationsData as $data) {
					$siteName = strtolower(str_replace(array('"', '\'', ' '), '', $data[0]));
					if (in_array($siteName, $sites)) {
						
						$siteId = array_search($siteName, $sites);
						$fontSize = intval($data[1]);
						$text = str_replace(array("\r", "\r\n", "\n"), '|', $data[2]);
						$text = trim(preg_replace('/[|]+/', '|', $text), '|"\'');
						if (!$siteId || !$fontSize || !$text) continue;
						
						$alternative = serialize(array($fontSize, $text));
						
						$is = $this->db->single_query('
							SELECT 1 FROM ' . $this->table('BannersMedia') . '
							WHERE banner_id = ' . $bannerId . ' AND site_id = ' . $siteId
						);
						if (isset($is[0])) {
							$sql = 'UPDATE ' . $this->table('BannersMedia') . '
								SET alternative = "' . $this->db->escape($alternative) . '", is_hidden = 0
								WHERE banner_id = ' . $bannerId . ' AND site_id = ' . $siteId;
							$this->db->query($sql);
						} else {
							$res = $this->db->single_query_assoc('
								SELECT * FROM ' . $this->table('BannersMedia') . '
								WHERE banner_id = ' . $bannerId . ' AND media_type = \'flashXml\'
								LIMIT 1'
							);
							$ins = array(
								'banner_id' => $bannerId,
								'site_id' => $siteId,
								'filename' => $res['filename'],
								'media_type' => 'flashXml',
								'media_width' => $res['media_width'],
								'media_height' => $res['media_height'],
								'alternative' => $alternative,
								'url' => $res['url'],
								'created' => time()
							);
							$this->db->insert($ins, $this->table('BannersMedia'));
						}
					 }
				}
			} elseif ($errorMsg) {
				$this->set_var('error', '_csv_' . $errorMsg);
				return FALSE;
			}
			
		} else return $bannerId;
		
		// log this action
		blame($this->my('fullname'), 'Updated', $bannerId);
		$form->fill(array('id' => $bannerId));
		return $bannerId;
	}
	
	function handleCsvUpload(&$err)
	{
		$f = new moon_file;
		if (($isUpload = $f->is_upload('csv',$e)) && !$f->has_extension('csv')) {
			$err = 1; //neleistinas pletinys
			return;
		}
		
		$csvData = array();
		if ($isUpload) {
			$path = $f->file_path();
			$file = fopen($path, 'r');
			
			if ($file) {
				while (!feof($file)) {
					$buffer = fgets($file, 4096);
					$data = explode(';', $buffer);
					if (count($data) == 3 && $data[0] && $data[1] && $data[2]) {
						// site id aliases
						if ($this->env == 'pn' && $data[0] == 'en') {
							foreach (array('com', 'asia', 'uk') as $siteId) {
								$data[0] = $siteId;
								$csvData[] = $data;
							}
						} elseif ($this->env == 'casino' && $data[0] == 'en-c') {
							$data[0] = 'com-c';
							$csvData[] = $data;
						} else {
							$csvData[] = $data;
						}
					} elseif(count($data)) {
						$err = 2;
					}
				}
				fclose($file);
			}
			
			/*
			while (($data = fgetcsv($file, 0, ';')) !== FALSE) {
				if (count($data) == 3 && $data[0] && $data[1] && $data[2]) {
					
					// site id aliases
					if ($this->env == 'pn' && $data[0] == 'en') {
						foreach (array('com', 'asia', 'uk') as $siteId) {
							$data[0] = $siteId;
							$csvData[] = $data;
						}
					} elseif ($this->env == 'casino' && $data[0] == 'en-c') {
						$data[0] = 'com-c';
						$csvData[] = $data;
					} else {
						$csvData[] = $data;
					}
				} else {
					$err = 2;
				}
			}
			*/
			
			if (empty($csvData)) {
				$err = 2;
			}
		}
		return $csvData;
	}
	
	function saveSwf($bannerId, $name, &$err) //insertina irasa
	{
		$err=0;
		$dir=$this->get_dir('Banners');
		$newFileData = array();
		$f = new moon_file;
		
		if (($isUpload = $f->is_upload($name,$e)) && !$f->has_extension('swf')) {
			$err=1; //neleistinas pletinys
			return;
		}
		
		if ($isUpload) {
			// delete old one?
			$sql= 'SELECT max(filename) as filename
				FROM ' . $this->table('BannersMedia') . ' WHERE banner_id = ' . $bannerId;
			$oldFile = $this->db->single_query_assoc($sql);
			if (isset($oldFile['filename'])) {
				$deleteFile = new moon_file;
				if ($deleteFile->is_file($dir.$oldFile['filename'])) {
					$deleteFile->delete();
				}
			}
			
			$newFile = $dir . uniqid('') . '.' . $f->file_ext();
			if ($f->save_as($newFile)) {
				$newFile = $f->file_info();
				if ($fileInfo = moon_file::info_unpack($newFile)) {
					list($newFileData['media_width'], $newFileData['media_height']) = explode('x', $fileInfo['wh']);
					if ($newFileData['media_width'] == '') {
						$newFileData['media_width'] = NULL;
					}
					if ($newFileData['media_height'] == '') {
						$newFileData['media_height'] = NULL;
					}
					$newFileData['filename'] = $fileInfo['name_saved'];
					if (isset($oldFile['filename'])) $newFileData['replaced'] = true;
				}
			} else {
				$err=2; // technical error
				return;
			}
		}
		
		return $newFileData;
	}
	
	function removeItemMedia($id)
	{
		$id = intval($id);
		$this->db->query('UPDATE ' . $this->table('BannersMedia') . ' SET site_id = 0, url = "", alternative = "", is_hidden = 0 WHERE id = ' . $id);
		return TRUE;
	}
	
	function removeItemFlashXml($id)
	{
		$id = intval($id);
		$this->db->query('UPDATE ' . $this->table('BannersMedia') . ' SET alternative = "", is_hidden = 1 WHERE id = ' . $id);
		return TRUE;
	}
	
	function deleteItemMedia($bannerId, $id)
	{
		$id = intval($id);
		$res = $this->db->single_query_assoc('
			SELECT filename
			FROM ' . $this->table('BannersMedia') . '
			WHERE id = ' . $id
		);
		// delete file
		if (isset($res['filename'])) {
			$bannersDir = $this->get_dir('Banners');
			$deleteFile = new moon_file;
			if ($deleteFile->is_file($bannersDir.$res['filename'])) {
				$deleteFile->delete();
			}
		}
		
		// check/reset default sizes
		$sql = 'SELECT id 
			FROM ' . $this->table('BannersMedia') . '
			WHERE banner_id = ' . $bannerId;
		$result = $this->db->array_query_assoc($sql);
		if (!empty($result)) {
			$this->db->query('
				UPDATE ' . $this->table('Banners') . '
				SET media_width = NULL, media_height = NULL
				WHERE id = ' . $bannerId
			);
		}
		
		$this->db->query('DELETE FROM ' . $this->table('BannersMedia') . ' WHERE id = ' . $id);
		
		// log this action
		blame($this->my('fullname'), 'Deleted', $id);
		return TRUE;
	}
	
	function uploadFile()
	{
		$msg = '';
		$uploadName = 'Filedata';
		$file = new moon_file;
		$isUpload = $file->is_upload($uploadName,$e);
		if ($isUpload) {
			
			$page = moon::page();
			if (empty($_POST['banner_id'])) {
				$page->page404();
			}
			
			$bannerId = intval($_POST['banner_id']);
			$bannersDir = $this->get_dir('Banners');
			
			$fileError = false;
			if (!$file->has_extension('jpg,jpeg,gif,png,swf,flv,mp4')) {
				$msg .= 'Invalid file extension';
				$fileError = true;
			} else {
				$allSiteIds = $this->getAllSiteIds();
				// get site id from filename
				$ext = $file->file_ext();
				$filename = str_replace('.' . $ext, '', $file->file_name());
				$siteIds = array();
				/*if (($pos = strrpos($filename, '_')) !== FALSE) {
					if (($s = substr($filename, $pos + 1)) !== false) {
						if ($this->env == 'casino') {
							$s .= '-c';
							$enAliases = array('com-c');
							$siteIdsAll = array_values($allSiteIds) + array('en-c' => 'en-c');
							$en = 'en-c';
						} else {
							$enAliases = array('asia', 'uk', 'com');
							$siteIdsAll = array_values($allSiteIds) + array('en' => 'en');
							$en = 'en';
						}
						if (in_array($s, array_values($siteIdsAll))) {
							$siteIds = (strcasecmp($s, $en) === 0) ? $enAliases : array($s);
						}
					}
				}*/
				$siteIds = array('www.pokernetwork.com');
				
				
				//print_r($allSiteIds);
				//print_r($siteIds);
				//$siteIds = array_intersect($allSiteIds, $siteIds);
				//print_r($siteIds);
				//exit;
				// check for default sizes, if not set - set this file sizes
				$defaultWidth = $defaultHeight = '';
				$wh = $file->file_wh();
				if ($wh) {
					$res = $this->db->single_query_assoc('
						SELECT media_width,media_height
						FROM ' . $this->table('Banners') . '
						WHERE id = ' . $bannerId);
					if (empty($res['media_width']) || empty($res['media_height'])) {
						list($w,$h) = explode('x',$wh);
						$this->db->query('
							UPDATE ' . $this->table('Banners') . '
							SET media_width = ' . intval($w) . ', media_height = ' . intval($h) . '
							WHERE id = ' . $bannerId);
						$defaultWidth = $w;
						$defaultHeight = $h;
					} else {
						$defaultWidth = $res['media_width'];
						$defaultHeight = $res['media_height'];
					}
				}
				if ($wh != ($defaultWidth . 'x' . $defaultHeight)) {
					$siteIds = array(); // make banner undefined
				}
				
				$fileInfo = array();
				if (!empty($siteIds)) {
					$siteIds = array_intersect($allSiteIds, $siteIds);
					foreach ($siteIds as $siteId=>$name) {
						$sql = 'SELECT id, filename
							FROM ' . $this->table('BannersMedia') . '
							WHERE banner_id = ' . $bannerId . ' AND site_id = "' . $siteId . '"';
						$res = $this->db->single_query_assoc($sql);
						$replace = false;
						
						// make old file undefined
						$oldId = null;
						if (isset($res['filename']) && isset($res['id'])) {
							// make existing banner undefined
							$this->db->query('
								UPDATE ' . $this->table('BannersMedia') . '
								SET site_id = 0
								WHERE id = ' . intval($res['id'])
							);
							/*
							$deleteFile = new moon_file;
							if ($deleteFile->is_file($bannersDir.$res['filename'])) {
								$deleteFile->delete();
							}
							$replace = true;
							$oldId = $res['id'];
							*/
						}
						/* insert this file as undefined if exists
						$oldId = null;
						if (isset($res['filename']) && isset($res['id'])) {
							$siteId = null;
						}*/
						
						$newFile = $bannersDir . uniqid('') . '.' . $file->file_ext();
						if ($file->copy($newFile)) {
							$info = $file->file_info();
							$fileInfo = moon_file::info_unpack($info);
						} else {
							$msg .= 'System error';
							$fileError = true;
						}
						
						if (!empty($fileInfo)) {
							$ins = array(
								'banner_id' => $bannerId,
								'site_id' => $siteId,
								'filename' => $fileInfo['name_saved'],
								'media_type' => $fileInfo['ext'] == 'swf' ? 'flash' : 'image'
							);
							
							list($ins['media_width'], $ins['media_height']) = explode('x', $fileInfo['wh']);
							if ($ins['media_width'] == '') {
								$ins['media_width'] = null;
							}
							
							if ($replace) {
								$this->db->update($ins, $this->table('BannersMedia'), array('id' => $oldId));
							} else {
								$ins['created'] = time();
								$this->db->insert($ins, $this->table('BannersMedia'));
							}
							
							//$page->set_global($this->my('fullname') . '.success', 'Files(s) uploaded');
						}
						
					}
				} else {
					$newFile = $bannersDir . uniqid('') . '.' . $file->file_ext();
					if ($file->save_as($newFile)) {
						$fInfo = $file->file_info();
						if ($fileInfo = moon_file::info_unpack($fInfo)) {
							
							@list($mediaWidth, $mediaHeight) = explode('x', $fileInfo['wh']);
							if ($mediaWidth == '') {
								$mediaWidth = null;
							}
							$alternative = null;
							$mediaType = '';
							if ($fileInfo['ext'] == 'swf') {
								$mediaType = 'flash';
							} elseif (in_array($fileInfo['ext'], array('flv','mp4'))) {
								$mediaType = 'video';

								require_once(MOON_CLASSES . 'getid3/getid3.php');
								$getID3 = new getID3();
								$mediaInfo = $getID3->analyze($newFile);

								$f=fopen('tmp/preroll-log.txt','a');
								fwrite($f, print_r($mediaInfo, true));
 								fclose($f);

								$mediaWidth = '';
								$mediaHeight = '';
								$bitrate = '';
								$duration = '';

								if (is_array($mediaInfo) && !empty($mediaInfo['video'])) {
									$mediaWidth = isset($mediaInfo['video']['resolution_x']) ? $mediaInfo['video']['resolution_x'] : '';
									$mediaHeight = isset($mediaInfo['video']['resolution_y']) ? $mediaInfo['video']['resolution_y'] : '';
									$bitrate = isset($mediaInfo['video']['bitrate']) ? $mediaInfo['video']['bitrate'] : '';
									$duration = isset($mediaInfo['playtime_string']) ? $mediaInfo['playtime_string'] : '';
								}

								//if ($bitrate) {
									$alternative = serialize(array('bitrate'=>$bitrate, 'duration'=>$duration, 'ext'=>$fileInfo['ext']));
								//}
								
							} else {
								$mediaType = 'image';
							}

							$ins = array(
								'banner_id' => $bannerId,
								'filename' => $fileInfo['name_saved'],
								'media_type' => $mediaType,
								'media_width' => $mediaWidth,
								'media_height' => $mediaHeight,
								'alternative' => $alternative,
								'created' => time()
							);

							$this->db->insert($ins, $this->table('BannersMedia'));
							//$page->set_global($this->my('fullname') . '.success', 'Files(s) uploaded');
						}
					} else {
						$msg .= 'System error';
						$fileError = true;
					}
				}
			}
			if ($fileError) $page->page404();
			if ($msg == '') {
				$msg = 'swfupload:ok';
			}
			print $msg;
		}
	}
	
	function getAllSiteIds()
	{
		$items = array();
		$m = $this->db->single_query("show tables like '".$this->table('Servers')."'");
		$exist = (count($m) ? true : false);
		if ($exist) {
			$result = $this->db->array_query_assoc('
				SELECT site_id,id
				FROM ' . $this->table('Servers') . '
				WHERE server_disabled = 0
			', 'id');
			foreach ($result as $id => $r) {
				$items[$id] = $r['site_id'];
			}
			return $items;
		}
		foreach ($this->get_var('sitesNames') as $id => $r) {
			$items[$id] = $r['site_id'];
		}
		return $items;
	}
	
}
?>