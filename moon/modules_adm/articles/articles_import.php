<?php

class articles_import extends moon_com 
{
	/*

	IMPORT INSTRUCTIONS
	
	nurodyt sena db

	1. $this->oldDB = 'pokernetwork_old';

	paleisti importus

	2. http://www.pokernetwork.dev/adm/articles-articles_import/categories
	3. http://www.pokernetwork.dev/adm/articles-articles_import/tags
	4. http://www.pokernetwork.dev/adm/articles-articles_import/news
		reikes kelis kartus paleisti, importuoja dalimis
	5. http://www.pokernetwork.dev/adm/articles-articles_import/recompile

	6. moon/modules/articles.config.cfg.php 'var.suffixStartId' - nurodyt select max(id) + 1 from articles
	
	7. padaryt reikalingus redirectus naujienu kategorijoms
		http://www.pokernetwork.com/australian-poker-news
		http://www.pokernetwork.com/sports-betting
		http://www.pokernetwork.com/world-poker-news
		http://www.pokernetwork.com/articles
		http://www.pokernetwork.com/promotions
		http://www.pokernetwork.com/poker-strategy

		-> http://www.pokernetwork.com/news/australian-poker-news ir tt.

	*/

	function onload()
	{
		$this->oldDB = 'pokernetwork_old';

		$this->homeUrl = 'http://www.pokernetwork.com';
		$this->siteDomain = 'www.pokernetwork.com';

		$this->leadingImgWidth = 460;
		$this->leadingImgHeight = 305;

		$this->attachmentsDir = _W_DIR_ . 'articles/att/';
		$this->leadingImgDir = _W_DIR_ . 'articles/img/';
	}


	function events($event, $par)
	{
		$msg = 'Articles import<hr />' . $event . '<br />';

		switch($event)
		{
			case 'categories':
				$msg = $this->importCategories();
				break;
			case 'tags':
				$msg = $this->importTags();
				break;
			case 'news':
				$msg = $this->importNews();
				break;
			case 'recompile':
				$msg = $this->recompile();
		}

		print $msg;exit;
	}


