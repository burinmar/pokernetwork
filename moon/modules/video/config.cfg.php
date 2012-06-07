<?php
$cfg['video'] = array(
	//'sys.multiLang' => 3,
	'vocabulary' => '{dir.multilang}{module}.txt;{dir.multilang}shared.txt',
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

?>
