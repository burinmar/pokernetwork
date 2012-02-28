<?php
$cfg = array();
$cfg['articles'] = array(
	
	//'sys.multiLang' => 3,
	//'vocabulary' => '{dir.multilang}{module}.txt;{dir.multilang}shared.txt;{dir.multilang}reviews.txt',

	'page.1col' => 'xml.col1_darkright,fake',
	'page.2col' => 'xml.2col,fake',

	'tb.Authors' => 'articles_authors',
	'tb.Articles' => 'articles',
	'tb.Categories' => 'articles_categories',
	'tb.Attachments' => 'articles_attachments',
	'tb.Turbo' => 'articles_turbo',
	'tb.TurboAttachments' => 'articles_turbo_attachments',
	'tb.Tags' => 'articles_tags',
	'tb.Editors' => 'articles_authors',

	'comp.rtf' => 'MoonShared.rtf',

	'var.suffixStartId' => is_dev() ? 26550 : 1,
	'var.addToSuffix' => 1000,
	'var.imagesSrcArticlesStd' => '/w/articles/img/',
	'var.typeNews' => 1,
	//'var.imgSrcEditors' => '/w/editors/',
	//'var.imgEditorsDefault' => '/img/avatar200.png',

	// Comments
	'comp.comments' => 'MoonShared.comments',
	'tb.Comments' => 'articles_comments',
	'tb.CommentsParent' => 'articles',
	'tb.Users' => 'users'

);
?>