<?php
$typeNews = 1;
$cfg = array();
$cfg['articles'] = array(
	'page.Common' => 'sys.adm,fake',

	'comp.rtf' => 'MoonShared.rtf',
	'var.rtf' => 'articles',
	'var.rtf{blogs_import}' => 'blog_post',
	'var.rtf{authors}' => 'texts',

	//News component
	'comp.news' => 'articles.articles',
	'var.articlesType{news}' => $typeNews,
	'var.articlesType{turbo}' => $typeNews,
	//News categories component
	'comp.categories_news' => 'articles.categories',
	'var.articlesType{categories_news}' => $typeNews,

	'var.typeNews' => $typeNews,

	'tb.Articles' => 'articles',
	'tb.Authors' => 'articles_authors',
	'tb.ArticlesCategories' => 'articles_categories',
	'tb.ArticlesAttachments' => 'articles_attachments',
	'tb.Turbo' => 'articles_turbo',
	'tb.ArticlesTurboAttachments' => 'articles_turbo_attachments',
	'tb.Tags' => 'articles_tags',
	'tb.Rooms' => 'rw2_rooms',

	'var.imagesSrcArticlesStd' => '/w/articles/img/',
	'dir.imagesDirArticlesStd' => _W_DIR_ . 'articles/img/',

	'var.imagesSrc' => '/w/editors/',
	'dir.imagesDir' => _W_DIR_ . 'editors/',

	'var.articlesSuffixStartId' => 1
);