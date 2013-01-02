<?php
class import_news extends moon_com {

function events($event, $par)
{
	switch ($event) {
		/*
		case 'import-news-cron':
			$cronMsg = '';

			$data = $this->getData();
			if (is_array($data)) {
				$cronMsg .= $this->insertNews($data);
			} else {
				$cronMsg .= $data;
			}
			$page = &moon::page();
			$page->set_local('cron', $cronMsg);
			return;
		*/
		case 'import-news-by-url':
			$cronMsg = '';

			$url = isset($par[0]) ? $par[0] : '';
			$data = $this->getDataByUrl($url);
			if (is_array($data)) {
				$cronMsg .= $this->insertNews($data);
			} else {
				$cronMsg .= $data;
			}
			return;
		default:
			break;
	}
	$this->use_page('Common');
}

function getData()
{
	$id = 'com';
	$s = '';

	$par1 = $this->getLastImportedNewsTime();
	$par2 = 'includeturbo';

	$answer = 'response';
	$ok = callPnEvent($id, 'articles.export#get-news', array($par1, $par2), $answer,FALSE);
	if (!$ok) {
		$s .= $id . ' : <span style="color:red">Error: ' . $answer . "</span><br/>\n";
	} elseif(!$answer) {
		$s .= $id . " : <span style=\"color:red\">No data imported</span><br/>\n";
	} else {
		$s = unserialize($answer);
	}

	return $s;
}

function getDataByUrl($url = '')
{
	$id = 'com';
	$s = '';
	if ($url) {

		$par1 = $url;

		$answer = 'response';
		$ok = callPnEvent($id, 'articles.export#get-news-by-url', array($par1), $answer,FALSE);
		if (!$ok) {
			$s .= $id . ' : <span style="color:red">Error: ' . $answer . "</span><br/>\n";
		} elseif(!$answer) {
			$s .= $id . " : <span style=\"color:red\">No data imported</span><br/>\n";
		} else {
			$s = unserialize($answer);
		}
	}
	return $s;
}

function insertNews($newsData)
{
	set_time_limit(180);
	$msg = '<b>News import results</b><br />';

	$newsCnt = 0;
	if (empty($newsData)) {
		$newsItems = array();
	} else {
		$newsItems = $newsData;
	}

	$msg .= 'Importing news...<br />';
	foreach ($newsItems as $news) {
		if (empty($news['fields'])) continue;

		$newsCnt++;

		// insert news item
		$fields = $news['fields'];
		$masterId = $fields['id'];

		# check if article is imported
		$is = $this->db->single_query('SELECT 1 FROM ' . $this->table('Articles') . ' WHERE master_id = ' . intval($masterId));
		if (!empty($is)) continue;

		if (isset($fields['id'])) unset($fields['id']);
		if (isset($fields['category_id'])) unset($fields['category_id']);
		if (isset($fields['authors'])) unset($fields['authors']);
		if (isset($fields['turbo'])) unset($fields['turbo']);
		if (isset($fields['attachments'])) unset($fields['attachments']);
		if (isset($fields['pn_promo'])) unset($fields['pn_promo']);
		if (isset($fields['ad_room_id'])) unset($fields['ad_room_id']);

		$ins = $fields;
		$ins['master_id'] = $masterId;
		$ins['created'] = $ins['updated'];
		$ins['is_hidden'] = 1;
		$ins['is_imported'] = 1;

		$newsContent = $fields['content'];
		$newsId = $this->db->insert($ins, $this->table('Articles'), 'id');
		moon::page()->set_local('lastImportedNewsId', $newsId);

		// download and save images
		$fileName = $fileNameFull = $ins['img'];

		if ($fileName) {
			// leading image
			$this->getLeadingImage($fileName);
			// thumbnail
			$this->getLeadingImage($fileName, 'thumb_');
			// original
			$this->getLeadingImage($fileName, 'orig/');

			$path = _W_DIR_ . 'articles/img/';
			$subdir_ = substr($fileName, 0, 4);
			$fileName = substr($fileName, 4);
			$filePathOrig = $path . $subdir_ . '/orig/' . $fileName;

			$f = new moon_file;
			if ($f->is_file($filePathOrig)) {
				$img = &moon::shared('img');
				$img->resize_exact($f, $path . $subdir_ . '/mid_' . $fileName, 223,147);
			}
		}

		// insert news attachments
		if (!empty($news['attachments'])) {

			$newsAtt = $news['attachments'];

			foreach ($newsAtt as $att) {
				// insert attachment
				$ins = $att;
				$ins['parent_id'] = $newsId;
				$prevId = $ins['id'];
				if (isset($ins['id'])) unset($ins['id']);
				$attId = $this->db->insert($ins, $this->table('ArticlesAttachments'), 'id');

				// fix attachment ids
				// replace {id:xxx} in news contents
				$newsContent = str_ireplace('{id:' . $prevId . '}', '{id:' . $attId . '}', $newsContent);
				$upd = array();
				$upd['content'] = $newsContent;
				$this->db->update($upd, $this->table('Articles'), array('id' => $newsId));

				if ($att['content_type'] == 0) {
					$chunks = explode('|', $att['file']);
					if (!empty($chunks[3])) {
						$fileName = $chunks[3];
						$this->getAttachmentImage($fileName);
					}
					$chunks = explode('|', $att['thumbnail']);
					if (!empty($chunks[3])) {
						$fileName = $chunks[3];
						$this->getAttachmentImage($fileName);
					}
				}
			}
		}

		// insert turbos
		if (!empty($news['turbo'])) {
			$turbos = $news['turbo'];
			foreach ($turbos as $turbo) {
				if (empty($turbo['fields'])) continue;

				// insert turbo item
				$ins = $turbo['fields'];
				$ins['parent_id'] = $newsId;
				if (isset($ins['id'])) unset($ins['id']);
				if (isset($ins['attachments'])) unset($ins['attachments']);

				$turboContent = $ins['content'];
				$turboId = $this->db->insert($ins, $this->table('Turbo'), 'id');

				if (!empty($turbo['attachments'])) {

					$turboAtt = $turbo['attachments'];
					foreach ($turboAtt as $att) {
						// insert attachment
						$ins = $att;
						$ins['parent_id'] = $turboId;
						if (isset($ins['id'])) unset($ins['id']);
						$attId = $this->db->insert($ins, $this->table('ArticlesTurboAttachments'), 'id');

						// fix attachment ids
						// replace {id:xxx} in news contents
						$turboContent = preg_replace('/{id:[0-9]+}/U', '{id:' . $attId . '}', $turboContent);
						$upd = array();
						$upd['content'] = $turboContent;
						$this->db->update($upd, $this->table('Turbo'), array('id' => $turboId));

						// download and save images
						if ($att['content_type'] == 0) {
							$chunks = explode('|', $att['file']);
							if (!empty($chunks[3])) {
								$fileName = $chunks[3];
								$this->getAttachmentImage($fileName);
							}
							$chunks = explode('|', $att['thumbnail']);
							if (!empty($chunks[3])) {
								$fileName = $chunks[3];
								$this->getAttachmentImage($fileName);
							}
						}
					}
				}
			}
		}
	}
	return $msg .= '<em>' . $newsCnt . ' news imported</em><br />';
}

function getLastImportedNewsTime()
{
	$sql = 'SELECT max(created) as lastCreated
		FROM ' . $this->table('Articles') . '
		WHERE master_id > 0 OR is_imported = 1';
	$result = $this->db->single_query_assoc($sql);

	//return 1255520137;
	return (isset($result['lastCreated']) AND $result['lastCreated'] > 0) ? $result['lastCreated'] : time() - 3600 * 24;
}

function getLeadingImage($fileName, $prefix = '')
{
	$url = 'http://pnimg.net/w/articles/0/' . substr_replace($fileName, '/', 3, 0);
	/*$url = !is_dev()
		? 'http://www.pokernews.com/w/articles/'
		: 'http://www.pokernews.dev/w/articles/';*/
	$path = _W_DIR_ . 'articles/img/';

	$subdir_ = substr($fileName, 0, 4);
	$fileName = $prefix . substr($fileName, 4);

	//$url .= $subdir_ . '/' . $fileName;
	$imgFullPath = $path . $subdir_ . '/' . $fileName;

	$ch = curl_init ($url);
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

	if(file_exists($imgFullPath)){
		unlink($imgFullPath);
	}

	$oldumask = umask(0);
	if (!file_exists($path . $subdir_)) {
		mkdir($path . $subdir_, 0777);
	}
	if (!file_exists($path . $subdir_ . '/orig')) {
		mkdir($path . $subdir_ . '/orig', 0777);
	}
	umask($oldumask);

	$fp = fopen($imgFullPath, 'x');
	fwrite($fp, $rawdata);
	fclose($fp);
}

function getAttachmentImage($fileName)
{
	$url = !is_dev()
		? 'http://www.pokernews.com/files/cnt/'
		: 'http://www.pokernews.dev/files/cnt/';
	$path = _W_DIR_ . 'articles/att/';

	$url .= $fileName;
	$imgFullPath = $path . $fileName;

	$ch = curl_init ($url);
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

	if(file_exists($imgFullPath)){
		unlink($imgFullPath);
	}

	$fp = fopen($imgFullPath, 'x');
	fwrite($fp, $rawdata);
	fclose($fp);
}

}
?>