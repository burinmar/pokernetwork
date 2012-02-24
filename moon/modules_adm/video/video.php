<?php
class video extends moon_com {

function onload()
{
	$this->filter = array();
	$this->formFilter = &$this->form();
	$this->formFilter->names('name', 'playlists');

	$this->formItem = &$this->form('item');
	$this->formItem->names('id', 'playlist_ids', 'name', 'short_description', 'long_description', 'published_date', 'tags', 'thumbnail_url', 'length', 'is_hidden');

	$this->sqlWhere = ''; // set by filter
	$this->sqlOrder = '';
	$this->sqlLimit = ''; // set by paging
}
function events($event, $par)
{
	switch ($event) {
		case 'filter':
			$this->setFilter();
			break;
		case 'edit':
			$id = isset($par[0]) ? sprintf("%.0f", $par[0]) : 0;
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
		default:
			$this->setOrdering();
			$this->setPaging();
			break;
	}
	$this->use_page('Common');
}
function properties()
{
	$vars = array();
	$vars['view'] = 'list';
	$vars['currPage'] = '1';
	$vars['listLimit'] = '50';
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

	$currPage = $page->get_global($this->my('fullname') . '.currPage');
	if (!empty($currPage)) {
		$vars['currPage'] = $currPage;
	}

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
	$loc = &moon::locale();

	$ordering = $this->getOrdering();
	$filter = $this->getFilter();
	$paging = $this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit']);

	$goEdit = $this->linkas('#edit','{id}');
	$tpl->save_parsed('items',array('goEdit' => $goEdit));

	$items = $this->getItems();
	$itemsList = '';
	$now = round(time(), -2);
	foreach ($items as $item) {
		$item['name'] = htmlspecialchars($item['name']);
		$item['date'] = $loc->datef(substr($item['published_date'], 0, 10), 'DateTime');
		$item['thumbnail_url'] = $item['thumbnail_url'];
		$item['length'] = $this->miliSecToTime($item['length']);
		$item['status'] = ($item['is_hidden'] == 1) ? 'Hidden' : 'Visible';
		$item['classHidden'] = ($item['is_hidden'] == 1) ? 'class="item-hidden"' : '';
		$itemsList .= $tpl->parse('items', $item);
	}

	$m = array(
		'viewList' => TRUE,
		'filter' => $tpl->parse('filter', $filter),
		'items' => $itemsList,
		'paging' => $paging,
		'pageTitle' => $win->current_info('title'),
		'goDelete' => $this->my('fullname') . '#delete'
	) + $ordering;

	return $tpl->parse('main', $m);
}
function renderForm($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$sitemap = &moon::shared('sitemap');
	$page = &moon::page();
	$info = $tpl->parse_array('info');

	$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;

	$form = $this->formItem;
	$title = $form->get('id') ? $info['titleEdit'] . ' :: ' . $form->get('name') : $info['titleNew'];

	$showViewArticleLink = TRUE;
	$m = array(
		'viewList' => FALSE,
		'error' => ($err !== FALSE) ? $info['error' . $err] : '',
		'event' => $this->my('fullname') . '#save',
		'id' => $form->get('id'),
		'goBack' => $this->linkas('#') . '?page=' . $vars['currPage'],
		'pageTitle' => $win->current_info('title'),
		'formTitle' => htmlspecialchars($title),
		'refresh' => $page->refresh_field()
	) + $form->html_values();
	
	$m['uriVideo'] = $sitemap->getLink('video') . make_uri($m['name']) . '-' . $m['id'] . '.htm';
	$m['published_on'] = date('Y-m-d H:i', substr($m['published_date'], 0, 10));
	$m['is_hidden'] = $form->checked('is_hidden', 1);
	$m['showViewArticleLink'] = $showViewArticleLink;
	
	$m['reloadThumbUri'] = $this->linkas('video.video_import#reload-image', $m['id']);
	$m['reimportDataUri'] = $this->linkas('video.video_import#reimport-data', $m['id']);
	$m['timestamp'] = time();
	
	$m['playlists'] = '';
	$playlists = $this->getPlaylists();
	$assignedPlaylists = explode(',', $m['playlist_ids']);
	foreach($playlists as $id => $name) {
		$s = array(
			'playlist_id' => $id,
			'value'   => $id,
			'name'    => $name,
			'checked' => in_array($id, $assignedPlaylists)
		);
		$m['playlists'] .= $tpl->parse('playlists', $s);
	}

	return $tpl->parse('main', $m);
}
function getItems()
{
	$sql = 'SELECT id, published_date, name, short_description, thumbnail_url, length, is_hidden
		FROM ' . $this->table('Videos') . ' ' .
		$this->sqlWhere . ' ' .
		$this->sqlOrder . ' ' .
		$this->sqlLimit;
	$result = $this->db->array_query_assoc($sql);
	return $result;
}
function getItemsCount()
{
	$sql = 'SELECT count(*) as cnt
		FROM ' . $this->table('Videos') . ' ' .
		$this->sqlWhere;
	$result = $this->db->single_query_assoc($sql);
	return $result['cnt'];
}
function getItem($id)
{
	$sql = 'SELECT *
		FROM ' . $this->table('Videos') . '
		WHERE 	id = ' . sprintf("%.0f", $id);
	return $this->db->single_query_assoc($sql);
}
function getPlaylists()
{
	$sql = 'SELECT id, name
		FROM ' . $this->table('VideosPlaylists') . '
		WHERE 	is_hidden < 2
		ORDER BY sort_order ASC, name ASC';
	$result = $this->db->array_query_assoc($sql);

	$playlists = array();
	foreach ($result as $item) {
		$playlists[$item['id']] = $item['name'];
	}
	return $playlists;
}
function saveItem()
{
	$postData = $_POST;
	
	$tags = explode(',', $_POST['tags']);
	$tagsNew = array();
	foreach ($tags as $tag) {
		if ($tag == '') continue;
		$tagsNew[] = trim($tag);
	}
	$postData['tags'] = implode(',', $tagsNew);
	$playlists = (isset($_POST['playlists']) && is_array($_POST['playlists'])) ? $_POST['playlists'] : array();

	$form = &$this->formItem;
	$form->fill($postData);
	$values = $form->get_values();

	// Filtering
	$data = array();
	$data = $values;
	$data['id'] = sprintf("%.0f", $values['id']);

	$data['is_hidden'] = (empty($values['is_hidden'])) ? 0 : 1;
	$id = $data['id'];

	// Validation
	$errorMsg = 0;
	if ($data['name'] == '') {
		$errorMsg = 1;
	} elseif ($data['short_description'] == '') {
		$errorMsg = 2;
	}

 	if ($errorMsg) {
		$this->set_var('error', $errorMsg);
		return FALSE;
	}

	// if was refresh skip other steps and return
	if ($form->was_refresh()) {
		return $id;
	}

	$ins = $form->get_values('name', 'short_description', 'long_description', 'tags', 'is_hidden');
	
	if ($id) {
		$ins['playlist_ids'] = !empty($playlists) ? implode(',', $playlists) : '';
		
		$this->db->update($ins, $this->table('Videos'), array('id' => $id));
		
		// log this action
		blame($this->my('fullname'), 'Updated', $id);

		// update tags cache
		if (is_object($ctags = $this->object('other.tags_ctags'))) {
			$ctags->update($id, tags_ctags::videos, $tags);
		}

	} else {
		$errorMsg = 9;
		$this->set_var('error', $errorMsg);
		return FALSE;
	}

	$form->fill(array('id' => $id));
	return $id;
}
function srtToDfxp($file)
{
	$lang = str_replace('.srt', '', substr($file, strpos($file, '_')+1));
	$ttxml = '';
	$fullLine = '';
	if($fileArray = file($file)) {
		$ttxml = "\n\t".'<div xml:lang="'.$lang.'">'."\n";
		$linesCnt = count($fileArray);
		foreach($fileArray as $nr => $line) {
			$line = rtrim($line);
			
			// get begin and end
			if (strpos($line, ' --> ') !== false) {
				$parts = explode(' --> ', $line);
				$begin = isset($parts[0]) ? str_replace(' ', '', str_replace(',', '.', trim($parts[0]))) : '';
				$end = isset($parts[1]) ? str_replace(' ', '', str_replace(',', '.', trim($parts[1]))) : '';
				$fullLine = '';
			}
			// if the next line is not blank, get the text
			elseif($line != '') {
				if($fullLine != '') {
					$fullLine .= '<br />' . $line;
				} else {
					$fullLine .= $line;
				}
			}
			
			// if the next line is blank, write the paragraph
			if($line == '' || $linesCnt-1 == $nr) {
				$ttxml .= "\t\t<p begin=\"" . $begin . "\" end=\"" . $end . "\">" . $fullLine . "</p>\n";
				$fullLine = '';
			}
		}
		$ttxml .= "\t</div>";
	}
	return $ttxml;
}
function deleteItem($ids)
{
	if (!is_array($ids) || !count($ids)) return;
	foreach ($ids as $k => $v) {
		$ids[$k] = sprintf("%.0f", $v);
	}
	$this->db->query('UPDATE ' . $this->table('Videos') . ' SET is_hidden = 2 WHERE id IN (' . implode(',', $ids) . ')');

	// log this action
	blame($this->my('fullname'), 'Deleted', $ids);

	return TRUE;
}
function setSqlWhere()
{
	$where = array();
	$where[] = 'WHERE videos.is_hidden < 2';

	if (!empty($this->filter)) {
		if ($this->filter['name'] != '') {
			$where[] = 'videos.name LIKE \'%' . $this->db->escape($this->filter['name']) . '%\'';
		}
		if ($this->filter['playlist'] != '') {
			$where[] = 'FIND_IN_SET(' . $this->filter['playlist'] . ', videos.playlist_ids)';
		}
	}
	$this->sqlWhere = implode(' AND ', $where);
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
	$filter['name'] = $this->formFilter->get('name');
	$filter['playlist'] = $this->formFilter->get('playlist');

	$playlists = $this->getPlaylists();
	$filter['playlists'] = $this->formFilter->options('playlist', $playlists);

	foreach ($this->filter as $k => $v) {
		if ($v) {
			$filter['isOn'] = 1;
			break;
		}
	}
	$filter['classIsOn'] = $filter['isOn'] ? ' filter-on' : '';

	$this->setSqlWhere();

	return $filter;
}
function setPaging()
{
	$page = &moon::page();
	if (isset($_GET['page']) && is_numeric($_GET['page'])) {
		$currPage = $_GET['page'];
		$page->set_global($this->my('fullname') . '.currPage', $currPage);
	} else {
		$page->set_global($this->my('fullname') . '.currPage', 1);
	}
}
function getPaging($currPage, $itemsCnt, $listLimit)
{
	$pn = &moon::shared('paginate');
	$pn->set_curent_all_limit($currPage, $itemsCnt, $listLimit);
	$pn->set_url($this->linkas('#', '', array('page' => '{pg}')), $this->linkas('#'));
	$pnInfo = $pn->get_info();

	$this->sqlLimit = $pnInfo['sqllimit'];
	return $pn->show_nav();
}
function setOrdering()
{
	if (isset($_GET['ord'])) {
		$sort = (int)$_GET['ord'];
		$page = &moon::page();
		$page->set_global($this->my('fullname') . '.sort', $sort);
	}
}
function getOrdering()
{
	$page = &moon::page();
	$sort = $page->get_global($this->my('fullname') . '.sort');
	if (empty($sort)) {
		$sort = 1;
	}

	$links = array();
	$pn = &moon::shared('paginate');
	$ord = &$pn->ordering();
	$ord->set_values(
		//laukai, ir ju defaultine kryptis
		array('published_date' => 0, 'name' => 1, 'length' => 0, 'is_hidden' => 1) ,
		//antras parametras kuris lauko numeris defaultinis.
		0
	);

	$links = $ord->get_links(
		$this->linkas('#', '', array('ord' => '{pg}')),
		$sort
	);
	$this->sqlOrder = 'ORDER BY ' . $ord->sql_order();
	//gauna linkus orderby{nr}
	return $links;
}
function getPlaylistsForVideoStr($ids)
{
	$idsArray = explode(',', $ids);
	$sql = 'SELECT name
		FROM ' . $this->table('VideosPlaylists') . '
		WHERE id IN (\'' . implode('\', \'', $idsArray) . '\')';
	$result = $this->db->array_query_assoc($sql);
	
	$names = array();
	foreach ($result as $r) {
		$names[] = $r['name'];
	}
	return implode(', ', $names);
	
}
function miliSecToTime($miliseconds)
{
	return date('i:s',mktime(0,0,floor(($miliseconds/1000)),0,0,0));
}

}
?>