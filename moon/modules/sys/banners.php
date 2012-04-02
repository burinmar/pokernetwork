<?php
class banners extends moon_com
{
	function onload ()
	{
		$this->env = '';
		$this->site = null;
	}

	function events($event, $par) {

		switch ($event) {
			case 'view':
				$this->forget();
				if (isset($_GET['mid']) && is_numeric($_GET['mid'])) {
					$campaignId = isset($_GET['cid']) ? intval($_GET['cid']) : 0;
					$zone = isset($_GET['z']) ? urldecode($_GET['z']) : '';

					$this->viewRedirectAd($_GET['mid'], $campaignId, $zone);
					exit;
				}
				moon_close();
				exit;
				break;
			case 'getdata':
				$siteId = 1;
				$jsOut = '';
				// cache
				/*
				$cache = &moon::cache();
				$cacheFileName = 'banners_data_' . $siteId;
				$cache->file($cacheFileName);
				$res = $cache->get();
				if($res !== FALSE) {
					$jsOut = $res;	//return cached
				}
				*/
				$banners = $this->getBanners();
				$templates = $this->getTemplates();

				$jsOut .= 'var bannersList = ' . json_encode($banners) . ';';
				$jsOut .= 'var bnTemplates = {' . $templates . '};';
				//$cache->save($jsOut, '5m');

				header('Content-type: text/javascript; charset=UTF-8');
				header('Expires: ' .   gmdate('r', time()+600) , TRUE);
				header('Cache-Control: max-age=' . 600 , TRUE);
				header('Pragma: public', TRUE);

				print $jsOut;
				exit;
				break;
			case 'trackviews' :
				if (isset($_GET['d']) && $_GET['d'] != '') {
					$items = explode('|', $_GET['d']);
					$data = array();
					foreach ($items as $item) {
						$data[] = json_decode($item, true);
					}
					$this->setBannersViewed($data);
				}
				header('Content-type: text/javascript; charset=UTF-8');
				exit;
			default :
				$page = & moon :: page();
				$page->page404();
		}
	}

	function properties()
	{
		return array('view' => '');
	}

	function main($vars) {
		$output = '';
		switch ($vars['view']) {
			case 'home-2col':
				$tpl = $this->load_template();
				$output = $tpl->parse('viewBanner:home-2col');
				break;
			case 'right1':
				$tpl = $this->load_template();
				$output = $tpl->parse('viewBanner:right1');
				break;
			case 'right2':
				$tpl = $this->load_template();
				$output = $tpl->parse('viewBanner:right2');
				break;
			default:
				break;
		}
		return $output;
	}
	
	function getUriList()
	{
		$sitemap = moon::shared('sitemap');
		$pageIds = array(
			'news',
			'video',
			'poker-players',
			'rules',
			'strategy',
			'rooms',
			'reporting'
		);
		$targets = array();
		foreach ($pageIds as $pageId) {
			$uri = $sitemap->getLink($pageId);
			if ($uri) {
				$targets['[' . $pageId . ']'] = $uri;
			}
		}
		return json_encode($targets);
	}

	function viewRedirectAd($mediaId, $campaignId, $zone)
	{
		$mediaId = intval($mediaId);

		$sql = 'SELECT	bm.site_id, b.room_id, r.alias AS roomUri, IF(bm.url != "", bm.url, b.url) AS redirectUrl,
		              		b.id as bannerId
		              	FROM '. $this->table('BannersMedia') . ' bm
		              		LEFT JOIN '. $this->table('Banners') . ' b ON b.id = bm.banner_id
		              		LEFT JOIN '. $this->table('Rooms') . ' r ON r.id = b.room_id
		              	WHERE bm.id = ' . $mediaId;
		$item = $this->db->single_query_assoc($sql);
		if (empty($item)) {
			return FALSE;
		}
		$this->setBannerClicked($item['bannerId'], $campaignId, $zone);

		$redirectUrl = '/';
		if ($item['redirectUrl'] !== '') {
			$redirectUrl = $item['redirectUrl'];

			// look if relative url
			if ($redirectUrl && strpos($redirectUrl, 'http://') === false && strpos($redirectUrl, 'https://') === false) {
				$redirectUrl = moon::page()->home_url() . ltrim($redirectUrl, '/');
			}
		} elseif ($item['roomUri'] !== NULL) {
			// get server home url
			$redirectUrl = moon::page()->home_url() . $item['roomUri'] . '/ext/'.$params;
		}
		$page = moon::page();
		$page->redirect($redirectUrl);
	}

