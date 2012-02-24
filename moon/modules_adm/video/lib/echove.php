<?php
//error_reporting('E_ALL  & ~E_NOTICE');
/**
 * ECHOVE
 * A Brightcove PHP SDK
 * 
 * Official Website:
 *     http://echove.net/
 * Code Repository:
 *     http://code.google.com/p/echove/
 * Authors:
 *     Matthew Congrove, Professional Services Engineer, Brightcove
 *     Brian Franklin, Professional Services Engineer, Brightcove
 * Version:
 *     Echove 0.4.0 (19 AUGUST 2009)
 * Change Log:
 *     0.4.0 - Provided better logic for createVideo method. Fixed
 *             error reporting in createPlaylist, also included
 *             check to determine if video IDs are being passed.
 *     0.3.9 - Added share_video and get_upload_status methods.
 *             Corrected error for find_modified_videos return.
 *             Updated error codes and reporting points.
 *     0.3.8 - Improved debugging. Added new API find call, and
 *             get_item_count is now assumed as TRUE.
 *     0.3.7 - Fixed major error in Find method.
 *     0.3.6 - Added debug information, video tag filtering, and
 *             a true Find All Videos function.
 *     0.3.5 - Added support for 32-bit servers. Error reporting
 *             can now be configured during instantiation. Fixed
 *             URL encoding issue. Added support for tag arrays.
 *     0.3.4 - Improved error reporting. Added image upload.
 *     0.3.3 - Fixed RTMP to HTTP URL function. Fixed video upload.
 *     0.3.2 - Added RTMP to HTTP URL function, and function to
 *             easily parse video tags. Improved SEF function.
 *             Added support for remote assets.
 *     0.3.1 - Improved error reporting.
 *     0.3.0 - Added Write API methods for creating, updating, and
 *             deleting both videos and playlists.
 *     0.2.2 - Fix to remove notices. Added embed method. Corrected
 *             video lengths.
 *     0.2.1 - Setting default values to remove notices. Added inline
 *             code documentation. Added utilities to convert video
 *             lengths from milliseconds to formatted time or seconds
 *             for ease of use and to convert video names to a
 *             search-engine friendly format.
 *     0.2.0 - Updated to include API return properties.
 *     0.1.0 - Initial release.
 * 
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */
class Echove
{

   /**
    * @access Public
    * @since 0.1.0
    * @param string [$token_read] The read API token for the Brightcove account
    * @param string [$token_write] The write API token for the Brightcove account
    * @param bool [$show_errors] Whether or not to show Echove errors
    */
	public function __construct($token_read, $token_write = NULL, $show_errors = FALSE)
	{	
		$this->token_read = $token_read;
		$this->token_write = $token_write;
		$this->show_errors = $show_errors;
		$this->read_url = 'http://api.brightcove.com/services/library?';
		$this->write_url = 'http://api.brightcove.com/services/post';
		$this->page_number = NULL;
		$this->page_size = NULL;
		$this->total_count = NULL;
		$this->bit32 = ((string)'99999999999999' == (int)'99999999999999') ? TRUE : FALSE;
		$this->api_calls = 0;

		if(!$token_read)
		{
			$this->triggerError('001');
			return FALSE;
		}
	}
	
