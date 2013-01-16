<?php

class rtf2 extends moon_com {
	public $rtfDir = '/img/rtf/';
	public $swfUploadDir = '/js/swfupload/';
	public $tmpTable = 'sys_attachments';
	private $parentID = 0;

	//nustatymai
	function instances($instance) {

		/*$cfg=array(
		'tableObjects' => '', //table for storing attachments
		'fileDir' => '', //writable directory, where to put files on disk
		'fileSRC' => '', //file uri for "href" or "src"
		'fileExt' => 'jpg,jpeg,gif,png', //allowed file extensions
		'fileMaxSize' => '5MB', // pvz. 1024, 1KB, 1MB    (taciau <=10MB )
		'imgOrigWH' => '1024x1000', // width x height, resize uploaded images if larger  (>=200x200)
		'imgWH' => '524x1000', // width x height, max image size in page   (>=50x50)
		'features' => '-code,-smiles', // supported features
		'attachments' => '', // supported attachments: file, image, video, html
		'parserFeatures'=>array(), //kokius defaultinius nustatymus perrasyti  shared_text
		'canEdit' => '', //komponento pavadinimas, kuris turi metoda canEdit(), pvz. news.full
		);*/
		include (MOON_MODULES . $this->my('location') . 'rtf.instances.php');
		//print_r($cfg);
		return $cfg;
	}

	function setInstance($instance) {
		$this->instance = base64_encode($instance);
		$this->parentZone = 0;
		if (strpos($instance, ':')) {
			list($instance, $this->parentZone) = explode(':', $instance);
			$instance = trim($instance);
			$this->parentZone = intval(trim($this->parentZone));
		}
		$cfg = $this->instances($instance);
		$this->myTable = $cfg['attachmentsTable'];
		if (strpos($this->myTable, '|')) {
			list($this->myTable, $this->myTableColumn) = explode('|', $this->myTable);
		}
		else {
			$this->myTableColumn = 'attachments';
		}
		$s = strtolower($cfg['fileMaxSize']);
		$MB = 1024 * 1024;
		$this->maxFileSize = (int) $cfg['fileMaxSize'];
		if (strpos($s, 'kb')) {
			$this->maxFileSize = $this->maxFileSize * 1024;
		}
		elseif (strpos($s, 'mb')) {
			$this->maxFileSize = $this->maxFileSize * $MB;
		}
		if ($this->maxFileSize < 1024 || $this->maxFileSize > 10 * $MB) {
			$this->maxFileSize = 10 * $MB;
		}
		$wh = explode('x', $cfg['imgOrigWH']);
		$this->whO = count($wh) < 2 || $wh[0] < 200 || $wh[1] < 200 ? array(200, 200):array((int) $wh[0], (int) $wh[1]);
		$wh = explode('x', $cfg['imgWH']);
		$this->whT = count($wh) < 2 || $wh[0] < 50 || $wh[1] < 50 ? array(50, 50):array((int) $wh[0], (int) $wh[1]);
		$this->fileExt = $cfg['fileExt'];
		$this->fileDir = $cfg['attachmentsDir'];
		$this->fileSrc = '';
		$this->features = $cfg['features'];
		$this->attachments = $cfg['attachments'];
		$this->parserFeatures = $cfg['parserFeatures'];
		$this->canEdit = $cfg['canEdit'];
		return $this;
	}
	//___________________________________________________________________________
	//Prasideda komponentas


	//___________________________________________________________________________
	function onload() {
		$this->form = $this->form('id', 'parent_id', 'content_type', 'comment', 'url', 'options','wh');
		$this->form->fill(array('options' => ''));
		$instanceID = isset ($_REQUEST['wid']) ? base64_decode($_REQUEST['wid']):$this->get_var('rtf');
		$this->setInstance($instanceID);
	}

