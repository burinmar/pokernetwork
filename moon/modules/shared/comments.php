<?php
class comments extends moon_com {
	
	function onload() {
		$this->form = & $this->form();
		$this->form->names('obj_id', 'id', 'comment', 'username', 'pass', 'disable_smiles', 'facebook', 'iCard');
		$this->form->fill(array('facebook'=>(moon::user()->get('facebook_id') ? 1:0)));
		$this->myTable = $this->table('Comments');
		$page = & moon :: page();
		$this->selfUrl = $page->uri_segments(0);
		$this->forum = $this->get_var('considerForum');
	}

	function events($event, $par) {
		$page = & moon :: page();
		switch ($event) {
			case "save" :
				if ($url = $this->saveComment()) {
					$page->redirect($url);
				}
				$page->redirect($this->selfUrl);
				break;

			case "delete" :
				if (isset ($_POST['it'])) {
					$this->deleteComments($_POST['it']);
				}
				$page->redirect($this->selfUrl);
				break;

			case "spam" :
				//ajax
				if (isset ($_POST['id'])) {
					$this->markSpam($_POST['id']);
				}
				header('Content-type:text/plain;charset=UTF-8');
				moon_close();
				die('spam ok' .$_POST['id']);

			default :
				$this->use_page('Common');
				$this->set_var('event', 'show_all');
				break;
		}
	}

