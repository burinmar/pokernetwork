<?php
class promo extends moon_com {

var $form, $formFilter;

function onload()
{
	//form of item
	$this->form = &$this->form();
	$this->form->names(	'id', 'room_id', 'title', 'url', 'active_from', 'active_to', 'hide',  'master_id', 'master_updated', 'updated');
	$this->form->fill();

	//main table
	$this->myTable=$this->table('PromoList');
}

function events($event,$par)
{

	if (isset($_POST['room_id'])) {
		$roomId = (int)$_POST['room_id'];
	}
	elseif (isset($par[0])) {
		$roomId = (int) $par[0];
	}
	else {
		$p = &moon::page();
		$p->page404();
	}
	$this->set_var('roomId', $roomId);

	switch ($event) {
	case 'edit':
        $id= isset($par[1]) ? intval($par[1]) : 0;
		if ($id) {
			if (count($values=$this->getItem($id))) {
				$this->form->fill($values);
			}
			else {
				$this->set_var('error','404');
			}
		}
		$this->set_var('view','form');
		break;
    case 'save':
		if ($id=$this->saveItem()) {
            $this->redirect('#',$roomId);
        }
		else {
        	$this->set_var('view','form');
		}
		break;
    case 'delete':
		if (isset($_POST['it'])) $this->deleteItem($_POST['it']);
		$this->redirect('#',$roomId);
		break;
    case 'sort' :
		if (isset ($_POST['rows'])) {
			$this->updateSortOrder($_POST['rows']);
		}
		$this->redirect('#', $roomId);
		break;
	default:
	}
	$this->use_page('Common');
}

function properties()
{
	return array( 'view'=>'list');
}

function main($vars)
{
	$p = &moon::page();
	$t = &$this->load_template();
	$info = $t->parse_array('info');
	$win = &moon::shared('admin');
	$win->active($this->my('fullname'));

    $roomId = $vars['roomId'];
	$roomName = $this->getRoomName($roomId);
	$submenu=$win->subMenu(array('*id*'=>$roomId));

	$a = array();
	   //******* FORM **********
		$err=(isset($vars['error'])) ? $vars['error']:0;

		$f = $this->form;
		$title= $f->get('id') ? $info['titleEdit'] : $info['titleNew'];
		$m = array(
			'error' => $err ? $info['error'.$err] : '',
			'event' => $this->my('fullname').'#save',
			'refresh' => $p->refresh_field(),
			'id' => ($id = $f->get('id')),
			'goBack' => $this->linkas('#',$roomId),

			'pageTitle' => $win->current_info('title'),
			'formTitle' => htmlspecialchars($title),
			'roomName' => htmlspecialchars($roomName),
			'room_id' => $roomId,
			'goToRooms' => $this->linkas('reviews#'),
			'submenu' => $submenu,
			'hide' => $f->checked('hide', 1)
		) + $f->html_values();
		$m['class-hide'] = $err || $id ? '' : ' hide';
		$m['now'] = date('Y-m-d');
		$m['cancel'] = $id || $err;
		if ($m['master_id']) {
			$m['syncStatus'] = (int)$m['updated']>(int)$m['master_updated'] ? 1 : 2;
			$a = (int)$m['updated']<0 ? 0 : $this->getMasterInfo($m['master_id']);
			if (!empty($a)) {
				$m['master_title'] = htmlspecialchars($a['title']);
				$m['master_url'] = nl2br(htmlspecialchars($a['url']));
			}
		}

		$res=$t->parse('viewForm',$m);



    	//******* LIST **********
		$p->js('/js/tablednd_0_5.js');
		$m = array('items'=>'');

		$dat=$this->getList($roomId);
		$goEdit=$this->linkas('#edit',$roomId . '.{id}');
		$t->save_parsed('items',array('goEdit'=>$goEdit));

		foreach ($dat as $d) {
			if (!$d['active'] || $d['hide']) {
				$d['class'] = 'item-hidden';
			}
			$d['styleTD'] = '';
			if (!empty($d['master_id'])) {
				//sync ikona
				$sType = (int)$d['master_updated']<(int)$d['updated'] ? 1 : 2;
				$d['styleTD'] = ''.$sType.'';
			}
			if ($id == $d['id']) {
				$d['styleTD'] .= '" style="background-color:#F0F8FF"';
			}
			if ($d['styleTD']) {
				$d['styleTD'] = ' class="sync' . $d['styleTD'] . '"';
			}
			if ($d['active_from'] || $d['active_to']) {
				$d['date'] =  $d['active_from'] ? $d['active_from'] : '-';
				$d['date'] .=  '/' . ($d['active_to'] ? $d['active_to'] : '-');
			}
			else {
				$d['date'] = '&nbsp;';
			}
			$d['title'] = htmlspecialchars($d['title']);
			$d['url'] = htmlspecialchars($d['url']);
			$m['items'] .= $t->parse('items',$d);
	    }
		$title = $win->current_info('title');
        $m['title'] = htmlspecialchars($title);
		$m['room_id'] = $roomId;
		$m['goDelete']=$this->my('fullname').'#delete';
		$m['goSort'] = $this->my('fullname') . '#sort';

		$res .= $t->parse('viewList',$m);


	$p->title($title);
	return $res;
}

//***************************************
//           --- DB AND OTHER ---
//***************************************


function getList($roomId)
{
	$sql='SELECT *,
			IF((ISNULL(active_from) OR CURDATE()>= active_from) AND (ISNULL(active_to) || CURDATE()<= active_to),1,0) as active
		FROM '.$this->myTable.'
		WHERE room_id=' . intval($roomId) . '
		ORDER BY ord_no';
	return $this->db->array_query_assoc($sql);
}

function getItem($id)
{
	return $this->db->single_query_assoc('
		SELECT * FROM ' . $this->myTable . ' WHERE
			id = ' . intval($id)
		);
}

function saveItem()
{
    $form=&$this->form;
	$form->fill($_POST);
    $d=$form->get_values();
	$id=intval($d['id']);
	$roomID = intval($d['room_id']);
	//gautu duomenu apdorojimas
	$d['hide'] = empty($d['hide']) ? 0 : 1;
	$d['active_from'] = $this->makeTime($d['active_from']);
	$d['active_to'] = $this->makeTime($d['active_to']);
    $form->fill($d,false); //jei bus klaida

	//validacija
	$err=0;
	if ($d['title']==='') $err=1;
    if ($err) {
		$this->set_var('error',$err);
		return false;
	}

	//jei refresh, nesivarginam
    if ($wasRefresh=$form->was_refresh()) return $id;

    //save to database
	$ins=$form->get_values('title', 'url', 'active_from', 'active_to', 'hide');
	$ins['updated'] = time();

	$db=&$this->db();
	if ($id) {
		$db->update_query($ins, $this->myTable, array('id'=>$id));
		blame($this->my('fullname'), 'Updated', $id);
	}
	else {
		$ins['room_id'] = $roomID;
		$ins['created'] = $ins['updated'];
		$id=$db->insert_query($ins, $this->myTable, 'id');
		blame($this->my('fullname'), 'Created', $id);
	}
	if (_SITE_ID_ === 'com') {
		cronTask($this->my('module') . '.promotions#sync-init');
	}
	$form->fill( array('id'=>$id) );
	return $id;
}

function makeTime($d) {
	if ($d) {
		if (count($a = explode('-', $d)) != 3 || !checkdate($a[1], $a[2], $a[0])) {
			return NULL;
		}
		return $d;
	}
	return NULL;
}

function deleteItem($ids)
{
	if (!is_array($ids) || !count($ids)) return;
	foreach ($ids as $k=>$v) $ids[$k]=intval($v);
	$this->db->query('DELETE FROM '.$this->myTable.' WHERE id IN ('.implode(',',$ids).')');
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
			SET ord_no =
				CASE
				' . $when . '
				END
			WHERE id IN (' . implode(', ', $ids) . ')';
		$this->db->query($sql);
		blame($this->my('fullname'), 'Updated', 'Changed order');
	}
}
	function updateSortOrder($rows) {
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
				SET ord_no =
					CASE
					' . $when . '
					END
				WHERE id IN (' . implode(', ', $ids) . ')';
			$this->db->query($sql);
			blame($this->my('fullname'), 'Updated', 'Changed order');
		}
	}


function getRoomName($id) {
		$sql = 'SELECT `name`
			FROM ' . $this->table('Rooms') . '
			WHERE id = ' . $id;
		$result = $this->db->single_query_assoc($sql);
		if (!empty($result)) {
			return $result['name'];
		} else {
			return '';
		}
	}

	function getMasterInfo($id)
	{
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->table('PromotionsMaster') . ' WHERE
			id = ' . intval($id)
		);
	}

}
?>