	//___________________________________________________________________________
	function events($event, $par) {
		if (isset ($_POST['parent_id'])) {
			$parentID = intval($_POST['parent_id']);
		}
		else {
			$parentID = isset ($par[0]) && is_numeric($par[0]) ? $par[0]:0;
		}
		$this->parentID = $parentID;
		if ($this->canEdit && is_callable($this->canEdit)) {
			if (!call_user_func($this->canEdit, $parentID)) {
				header('HTTP/1.0 404 Not Found');
				die();
			}
		}
		$this->forget();
		$this->use_page('');
		$this->set_var('parent_id', $parentID);
		switch ($event) {

			case 'preview':
				$this->set_var('view', 'preview');
				break;

			case 'add':
			case 'add-img':
				$this->set_var('view', 'form');
				$this->form->fill(array('content_type' => 'img', 'parent_id' => $parentID));
				break;

			case 'add-video':
				$this->set_var('view', 'form');
				$this->form->fill(array('content_type' => 'video', 'parent_id' => $parentID));
				break;

			case 'add-html':
				$this->set_var('view', 'form');
				$this->form->fill(array('content_type' => 'html', 'parent_id' => $parentID));
				break;

			case 'add-file':
				$this->set_var('view', 'form');
				$this->form->fill(array('content_type' => 'file', 'parent_id' => $parentID));
				break;

			case 'edit':
				//sleep(5);
				$id = isset ($par[1]) ? $par[1]:0;
				$this->set_var('view', 'form');
				$this->set_var('id',$id );
				break;

			case 'delete':
				$this->deleteItem($par[1]);
				break;

			case 'insert':
				$this->admSaveItem($err);
				$this->set_var('view', $err ? 'form':'return');
				break;

			default:
		}
	}

	//___________________________________________________________________________
	function properties() {
		return array('view' => '', 'parent_id' => 0, 'msg' => '');
	}

