<?php
class spotlight extends moon_com {

function onload()
{
	$this->filter = array();
	$this->formFilter = &$this->form();
	$this->formFilter->names('show_expired');

	$this->tblSpotlight = $this->table('Spotlight');
	$this->_minTime = -2147483647;
	$this->_maxTime = 2147483647;
	$this->imgWidth = '500';
	$this->imgHeight = '327';
	$this->place = 'home';

	// customize place
	/*
	$this->imgWidth = $this->my('module') == 'club' ? '730' : '500';
	$this->imgHeight = $this->my('module') == 'club' ? '250' : '327';
	$this->place = $this->my('module') == 'club' ? 'club' : 'home';
	*/

	// item form
	$this->formItem = &$this->form('item');
	$this->formItem->names('id', 'title', 'uri', 'img', 'img_alt', 'date_intervals', 'is_hidden',  'active_from', 'active_to', 'geo_target');
}
function events($event, $par)
{
	switch ($event) {
		case 'filter':
			$this->setFilter();
			break;
		case 'edit':
			$id = isset($par[0]) ? intval($par[0]) : 0;
			if ($id) {
				if (count($values = $this->getItem($id))) {
					$this->formItem->fill($values);
				}
				else {
					$this->set_var('error', '404');
				}
			}
			$this->set_var('view', 'form');
			break;
		case 'save':
			if ($id = $this->saveItem()) {
				if (isset($_POST['return']) ) {
					$this->redirect('#edit', $id);
				} else {
					$this->redirect('#');
				}
			} else {
				$this->set_var('view', 'form');
			}
			break;
		case 'delete':
			if (isset($_POST['it'])) $this->deleteItem($_POST['it']);
			$this->redirect('#');
			break;
		case 'imgtool':
			if (is_object($tool = & moon::shared('imgtool'))) {
				$id = empty($par[0]) ? 0 : $par[0];
				if (isset($_POST['id'])) {
					//cia img apdorojimas
					$this->imgReplace($_POST['id']);
					$tool->close();
				}
				$m = $this->getItem($id);
				$tool->show(array(
					'id' => $id,
					'src' => $this->get_var('imgSrcSpotlight').$m['img'],
					'minWH' => $this->imgWidth.'x'.$this->imgHeight,
					'fixedProportions' => TRUE
				));
			}
			//forget reikia kai nuimti filtra
			$this->forget();
			break;
		case 'sort':
			$itemId = 0;
			if (isset($_POST['rows'])) {
				$rows = explode(';', $_POST['rows']);
				if (!is_array($rows)) {
					exit;
				}
				$order = array();
				$when = '';
				$i = 1;
				foreach ($rows as $k => $id) {
					$key = substr($id, 3);
					$itemId = $key;
					if ($key == '') continue;
					$when .= 'WHEN id = ' . $key . ' THEN ' . $i++ . ' ';
				}
				$this->updateSortOrder($when);
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
	$vars['error'] = FALSE;
	return $vars;
}
function main($vars)
{
	$page = &moon::page();
	$win = &moon::shared('admin');
	$win->active($this->my('fullname'));
	$title = $win->current_info('title');
	$page->title($title);

	if ($vars['view'] == 'form') {
		return $this->renderForm($vars);
	} else {
		return $this->renderList($vars);
	}
}
function renderList($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();

	$page->js('/js/jquery/tablednd_0_5.js');

	$goEdit = $this->linkas('#edit','{id}');
	$tpl->save_parsed('item',array('goEdit' => $goEdit));

	$filter = $this->getFilter();

	$items = $this->getItems();
	$itemsList = '';

	if (!empty($items)) {

		$showExpired = isset($this->filter['show_expired']);

		// geo zones
		include_once(MOON_CLASSES."geoip/geoip.inc");
		$gi=new GeoIP;
		$geo = geo_zones();
		$now = round(time(), -2);

		foreach ($items as $item) {
			$item['title'] = htmlspecialchars($item['title']);
			$item['classHidden'] = ($item['is_hidden'] == 1) ? 'class="item-hidden"' : '';

			$geoTargets = array();
			foreach ($geo as $name=>$k) {
				$c  = strtoupper($name);
				$id = isset($gi->GEOIP_COUNTRY_CODE_TO_NUMBER[$c]) ? $gi->GEOIP_COUNTRY_CODE_TO_NUMBER[$c]:0;
				switch ($c) {
					case 'AA':
						$title= 'Austral-Asia';
						break;
					default:
						$title= $id ? $gi->GEOIP_COUNTRY_NAMES[$id] : NULL;
						break;
				}
				if ($item['geo_target'] & (1<<$k)) {
					$geoTargets[] = htmlspecialchars($title);
				}

			}
			if ($item['geo_target'] & 1) {
				$geoTargets[] = 'Other countries';
			}
			$item['geo_targets'] = implode(', ', $geoTargets);

			$dateRanges = '';
			$active = true;
			$expired = false;

			if ($item['date_intervals']) {
				$active = false;
				$ranges = explode(';', $item['date_intervals']);
				$i = 1;
				foreach ($ranges as $range) {
					list($from, $to) = explode(',', $range);

					if ($now >= $from && $now <= $to) {
						$active = true;
						//continue; // break;
					}

					$expired = false;
					$scheduled = false;
					$s = '';
					if ($from > $this->_minTime) {
						$s .= $tpl->parse('active_from', array('active_from' => gmdate('Y-m-d', $from)));
						$scheduled = !$active && $from > $now;
					}
					if ($to < $this->_maxTime) {
						$s .= $tpl->parse('active_to', array('active_to' => gmdate('Y-m-d', $to)));
						$expired = !$active && $to < $now;
					}

					if (!$showExpired && $expired) break;

					$status = $tpl->parse('status', array('expired' => $expired, 'scheduled' => $scheduled));

					if ($dateRanges != '') $dateRanges .= '<br />';
					if ($s != '') $dateRanges .= $s . $status;
				}
			}

			if (!$showExpired && $expired) continue;

			$item['date_ranges'] = $dateRanges;

			$itemsList .= $tpl->parse('item', $item);
		}
	}

	$m = array(
		'submenu' => $win->subMenu(),
		'viewList' => TRUE,
		'filter' => $tpl->parse('filter', $filter),
		'items' => $itemsList,
		'pageTitle' => $win->current_info('title'),
		'goNew' => $this->linkas('#edit'),
		'goDelete' => $this->my('fullname') . '#delete',
		'goSort' => $this->my('fullname') . '#sort'
	);

	return $tpl->parse('main', $m);
}
function renderForm($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();
	$info = $tpl->parse_array('info');

	$page->js('/js/jquery/livequery.js');
	$page->js('/js/modules_adm/other.spotlight.js');

	$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;

	$form = $this->formItem;
	$title = $form->get('id') ? $info['titleEdit'] . ' :: ' . $form->get('title') : $info['titleNew'];

	$m = array(
		'submenu' => $win->subMenu(),
		'viewList' => FALSE,
		'error' => ($err !== FALSE) ?  $info['error' . $err] : '',
		'event' => $this->my('fullname') . '#save',
		'id' => $form->get('id'),
		'goBack' => $this->linkas('#'),
		'pageTitle' => $win->current_info('title'),
		'formTitle' => htmlspecialchars($title),
		'refresh' => $page->refresh_field(),
		'imgDimensions' => $this->imgWidth.'x'.$this->imgHeight
	) + $form->html_values();
	$m['is_hidden'] = $form->checked('is_hidden', 1);

	if ($m['img']) {
		$m['imgSrc'] = $this->get_var('imgSrcSpotlight') . $m['img'];
		$m['imgTool'] = $this->linkas('#imgtool',$m['id']);
		$m['selfUrl'] = $this->linkas('#edit',$m['id']);
	}
	$m['geo_zones'] = '';

	// date ranges
	$m['now'] = gmdate('Y-m-d');
	$i = 1;
	$dateRanges = '';
	if ($m['date_intervals']) {
		$ranges = explode(';', $m['date_intervals']);
		foreach ($ranges as $r) {
			list($from, $to) = explode(',', $r); // error cases here

			$from = ((int)$from === $this->_minTime) ? '' : $from;
			$to = ((int)$to === $this->_maxTime) ? '' : $to;
			if ($from === '' && $to === '') {
				continue;
			}

			$range = array(
				'no' => $i++,
				'enableRemove' => $i > 2,
				'active-from' => $from !== '' ? gmdate('Y-m-d', intval($from)) : '',
				'active-to' => $to !== '' ? gmdate('Y-m-d', intval($to)) : ''
			);

			$dateRanges .= $tpl->parse('date_ranges', $range);
		}
	}
	$m['date_ranges'] = $dateRanges;

	// geo zones
	include_once(MOON_CLASSES."geoip/geoip.inc");
	$gi=new GeoIP;
	$geo = geo_zones();
	foreach ($geo as $name=>$k) {
		$c  = strtoupper($name);
		$id = isset($gi->GEOIP_COUNTRY_CODE_TO_NUMBER[$c]) ? $gi->GEOIP_COUNTRY_CODE_TO_NUMBER[$c]:0;
		switch ($c) {
			case 'AA':
				$title= 'Austral-Asia';
				break;
			default:
				$title= $id ? $gi->GEOIP_COUNTRY_NAMES[$id] : NULL;
				break;
		}
		$m['geo_zones'].= $tpl->parse('geo_zones', array(
			'value'   => $name,
			'name'    => htmlspecialchars($title),
			'checked' => ($m['geo_target'] & (1<<$k))
		)). ' ';
	}
	$m['geo_zones'] .= $tpl->parse('geo_zones', array(
		'value'   => '*',
		'name'    => 'Other countries',
		'checked' => ($m['geo_target'] & 1)
	));

	return $tpl->parse('main', $m);
}
function getItems()
{
	$sql = 'SELECT id, title, uri, is_hidden, geo_target, date_intervals
		FROM ' . $this->tblSpotlight . ' ' . $this->sqlWhere() . '
		ORDER BY sort_order ASC';
	$result = $this->db->array_query_assoc($sql);
	return $result;
}
function getItemsCount()
{
	$sql = 'SELECT count(*) as cnt
		FROM ' . $this->tblSpotlight . ' ' . $this->sqlWhere();
	$result = $this->db->single_query_assoc($sql);
	return $result['cnt'];
}
function sqlWhere()
{
	return 'WHERE place = \'' . $this->place . '\'';
}
function getItem($id)
{
	$sql = 'SELECT *
		FROM ' . $this->tblSpotlight . '
		WHERE id = ' . intval($id);
	return $this->db->single_query_assoc($sql);
}
function saveItem()
{
	$postData = $_POST;
	$form = &$this->formItem;
	$form->fill($postData);
	$values = $form->get_values();
	// Filtering
	$data['id'] = intval($values['id']);
	$data['title'] = strip_tags($values['title']);
	$data['uri'] = $values['uri'];
	$data['active_from'] = $values['active_from'];
	$data['active_to'] = $values['active_to'];
	$data['is_hidden'] = (empty($values['is_hidden'])) ? 0 : 1;
	$id = $data['id'];

	$geoTarget = 0;
	if (isset($postData['geo_target']) && is_array($postData['geo_target'])) {
		$zones = geo_zones();
	   	foreach ($postData['geo_target'] as $zone) {
		   	if (isset($zones[$zone])) {
		   		$bt = $zones[$zone];
		   	} elseif ('*' === $zone) {
		   		$bt = 0;
		   	} else {
		   		continue;
		   	}
			$geoTarget += 1 << ($bt);
		}
	}
	$geoTarget = (0 !== $geoTarget) ? $geoTarget : NULL;
	$form->fill(array('geo_target' => $geoTarget));

	// Validation
	$errorMsg = 0;
	if ($data['title'] == '') {
		$errorMsg = 1;
	} elseif ($data['uri'] == '') {
		$errorMsg = 2;
	} elseif (strpos($data['uri'], 'http') === 0 || strpos($data['uri'], 'www') === 0) {
		//$errorMsg = 5; allow all urls
	}

	// date intervals
	$dbIntervals = array();
	$activeFrom = $data['active_from'];
	$activeTo = $data['active_to'];
	$intervals = count($activeFrom);
	for ($i=0;$i<$intervals;$i++) {
		// from
		$timeFrom = $activeFrom[$i] ? strtotime($activeFrom[$i].' 00:00:00 +0000') : NULL;
		//var_dump($timeFrom);
		if ($timeFrom <= 0 && $activeFrom[$i] !== '') {
			$errorMsg = 3; // Incorect date
		}
		if ($timeFrom <= 0) {
			$activeFrom[$i] = $this->_minTime;
		} else {
			$activeFrom[$i] = $timeFrom;
		}
		// to
		$timeTo = $activeTo[$i] ? strtotime($activeTo[$i].' 23:59:59 +0000') : NULL;
		if ($timeTo <= 0 && $activeTo[$i] !== '') {
			$errorMsg = 3; // Incorect date
		}
		if ($timeTo <= 0) {
			$activeTo[$i] = $this->_maxTime;
		} else {
			$activeTo[$i] = $timeTo;
		}

		if ($timeFrom > $timeTo && $timeTo !== NULL) {
			$errorMsg = 3; // Incorect date
		}

		if ($activeFrom[$i] !== NULL && $activeTo[$i] !== NULL) {
			$dbIntervals[] = $activeFrom[$i] . ',' . $activeTo[$i];
		}

		$form->fill(array('date_intervals' => implode(';', $dbIntervals)));
	}

 	if ($errorMsg) {
		$this->set_var('error', $errorMsg);
		return FALSE;
	}

	// if was refresh skip other steps and return
	if ($wasRefresh = $form->was_refresh()) {
		return $id;
	}

	$ins = $form->get_values('title', 'uri', 'img', 'img_alt', 'date_intervals', 'is_hidden', 'geo_target');
	$ins['uri'] = urldecode($ins['uri']);
	$ins['place'] = $this->place;

	// image
	$del = isset($_POST['del_img']) && $_POST['del_img'] ? TRUE : FALSE;
	$err2 = 0;
	$img = $this->saveImage($id, 'img', $err2, $del);
	if (!$err2) {
		$ins['img'] = $img;
	} else {
		$ins['is_hidden'] = 1;
		$page = &moon::page();
		$tpl = &$this->load_template();
		$info = $tpl->parse_array('info');
		$page->alert(str_replace('{imgDimensions}', $this->imgWidth.'x'.$this->imgHeight, $info['errorImage' . $err2]));
	}

	if ($id) {
		$this->db->update($ins, $this->tblSpotlight, array('id' => $id));

		// log this action
		blame($this->my('fullname'), 'Updated', $id);
	} else {
		$id = $this->db->insert($ins, $this->tblSpotlight, 'id');

		// log this action
		blame($this->my('fullname'), 'Created', $id);
	}

	$form->fill(array('id' => $id));
	return $id;
}
function saveImage($id, $name, &$err, $del = FALSE) //insertina irasa
{
	$err=0;
	$dir=$this->get_dir('imgDirSpotlight');

	$sql= 'SELECT img
		FROM ' . $this->tblSpotlight . ' WHERE id = ' . $id;
	$is = $this->db->single_query_assoc($sql);

	$f=new moon_file;
	$isUpload = $f->is_upload($name,$e);
	//downloads pic from gallery and saves to tmp
	$tmpFileName = '';
	if(isset($_POST['gallery_file_name']) && isset($_POST['gallery_file_name'][$name]) && !$isUpload) {
		$pos = strrpos($_POST['gallery_file_name'][$name], '.');

		$tmpFileName = 'tmp/'.uniqid('gallery_').rand().
			substr($_POST['gallery_file_name'][$name], $pos, strlen($_POST['gallery_file_name'][$name])-$pos);

		$isUpload=$f->is_url_content($_POST['gallery_file_name'][$name], $tmpFileName);

	}elseif ($isUpload && !$f->has_extension('jpg,jpeg,gif,png')) {
		//neleistinas pletinys
		$err=1;
		return;
	}

	$newPhoto=$curPhoto=isset($is[$name]) ? $is[$name] : '';
	//ar reikia sena trinti?
	if ( ($isUpload || $del) && $curPhoto) {
		$fDel=new moon_file;
		if ($fDel->is_file($dir.$curPhoto)) {
			//gaunam failo pav. pagrindine dali ir extensiona
			$fnameBase = substr($curPhoto,0,13);
			$fnameExt = rtrim('.' . $fDel->file_ext(), '.');
			//trinamas pagrindinis img
			$fDel->delete();
			$newPhoto=null;
			//dabar dar trinam mazesni img
			if ($fDel->is_file($dir . '_' . $fnameBase . $fnameExt)) $fDel->delete();
		}
	}
	if ($isUpload) { //isaugom faila
		$fnameBase = uniqid('');
		$fnameExt = rtrim('.' . $f->file_ext(), '.');
		$img=&moon::shared('img');

		if ($f->file_wh() !== $this->imgWidth.'x'.$this->imgHeight) {
			$err=4;
			return;
		}
		$nameSave = $dir . $fnameBase . $fnameExt;

		//pernelyg dideli img susimazinam bent iki 800x800
		if ($img->resize($f,$nameSave,$this->imgWidth,$this->imgHeight) && $f->is_file($nameSave) ) {
			$newPhoto = $fnameBase . $fnameExt;

			//pagaminam mazesni paveiksliuka
			$img->resize_exact($f, $dir . '_' . $fnameBase . $fnameExt, 220, 144);
		} else {
			//technine klaida
			$err = 2;
		}
	}

	if ($newPhoto =='') {
		$err = 3;
		$newPhoto=null;
		$noPicture = TRUE;
	}

	//deletes tmp pic downloaded from gallery
	if($tmpFileName) {
		unlink($tmpFileName);
	}

	return $newPhoto;
}
//pakeiciam paveiksliuka gauta su crop toolsu
function imgReplace($id) //insertina irasa
{
	$dir = $this->get_dir('imgDirSpotlight');
	$is=$this->db->single_query_assoc('
		SELECT img
		FROM ' . $this->tblSpotlight . ' WHERE id=' . intval($id)
	);

	$f = new moon_file;
	if ($f->is_file($dir . substr_replace($is['img'], '_orig', 13, 0))) {
		$nw = $_POST['newwidth'];
		$nh = $_POST['newheight'];
		$left = $_POST['left'];
		$top = $_POST['top'];
		$img = &moon::shared('img');

		$newName = uniqid('') . '.' . $f->file_ext();

		//pernelyg dideli img susimazinam bent iki 800x800
		$nameSave = $dir . substr_replace($newName, '_orig',13,0);
		$img = & moon::shared('img');
		//padarom kopijas
		if ( $f->copy($nameSave) ) {
			//crop is originalo pagal imgtool duomenis
			if ($img->crop($f, $dir . $newName, $nw, $nh, $left, $top)) {
				if ($f->is_file($dir . $newName)) {
					$img->resize_exact($f, $dir . $newName, 260, 164);
				}
			}
			$this->db->update(array('img'=>$newName), $this->tblSpotlight, $id);

			//dabar trinam senus
			$del = array(
				$is['img'],
				substr_replace($is['img'], '_orig', 13, 0),
				substr_replace($is['img'], '_', 13, 0)
				);
			foreach ($del as $name) {
				if ($f->is_file($dir.$name)) {
					$f->delete();
				}
			}
		}
	}
}
function deleteItem($ids)
{
	if (!is_array($ids) || !count($ids)) return;
	foreach ($ids as $k => $v) {
		$ids[$k] = intval($v);
	}

	// delete image files
	$delete = $ids;
	$mediaDir = $this->get_dir('imgDirSpotlight');
	if (0 === count($delete)) {
		return;
	}
	$files = $this->db->array_query_assoc('
		SELECT img FROM '.$this->tblSpotlight.'
		WHERE id IN (' . implode(',', $delete) . ') AND img IS NOT NULL'
	);
	$deleteFile = new moon_file;
	foreach ($files as $file) {
		$filename = $file['img'];
		$filenameOrig = str_replace('.', '_orig.', $filename);

		if ($deleteFile->is_file($mediaDir.$filename)) {
			$deleteFile->delete();
		}
		if ($deleteFile->is_file($mediaDir.$filenameOrig)) {
			$deleteFile->delete();
		}
	}

	$this->db->query('DELETE FROM ' . $this->tblSpotlight . ' WHERE id IN (' . implode(',', $ids) . ')');
	// log this action
	blame($this->my('fullname'), 'Deleted', $ids);

	return TRUE;
}
function updateSortOrder($when)
{
	$sql = 'UPDATE ' . $this->tblSpotlight . '
		SET sort_order =
			CASE
			' . $when . '
			END';
	$this->db->query($sql);
}
function setFilter()
{
	$page = &moon::page();
	if (isset($_POST['filter'])) {
		$this->filter = $_POST['filter'];
		$page->set_global($this->my('fullname') . '.filter', $this->filter);
	} else {
		$page->set_global($this->my('fullname') . '.filter', '');
	}
}
function getFilter()
{
	$page = &moon::page();
	$savedFilter = $page->get_global($this->my('fullname') . '.filter');
	if (!empty($savedFilter)) {
		$this->filter = $savedFilter;
	}
	$this->formFilter->fill($this->filter);

	$filter = $this->formFilter->html_values();

	$filter['goFilter'] = $this->my('fullname').'#filter';
	$filter['noFilter'] = $this->linkas('#filter');
	$filter['isOn'] = '';

	// custom fields
	$filter['show_expired'] = $this->formFilter->checked('show_expired', 1);

	foreach ($this->filter as $k => $v) {
		if ($v) {
			$filter['isOn'] = 1;
			break;
		}
	}
	$filter['classIsOn'] = $filter['isOn'] ? ' filter-on' : '';

	return $filter;
}

}

?>