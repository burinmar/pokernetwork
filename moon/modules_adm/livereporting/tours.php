<?php
class tours extends moon_com {

function onload()
{
	$this->formItem = &$this->form();
	$this->formItem->names('id', 'title', 'meta_title', 'meta_keywords', 'meta_description', 'about', 'is_hidden', 'playlist_id', 'news_tag');
}
function events($event, $par)
{
	switch ($event) {
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

	$info = $tpl->parse_array('info');
	$goEdit = $this->linkas('#edit','{id}');
	$tpl->save_parsed('item',array('goEdit' => $goEdit));

	$items = $this->getItems();
	$itemsList = '';
	foreach ($items as $id => $item) {
                $item['id'] = $id;
                $item['status'] = (isset($item['is_hidden']) && $item['is_hidden'] == 0) ? '' : '<span class="hiddenIco" title="Hidden"></span>';
		$item['classHidden'] = (isset($item['is_hidden']) && $item['is_hidden'] == 0) ? '' : 'class="item-hidden"';
		$itemsList .= $tpl->parse('item', $item);
	}

	$main = array();
	$main['viewList'] = TRUE;
	$main['items'] = $itemsList;
	$main['pageTitle'] = $win->current_info('title');

	return $tpl->parse('main', $main);
}
function renderForm($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();
	$info = $tpl->parse_array('info');

	$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;

	$form = $this->formItem;
	$title = $form->get('id') ? $info['titleEdit'] . ' :: ' . $form->get('title') : '';

	$main = array();
	$main['viewList'] = FALSE;
	$main['error'] = ($err !== FALSE) ? $info['error' . $err] : '';
	$main['event'] = $this->my('fullname') . '#save';
	$main['id'] = $form->get('id');
	$main['goBack'] = $this->linkas('#');
	$main['pageTitle'] = $win->current_info('title');
	$main['formTitle'] = htmlspecialchars($title);
	$main['refresh'] = $page->refresh_field();
		$main['toolbar'] = '';
	$main += $form->html_values();
	$main['is_hidden'] = $form->checked('is_hidden', 1);
		
		// add toolbar
	if (is_object( $rtf = $this->object('rtf') )) {
		$rtf->setInstance( $this->get_var('rtf') );
		$main['toolbar'] = $rtf->toolbar('i_about',(int)$main['id']);
	}
		
	return $tpl->parse('main', $main);
}
function getItems()
{
	// functions.php
	$tours = poker_tours();
	$sql = 'SELECT id, is_hidden
	FROM ' . $this->table('Tours');
	$result = $this->db->array_query_assoc($sql);
	foreach ($result as $r) {
		$tours[$r['id']]['is_hidden'] = (isset($tours[$r['id']])) ? $r['is_hidden'] : 1;
	}
	uasort($tours, array($this, 'cmpItems'));
	return $tours;
}
private function cmpItems($a, $b) {
	return strcmp($a['title'], $b['title']);
}
function getItemsCount()
{
		// functions.php
	return count(poker_tours());
}
function getItem($id)
{
		$tours = poker_tours();
		if (empty($tours[$id])) {
				return array();
		}
		
		// adjust for empty records and stored array
		$sql = 'SELECT *
		FROM ' . $this->table('Tours') . '
		WHERE id = ' . intval($id);
		$result = $this->db->single_query_assoc($sql);
		if (!empty($result)) {
				$data = $result;
		} else {
				$data = array(
						'id' => $id,
						'meta_title' => '',
						'meta_keywords' => '',
						'meta_description' => '',
						'about' => '',
						'news_tag' => '',
						'is_hidden' => 1
				);
		}
		
	if (empty($data['playlist_id'])) unset($data['playlist_id']);
		return array_merge($tours[$id], $data);
}
/**
 * strip multiple slashes at the end of uri field
 */
function saveItem()
{
	$form = &$this->formItem;
	$form->fill($_POST);
	$values = $form->get_values();
	// Filtering
	$data['id'] = intval($values['id']);
	$data['meta_title'] = strip_tags($values['meta_title']);
	$data['meta_keywords'] = $values['meta_keywords'];
	$data['meta_description'] = $values['meta_description'];
	$data['playlist_id'] = $values['playlist_id'];
	$data['about'] = $values['about'];
	$data['news_tag'] = $values['news_tag'];
	$data['is_hidden'] = (empty($values['is_hidden'])) ? 0 : 1;
	$id = $data['id'];

	// Validation
	$errorMsg = 0;
	if (!empty($data['playlist_id']) && !ctype_digit($data['playlist_id'])) {
		$errorMsg = 1;
	} elseif (!is_object($rtf = $this->object('rtf'))) {
		$errorMsg = 9;
	}
	
	if ($errorMsg) {
		$this->set_var('error', $errorMsg);
		return FALSE;
	}
	
	// if was refresh skip other steps and return
	if ($wasRefresh = $form->was_refresh()) {
		return $id;
	}

	$ins = $form->get_values('id', 'meta_title', 'meta_keywords', 'meta_description', 'news_tag', 'about', 'is_hidden', 'playlist_id');
	$rtf->setInstance( $this->get_var('rtf') );
	list(,$ins['about_html']) = $rtf->parseText($id, $ins['about'], TRUE);
	
		// if row exists
		$result = $this->db->single_query_assoc('
				SELECT count(*) as cnt from ' . $this->table('Tours') . '
				WHERE id = ' . $id
		);
		if ($result['cnt']) {
			$this->db->update($ins, $this->table('Tours'), array('id' => $id));

			// log this action
			blame($this->my('fullname'), 'Updated', $id);
			livereporting_adm_alt_log(0, 0, 0, 'update', 'tours', $id);
		} else {
			$this->db->insert($ins, $this->table('Tours'), 'id');

			// log this action
			blame($this->my('fullname'), 'Created', $id);
			livereporting_adm_alt_log(0, 0, 0, 'insert', 'tours', $id);
		}

	$form->fill(array('id' => $id));
	return $id;
}

}

?>