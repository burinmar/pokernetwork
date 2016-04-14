<?php

if (!defined('NOCOOKIES')) {
	require_once(dirname(dirname(__FILE__)) . '/vb_moon.php');

	// navigation
	vB_Template::preRegister('header', array(
		'moon_header' => moon::engine()->call_component('sys.header')
	));

	// footer
	vB_Template::preRegister('footer', array(
		'moon_footer' => moon::engine()->call_component('sys.footer')
	));

	$template_hook['headinclude_bottom_css'] .= <<<'CSS'
		<link rel="stylesheet" href="/css/stylevbuletin.css?2" type="text/css" media="screen" />
CSS;

	if (!is_dev()) {
		$template_hook['headinclude_javascript'] .= <<<'JS'
			<script>
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
			ga('create', 'UA-5032249-2', 'pokernetwork.com');
			ga('require', 'displayfeatures');
			ga('require', 'linkid', 'linkid.js');
			ga('send', 'pageview', {gaUniversalCustomVars});
			</script>
JS;

		$template_hook['headinclude_javascript'] = str_replace(
			'{gaUniversalCustomVars}',
			json_encode(array(
				'dimension1' => moon::user()->get_user_id() ? 'Member' : 'Visitor',
				'dimension2' => 'Forum'
			)),
			$template_hook['headinclude_javascript']
		);
	}
}
