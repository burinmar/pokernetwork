<?php

class blogs_import extends moon_com 
{
	/*

	IMPORT INSTRUCTIONS
	
	nurodyt sena db

	1. $this->oldDB = 'pokernetwork_old';

	paleisti importus

	3. http://www.pokernetwork.dev/adm/articles-blogs_import/blogs
		reikes kelis kartus paleisti, importuoja dalimis

	4. moon/modules/articles.config.cfg.php 'var.suffixStartId' - nurodyt select max(id) + 1 from articles
	
	*/

	function onload()
	{
		$this->oldDB = 'pokerne2043';

		$this->homeUrl = 'http://www.pokernetwork.com';
		$this->siteDomain = 'www.pokernetwork.com';

		$this->leadingImgWidth = 460;
		$this->leadingImgHeight = 305;

		// $this->attachmentsDir = _W_DIR_ . 'articles/att/';
		// $this->leadingImgDir = _W_DIR_ . 'articles/img/';
	}


	function events($event, $par)
	{
		$msg = 'Blogs import<hr />' . $event . '<br />';

		switch($event)
		{
			case 'blogs':
				$msg = $this->importBlogs();
				break;
		}

		print $msg;
		moon_close();
		exit;
	}


	private function importBlogs()
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

		$this->db->query('TRUNCATE blog_attachments');
		$this->db->query('TRUNCATE blog_posts');
		$this->db->query('TRUNCATE blog_posts_bodies');
		$this->db->query('TRUNCATE blog_comments');

		$sql1 = 'SELECT id FROM blog_posts';
		$res1 = $this->db->array_query_assoc($sql1, 'id');
		$importedIds = array_keys($res1);

		// get main data
		$sql = '
			SELECT n.nid as id, n.title, n.status, n.created, n.changed, n.uid user_id, nr.body, nr.teaser, nr.format, nrel.cnid as img_id
			FROM ' . $this->oldDB . '.node n
				LEFT JOIN ' . $this->oldDB . '.node_revisions nr ON n.nid=nr.nid
				LEFT JOIN ' . $this->oldDB . '.node_relation nrel ON n.nid=nrel.pnid AND nrel.relation = "node_to_image"
			WHERE ' . (!empty($importedIds) ? 'n.nid NOT IN (' . implode(',', $importedIds) . ') AND ' : '') . ' n.type= "blog"
			--	AND n.nid IN(16266)
			GROUP BY n.nid
			ORDER BY rand()
			LIMIT 500
		';
		$res = $this->db->array_query_assoc($sql, 'id');
		$msg[] = count($res);
		$ids = array_keys($res);

