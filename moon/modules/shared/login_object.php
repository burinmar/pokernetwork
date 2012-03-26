<?php
class login_object extends moon_com{


function getPermissionGroups($group=false, $perm=false) {
	// pakeitus cia, pakeisti reikia ir cms/modules_adm/user.admins
	$roles = array(
		'' => array('content', 'tournaments', 'reviews', 'ads'),
		'administrator' => array('content','users', 'tournaments', 'reviews', 'ads')

	);
	//$roles['administrator'] = array_merge(array('users'), $roles['editor']);
	//ntsg team (tik tiems, kam leidzia audrius ;-) )
	$roles['developer'] = array_merge(array('developer'), $roles['administrator']);

	if ($group === false && $perm === false){
		return $roles;
	}elseif ($group !== FALSE){
		return empty($roles[$group]) ? array() : $roles[$group];
	}else{
		$gArr = array();
		foreach($roles as $k=>$v) {
			if($k !=='' && in_array($perm, $v)) $gArr[] = '@'.$k;
		}
		return $gArr;
	}
}


//*************************************
//             PUBLIC
//*************************************
function logout()
{
	$this->cookie('',time()-3600);
	$this->vbLogout();
}

function login($uName,$uPass,&$err) {
	if ($uName==='admin' && $uPass==='admin') {
		//laikinos galines durys
		$err = 0;
		$a = array('userid'=>-1,'username'=>'admin','admin'=>1,'email'=>'audrius.naslenas@ibus.lt');
		$this->loginUnconditional($a);
		return true;
	}
	$this->vbLogin($uName, $uPass, $err);
	$user =& moon::user();
	if ($id = $user->id()) {
		$code = $this->autologin_code($id, $user->get('email'));
		$lifetime=isset($_POST['remember']) ? 86400*200 : 3600*2 ; //300d : 2h
		$this->cookie( $code, time()+$lifetime);
		return true;
	}
	return false;
}

function vbLogout()
{
	include_class('moon_vb_relay');
	moon::user()->logout();
	moon_vb_relay::getInstance()->logout();
}

function vbLogin($uName, $uPass, &$err) {
	include_class('moon_vb_relay');
	$err = 1;
	$userInfo = moon_vb_relay::getInstance()->login($uName, $uPass);
	if (null != $userInfo) {
		$err = 0;
		$this->loginUnconditional($userInfo);
	}
	return false;
}

function loginUnconditional($userInfo)
{
	moon::user()->login(array(
		'id'    => intval($userInfo['userid']),
		'nick'  => $userInfo['username'],
		'admin' => $this->have_permissions($userInfo['userid']),
		'email' => $userInfo['email'],
	));
	if (!empty($userInfo['userid']) && !empty($userInfo['email'])) {
		// setlast used ip
		$ip = moon::user()->get_ip();
		$this->db->replace(
				array('user_id'=>$userInfo['userid'], 'ip'=>$ip, 'created'=>gmdate('Y-m-d')),
				'users_used_ip'
				);
	}
}

//pagal cookie admdaliai, swfupload
function autologin($cookie)
{
	$userID= ($pos=strpos($cookie,'_')) ? intval(substr($cookie,$pos+1)):0;
	if ($userID) {
		$uInfo = moon::db('database-vb')->single_query_assoc('
			SELECT userid , username, email FROM vb_user WHERE userid = ' . intval($userID)
			);
		if (is_array($uInfo) && $this->autologin_code($userID, $uInfo['email'])===$cookie) {
			$this->loginUnconditional($uInfo);
			return true;
		}
	}
	$this->cookie('0',time()-25*3600);//istrinam
	return false;
}

function refresh()
{
	$u=&moon::user();
	if (($id=$u->get_user_id()) &&  $id!==-1) {
		$has=$this->have_permissions($id);
		$u->set_user('admin',(count($has) ? $has:0));
	}
}

//*************************************
//             PRIVATE
//*************************************

function autologin_code($id, $email)
{
   return abs(sprintf('%u',crc32($id.$email."a+ju:g'c32"))).'_'.$id;
}


function have_permissions($id, $access = FALSE) {
	if ($id == - 1) {
		return 1;
	}
	if ($access === FALSE) {
		$sql = "SELECT `access` FROM " . $this->table('Users') . " WHERE id=" . intval($id);
		$dat = $this->db->single_query($sql);
		$access = empty ($dat[0]) ? '':$dat[0];
	}
	$m = array();
	$dat = $access === '' ? array():explode(',', $access);
	foreach ($dat as $v) {
		if ($v[0] === '@') {
			$m = array_merge($this->getPermissionGroups(substr($v, 1)), $m);
		}
		else {
			$m[] = $v;
		}
	}
	//reikia perdaryt i_admin funkcijai
	$n = array();
	foreach ($m as $v) {
		$n[$v] = 1;
	}
	return $n;
}

function cookie($value, $time)
{
	setcookie ('pnlg',$value,$time,'/');
}


}
?>