	//___________________________________________________________________________
	function main($vars = array()) {
		$p = moon :: page();
		$p->set_local('output', 'modal');
		$p->js($this->rtfDir . 'rtf.js');
		$t = $this->load_template('rtf');
		$info = $t->parse_array('info');
		$msg = '';
		if ($vars['msg']) {
			// Grazinam kad padarem
			$i = $vars['msg'];
			$msg = isset ($info[$i]) ? $info[$i]:'Ok: ' . $i;
		}
		switch ($vars['view']) {

			case 'link':
				// urlo iterpimui
				$res = $t->parse('form_link', array('instanceID' => $this->instance));
				break;

			case 'form':
				$id = isset($vars['id']) ? $vars['id'] : 0;
				if ($id && count($d = $this->admGetItem($id))) {
					$this->form->fill($d, true);
				}
				//Forma
				$a = array('form' => '', 'instanceID' => $this->instance);
				$a += $this->form->html_values();
				$a['parent_id'] = $this->parentID;
				switch ($a['content_type']) {

					case 'file':
						$tpl = 'file';
						$a['extensions'] = '.' . str_replace(',', ', .', $this->fileExt);
						break;

					case 'html':
						$tpl = 'html';
						break;

					case 'video':
						$tpl = 'video';
						break;

					default:
						$tpl = 'img';
						$storage = moon :: shared('storage')->location($this->fileDir);
						if ($is = $this->form->get('file')) {
							list($w, $h) = explode('x', $this->form->get('wh'));
							if ($h && $w) {
								$k = 100 / ($h > $w ? $h:$w);
								if ($k > 1) {
									$k = 1;
								}
								$a['w'] = round($w * $k);
								$h = $a['h'] = round($h * $k);
								$a['padding'] = floor((100 - $h) / 2);
								$a['img_src'] = $storage->url($is,'t');
							}
						}
						;
						//options
						if ($this->form->get('options') === '') {
							$this->form->fill(array('options' => ''));
						}
						$a['align-left'] = $this->form->checked('options', 'left');
						$a['align-center'] = $this->form->checked('options', 'center');
						$a['align-right'] = $this->form->checked('options', 'right');
						$a['align-default'] = $this->form->checked('options', '');
						if (strrpos($this->attachments, 'image+') !== FALSE && empty ($a['id'])) {
							$a['swfupload'] = 1;
							$p->js($this->swfUploadDir . 'swfupload.js');
							$p->js($this->swfUploadDir . 'swfupload.queue.js');
							$p->js($this->swfUploadDir . 'swfupload.handlers.js');
							$p->js($this->swfUploadDir . 'swfupload.fileprogress.js');
							$user = moon :: user();
							$swfKey = $user->id() ? $this->object('sys.login_object')->autologin_code($user->id(), $user->get('email')):'';
							$a['swfkey'] = $t->ready_js($swfKey);
						}
						//pick from gallery
						$a['galleryScript'] = ($gCat = $this->get_var('rtfAddGallery')) && is_numeric($gCat) ? '<script type="text/javascript">initMultiUpload(\'img\',' . $gCat . ');</script>':'';
				}
				$a['i'] = uniqid('');
				$a['form'] = $t->parse('form_' . $tpl, $a);
				$a['!action'] = $this->linkas('#');
				$a['event'] = $this->my('fullname') . '#insert';
				$a['refresh'] = $p->refresh_field();
				$a['error'] = '';
				$err = isset ($vars['error']) ? $vars['error']:'';
				if ($err) {
					$a['error'] = isset ($info[$err]) ? $info[$err]:'Error: ' . $err;
				}
				$a['header'] = $a['id'] ? $info['edit-' . $tpl]:$info['new-' . $tpl];
				$res = $t->parse('forms', $a);
				break;

			case 'return':
				// Grazinam kad padarem
				$a = array('instanceID' => $this->instance);
				$i = $vars['msg'];
				$a['msg'] = isset ($info['msg-' . $i]) ? $info['msg-' . $i]:'Ok: ' . $i;
				if (!empty ($vars['insertedID'])) {
					$a['new_obj_tag'] = '{id:' . $vars['insertedID'] . '}';
				}
				$a['i'] = uniqid('');
				$res = $t->parse('return', $a);
				break;

			case 'preview':
				// Preview
				if (isset ($_POST['body']) && $_POST['body'] != '') {
					list(, $res) = $this->parseText($this->parentID, $_POST['body']);
				}
				else {
					$res = $info['err.preview.nocontent'];
				}
				$txt = moon :: shared('text');
				$res = $txt->check_timer($res);
				$pages = $txt->break_pages($res);
				if (is_array($pages) && count($pages) > 1) {
					$a = array('navPreview' => '', 'pagesPreview' => '');
					foreach ($pages as $k => $v) {
						$d['id'] = $k;
						list($d['title'], $d['text']) = $v;
						$a['navPreview'] .= $t->parse('navPreview', $d);
						$d['size'] = moon :: file()->format_size(strlen(strip_tags($d['text'])));
						$a['pagesPreview'] .= $t->parse('pagesPreview', $d);
					}
				}
				else {
					$a = array('text' => $res);
				}
				$err = $txt->error(FALSE);
				if ($err !== FALSE) {
					$a['error'] = htmlspecialchars($err);
				}
				$res = $t->parse('preview', $a);
				moon_close();
				die($res);
				break;

			default:
				// ifframe browseris
				$parentID = $this->parentID;
				$p->set_local('output', 'popup');
				$a = array('instanceID' => $this->instance, 'available' => '');
				$list = $this->admGetList();
				$a['obj_cnt'] = count($list);
				$a['items'] = '';
				$a['msg'] = $t->ready_js($msg);
				$f = moon :: file();
				//features
				if ($this->attachments) {
					$d = explode(',', $this->attachments);
					foreach ($d as $v) {
						if ($v = trim($v)) {
							if ($a['available'] !== '')
								$a['available'] .= ' | ';
							$a['available'] .= $t->parse('available', array('objtype' => rtrim($v, '+')));
						}
					}
				}
				//kokias ico turim tipui file
				$tmp = explode(',', $info['fico_show']);
				$ficos = array('default' => $info['fico_default']);
				foreach ($tmp as $v) {
					$v = explode('|', $v);
					$ico = trim($v[0]);
					foreach ($v as $vv)
						$ficos[trim($vv)] = $ico;
				}
				$uniqID = uniqid('');
				$storage = moon :: shared('storage')->location($this->fileDir);
                //echo $this->parentZone;print_r($list);
				foreach ($list as $id => $v) {
					$v['id'] = $id;
					$v['go_edit'] = $this->linkas('#edit', $parentID . '.' . $v['id'], array('wid' => $this->instance));
					$v['go_delete'] = $this->linkas('#delete', $parentID . '.' . $v['id'], array('wid' => $this->instance));
					$v['tag'] = '{id:' . $v['id'] . '}';
					$v['comment'] = isset($v['comment']) ? htmlspecialchars($v['comment']) : '';
					$v['i'] = $uniqID;
					//jei file
					if ($v['content_type'] == 'file') {
						$b = $f->info_unpack($v['file']);
						$v['fico'] = isset ($ficos[$b['ext']]) ? $ficos[$b['ext']]:$ficos['default'];
						$v['fsize'] = $f->format_size($b['size']);
						$v['fname'] = $b['name_original'];
						$a['items'] .= $t->parse('itemFile', $v);
					}
					elseif ($v['content_type'] == 'img') {
						$f->file_name(basename($v['file']));
						if ($f->has_extension( '.gif,.png,.jpg,.jpeg')) {
							//$b = $f->info_unpack($v['thumbnail']);
							list($w, $h) = explode('x', isset($v['wh']) ? $v['wh'] : '100x100');
							if ($h && $w) {
								$k = 100 / ($h > $w ? $h:$w);
								if ($k > 1) {
									$k = 1;
								}
								$w = $v['w'] = round($w * $k);
								$h = $v['h'] = round($h * $k);
								//$v['img_src'] = $src.$b['name_saved'];
								//$v['img_src'] = $this->linkas('#', false, array('wid' => $this->instance, 'thumb' => $b['name_saved']));
								$v['img_src'] = $storage->url($v['file'],'t');
								$v['padding'] = floor((100 - $h) / 2);
							}
						}
						$a['items'] .= $t->parse('obj_item', $v);
					}
					else {
						$a['items'] .= $t->parse('obj_item', $v);
					}
				}
				$a['hasFiles'] = count($list) ? 'true':'false';
				$a['goNew'] = $this->linkas('#add');
				$a['urlVideo'] = $this->linkas('#add-video', false, array('wid' => $this->instance));
				$a['urlImg'] = $this->linkas('#add-img', false, array('wid' => $this->instance));
				$a['urlFile'] = $this->linkas('#add-file', false, array('wid' => $this->instance));
				$a['urlHtml'] = $this->linkas('#add-html', false, array('wid' => $this->instance));
				$a['urlLink'] = $this->linkas('#add-link', false, array('wid' => $this->instance));
				$a['i'] = $uniqID;
				$res = $t->parse('obj_browser', $a);
				moon_close();
				die($res);
		}
		return $res;
	}