   /**
    * Formats the API request URL, retrieves the data, and returns it.
    * @access Public
    * @since 0.1.0
    * @param string [$call] The requested API method
    * @param array [$params] A key-value array of API parameters and values
    * @return object An object containing all API return data
    */
	public function find($call, $params = NULL)
	{
		$call = strtolower(str_replace('find', '', str_replace('_', '', $call)));
		
		switch($call)
		{
			case 'allvideos':
				$method = 'find_all_videos';
				$get_item_count = TRUE;
				break;
			case 'videobyid':
				$method = 'find_video_by_id';
				$default = 'video_id';
				$get_item_count = FALSE;
				break;
			case 'relatedvideos':
				$method = 'find_related_videos';
				$default = 'video_id';
				$get_item_count = TRUE;
				break;
			case 'videosbyids':
				$method = 'find_videos_by_ids';
				$default = 'video_ids';
				$get_item_count = FALSE;
				break;
			case 'videobyreferenceid':
				$method = 'find_video_by_reference_id';
				$default = 'reference_id';
				$get_item_count = FALSE;
				break;
			case 'videosbyreferenceids':
				$method = 'find_videos_by_reference_ids';
				$default = 'reference_ids';
				$get_item_count = FALSE;
				break;
			case 'videosbyuserid':
				$method = 'find_videos_by_user_id';
				$default = 'user_id';
				$get_item_count = TRUE;
				break;
			case 'videosbycampaignid':
				$method = 'find_videos_by_campaign_id';
				$default = 'campaign_id';
				$get_item_count = TRUE;
				break;
			case 'videosbytext':
				$method = 'find_videos_by_text';
				$default = 'text';
				$get_item_count = TRUE;
				break;
			case 'videosbytags':
				$method = 'find_videos_by_tags';
				$default = 'or_tags';
				$get_item_count = TRUE;
				break;
			case 'modifiedvideos':
				$method = 'find_modified_videos';
				$default = 'from_date';
				$get_item_count = TRUE;
				break;
			case 'allplaylists':
				$method = 'find_all_playlists';
				$get_item_count = TRUE;
				break;
			case 'playlistbyid':
				$method = 'find_playlist_by_id';
				$default = 'playlist_id';
				$get_item_count = FALSE;
				break;
			case 'playlistsbyids':
				$method = 'find_playlists_by_id';
				$default = 'playlist_ids';
				$get_item_count = FALSE;
				break;
			case 'playlistbyreferenceid':
				$method = 'find_playlist_by_reference_id';
				$default = 'reference_id';
				$get_item_count = FALSE;
				break;
			case 'playlistsbyreferenceids':
				$method = 'find_playlists_by_reference_ids';
				$default = 'reference_ids';
				$get_item_count = FALSE;
				break;
			case 'playlistsforplayerid':
				$method = 'find_playlists_for_player_id';
				$default = 'player_id';
				$get_item_count = TRUE;
				break;
			default:
				$this->triggerError('005');
				return FALSE;
				break;
		}
		
		if(isset($params['from_date']) || $default == 'from_date')
		{
			if($default == 'from_date' && !isset($params['from_date']))
			{
				$from_date = (string)$params;
			} else {
				$from_date = (string)$params['from_date'];
			}
			
			if(strlen($from_date) > 9)
			{
				$from_date = floor((int)$from_date / 60);
			}
			
			if(!is_array($params) && $default == 'from_date')
			{
				$params = $from_date;
			} else {
				$params['from_date'] = $from_date;
			}
		}
		
		if($get_item_count)
		{
			if(!isset($params['get_item_count']))
			{
				if(!is_array($params))
				{
					  $par = $params;
					  $params = array();
					$params[$default] = $par;
				}
				
				$params['get_item_count'] = 'TRUE';
			}
		}
		
		if($default && !is_array($params)) {
			$url = $this->appendParams($method, $params, $default);
		} else {			
			$url = $this->appendParams($method, $params);
		}

		$result = $this->getData($url);
		
		if(is_object($result) AND !empty($result->error))
		{
			$this->triggerError('11');
			return FALSE;
		} else {
			return $result;
		}
	}
	
