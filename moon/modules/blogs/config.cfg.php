<?php
$cfg = array();
$cfg['blogs'] = array(
	'page.Main' => 'xml.2col,fake',
	'page.1col' => 'xml.2col,fake',

	'tb.Posts' => 'blog_posts',
	'tb.Bodies' => 'blog_posts_bodies',
	'tb.Users' => 'users',
	
	'var.srcDefaultAvatar' => '/img/avatar100.png',
	
	'tb.Comments' => 'blog_comments',
	'tb.CommentsParent' => 'blog_posts',
	
	'comp.login_object' => 'MoonShared.login_object',
	'comp.rtf' => 'MoonShared.rtf',
	'var.rtf' => 'blog_post',
    'var.rtfComment' => 'blog_post_comment',
	
	'vocabulary'=>'{dir.multilang}{module}.txt',
	'vocabulary{rtf}' => '{dir.multilang}shared.txt',

	// Comments
	'comp.blogcomments' => 'MoonShared.comments',
	'tb.Comments' => 'blog_comments',
	'tb.CommentsParent' => 'blog_posts',
	'tb.Users' => 'users'
);
?>