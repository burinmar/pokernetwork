<?php
/**
 * https://developers.google.com/youtube/js_api_reference
 * http://flowplayer.org/documentation/api/player.html
 * http://www.longtailvideo.com/support/open-video-ads/25373/examples
 */
class shared_pnplayer
{
	private $tpl;
	private $embeddedHead = false;
	function shared_pnplayer($path)
	{
		$moon = moon::engine();
		$this->tpl = $moon->load_template(
			$path . 'shared_pnplayer.htm'
			/*,$moon->ini('dir.multilang').'shared/shared_paginate.txt'*/
		);
	}

	public function getHtml($videoUri, $videoLength = 0, $preset = 'default', $playlist = null, $ad = array(), $thumbnailSrc = null) 
	{
		$this->setHtmlEnv();

		$tplArgs = $this->loadPreset($preset);
		$tplArgs['videoUri'] = htmlspecialchars($videoUri);
		$tplArgs['videoLength'] = htmlspecialchars($videoLength);
		$tplArgs['videoThumb'] = htmlspecialchars($thumbnailSrc);

		if (strlen($videoUri) != 11) { // not youtube
			if ($ad === null)
				$ad = array();
			$playlist = null;
		} elseif ($thumbnailSrc == '') { // youtube and auto thumbnail
			$tplArgs['videoThumb'] = 'http://i.ytimg.com/vi/' . $tplArgs['videoUri'] . '/0.jpg';
		}

		$tplArgs['ad'] = $ad !== null;
		$tplArgs['adArgs'] = '';
		if (is_array($ad)) {
		foreach ($ad as $adArgName => $adArgVal) {
			$tplArgs['adArgs'] .= $this->tpl->parse('player:adArgs.item', array(
				'name' => htmlspecialchars($adArgName),
				'value' => htmlspecialchars($adArgVal),
			)) . ' ';
		}}

		$tplArgs['playlist'] = '';
		if (is_array($playlist)) {
		foreach ($playlist as $playlistItem) {
			$tplArgs['playlist'] .= $this->tpl->parse('player:playlist.item', array(
				'videoUri' => htmlspecialchars($playlistItem[0]),
				'videoLength' => htmlspecialchars($playlistItem[1]),
				'videoTitle' => htmlspecialchars($playlistItem[2]),
				'videoPageUri' => htmlspecialchars($playlistItem[3]),
			));
		}}

		return $this->tpl->parse('player', $tplArgs);
	}

	public function getDefaultAdsConfig()
	{
		$zone = 3;
		switch (_SITE_ID_) {
		case 'it':
			$zone = 4;
		default:
			switch (geo_my_country()) {
			case 'us':
				$zone = 5;
				break;
			}
		}
		return array('zone' => $zone);
	}

	private function loadPreset($preset)
	{
		$tpl = $this->tpl;
		if (!$tpl->has_part('preset:' . $preset))
			$preset = 'default';

		return $tpl->parse_array('preset:' . $preset);
	}

	private function setHtmlEnv()
	{
		if ($this->embeddedHead) {
			return;
		}
		moon::page()->js('/js/swfobject.js');
		moon::page()->js('/js/pnplayer.js');
		moon::page()->css('/css/pnplayer.css');
		$this->embeddedHead = true;
	}
}