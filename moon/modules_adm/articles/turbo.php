<?php
class turbo extends moon_com {

function onload()
{
	$this->filter = array();
	$this->formFilter = &$this->form();
	$this->formFilter->names('title');

	$this->formItem = &$this->form('item');
	$this->formItem->names('id', 'category_id', 'title', 'uri', 'meta_keywords', 'meta_description', 'summary', 'content', 'content_html', 'authors', 'img', 'img_alt', 'tags', 'published', 'is_hidden', 'is_promo', 'room_id', 'double_banner', 'turbo_sent_cnt', 'turbo_ic_message_id', 'turbo_ic_spam_score', 'turbo_ic_spam_info', 'content_type', 'geo_target', 'homepage_promo', 'short_title',  'facebook_summary', 'twitter_text');

	$this->formItemTurbo = &$this->form('turbo-item');
	$this->formItemTurbo->names('id', 'parent_id', 'title', 'content', 'content_html');

	$this->sqlWhere = ''; // set by filter
	$this->sqlOrder = '';
	$this->sqlLimit = ''; // set by paging

	$this->articlesSuffixStartId = $this->get_var('articlesSuffixStartId');
	$this->addToArticlesSuffix = 1000;
}
function events($event, $par)
{
	$page = &moon::page();
	switch ($event) {
		case 'ajax-edit-form':
			$tpl = $this->load_template();
			$parentId = isset($par[0]) ? intval($par[0]) : 0;
			if ($parentId) {
				$turboId = isset($par[1]) ? intval($par[1]) : 0;
				if ($turboId) {
					if (count($values = $this->getItemTurbo($turboId))) {
						$this->formItemTurbo->fill($values);

						$form = $this->formItemTurbo;

						$formTurbo = array(
							'action' => $this->linkas('#save-turbo'),
							'event' => $this->my('fullname') . '#save-turbo',
							'id' => $form->html_values('id'),
							'goBackTurbo' => $this->linkas('#show',$parentId),
							'title' => $form->html_values('title'),
							'content' => $form->html_values('content'),
							'parent_id' => $parentId,
							'refresh' => $page->refresh_field(),
							'formTitle' => 'Edit Story',
							'hideForm' => FALSE,
							'toolbar' => ''
						);
						// add toolbar
						if (is_object( $rtf = $this->object('rtf') )) {
							$rtf->setInstance( $this->get_var('rtf') . '~' . $turboId);
							$formTurbo['toolbar'] = $rtf->toolbar('i_content_' . $turboId, $turboId);
						}

						print $tpl->parse('formTurbo', $formTurbo);
						exit;
					}
				}
			}
			$page->page404();
			exit;
			break;
		case 'filter':
			$this->setFilter();
			break;
		case 'show':
			$id = isset($par[0]) ? intval($par[0]) : 0;
			if ($id) {
				if (count($values = $this->getItem($id))) {
					$this->set_var('articleData', $values);
				}
				else {
					$this->set_var('error', '404');
				}
			}
			$this->set_var('view', 'showArticle');
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
				if (isset($_POST['return']) AND !$page->get_local('showSaved')) {
					$this->redirect('#edit', $id);
				} elseif(!$page->get_local('showSaved')) {
					$this->redirect('#');
				}

				$page->set_global('enableSendButton', 1);
				$this->redirect('#show-saved', $id);
			} else {
				$this->set_var('view', 'form');
			}
			break;
		case 'save-turbo':
			if ($id = $this->saveItemTurbo()) {

				if (!$page->get_local('showSaved')) {
					$page->alert('Turbo story saved', 'ok');
					$this->redirect('#show', $id);
				}
				// else - show article contents with 'send newsletter" button
				$page->set_global('enableSendButton', 1);
				$this->redirect('#show-saved', $id);

			} elseif($id === FALSE) {
				// error
				$page->alert('Error: unable to save turbo story');

				$idArticle = $page->get_local('idArticle');

				$this->redirect('#show', $idArticle);
			} else {

			}
			break;
		case 'show-saved':
				$id = isset($par[0]) ? intval($par[0]) : 0;
				if ($id) {
					if (count($values = $this->getItem($id))) {
						$this->set_var('articleData', $values);
					}
					else {
						$this->set_var('error', '404');
					}
				}
				$this->set_var('view', 'showSaved');
			break;
		case 'send-newsletter':
			if ($id = $this->sendNewsletter()) {
				sleep(2);
				$this->redirect('#show', $id);
			} else {
				$this->redirect('#');
			}
			break;
		case 'delete':
			if (isset($_POST['it'])) $this->deleteItem($_POST['it']);
			$this->redirect('#');
			break;
		case 'delete-turbo':
			$id = isset($par[1]) ? intval($par[1]) : 0;
			$articleId = isset($par[0]) ? intval($par[0]) : 0;
			if ($id) {
				$this->deleteItemTurbo($id);
			}
			if ($articleId) {
				$this->redirect('#show', $articleId);
			} else {
				$this->redirect('#');
			}

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
					'src' => $this->object('articles')->getImageSrc($m['img'],'orig/'),
					'minWH' => '460x305',
					'fixedProportions' => TRUE
					));
			}
			//forget reikia kai nuimti filtra
			$this->forget();
			break;
		case 'authors':
			//forget reikia kai nuimti filtra
			$this->forget();
			$need = isset($_GET['need']) ? $_GET['need'] : '';
			$this->ajaxGetAuthors($need);
			moon_close();
			exit;
			break;
		case 'ajax-tags':
			$page = &moon::page();
			if (isset($_POST['text']) AND $_POST['text'] != '') {
				$text = $_POST['text'];
				$tags = $this->getRecommendedTags($text);
				if (!empty($tags)) {
					$outputStr = '<strong>Recommended tags:</strong>&nbsp;';
					$output = array();
					foreach ($tags as $t) {
						$output[] = '<a href="" onclick="addTag(\'' . $t . '\');return false;" style="line-height: 20px">' . $t . '</a>';
					}
					print $outputStr . implode(',&nbsp;', $output);
				} else {
					$page->page404();
				}
			} else {
				$page->page404();
			}
			moon_close();
			exit;
		default:
			$this->setOrdering();
			$this->setPaging();
			break;
	}
	$this->use_page('Common');
}
function properties()
{
	return array(
		'view' => 'list',
		'currPage' => '1',
		'listLimit' => '50',
		'error' => FALSE,
		'articleData' => array()
	);
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
	} elseif($vars['view'] == 'showArticle') {
		return $this->renderArticle($vars);
	} elseif($vars['view'] == 'showSaved') {
		return $this->renderArticleSaved($vars);
	} else {
		return $this->renderList($vars);
	}
}
function renderList($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$loc = moon::locale();

	$ordering = $this->getOrdering();
	$filter = $this->getFilter();
	$paging = $this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit']);

	$goEdit = $this->linkas('#show','{id}');
	$tpl->save_parsed('items',array('goEdit' => $goEdit));

	$items = $this->getItems();
	$itemsList = '';
	$now = round(time(), -2);
	foreach ($items as $item) {
		$item['title'] = htmlspecialchars($item['title']);
		$item['date'] = $loc->datef($item['published'], 'Date');
		$item['time'] = date('H:i', $item['published']);
		$item['categoryTitle'] = ($item['categoryTitle'] != '') ? htmlspecialchars($item['categoryTitle']) : 'None';
		$item['status'] = ($item['is_hidden'] == 1) ? '<span class="hiddenIco" title="Hidden">Hidden</span>' : '';
		$item['classHidden'] = ($item['is_hidden'] == 1) ? 'class="item-hidden"' : '';
		$item['classHiddenTd'] = ($item['published'] >= $now) ? 'class="item-hidden"' : '';
		$itemsList .= $tpl->parse('items', $item);
	}

	$m = array(
		'viewList' => TRUE,
		'filter' => $tpl->parse('filter', $filter),
		'items' => $itemsList,
		'paging' => $paging,
		'pageTitle' => $win->current_info('title'),
		'goNew' => $this->linkas('#edit'),
		'goDelete' => $this->my('fullname') . '#delete'
	) + $ordering;

	return $tpl->parse('main', $m);
}
function renderForm($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();
	$user = &moon::user();
	$sitemap = moon::shared('sitemap');
	$info = $tpl->parse_array('info');
	$page->js('/js/modules_adm/articles.articles.js');

	$err = ($vars['error'] !== FALSE) ? $vars['error'] : FALSE;

	$form = $this->formItem;
	$title = $form->get('id') ? $info['titleEdit'] . ' :: ' . $form->get('title') : $info['titleNew'];

	$showViewArticleLink = FALSE;
	$m = array(
		'isDeveloper' => $user->i_admin('developer'),
		'viewList' => FALSE,
		'error' => ($err !== FALSE) ? $info['error' . $err] : '',
		'event' => $this->my('fullname') . '#save',
		'id' => $form->get('id'),
		'goBackList' => $this->linkas('#') . '?page=' . $vars['currPage'],
		'goBackArticle' => $this->linkas('#show', $form->get('id')),
		'pageTitle' => $win->current_info('title'),
		'formTitle' => htmlspecialchars($title),
		'uriPrefix' => $this->getMyUriPrefix(),
		'refresh' => $page->refresh_field(),
		'toolbar' => '',
		'geo_zones' => '',
		'uriNumericSuffix' => '',

		'showPromo' => true,
		'showHomepageSlider' => false,
		'showViewArticleLink' => $form->get('id'),
		'showTwitter' => false,//$this->my('name') == 'news',
		'showFacebook' => false,//$this->my('name') == 'news',
		'showCheckLinks' => false,//$form->get('id');
		'showCategories' => true,
		'showGeoTarget' => false
	) + $form->html_values();
	$m['is_hidden'] = $form->checked('is_hidden', 1);
	$m['is_promo'] = $form->checked('is_promo', 1);
	$m['double_banner'] = $form->checked('double_banner', 1);
	$m['homepage_promo'] = $form->checked('homepage_promo', 1);

	// uri suffix
	if ($form->get('id') == '') {
		$m['uriNumericSuffix'] = '-????';
	} elseif ($form->get('id')  >= $this->articlesSuffixStartId) {
		$m['uriNumericSuffix'] = '-' . ($this->addToArticlesSuffix + $form->get('id'));
	}

	// leading image
	if ($m['img']) {
		$m['imgSrc'] = $this->getImageSrc($m['img']);
		$m['imgSrcThumb'] = $this->getImageSrc($m['img'], 'thumb_');
		$m['imgTool'] = $this->linkas('#imgtool',$m['id']);
		$m['selfUrl'] = $this->linkas('#edit',$m['id']);
	}

	// publishing date
	if ($form->get('published') != '') {
		$m['publishedDate'] = date('Y-m-d', $form->get('published'));
		$m['publishedTime'] = date('H:i', $form->get('published'));
	} else {
		$m['publishedDate'] = date('Y-m-d');
		$m['publishedTime'] = date('H:i');
	}

	// authors
	if (!$err) {
		if (is_object($oAuthors = $this->object('authors_shared'))) {
			$m['authors'] = $oAuthors->getAuthorsString($form->get('authors'));
		} else {
			$page->alert('Technical error! Problems with authors component!');
		}
	}

	// autocomplete
	$authors = $this->getHtmlAuthors();
	$m['authors_suggest'] = count($authors) ? ('"' . implode('", "', $authors) . '"') : '';
	$tags = $this->getHtmlTags();
	$m['tags_suggest'] = count($tags) ? ('"' . implode('","', $tags) . '"') : '';
	$page->js('/js/jquery/autocomplete_1.0.2.js');
	$page->css('/css/jquery/autocomplete_1.0.2.css');

	// categories
	$dummy = array();
	$categoriesItems = $this->getCategories($form->get('category_id'));
	$tree = $this->getTree($categoriesItems, 0, $dummy);
	$m['optCategories'] = !empty($tree) ? $form->options('category_id', $tree) : '';

	$optRooms = $this->getRoomsForBanner();
	$m['optRooms'] = $form->options('room_id', $optRooms);

	/*
	$optContentType = $this->getContentTypes($form->get('content_type'));
	$m['optContentType'] = $form->options('content_type', $optContentType);
	*/

	/*
	//geo target
	$m['showGeoTarget'] = true;
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
	*/

	// add toolbar
	if (is_object( $rtf = $this->object('rtf') )) {
		$rtf->setInstance( $this->get_var('rtf') );
		$m['toolbar'] = $rtf->toolbar('i_content',(int)$m['id']);
	}

	return $tpl->parse('main', $m);
}
function renderArticle($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();
	$loc = &moon::locale();
	$sitemap = &moon::shared('sitemap');

	$page->js('/js/jquery/livequery.js');
	$page->js('/js/modules_adm/articles.turbo.js');

	$main = $vars['articleData'];

	if (empty($main)) return '';

	$main['goBack'] = $this->linkas('#') . '?page=' . $vars['currPage'];
	$main['goEditArticle'] = $this->linkas('#edit',$main['id']);
	$main['goShowSaved'] = $this->linkas('#show-saved',$main['id']);
	$main['pageTitle'] = $win->current_info('title');

	$main['uriPrefix'] = $sitemap->getLink('news');

	if ($main['id'] == '') {
		$main['uriNumericSuffix'] = '-????';
	} elseif ($main['id']  >= $this->articlesSuffixStartId) {
		$main['uriNumericSuffix'] = '-' . ($this->addToArticlesSuffix + $main['id']);
	} else {
		$main['uriNumericSuffix'] = '';
	}

	$main['shortDate'] = $loc->datef($main['published'], 'News');

	if (!empty($main['authors'])) {
		$authors = $this->getAuthors($main['authors']);
		if (count($authors)) {
			$d = array();
			foreach ($authors as $a) {
				$a['name'] = htmlspecialchars($a['name']);
				$d[] = $a['name'];
			}
			$main['authors'] = implode(', ', $d);
		}
	}

	// turbo items
	$items = $this->getTurboItems($main['id']);
	$itemsList = '';
	foreach ($items as $item) {
		$item['saveAction'] = ($item['created'] < $item['updated']) ? 'Updated' : 'Added';
		$item['shortDate'] = ' on ' . date('H:s:i', $item['updated']) . ' ' . $loc->datef($item['updated'], 'News');
		$item['goEditTurbo'] = $this->linkas('#edit-turbo',$main['id'] . '.' . $item['id']);
		$item['goDeleteTurbo'] = $this->linkas('#delete-turbo',$main['id'] . '.' . $item['id']);
		$itemsList .= $tpl->parse('turbo_item', $item);
	}
	$main['turbo_items'] = $itemsList;

	// new turbo form
	$form = $this->formItemTurbo;

	// leading image
	$main['imgAllowed'] = $this->get_var('articleHasImgTurbo');
	if ($main['img']) {
		$main['imgSrc'] = $this->object('articles')->getImageSrc($main['img']);
	}

	$formTurbo = array();
	$formTurbo['event'] = $this->my('fullname') . '#save-turbo';
	$formTurbo['action'] = $this->linkas('#save-turbo');
	$formTurbo['id'] = $form->html_values('id');
	$formTurbo['goBackTurbo'] = $this->linkas('#show',$main['id']);
	$formTurbo['title'] = $form->html_values('title');
	$formTurbo['content'] = $form->html_values('content');
	$formTurbo['parent_id'] = $main['id'];
	$formTurbo['refresh'] = $page->refresh_field();
	$formTurbo['formTitle'] = 'New Story';
	$formTurbo['hideForm'] = TRUE;

	// add toolbar
	$formTurbo['toolbar'] = '';
	if (is_object( $rtf = $this->object('rtf') )) {
		$rtf->setInstance( $this->get_var('rtf') . '~0');
		$formTurbo['toolbar'] = $rtf->toolbar('i_content_',(int)$formTurbo['id']);
	}

	$main['formTurbo'] = $tpl->parse('formTurbo', $formTurbo);
	$main['enableSendButton'] = true;

	// spam block
	if (!empty($main['turbo_ic_spam_info'])) {
		if (($sc=floatval($main['turbo_ic_spam_score']))>0.0 || $main['turbo_ic_spam_score']) {
			if ($sc>=5.0) {
				$main['spam_color'] = ' style="padding-left:5px;padding-right:5px;background-color:red;color:white;font-weight:bold"';
			}
			elseif ($sc>=3.0) {
				$main['spam_color'] = ' style="padding-left:5px;padding-right:5px;background-color:yellow"';
			}
			else {
				$main['spam_color'] = '';
			}
			$main['turbo_ic_spam_info'] = nl2br(htmlspecialchars($main['turbo_ic_spam_info']));
		}
	}

	return $tpl->parse('show_article', $main);
}
function renderArticleSaved($vars)
{
	$tpl = &$this->load_template();
	$win = &moon::shared('admin');
	$page = &moon::page();
	$loc = &moon::locale();

	$main = $vars['articleData'];

	$main['goBack'] = $this->linkas('#') . '?page=' . $vars['currPage'];
	$main['goEditArticle'] = $this->linkas('#edit',$main['id']);
	$main['goShowArticle'] = $this->linkas('#show',$main['id']);
	$main['pageTitle'] = $win->current_info('title');

/*
	$year = ($main['published'] != '') ? date('Y', $main['published']) : date('Y');
	$month = ($main['published'] != '') ? date('m', $main['published']) : date('m');
	$main['uriPrefix'] = $this->get_var('articleUriPrefix') . $year . '/' . $month . '/';

	// leading image
	$main['imgAllowed'] = $this->get_var('articleHasImgTurbo');
	if ($main['img']) {
		$main['imgSrc'] = $this->object('articles')->getImageSrc($main['img']);
	}

	$main['shortDate'] = $loc->datef($main['published'], 'News');

	if (!empty($main['authors'])) {
		$authors = $this->getAuthors($main['authors']);
		if (count($authors)) {
			$d = array();
			foreach ($authors as $a) {
				$a['name'] = htmlspecialchars($a['name']);
				$d[] = $a['name'];
			}
			$main['authors'] = implode(', ', $d);
		}
	}

	// turbo items
	$items = $this->getTurboItems($main['id']);
	$itemsList = '';
	foreach ($items as $item) {
		$itemsList .= $tpl->parse('turbo_item_saved', $item);
	}
	$main['turbo_items_saved'] = $itemsList;
*/

	$sql = 'SELECT turbo_sent_cnt
		FROM ' . $this->table('Articles') . '
		WHERE 	id = ' . intval($main['id']);
	$res = $this->db->single_query_assoc($sql);

	$main['enableSendButton'] = (isset($res['turbo_sent_cnt']) && $res['turbo_sent_cnt'] == 0);//$page->get_global('enableSendButton');
	$main['event'] = $this->my('fullname') . '#send-newsletter';
	$main['refresh'] = $page->refresh_field();

	$articleTurbo = $this->getArticleTurboData($main['id']);

	$html = $articleTurbo['html'];
	$url = $articleTurbo['url'];
	$title = $articleTurbo['title'];
	$imgSrc = $articleTurbo['imgSrc'];
	$imgAlt = $articleTurbo['imgAlt'];

	$html = preg_replace('/\<object.*object\>/', '<a href="' . $url . '" style="color: #E6E6E6;">Visit PokerNews to watch the video</a>', $html);

	$tplHtml = array(
		'date' => $loc->datef(time(), 'Article'),
		'title' => $title,
		'url' => $url,
		'contents' => $html,
		'homeUrl' => $page->home_url,
		'imgSrc' => $imgSrc,
		'imgAlt' => $imgAlt
	);

	$main['newsletter_html'] = $tpl->parse('newsletter_html', $tplHtml);
	return $tpl->parse('show_saved', $main);
}
function getArticleTurboData($id, $turboOnly = FALSE)
{
	$tpl = $this->load_template();
	$loc = &moon::locale();

	$contentHtml = '';
	$title = '';
	$url = '';
	$text = '';
	$html = '';
	$imgSrc = '';
	$imgAlt = '';
	$iContactInfo = array();
	if (!$turboOnly) {
		$sql = 'SELECT title, content, content_html, img, img_alt, turbo_ic_message_id, turbo_ic_message_id, turbo_ic_spam_score, turbo_ic_spam_info
			FROM ' . $this->table('Articles') . '
			WHERE	id = ' . intval($id) . ' AND
			     	is_hidden = 0';
		$result = $this->db->single_query_assoc($sql);

		if (!empty($result['content_html'])) {
			$contentHtml .= str_replace('<p', '<p style="color: rgb(255, 255, 255); margin: 0pt; padding: 10px 0pt;" ', $result['content_html']);
			$contentHtml = str_replace('<a', '<a style="color: #0098d9;" ', $contentHtml);
		}
		if (!empty($result['content'])) {
			$text .= $result['content'];
		}
		if (!empty($result['title'])) {
			$title = $result['title'];
		}
		if (!empty($result['img'])) {
			$imgSrc =  $this->object('articles')->getImageSrc($result['img']);
			if (strpos($imgSrc,'http') !==0) {
				$page = & moon :: page();
				$imgSrc =  $page->home_url() . ltrim($imgSrc, '/');
			}
		}
		if (!empty($result['img_alt'])) {
			$imgAlt = $result['img_alt'];
		}

		$iContactInfo['message_id'] = $result['turbo_ic_message_id'];
		$iContactInfo['pam_score'] = $result['turbo_ic_spam_score'];
		$iContactInfo['spam_info'] = $result['turbo_ic_spam_info'];
	}

	$url = $this->getArticleUrl($id);

	// start html
	$m = array(
		'leading_story' => 1,
		'url' => $url,
		'imgSrc' => $imgSrc,
		'imgAlt' => $imgAlt,
		'title' => $title,
		'authors' => '',
		'contentHtml' => $contentHtml,
		'date' => $loc->datef(time(), 'Article')
	);
	$html = $tpl->parse('newsletter_turbo_item', $m);

	// get turbo stories
	$sql = 'SELECT id, title, content, content_html
		FROM ' . $this->table('Turbo') . '
		WHERE 	parent_id = ' . intval($id) . ' AND
			is_deleted = 0
		ORDER BY created';
	$result = $this->db->array_query_assoc($sql);

	foreach ($result as $r) {
		if (!empty($r['content_html'])) {

			$urlStory = $url . '#story-' . $r['id'];

			// replace objects with links to video
			$r['content_html'] = preg_replace('/\<object.*object\>/', '<a href="' . $urlStory . '" style="color: #E6E6E6;">Visit PokerNews to watch the video</a>', $r['content_html']);

			// get firrst paragraph
			$r['content_html'] = strip_tags($r['content_html'], '<p><br><h1><h2><h3><b><strong><i><ul><ol><li><blockquote><strike><a><sub><sup>');
			preg_match('/(\<p\>.*\<\/p\>)/sU', $r['content_html'], $matches);
			$contentHtml = (isset($matches[1])) ? $matches[1] : $r['content_html'];
			$contentHtml = str_replace(array('<p>', '</p>', '<i>', '</i>'), '', $contentHtml);
			$contentHtml = str_replace('<a', '<a style="color: #0098d9;" ', $contentHtml);
			$contentHtml .= '<p><i><a style="color: #e6e6e6" href="' . $urlStory . '">Read more</a></i></p>';

			$m = array(
				'url' => $urlStory,
				'title' => htmlspecialchars($r['title']),
				'contentHtml' => $contentHtml
			);
			$html .= $tpl->parse('newsletter_turbo_item', $m);
		}
		if (!empty($r['content'])) {
			$text .= "\n\n" . $r['title'] . "\n\n";
			$text .= $r['content'];
		}
	}

	$txt = &moon::shared('text');
	$page = &moon::page();
	$text = $txt->strip_tags($text);

	$html = str_replace('files_en', $page->home_url . 'files', $html);
	$html = str_replace('files/', $page->home_url . 'files/', $html);
	$html = str_replace('<img', '<img style="float:none;border:1px solid #000000;margin-right:20px;" ', $html);
	//$html = str_replace('<a', '<a style="color: #0098d9;" ', $html);
	//$html = str_replace('<p><b><i>', '<h2 style="margin: 0px 0px 0px 0px; padding: 0px 0px 0px 0px; font-size: 14px; font-style: italic">', $html);
	//$html = str_replace('</i></b></p>', '</h2>', $html);

	$html = str_replace('£', '&pound;', $html);
	$title = str_replace('£', '&pound;', $title);
	$html = str_replace('€', '&euro;', $html);
	$title = str_replace('€', '&euro;', $title);
	$html = str_replace(array('“', '”'), '"', $html);
	$title = str_replace(array('“', '”'), '"', $title);
	$html = str_replace(array('’'), '\'', $html);
	$title = str_replace(array('’'), '\'', $title);

	return array('title' => $title, 'url' => $url, 'text' => $text, 'html' => $html, 'imgSrc' => $imgSrc, 'imgAlt' => $imgAlt) + $iContactInfo;
}
function saveItem()
{
	$postData = $_POST;
	$postData['published'] = strtotime($_POST['published_date'] . ' ' . $_POST['published_time']);

	$tags = explode(',', $_POST['tags']);
	$tagsNew = array();
	foreach ($tags as $tag) {
		if ($tag == '') continue;
		$tagsNew[] = trim($tag);
	}
	$postData['tags'] = implode(',', $tagsNew);

	$postData['facebook_summary'] = isset($postData['facebook_summary']) ? trim(strip_tags($postData['facebook_summary'])) : '';
	$postData['twitter_text'] = isset($postData['twitter_text']) ? trim(strip_tags($postData['twitter_text'])) : '';
	$postData['short_title'] = isset($postData['short_title']) ? trim(strip_tags($postData['short_title'])) : '';

	$form = &$this->formItem;
	$form->fill($postData);
	$values = $form->get_values();

	// Filtering
	$data = array();
	$data = $values;
	$data['id'] = intval($values['id']);
	$data['category_id'] = intval($values['category_id']);
	if ($data['content'] === '') {
		$data['content'] = NULL;
	}

	$data['is_hidden'] = (empty($values['is_hidden'])) ? 0 : 1;
	$data['is_promo'] = (empty($values['is_promo'])) ? 0 : 1;
	$data['homepage_promo'] = (empty($values['homepage_promo'])) ? 0 : 1;
	$data['double_banner'] = (empty($values['double_banner'])) ? 0 : 1;
	$data['room_id'] = intval($values['room_id']);
	$data['enable_export'] = (empty($values['enable_export'])) ? 0 : 1;
	$id = $data['id'];

	// geo zones
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

	// Check date and time
	$badDate = FALSE;
	$badTime = FALSE;
	$date = explode('-', $postData['published_date']);
	$time = explode(':', $postData['published_time']);
	if (sizeof($date) != 3 OR checkdate($date[1], $date[2], $date[0]) === FALSE) {
		$badDate = TRUE;
	}
	if (sizeof($time) != 2 OR abs(intval($time[0])) > 24  OR abs(intval($time[1])) > 60) {
		$badTime = TRUE;
	}

	// tags


	// Validation
	$errorMsg = 0;
	if ($data['title'] == '') {
		$errorMsg = 1;
	} elseif ($data['uri'] == '') {
		$errorMsg = 2;
	} elseif ($badDate) {
		$errorMsg = 3;
	} elseif ($badTime) {
		$errorMsg = 4;
	} elseif ($data['category_id'] == 0) {
		$errorMsg = 6;
	} elseif (!is_object($rtf = $this->object('rtf'))) {
		$errorMsg = 9;
	} else {
		//check for uri duplicates
		$chechMonth = '';
		if ($this->my('name') == 'news') {
			$chechMonth = ' AND FROM_UNIXTIME(articles.published, \'%Y-%m\') = FROM_UNIXTIME(' . $this->db->escape($postData['published']) . ', \'%Y-%m\')';
		}
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->table('Articles') . '
			WHERE	uri = "' . $this->db->escape($data['uri']) . '" AND
			     	id <> ' . $id . $chechMonth;
		$result = $this->db->single_query_assoc($sql);
		if ($result['cnt'] != 0) {
			$errorMsg = 5;
		}
	}

 	if ($errorMsg) {
		$this->set_var('error', $errorMsg);
		return FALSE;
	}

	// if was refresh skip other steps and return
	if ($form->was_refresh()) {
		return $id;
	}

	$ins = $form->get_values('category_id', 'title', 'uri', 'meta_keywords', 'meta_description', 'summary', 'content', 'authors', 'img', 'img_alt', 'tags', 'published', 'is_hidden', 'is_promo', 'room_id', 'double_banner', 'enable_export', 'content_type', 'geo_target', 'homepage_promo', 'short_title', 'facebook_summary', 'twitter_text');
	if ($ins['geo_target'] === '') $ins['geo_target'] = NULL;
	$ins['updated'] = time();

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
		$page->alert($info['errorImage' . $err2]);
	}

	// authors
	if (is_object($oAuthors = $this->object('authors_shared'))) {
		$ins['authors'] = $oAuthors->assignAuthors($form->get('authors'));
	} else {
		$page = &moon::page();
		$page->alert('Authors not saved! Problems with authors component!');
	}

	//iskarpa ir kompiliuojam i html
	$rtf->setInstance( $this->get_var('rtf') . '~article');

	if ($ins['content_type'] == 'img') {
		list(,$ins['content_html']) = $rtf->parseTextTypeImg($id, $ins['content'], TRUE);
	} else {
		list(,$ins['content_html']) = $rtf->parseText($id, $ins['content'], TRUE);
	}

	//list(,$ins['content_html']) = $rtf->parseText($id, $ins['content']);
	$txt = &moon::shared('text');
	if ($ins['summary'] === '') {
		$ins['summary'] = $txt->excerpt($txt->strip_tags($ins['content']), 250);
	} else {
		$ins['summary'] = $txt->excerpt($txt->strip_tags($ins['summary']), 250);
	}

	$ins['is_turbo'] = 1;
	if ($id) {
		$this->db->update($ins, $this->table('Articles'), array('id' => $id));

		// log this action
		blame($this->my('fullname'), 'Updated', $id);

	} else {
		$ins['created'] = $ins['updated'];
		$ins['article_type'] = $this->get_var('typeNews');
		$id = $this->db->insert($ins, $this->table('Articles'), 'id');

		// log this action
		blame($this->my('fullname'), 'Created', $id);
		//$page = &moon::page();

		// ! uncomment later
		//$page->set_local('showSaved', 1);
	}
	
	if ($id) {
		$rtf->assignObjects($id);
	}

	$form->fill(array('id' => $id));
	return $id;

}
function saveItemTurbo()
{
	$page = &moon::page();
	$postData = $_POST;
	$form = &$this->formItemTurbo;
	$form->fill($postData);
	$values = $form->get_values();

	// Filtering
	$data = array();
	$data = $values;
	$data['id'] = intval($values['id']);
	$data['parent_id'] = intval($values['parent_id']);
	$data['title'] = strip_tags($data['title']);
	if ($data['content'] === '') {
		$data['content'] = NULL;
	}
	$id = $data['id'];
	$page->set_local('idArticle', $data['parent_id']);

	// Validation
	$errorMsg = 0;
	if ($data['title'] == '') {
		$errorMsg = 1;
	} elseif (!$data['content']) {
		$errorMsg = 2;
	} elseif (!is_object($rtf = $this->object('rtf'))) {
		$errorMsg = 9;
	}

 	if ($errorMsg) {
		$this->set_var('errorTurbo', $errorMsg);
		return FALSE;
	}

	// if was refresh skip other steps and return
	if ($form->was_refresh()) {
		return FALSE;
	}

	$ins = $form->get_values('parent_id', 'title', 'content');
	$ins['updated'] = time();

	$rtf->setInstance( $this->get_var('rtf') . '~' . $id);
	list(,$ins['content_html']) = $rtf->parseText($id, $ins['content'], TRUE);

	if ($id) {
		$this->db->update($ins, $this->table('Turbo'), array('id' => $id));

		// log this action
		blame($this->my('fullname'), 'Updated', $id);
	} else {
		$ins['created'] = $ins['updated'];
		$id = $this->db->insert($ins, $this->table('Turbo'), 'id');

		// log this action
		blame($this->my('fullname'), 'Created', $id);

		// ! uncomment later
		//$page->set_local('showSaved', 1);
	}

	if ($id) {
		//$rtf->setInstance( $this->get_var('rtf') . '~' . $id);
		$rtf->assignObjects($id);
	}

	$form->fill(array('id' => $id));
	return $data['parent_id'];
}
function saveImage($id , $name, &$err, $del = FALSE) //insertina irasa
{
	$err=0;
	$dir = $this->get_dir('imagesDirArticlesStd');
	$noPicture = FALSE;

	$f=new moon_file;
	$isUpload=$f->is_upload($name,$e);

	$sql = 'SELECT img
		FROM ' . $this->table('Articles') . ' WHERE id = ' . $id;
	$is = $this->db->single_query_assoc($sql);


	//downloads pic from gallery and saves to tmp
	$tmpFileName = '';
	if(isset($_POST['gallery_file_name']) && isset($_POST['gallery_file_name'][$name]) && !$isUpload) {
		$pos = strrpos($_POST['gallery_file_name'][$name], '.');

		$tmpFileName = 'tmp/'.uniqid('gallery_').rand().
			substr($_POST['gallery_file_name'][$name], $pos, strlen($_POST['gallery_file_name'][$name])-$pos);

		$isUpload=$f->is_url_content($_POST['gallery_file_name'][$name], $tmpFileName);

	}elseif (($isUpload) && !$f->has_extension('jpg,jpeg,gif,png')) {
		//neleistinas pletinys
		$err= 1;
		return;
	}
	$newPhoto=$curPhoto=isset($is[$name]) ? $is[$name] : '';
	//ar reikia sena trinti?
	if ( ($isUpload || $del) && $curPhoto) {
		$fDel=new moon_file;
		$dir_ = $dir . substr($curPhoto, 0, 4);
		$curPhoto = substr($curPhoto, 4);
		if ($fDel->is_file($dir_.'/'.$curPhoto)) {
			//gaunam failo pav. pagrindine dali ir extensiona
			$fnameBase = substr($curPhoto,0,9);
			$fnameExt = rtrim('.' . $fDel->file_ext(), '.');
			//trinamas pagrindinis img
			$fDel->delete();
			$newPhoto=null;
			//dabar dar trinam maziausia img

			if ($fDel->is_file($dir_ . '/thumb_' . $fnameBase . $fnameExt)) $fDel->delete();
			//dabar dar trinam originalu img
			if ($fDel->is_file($dir_ . '/orig/' . $fnameBase . $fnameExt)) $fDel->delete();
			$noPicture = TRUE;
		}
	}
	if ($isUpload) { //isaugom faila
		$fnameBaseFull = uniqid('');
		$dir_ = $dir . substr($fnameBaseFull, 0, 4);
		$fnameBase  = substr($fnameBaseFull, 4);
		$fnameExt = rtrim('.' . $f->file_ext(), '.');
		$img=&moon::shared('img');
		if (!file_exists($dir_)) {
			$oldumask = umask(0);
			mkdir($dir_, 0777);
			mkdir($dir_ . '/orig', 0777);
			umask($oldumask);
		}
		if (!file_exists($dir_ . '/orig')) {
			$oldumask = umask(0);
			mkdir($dir_ . '/orig', 0777);
			umask($oldumask);
		}
		//pernelyg dideli img susimazinam bent iki 800x800
		$nameSave = $dir_ . '/orig/' . $fnameBase . $fnameExt;
		if ( $img->resize($f,$nameSave,800,800) && $f->is_file($nameSave) ) {
			$newPhoto = $fnameBaseFull . $fnameExt;
			//pagaminam thumbnailus is paveiksliuko
			$img->resize_exact($f, $dir_ . '/' . $fnameBase . '' . $fnameExt, 460, 305);
			if ($f->is_file($dir_ . '/' . $fnameBase . '' . $fnameExt))
			$img->resize_exact($f, $dir_ . '/thumb_' . $fnameBase . $fnameExt, 140,93);
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
	//if ($noPicture) {
	//	$err = 3;
	//}

	//deletes tmp pic downloaded from gallery
	if($tmpFileName) {
		unlink($tmpFileName);
	}

	return $newPhoto;
}

//pakeiciam paveiksliuka gauta su crop toolsu
function imgReplace($id) //insertina irasa
{
	$dir = $this->get_dir('imagesDirArticlesStd');
	$is=$this->db->single_query_assoc('
			SELECT img
			FROM ' . $this->table('Articles') . ' WHERE id=' . intval($id)
		);
	if (empty ($is)) {
		return;
	}

	$curDir_ = $dir . substr($is['img'], 0, 4);
	$curPhoto = substr($is['img'], 4);

	$f = new moon_file;
	if ($f->is_file($curDir_ . '/orig/' . $curPhoto)) {
		$nw = $_POST['newwidth'];
		$nh = $_POST['newheight'];
		$left = $_POST['left'];
		$top = $_POST['top'];
		$img = &moon::shared('img');

		$newPhotoFull = uniqid('') . '.' . $f->file_ext();
		$newDir_ = $dir . substr($newPhotoFull, 0, 4);
		$newPhoto  = substr($newPhotoFull, 4);

		//pernelyg dideli img susimazinam bent iki 800x800
		$nameSave = $newDir_ . '/orig/' . $newPhoto;
		$img = & moon::shared('img');
		//padarom kopijas
		if ( $f->copy($nameSave) ) {

			//crop is originalo pagal imgtool duomenis
			if ($img->crop($f, $newDir_ . '/' . $newPhoto, $nw, $nh, $left, $top)) {
				if ($f->is_file($newDir_ . '/' . $newPhoto)) {
					$img->resize_exact($f, $newDir_ . '/' . $newPhoto, 460, 305);
				}
			}
			if ($f->is_file($newDir_ . '/' . $newPhoto)) {
				$img->resize_exact($f, $newDir_ . '/thumb_' . $newPhoto, 140, 93);
			}
			$this->db->update(array('img'=>$newPhotoFull), $this->table('Articles'), $id);

			//dabar trinam senus
			$del = array(
				$curDir_ . '/' . $curPhoto,
				$curDir_ . '/orig/' . $curPhoto,
				$curDir_ . '/thumb_' . $curPhoto
				);
			foreach ($del as $name) {
				if ($f->is_file($name)) {
					$f->delete();
				}
			}
		}
	}
}
function sendNewsletter()
{
	$startTime = time();

	$loc = &moon::locale();
	$page = &moon::page();
	$page->set_global('enableSendButton', '');
	$tpl = $this->load_template();

	$postData = $_POST;
	$errMsg = '';
	$okMsg = '';
	$id = 0;

	if (!empty($postData['id'])) {
		$id = intval($postData['id']);

		if ($page->was_refresh()) {
			return $id;
		}

		if($this->isTurboAlreadySent($id)) {
			$errMsg .= 'Turbo newsletter is already sent';
		} else {
			$articleTurbo = $this->getArticleTurboData($id);

			$text = $articleTurbo['text'];
			$html = $articleTurbo['html'];
			$url = $articleTurbo['url'];
			$title = $articleTurbo['title'];
			$imgSrc = $articleTurbo['imgSrc'];
			$imgAlt = $articleTurbo['imgAlt'];

			if (!$html OR !$text) {
				$errMsg .= 'unable to get article text';
			}

			$tplText = array(
				'date' => $loc->datef(time(), '%{m} %D %H:%i'),
				'title' => $title,
				'url' => $url,
				'contents' => $text,
				'homeUrl' => $page->home_url
			);
			$tplHtml = array(
				'date' => $loc->datef(time(), '%{m} %D %H:%i'),
				'title' => $title,
				'url' => $url,
				'contents' => $html,
				'homeUrl' => $page->home_url,
				'imgSrc' => $imgSrc,
				'imgAlt' => $imgAlt
			);

			$bodyTxt = $tpl->parse('newsletter_txt', $tplText);
			$bodyHtml = $tpl->parse('newsletter_html', $tplHtml);

			
			if (is_dev()) {
				$tpl = $this->load_template();

				$tplText = array(
					'date' => $loc->datef(time(), '%{m} %D %H:%i'),
					'title' => $title,
					'url' => $url,
					'contents' => $text,
					'homeUrl' => $page->home_url
				);
				$tplHtml = array(
					'date' => $loc->datef(time(), '%{m} %D %H:%i'),
					'title' => $title,
					'url' => $url,
					'contents' => $html,
					'homeUrl' => $page->home_url
				);

				$bodyTxt = $tpl->parse('newsletter_txt', $tplText);
				$bodyHtml = $tpl->parse('newsletter_html', $tplHtml);

				$mail = &moon::mail();
				$mail->charset('utf-8');
				$mail->subject('Nightly Turbo - ' . date('H:i'));
				$mail->from('aleksandras.kotovas@ntsg.lt');
				$mail->body($bodyTxt, $bodyHtml);

				//$mail->cc('juras.jursenas@ntsg.lt, marius.burinskas@ntsg.lt, audrius.naslenas@ntsg.lt);
				$mail->to('aleksandras.kotovas@seo.lt');

				if ($mail->send()) {
					//echo 'Ok - ' . date('Y-m-d H:i:s');
				}
				else {
					$errMsg .= 'cannot send newsletter';
				}
			} else {
				// send newsletter here to iContact
				/*
				include_class('icontact');
				$db = new icontact;

				if (!is_dev()) {
					$db->select_list(29200);
					$db->select_campaign(14545);
				}

				$messageId = !empty($articleTurbo['message_id']) ? $articleTurbo['message_id'] : 0;
				$scheduledTime = 0;
				$sent = $db->send_email($title, $bodyTxt, $bodyHtml, $scheduledTime, $messageId);
				if (is_array($sent) && $sent[0]) {
					$this->updateTurboSentCount($id);
				} else {
					$errMsg .= 'Unable to sent email';
				}

				if ($id && is_array($sent)) {
					$this->updateIcontactInfo($id, $sent);
				}
				*/
			}
		}

	} else {
		$errMsg .= 'article id not set';
	}

	if ($errMsg) {
		$page->alert('Error occured: ' . $errMsg);
		// log this action
		blame($this->my('fullname'), 'Created', 'Newsletter sent (was error)');
	} else {
		$page->alert('Success: Newsletter was sent', 'ok');

		// log this action
		blame($this->my('fullname'), 'Created', 'Newsletter sent');
	}

	return $id;
}
//***************************************
//             --- DB ---
//***************************************
function getItems()
{
	$sql = '
		SELECT a.id, a.published, a.title, a.category_id, a.is_hidden, c.title as categoryTitle, a.homepage_promo, a.views_count
		FROM ' . $this->table('Articles') . ' as a
			LEFT JOIN ' . $this->table('ArticlesCategories') . ' as c
			ON a.category_id = c.id' . ' ' .
		$this->sqlWhere . ' ' .
		$this->sqlOrder . ' ' .
		$this->sqlLimit;
	$result = $this->db->array_query_assoc($sql);
	return $result;
}
function getItemsCount()
{
	$sql = '
		SELECT count(*) as cnt
		FROM ' . $this->table('Articles') . ' as a ' .
		$this->sqlWhere;
	$result = $this->db->single_query_assoc($sql);
	return $result['cnt'];
}
function getItem($id)
{
	$sql = '
		SELECT *
		FROM ' . $this->table('Articles') . '
		WHERE	id = ' . intval($id) . ' AND
		     	article_type = ' . $this->get_var('articlesType');
	return $this->db->single_query_assoc($sql);
}
function deleteItem($ids)
{
	if (!is_array($ids) || !count($ids)) return;
	foreach ($ids as $k => $v) {
		$ids[$k] = intval($v);
	}
	$this->db->query('UPDATE ' . $this->table('Articles') . ' SET is_hidden = 2 WHERE id IN (' . implode(',', $ids) . ')');

	// log this action
	blame($this->my('fullname'), 'Deleted', $ids);

	return TRUE;
}
function deleteItemTurbo($id)
{
	$this->db->query('UPDATE ' . $this->table('Turbo') . ' SET is_deleted = 1 WHERE id = ' . $id);

	// log this action
	blame($this->my('fullname'), 'Deleted', $id);

	return TRUE;
}
function updateTurboSentCount($articleId)
{
	$sql = 'UPDATE ' . $this->table('Articles') . '
		SET turbo_sent_cnt = turbo_sent_cnt + 1
		WHERE id = ' . intval($articleId);
	$this->db->query($sql);
	return TRUE;
}
function updateIcontactInfo($id, $sent)
{
	if ($id && is_array($sent)) {
		list($sendID, $messageID, $spamScore, $spamInfo) = $sent;
	} else {
		return FALSE;
	}
	$a = array();
	$a['turbo_ic_message_id'] = $messageID;
	$a['turbo_ic_spam_score'] = $spamScore;
	$a['turbo_ic_spam_info'] = $spamInfo;
	if (floatval($spamScore) >= 5.0) {
		$page = moon::page();
		$page->alert('Spam score of this newsletter is too high! Score: ' . $spamScore, 'W');
	}
	$this->db->update($a, $this->table('Articles'), $id);
}
function isTurboAlreadySent($articleId)
{
	$sql = 'SELECT turbo_sent_cnt
		FROM ' . $this->table('Articles') . '
		WHERE id = ' . intval($articleId);
	$res = $this->db->single_query_assoc($sql);
	if (isset($res['turbo_sent_cnt']) && $res['turbo_sent_cnt'] > 0) {
		return true;
	}
	return false;
}
function setSqlWhere()
{
	$where = array();

	$type = $this->get_var('typeNews');

	$where[] = 'WHERE article_type = ' . $type;
	$where[] = 'a.is_hidden < 2';
	$where[] = 'a.is_turbo = 1';

	if (!empty($this->filter)) {
		if ($this->filter['title'] != '') {
			$where[] = 'a.title LIKE \'%' . $this->db->escape($this->filter['title']) . '%\'';
		}
	}
	$this->sqlWhere = implode(' AND ', $where);
}
function getRoomsForBanner()
{
	if(!$this->tablesExists($this->table('Rooms'))) return array();

	$sql = '
		SELECT id, name
		FROM ' . $this->table('Rooms') . '
		WHERE is_hidden = 0';
	$result = $this->db->array_query_assoc($sql);

	$rooms = array();
	foreach ($result as $item) {
		$rooms[$item['id']] = $item['name'];
	}
	return $rooms;
}
function getCategories($catId = null)
{
	$sql = '
		SELECT id, title, parent_id
		FROM ' . $this->table('ArticlesCategories') . '
		WHERE	(is_hidden = 0 ' . (($catId) ? ' OR id = ' . $catId : '') . ') AND
		     	category_type = ' . $this->get_var('articlesType') . '
		ORDER BY sort_order ASC, title ASC';
	return $this->db->array_query_assoc($sql, 'id');
}
function getTree($items, $currentParent, &$tree, $currLevel = 0, $prevLevel = -1)
{
	foreach ($items as $categoryId => $category) {
		if ($currentParent == $category['parent_id']) {
			$title = $category['title'];
			for ($i = 0;$i < $currLevel;$i++) {
				$title = ' -- ' . $title;
			}

			$tree[$categoryId] = $title;

			if ($currLevel > $prevLevel) {
				$prevLevel = $currLevel;
			}
			$currLevel++;
			$this->getTree($items, $categoryId, $tree, $currLevel, $prevLevel);
			$currLevel--;
		}
	}
	return $tree;
}
function getTurboItems($id)
{
	$sql = 'SELECT id, title, content, content_html, created, updated
		FROM ' . $this->table('Turbo') . '
		WHERE 	is_deleted = 0 AND
			parent_id = ' . $id . '
		ORDER BY created';
	$result = $this->db->array_query_assoc($sql);
	return $result;
}
function getItemTurbo($id)
{
	$sql = 'SELECT *
		FROM ' . $this->table('Turbo') . '
		WHERE	id = ' . intval($id);
	return $this->db->single_query_assoc($sql);
}
//***************************************
//           --- COMON ---
//***************************************
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
	$filter['title'] = $this->formFilter->get('title');

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
		array('published' => 0, 'title' => 1, 'is_hidden' => 1) ,
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
//***************************************
//        --- TAGS & AUTHORS ---
//***************************************
function getHtmlAuthors()
{
	$sql = 'SELECT name
		FROM ' . $this->table('Authors') . '
		WHERE	duplicates = 0 AND
		     	is_deleted = 0';
	$result = $this->db->array_query($sql);
	$authors = array();
	foreach ($result as $r) {
		$r[0] = str_replace('\\', '\\\\', $r[0]);
		$authors[] = str_replace('"', '\"', $r[0]);
	}
	return $authors;
}
function getHtmlTags()
{
	$sql = 'SELECT name
		FROM ' . $this->table('Tags') . '
		WHERE is_hidden = 0';
	$result = $this->db->array_query($sql);
	$tags = array();
	foreach ($result as $r) {
		$r[0] = str_replace('\\', '\\\\', $r[0]);
		$tags[] = str_replace('"', '\"', $r[0]);
	}
	return $tags;
}
function ajaxGetAuthors($starts)
{
	$limit = 10;
	$sql = 'SELECT name
		FROM ' . $this->table('Authors') . "
		WHERE	name like '" . $this->db->escape($starts) . "%'
		LIMIT " . ($limit + 1);
	$result = $this->db->array_query($sql);

	header('Content-Type: text/plain;charset=utf-8');
	header('Expires: Mon, 26 Jul 2000 05:00:00 GMT');
	header('Last-Modified: '.gmdate('r'));
	header('Cache-Control: no-cache, must-revalidate');
	header('Pragma: no-cache');
	foreach ($result as $key => $value) {
		if ($key == $limit) {
			echo '<span>...</span>';
		}
		echo '<div>' . $value['name'] . '</div>';
	}
}
function getRecommendedTags($text)
{
	$sql = 'SELECT name
		FROM ' . $this->table('Tags') . '
		WHERE is_hidden = 0
		ORDER BY name';
	$result = $this->db->array_query_assoc($sql);

	$text = str_replace(array('.', ',', '"', ':', ']', '[', '=', '+', '-', ')', '(', '*', '!', '?', '', "\n", "\r"), ' ', $text);
	$recommended = array();
	foreach ($result as $r) {
		if (stripos($text, $r['name']) !== FALSE) {
			$recommended[] = $r['name'];
		} elseif(($wordsCnt = count($words = explode(' ', $r['name']))) > 1) {
			$found = 0;
			foreach ($words as $w) {
				if (stripos($text, ' ' . $w . ' ') !== FALSE) {
					$found++;
				}
			}
			if ($found == $wordsCnt) {
				$recommended[] = $r['name'];
			}
		}
	}
	return $recommended;
}
function getAuthors($idsString)
{
	$a = explode(',', $idsString);
	$ids = array();
	foreach ($a as $id) {
		if ($id = intval($id)) {
			$ids[] = $id;
		}
	}
	//kas yra istrinti ir turi pakaitala
	if (!count($ids)) return '';
	$m = $this->db->array_query_assoc(
		"SELECT duplicates,id FROM " . $this->table('Authors') .
		" WHERE id IN (" . implode(', ', $ids) . ") AND duplicates>0"
	);
	//pakeiciam id tu, kurie turi pakaitala
	foreach ($m as $v) {
		$k = array_search($v['id'],$ids);
		if (isset($ids[$k])) {
			$ids[$k] = $v['duplicates'];
		}
	}
	//dabar gaunam sarasa
	$m = $this->db->array_query_assoc(
		"SELECT name,id,uri FROM " . $this->table('Authors') .
		" WHERE id IN (" . implode(', ', $ids) . ")"
	);
	$authors = array();
	foreach ($m as $d) {
		$authors[$d['id']] = $d;
	}
	$r = array();
	foreach ($ids as $id) {
		if (isset($authors[$id])) {
			$r[$id] = $authors[$id];
		}
	}
	return $r;
}
//***************************************
//         --- BROKEN LINKS ---
//***************************************
function checkBrokenLinks($html)
{
	$patternImg = '/.*<img.*src="(.*)".*/U';
	$patternA = '/.*<a.*href="(.*)".*/U';

	$detected = false;

	$img = array();
	$a = array();

	$matches = array();
	if(preg_match_all($patternImg, $html, $matches)) {
		foreach ($matches[1] as $m) {
			if ($m) {
				// check link
				if (($code = $this->isUrlAvailable($m)) !== true) {
					$img[$m] = $code;
				}
			}
		}
	}

	$matches = array();
	if(preg_match_all($patternA, $html, $matches)) {
		foreach ($matches[1] as $m) {
			if ($m) {
				// check link
				if (($code = $this->isUrlAvailable($m)) !== true) {
					$a[$m] = $code;
				}
			}
		}
	}

	$res = array();
	if (!empty($img)) $res['img'] = $img;
	if (!empty($a)) $res['a'] = $a;
	return $res;
}
function isUrlAvailable($url)
{
	if ($url == '#' || stripos($url, 'winamp://') === 0 || stripos($url, 'itpc://') === 0) {
		return true;
	}

	$page = moon::page();
	$homeUrl = $page->home_url();

	$url = strpos($url, 'http://') === false && strpos($url, 'https://') === false ? $homeUrl . ltrim($url, '/') : $url;
	$url = str_replace('http://'.$this->getMyDomainName().'.com', 'http://www.'.$this->getMyDomainName().'.com', $url);
	$url = htmlspecialchars_decode($url);

	$ch = curl_init();
        $opts = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_URL => $url,
		CURLOPT_NOBODY => false,
		CURLOPT_TIMEOUT => 5,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1'
	);
	curl_setopt_array($ch, $opts);
	curl_exec($ch);

	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$retval = (substr($httpCode,0,1) == 2) || (substr($httpCode,0,1) == 1) || (substr($httpCode,0,1) == 3);
	// redirects are ok

    curl_close($ch);
    return $retval ? true : $httpCode;
}
//***************************************
//           --- OTHER ---
//***************************************
function getImageSrc($imageFn, $prefix = '')
{
	if (empty($imageFn)) {
		return NULL;
	}
	$fnParts = explode(':', $imageFn);
	$imagePrefix = 1 == count($fnParts)
		? NULL
		: $fnParts[0];
	$imageFn = array_pop($fnParts);

	if ($imagePrefix == 'shr') {
		// gallery
		return;
	}

	$imageFn = $this->get_var('imagesSrcArticlesStd') . substr($imageFn, 0, 4) . '/' . $prefix . substr($imageFn, 4);
	if ($imagePrefix != NULL) {
		$rhost = $imagePrefix == 'com' ? 'www' : $imagePrefix;
		$imageFn = 'http://' . $rhost . '.' . $this->getMyDomainName() . '.' . (is_dev() ? 'dev' : 'com') . $imageFn;
	}
	return $imageFn;
}
function getArticleUrl($id, $absolute = TRUE) {

	$newsItem = $this->getitem($id);

	$year = date('Y', $newsItem['published']);
	$month = date('m', $newsItem['published']);

	$articlesNumericSuffix = '';
	if ($newsItem['id'] >= $this->articlesSuffixStartId) {
		$articlesNumericSuffix = $this->addToArticlesSuffix + $newsItem['id'];
	}
	$eng = &moon::engine();
	$page = &moon::page();
	$sitemap = &moon::shared('sitemap');

	$homeURL = $page->home_url();
	$urlBase = $absolute ? $homeURL : '';

	$uri =	$sitemap->getLink('news') .
	      	$year .
	      	'/' . $month .
	      	'/' . $newsItem['uri'] . '-' . $articlesNumericSuffix . '.htm';
	return $urlBase
		? rtrim($urlBase, '/') . $uri
		: $uri;
}
function getContentTypes()
{
	return array(
		'video' => 'Video'
	);
}
//***************************************
//    --- ARTICLE TYPE SPECIFIC ---
//***************************************
function getMyUriPrefix()
{
	return moon::shared('sitemap')->getLink('news');
}
function getMyDomainName()
{
	return 'pokernetwork';
}

// other
function tablesExists($tableName)
{
	$m = $this->db->single_query("show tables like '" . $tableName . "'");
	return (count($m) ? TRUE : FALSE);
}

}
?>