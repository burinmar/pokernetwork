<?php
class posts_edit extends moon_com {
	
	function onload()
	{
		$user = &moon::user();
		$this->userId = $user->get_user_id();
		$this->blog = $this->object('blog');
		
		$this->formItem = &$this->form('item');
		$this->formItem->names('id', 'title', 'uri', 'body', 'body_short', 'tags', 'is_hidden', 'created_on', 'disable_comments', 'disable_smiles');
		
		$this->sqlOrder = '';
		$this->sqlLimit = ''; // set by paging
	}
	
	function events($event, $par)
	{
		if (!$this->blog->isBlogOwner()) {
			//$this->redirect('posts#');
		}
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
					$this->redirect('#');
					
					$page = &moon::page();
					$user = &moon::user();
					$nick = $user->get('nick');
					if (isset($_POST['return'])) {
						$redirUri = '/'.$nick.'/'.ltrim($this->linkas('#edit', $id), '/');
						$page->redirect($redirUri);
					} else {
						$redirUri = '/'.$nick.'/'.ltrim($this->linkas('#'), '/');
						$page->redirect($redirUri);
					}
				} else {
					$this->set_var('view', 'form');
				}

				break;
			case 'delete':
				$page = &moon::page();
				if (isset($par[0]) && is_numeric($par[0])) {
					$this->deleteItem(array($par[0]));
					$this->redirect('posts#');
				} elseif (isset($_POST['it'])) {
					$this->deleteItem($_POST['it']);
					$page->back(1);
				}
				
