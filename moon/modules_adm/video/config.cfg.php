<?php
include_once(MOON_MODULES . '../modules/video/config.cfg.php');
$cfgPub = $cfg['video'];
$cfg=array();
$cfg['video'] = array(
	'page.Common' => 'sys.adm,fake',
	
	'tb.Videos' => 'videos',
	'tb.VideosPlaylists' => 'videos_playlists',

	'dir.videoThumbDir' => _W_DIR_ . 'video/',
	'var.videoThumbSrc' => '/w/video/',

	'var.tokenRead' => $cfgPub['var.tokenRead'],
	'var.tokenWrite' => $cfgPub['var.tokenWrite'],
	'var.playerId' => $cfgPub['var.playerId']
);
?>