	//***************************************
	//           --- EXTERNAL ---
	//***************************************
	function show($parentID, $iCard = NULL) {
		$vars = $this->my('vars');
		$page = & moon :: page();
		$page->js('/js/modules/comments.js');
		$u = & moon :: user();
		$t = & $this->load_template();
		$info = $t->parse_array('info');
		$myID = $u->get_user_id();
		//ar as adminas
		$iAdmin = $this->iAdmin($parentID);
		$hideList = FALSE;
		if (!$this->forum) {
			//dabar gal kas nors daroma su vienu komentaru
			if (!empty ($_GET['comm-edit'])) {
				$this->forget();
				$id = (int) $_GET['comm-edit'];
				if (count($d = $this->getItem($id))) {
					//$hideList = TRUE; // show comments list all the time
					$this->form->fill($d);
				}
			}
			elseif (!empty ($_GET['comm-quote'])) {
				$this->forget();
				$id = (int) $_GET['comm-quote'];
				if (count($d = $this->getItem($id))) {
					//$hideList = TRUE; // show comments list all the time
					/*$res=$this->db->single_query('SELECT nick FROM ' .$this->table('Users') . ' WHERE id = ' . intval($d['user_id']));
					$name = isset($res[0]) ? $res[0] : '';*/
					$res = $this->object('users.vb')->users($d['user_id']);
					$name = isset($res[$d['user_id']]) ? $res[$d['user_id']]['nick'] : '';
					$d['comment'] = $t->parse('quote', array('name'=>htmlspecialchars($name), 'text' => $d['comment']));
					unset($d['id']);
					$this->form->fill($d);
				}
			}
			elseif (!empty ($_GET['comm-spam'])) {
				$this->forget();
				$this->markSpam($_GET['comm-spam']);
			}
			elseif (!empty ($_GET['comm-del'])) {
				$this->forget();
				$id = (int) $_GET['comm-del'];
				$this->deleteComments($id);
				$page->redirect($this->selfUrl);
			}
		}
		//gal yra nepavykes issaugoti facebook postas?
		if ($fbPost = $page->get_global($this->my('fullname') . '_postFacebook') && $u->get('facebook_id')) {
			if ($fbPost['user_id'] === $myID && $fbPost['msg'] !== '') {
				$this->facebookPost($fbPost['msg'], $fbPost['iCard']);
				$page->set_global($this->my('fullname') . '_postFacebook', '');
			}
		}
		
		// content
		//******* FORM **********
		$f = $this->form;
		$err = 0;
		if ($error = $page->get_global($this->my('fullname') . '_error')) {
			$err = $error['error'];
			$f->fill($error);
			$page->set_global($this->my('fullname') . '_error', '');
		}
		$m = array(
			'error' => $err ? $info['error' . $err] : '',
			'event' => $this->my('fullname') . '#save',
			'refresh' => $page->refresh_field(),
			'id' => ($id = $f->get('id')),
			'obj_id' => $parentID,
			'toolbar' => ''
			) + $f->html_values();
		$m['disable_smiles'] = $f->checked('disable_smiles', 1);
		$m['facebook'] = $f->checked('facebook', 1);
		$m['!action'] = $this->selfUrl;
		$m['anonymous'] = $myID ? FALSE : TRUE;
		$m['url.facebook'] = $this->link('users.signup#facebook');
		$m['iCard'] = is_array($iCard) ? htmlspecialchars(serialize($iCard)) : '';
		//$m['fbID'] = $id ? 0 : 1;
		
		// add toolbar
		/*if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance( $this->get_var('rtfComment') );
			$m['toolbar'] = $rtf->toolbar('i_body',(int)$m['id']);
		}*/

		$forma = $t->parse('viewForm', $m);

		//******* LIST **********
		$text = & moon :: shared('text');
		$locale = & moon :: locale();
		//$page->set_local("show_meta", true);
		$m = array('items' => '', 'paging' => '');
		$m['items_count'] = $m['commentsCount'] = $count = $this->countComments($parentID);
		$m['!action'] = $this->selfUrl;
		$m['obj_id'] = $parentID;
		$m['event'] = $this->my('fullname') . "#delete";
		$m['form'] = $forma;
		$m['canDeleteMulti'] = false;//$iAdmin; - disabled select all
		if ($hideList) {
			$m['items'] = '&nbsp;';
			$m['canDeleteMulti'] = FALSE;
			$count = 0;
		}
		if ($count) {
			//puslapiavimui
			$currPage = empty ($_GET['comm']) ? 1 : intval($_GET['comm']);
			$pn = & moon :: shared('paginate');
			$pn->set_curent_all_limit($currPage, $count, 10);
			$pn->set_url($this->selfUrl . '?comm={pg}'/*, $this->selfUrl*/);
			$m['paging'] = $pn->show_nav();
			$psl = $pn->get_info();
			$m['comm_count'] = $count;
			if ($this->forum) {
				$dat = $this->getCommentsLatest($parentID);
				$m['onlyLatest'] = TRUE;
				$m['canDeleteMulti'] = FALSE;
				$forum = $this->forumInfo($parentID);
				$m['goShowAll'] = isset($forum['url']) ? $forum['url'] : $this->selfUrl . '?comm';
			}
			elseif ($count > 3 && !isset($_GET['comm'])) {
				$dat = $this->getCommentsLatest($parentID);
				$m['onlyLatest'] = TRUE;
				$m['canDeleteMulti'] = FALSE;
				$m['goShowAll'] = $this->selfUrl . '?comm';
			}
			else {
				if ($psl['countPages'] > 1) {
					$page->meta('robots','nofollow,noindex');
				}
				$dat = $this->getComments($parentID, $psl['sqllimit']);
				$m['onlyLatest'] = FALSE;
			}
			
			$userIds = array();
			foreach ($dat as $v) {
				$userIds[] = intval($v['user_id']);
			}
			$users = $this->getUsersData($userIds);
			$uLink = moon::shared('sitemap')->getLink('users');

			foreach ($dat as $v) {
				$d = array();
				$d['id'] = $v['id'];
				if ($this->forum) {
					$d['comment'] = $v['comment'];
				}
				else {
					//$text->features['smiles'] = !$v['disable_smiles'];
					$d['comment'] = $text->message($v['comment']);
					$d['goEdit'] = $d['goDelete'] = '';
					//$d['goQuote'] = htmlspecialchars($this->selfUrl . '?comm-quote=' . $v['id'] . (!$m['onlyLatest']?'&comm':''));
					if (!$this->forum) {
						//spam report
						//$d['goSpam'] = htmlspecialchars($this->selfUrl . '?comm-spam=' . $v['id'] . (!$m['onlyLatest']?'&comm':''));
						//javascript
						//$d['goSpam'] = 1;
					}
					if ($myID == $v['user_id'] || $iAdmin) {
						$d['goEdit'] = htmlspecialchars($this->selfUrl . '?comm-edit=' . $v['id'] . (!$m['onlyLatest']?'&comm':''));
						$d['goDelete'] = htmlspecialchars($this->selfUrl . '?comm-del=' . $v['id']);
					}
				}
				$d['date'] = $text->ago($v['created'], true);
				if ($d['date'] === "") {
					$d['date'] = $locale->datef($v['created'], 'DateTime');
				}
				$d['authorURL'] = '';
				$d['author'] = '?';
				$d['authorAvatar'] = '/img/avatar50.png';
				if (isset ($users[$v['user_id']])) {
					$ui = $users[$v['user_id']];
					$d['author'] = htmlspecialchars($ui['nick']);
					$d['authorURL'] = '/forums/member.php?u=' . $v['user_id'];
					/*if ($uLink) {
						$d['authorURL'] = $uLink . htmlspecialchars($ui['nick']).'/';
					}*/
					if ($ui['avatar']) {
						$d['authorAvatar'] = img('avatar', $v['user_id'] . '-' . $ui['avatar']);
					}
				}
				$d['canDeleteMulti'] = $m['canDeleteMulti'];
				$m['items'] .= $t->parse('items', $d);
			}
		}
		$res = $t->parse('viewComments', $m);
		return $res;
	}


