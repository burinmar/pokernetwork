<?php
//***************************************
//           --- rtf.php instances ---
//***************************************

//default settings
$cfg = array(
	//table for storing attachments
	'tableObjects' => '',
	//table for storing original source
	'tableSource' => '',
	//writable directory, where to put files on disk
	'fileDir' => '',
	//file uri for "href" or "src"
	'fileSRC' => '',
	//allowed file extensions
	'fileExt' => 'jpg,gif,png,pdf,mp3,doc,docx,odt,xls',
	// pvz. 1024, 1KB, 1MB    (taciau <=10MB )
	'fileMaxSize' => '5MB',
	// width x height, resize uploaded images if larger  (>=200x200)
	'imgOrigWH' => '1024x1000',
	// width x height, max image size in page   (>=50x50)
	'imgWH' => '530x400',
	// supported features
	'features' => '-code,-smiles,-underline,-cards,-timer,-img,-video,-spoiler,-game,-twitter',
	// supported attachments: file, image, video, html, image+
	'attachments' => '',
	//kokius defaultinius nustatymus perrasyti  shared_text
	'parserFeatures' => array(),
	//komponento pavadinimas, kuris turi metoda canEdit(), pvz. news.full
	'canEdit' => '',
);

if (strpos($instance, '~')) {
	list($instance, $par) = explode('~', $instance);
} else {
	$par = '';
}

//custom settings
switch ($instance) {

	//news
	case 'articles' :
		$cfg['parserFeatures'] = array('replace' => array('h' => 'h2'), 'allowScript' => TRUE);
		$cfg['features'] = '-code,-smiles,-underline,-img,-video,-spoiler,-game,twitter';
		$cfg['tableObjects'] = 'articles_attachments';
		$cfg['fileDir'] = _W_DIR_ . 'articles/att/';
		$cfg['fileSRC'] = '/' . _W_DIR_ . 'articles/att/';
		$cfg['attachments'] = 'image+, video, html';
		break;

	case 'sitemap' :
		$cfg['parserFeatures'] = array('replace' => array('h' => 'h2'));
		$cfg['features'] = '-code,-smiles,-underline,-timer,-img,-video,-spoiler,-cards,-game,-twitter';
		$cfg['tableObjects'] = 'sitemap_attachments';
		$cfg['fileDir'] = 'w/sitemap/';
		$cfg['fileSRC'] = '/w/sitemap/';
		$cfg['attachments'] = 'image+, video, html';
		break;

	case 'reviews' :
		$cfg['parserFeatures'] = array('replace' => array('h' => 'h2'));
		$cfg['features'] = '-quote,-code,-smiles,-underline,-cards,-img,-video,-spoiler,-game,-twitter';
		$cfg['tableObjects'] = 'rw2_attachments';
		$cfg['fileDir'] = _W_DIR_ . 'rw-attachments/';
		$cfg['fileSRC'] = '/w/rw-attachments/';
		$cfg['attachments'] = 'image+, video, html';
		$cfg['imgWH'] = '380x600';
		break;

	case 'freeroll' :
		$cfg['parserFeatures'] = array('replace' => array('h' => 'h2'), 'allowScript' => TRUE);
		break;

}
?>