	function admSaveItem(& $err) {
		$form = $this->form;
		$form->fill($_POST);
		$d = $form->get_values();
		$id = $d['id'];
		$d['layer']=$this->parentZone;
		$form->fill($d);
		$ins = $form->get_values('content_type', 'comment', 'options','layer');
		$ins['layer'] = $this->parentZone;
		//validacija
		$wasRefresh = $form->was_refresh();
		$isUpload = false;
		$stop = FALSE;
		$err = '';
		//failams reik issaugoti originalu pavadinima
		$fNameOrig = '';
		switch ($d['content_type']) {
			// Video
			case 'video':
				if ($d['comment'] === '') {
					$err = 'video.notext';
				}
				break;
				// HMTL
			case 'html':
				if ($d['comment'] === '') {
					$err = 'html.notext';
				}
				break;
				// File
			case 'file':
				if (!$wasRefresh) {
					$f = moon :: file();
					//jei atejo failas
					if ($isUpload = $f->is_upload('file', $e)) {
						$ins['fname'] = $f->file_name();
						if ($this->fileExt && !$f->has_extension($this->fileExt)) {
							$err = 'file.extension';
						}
					}
					else {
						$err = 'file.nofile';
					}
				}
				break;
				// Image
			case 'img':
			default:
				if (!empty ($_POST['swfupload'])) {
					$f = moon :: file();
					$isUpload = $f->is_upload('file', $errF);
					if (!$isUpload) {
						//jei neatejo failas
						echo 'No file received!';
						exit;
					}
					$ins['content_type'] = 'img';
					$stop = TRUE;
				}
				elseif (!$wasRefresh) {
					//paveiksliukas bus tik jeigu naujas objektas
					$f = moon :: file();
					if ($isUpload = $f->is_upload('file', $errF)) {
						//jei atejo failas uploadu
					}
					elseif (isset ($_POST['url']) && $_POST['url']) {
						//jei url
						$f->file_name('@' . $_POST['url']);
						$isUpload = TRUE;
					}
					elseif (isset ($_POST['gallery_file_name']) && isset ($_POST['gallery_file_name']['img'])) {
						//jei galerijos url
						$f->file_name('@' . $_POST['gallery_file_name']['img']);
						$isUpload = TRUE;
					}
					elseif (!$id && !$err) {
						// insertas ir failas neatejo
						$err = 'img.nofile';
					}
				}
				if ($isUpload && !$f->has_extension('jpg,jpeg,gif,png')) {
					//tik paveiksliukai turi buti
					$err = 'img.extension';
				}
		}
		if ($err) {
			$this->set_var('error', $err);
			return false;
		}
		if ($wasRefresh) {
			return $id;
		}

		/*save to database*/
		//dabar failas
		if ($isUpload) {
			if(is_array($files = $this->admSaveFile($f, $err2))) {
				list($ins['file'], $ins['wh']) = $files;
			}
			elseif ($err2) {
				$err = $err ? $err : $err2;
				$this->set_var('error', $err);
				return false;
			}
		}

		foreach ($ins as $k=>$v) {
			if (empty($v)) {
				unset($ins[$k]);
			}
		}

		if ($id) {
			$ins['updated'] = time();
			//ieskom nepriskirtu
			$a = $this->getObjects(0, TRUE);
			if (isset ($a[$id])) {
				$a[$id] = array_merge($a[$id], $ins);
				$uid = moon :: user()->id();
				$this->db->replace(array('user_id' => $uid, 'attachments' => $this->compact($a)), $this->tmpTable);
				//return;
			}
			else {
				$a = $this->getObjects($this->parentID, FALSE);
				if (isset ($a[$id])) {
					$a[$id] = array_merge($a[$id], $ins);
					$this->db->update(array($this->myTableColumn => $this->compact($a)), $this->myTable, (int) $this->parentID);
				}
			}
			$this->set_var('msg', 'updated');
		}
		else {
			$uid = moon :: user()->id();
			$ins['user_id'] = $uid;
			$ins['created'] = time();
			//gaunam turimus attacmentus
			$r = $this->db->single_query('SELECT attachments FROM ' . $this->tmpTable . ' WHERE user_id=' . $uid);
			$a = !empty ($r) ? $this->extract($r[0]):array();
			for ($id = 1; $id < count($a) + 2; $id++) {
				if (!isset ($a[$id])) {
					break;
				}
			}
			$a[$id] = $ins;
			$this->db->replace(array('user_id' => $uid, 'attachments' => $this->compact($a)), $this->tmpTable);
			$this->set_var('msg', 'inserted');
			$this->set_var('insertedID', $id);
		}
		if ($stop) {
			echo 'swfupload:ok';
			moon_close();
			exit;
		}
		return $id;
	}

