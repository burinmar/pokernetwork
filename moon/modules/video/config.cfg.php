<?php
$cfg['video'] = array(
	//'sys.multiLang' => 3,
	'vocabulary' => '{dir.multilang}video.txt;{dir.multilang}shared.txt',
	'page.Main' => 'xml.2col,fake',
	'tb.Videos' => 'videos',
	'tb.VideosPlaylists' => 'videos_playlists',
	'var.tokenRead' => 'VnwZdwExhBJ6MtwQOWDn4HP1APBKaU-kPR3HSx_X3uk.',
	'var.tokenWrite' => '9r2JKJdzYqQYraoIJUoQDiQprc9s4UKD_K7hpaAHFddjOkkzewf80w..',
	// Comments
	'comp.comments' => 'MoonShared.comments',
	'tb.Comments' => 'videos_comments',
	'tb.CommentsParent' => 'videos',
	'tb.Users' => 'users'
);

	$cfg['video'] = array_merge($cfg['video'], array(
		'comp.video' => 'video2',
		'tb.Videos' => 'video2',
		'tb.VideosPlaylists' => 'video2_categories',
		'tb.Comments' => 'video2_comments',
		'tb.CommentsParent' => 'video2',
	));

$playerId = 0;
switch (_SITE_ID_) {
	case 'bg':
		$playerId = '46544417001';
		break;
	case 'br':
		$playerId = '607023609001';
		break;
	case 'de':
		$playerId = '57825944001';
		break;
	case 'hu':
		$playerId = '57476345001';
		break;
	case 'es':
		$playerId = '57487255001';
		break;
	case 'nl':
		$playerId = '57487256001';
		break;
	case 'fr':
		$playerId = '57487257001';
		break;
	case 'ru':
		$playerId = '57825943001';
		break;
	case 'ua':
		$playerId = '930067606001';
		break;
	case 'ukraina':
		$playerId = '57825943001';
		break;
	case 'it':
		$playerId = '57476346001';
		break;
	case 'ee':
		$playerId = '61485831001';
		break;
	case 'uk':
		$playerId = '111717895001';
		break;
	case 'gr':
		$playerId = '111993196001';
		break;
	case 'lt':
		$playerId = '106428004001';
		break;
	case 'pl':
		$playerId = '695992379001';
		break;
	case 'kr':
		$playerId = '801013620001';
		break;
	case 'zh':
		$playerId = '801013621001';
		break;
	case 'lv':
		$playerId = '817524479001';
		break;
	case 'balkan':
		$playerId = '931359209001';
		break;
	case 'com':
	default:
		$playerId = '35625874001'; //3230433001
		break;
}
$cfg['video']['var.playerId'] = $playerId;
?>
