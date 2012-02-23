<?php
/**
 * @package livereporting
 */
/**
 * @package livereporting
 */
class shoutbox extends moon_com {
	var $msgLimit = 300;

	function onload()
	{
		$this->form = & $this->form('content');
		$this->form->fill();

		$user = & moon :: user();
		$page = & moon::page();
		$this->iAdmin = $user->i_admin('reporting') && $page->get_global('adminView');
	}

	function events($event, $argv)
	{
		$page = &moon::page();
		if (isset($_GET['del'])) {
			//trinam viena msg
			$this->forget();
			$par[0] = (int) $_GET['del'];
			$event = 'delete';
		} elseif (isset($_GET['ajax'])) {
			//prasomas ajaxu atnaujinimas
			$this->forget();
			$event = 'ajax';
		}

		if (!isset($argv['event_id'])) {
			$lr = $this->object('livereporting');
			list ($component, $argv) = $lr->readUri();
		}
		if (empty($argv['event_id'])) {
			moon_close();
			exit;
		}
		$this->parentID = $argv['event_id'];
		switch ($event) {
			case "save" :
				if(!$this->not_expired()) {
					$page->back(TRUE);
				}
				$this->saveItem();
				$page->back(TRUE);

				break;
			case "delete" :
				//pasalinam viena arba multiple zinutes
				$this->forget();
				$ids = isset ($_POST['it']) ? $_POST['it'] : $par[0];
				$this->deleteItems($ids);
				$page->back(TRUE);
				break;

			case "ajax-redirected" :
				$msgcnt = 0;
				return $this->messagesHMTL($msgcnt);

			case "ajax" :
				//atnaujinam ajaxu
				$this->forget();
				moon_close();
				$msgcnt = 0;
				die($this->messagesHMTL($msgcnt));
				break;

			default :
				//shoutbox archyvas
				if (isset ($_GET['page'])) {
					$this->set_var('psl', (int) $_GET['page']);
				}
				$this->set_var('archive', 1);
				$this->use_page('LiveReporting1col');
		}
	}


