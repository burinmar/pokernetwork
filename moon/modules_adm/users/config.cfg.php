<?
$cfg = array();
$cfg['users'] = array(
	//'sys.moduleDir' => 'user/',
	'tb.Users' => 'users',
	'tb.Access' => 'users_access',
	'tb.GAccess' => 'users_global_access',
	'tb.Servers' => 'servers',
	'tb.UsedIP' => 'users_used_ip',

	'comp.login_object' => 'MoonShared.login_object',

	'page.Common' => 'sys.adm,fake',
	'page.Login' => 'login',
);
?>