	//***************************************
	//           --- DB AND OTHER ---
	//***************************************

	function forumInfo($parentID) {
		static $forum;
		if (!isset($forum)) {
			$forum = $this->object('board.board')->instArticles()->getCommentsThread($parentID);
		}
		return $forum;
	}




	//*************
	function countComments($parentID) {
		if ($this->forum) {
			$board = $this->forumInfo($parentID);
			return (isset($board['posts_count']) ? $board['posts_count'] : 0);
		}
		$sql = 'SELECT count(*) FROM ' . $this->myTable . $this->_where($parentID);
		$m = $this->db->single_query($sql);
		return (count($m) ? $m[0] : 0);
	}

	function getComments($parentID, $limit = '') {
		return $this->db->array_query_assoc('
			SELECT * FROM ' . $this->myTable . $this->_where($parentID) . '
			ORDER BY created, id' . $limit
			);
	}

	function getCommentsLatest($parentID) {
		if ($this->forum) {
			$board = $this->object('board.board');
			$a = $board->instArticles()->latestComments($parentID, FALSE);
			$b = array();
			foreach ($a as $v) {
				$board->revizePostFormatSrcNews($v);
				$b[] = array('comment'=>$v['contents'] , 'user_id'=>$v['user_id'], 'id'=>0, 'created'=>$v['created_on']);
			}
			return $b;
		}

		return $this->db->array_query_assoc('
			SELECT * FROM ' . $this->myTable . $this->_where($parentID) . '
			ORDER BY created DESC, id DESC LIMIT 3'
			);
	}

	function _where($parentID) {
		if (isset ($this->tmpWhere)) {
			return $this->tmpWhere;
		}
		$w = array();
		$w[] = 'obj_id=' . sprintf("%.0f", $parentID);
		$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';
		return ($this->tmpWhere = $where);
	}
	
	function getUsersData($userIds = array())
	{
		return $this->object('users.vb')->users($userIds);
		/*$userIds = array_unique($userIds);
		if (!is_array($userIds) || empty($userIds)) return array();
		return $this->db->array_query_assoc('
			SELECT id, nick, avatar
			FROM ' . $this->table('Users') . '
			WHERE id IN (' . implode(',', $userIds) . ')
		', 'id');*/
	}
	
