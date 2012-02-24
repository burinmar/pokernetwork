<?php
require('lib/echove.php');
class video_import extends moon_com {
	
	function onload()
	{
		$this->tokenRead = $this->get_var('tokenRead');
		$this->tokenWrite = $this->get_var('tokenWrite');
		$this->bc = new Echove($this->tokenRead, $this->tokenWrite);
	}
	function events($event, $par)
	{
		switch ($event) {
			case 'import-bc-videos':
				$page = &moon::page();
				if ($page->was_refresh()) {
					break;
				}
				
				$cron = FALSE;
				$msg = $this->bcImportVideos($cron);
				
				if ($msg === FALSE) {
					return;
				} else {
					$this->set_var('importMsg', $msg);
				}
				
				break;
			case 'import-bc-videos-cron':
				$msg = $this->bcImportVideos();
				return;
				break;
			case 'reload-image':
				$id = isset($par[0]) ? sprintf("%.0f", $par[0]) : 0;
				$page = &moon::page();
				if ($id) {
					$msg = $this->bcReloadVideoThumbnail($id);
					
					$page->alert($msg, 'ok');
					$this->redirect('video.video#edit', $id);
				} else {
					$page->alert('Unable to reload image. Technical error');
					$this->redirect('video.video');
				}
				
				break;
			case 'reimport-data':
				$id = isset($par[0]) ? sprintf("%.0f", $par[0]) : 0;
				$page = &moon::page();
				if ($id) {
					$msg = $this->bcReimportVideoData($id);
					
					$page->alert($msg, 'ok');
					$this->redirect('video.video#edit', $id);
				} else {
					$page->alert('Unable to reimport data. Technical error');
					$this->redirect('video.video');
				}
				break;
			default:
				break;
		}
		$this->use_page('Common');
	}
	function properties()
	{
		return array('importMsg' => '');
	}
	function main($vars)
	{
		$page = &moon::page();
		$win = &moon::shared('admin');
		$win->active($this->my('fullname'));
		$title = $win->current_info('title');
		$page->title($title);
		
		$tpl = &$this->load_template();
		
		$main = array();
		$main['pageTitle'] = $title;
		$main['goImport'] = $this->my('fullname').'#import-bc-videos';
		$main['importMsg'] = $vars['importMsg'];
		$main['refresh'] = $page->refresh_field();
		return $tpl->parse('main', $main);
	}
	// -------------------- BRIGHTCOVE CALLS --------------------
	/**
	 * Gets videos by ids from brightcove
	 * @param array $ids brightcove video ids
	 * @return object an ItemCollection that contains the video objects corresponding to the video ids given
	 */
	function bcGetVideosByIds($ids)
	{
		$playerId = $this->get_var('playerId');
		$params = array(
			'video_ids' => implode(',', $ids)
		);
		
		$videos = $this->bc->find('find_videos_by_ids', $params);
		return $videos;
	}
	/**
	 * Gets video ids by tags from brightcove, all tags included
	 * @param array $tags brightcove video tags
	 * @return array $videoIds
	 */
	function bcGetVideoIdsByTags($tags, $tagsOptions = 'and_tags')
	{
		$playerId = $this->get_var('playerId');
		$videoIds = array();
		
		$params = array(
			$tagsOptions => implode(',', $tags),
			'fields' => 'id',
		);
		
		$videos = $this->bc->find('find_videos_by_tags', $params);
		foreach ($videos->items as $item) {
			$videoIds[] = $item->id;
		}
		
		$pageSize = $videos->page_size;
		$pageNumber = $videos->page_number;
		$totalCount = $videos->total_count;
		$totalPages = floor($totalCount/$pageSize);
		
		for ($i=1;$i<=$totalPages;$i++) {
			
			$params = array(
				$tagsOptions => implode(',', $tags),
				'page_number' => $i,
				'fields' => 'id',
			);
			$videos = $this->bc->find('find_videos_by_tags', $params);
			foreach ($videos->items as $item) {
				$videoIds[] = $item->id;
			}
			
		}
		
		return $videoIds;
	}
	/**
	 * Gets all the playlists assigned to current site player
	 * @return object The collection of playlists requested
	 */
	function bcGetVideoPlaylistsData()
	{
		$playerId = $this->get_var('playerId');
		$params = array(
			'player_id' => $playerId,
			'fields' => 'id,referenceId,name,shortDescription,filterTags,videoIds',
			'get_item_count' => 'false'
		);
		return $playlists = $this->bc->find('find_playlists_for_player_id', $params);
	}
	/**
	 * Gets video data by id from brightcove
	 * @param int $id brightcove video id
	 * @return object an ItemCollection that contains the video objects corresponding to the video ids given
	 */
	function bcGetVideoDataById($id, $fields = array())
	{
		$params = array(
			'video_id' => $id,
			'fields' => (!empty($fields)) ? implode(',', $fields) : '' // only used for getting thumbnails so far
		);
		
		$video = $this->bc->find('find_video_by_id', $params);
		return $video;
	}
	/**
	 * Updates brightcove video data by id
	 * @param int $id brightcove video id
	 * @return array video fields
	 */
	function bcUpdateVideoData($id = 0, $data = array())
	{
		if (!$id || empty($data)) return false;
		$data['id'] = $id;
		$ret = $this->bc->update('video', $data);
		return $ret;
	}
	// ---------------- BRIGHTCOVE VIDEOS IMPORT ----------------
	/**
	 * Gets videos and playlists from brightcove, determines which of them are new and
	 * inserts them into database. Updates videos playlists ids if needed.
	 */
	function bcImportVideos($cron = TRUE)
	{
		set_time_limit(300);
		$cronMsg = '';
		
		// get playlists data from brightcove
		$playlists = $this->bcGetVideoPlaylistsData();
		
		if (empty($playlists->items) OR !is_array($playlists->items)) {
			$cronMsg .= 'Cannot get playlists data from Brightcove.';
		} else {
			foreach ($playlists->items as $playlist) {
				
				// get playlists ids from db
				list($currentVideosIds, $currentPlaylistsIds) = $this->getPlaylistsVideosIds();
				
				$playlistId = (string)$playlist->id;
				// insert new playlist
				if (!$this->playlistExists($playlistId, $currentPlaylistsIds)) {
					
					$cronMsg .= 'Importing new playlist: ' . $playlist->name . ', <em>' . $playlistId . '</em><br />';
					
					$ins = array(
						'id' => $playlistId,
						'reference_id' => $playlist->referenceId,
						'name' => $playlist->name,
						'uri' => make_uri($playlist->name),
						'short_description' => $playlist->shortDescription,
						'filter_tags' => implode(',', $playlist->filterTags)
					);
					$this->dbInsertNewPlaylist($ins);
					
				}
				$cronMsg .= 'Importing videos for playlist: ' . $playlist->name . ', <em>' . $playlistId . '</em><br />';
				
				// determine new videos to insert
				$newVideoIds = array_diff($playlist->videoIds, $currentVideosIds);
				
				$videosCnt = count($newVideoIds);
				if ($videosCnt > 0) {
					$cronMsg .= ' - ' . $videosCnt . ' new videos for this playlist. Importing...<br />';
				} else {
					$cronMsg .= ' - ' . $videosCnt . ' new videos for this playlist.<br />';
				}
				
				$videoIdsLimit = 10;
				$videoIdsChunks = array_chunk($newVideoIds, $videoIdsLimit);
				$null = "NULL";
				// insert new videos
				foreach ($videoIdsChunks as $vIds) {
					
					$videos = $this->bcGetVideosByIds($vIds);
					
					if (empty($videos->items)) {
						$cronMsg .= ' - ERROR: cannot get videos from Brightcove for this playlist.';
						continue;
					}
					$sql = 'INSERT INTO ' . $this->table('Videos') . '(`id`,`playlist_ids`,`name`,`short_description`, `long_description`, `creation_date`, `published_date`, `last_modified_date`, `link_url`, `link_text`, `tags`, `video_still_url`, `thumbnail_url`, `reference_id`, `length`, `economics`, `plays_total`, `plays_trailing_week`, `is_hidden`) VALUES ';
					$insert = array();
					
					foreach ($videos->items as $v) {
						if (empty($v)) continue;
						$insert[] = '(' . implode(',', array(
							'id' => $v->id,
							'playlist_ids' => $playlistId,
							
							'name' => '\'' . $this->db->escape($v->name) . '\'',
							'short_description' => '\'' . $this->db->escape($v->shortDescription) . '\'',
							'long_description' => '\'' . $this->db->escape($v->longDescription) . '\'',
							'creation_date' => ($v->creationDate) ? $this->db->escape($v->creationDate) : $null,
							'published_date' => ($v->publishedDate) ? $this->db->escape($v->publishedDate) : $null,
							'last_modified_date' => ($v->lastModifiedDate) ? $this->db->escape($v->lastModifiedDate) : $null,
							'link_url' => '\'' . $this->db->escape($v->linkURL) . '\'',
							'link_text' => '\'' . $this->db->escape($v->linkText) . '\'',
							'tags' => '\'' . $this->db->escape(implode(',', $v->tags)) . '\'',
							'video_still_url' => '\'' . $this->db->escape($v->videoStillURL) . '\'',
							'thumbnail_url' => '\'' . $this->db->escape($this->getVideoThumbnailSource($v->thumbnailURL, $v->id)) . '\'',
							'reference_id' => '\'' . $v->referenceId . '\'',
							'length' => ($v->length) ? $v->length : $null,
							'economics' => ($v->economics) ? '\'' . $v->economics . '\'': $null,
							'plays_total' => ($v->playsTotal) ? $v->playsTotal : 0,
							'plays_trailing_week' => ($v->playsTrailingWeek) ? $v->playsTrailingWeek : 0,
							0
							)
						) . ')';
						// update tags cache
						if (is_object($ctags = $this->object('other.tags_ctags'))) {
							$tags = is_array($v->tags) ? $v->tags : array();
							$ctags->update($v->id, tags_ctags::videos, $v->tags);
						}
					}
					if (!empty($insert)) {
						$sql .= implode(',', $insert);
						$this->db->query($sql);
					}
					$cronMsg .= ' - ' . count($videos->items) . ' videos have been imported<br />';
				}
				
				// determine new videos in THIS PLAYLIST - these are all not inserted videos
				$idsIn = (isset($currentPlaylistsIds[$playlistId])) ? $currentPlaylistsIds[$playlistId] : array();
				$newPlaylistVideoIds = array_diff($playlist->videoIds, $newVideoIds + $idsIn);
				
				$when = '';
				foreach ($newPlaylistVideoIds as $id) {
					$when .= 'WHEN id = ' . $id . ' THEN CONCAT(playlist_ids, \',' . $playlistId . '\') ';
				}
				if ($when) {
					$sql = 'UPDATE ' . $this->table('Videos') . '
						SET playlist_ids =
							CASE
							' . $when . '
							ELSE playlist_ids
							END';
					$this->db->query($sql);
					$cronMsg .= ' - ' . count($newPlaylistVideoIds) . ' videos have their playlists ids updated<br />';
				}
				
				$cronMsg .= '<hr />';
			}
		}
		$cronMsg .= '<br />Done.';
		
		if ($cron) {
			$page = &moon::page();
			$page->set_local('cron', $cronMsg);

			return FALSE;
		} else {
			// log this action
			blame($this->my('fullname'), 'Updated', 'Imported videos');
			return $cronMsg;
		}
	}
	function getPlaylistsVideosIds()
	{
		$sql = 'SELECT id, playlist_ids
			FROM ' . $this->table('Videos');
		$result = $this->db->array_query_assoc($sql);
		
		$playlistItems = array();
		$videoIds = array();
		
		foreach ($result as $r) {
			$videoIds[] = $r['id'];
			$chunks = explode(',', $r['playlist_ids']);
			foreach ($chunks as $id) {
				if ($id) {
					$playlistItems[$id][$r['id']] = $r['id'];
				}
			}
		}
		return array($videoIds, $playlistItems);
	}
	/*function getVideosIds()
	{
		$sql = 'SELECT id
			FROM ' . $this->table('Videos');
		$result = $this->db->array_query_assoc($sql);
		$items = array();
		foreach ($result as $r) {
			$items[] = $r['id'];
		}
		return $items;
	}*/
	function dbInsertNewPlaylist($ins)
	{
		$this->db->insert($ins, $this->table('VideosPlaylists'));
		return TRUE;
	}
	function playlistExists($playlistId, $currentPlaylistsIds)
	{
		//if(array_key_exists($playlistId, $currentPlaylistsIds)) return TRUE;
		//else {
			$result = $this->db->single_query_assoc('
				SELECT count(*) as cnt
				FROM ' . $this->table('VideosPlaylists') . '
				WHERE id = ' . $playlistId
			);
			return ($result['cnt']) ? TRUE : FALSE;
		//}
	}
	function bcReloadVideoThumbnail($id)
	{
		$msg = '';
		$data = $this->bcGetVideoDataById($id, array('thumbnailURL'));
		if (!empty($data->thumbnailURL)) {
			$thumbSrc = $this->getVideoThumbnailSource($data->thumbnailURL, $id, TRUE);
			
			// update thumb in database
			$upd = array('thumbnail_url' => $thumbSrc);
			$this->db->update($upd, $this->table('Videos'), array('id' => $id));
			$msg = 'Image reloaded';
		} else {
			$msg = 'Unable to get data from brightcove';
		}
		
		return $msg;
	}
	function bcReimportVideoData($id)
	{
		$msg = '';
		$data = $this->bcGetVideoDataById($id);
		$null = "NULL";
		if (!empty($data)) {
			$upd = array();
			if (!empty($data->thumbnailURL)) {
				// reimport thumb, update field in database
				$thumbSrc = $this->getVideoThumbnailSource($data->thumbnailURL, $id, TRUE);
				$upd['thumbnail_url'] = $thumbSrc;
			}
			
			$upd += array(
				'name' => $data->name,
				'short_description' => $data->shortDescription,
				'long_description' => $data->longDescription,
				'creation_date' => ($data->creationDate) ? $data->creationDate : $null,
				'published_date' => ($data->publishedDate) ? $data->publishedDate : $null,
				'last_modified_date' => ($data->lastModifiedDate) ? $data->lastModifiedDate : $null,
				'link_url' => $data->linkURL,
				'link_text' => $data->linkText,
				'tags' => implode(',', $data->tags),
				'video_still_url' => $data->videoStillURL,
				'reference_id' => $data->referenceId,
				'length' => ($data->length) ? $data->length : $null,
				'economics' => ($data->economics) ? $data->economics : $null,
				'plays_total' => ($data->playsTotal) ? $data->playsTotal : 0,
				'plays_trailing_week' => ($data->playsTrailingWeek) ? $data->playsTrailingWeek : 0
			);
			
			$this->db->update($upd, $this->table('Videos'), array('id' => $id));

			// update tags cache
			if (is_object($ctags = $this->object('other.tags_ctags'))) {
				$tags = is_array($data->tags) ? $data->tags : array();
				$ctags->update($id, tags_ctags::videos, $tags);
			}
			
			$msg = 'Data reimported';
		} else {
			$msg = 'Unable to get data from brightcove';
		}
		
		return $msg;
	}
	/**
	 * 
	 */
	function bcImportFullPlaylist($playlistId, $tags = array(), $tagsOptions = 'and_tags')
	{
		set_time_limit(300);
		$cronMsg = '<b>Import results</b><hr />';
		
		$videoIds = $this->bcGetVideoIdsByTags($tags, $tagsOptions);
		
		// get playlists ids from db
		list($currentVideosIds, $currentPlaylistsIds) = $this->getPlaylistsVideosIds();
		
		
		// determine new videos to insert
		$newVideoIds = array_diff($videoIds, $currentVideosIds);
		
		$videosCnt = count($newVideoIds);
		if ($videosCnt > 0) {
			$cronMsg .= ' - ' . $videosCnt . ' new videos for this playlist. Importing...<br />';
		} else {
			$cronMsg .= ' - ' . $videosCnt . ' new videos for this playlist.<br />';
		}
		
		$videoIdsLimit = 40;
		$videoIdsChunks = array_chunk($newVideoIds, $videoIdsLimit);
		$null = "NULL";
		// insert new videos
		foreach ($videoIdsChunks as $vIds) {
			
			$videos = $this->bcGetVideosByIds($vIds);
			
			if (empty($videos->items)) {
				$cronMsg .= ' - ERROR: cannot get videos from Brightcove for this playlist.';
				continue;
			}
			$sql = 'INSERT INTO ' . $this->table('Videos') . '(`id`,`playlist_ids`,`name`,`short_description`, `long_description`, `creation_date`, `published_date`, `last_modified_date`, `link_url`, `link_text`, `tags`, `video_still_url`, `thumbnail_url`, `reference_id`, `length`, `economics`, `plays_total`, `plays_trailing_week`, `is_hidden`) VALUES ';
			$insert = array();
			foreach ($videos->items as $v) {
				$insert[] = '(' . implode(',', array(
					'id' => $v->id,
					'playlist_ids' => $playlistId,
					
					'name' => '\'' . $this->db->escape($v->name) . '\'',
					'short_description' => '\'' . $this->db->escape($v->shortDescription) . '\'',
					'long_description' => '\'' . $this->db->escape($v->longDescription) . '\'',
					'creation_date' => ($v->creationDate) ? $this->db->escape($v->creationDate) : $null,
					'published_date' => ($v->publishedDate) ? $this->db->escape($v->publishedDate) : $null,
					'last_modified_date' => ($v->lastModifiedDate) ? $this->db->escape($v->lastModifiedDate) : $null,
					'link_url' => '\'' . $this->db->escape($v->linkURL) . '\'',
					'link_text' => '\'' . $this->db->escape($v->linkText) . '\'',
					'tags' => '\'' . $this->db->escape(implode(',', $v->tags)) . '\'',
					'video_still_url' => '\'' . $this->db->escape($v->videoStillURL) . '\'',
					'thumbnail_url' => '\'' . $this->db->escape($this->getVideoThumbnailSource($v->thumbnailURL, $v->id)) . '\'',
					'reference_id' => '\'' . $v->referenceId . '\'',
					'length' => ($v->length) ? $v->length : $null,
					'economics' => ($v->economics) ? '\'' . $v->economics . '\'': $null,
					'plays_total' => ($v->playsTotal) ? $v->playsTotal : 0,
					'plays_trailing_week' => ($v->playsTrailingWeek) ? $v->playsTrailingWeek : 0,
					0
					)
				) . ')';

				// update tags cache
				if (is_object($ctags = $this->object('other.tags_ctags'))) {
					$tags = is_array($v->tags) ? $v->tags : array();
					$ctags->update($v->id, tags_ctags::videos, $tags);
				}
			}
			if (!empty($insert)) {
				$sql .= implode(',', $insert);
				$this->db->query($sql);
			}
			$cronMsg .= ' - ' . count($videos->items) . ' videos have been imported<br />';
		}
		
		// determine new videos in THIS PLAYLIST - these are all not inserted videos
		$idsIn = (isset($currentPlaylistsIds[$playlistId])) ? $currentPlaylistsIds[$playlistId] : array();
		$newPlaylistVideoIds = array_diff($videoIds, $newVideoIds + $idsIn);
		
		$when = '';
		foreach ($newPlaylistVideoIds as $id) {
			$when .= 'WHEN id = ' . $id . ' THEN CONCAT(playlist_ids, \',' . $playlistId . '\') ';
		}
		if ($when) {
			$sql = 'UPDATE ' . $this->table('Videos') . '
				SET playlist_ids =
					CASE
					' . $when . '
					ELSE playlist_ids
					END';
			$this->db->query($sql);
			$cronMsg .= ' - ' . count($newPlaylistVideoIds) . ' videos have their playlists ids updated<br />';
		}
		
		return $cronMsg .= '<hr />';
	}
	// ------------ END BRIGHTCOVE VIDEOS IMPORT ----------------
	