	function main($vars)
	{
		$tpl = &$this->load_template();
		$info = $tpl->parse_array('info');
		$text   = &moon::shared('text');
		$locale = &moon::locale();
		$page   = &moon::page();
		$user   = &moon::user();
		
		if (empty ($vars['archive'])) {
			//shoutbox box
			$evt = $this->object('livereporting_event');
			$this->parentID = $evt->requestArgv('event_id');
			$baseUrl = $this->linkas('event#view', array(
				'event_id' => $this->parentID,
				'path' => 'shoutbox'
			));
			$myID = $user->get_user_id();
			$tplArgv = array(
				'items' => '',
				'goArchive' => $baseUrl,
				'ajaxEvent'   => $evt->requestArgv('event_id'),
				'defaultMsg' => $info['default_content'],
				'maxlimit' => $this->msgLimit,
				'form' => ''
			);
			if ($myID) {
				$err = isset ($vars['error']) 
					? $vars['error']
					: 0;
				$info['error2'] = str_replace("{max_limit}", $this->msgLimit, $info['error2']);
				$form = $this->form;
				$formArgv = array(
					'error' => $err ? $info['error' . $err] : '',
					'event' => $this->my('fullname') . '#save',
					'refresh' => $page->refresh_field(),
				) + $form->html_values();
				if ($formArgv['content'] == "") {
					$formArgv['content'] = $info['default_content'];
				}
				
				$tplArgv['form'] = $tpl->parse('form', $formArgv);
			}
			$tplArgv['not_expired'] = ($this->not_expired()) ? TRUE : FALSE;
			$msgcnt = 0;
			$tplArgv['ajax-content'] = $this->messagesHMTL($msgcnt);
			$res = $tpl->parse('viewBox', $tplArgv);
			if ($tplArgv['not_expired'] == false && $msgcnt == 0) {
				$res = '';
			}
			if ($this->parentID == 735) {
				$res = $tpl->parse('chirps') . $res;
			}
		} else {
			$evt = $this->object('livereporting_event');
			$this->parentID = $evt->requestArgv('event_id');

			$this->set_var('archive', 0);
			$tplArgv = array(
				'arch-items' => '',
				'paginate' => ''
			);
			$count = $this->countItems();
			if ($count) {
				//puslapiavimui
				$baseUrl = $this->linkas('event#view', array(
					'event_id' => $this->parentID,
					'path' => 'shoutbox'
				));
				$vars['psl'] = empty($vars['psl']) ? 1 : $vars['psl'];
				$pn = & moon :: shared('paginate');
				$pn->set_curent_all_limit($vars['psl'], $count, 30);
				$pn->set_url(
					$this->linkas('event#view', array(
						'event_id' => $this->parentID,
						'path' => 'shoutbox',
					), array('page' => '{pg}')),
					$this->linkas('event#view', array(
						'event_id' => $this->parentID,
						'path' => 'shoutbox'
					))
				);
				$tplArgv['paginate'] = $pn->show_nav();
				$psl = $pn->get_info();

				$dat = $this->getList($psl['sqllimit']);
				$uID = array();
				foreach ($dat as $v) {
					$uID[] = $v['user_id'];
				}
				$users = $this->users($uID);

				$tplArgv['iAdmin'] = $this->iAdmin;
				$tplArgv['event'] = $this->my('fullname') . '#delete';

				$formArgv = array('iAdmin'=>$tplArgv['iAdmin']);
				foreach ($dat as $v) {
					$formArgv['id'] = $v['id'];
					$formArgv['msg'] = htmlspecialchars($v['content']);
					$formArgv['date'] = $text->ago($v['created'], true);
					if ($formArgv['date'] === "") {
						$formArgv['date'] = $locale->datef($v['created'], 'WdayDateTime');
					}
					$formArgv['goUser'] = "#";
					$formArgv['nick'] = "?";
					if (isset ($users[$v['user_id']])) {
						$ui = $users[$v['user_id']];
						$formArgv['nick'] = htmlspecialchars($ui['nick']);
						$formArgv['goUser'] = $ui['uri'];
					}
					$tplArgv['arch-items'] .= $tpl->parse('arch-items', $formArgv);
				}
			}

			$argv = array_merge($evt->requestArgvAll(), array(
				'render' => 'custom',
				'tab' => 'custom',
				'content' => array(
					'body' => $tpl->parse('viewArchive', $tplArgv),
					'id' => 'livePokerLiveReporting',
					'right' => ''
				)
			));
			$res = $evt->main($argv);
		}
		return $res;
	}


	function messagesHMTL(&$msgcnt)
	{
		$user  = &moon::user();
		$text  = &moon::shared('text');
		$locale= &moon::locale();
		$tpl   = &$this->load_template();

		$dat = $this->getList(' LIMIT 5');
		$user_ids = array();
		foreach ($dat as $k => $v) {
			$user_ids[] = $v['user_id'];
		}
		$users = $this->users($user_ids);
		$iAdmin = $this->iAdmin;
		$myID = $user->get_user_id();
		$m = array('items' => '');
		$d = array();
		$baseUrl = NULL;
		$msgcnt = 0;
		foreach ($dat as $v) {
			$d['id'] = $v['id'];
			$d['canDel'] = $iAdmin || $v['user_id'] == $myID ? 1 : 0;
			if ($d['canDel'] && $baseUrl == NULL) {
				$baseUrl = $this->linkas('event#view', array(
					'event_id' => $this->parentID,
					'path' => 'shoutbox'
				));
				$tpl->save_parsed('items', array(
					'goDel' => $baseUrl . '?del={id}')
				);
			}
			$d['author_nick'] = "?";
			$d['author_url'] = "#";
			$d['content'] = htmlspecialchars($v['content']);
			$d['date'] = $text->ago($v['created'], true);
			if ($d['date'] === "") {
				$d['date'] = $locale->datef($v['created'], 'WdayDateTime');
			}
			if (isset ($users[$v['user_id']])) {
				$ui = $users[$v['user_id']];
				$d['author_nick'] = $ui['nick'];
				$d['author_url'] = $ui['uri'];
			}
			$m['items'] .= $tpl->parse('items', $d);
			$msgcnt++;
		}
		$res = $tpl->parse('ajax-content', $m);
		return $res;
	}