	function setBannerClicked($bannerId, $campaignId, $zone)
	{
		/* can lose some clicks on hour change */
		$sql = 'UPDATE ' . $this->table('BannersStats') . '
			SET clicks = clicks+1
			WHERE	banner_id = ' . intval($bannerId) . '
			     	AND date = ' . floor(time() / 3600)*3600 . '
			     	AND campaign_id = ' . intval($campaignId) . '
			     	AND zone = "' . $this->db->escape($zone) . '"
			';
		$this->db->query($sql);
	}

	function setBannersViewed($data)
	{
		foreach ($data as $d) {
			print $sql = '
				INSERT DELAYED INTO ' . $this->table('BannersStats') . '
				(banner_id, campaign_id, site_id, zone, date, views, clicks)
				VALUES (' . intval($d['gid']) . ', ' . intval($d['cid']) . ', ' . intval($d['sid']) . ', "' . $this->db->escape($d['zone']) . '", ' . floor(time() / 3600)*3600 . ', 1, 0)
				ON DUPLICATE KEY UPDATE views=views+1
			';
			$this->db->query($sql);
		}
	}

	function getBanners()
	{
		$sql = '
			SELECT
				c.id as campaignId,
				c.geo_target,
				c.date_intervals,
				cb.uri_target,
				cb.zone_target,
				cb.views_limit,
				b.id as bannerId,
				b.title,
				b.type,
				b.room_id,
				b.url AS groupUrl,
				b.target_blank,
				b.img_alt,
				bm.id as mediaId,
				bm.site_id,
				bm.filename,
				bm.media_type,
				bm.media_width,
				bm.media_height,
				bm.alternative,
				bm.url,
				bm.created
			FROM ' . $this->table('Campaigns') . ' c
			  LEFT JOIN ' . $this->table('CampaignsBanners') . ' cb
			    ON c.id = cb.campaign_id
			  LEFT JOIN ' . $this->table('Banners') . ' b
			    ON cb.banner_id = b.id
			  LEFT JOIN ' . $this->table('BannersMedia') . ' bm
			    ON cb.banner_id = bm.banner_id
			WHERE	c.is_hidden = 0
			     	AND cb.is_hidden = 0
			     	AND b.is_hidden = 0
			     	AND bm.is_hidden = 0
			GROUP BY cb.id;';
		$result = $this->db->array_query_assoc($sql);
		
		$locale = &moon::locale();
		$nowDay = floor($locale->now() / 86400) * 86400;

		$banners = array();
		$bannerIds = array();
		$zones = $this->getZones();

		$activeRooms = $this->getActiveRoomsIds();

		foreach ($result as $r) {

			// filter by room (if banner room is not active for this site id - skip it)
			if (!empty($r['room_id']) && !in_array($r['room_id'],$activeRooms)) {
				continue;
			}

			// filter by date intervals
			if ($r['date_intervals']) {
				$skip = TRUE;
				$ranges = explode(';', $r['date_intervals']);
				foreach ($ranges as $range) {
					list($from, $to) = explode(',', $range);
					if ($nowDay >= $from && $nowDay <= $to) {
						$skip = FALSE;
						break;
					}
				}
				// skip the banner
				if ($skip) continue;
			}

			$banners[] = $r;
			$bannerIds[] = $r['mediaId'];
		}

		// get active banners view counts
		$viewCounts = array();
		/* views limit disabled
		$sql = 'SELECT ad_id, SUM(views) AS views
			FROM ' . $this->table('AdStats') . '
			WHERE ad_id IN (' . implode(',', $bannerIds) . ')
			GROUP BY ad_id';
		$result = $this->db->array_query_assoc($sql);
		foreach ($result as $r) {
			$viewCounts[$r['ad_id']] = $r['views'];
		}
		*/

		$items = array();
		shuffle($banners);
		foreach ($banners as $r) {

			// filter by views limit
			//if ($r['views_limit'] > 0 && isset($viewCounts[$r['id']]) && $viewCounts[$r['id']] >= $r['views_limit']) {
			//	continue; // over the view limits - skip the banner
			//}

			$banner = $this->getAdParams($r);
			$banner['id'] = $r['mediaId'];
			if ($r['uri_target']) {
				$banner['uri_target'] = trim(str_replace("\r", '', $r['uri_target']));
			}
			if ($r['geo_target']) {
				$banner['geo_target'] = $r['geo_target'];
			}
			if ($r['room_id']) {
				$banner['room_id'] = $r['room_id'];
			}

			//if ($r['priority'] > 0) {
			//	$banner['priority'] = $r['priority'];
			//}

			// same banner may be in more than one zone
			$zoneTarget = explode(',', $r['zone_target']);
			foreach ($zoneTarget as $zone) {
				if (!$zone) continue;
				// ad BN parameter
				$bnTmp = $banner;
				if (!empty($bnTmp['redirect_url']) && isset($zones[$zone])) {
					$glue = (strpos($bnTmp['redirect_url'], '?') !== false) ? '&' : '?';
					$par = $zones[$zone]['bn'];

					if ($bnTmp['type'] == 'flashXml' || $bnTmp['type'] == 'flash') {
						$bnTmp['redirect_url'] .= $glue . 'BN=' . $par . '&z=' . $zone;
						$bnTmp['redirect_url'] = urlencode($bnTmp['redirect_url']);
					} else {
						$bnTmp['redirect_url'] .= $glue . 'BN=' . $par . '&z=' . urlencode($zone);
					}

				}
				$items[$zone][] = $bnTmp;
			}
		}
		return $items;
	}

