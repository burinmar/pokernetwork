<?php
class pages extends moon_com {


function onload()
{
	//form of item
	$this->form = &$this->form();
	$this->form->names(	'id', 'room_id', 'hide', 'uri',	'title', 'meta_title', 'meta_keywords', 'meta_description', 'content', 'content_html', 'recompile', 'is_link', 'bonus_code', 'tracker_url', 'is_html');
	$this->form->fill( array('hide'=>1) );

	//main table
	$this->myTable=$this->table('Pages');
}


function events($event,$par)
{
	if (isset($_POST['room_id'])) {
		$parentID = (int) $_POST['room_id'];
	}
	elseif (isset($par[0])) {
		$parentID = (int) $par[0];
	}
	else {
		$parentID = 0;
	}
	$this->set_var('parentID',$parentID);

	switch ($event) {
	case 'edit':
        $id= isset($par[1]) ? intval($par[1]) : 0;
		if (!empty($_GET['link'])) {
			$this->form->fill(array('is_link'=>1, 'hide'=>0));
		}
		if ($id) {
			if (count($values=$this->getItem($id))) $this->form->fill($values);
			else $this->set_var('error','404');
		}
		$this->set_var('view','form');
		break;
    case 'save':
		if ($id=$this->saveItem()) {
            if (isset($_POST['return']) ) $this->redirect('#edit',$parentID . '.' . $id);
			else $this->redirect('#', $parentID);
        } else $this->set_var('view','form');
		break;
	case 'save-link':
		if ($id=$this->saveLink()) {
            if (isset($_POST['return']) ) $this->redirect('#edit',$parentID . '.' . $id);
			else $this->redirect('#', $parentID);
        } else $this->set_var('view','formLink');
		break;
    case 'delete':
		if (isset($_POST['it'])) $this->deleteItem();
		$this->redirect('#', $parentID);
		break;
    case 'save-order':
		if (isset($_POST['rows'])) {
			$this->saveOrder($_POST['rows']);
		}
        $this->redirect('#',$parentID);
		break;
	default:
	}
	$this->use_page('Common');
}


function properties()
{
	return array('view'=>'list');
}

function main($vars)
{
	$p = &moon::page();
	$t = &$this->load_template();
	$info = $t->parse_array('info');
	$win = &moon::shared('admin');
	$win->active($this->my('fullname'));

    $roomID = intval($vars['parentID']);
	$roomName = $this->getRoomName($roomID);
	$submenu = $win->subMenu(array('*id*' => $roomID));

	$a = array();
	if ($vars['view'] == 'form' && $this->form->get('is_link')) {
		$vars['view'] = 'formLink';
	}
	if ($vars['view'] == 'form') {

        //******* FORM **********
		$err=(isset($vars['error'])) ? $vars['error']:0;
		$p->css($t->parse('cssForm'));

		$f = $this->form;
		$title= $f->get('id') ? $info['titleEdit'].' :: '.$f->get('title') : $info['titleNew'];
		$m = array(
			'error' => $err ? $info['error'.$err] : '',
			'event' => $this->my('fullname').'#save',
			'refresh' => $p->refresh_field(),
			'id' => ($id = $f->get('id')),
			'goBack' => $this->linkas('#', $roomID),
			'room_id' => $roomID,

			'pageTitle' => $win->current_info('title'),
			'formTitle' => htmlspecialchars($title),
			'toolbar' => '',
			'hide' =>$f->checked('hide',1),
			'submenu' => $submenu,
			'roomName' => $roomName,
		) + $f->html_values();

		// html ar text
		$m['is-text'] = $f->checked('is_html', 0);
		$m['is-html'] = $f->checked('is_html', 1);


		//pridedam attachmentus ir toolbara
		if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance( $this->get_var('rtf') );
			$m['toolbar'] = $rtf->toolbar('i_content',(int)$m['id']);
		}
		$res=$t->parse('viewForm',$m);

	}
	elseif ($vars['view'] == 'formLink') {

        //******* FORM **********
		$err=(isset($vars['error'])) ? $vars['error']:0;
		$p->css($t->parse('cssForm'));

		$f = $this->form;
		$title= $f->get('id') ? $info['titleLinkEdit'].' :: '.$f->get('title') : $info['titleLinkNew'];
		$m = array(
			'error' => $err ? $info['error'.$err] : '',
			'event' => $this->my('fullname').'#save-link',
			'refresh' => $p->refresh_field(),
			'id' => ($id = $f->get('id')),
			'goBack' => $this->linkas('#', $roomID),
			'room_id' => $roomID,

			'pageTitle' => $win->current_info('title'),
			'formTitle' => htmlspecialchars($title),
			'hide' =>$f->checked('hide',1),
			'submenu' => $submenu,
			'roomName' => $roomName,
		) + $f->html_values();
		$res=$t->parse('viewFormLink',$m);

	}
	else {

    	//******* LIST **********
		$p->js('/js/tablednd_0_5.js');
		$m = array('items'=>'');

		//generuojam sarasa
        $dat=$this->getList($roomID);
		$goEdit=$this->linkas('#edit',$roomID . '.{id}');
		$t->save_parsed('items',array('goEdit'=>$goEdit));

		foreach ($dat as $d) {
			$d['class'] = $d['hide'] ? 'item-hidden' : '';
			$d['title'] = htmlspecialchars($d['title']);
			$m['items'] .= $t->parse('items',$d);
	    }

		$m['goNew']=$this->linkas('#edit',$roomID);
		$m['goNewLink']=$this->linkas('#edit',$roomID,'link=1');
		$m['goDelete']=$this->my('fullname').'#delete';
		$m['goSort'] = $this->my('fullname') . '#save-order';
		$m['room_id'] = $roomID;
		$m['submenu'] = $submenu;
		$m['roomName'] = $roomName;

		$title = $win->current_info('title');
        $m['title'] = htmlspecialchars($title);

		$res = $t->parse('viewList',$m);

	}