		// get uri
		$idsString = array();
		foreach ($ids as $id) {
			$idsString[] = '"node/' . $id . '"';
		}
		$sql2 = '
			SELECT SUBSTRING_INDEX(dst,\'/\',-1) AS uri, SUBSTRING_INDEX(src,\'/\',-1) AS id FROM ' . $this->oldDB . '.url_alias
			WHERE src IN (' . implode(',', $idsString) . ')
		';
		$res2 = $this->db->array_query_assoc($sql2, 'id');

		// get meta data
		$sql3 = '
			SELECT keywords, description, SUBSTRING_INDEX(link,\'/\',-1) AS id FROM ' . $this->oldDB . '.metatags_all
			WHERE link IN (' . implode(',', $idsString) . ')
		';
		$res3 = $this->db->array_query_assoc($sql3, 'id');

		$sql4 = '
			SELECT cid, nid, uid, comment, hostname, timestamp FROM ' . $this->oldDB . '.comments
			WHERE nid IN (' . implode(',', $ids) . ')
		';
		$res4 = array();
		foreach ($this->db->array_query_assoc($sql4) as $comment) {
			if (!isset($res4[$comment['nid']]))
				$res4[$comment['nid']] = array(
					'comments' => array(),
					'count' => 0,
					'last' => 0,
				);
			$comment['comment'] = str_replace(base64_decode('wpI='), "'", $comment['comment']);
			$res4[$comment['nid']]['comments'][] = $comment;
			$res4[$comment['nid']]['count']++;
			$res4[$comment['nid']]['last'] = max($res4[$comment['nid']]['last'], $comment['timestamp']);
		}

		// content, content_html, summary, attachments
		$data = array();
		foreach ($res as $articleId => $r) {
			$img = '';
			$imgAlt = '';

			// decompile html. save attachments

			$content = $r['body'];
			$content = preg_replace('~<xml>.*?</xml>~s', '', $content);
			$content = preg_replace('~<style>.*?</style>~s', '', $content);
			$content = preg_replace('~<!--\[if gte mso [0-9]+\]>~s', '', $content);
			$content = preg_replace('~<h[345][^>]*>~s', '', $content);
			$content = preg_replace('~<[/]?st1:[^>]*>~s', '', $content);
			$content = str_replace(array('<wbr></wbr>', '</h3>', '</h4>', '</h5>', '<meta charset="utf-8">', '</meta>'), '', $content);
			$content = html_entity_decode($parser->parse($content), ENT_QUOTES, 'UTF-8');

			// generate summary from compiled content
			$summary = $txt->excerpt($txt->strip_tags(htmlspecialchars_decode($content)), 125);

			$comments = isset($res4[$articleId])
				? $res4[$articleId]
				: array(
					'comments' => array(),
					'count' => 0,
					'last' => 0,
				);

			$ptags = (isset($res3[$r['id']])) 
					? strip_tags($res3[$r['id']]['keywords']) 
					: '';
			$ptags = explode(',', $ptags);
			foreach ($ptags as $key => $value) {
				$ptags[$key] = make_uri(trim($value));
			}

			$ins = array(
				'id' => $r['id'],
				'user_id' => $r['user_id'],
				'created_on' => $r['created'],
				'updated_on' => $r['changed'],

				'title' => $r['title'],
				'body' => '', // will populate later $r['body'],
				'body_short' => $summary,
				'uri' => (isset($res2[$r['id']])) 
					? str_replace('.htm','',$res2[$r['id']]['uri']) 
					: md5(uniqid()),
				'tags' => implode(',', $ptags),

				'comm_count' => $comments['count'],
				'comm_last' => $comments['last'],
				'comm_options' => 0, 
				'disable_comments' => 0,
				'views_count' => 0,
				'rating' => 0,

				'is_hidden' => $r['status'] ? 0 : 1,
				
				// 'meta_description' => (isset($res3[$r['id']])) ? strip_tags(htmlspecialchars_decode($res3[$r['id']]['description'])) : '',
			);
			if (!$this->db->insert($ins, 'blog_posts', 'id')) {
				$msg[] = mysql_error();
				continue;
			}

			$msg[] = 'blog post inserted. new id: ' . $articleId;

			$ins = array(
				'post_id' => $r['id'],
				'compiled' => 1,
				'body' => $content
			);
			$this->db->insert($ins, 'blog_posts_bodies');

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
					$attId = $this->db->insert($ins, 'blog_attachments', 'id');

					$msg[] = 'Attachment saved. att id: ' . $attId;

					// fix attachment ids
					// replace {id:xxx} in news contents
					$content = str_ireplace('{id:' . $prevId . '}', '{id:' . $attId . '}', $content);
				}

				$upd = array();
				$upd['body'] = $content;
				$this->db->update($upd, 'blog_posts_bodies', array('post_id' => $articleId));
			}
			// compile html
			$upd = array();
			list(,$upd['body']) = $rtf->parseText($articleId, $content);
			$this->db->update($upd, 'blog_posts', array('id' => $articleId));

			foreach ($comments['comments'] as $comment) {
				$content = html_entity_decode($parser->parse($comment['comment']), ENT_QUOTES, 'UTF-8');
				$ins = array(
					'id' => $comment['cid'],
					'obj_id' => $articleId,
					'user_id' => $comment['uid'],
					'comment' => $content,
					'created' => $comment['timestamp'],
					'user_ip' => $comment['hostname'],
				);

				$commentId = $this->db->insert($ins, 'blog_comments', 'id');
				$msg[] = 'Comment saved. id: ' . $commentId;
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
}