	function admSaveFile($fileObj, & $err) {
		$err = 0;
		$isUpload = is_object($fileObj) ? true:false;
		if ($isUpload && $fileObj->file_size() > $this->maxFileSize) {
			//per didelis failas
			$err = 'file.toobig';
			return;
		}
		if ($isUpload) {
			$response =  moon :: shared('storage')->location($this->fileDir)->save($fileObj);
			if (!is_array($response)) {
				$err = 'err.nofile';
				//$err = 'img.404';
				return;
			}
			list($url, $wh) = $response;
			$url = basename($url);
			return array($url, $wh);
		}
		return FALSE;
	}

	//___________________________________________________________________________
	// sarasas
	function admGetList() {
		return $this->getObjects($this->parentID, TRUE);
	}

	//___________________________________________________________________________
	// vienas konkretus irasas
	function admGetItem($id) {
		$a = $this->admGetList();
		return isset ($a[$id]) ? $a[$id] + array('id' => $id):array();
	}

	//___________________________________________________________________________
	// iraso pasalinimas
	function deleteItem($id) {
		//ieskom nepriskirtu
		$a = $this->getObjects(0, TRUE);
		if (isset ($a[$id])) {
			$this->admSaveFile($id, $err);
			unset ($a[$id]);
			$uid = moon :: user()->id();
			$this->db->replace(array('user_id' => $uid, 'attachments' => $this->compact($a)), $this->tmpTable);
			return;
		}
		$a = $this->getObjects($this->parentID, FALSE);
		if (isset ($a[$id])) {
			$this->admSaveFile($id, $err);
			unset ($a[$id]);
			$this->db->update(array($this->myTableColumn => $this->compact($a)), $this->myTable, (int) $this->parentID);
		}
	}