   /**
    * Finds all videos in account, ignoring pagination
    * @access Public
    * @since 0.3.6
    * @param array [$params] A key-value array of API parameters and values
    * @return object An object containing all API return data
    */
	public function findAll($params = NULL)
	{
		$videos = array();
		$current_page = 0;
		$total_count = 0;
		$total_page = 1;
		
		$params['get_item_count'] = 'TRUE';
		$params['page_size'] = 100;
		$params['page_number'] = 0;

		while($current_page < $total_page)
		{
			$params['page_number'] = $current_page;
			
			$url = $this->appendParams('find_all_videos', $params);
			$result = $this->getData($url);
			
			if($total_count < 1)
			{
				$total_count = $this->total_count;
				$total_page = ceil($total_count / 100);
			}
			
			if(!empty($result->error))
			{
				$this->triggerError('011');
				return FALSE;
			} else {
				foreach($result as $video)
				{
					$videos[] = $video;
				}
			}
			
			$current_page++;
		}
		
		return $videos;
	}

   /**
    * Appends API parameters onto API request URL
    * @access Private
    * @since 0.1.0
    * @param string [$method] The requested API method
    * @param array [$params] A key-value array of API parameters and values
    * @param string [$default] The default API parameter if only 1 provided
    * @return string The complete API request URL
    */
	private function appendParams($method, $params = NULL, $default = NULL)
	{
		$url = $this->read_url . 'token=' . $this->token_read . '&command=' . $method;
		
		if($params)
		{
			if($default)
			{
				$url .= '&' . $default . '=' . urlencode($params);
			} else {
				foreach($params as $option => $value)
				{
					$url .= '&' . $option . '=' . urlencode($value);
				}
			}
		}
		
		return $url;
	}

   /**
    * Retrieves API data from provided URL
    * @access Private
    * @since 0.1.0
    * @param string [$url] The complete API request URL
    * @return object An object containing all API return data
    */
	private function getData($url)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		$response = curl_exec($curl);
		
		$this->api_calls++;

		if(curl_errno($curl))
		{
			$this->triggerError('003');
			echo curl_error($curl);
			return FALSE;
		}

		curl_close($curl);
		
		if($this->bit32)
		{
			$response = preg_replace('/:\s*(\d{10,})/', ':"$1"', $response);
			$response = preg_replace('/(\d{10,})\]/', '"$1"]', $response);
			$response = preg_replace('/(\d{10,})\,/', '"$1",', $response);
		}
		