	function getAdParams($ad)
	{
		$params = array();
		$page = moon::page();
		$hasUrl = FALSE;
		$urlParam = array(
			'env' => $this->env,
			'cid' => $ad['campaignId']
		);

		if ($ad['room_id']) {
			$hasUrl = TRUE;
			$urlParam['BID'] = $ad['room_id'];
		} else {
			$urlParam['BID'] = 'B' . $ad['bannerId'];
		}
		if ($ad['url'] || $ad['groupUrl']) {
			$hasUrl = TRUE;
		}
		switch ($ad['type']) {
			case 'html':
				if (!$hasUrl) {
					$ad['alternative'] = str_replace('{url}', '', $ad['alternative']);
				} else {
					$ad['alternative'] = str_replace('{url}', $this->makeRedirectUrl($ad['mediaId'], $urlParam), $ad['alternative']);
				}
				$params += array(
					'text' => $this->escapeJs($ad['alternative']),
					'type' => 'html',

					// for views stats
					'cid' => $ad['campaignId'],
					'gid' => $ad['bannerId'],
					'sid' => $ad['site_id']
				);
				break;
			case 'media':
				if ($ad['media_type'] == 'image') {
					$ad['alternative'] = htmlspecialchars($ad['alternative']);
					$ad['alternative'] = str_replace("\n", '', $ad['alternative']);
					$ad['alternative'] = str_replace("\r", '', $ad['alternative']);
				}
				if (!$hasUrl) {
					$redirectUrl = '';
				} else {
					$redirectUrl = $this->makeRedirectUrl($ad['mediaId'], $urlParam);
				}
				switch ($ad['media_type']) {
					case 'flash':
						$params['type'] = 'flash';
						break;
					case 'image':
						if ($redirectUrl) {
							$params['type'] = 'imagec';
							$params['target'] = $ad['target_blank'] ? '_blank' : '_self';
						} else {
							$params['type'] = 'imagenc';
						}
						break;
				}
				$params += array(
					'redirect_url' => $redirectUrl,
					'width' => $ad['media_width'],
					'height' => $ad['media_height'],
					'timestamp' => $ad['created'],
					'path' => $page->home_url() . $this->get_var('srcBanners') . $ad['filename'],
					'alternative' => addslashes($ad['alternative']),

					// for views stats
					'cid' => $ad['campaignId'],
					'gid' => $ad['bannerId'],
					'sid' => $ad['site_id']
				);
				break;
			case 'flashXml':
				if (!$hasUrl) {
					$redirectUrl = '';
				} else {
					$redirectUrl = $this->makeRedirectUrl($ad['mediaId'], $urlParam);
				}

				$data = unserialize($ad['alternative']);
				if (empty($data[0]) || empty($data[1])) break; // !!

				$params = array(
					'type' => 'flashXml',
					'redirect_url' => $redirectUrl,
					'width' => $ad['media_width'],
					'height' => $ad['media_height'],
					'timestamp' => $ad['created'],
					'path' => $page->home_url() . $this->get_var('srcBanners') . $ad['filename'],

					'params' => 'f=' . $this->escapeJs($data[0]) . '&t=' . urlencode($this->escapeJs($data[1])),

					// for views stats
					'cid' => $ad['campaignId'],
					'gid' => $ad['bannerId'],
					'sid' => $ad['site_id']
				);
				break;
		}
		return $params;
	}

