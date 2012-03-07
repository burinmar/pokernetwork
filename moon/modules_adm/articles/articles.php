<?php
class articles extends moon_com {

function onload()
{
	$this->filter = array();
	$this->formFilter = &$this->form();
	$this->formFilter->names('title', 'groups', 'has_broken_links');

	$this->formItem = &$this->form('item');
	$this->formItem->names('id', 'category_id', 'title', 'uri', 'meta_keywords', 'meta_description', 'summary', 'content', 'authors', 'img', 'img_alt', 'tags', 'published', 'is_hidden', 'is_promo', 'room_id', 'double_banner', 'content_type', 'geo_target', 'promo_text', 'promo_box_on', 'homepage_promo', 'short_title', 'facebook_summary', 'twitter_text');

	$this->sqlWhere = ''; // set by filter
	$this->sqlOrder = '';
	$this->sqlLimit = ''; // set by paging

	$this->articlesSuffixStartId = $this->get_var('articlesSuffixStartId');
	$this->addToArticlesSuffix = 1000;

	$this->leadImgWidth = 460;
	$this->leadImgHeight = 305;
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
					'src' => $this->getImageSrc($m['img'],'orig/'),
					'minWH' => $this->leadImgWidth.'x'.$this->leadImgHeight,
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
			$this->forget();
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
		case 'ajax-check-broken-links':
			$this->forget();
			$page = &moon::page();
			if (isset($_POST['id']) && is_numeric($_POST['id'])) {
				$id = intval($_POST['id']);
				$html = '';
				if(!empty($_POST['content'])) {

					ignore_user_abort(true);

					ob_end_clean();
					ob_start();
					$size = ob_get_length();
					header("Connection: close");
					header("Content-Length: $size");
					ob_end_flush();flush();

					$html = $_POST['content'];
					if (is_object($rtf = $this->object('rtf'))) {
						$rtf->setInstance( $this->get_var('rtf') );
						list(,$html) = $rtf->parseText(0,$html);
					}
				} else {
					$a = $this->db->single_query_assoc(
						'SELECT content_html
						FROM ' . $this->table('Articles') . '
						WHERE id = ' . $id
					);
					$html = !empty($a['content_html']) ? $a['content_html'] : '';
				}

				if ($html) {
					$res = $this->checkBrokenLinks($html);
					$found = 0;
					$outputStr = '';
					if (!empty($res)) {
						$outputStr .= '<b>Possible broken links found in this article, please fix them:</b>';
						$links = array();
						if (isset($res['img'])) {
							foreach ($res['img'] as $url=>$v) {
								$links[] = '<a href="'.$url.'">'.$url.'</a> - status code: ' . $v;
							}
						}
						if (isset($res['a'])) {
						       foreach ($res['a'] as $url=>$v) {
								$links[] = '<a href="'.$url.'">'.$url.'</a> - status code: ' . $v;
							}
						}
						if (!empty($links)) $found = 1;
						$outputStr .= implode('<br />', $links);
					} else {
						//$outputStr .= '<span style="color:#00AA00;">Ok</span>';
					}
					$this->db->update(array('has_broken_links' => $found), $this->table('Articles'), array('id' => $id));
					print $outputStr;
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
			if (isset ($_GET['page'])) {
				$this->set_var('currPage', (int) $_GET['page']);
			}
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
		'error' => FALSE
	);
}
function main($vars)
{
	$page = &moon::page();
	$win = &moon::shared('admin');
	$win->active($this->my('fullname'));
	$title = $win->current_info('title');
	$page->title($title);

	$save = array('currPage' => $vars['currPage']);
	$this->save_vars($save);

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

	$ordering = $this->getOrdering();
	$filter = $this->getFilter();
	$paging = $this->getPaging($vars['currPage'], $this->getItemsCount(), $vars['listLimit']);

	$goEdit = $this->linkas('#edit','{id}');
	$tpl->save_parsed('items',array('goEdit' => $goEdit));

	$loc = moon::locale();

	$items = $this->getItems();
	$itemsList = '';
	$now = round(time(), -2);
	foreach ($items as $item) {
		$item['title'] = htmlspecialchars($item['title']);
		$item['date'] = $loc->datef($item['published'], 'Date');
		if ($item['homepage_promo']) {
			$item['date'] .= '<span style="color:red">*</span>';
		}
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
		'goBack' => $this->linkas('#') . '?page=' . $vars['currPage'],
		'pageTitle' => $win->current_info('title'),
		'formTitle' => htmlspecialchars($title),
		'uriPrefix' => $this->getMyUriPrefix(),
		'refresh' => $page->refresh_field(),
		'toolbar' => '',
		'geo_zones' => '',
		'uriNumericSuffix' => '',

		'showPromo' => true,
		'showHomepageSlider' => true,
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

	$optContentType = $this->getContentTypes($form->get('content_type'));
	$m['optContentType'] = $form->options('content_type', $optContentType);
	
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

	// promo text
	//$postData['promo_text'] = preg_replace('/(?:(?:\r\n)|\r|\n){2,}/', "\n", $postData['promo_text']);

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
	$data['promo_box_on'] = (empty($values['promo_box_on'])) ? 0 : 1;
	$data['room_id'] = intval($values['room_id']);
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
	if (sizeof($date) != 3 AND checkdate($date[1], $date[2], $date[0]) === FALSE) {
		$badDate = TRUE;
	}
	if (sizeof($time) != 2 AND (strlen(strval($time[0]))) != 2 OR strlen(strval($time[1])) != 2) {
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
		$sql = 'SELECT count(*) as cnt
			FROM ' . $this->table('Articles') . '
			WHERE	uri = "' . $this->db->escape($data['uri']) . '" AND
			     	article_type = ' . $this->get_var('articlesType') . ' AND
			     	id <> ' . $id;
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

	$ins = $form->get_values('category_id', 'title', 'uri', 'meta_keywords', 'meta_description', 'summary', 'content', 'authors', 'img', 'img_alt', 'tags', 'published', 'is_hidden', 'is_promo', 'room_id', 'double_banner', 'content_type', 'geo_target', 'promo_text', 'promo_box_on', 'homepage_promo', 'short_title', 'facebook_summary', 'twitter_text');
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
	$rtf->setInstance( $this->get_var('rtf') );

	if ($ins['content_type'] == 'img') {
		list(,$ins['content_html']) = $rtf->parseTextTypeImg($id, $ins['content'], TRUE);
	} else {
		list(,$ins['content_html']) = $rtf->parseText($id, $ins['content'], TRUE);
	}

	$txt = &moon::shared('text');
	if ($ins['summary'] === '') {
		$ins['summary'] = $txt->excerpt($txt->strip_tags($ins['content']), 125);
	} else {
		$ins['summary'] = $txt->excerpt($txt->strip_tags($ins['summary']), 125);
	}

	if ($id) {
		$this->db->update($ins, $this->table('Articles'), array('id' => $id));

		// log this action
		blame($this->my('fullname'), 'Updated', $id);

	} else {
		// check for broken links
		$res = $this->checkBrokenLinks($ins['content_html']);
		if (!empty($res)) $ins['has_broken_links'] = 1;

		$ins['created'] = $ins['updated'];
		$ins['article_type'] = $this->get_var('articlesType');
		$id = $this->db->insert($ins, $this->table('Articles'), 'id');

		// log this action
		blame($this->my('fullname'), 'Created', $id);
	}

	if ($id) {
		$rtf->assignObjects($id);
	}

	$form->fill(array('id' => $id));
	return $id;

}
function saveImage($id , $name, &$err, $del = FALSE) //insertina irasa
{

	$err=0;
	$dir = $this->get_dir('imagesDirArticlesStd');
	$noPicture = FALSE;

	$sql = 'SELECT img
		FROM ' . $this->table('Articles') . ' WHERE id = ' . $id;
	$is = $this->db->single_query_assoc($sql);
	$f=new moon_file;
	$isUpload=$f->is_upload($name,$e);

	//downloads pic from gallery and saves to tmp
	$tmpFileName = '';
	if(isset($_POST['gallery_file_name']) && isset($_POST['gallery_file_name'][$name]) && !$isUpload) {
		$pos = strrpos($_POST['gallery_file_name'][$name], '.');

		$tmpFileName = 'tmp/'.uniqid('gallery_').rand().
			substr($_POST['gallery_file_name'][$name], $pos, strlen($_POST['gallery_file_name'][$name])-$pos);

		$isUpload=$f->is_url_content($_POST['gallery_file_name'][$name], $tmpFileName);

	}elseif ($isUpload && !$f->has_extension('jpg,jpeg,gif,png')) {
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

			//dabar dar trinam vidutini img
			if ($fDel->is_file($dir_ . '/mid_' . $fnameBase . $fnameExt)) $fDel->delete();

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
			$img->resize_exact($f, $dir_ . '/' . $fnameBase . $fnameExt, $this->leadImgWidth, $this->leadImgHeight);
			if ($f->is_file($dir_ . '/' . $fnameBase . '' . $fnameExt)) {
				$img->resize_exact($f, $dir_ . '/thumb_' . $fnameBase . $fnameExt, 120,80);
				$img->resize_exact($f, $dir_ . '/mid_' . $fnameBase . $fnameExt, 223,147);
			}
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

		if (!file_exists($newDir_)) {
			$oldumask = umask(0);
			mkdir($newDir_, 0777);
			mkdir($newDir_ . '/orig', 0777);
			umask($oldumask);
		}
		//padarom kopijas
		if ( $f->copy($nameSave) ) {

			//crop is originalo pagal imgtool duomenis
			if ($img->crop($f, $newDir_ . '/' . $newPhoto, $nw, $nh, $left, $top)) {
				if ($f->is_file($newDir_ . '/' . $newPhoto)) {
					$img->resize_exact($f, $newDir_ . '/' . $newPhoto, $this->leadImgWidth, $this->leadImgHeight);
				}
			}
			if ($f->is_file($newDir_ . '/' . $newPhoto)) {
				$img->resize_exact($f, $newDir_ . '/thumb_' . $newPhoto, 120, 80);
				$img->resize_exact($f, $newDir_ . '/mid_' . $newPhoto, 223,147);
			}
			$this->db->update(array('img'=>$newPhotoFull), $this->table('Articles'), $id);

			//dabar trinam senus
			$del = array(
				$curDir_ . '/' . $curPhoto,
				$curDir_ . '/orig/' . $curPhoto,
				$curDir_ . '/thumb_' . $curPhoto,
				$curDir_ . '/mid_' . $curPhoto
				);
			foreach ($del as $name) {
				if ($f->is_file($name)) {
					$f->delete();
				}
			}
		}
	}
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
function setSqlWhere()
{
	$where = array();
	$where[] = 'WHERE article_type = ' . $this->get_var('articlesType');
	$where[] = 'a.is_hidden < 2';
	$where[] = 'a.is_turbo = 0';

	if (!empty($this->filter)) {
		if ($this->filter['title'] != '') {
			$where[] = 'a.title LIKE \'%' . $this->db->escape($this->filter['title']) . '%\'';
		}
		if ($this->filter['category'] != '') {
			$where[] = 'a.category_id = ' . $this->filter['category'];
		}
		if (!empty($this->filter['has_broken_links'])) {
			$where[] = 'a.has_broken_links = 1';
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
	$filter['category'] = $this->formFilter->get('category');
	$filter['has_broken_links'] = $this->formFilter->checked('has_broken_links', 1);
	$filter['showBrokenLinks'] = true;

	$dummy = array();
	$categoriesItems = $this->getCategories();
	$tree = $this->getTree($categoriesItems, 0, $dummy);
	$filter['categories'] = $this->formFilter->options('category', $tree);

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

function getPaging($currPage, $itemsCnt, $listLimit)
{
	$pn = &moon::shared('paginate');
	$pn->set_curent_all_limit($currPage, $itemsCnt, $listLimit);
	$pn->set_url($this->linkas('#', '', array('page' => '{pg}')));
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
		WHERE 	duplicates = 0 AND
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
		WHERE 	is_hidden = 0';
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
		WHERE 	name like '" . $this->db->escape($starts) . "%'
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
		WHERE	is_hidden = 0
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
function getContentTypes()
{
	return array(
		'img' => 'Image',
		'video' => 'Video',
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