				break;
			case 'ajax':
				$this->forget();
				if ($this->userId && !empty($_POST['ajax'])) {
					// maybe toggle hide
					if (isset($par[0]) && $par[0] == 'toggle-hide' && isset($par[1]) && is_numeric($par[1])) {
						$id = intval($par[1]);
						$this->db->query('
							UPDATE ' . $this->table('Posts') . '
							SET is_hidden = !is_hidden
							WHERE id = ' . $id
						);
					}
					// post preview
					if (isset($par[0]) && $par[0] == 'post-preview') {
						$tpl = $this->load_template();
						$loc = &moon::locale();
						$vars = array(
							'title' => isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '',
							'date' => isset($_POST['date']) ? $loc->datef(intval($_POST['date']), 'BlogPostList') : '',
							'body' => '',
							'tags' => isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : ''
						);
						if (is_object( $rtf = $this->object('rtf') )) {
							$rtf->setInstance( $this->get_var('rtf') );
							if (isset($_POST['disableSmiles']) && $_POST['disableSmiles']) {
								$rtf->parserFeatures['smiles'] = false;
							}
							$postId = isset($par[1]) ? intval($par[1]) : 0;
							list(,$vars['body']) = $rtf->parseText($postId, $_POST['body']);
						}
						print $tpl->parse('postPreview', $vars);
					}
				} else {
					$page = &moon::page();
					$page->page404();
				}
				moon_close();
				exit;
			default:
				//$this->setOrdering();
				//$this->setPaging();
				$userNick = moon::user()->get('nick');
				$url = $userNick ? $this->linkas('posts#') . 'user/' . $userNick . '/' : $this->linkas('posts#');
				moon::page()->redirect($url);
				break;
		}
		$this->use_page('1col');
	}
	
	function properties()
	{
		return array(
			'view' => 'list',
			'currPage' => '1',
			'listLimit' => '10',
			'error' => false
		);
	}
	
	function main($vars)
	{
		$output = '';
		if ($vars['view'] == 'form') {
			$output = $this->viewForm($vars);
		} else {
			$output = $this->viewList($vars);
		}
		return $output;
	}
	
	function viewList($vars)
	{
		$page = &moon::page();
		$currPage = $page->get_global($this->my('fullname') . '.currPage');
		if (!empty($currPage)) {
			$vars['currPage'] = $currPage;
		}
		
		$tpl = &$this->load_template();
		$loc = &moon::locale();
		
		$info = $tpl->parse_array('info');
		$page->title(htmlspecialchars($info['metaTitle']));
		
		$page->js('/js/modules/blogs_manage_posts.js');
		
		$ordering = $this->getOrdering();
		$paging = $this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit']);
		
		$goEdit = $this->linkas('#edit','{id}');
		$tpl->save_parsed('items',array('goEdit' => $goEdit));
		
		$goPost = $this->linkas('posts#','{uri}');
		$tpl->save_parsed('items',array('goPost' => $goPost));
		
		$items = $this->getItems();
		$itemsList = '';
		foreach ($items as $item) {
			$item['title'] = htmlspecialchars($item['title']);
			$item['date'] = $loc->datef($item['created_on'], 'BlogPostList');
			$item['time'] = date('H:i', $item['created_on']);
			$itemsList .= $tpl->parse('items', $item);
		}
		
		$main = array(
			'items' => $itemsList,
			'paging' => $paging,
			'goNew' => $this->linkas('#edit'),
			'goManage' => $this->linkas('#'),
			'goDelete' => $this->my('fullname') . '#delete'
		) + $ordering;
		
		return $tpl->parse('viewList', $main);
	}
	
	function viewForm($vars)
	{
		$tpl = &$this->load_template();
		$page = &moon::page();
		$info = $tpl->parse_array('info');
		
		$page->js('/js/modal.window.js');
		
		$info = $tpl->parse_array('info');
		$page->title(htmlspecialchars($info['metaTitle']));
		
		$page->js('/js/modules/blogs_manage_posts.js');
		
		$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;
		$form = $this->formItem;
		
		$m = array(
			'error' => ($err !== false) ? $info['error' . $err] : '',
			'event' => $this->my('fullname') . '#save',
			'id' => $form->get('id'),
			'goBack' => $this->linkas('#') . '?page=' . $vars['currPage'],
			'goNew' => $this->linkas('#edit'),
			'goManage' => $this->linkas('#'),
			'url.ajax' => $this->linkas('#ajax'),
			
			'refresh' => $page->refresh_field(),
			'toolbar' => '',
			'userTags' => ''
		) + $form->html_values();
		$m['is_hidden'] = $form->checked('is_hidden', 1);
		$m['disable_comments'] = $form->checked('disable_comments', 1);
		$m['disable_smiles'] = $form->checked('disable_smiles', 1);
		if (!$form->get('created_on')) {
			$m['created_on'] = time();
		}
		
		$tags = $this->getHtmlTags();
		$m['tags_autosuggest'] = count($tags) ? ('"' . implode('","', $tags) . '"') : '';
		$userTags = array();
		foreach ($tags as $t) {
			$userTags[] = $tpl->parse('userTags', array('tag' => $t));
		}
		$m['userTags'] = implode(', ', $userTags);
		
		// add toolbar
		if (is_object( $rtf = $this->object('rtf') )) {
			$rtf->setInstance( $this->get_var('rtf') );
			$m['toolbar'] = $rtf->toolbar('i_body',(int)$m['id']);
		}
		
		return $tpl->parse('viewForm', $m);
	}
	
	function getItems()
	{
		$sql = 'SELECT id, title, uri, is_hidden, created_on
			FROM ' . $this->table('Posts') . ' ' .
			$this->sqlWhere() . ' ' .
			$this->sqlOrder . ' ' .
			$this->sqlLimit;
		$result = $this->db->array_query_assoc($sql);
		return $result;
	}
	
	function getItemsCount()
	{
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->table('Posts') . ' ' .
			$this->sqlWhere();
		$result = $this->db->single_query_assoc($sql);
		return $result['cnt'];
	}
	
	function getItem($id)
	{
		$sql = 'SELECT p.*, b.body
			FROM ' . $this->table('Posts') . ' p
				LEFT JOIN ' . $this->table('Bodies') . ' b
				ON p.id = b.post_id
			WHERE 	id = ' . intval($id) . ' AND
				user_id = ' . $this->userId;
		return $this->db->single_query_assoc($sql);
	}

	function deleteItem($ids)
	{
		if (!is_array($ids) || !count($ids)) return;
		foreach ($ids as $k => $v) {
			$ids[$k] = intval($v);
		}
		$this->db->query('UPDATE ' . $this->table('Posts') . ' SET is_hidden = 2 WHERE user_id = ' . $this->userId . ' AND id IN (' . implode(',', $ids) . ')');
		
		// perform body cleanup
		// move images, attachments to tmp dir
		
		return TRUE;
	}
	
	function saveItem()
	{
		$postData = $_POST;
		$tags = explode(',', trim($_POST['tags'], ','));
		$tagsNew = array();
		foreach ($tags as $tag) {
			if ($tag == '') continue;
			$tag = ucfirst(trim($tag));
			$tagsNew[$tag] = $tag;
		}
		$postData['tags'] = implode(',', $tagsNew);
		
		$form = &$this->formItem;
		$form->fill($postData);
		$values = $form->get_values();
		
		// Filtering
		$data = array();
		$data = $values;
		$data['id'] = intval($values['id']);
		if ($data['body'] === '') {
			$data['body'] = NULL;
		}
		
		$data['is_hidden'] = (empty($values['is_hidden'])) ? 0 : 1;
		$data['disable_comments'] = (empty($values['disable_comments'])) ? 0 : 1;
		$data['disable_smiles'] = (empty($values['disable_smiles'])) ? 0 : 1;
		$id = $data['id'];
		
		// spam exceptions for some users
		$spamExceptions = array();
		$user = &moon::user();
		$nick = $user->get('nick');

		// Validation
		$errorMsg = 0;
		if ($data['title'] == '') {
			$errorMsg = 1;
		} elseif (!is_object($rtf = $this->object('rtf'))) {
			$errorMsg = 9;
		} elseif ($data['body'] && !in_array(strtolower($nick), $spamExceptions) && isSpam($data['body'])) {
			$errorMsg = 2;
			
			/*
			$mail = &moon :: mail();
			$mail->charset('utf-8');
			$mail->subject('blogs.pokernews - spam post identified');
			$mail->to($to = 'aleksandras.kotovas@ntsg.lt');
			$mail->from('info@blogs.pokernews.com', 'blogs.Pokernews.com');
			$mail->body('Post id: ' . $data['id'] . ', body: ' . $data['body']);
			$mail->send();
			*/
		}
		
		if ($errorMsg) {
			$this->set_var('error', $errorMsg);
			return FALSE;
		}
		
		// if was refresh skip other steps and return
		if ($form->was_refresh()) {
			return $id;
		}
		
		$ins = $form->get_values('title', 'body', 'tags', 'is_hidden', 'disable_comments', 'disable_smiles');
		$ins['updated_on'] = time();
		
		// make uri here if item is new
		if (!$id && $data['title'] != '') {
			$uri = make_uri($data['title']);
			$sql = 'SELECT count(*) 
				FROM ' . $this->table('Posts') . '
				WHERE	user_id = ' . $this->userId . ' AND
				     	uri LIKE "' . $this->db->escape($uri) . '%"';
			$is = $this->db->single_query($sql);
			if (isset($is[0]) && $is[0] > 0) {
				// uri already exists
				$sql = 'SELECT uri
					FROM ' . $this->table('Posts') . '
					WHERE	user_id = ' . $this->userId . ' AND
					     	uri LIKE "' . $this->db->escape($uri) . '%"';
				$res = $this->db->array_query_assoc($sql, 'uri');
				$i = 0;
				$found = false;
				$newUri = $uri;
				while($found === false) {
					$newUri = $uri.'-'.++$i;
					if (!array_key_exists($newUri, $res)) {
						$found = true;
						$uri = $newUri;
					}
				}
			}
			$ins['uri'] = $uri;
		}
		
		//iskarpa ir kompiliuojam i html
		$rtf->setInstance( $this->get_var('rtf') );
		$txt = &moon::shared('text');
		if ($data['disable_smiles']) {
			$rtf->parserFeatures['smiles'] = false;
			$txt->features['smiles'] = false;
		}
		list(,$bodyCompiled) = $rtf->parseText($id, $ins['body']);
		
		if ($bodyCompiled !== '') {
			if ($ins['body']) {
				$ins['body_short'] = $txt->message($txt->excerpt($txt->strip_tags($ins['body']), 250));
			} else {
				// images post
				list(,$ins['body_short']) = $rtf->parseText($id, $ins['body'], true);
			}
		} else {
			$ins['body_short'] = '';
		}
		
		// switch - original body will go to blog_posts_bodies table
		$bodyOrig = $ins['body'];
		$ins['body'] = $bodyCompiled;
		
		if ($id) {
			$this->db->update($ins, $this->table('Posts'), array('id' => $id));
			// update post body
			$is = $this->db->single_query('
				SELECT 1 FROM ' . $this->table('Bodies') . '
				WHERE post_id = ' . $id);
			if (isset($is[0])) {
				$this->db->update(array('body'=>$bodyOrig),$this->table('Bodies'),array('post_id' => $id));
			} else {
				$this->db->insert(array('post_id'=>$id,'body'=>$bodyOrig),$this->table('Bodies'));
			}
		} else {
			$ins['created_on'] = $ins['updated_on'];
			$ins['user_id'] = $this->userId;
			$id = $this->db->insert($ins, $this->table('Posts'), 'id');
			// insert post body
			if ($id && $bodyOrig !== '') {
				$this->db->insert(array('post_id'=>$id,'body'=>$bodyOrig), $this->table('Bodies'));
			}

		}
		
		if ($id) {
			$rtf->assignObjects($id);
		}
		
		$form->fill(array('id' => $id));
		return $id;
	}

	/**
	 * Sets sql where condition. Used in posts list
	 * @return string
	 */
	function sqlWhere()
	{
		if (!isset($this->tmpWhere)) {
			$w = array();
			
			$w[] = 'is_hidden < 2';
			$w[] = 'user_id = ' . $this->userId;
			
			$where = count($w) ? (' WHERE ' . implode(' AND ', $w)) : '';
			$this->tmpWhere = $where;
		}
		return $this->tmpWhere;
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
			array('id' => 0, 'title' => 1, 'is_hidden' => 1) ,
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
	
	function getHtmlTags()
	{
		$sql = 'SELECT tags
			FROM ' . $this->table('Posts') . '
			WHERE	user_id = ' . $this->userId . ' AND
				is_hidden = 0';
		$result = $this->db->array_query_assoc($sql);
		$tags = array();
		foreach ($result as $r) {
			if ($r['tags'] == '') continue;
			$t = explode(',', $r['tags']);
			foreach ($t as $tag) {
				$tag = str_replace('\\', '\\\\', $tag);
				$tag = str_replace('"', '\"', $tag);
				$tags[$tag] = 1;
			}
		}
		return array_keys($tags);
	}
	
}

?>