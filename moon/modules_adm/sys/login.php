<?php
class login extends moon_com{

function main($vars)
{
	$p=&moon::page();
	$p->set_local('output','none');
	$t=$this->load_template();
	$err= isset($vars['error']) ? intval($vars['error']):0;
	$m=array();
	if ($err){//jei buvo klaida
		$data=&$_POST;
		$errors=$t->explode_ini('errors');
		$m['error']=$errors['err'.$err]; 
	}else{
		$data=array('username'=>'','pass'=>'');
		if (isset($_COOKIE['ausername'])) $data['username']=$_COOKIE['ausername'];
	}
	$m['event']=$this->my('fullname').'#login';
	$m['home_url']=$p->home_url();
	$m['username']=htmlspecialchars($data['username']);
	$m['pass']='';//htmlspecialchars($data['pass']);
	$res=$t->parse('main',$m);
	return $res;
}

function events($event)
{
	switch ($event) {
	case 'login':
    	$u=&moon::user();
		if (!$u->get_user_id() && isset($_POST['username']) && isset($_POST['pass'])) {
			if (is_object($loginObj=$this->object('login_object'))) {
				$loginObj->login($_POST['username'],$_POST['pass'],$err);
				if ($err) {
					$this->set_var('error',$err);
					$this->use_page('Login');
					break;
				} elseif(!$u->i_admin()) {
                	$loginObj->logout();
					$this->set_var('error',2);
					$this->use_page('Login');
					break;
				}
			}
		}
        $p=&moon::page();
		$p->back(true);
		break;
    case 'logout':
        $u=&moon::user();
		if (is_object($loginObj=$this->object('login_object')))
			$loginObj->logout();
        $p=&moon::page();
		$p->back(true);
		break;
    case 'recheck':
		$u=&moon::user();
		if ( ($uid=$u->get_user_id()) && $uid!==-1) {
        	$has=$this->have_permissions($uid);
            if (count($has)) $u->set_user('admin',$has);
			else $u->set_user('admin',0);
		}
		return;
	default:;
	}
	$this->forget();
}

//*************************************
//             OTHER
//*************************************



}
?>