	function saveComment() {
		$form = & $this->form;
		if (!isset($_POST['facebook'])) {
			$_POST['facebook'] = 0;
		}
		$form->fill($_POST);
		$d = $form->get_values();
		$id = intval($d['id']);
		
		//gautu duomenu apdorojimas
		/*
		if ($d['comment']==='') {
		$d['comment']=null;
		}
		$form->fill($d,false); //jei bus klaida
		*/
		//validacija
		$err = 0;
		
		$user = & moon :: user();
		$myID = $user->get_user_id();
		
		if (!$myID && isset($d['username']) && isset($d['pass'])) {
			if ($d['username'] == '' || $d['pass'] == '') {
				$error = 2;
			}
			else {
				if (is_object($loginObj = $this->object('sys.login_object'))) {
					$loginObj->login($d['username'], $d['pass'], $error);
					$myID = $user->get_user_id();
				}
			}
		}
		
		if ($d['comment'] === '') {
			$err = 1;
		}
		elseif (!$myID) {
			$err = 2;
		}
		else {
			if ($id) {
				$d = $this->getItem($id);
				$iAdmin = $this->iAdmin($d['obj_id']);
				if (empty ($d['user_id']) || ($d['user_id'] != $myID && !$iAdmin)) {
					$err = 2;
				}
			}
			if (!$err && $this->isCommentSpam($d['comment'])) {
				$err = 3;
			}
		}
		if ($err) {
			//$this->set_var('error', $err);
			$d['error'] = $err;
			$p = & moon :: page();
			$p->set_global($this->my('fullname') . '_error', $d);
			return false;
		}
		//jei refresh, nesivarginam
		if ($wasRefresh = $form->was_refresh()) {
			return $id;
		}
		// SAVE
		$ins = $form->get_values('obj_id', 'comment'/*, 'disable_smiles'*/);
		if ($this->forum) {
			// save to forum
			$arg = array('pId'=>$ins['obj_id'], 'comment'=>$ins['comment']);
			$_POST = $arg;
			$url = $this->object('board.board')->instArticles()->eSaveComment($arg);
		}
		else {
			//save to database
			$ins['user_ip'] = ip2long($user->get_ip());
			if ($id) {
				unset($ins['object_id']);
				$this->db->update($ins, $this->myTable, array('id' => $id));
				//kad i facebook nepostintu (zemiau)
				return;
			}
			else {
				$ins['created'] = time();
				$ins['user_id'] = $user->get_user_id();
				$id = $this->db->insert($ins, $this->myTable, 'id');
			}
			if ($id) {
				$this->recountComments($ins['obj_id']);
			}
			$url = $this->selfUrl;
		}
		// gal post to facebook
		if ($url && $form->get('facebook')) {
			if ($iCard = $form->get('iCard')) {
				$iCard = unserialize($iCard);
			}
			if (!$this->facebookPost($ins['comment'], $iCard)) {
				// facebook neprieme, redirektinam isiloginti
				moon::page()->set_global($this->my('fullname') . '_postFacebook', array('user_id'=>$ins['user_id'], 'msg'=>$ins['comment'], 'iCard'=>$iCard));
				$fb = & moon :: shared('facebook');
				$fb->gotoLogin();
				//cia jau nebeateinam, nes ivyko redirektas
			}
		}
		return $url;

	}