	//***************************************
	//           --- EXTERNAL ---
	//***************************************
	function toolbar($textAreaName, $parentID) {
		$p = moon :: page();
		$p->js('/js/modal.window.js');
		$p->js($this->rtfDir . 'rtf.js');
		$p->css($this->rtfDir . 'rtf.css');
		$a = array();
		$a['name'] = $this->my('name') . $this->parentZone;
		$a['buttons'] = $this->features;
		$a['attachments'] = $this->attachments;
		$a['instance'] = $this->instance;
		$a['fld'] = $textAreaName;
		$a['go_preview'] = $this->url('#preview', $parentID);
		if (is_object($this->object('hands'))) {
			$a['urlHands'] = $this->link('hands#');
		}
		if (is_object($this->object('poll.poll_for_rtf'))) {
			$a['urlPoll'] = $this->link('poll.poll_for_rtf#');
		}
		$a['goObjList'] = $this->attachments ? $this->url('#', $parentID):'';
		$t = $this->load_template('rtf');
		return $t->parse('toolbar', $a);
	}

	// nepriskirtu isoriniu objektu priskyrimas objektui
	function assignObjects($objID) {
		$u = moon :: user();
		if (($uid = $u->get_user_id()) && $this->myTable) {
			$r = $this->db->single_query('SELECT attachments FROM ' . $this->tmpTable . ' WHERE user_id=' . $uid);
			if (!empty ($r[0])) {
				$this->db->query('UPDATE ' . $this->myTable . " SET {$this->myTableColumn}='" . $this->db->escape($this->compact($this->getObjects($objID, TRUE))) . "' WHERE id=" . (int) $objID);
				$this->db->query('DELETE FROM ' . $this->tmpTable . ' WHERE user_id=' . $uid);
			}
		}
	}

	//objektui priskirtu failu sarasiukas
	function getObjects($parentID, $showNew = false) {
		//if (!count($ids) || !$this->myTable) return array();
		$r = $this->db->single_query('SELECT ' . $this->myTableColumn . ' FROM ' . $this->myTable . ' WHERE id=' . (int)$parentID);
		$a = empty ($r) ? array():$this->extract($r[0]);
		if ($showNew) {
			$u = moon :: user();
			$uid = intval($u->get_user_id());
			$r = $this->db->single_query('SELECT attachments FROM ' . $this->tmpTable . ' WHERE user_id=' . $uid);
			if (!empty ($r)) {
				$my = $this->extract($r[0]);
				$needUpdate = FALSE;
				foreach ($my as $id=>$v) {
					if (isset($a[$id])) {
						$needUpdate = TRUE;
						break;
					}
				}
				if ($needUpdate) {
					$b = array();
					foreach ($my as $id=>$v) {
						while (isset($a[$id])) {
							$id++;
						}
						$b[$id] = $v;
						$a[$id] = $v;
					}
					$this->db->replace(array('user_id' => $uid, 'attachments' => $this->compact($b)), $this->tmpTable);
				}
				else {
					$a += $my;
				}
			}
		}
		return $a;
	}

	function extract($text) {
		if ('' == $text || !is_array($a = unserialize($text))) {
			return array();
		}
		//if ($location !== FALSE) {
		if (empty($this->parentZone)) {
			foreach ($a as $k => $v) {
				if (!empty ($v['layer'])) {
					unset ($a[$k]);
				}
			}
		}
		else {
			foreach ($a as $k => $v) {
				if (empty($v['layer']) || $this->parentZone != $v['layer']) {
					unset ($a[$k]);
				}
			}
		}
		return $a;
	}

	function compact($array) {
		return empty ($array) ? '':serialize($array);
	}