	$p->title($title);
	return $res;
}

//***************************************
//           --- DB AND OTHER ---
//***************************************

function getList($roomId)
{
	$sql='SELECT * FROM '.$this->myTable.'
		WHERE room_id=' . intval($roomId) . ' AND hide<2
		ORDER BY sort';
	return $this->db->array_query_assoc($sql);
}

function getItem($id)
{
	$a = $this->db->single_query_assoc('
		SELECT * FROM ' . $this->myTable . ' WHERE
			id = ' . intval($id)
		);
	if (count($a)) {
		$a['is_html'] = ($a['content'] != '' OR ($a['content'] == '' AND $a['content_html'] == '')) ? 0 : 1;
	}
	return $a;
}

function saveItem()
{
    $form=&$this->form;
	$form->fill($_POST);
    $d=$form->get_values();
	$id=intval($d['id']);

	//gautu duomenu apdorojimas
	if ($d['content']==='') $d['content']=null;
	$d['hide'] = isset($_POST['hide']) && $_POST['hide'] ? 1:0;
    $form->fill($d, false); //jei bus klaida

	//validacija
	$err=0;
	if ($d['title']==='') $err=1;
	elseif (empty($d['room_id'])) $err=9;
	elseif (!is_object($rtf = $this->object('rtf'))) $err=9;
	//uri
	elseif ($d['uri']==='') $err=3;
	else {
		//check for duplicates
		$m = $this->db->array_query(
			'SELECT id FROM ' . $this->myTable .
			" WHERE uri='" . $this->db->escape($d['uri']) . "' AND id<>" . $id
			);
		if (count($m)) $err = 3;
	}
    if ($err) {
		$this->set_var('error',$err);
		return false;
	}

	//jei refresh, nesivarginam
    if ($wasRefresh=$form->was_refresh()) return $id;

    //save to database
	$ins=$form->get_values('room_id', 'hide', 'uri',	'title', 'meta_title', 'meta_keywords', 'meta_description', 'content', 'content_html', 'bonus_code', 'tracker_url');
	$ins['updated'] = time();


	//iskarpa ir kompiliuojam i html
    //$rtf->setInstance( $this->get_var('rtf') );
	//list(,$ins['content_html']) = $rtf->parseText($id,$ins['content']);

	if (empty($_POST['is_html'])) {
		$rtf->setInstance( $this->get_var('rtf') );
		list(,$ins['content_html']) = $rtf->parseText($id, $ins['content'], TRUE);
	} else {
		$ins['content'] = '';
	}


	$db=&$this->db();
	if ($id) {
		$db->update_query($ins, $this->myTable, array('id'=>$id));
		blame($this->my('fullname'), 'Updated', $id);
	}
	else {
		$ins['created'] = $ins['updated'];
		$id=$db->insert_query($ins, $this->myTable, 'id');
		blame($this->my('fullname'), 'Created', $id);
	}
    if ($id) { //"prisegam" objektus
		$rtf->assignObjects($id);
	}
	$form->fill( array('id'=>$id) );
	return $id;
}