	private function importNews()
	{
		$msg = array();

		$txt = &moon::shared('text');
		$user = moon::user();

		$rtf = $this->object('rtf');
		$rtf->setInstance($this->get_var('rtf'));
		$homeUrl = $this->homeUrl;//$page->home_url();


		require 'htmltocode.php';
		$parser = new htmlToCode();
		$parser->siteDomain = $this->siteDomain;
		$parser->externalImagesAsObj = FALSE;


		$sql1 = 'SELECT id FROM articles WHERE article_type = 1';
		$res1 = $this->db->array_query_assoc($sql1, 'id');
		$importedIds = array_keys($res1);

		// get main data
		$sql = '
			SELECT n.nid as id, n.title, n.status, n.created, n.changed, /*nr.title,*/ nr.body, nr.teaser, nr.format, tn.tid as category_id, nrel.cnid as img_id
			FROM ' . $this->oldDB . '.node n
				LEFT JOIN ' . $this->oldDB . '.node_revisions nr ON n.nid=nr.nid
				LEFT JOIN ' . $this->oldDB . '.term_node tn ON n.nid=tn.nid
				LEFT JOIN ' . $this->oldDB . '.node_relation nrel ON n.nid=nrel.pnid AND nrel.relation = "node_to_image"
			WHERE ' . (!empty($importedIds) ? 'n.nid NOT IN (' . implode(',', $importedIds) . ') AND ' : '') . ' n.type= "story"
			ORDER BY rand()
			LIMIT 500
		';
		$res = $this->db->array_query_assoc($sql, 'id');
		$msg[] = count($res);
		$ids = array_keys($res);

		// get uri
		$sql2 = '
			SELECT SUBSTRING_INDEX(dst,\'/\',-1) AS uri, SUBSTRING_INDEX(src,\'/\',-1) AS id FROM ' . $this->oldDB . '.url_alias
			WHERE src REGEXP "node/('.implode('|',$ids).'){1}$"
		';
		$res2 = $this->db->array_query_assoc($sql2, 'id');

		// get meta data
		$sql3 = '
			SELECT keywords, description, SUBSTRING_INDEX(link,\'/\',-1) AS id FROM ' . $this->oldDB . '.metatags_all
			WHERE link REGEXP "node/('.implode('|',$ids).'){1}$"
		';
		$res3 = $this->db->array_query_assoc($sql3, 'id');

		// get tags - no tags found for articles
		/*
		$sql4 = '
			SELECT tn.nid, tn.tid, td.name as tag
			FROM ' . $this->oldDB . '.term_node tn
			INNER JOIN ' . $this->oldDB . '.term_data td ON tn.tid=td.tid
			WHERE td.vid = 5 AND tn.nid IN ('.implode(',',$ids).')
		';
		$res4 = $this->db->array_query_assoc($sql4);
		$tags = array();
		foreach ($res4 as $r) {
			$tags[$r['nid']][] = $r['name'];
		}
		print_r($res4);exit;
		*/

		// images
		$idsImg = array();
		foreach ($res as $r) {
			if ($r['img_id']) $idsImg[] = $r['img_id'];
		}
		$sql5 = '
			SELECT n.nid, nr.body
			FROM ' . $this->oldDB . '.node n
			INNER JOIN ' . $this->oldDB . '.node_revisions nr ON n.nid=nr.nid
			WHERE n.nid IN ('.implode(',',$idsImg).')';
		$res5 = $this->db->array_query_assoc($sql5, 'nid');


		// content, content_html, summary, attachments

		$data = array();
		foreach ($res as $articleId => $r) {

			$img = '';
			$imgAlt = '';

			// --- leading image begin
			if (isset($res5[$r['img_id']])) {
				$imgData = unserialize($res5[$r['img_id']]['body']);
				$filepath = !empty($imgData->filepath) ? $imgData->filepath : '';
				$imgAlt = !empty($imgData->description) ? $imgData->description : '';

				if ($filepath) {
					$imgUrl = $this->homeUrl.'/'.$filepath;
					$die = true;

					// download and save leading image, update img field
					$hideArticle = 0;
					if (strpos($filepath, '.')) {
						$msg[] = 'Downloading leading image: ' . $this->homeUrl.'/'.$filepath;
						$img = $this->downloadAndSaveLeadingImage($filepath);
						if($img !== '') {
							$msg[] = 'Leading image downloaded: ' . $img;
						} elseif(0) {
							$msg[] = '<span style="color:#F00;">Leading image download failed. Aborting.</span>';
							print implode('<br />', $msg) . '<hr />';
							exit;
						}
					} else {
						$msg[] = '<span style="color:#F00;">Incorrect leading image.</span>';
						//$hideArticle = 1;
					}
				}
			}
			// --- leading image end

			// decompile html. save attachments
			$content = html_entity_decode($parser->parse($r['body']), ENT_QUOTES, 'UTF-8');

			// generate summary from compiled content
			$summary = $txt->excerpt($txt->strip_tags(htmlspecialchars_decode($content)), 125);

			$ins = array(
				'id' => $r['id'],
				'article_type' => 1,
				'title' => $r['title'],
				'uri' => (isset($res2[$r['id']])) ? str_replace('.htm','',$res2[$r['id']]['uri']) : '',
				'meta_keywords' => (isset($res3[$r['id']])) ? strip_tags($res3[$r['id']]['keywords']) : '',
				'meta_description' => (isset($res3[$r['id']])) ? strip_tags(htmlspecialchars_decode($res3[$r['id']]['description'])) : '',
				'is_hidden' => $r['status'] ? 0 : 1,
				'created' => $r['created'],
				'published' => $r['created'],
				'updated' => $r['changed'],

				'category_id' => $r['category_id'],

				'img' => $img,
				'img_alt' => $imgAlt,

				'summary' => $summary,
				'content' => $content,
				'content_html' => $r['body'],
				
			);
			//$data[$r['id']] = $ins;
			//print_r($ins);
			

			//if (isset($die)) {
				$this->db->insert($ins, 'articles');
				//$articleId = $r['id'];

				$msg[] = 'article inserted. new id: ' . $articleId;

				// save html attachments and replace attachment ids in text
				if ($articleId && !empty($parser->htmlObjects)) {
					$msg[] = 'Saving html attachments...';

					foreach($parser->htmlObjects as $id => $comment) {

						// insert attachment
						$ins = array();
						$ins['user_id'] = $user->get_user_id();
						$ins['parent_id'] = $articleId;
						$ins['content_type'] = 2;
						$ins['created'] = $ins['updated'] = time();
						$ins['comment'] = $comment;
						$prevId = $id;
						$attId = $this->db->insert($ins, 'articles_attachments', 'id');

						$msg[] = 'Attachment saved. att id: ' . $attId;

						// fix attachment ids
						// replace {id:xxx} in news contents
						$content = str_ireplace('{id:' . $prevId . '}', '{id:' . $attId . '}', $content);
						$upd = array();
						$upd['content'] = $content;
						list(,$upd['content_html']) = $rtf->parseText($articleId, $content);
						$this->db->update($upd, 'articles', array('id' => $articleId));

						/*
						print '<hr />';
						print_r($ins);
						print '<hr />';
						print 'att id: ' . $attId;
						print '<hr />';
						print 'Content: ' . $content;
						print '<hr />';
						print_r($upd);
						exit;
						*/
					}
				//}

				// save image attachments and replace attachment ids in text
				if ($articleId && !empty($parser->images)) {
					$msg[] = 'Saving image attachments...';

					foreach($parser->images as $id => $data) {
						$msg[] = 'Saving attachment nr: ' . $id;
						$imageSaved = false;


						// insert attachment
						$ins = array();
						$ins['user_id'] = $user->get_user_id();
						$ins['parent_id'] = $articleId;
						$ins['content_type'] = 0;
						$ins['created'] = $ins['updated'] = time();
						$ins['comment'] = isset($data[1]) ? $data[1] : '';
						$prevId = $id;

						// download and save file
						$imgUrl = !empty($data[0]) ? $data[0] : '';
						if ($imgUrl !== '') {
							if (stripos($imgUrl, 'http://') !== 0) {
								$imgUrl = trim($homeUrl, '/') . '/' . rtrim($imgUrl, '/');
							}

							$files = $this->downloadAndSaveAttachment($imgUrl);
							if (is_array($files)) {
								list($ins['file'],$ins['thumbnail']) = $files;
								$imageSaved = true;
							}
						}

						if ($imageSaved) {
							$msg[] = 'Attachment downloaded.';

							$attId = $this->db->insert($ins, 'articles_attachments', 'id');

							// fix attachment ids
							// replace {id:xxx} in news contents
							$content = str_ireplace('{id:' . $prevId . '}', '{id:' . $attId . '}', $content);
							$upd = array();
							$upd['content'] = $content;
							list(,$upd['content_html']) = $rtf->parseText($articleId, $content);
							$this->db->update($upd, 'articles', array('id' => $articleId));
						} else {
							$msg[] = '<span style="color: #F00;">Attachment download failed...</span>';
						}

						/*
						print '<hr />';
						print_r($ins);
						print '<hr />';
						print 'att id: ' . $attId;
						print '<hr />';
						print 'Content: ' . $content;
						print '<hr />';
						print_r($upd);
						exit;
						*/
					}
				}
			} 
		}

		return '<pre>'.print_r($msg, true).'</pre>';
	}


	private function importTags()
	{
		$this->db->query('TRUNCATE TABLE articles_tags');
		$sql = '
			SELECT td.tid as id, td.name, td.description
			FROM ' . $this->oldDB . '.term_data td
				INNER JOIN ' . $this->oldDB . '.vocabulary_node_types vnt ON td.vid=vnt.vid
				INNER JOIN ' . $this->oldDB . '.vocabulary v ON vnt.vid=v.vid
			WHERE 
				vnt.type = "blog"
		';
		$res = $this->db->array_query_assoc($sql, 'id');

		// get uri
		$sql2 = '
			SELECT SUBSTRING_INDEX(dst,\'/\',-1) AS uri, SUBSTRING_INDEX(src,\'/\',-1) AS id FROM ' . $this->oldDB . '.url_alias
			WHERE src REGEXP "taxonomy/term/('.implode('|',array_keys($res)).'){1}$"
		';
		$res2 = $this->db->array_query_assoc($sql2, 'id');

		$ids = array();
		foreach ($res as $id => $r) {
			$ins = array(
				'id' => $r['id'],
				'name' => $r['name'],
				'description' => $r['description'],
				'uri' => (isset($res2[$id])) ? $res2[$id]['uri'] : ''
			);
			$ids[] = $this->db->insert($ins, 'articles_tags');
		}

		return '<pre>'.print_r(count($ids), true).'</pre>';
	}


	private function importCategories()
	{
		$this->db->query('TRUNCATE TABLE articles_categories');
		$sql = '
			SELECT td.tid as id, td.name, td.description
			FROM ' . $this->oldDB . '.term_data td
				INNER JOIN ' . $this->oldDB . '.vocabulary_node_types vnt ON td.vid=vnt.vid
				INNER JOIN ' . $this->oldDB . '.vocabulary v ON vnt.vid=v.vid
			WHERE 
				vnt.type = "story"
		';
		$res = $this->db->array_query_assoc($sql, 'id');

		// get uri
		$sql2 = '
			SELECT dst, SUBSTRING_INDEX(src,\'/\',-1) AS id FROM ' . $this->oldDB . '.url_alias
			WHERE src REGEXP "taxonomy/term/('.implode('|',array_keys($res)).'){1}$"
		';
		$res2 = $this->db->array_query_assoc($sql2, 'id');

		// get meta data
		$sql3 = '
			SELECT keywords, description, SUBSTRING_INDEX(link,\'/\',-1) AS id FROM ' . $this->oldDB . '.metatags_all
			WHERE link REGEXP "taxonomy/term/('.implode('|',array_keys($res)).'){1}$"
		';
		$res3 = $this->db->array_query_assoc($sql3, 'id');

		$ids = array();
		foreach ($res as $id => $r) {
			$ins = array(
				'id' => $r['id'],
				'category_type' => 1,
				'title' => $r['name'],
				'description' => $r['description'],
				'uri' => (isset($res2[$id])) ? $res2[$id]['dst'] : '',
				'meta_keywords' => (isset($res3[$id])) ? $res3[$id]['keywords'] : '',
				'meta_description' => (isset($res3[$id])) ? $res3[$id]['description'] : '',
			);
			//print_r($ins);
			$ids[] = $this->db->insert($ins, 'articles_categories');
		}

		return '<pre>'.print_r($ids, true).'</pre>';
	}


	private function downloadAndSaveLeadingImage($imgPath = '')
	{
		if (!$imgPath) return '';
		$imgUrl = $this->homeUrl . '/' . $imgPath;

		if (is_dev()) {
			// http
			$ch = curl_init ($imgUrl);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_TIMEOUT, 25);
			$rawdata = curl_exec($ch);
			if (curl_errno($ch)) {
				return '';
			}
			curl_close($ch);
		} else {
			$imgUrl = str_replace($this->homeUrl . '/', '../httpdocs/', $imgUrl);
			$rawdata = file_get_contents($imgUrl);
		}

		// get true image type
		$fileExt = '';
		$imageType = exif_imagetype($imgUrl);
		switch ($imageType) {
			case IMAGETYPE_GIF: // 1
				$fileExt = 'gif';
				break;
			case IMAGETYPE_JPEG: // 2
				$fileExt = 'jpg';
				break;
			case IMAGETYPE_PNG: // 3
				$fileExt = 'png';
				break;
			default:
				return '';
		}

		//if (!in_array($fileExt = substr($imgUrl, (int)strrpos($imgUrl, '.') + 1), array('jpg', 'jpeg', 'png', 'gif'))) {
		//	return '';
		//}

		$fileNameFullOrig = uniqid('');
		$fileNameFull = str_shuffle($fileNameFullOrig);
		while (!is_numeric(substr($fileNameFull, 0, 4))) {
			$fileNameFull = str_shuffle($fileNameFullOrig);
		}
		$fileNameFull = substr_replace($fileNameFull, 'pn', 0, 2);


		$fileDir = $this->leadingImgDir . substr($fileNameFull, 0, 4);
		$fileNameBase  = substr($fileNameFull, 4);

		// save original image
		if (!file_exists($fileDir)) {
			$oldumask = umask(0);
			mkdir($fileDir, 0777);
			mkdir($fileDir . '/orig', 0777);
			umask($oldumask);
		}

		$filePathOrig = $fileDir . '/orig/' . $fileNameBase . '.' . $fileExt;

		// if file exists - use original unique filename
		if (file_exists($filePathOrig)) {
			$fileDir = $this->leadingImgDir . substr($fileNameFullOrig, 0, 4);
			$fileNameBase  = substr($fileNameFullOrig, 4);

			// save original image
			if (!file_exists($fileDir)) {
				$oldumask = umask(0);
				mkdir($fileDir, 0777);
				mkdir($fileDir . '/orig', 0777);
				umask($oldumask);
			}

			$filePathOrig = $fileDir . '/orig/' . $fileNameBase . '.' . $fileExt;
		}

		$fp = fopen($filePathOrig, 'x');
		fwrite($fp, $rawdata);
		fclose($fp);

		$newPhoto = '';
		$f = new moon_file;
		if ($f->is_file($filePathOrig)) {
			$newPhoto = $fileNameFull . '.' . $fileExt;

			$img = &moon::shared('img');

			$resizeOrig = true;
			$wh = $f->file_wh(); // 120x66
			if ($wh!=='') {
				list($x,$y)=explode('x',$wh);
				if ($x > 800 || $y > 800) {
					//pernelyg dideli img susimazinam bent iki 800x800
					$img->resize($f,$filePathOrig,800,800);
				}

				// if width is less than required - don't resize
				if ($x < $this->leadingImgWidth) {
					$resizeOrig = false;
					$f->copy($fileDir . '/' . $fileNameBase . '.' . $fileExt);
				}
			}

			//$fileDir . '/' . $fileNameBase . '.' . $fileExt;
			//pagaminam thumbnailus is paveiksliuko

			if ($resizeOrig) {
				$img->resize_exact($f, $fileDir . '/' . $fileNameBase . '.' . $fileExt, $this->leadingImgWidth, $this->leadingImgHeight);
			}

			if ($f->is_file($fileDir . '/' . $fileNameBase . '.' . $fileExt)) {
				$img->resize_exact($f, $fileDir . '/thumb_' . $fileNameBase . '.' . $fileExt, 120,80);
				$img->resize_exact($f, $fileDir . '/mid_' . $fileNameBase . '.' . $fileExt, 223,147);
			}

		}

		return $newPhoto;
	}


	private function downloadAndSaveAttachment($imgUrl = '')
	{

		if (is_dev()) {
			$ch = curl_init ($imgUrl);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			$rawdata = curl_exec($ch);
			if (curl_errno($ch)) {
				return FALSE;
			}
			curl_close($ch);
		} else {
			$imgUrl = str_replace($this->homeUrl . '/', '../httpdocs/', $imgUrl);
			$rawdata = file_get_contents($imgUrl);
		}


		// get true image type
		$fileExt = '';
		$imageType = exif_imagetype($imgUrl);
		switch ($imageType) {
			case IMAGETYPE_GIF: // 1
				$fileExt = 'gif';
				break;
			case IMAGETYPE_JPEG: // 2
				$fileExt = 'jpg';
				break;
			case IMAGETYPE_PNG: // 3
				$fileExt = 'png';
				break;
			default:
				return false;
		}

		//if (!in_array($fileExt = substr($imgUrl, (int)strrpos($imgUrl, '.') + 1), array('jpg', 'jpeg', 'png', 'gif'))) {
		//	return false;
		//}

		$fileDir = $this->attachmentsDir;
		$fileName = uniqid('');
		$filePath = $fileDir . $fileName . '.' . $fileExt;

		$fp = fopen($filePath, 'x');
		fwrite($fp, $rawdata);
		fclose($fp);

		$newO = $newT = '';
		$f = new moon_file;
		if ($f->is_file($filePath)) {
			$newO = $f->file_info();
			$img = &moon::shared('img');
			$fileT = $fileDir . $fileName . '_.' . $fileExt;
			if ($img->resize($f, $fileT, 530, 400) && $f->is_file($fileT)) $newT = $f->file_info();
		}
		if ($newO==='') $newO=null;
		if ($newT==='') $newT=null;
		return array($newO,$newT);
	}


	private function recompile()
	{
		if (is_object($rtf = $this->object('rtf'))) {
			$rtf->setInstance($this->get_var('rtf'));

			$msgs = array();
			$sql = 'SELECT id, content FROM articles';
			//$sql = 'SELECT id, content FROM articles where content REGEXP \'[10|[02-9AKQJtx]{1}\[[h|s|c|d|x]{1}\]\'';
			$res = $this->db->array_query_assoc($sql);
			foreach ($res as $r) {
				list(,$contentHtml) = $rtf->parseText($r['id'], $r['content']);
				//print $contentHtml;
				$this->db->update(array('content_html' => $contentHtml), 'articles', array('id' => $r['id']));
				$msgs[] = 'Id: ' . $r['id'] . ' - recompiled';
			}

		} else {
			$msgs[] = 'Technical error';
		}

		print implode('<br />', $msgs);
	}

}

?>