		if($response)
		{
			if($response != 'NULL')
			{
				$json = $this->json_decode($response);
				 
				 // make it return all data, including total_count, etc... [aleksandras]
				 $data = $json;
				/*if(isset($json->items))
				{
					$data = $json->items;
				} else {
					$data = $json;
				}*/
				if (isset($json->page_number)) {
				 $this->page_number = $json->page_number;
				}
				if (isset($json->page_size)) {
				 $this->page_size = $json->page_size;
				}
				if (isset($json->total_count)) {
				 $this->total_count = $json->total_count;
				}
				
				return $data;
			} else {
				return FALSE;
			}
		} else {
			$this->triggerError('003');
			return FALSE;
		}
	}
	
   /**
    * Uploads a video file to Brightcove
    * @access Public
    * @since 0.3.0
    * @param string [$file] The location of the temporary file
    * @param array [$meta] The video information
    * @param bool [$multiple] Whether or not to create multiple renditions
    * @return mixed The video ID if successful, otherwise FALSE
    */
	public function createVideo($file = NULL, $meta, $multiple = FALSE)
	{
		if(!$this->token_write)
		{
			$this->triggerError('002');
			return FALSE;
		}

		$request = array();
		$post = array();
		$params = array();
		$video = array();
		
		foreach($meta as $key => $value)
		{
			$video[$key] = $value;
		}
		
		if(!$video['referenceId'])
		{
			$video['referenceId'] = time();
		}
		
		$params['token'] = $this->token_write;
		$params['video'] = $video;
		
		if(!isset($meta['create_multiple_renditions']))
		{
			if($multiple)
			{
				$params['create_multiple_renditions'] = 'TRUE';
			} else {
				$params['create_multiple_renditions'] = 'FALSE';
			}
		}
		
		if($file && isset($params['create_multiple_renditions']))
		{
			$extension = substr(strtolower($file), -3);
			$vp6 = array('f4a', 'f4b', 'f4v', 'f4p', 'flv');
			
			if(in_array($extension, $vp6))
			{
				$params['create_multiple_renditions'] = 'FALSE';
				$this->triggerError('013');
			}
		}
		
		$post['method'] = 'create_video';
		$post['params'] = $params;
		
		$request['json'] = $this->json_encode($post) . "\n";
		
		if($file)
		{
			$request['file'] = '@' . $file;
		}
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->write_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		$response = curl_exec($curl);
		
		$this->api_calls++;
		
		if(curl_errno($curl))
		{
			$this->triggerError('010');
			echo curl_error($curl);
			return FALSE;
		}
		
		curl_close($curl);
		
		if($this->bit32)
		{
			$response = preg_replace('/:\s*(\d{10,})/', ':"$1"', $response);
			$response = preg_replace('/(\d{10,})\]/', '"$1"]', $response);
			$response = preg_replace('/(\d{10,})\,/', '"$1",', $response);
		}
		
		$json = $this->json_decode($response);

		if($json->result)
		{
			return $json->result;
		} else {
			$this->triggerError('011');
			return FALSE;
		}
	}
	
   /**
    * Retrieves the status of a video upload
    * @access Public
    * @since 0.3.9
    * @param int [$video_id] The ID of the video asset
    * @param string [$ref_id] The reference ID of the video asset
    * @return string The upload status
    */
	public function getStatus($video_id = NULL, $ref_id = TRUE)
	{
		if(!$this->token_write)
		{
			$this->triggerError('002');
			return FALSE;
		}
		
		if(!$video_id && !$ref_id)
		{
			$this->triggerError('008');
		}
		
		$request = array();
		$post = array();
		$params = array();
		
		$params['token'] = $this->token_write;
		
		if($video_id)
		{
			$params['video_id'] = $video_id;
		}
		
		if($ref_id)
		{
			$params['reference_id'] = $ref_id;
		}
		
		$post['method'] = 'get_upload_status';
		$post['params'] = $params;
		
		$request['json'] = $this->json_encode($post) . "\n";
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->write_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		$response = curl_exec($curl);
		
		$this->api_calls++;
		
		if(curl_errno($curl))
		{
			$this->triggerError('004');
			echo curl_error($curl);
			return FALSE;
		}
		
		curl_close($curl);
		
		$json = $this->json_decode($response);

		if($json->result)
		{
			return $json->result;
		} else {
			$this->triggerError('011');
			return FALSE;
		}
	}
	
   /**
    * Uploads an image file to Brightcove
    * @access Public
    * @since 0.3.4
    * @param string [$file] The location of the temporary file
    * @param array [$meta] The image information
    * @param int [$video_id] The ID of the video asset to assign the image to
    * @param bool [$resize] Whether or not to resize the image on upload
    * @return mixed The image asset ID if successful, otherwise FALSE
    */
	public function createImage($file = NULL, $meta, $video_id = NULL, $resize = TRUE)
	{
		if(!$this->token_write)
		{
			$this->triggerError('002');
			return FALSE;
		}
		
		$request = array();
		$post = array();
		$image = array();
		$params = array();
		
		foreach($meta as $key => $value)
		{
			$image[$key] = $value;
		}
		
		if(!$image['referenceId'])
		{
			$image['referenceId'] = time();
		}
		
		$params['token'] = $this->token_write;
		$params['image'] = $image;
		
		if($video_id)
		{
			$params['video_id'] = $video_id;
		} else {
			$this->triggerError('008');
			return FALSE;
		}
		
		if($resize)
		{
			$params['resize'] = 'TRUE';
		} else {
			$params['resize'] = 'FALSE';
		}
		
		$post['method'] = 'add_image';
		$post['params'] = $params;
		
		$request['json'] = $this->json_encode($post) . "\n";
		
		if($file)
		{
			$request['file'] = '@' . $file;
		}
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->write_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		$response = curl_exec($curl);
		
		$this->api_calls++;
		
		if(curl_errno($curl))
		{
			$this->triggerError('012');
			echo curl_error($curl);
			return FALSE;
		}
		
		curl_close($curl);
		
		if($this->bit32)
		{
			$response = preg_replace('/:\s*(\d{10,})/', ':"$1"', $response);
			$response = preg_replace('/(\d{10,})\]/', '"$1"]', $response);
			$response = preg_replace('/(\d{10,})\,/', '"$1",', $response);
		}
		
		$json = $this->json_decode($response);

		if($json->result)
		{
			return $json->result->id;
		} else {
			$this->triggerError('011');
			return FALSE;
		}
	}

   /**
    * Creates a playlist
    * @access Public
    * @since 0.3.0
    * @param array [$meta] The playlist information
    * @return mixed The playlist ID if successful, otherwise FALSE
    */
	public function createPlaylist($meta)
	{
		if(!$this->token_write)
		{
			$this->triggerError('002');
			return FALSE;
		}

		$request = array();
		$post = array();
		$params = array();
		$playlist = array();
		
		foreach($meta as $key => $value)
		{
			$playlist[$key] = $value;
		}
		
		if(!$playlist['referenceId'])
		{
			$playlist['referenceId'] = time();
		}
		
		if(isset($playlist['videoIds']))
		{
			if($playlist['playlistType'] != 'explicit')
			{
				foreach($playlist['videoIds'] as $key => $value)
				{
					$playlist['videoIds'][$key] = (int)$value;
				}
			}
		}
		
		$params['token'] = $this->token_write;
		$params['playlist'] = $playlist;
		
		$post['method'] = 'create_playlist';
		$post['params'] = $params;
		
		$request['json'] = $this->json_encode($post);
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->write_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		$response = curl_exec($curl);
		
		$this->api_calls++;
		
		if(curl_errno($curl))
		{
			$this->triggerError('004');
			echo curl_error($curl);
			return FALSE;
		}
		
		curl_close($curl);
		
		if($this->bit32)
		{
			$response = preg_replace('/:\s*(\d{10,})/', ':"$1"', $response);
			$response = preg_replace('/(\d{10,})\]/', '"$1"]', $response);
			$response = preg_replace('/(\d{10,})\,/', '"$1",', $response);
		}
		
		$json = $this->json_decode($response);

		if($json->result)
		{
			return $json->result;
		} else {
			$this->triggerError('011');
			return FALSE;
		}
	}
	
   /**
    * Updates a video or playlist
    * @access Public
    * @since 0.3.0
    * @param string [$type] The item to update, either a video or playlist
    * @param array [$meta] The information for the video or playlist
    */
	public function update($type, $meta)
	{
		if(!$this->token_write)
		{
			$this->triggerError('002');
			return FALSE;
		}
		
		$request = array();
		$post = array();
		$params = array();
		$metaData = array();

		$params['token'] = $this->token_write;
		
		if(strtolower($type) == 'video')
		{		
			foreach($meta as $key => $value)
			{
				$metaData[$key] = $value;
			}
		
			$params['video'] = $metaData;
			$post['method'] = 'update_video';
		} elseif(strtolower($type) == 'playlist') {	
			foreach($meta as $key => $value)
			{
				$metaData[$key] = $value;
			}
				
			$params['playlist'] = $metaData;
			$post['method'] = 'update_playlist';
		} else {
			$this->triggerError('006');
			return FALSE;
		}
		
		$post['params'] = $params;
		
		$request['json'] = $this->json_encode($post) . "\n";
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->write_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		$response = curl_exec($curl);
		
		$this->api_calls++;
		
		if(curl_errno($curl))
		{
			$this->triggerError('004');
			echo curl_error($curl);
			return FALSE;
		}
		
		curl_close($curl);
	}
	
   /**
    * Deletes a video or playlist
    * @access Public
    * @since 0.3.0
    * @param string [$type] The item to delete, either a video or playlist
    * @param int [$id] The ID of the video or playlist
    * @param string [$ref_id] The reference ID of the video or playlist
    * @param bool [$cascade] Whether or not to cascade the deletion
    */
	public function delete($type, $id = NULL, $ref_id = NULL, $cascade = TRUE)
	{
		if(!$this->token_write)
		{
			$this->triggerError('002');
			return FALSE;
		}
		
		$request = array();
		$post = array();
		$params = array();

		$params['token'] = $this->token_write;
		$params['cascade'] = $cascade;
		
		if(strtolower($type) == 'video')
		{
			if($id)
			{
				$params['video_id'] = $id;
			} elseif($ref_id) {
				$params['reference_id'] = $ref_id;
			} else {
				$this->triggerError('008');
				return FALSE;
			}
			
			$post['method'] = 'delete_video';
		} elseif(strtolower($type) == 'playlist') {
			if($id)
			{
				$params['playlist_id'] = $id;
			} elseif($ref_id) {
				$params['reference_id'] = $ref_id;
			} else {
				$this->triggerError('008');
				return FALSE;
			}
			
			$post['method'] = 'delete_playlist';
		} else {
			$this->triggerError('006');
			return FALSE;
		}
		
		$post['params'] = $params;
		
		$request['json'] = $this->json_encode($post) . "\n";
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->write_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		$response = curl_exec($curl);
		
		$this->api_calls++;
		
		if(curl_errno($curl))
		{
			$this->triggerError('004');
			echo curl_error($curl);
			return FALSE;
		}
		
		curl_close($curl);
	}
	
   /**
    * Shares a video with the selected accounts
    * @access Public
    * @since 0.3.9
    * @param int [$video_id] The ID of the video asset
    * @param array [$account_ids] An array of account IDs
    * @param bool [$accept] Whether the share should be auto accepted
    * @return array The new video IDs
    */
	public function shareVideo($video_id, $account_ids, $accept = FALSE)
	{
		if(!$this->token_write)
		{
			$this->triggerError('002');
			return FALSE;
		}
		
		if(!$video_id)
		{
			$this->triggerError('008');
			return FALSE;
		}
		
		$request = array();
		$post = array();
		$params = array();
		
		$params['token'] = $this->token_write;
		$params['video_id'] = $video_id;
		$params['sharee_account_ids'] = $account_ids;
		
		if($accept)
		{
			$params['auto_accept'] = 'TRUE';
		} else {
			$params['auto_accept'] = 'FALSE';
		}
		
		$post['method'] = 'share_video';
		$post['params'] = $params;
		
		$request['json'] = $this->json_encode($post) . "\n";
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->write_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		$response = curl_exec($curl);
		
		$this->api_calls++;
		
		if(curl_errno($curl))
		{
			$this->triggerError('004');
			echo curl_error($curl);
			return FALSE;
		}
		
		curl_close($curl);
		
		$json = $this->json_decode($response);
		
		if($json->result)
		{
			return $json->result;
		} else {
			$this->triggerError('011');
			return FALSE;
		}
	}
	
   /**
    * Converts milliseconds to formatted time or seconds
    * @access Public
    * @since 0.2.1
    * @param int [$ms] The length of the video in milliseconds
    * @param bool [$seconds] Whether to return only seconds
    * @return mixed The formatted length or total seconds of the video
    */
	public function time($ms, $seconds = FALSE)
	{
		$total_seconds = ($ms / 1000);
			
		if($seconds)
		{
			return $total_seconds;
		} else {
			$time = '';
			$value = array(
				'hours' => 0,
				'minutes' => 0,
				'seconds' => 0
			);
			
			if($total_seconds >= 3600) {
				$value['hours'] = floor($total_seconds/3600);
				$total_seconds = ($total_seconds%3600);

				$time .= $value['hours'] . ':';
			}
			
			if($total_seconds >= 60) {
				$value['minutes'] = floor($total_seconds/60);
				$total_seconds = ($total_seconds%60);
				
				$time .= $value['minutes'] . ':';
			} else {
				$time .= '0:';
			}
			
			$value['seconds'] = floor($total_seconds);
			
			if($value['seconds'] < 10)
			{
				$value['seconds'] = '0' . $value['seconds'];
			}
			
			$time .= $value['seconds'];
			
			return $time;
		}
	}
	
   /**
    * Parses video tags array into a key-value array
    * @access Public
    * @since 0.3.2
    * @param array [$tags] The tags array from a video DTO
    * @return array A key-value array of tags
    */
	public function tags($tags)
	{
		$return = array();
	
		if(count($tags) > 0)
		{
			foreach($tags as $tag)
			{
				if(strpos($tag, '=') === FALSE)
				{
					$return[] = $tag;
				} else {
					$group = explode('=', $tag);
					$key = trim($group[0]);
					$value = trim($group[1]);
					
					if(!isset($return[$key]))
					{
						$return[$key] = $value;
					} else {
						if(is_array($return[$key]))
						{
							$return[$key][] = $value;
						} else {
							$return[$key] = array($return[$key], $value);
						}
					}
				}
			}
		}
	
		return $return;
	}
	
   /**
    * Removes videos that don't contain the appropriate tags
    * @access Public
    * @since 0.3.6
    * @param array [$videos] All the videos you wish to filter
    * @param string [$tag] A comma-separated list of tags to filter on
    * @return array The filtered list of videos
    */
	public function filter($videos, $tags)
	{
		$filtered = array();
		
		foreach($videos as $video)
		{
			if(count(array_intersect(explode(',', $tags), $video->tags)) > 0)
			{
				$filtered[] = $video;
			}
		}
	
		return $filtered;
	}
	
   /**
    * Formats a video name to be search-engine friendly
    * @access Public
    * @since 0.2.1
    * @param string [$name] The video name
    * @return string The SEF video name
    */
	public function sef($name)
	{
		$name = preg_replace('/[^a-zA-Z0-9\s]+/', '', $name);
		$name = preg_replace('/\s/', '-', $name);
		
		return $name;
	}
	
   /**
    * Retrieves the HTTP URL from a streaming asset (RTMP).
    * @access Public
    * @since 0.3.2
    * @param string [$flvUrl] The RTMP FLV URL of an asset
    * @return string The HTTP URL of an asset
    */
	public function downloadUrl($flvUrl)
	{
		$return = '';
		$url = 'http://brightcove.vo.llnwd.net/';
		$matches = array();
		
		$preg = preg_match('/((.*?)\/)*/', $flvUrl, $matches);
		$filename = preg_replace('/.*\//', '', $flvUrl);
		$filename = preg_replace('/&.*/', '', $filename);

		if(strpos($flvUrl, 'mp4') !== false)
		{
			$filename .= '.mp4';
		} else {
			$filename .= '.flv';
		}
		
		if(strpos($flvUrl, '/d5/') !== false)
		{
			$return = ($url . 'pd5/media/' . $matches[2] . '/' . $filename);
		} elseif(strpos($flvUrl, '/o2/') !== false) {
			$return = ($url . 'pd2/media/' . $matches[2] . '/' . $filename);
		} elseif(strpos($flvUrl, '/d6/') !== false) {
			$return = ($url . 'pd6/media/' . $matches[2] . '/' . $filename);
		} elseif(strpos($flvUrl, '/d7/') !== false) {
			$return = ($url . 'pd7/media/' . $matches[2] . '/' . $filename);
		}
		
		return $return;
	}
	
   /**
    * Returns the JavaScript version of the player embed code
    * @access Public
    * @since 0.2.2
    * @param int [$playerId] The ID of the player to embed
    * @param mixed [$videoIds] The ID of the default video, or an array of video IDs
    * @param array [$params] A key-value array of embed parameters
    * @param array [$additional] A key-value array of additional embed parameters
    * @return string The embed code
    */
	public function embed($playerId, $videoIds = NULL, $params = NULL, $additional = NULL)
	{
		if(!$playerId)
		{
			$this->triggerError('008');
			return FALSE;
		}
			
		$values = array('id' => 'myExperience', 'bgcolor' => 'FFFFFF', 'width' => 486, 'height' => 412);

		foreach($values as $key => $value)
		{
			if(isset($params[$key]))
			{
				$values[$key] = $params[$key];
			}
		}
		
		$additionalCode = '';
		
		if(is_array($additional))
		{
			foreach($additional as $key => $value)
			{
				$additionalCode .= '<param name="' . $key . '" value="' . $value . '" />';
			}
		}
		
		$videoCode = '';
		
		if($videoIds)
		{
			if(is_array($videoIds))
			{
				$videoCode = '<param name="@videoPlayer" value="';
				
				foreach($videoIds as $videoId)
				{
					$videoCode .= $videoId . ',';
				}
				
				$videoCode = substr($videoCode, 0, -1);
				$videoCode .= '" />';
			} else {
				$videoCode = '<param name="@videoPlayer" value="' . $videoIds . '" />';
			}
		}
		
		$code = '
			<object id="' . $values['id'] . '" class="BrightcoveExperience">
				<param name="bgcolor" value="#' . $values['bgcolor'] . '" />
				<param name="width" value="' . $values['width'] . '" />
				<param name="height" value="' . $values['height'] . '" />
				<param name="playerID" value="' . $playerId . '" />'
				. $videoCode
				. $additionalCode . '
				<param name="isVid" value="true" />
				<param name="isUI" value="true" />
			</object>
		';
		
		return $code;
	}

   /**
    * Triggers an error if errors are enabled
    * @access Private
    * @since 0.3.1
    * @param string [$err_code] The code number of an error
    */	
	private function triggerError($err_code)
	{
		if($this->show_errors)
		{
			switch($err_code)
			{
				case '001':
					$text = 'Read token not provided';
					$type = 'WARNING';
					break;
				case '002':
					$text = 'Write token not provided';
					$type = 'WARNING';
					break;
				case '003':
					$text = 'Read API transaction failed';
					$type = 'NOTICE';
					break;
				case '004':
					$text = 'Write API transaction failed';
					$type = 'WARNING';
					break;
				case '005':
					$text = 'Requested method not found';
					$type = 'WARNING';
					break;
				case '006':
					$text = 'Type not specified';
					$type = 'WARNING';
					break;
				case '007': $text = ''; $type = ''; break;
				case '008':
					$text = 'ID not provided';
					$type = 'WARNING';
					break;
				case '009': $text = ''; $type = ''; break;
				case '010':
					$text = 'Media file transfer failed';
					$type = 'WARNING';
					break;
				case '011':
					$text = 'Unknown API error';
					$type = 'WARNING';
					break;
				case '012':
					$text = 'Image file transfer failed';
					$type = 'WARNING';
					break;
				case '013':
					$text = 'FLV files cannot have multiple renditions';
					$type = 'NOTICE';
			}
			
			if($type == 'NOTICE')
			{
				trigger_error(' [ECHOVE-' . $err_code . '] ' . $text . ' ', E_USER_NOTICE);
			} elseif($type == 'WARNING') {
				trigger_error(' [ECHOVE-' . $err_code . '] ' . $text . ' ', E_USER_WARNING);
			} else {
				// Deprecated, do nothing
			}
		}
	}
	
	private function json_encode($arg)
	{
		return json_encode($arg);
	}
	
	private function json_decode($arg)
	{
		return json_decode($arg);
	}

}

?>