	function getItem($id) {
		return $this->db->single_query_assoc('
			SELECT * FROM ' . $this->myTable . '
			WHERE id = ' . intval($id));
	}

	function deleteComments($ids) {
		if (!is_array($ids)) {
			$ids = array(intval($ids));
		}
		foreach ($ids as $k => $v) {
			$ids[$k] = intval($v);
		}
		$where = ' WHERE id IN (' . implode(',', $ids) . ')';
		$user = & moon :: user();
		//gaunam, kokie parent objektai
		$parentID = $this->db->array_query('
			SELECT DISTINCT obj_id, obj_id
			FROM ' . $this->myTable . $where, TRUE);
		//dar patikrinam parent teises
		foreach ($parentID as $pId) {
			if (!$this->iAdmin($pId)) {
				//jei ne adminas, tai gali trinti tik savo
				$where .= ' AND user_id = ' . $user->get_user_id();
				break;
			}
		}
		$this->db->query('DELETE FROM ' . $this->myTable . $where);
		$this->recountComments($parentID);
	}

	function markSpam($id) {
		//apsauga nuo kvailu robotu
		if (moon::page()->history_step()) {
			$this->db->query('UPDATE ' . $this->myTable . ' SET spam=spam+1 WHERE id=' . intval($id));
		}
	}

	//___________________________________________________________________________
	// statistiniu duomenu perskaiciavimas
	function recountComments($ids) {
		if (!is_array($ids)) {
			$ids = array(sprintf("%.0f",$ids));
		}
		foreach ($ids as $k => $v) {
			$ids[$k] = sprintf("%.0f", $v);
		}
		$ids = array_unique($ids);
		$stats = $this->db->array_query_assoc('
			SELECT obj_id, COUNT(*) AS cnt, MAX(created) AS max
			FROM ' . $this->myTable . "
			WHERE obj_id IN (" . implode(', ', $ids) . ")
			GROUP BY obj_id ORDER BY NULL", 'obj_id');
		$ins = array();
		foreach ($ids as $id) {
			$ins['comm_count'] = isset($stats[$id]) ? $stats[$id]['cnt'] : 0;
			$ins['comm_last'] = isset($stats[$id]) ? $stats[$id]['max'] : 0;
			$this->db->update($ins, $this->table("CommentsParent"), $id);
		}
	}

	function iAdmin($parentID) {
		if (moon::user()->i_admin()) {
			return TRUE;
		}
		//blogu komentarams
		if ($parentID && 'blogcomments' == $this->my('name') && ($myID = moon::user()->id())) {
			$a =$this->db->single_query('SELECT user_id FROM ' .$this->table("CommentsParent") . ' WHERE id='. sprintf("%.0f", $parentID));
			if (!empty($a[0]) && $myID == $a[0]) {
				return TRUE;
			}
		}
		return FALSE;
	}

	function isCommentSpam($text = '')
	{
		$u = &moon::user();
		if ($text == '' || !($userId = $u->get_user_id()) || $this->forum) {
			return false;
		}
		$isSpam = false;
		$newUser = $u->get('suspicious');
		if ($newUser && isSpam($text)) {
			$isSpam = true;
		}
		else {
			$r = $this->db->single_query('SELECT COUNT(*) FROM ' . $this->table('Comments') . ' WHERE user_id = ' . $userId . ' AND created > ' . (time() - 3600));
			$commLastH = (1 === count($r)) ? $r[0] : 0;
			if ($newUser) {
				if ($commLastH >= 3) $isSpam = true;
			} else {
				if ($commLastH >= 20)  $isSpam = true;
				elseif ($commLastH >= 3 && isSpam($text)) $isSpam = true;
			}
		}
		return $isSpam;
	}

	function facebookPost($msg, $iCard = NULL) {
		if (!($to = moon::user()->get('facebook_id'))) {
			return FALSE;
		}
		if (is_array($iCard)) {
			$take = array('title'=>'name', 'description'=>'description', 'url'=>'link', 'img'=>'picture');
			$a = array();
			foreach ($take as $k=>$v) {
				if (!empty($iCard[$k])) {
					$a[$v] = $iCard[$k];
				}
			}
			$iCard = $a;
		}
		$fb = & moon :: shared('facebook');
		$id = $fb->post($to, $msg, $iCard);
		return $id;
	}


	//***************************************
	//		  --- OTHER ---
	//***************************************
	function articleForumNotify($id, $type = NULL) {
		$board = &$this->object('board.board');
		$type = $type ? $type : 1; // not very good, but seems ok
		if (!$board->instArticles()->queryIntegrateBoard(array('article_type' => $type))) {
			return;
		}
		$board->instArticles()->notifyUpdatedArticle($id);
	}


}

?>