	/**
	 * Saves video image thumbnail to /w/video/ directory
	 * @param string $url brightcove thumbnail url
	 * @param number $videoId video id - will be used as a part of file name
	 * @param boolean $reload overwrite image file if exists
	 * @return string Thumbnail uri if successfull, if not - initial url
	 */
	function getVideoThumbnailSource($url, $videoId, $reload = FALSE)
	{
		$src = $this->get_var('videoThumbSrc');
		$dir = $this->get_dir('videoThumbDir');
		
		$thumbName = 'thumb_' . $videoId;
		
		include_class('moon_file');
		$file = new moon_file;
		
		// get file extension
		$ext = substr($file->file_ext($url), 0, 3);
		
		$thumbFile = $dir . $thumbName . '.' . $ext;
		$thumbSrc = $fileSrc = $src . $thumbName . '.' . $ext;
		
		if (!$file->is_file($thumbFile) OR $reload) {
			$thumbSrc = $url;
			if ($file->is_url_content($url, $thumbFile)) {
				$thumbSrc = $fileSrc;
				// resize if width > 120
				$wh = $file->file_wh();
				if ($wh !== '') {
					list($width, $height) = explode('x', $wh);
					if ($width > 120) {
						$img = &moon::shared('img');
						if ($img->resize_exact($file, $thumbFile, 120, 90) && $file->is_file($thumbFile)) {
							// ok
						} else {
							// unable to resize image
							//print 'error';
						}
					}
				}
			}
		}
		return $thumbSrc;
	}

}
?>