	function countItems() {
		$sql = 'SELECT count(*) FROM ' . $this->table('Shoutbox') . $this->_where();
		$m = $this->db->single_query($sql);
		return (count($m) ? $m[0] : 0);
	}


	function getList($limit = '')
	{
		return $this->db->array_query_assoc('
			SELECT id, user_id, created, content
			FROM ' . $this->table('Shoutbox') . $this->_where() . '
			ORDER BY created DESC' . $limit
		);
	}

	function _where()
	{
		if (isset ($this->tmpWhere)) {
			return $this->tmpWhere;
		}
		$w = array();
		$w[] = 'parent_id=' . intval($this->parentID);
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';
		return ($this->tmpWhere = $where);
	}

	function saveItem() {
		$form = & $this->form;
		$form->fill($_POST);
		$d = $form->get_values();

		//gautu duomenu apdorojimas
		$tpl = & $this->load_template();
		$info = $tpl->parse_array('info');
		if ($d['content'] == $info['default_content']) {
			$d['content'] = '';
		}

		//validacija
		$err = 0;
		$u = & moon :: user();
		$myID = $u->get_user_id();
		$l = strlen($d['content']);
		if (!$l) {
			$err = 1;
		}
		elseif ($l > $this->msgLimit) {
			$err = 2;
		}
		elseif (!$myID) {
			$err = 3;
		}
		if ($err) {
			$this->set_var('error', $err);
			return FALSE;
		}
		//jei refresh, nesivarginam
		if (true == ($wasRefresh = $form->was_refresh())) {
			return TRUE;
		}
		//save to database
		$ins = $form->get_values('content');
		$ins['parent_id'] = $this->parentID;
		$ins['created'] = time();
		$ins['user_id'] = $myID;
		$id = $this->db->insert_query($ins, $this->table('Shoutbox'), 'id');
		return $id;
	}


	function deleteItems($ids) {
		if (!is_array($ids)) {
			$ids = array(intval($ids));
		}
		foreach ($ids as $k => $v) {
			$ids[$k] = intval($v);
		}
		$where = ' WHERE id IN (' . implode(',', $ids) . ') AND parent_id=' . $this->parentID;
		$user = & moon :: user();
		if (!$this->iAdmin) {
			//jei ne adminas, tai gali trinti tik savo
			$where .= ' AND user_id = ' . $user->get_user_id();
		}
		$this->db->query('DELETE FROM ' . $this->table('Shoutbox') . $where);
	}

	function not_expired()
	{
		$evtData = $this->object('livereporting')->instEventModel('_src_shoutbox')->getEventData($this->parentID);
		if (NULL == $evtData) {
			return false;
		}
		
		return $evtData['state'] == 1;
	}

	function users($ids)
	{
		if (empty($ids)) {
			return array();
		}
		$ids = array_unique($ids);
		$res = array();
		$usersUrl = moon::shared('sitemap')->getLink('users');
		if(count($ids)) {
			$res = $this->db->array_query_assoc('
				SELECT id, nick
				FROM '.$this->table('Users').'
				WHERE id IN('.implode(',', $ids).')'
				, 'id');
			foreach ($res as $k=>$v) {
				$res[$k]['uri'] = $usersUrl . rawurlencode($v['nick']) . '/';
			}
		}
		return $res;
    }
}