function saveLink()
{
    $form=&$this->form;
	$form->fill($_POST);
    $d=$form->get_values();
	$id=intval($d['id']);

	//gautu duomenu apdorojimas
	$d['hide'] = isset($_POST['hide']) && $_POST['hide'] ? 1:0;
    $form->fill($d, false); //jei bus klaida

	//validacija
	$err=0;
	if ($d['title']==='') $err=1;
	elseif (empty($d['room_id'])) $err=9;
	//uri
	elseif ($d['uri']==='') $err=3;
    if ($err) {
		$this->set_var('error',$err);
		return false;
	}

	//jei refresh, nesivarginam
    if ($wasRefresh=$form->was_refresh()) return $id;

    //save to database
	$ins=$form->get_values('room_id', 'hide', 'uri',	'title');
	$ins['is_link'] = 1;
	$ins['updated'] = time();

	$db=&$this->db();
	if ($id) {
		$db->update_query($ins, $this->myTable, array('id'=>$id));
		blame($this->my('fullname'), 'Updated', $id);
	}
	else {
		$ins['created'] = $ins['updated'];
		$id=$db->insert_query($ins, $this->myTable, 'id');
		blame($this->my('fullname'), 'Created', $id);
	}
	$form->fill( array('id'=>$id) );
  	return $id;
}

function deleteItem()
{
	$ids = $_POST['it'];
	if (!is_array($ids) || !count($ids)) return;
	foreach ($ids as $k=>$v) $ids[$k]=intval($v);
	$this->db->query('UPDATE '.$this->myTable.' SET hide=2 WHERE id IN ('.implode(',',$ids).')');
	blame($this->my('fullname'), 'Deleted', $ids);
	return true;
}

function saveOrder($rows)
{
    $rows = explode(';',$rows);
	$order = array();
	$when = '';
	$i = 1;
	$ids = array();
	foreach ($rows as $id) {
		$key = intval(substr($id, 3));
		if (!$key) continue;
		$ids[] = $key;
		$when .= 'WHEN id = ' . $key . ' THEN ' . $i++ . ' ';
	}
	if (count($ids)) {
    	$sql = 'UPDATE ' . $this->myTable . '
			SET sort =
				CASE
				' . $when . '
				END
			WHERE id IN (' . implode(', ', $ids) . ')';
		$this->db->query($sql);
		blame($this->my('fullname'), 'Updated', 'Changed order');
	}
}

function getRoomName($id)
{
	$sql = 'SELECT `name` FROM ' . $this->table('Rooms') . ' WHERE id = ' . $id;
	$m = $this->db->single_query($sql);
	return (empty($m[0]) ? '' : $m[0]);
}

}
?>