	//___________________________________________________________________________
	function removeObjects($parentID) {
		moon :: error('Nebaigtas', 'f');
		return;
		$this->parentID = $parentID;
		$dat = $this->getObjects($parentIDs, false);
		foreach ($dat as $d) {
			$this->deleteItem($d['id']);
		}
	}

	//___________________________________________________________________________
	// teksto apdorojimas
	function parseText($parent_id, $source, $alertErrors = FALSE) {
		$txt = moon :: shared('text');
		$txt->features = array_merge($txt->features, $this->parserFeatures);
		//print_a($txt->features);
		//die();
		if (is_array($source)) {
			list($source,$objects) = $source;
			$objects = $this->extract($objects);
		}
		elseif ($parent_id !== FALSE) {
			$objects = $this->getObjects($parent_id, true);
		}
		else {
			$objects = FALSE;
		}
		if (!empty($objects) && is_array($objects)) {
			$txt->objects(array($this->fileDir, $this->fileSrc), $objects);
		}
		$res = $txt->article($source);
		if ($alertErrors) {
			$txt->error();
		}
		return array($txt->preview($source), $res);
	}

	function parseTextTypeImg($parent_id, $source, $alertErrors = FALSE, $attachJs = true) {
		$txt = moon :: shared('text');
		$txt->features = array_merge($txt->features, $this->parserFeatures);
		if ($parent_id !== FALSE) {
			$objects = $this->getObjects($parent_id, true);
			//$objects = array_reverse($objects);
			$objectsImg = array();
			$objectsOther = array();
			$images = array();
			$startPos = null;
			$startIdx = null;
			$tpl = $this->load_template('rtf');
			$m = array('items:images' => '', 'items:tabs' => '', 'attachJs' => $attachJs);
			$images = array();
			// split images and other objects
			foreach ($objects as $k => $o) {
				if ($o['content_type'] == 'img') {
					// image
					$pos = strpos($source, '{id:' . $o['id'] . '}');
					if ($pos !== FALSE) {
						$objectsImg[$k] = $o;
						$a = explode('|', $o['file']);
						if (!empty ($a[3])) {
							$vars = array('imgSrc' => str_replace('adm/', '', $this->linkas('articles.img#' . $a[3], '550.400')), 'thumbSrc' => str_replace('adm/', '', $this->linkas('articles.img#' . $a[3], '120.90')), 'url.img' => '/files/cnt/' . $a[3], 'description' => $o['comment']);
							$images[$pos] = $vars;
							//$m['items:images'] .= $tpl->parse('items:images', $vars);
							//$m['items:tabs'] .= $tpl->parse('items:tabs', $vars);
						}
						if ($startPos == null || $pos < $startPos) {
							$startPos = $pos;
							$startIdx = $k;
						}
					}
				}
				else {
					$objectsOther[] = $o;
				}
			}
			ksort($images);
			$nr = 0;
			foreach ($images as $img) {
				$img['hide'] =++$nr != 1 ? 1:0;
				$m['items:images'] .= $tpl->parse('items:images', $img);
				$m['items:tabs'] .= $tpl->parse('items:tabs', $img);
			}
			if (isset ($objectsImg[$startIdx])) {
				$htmlCode = $tpl->parse('article_img_slideshow', $m);
				// assign code as html attachment to first image id
				$attImg = array('id' => $objectsImg[$startIdx]['id'], 'file' => null, 'thumbnail' => null, 'parent_id' => $objectsImg[$startIdx]['parent_id'], 'comment' => $htmlCode, 'content_type' => 'html', 'options' => null, 'obj_type' => 'html');
				unset ($objectsImg[$startIdx]);
				$objectsOther[] = $attImg;
				// assign other objects
				$txt->objects(array($this->fileDir, $this->fileSrc), $objectsOther);
				// remove left image ids from source
				foreach ($objectsImg as $v) {
					$source = str_replace('{id:' . $v['id'] . '}', '', $source);
				}
			}
			else {
				$txt->objects(array($this->fileDir, $this->fileSrc), $objects);
			}
		}
		$res = $txt->article($source);
		if ($alertErrors) {
			$txt->error();
		}
		return array($txt->preview($source), $res);
	}

}

?>