	function getActiveRoomsIds()
	{
		$sql = 'SELECT id
				FROM ' . $this->table('Rooms') . '
				WHERE is_hidden=0';
		$res = $this->db->array_query_assoc($sql, 'id');
		return !empty($res) ? array_keys($res) : array();
	}

	function getZones()
	{
		$zones = array();
		include_once(MOON_MODULES . '../modules_adm/banners/config.cfg.php');

		if (isset($cfg) && isset($cfg['banners'])) {
			$zones = $cfg['banners']['var.zones'];
		}
		return $zones;
	}

	function getTemplates()
	{
		$tpl = $this->load_template();
		$templates[] = "html:'"  .   $this->parseJs('bannerType:html', $tpl) . "'";
		$templates[] = "flash:'" .   $this->parseJs('bannerType:flash', $tpl) . "'";
		$templates[] = "flashXml:'" .$this->parseJs('bannerType:flashXml', $tpl) . "'";
		$templates[] = "imagec:'"  . $this->parseJs('bannerType:imagec', $tpl) . "'";
		$templates[] = "imagenc:'" . $this->parseJs('bannerType:imagenc', $tpl) . "'";
		return implode(',', $templates);
	}

	function parseJs($block, &$tpl)
	{
		$out = $tpl->parse($block);
		$out = $this->escapeJs($out);
		return $out;
	}

	function escapeJs($out)
	{
		$out = str_replace("\r", ' ', $out);
		$out = str_replace("\n", ' ', $out);
		$out = preg_replace("~\s+~", ' ', $out);
		$out = str_replace("'", "\'", $out);
		return $out;
	}

	function makeRedirectUrl($id, $params)
	{
		if (!$id) return '';
		static $homeUrl;
		if (is_null($homeUrl)) {
			$page = moon::page();
			$homeUrl = $page->home_url();
		}

		$url = $homeUrl . 'banners/view/?mid=' . $id;
		foreach ($params as $k=>$v) {
			$url .= '&' . $k . '=' . $v;
		}
		return $url;
	}

}
?>