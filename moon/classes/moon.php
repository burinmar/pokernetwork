<?/*************************************************
modified: 2013-12-19 10:10
version: 2.14a
project: Moon
author: Audrius Naslenas, a.naslenas@gmail.com
*************************************************/
if (!defined('MOON_CLASSES')) define('MOON_CLASSES',dirname(__FILE__).'/');
function include_class($name)
{switch($name){
case 'moon': break;
case 'moon_engine': break;
case 'moon_ini': break;
case 'moon_cfg': break;
case 'moon_com': break;
case 'moon_error': break;
case 'moon_locale': break;
case 'moon_log': break;
case 'moon_user': break;
case 'moon_page': break;
case 'moon_template': break;
case 'moon_xml': break;
case 'moon_cache': break;
case 'moon_file': break;
case 'moon_xml_read': break;
case 'moon_xml_write': break;
case 'moon_paginate': break;
case 'mysql': break;
default:
include_once(MOON_CLASSES.$name.'.php');
}}function moon_headers($sendHeaders=true)
{if (!$sendHeaders){
if (!defined('MOON_HEADERS')) define('MOON_HEADERS',0);
}}function moon_init($iniFile,$engineIniGroup='engine')
{if (!class_exists('moon')) {
class moon extends moon_core{}
}$gini=&moon::moon_ini();
if (is_array($iniFile)) $gini->load_array($iniFile);
else $gini->load_file($iniFile);
$eng=&moon::engine();
$eng->read_ini($engineIniGroup);
if (!defined('MOON_MODULES')) {
if (!($modDir=$eng->ini('modules.dir'))) $modDir='modules/';
define('MOON_MODULES',(string)$modDir);
}$loc=&moon::locale();
if (!($localeFile=$eng->ini('locale.file')))  $localeFile=MOON_CLASSES.'locale.ini';
$loc->load_file($localeFile);
$loc->set_locale($eng->ini('locale'));
if ($sid=$eng->ini('session.name')) {
session_name ($sid);
if ( isset($_GET[$sid]) ) $id=session_id($_GET[$sid]);
session_start();
}$ini=&moon::cfg();
$ini->read_cfg($eng->ini('modules.cfg'));
$p=&moon::page();
$tl=$p->pause_lasted();
$l=&moon::log();
if ($tl!==false) $l->log_it('TL',$tl);
}function &moon_process()
{$engine=&moon::engine();
$htm=$engine->process();
return $htm;
}function moon_close(){
$u=&moon::user();
$u->dump_to_session();
$eng=&moon::engine();
if ($dirLog=$eng->ini('dir.logs')) {
$l=&moon::log();
$l->save($dirLog.date('ymd').'.log',true);
}$err=&moon::error();
if ($eng->ini('error.display')) $err->show();
if ($errFile=$eng->ini('error.file')) {
$p=&moon::page();
if ($ev=$p->requested_event('POST')) $ev='POST '.$ev;
else $ev=rtrim($p->requested_event('GET').', '.$p->requested_event('param'),', ');
$err->save($errFile," [$ev]");
}$p=&moon::page();
$p->close();
}class moon_core {
static function &moon_ini()
{if (is_null($out = &$GLOBALS['MoonGlobalIni'])) {
include_class('moon_ini');
$out=new moon_ini();
}return $out;
}static function &ini() {return moon::cfg();}
static function &cfg()
{if (is_null($out = &$GLOBALS['MoonIni'])) {
include_class('moon_cfg');
$out=new moon_cfg();
}return $out;
}static function &page()
{if (is_null($out = &$GLOBALS['MoonPage'])) {
include_class('moon_page');
$out=new moon_page();
}return $out;
}static function &engine()
{if (is_null($out = &$GLOBALS['MoonEngine'])) {
include_class('moon_engine');
$out=new moon_engine;
}return $out;
}static function &user()
{if (is_null($out = &$GLOBALS['MoonUser'])) {
include_class('moon_user');
$out=new moon_user;
}return $out;
}static function &error($msg=FALSE, $type = 'W')
{if (is_null($out = &$GLOBALS['MoonError'])) {
include_class('moon_error');
$out=new moon_error;
}if (FALSE !== $msg) {
$out->error($msg, $type);
}return $out;
}static function &locale()
{if (is_null($out = &$GLOBALS['MoonLocale'])) {
include_class('moon_locale');
$out=new moon_locale;
}return $out;
}static function &log()
{if (is_null($out = &$GLOBALS['MoonLog'])) {
include_class('moon_log');
$out=new moon_log;
}return $out;
}static function &cache($name='default')
{if (is_null($out = &$GLOBALS['MoonCache_'.$name])) {
include_class('moon_cache');
$out=new moon_cache($name);
}return $out;
}static function &xml_read()
{include_class('moon_xml_read');
$a=new moon_xml_read;
return $a;
}static function &xml_write()
{include_class('moon_xml_write');
$a=new moon_xml_write;
return $a;
}static function &file()
{include_class('moon_file');
$a=new moon_file;
return $a;
}static function &template($tplFile, $zodFile='', $zodLang = FALSE)
{$t = new moon_template($tplFile, $zodFile, $zodLang);
return $t;
}static function &mail()
{include_class('moon_mail');
$a=new moon_mail;
return $a;
}static function &shared($name='')
{$out =&$GLOBALS['MoonShared_'.$name];
if (is_null($out)) {
$cname='shared_'.$name;
$cfg=&moon::cfg();
$whereIs = $cfg->has('MoonShared','sys.moduleDir') ? $cfg->get('MoonShared','sys','moduleDir') : 'moon/';
$fname=MOON_MODULES.$whereIs.$cname.'.php';
if (file_exists($fname)){
include_once($fname);
$out=new $cname(MOON_MODULES.$whereIs);
}} elseif (method_exists($out,'init')) $out->init();
return $out;
}static function &db($con='')
{$out = &$GLOBALS['MoonDB'.$con];
if (is_null($out)) {
$ini =&moon::moon_ini();
if ($con === '') {
$engine = & moon :: engine();
$con = $engine->ini('db');
}$group = $con==='' ? 'database':$con;
$out = '';
if ($ini->has($group,false)){
if ($ini->has($group,'dbclass')) $name=strtolower($ini->get($group,'dbclass'));
else $name= $ini->has($group,'dbtype') ? 'db_'.strtolower($ini->get($group,'dbtype')) : 'db_mysql';
if (!class_exists($name) && file_exists(MOON_CLASSES.$name.'.php')) include_class($name);
if (class_exists($name)) {
$out=new $name;
$out->moon_connect($ini->read_group($group));
}}}return $out;
}static function chmod($filename, $mode = FALSE)
{if ($mode === FALSE) {
switch (filetype($filename)) {
case 'dir': $mode = 0777; break;
case 'file': $mode = 0666; break;
default: return FALSE;
}}$old = umask(0);
$ok = chmod($filename, $mode);
umask($old);
if (!$ok) {
moon::error('Unable to chmod ' . $filename);
}return $ok;
}}class moon_engine {
var $loadedComponents;
var $components;
var $pg,$pgCompRemove,$pgCompInsert;
var $layout;
var $eventDisabled;
var $halt,$haltPar;
var $dirCache;
var $unknownModule;
var $iniData;
var $content;
function moon_engine($options='')
{$this->iniData=array();
$this->loadedComponents=array();
$this->components=array();
$this->layout='';
$this->pg=$this->pgCompRemove='';
$this->halt='';
$this->dirCache='';
$this->eventDisabled=false;
$this->unknownModule='sys';
$this->txtConstants=array();
$this->debugOn=false;
$this->content=array();
}function process()
{include_class('moon_com');
include_class('moon_template');
$this->dirCache=$this->ini('dir.chtm');
$p=&moon::page();
$home=$p->home_url();
$this->urlPrefix=$this->ini('url.prefix');
$self=$this->ini('php_script');
if ($this->urlPrefix==='') {
$a=parse_url($home);
$this->urlPrefix = isset($a['path']) ? $a['path'] :'/';
} elseif ($this->urlPrefix[0]!=='/') $this->urlPrefix=rtrim(dirname($self),'/').'/';
$this->eventsMap=array();
if ($mapFile=$this->ini('url.map')) {
$loc=&moon::locale();
$gr=$loc->current_locale();
$f=new moon_ini($mapFile);
if (!$f->has($gr,false))
$gr= count($groups=$f->read_groups()) ? $groups[0]:'?';
$evMap=$f->read_group($gr);
$this->eventsMap=array();
foreach ($evMap as $k=>$v) {
$in = substr($k,-1)==='*' ? -2 : -1;
if (substr($k,$in,1)==='#') $k=substr_replace($k,'',$in,1);
$this->eventsMap[$k]=ltrim($v,'/');
}}if ($this->ini('constants')) {
$const=new moon_ini($this->ini('constants'));
if ($const->has('txt')) $this->txtConstants=$const->read_group('txt');
if ($const->has('php')) {
$c=$const->read_group('php');
foreach ($c as $k=>$v)
if (!defined((string)$k)) {
if (is_numeric($v)) define((string)$k,(int)$v);
else define((string)$k,(string)$v);
}}}$uri=(isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI']:'';
list($uri)=explode('?',$uri);
$uri = urldecode($uri);
if ($uri==$this->ini('php_script')) $uri='';
elseif (strpos(strtolower($uri),strtolower($this->urlPrefix))!==false) $uri=substr($uri,strlen($this->urlPrefix));
$p->analyze_request($uri);
$ev=$p->requested_event();
if ($startup=$this->ini('startup')) $this->call_event($startup);
if (strlen($ev)) {
$logas=&moon::log();
$logas->event($ev.'|'.$p->requested_event('param'));
$ok=$this->call_event($ev,$p->requested_event('param'));
if ($ok===false && ($ev404=$this->ini('event404'))) $this->call_event( $ev404, $ev );
}$content=$this->_construct_content();
if ($this->halt) {
$p->forget();
$this->pgCompRemove='';
$this->eventDisabled=false;
$this->content=array();
$this->call_event($this->halt,$this->haltPar);
$this->halt='';
$content=$this->_construct_content();
}$s='';
$s=$this->call_component($this->ini('output'), $content);
return $s;
}function call_component($name,$vars='')
{$comp=&$this->_load_component($name);
if (!is_object($comp)) {
$this->_error('bad_component','W',__LINE__,array('name'=>$name));
return '';
}if (!is_array($vars)) $vars=array();
$vars += $comp->my('vars');
$p=&moon::page();
if ($comp->my('name')=='html' && $comp->my('module')=='sys') {
$failas=(isset($vars['file']) ) ? trim($vars['file']):'';
$linkai=(isset($vars['links']) && $vars['links']) ? true:false;
$out=$p->html($failas,$linkai);
} else {
$out=$comp->main($vars);
$p->save_stack($this->pg,$comp->my('fullname'),$comp->save_vars());
}if ($this->debugOn) return "\n\n<!-- ** MOON ** begin $name (".$comp->my('file').") -->\n".$out."\n<!-- ** MOON ** end $name -->\n\n";
else return $out;
}function call_event($name,$vars='')
{if ($name==='') return '';
if ($this->eventDisabled) {
$this->eventDisabled=false;
return '';
}$compR=$this->_comp_info($name);
$this->unknownModule=$compR['module'];
$name=$this->find_alias($name);
$d=explode('#',$name);
if (!isset($d[1])) $d[1]='';
$comp=&$this->_load_component($d[0]);
if (!is_object($comp)) {
$this->_error('bad_event','N',__LINE__,array('name'=>$name));
return false;
}if ($vars=='') $vars==null;
elseif (is_scalar($vars)) $vars=explode('.',$vars);
$comp->events($d[1],$vars);
return true;
}function disable_event()
{$this->eventDisabled=true;
}function halt($event,$par='')
{$this->halt=trim($event);
$this->haltPar=trim($par);
}function read_ini($group)
{$gini=&moon::moon_ini();
$this->iniData=$gini->read_group($group);
$this->iniData['#iniGroupName']=$group;
}function ini($name)
{if (!isset($this->iniData[$name])) {
switch ($name) {
case 'url_function': $s=false; break;
case 'php_script': $s=$_SERVER['SCRIPT_NAME']; break;
case 'dir_html': $s=false; break;
case 'dir.cxml': $s=false; break;
case 'dir.cache': $s=false; break;
default: $s='';
}$this->iniData[$name]=$s;
}return $this->iniData[$name];
}function ini_set($name,$value)
{$this->iniData[$name]=$value;
}function find_alias($name,$pref='ev')
{if ($pref==='ev') {
$ed=explode('#',$name);
$comp=$ed[0];
if (isset($ed[1])) return $name;
} else $comp=rtrim($name,'#');
if ($id=strrchr($comp,'.')){
$id=substr($id,1);
if (is_numeric($id)) $comp=substr($comp,0,strlen($comp)-strlen($id)-1);
else $id=0;
}else $id=0;
$ini=&moon::cfg();
$ev=explode('.',$comp);
if (count($ev)<2) {$ev[1]=$ev[0];$ev[0]=$this->unknownModule;}
$a=$ini->get($ev[0],$pref);
if (is_array($a) && isset($a[$ev[1]])) {
$comp=$a[$ev[1]];
if (!strpos($comp,'.')) $comp=$ev[0].'.'.$comp;
}if ($id) $comp.='.'.$id;
return $comp;
}function &_load_component($name)
{$vname=strtolower(trim($name));
$compV=$this->_comp_info($vname);
$name=$this->find_alias($vname,'comp');
$compR=$this->_comp_info($name);
$compMod=$compR['module'];
$compName=$compR['name'];
$comIndex=$compMod.'-'.$compName;
if (!isset($this->loadedComponents[$comIndex])) {
if ($comIndex=='sys-html') $comp=new moon_com('','sys','html',0);
else {
$comp=false;
$ini=&moon::cfg();
$u=&moon::user();
$allow=true;
if (($adm=$ini->get($compV['module'],'admin','')) && $adm!=='*') $allow=$u->i_admin($adm);
if ($adm=$ini->get($compV['module'],'admin',$compV['name']))
$allow=$adm==='*' ? true : $u->i_admin($adm);
$sysMas=$ini->get($compMod,'sys');
if ($allow && isset($sysMas['moduleDir'])) {
$fileName=MOON_MODULES.$sysMas['moduleDir'].$compName.'.php';
if (file_exists($fileName)) {
include_once($fileName);
if (class_exists($compName)) {
$comp=new $compName($compMod,$compName,$sysMas['moduleDir']);
}}}}$this->loadedComponents[$comIndex]=$comp;
}if (is_object($this->loadedComponents[$comIndex]))
$this->loadedComponents[$comIndex]->init($compV['module'],$compV['name'],$compR['id']);
return $this->loadedComponents[$comIndex];
}function _comp_info($name)
{if ($id=strrchr($name,'.')){
$id=substr($id,1);
if (is_numeric($id)) $name=substr($name,0,strlen($name)-strlen($id)-1);
else $id=0;
}else $id=0;
$tsk=strpos($name,'.');
$compMod=($tsk===false) ? $this->unknownModule:substr($name,0,$tsk);
$compName=($tsk===false) ? $name:substr($name,$tsk+1);
return array('module'=>$compMod,'name'=>$compName,'id'=>$id);
}function &load_template($fileName,$langFile='')
{return moon::template($fileName,$langFile,moon::locale()->current_locale());
}function map_find_alias($event)
{if (isset($this->eventsMap[$event])) return $this->eventsMap[$event];
else return false;
}function map_find_event($uri)
{$ev=$par=$mask=false;
foreach ($this->eventsMap as $k=>$v) {
if (strpos($uri,$v)===0) {
$par=trim(substr($uri,strlen($v)));
if (substr($v,-1)!=='/' && $par!=='' && $par[0]!=='/') {
continue;
}$in = substr($k,-1)==='*' ? -2 : -1;
if (substr($k,$in,1)==='#') {
$k=substr_replace($k,'',$in,1);
}$ev=$k;
$mask = $r = strlen($par) ? substr($uri,0,-strlen($par)) : $uri;
$par = ltrim($par,'/');
if (substr($ev,-1)==='*') {
$ev=trim($ev,'*');
if ($k=strpos($par,'/')) {
$ev.='#'.substr($par,0,$k);
$par=substr($par,$k+1);
}}break;
}}return array($ev,$par,$mask);
}function url_parse($url)
{if (isset($_GET['url_'])) $url = $_GET['url_'];
elseif (isset($_GET['replace_'])) $url = $_GET['replace_'];
else {
list($url)=explode('?',$url);
if ($url==='') return array('','');
}$url=ltrim($url,'/');
if ($url==='') return array('','');
list($ev,$par,$mask)=$this->map_find_event($url);
if ($ev===false) {
$kiek=count($d=explode('/',$url));
$page = & moon :: page();
$mask = $d[0] . (isset($d[1]) ? '/' : '');
$page->set_request_mask($mask,substr($url,strlen($mask)));
if ($kiek==2 && substr($d[$kiek-1],-4)=='.htm') $par=array_pop($d);
$ev=array_shift($d);
$ev= strpos($ev,'-') ? str_replace('-','.',$ev) : 'sys.'.$ev;
$ev.= count($d) ? rtrim('#'.array_shift($d),'#') : '';
if ($par!==false) $d[]=$par;
$par=implode('/',$d);
} else {
$page = & moon :: page();
$page->set_request_mask($mask,substr($url,strlen($mask)));
}if (substr($par,-4)=='.htm') $par=substr($par,0,-4);
return array($ev,$par);
}function url_construct($event,$par)
{$uri = $sev = $get='';
if (FALSE !== strpos($event, '?')) {
list($event, $get) = explode('?', $event, 2);
}if ($event!='') {
list($ev, $sev) = explode('#', $event . '#');
if (strpos($ev, '.')) {
list($mod, $com)=explode('.',$ev);
}else {
$mod = 'sys';
$com = $ev;
}if (($uri = $this->map_find_alias(rtrim($ev.'#'.$sev,'#'))) !== false) {
$sev = '';
}else {
(($uri = $this->map_find_alias($ev . '*')) !== false) ||
(($uri = $this->map_find_alias($mod . '*')) !== false) ||
($uri = ($mod==='sys' ? '' : "$mod-") . $com . '/');
}if ($uri !== '' && $uri[0] == '&') {
$d = explode('#', ltrim(substr(rtrim($uri, '/'), 1)) . '#');
$comp = $this->_load_component($d[0]);
if (is_object($comp = $this->_load_component($d[0]))) {
$uri = (string)$comp->events($d[1], array('event'=>$event, 'params' => $par ));
$uri = ltrim($uri, '/');
}else {
$this->_error('bad_event','N',__LINE__,array('name'=>$uri));
return '';
}$par = FALSE;
}elseif ($sev !== '') {
$uri = rtrim($uri, '/') . "/$sev/";
}if ((string)$par !== '') {
$uri= rtrim($uri,'/') . '/' . $par.'.htm';
}$uri = $this->urlPrefix . $uri;
if ($get!=='') {
$uri .= (strpos($uri, '?') ? '&' : '?') . $get;
}}return $uri;
}function txt_constants()
{return $this->txtConstants;
}function _load_xml($xmlName)
{include_class('moon_xml');
$install=new moon_xml;
$inf=$install->load_xml($xmlName);
foreach ($inf['com'] as $k=>$v) {
$this->components[$k]=array('name'=>$v['name'],'part'=>$v['part'],'vars'=>$v['vars'],'output'=>'');
}$this->layout=$inf['layout'];
if (isset($inf['set_local']) && is_array($inf['set_local'])) {
$p=&moon::page();
foreach ($inf['set_local'] as $k=>$v) $p->set_local($k,$v);
}$p=&moon::page();
if ($p->title()=='' && $inf['title']) $p->title($inf['title']);
}function _construct_content()
{if ($this->pg!=='' && $this->pg{0}==='#') {
$r=array('content'=>$this->call_component(substr($this->pg,1)));
} else {
$this->_load_xml($this->pg);
$replaceComp= $this->pgCompRemove==='' ? false:true;
foreach ($this->components as $id=>$v) {
if ($replaceComp && $v['name']===$this->pgCompRemove) {
$v['name']=$this->pgCompInsert;
}$tpl=$v['part'];
if (!isset($this->content[$tpl])) {
$this->content[$tpl]='';
}if ($v['name']==='none' || $v['name']==='fake') {
;
}else {
$this->content[$tpl] .= $this->call_component($v['name'], $v['vars']);
}if ($this->halt) {
$this->content = $this->components=array();
return '';
}}if ($dirL=$this->ini('dir.layouts')) {
$fileName = $dirL.$this->layout.'.htm';
$t=new moon_template();
if (file_exists($fileName)) $t->load_file($fileName);
$content= $this->layout==='' ? implode('',$parts) : $t->parse('main',$parts);
$r=array('content'=>$content);
} else $r=array('layout'=>$this->layout, 'parts'=>$this->content);
}return $r;
}function _error($code,$tipas,$line,$m='')
{moon :: error(array("@engine.$code",$m), $tipas);
}}class moon_ini {
var $iniFileName='';
var $groups;
var $currVar,$currGroup;
var $comments;
var $multiLineValue,$multiLineVar=false;
function moon_ini($fileName='')
{$this->currVar=$this->currGroup='';
$this->groups=$this->comments=array();
$this->set_file_name($fileName);
if ($fileName!=='') $this->load_file( $fileName);
}function load_file( $filename = '')
{if ( empty($filename) ) {
$this->_error(3,'N',__LINE__ ); return;
} elseif ( !(file_exists($filename) && is_file($filename))) {
$this->_error(1,'W',__LINE__,array('file'=>$filename)); return;
}if ( empty($this->iniFileName) ) $this->iniFileName = $filename;
if ( !isset( $this->currGroup ) )   $this->currGroup=false;
if ( !isset( $this->groups ) )   $this->groups=array();
$iniData = file( $filename);
if (isset($iniData[0]) && strpos($iniData[0], $bom="\xEF\xBB\xBF" )==0)
$iniData[0]=str_replace($bom,'',$iniData[0]);
foreach ($iniData as $row) $this->_parse_row(trim($row));
}function load_array( $a)
{foreach ($a as $k=>$v) {
if (!$this->has($k)) $this->add_group($k);
$this->set($k,$v);
}}function set_file_name($newname)
{return ($this->iniFileName=$newname);
}function save_file($saveAsFile=false)
{$groups = $this->read_groups();
$s= isset($this->comments['']) ? $this->comments[''] : '';
foreach ($groups as $groupName) {
if ($s!=='') $s=rtrim($s)."\n\n";
$s.='['.$groupName."]\n";
if (isset($this->comments[$groupName])) $s.=$this->comments[$groupName]."\n";
$group = $this->read_group($groupName);
foreach ($group as $k=>$v) {
$s.="$k=$v\n";
$cid=$groupName."[$k]";
if (isset($this->comments[$cid])) $s.=$this->comments[$cid]."\n";
}}if ($saveAsFile===false) $saveAsFile=$this->iniFileName;
if (empty($saveAsFile)) $this->_error(2,'W',__LINE__);
else {
$fp = @fopen($saveAsFile, 'w');
if ($fp) {
flock($fp,2);
$re = fputs($fp, $s);
flock($fp,3);
fclose($fp);
return true;
} else $this->_error(4,'W',__LINE__,array('file'=>$saveAsFile));
}return false;
}function read_only(){return false;}
function has_group( $name ) {return $this->has($name);}
function has_var( $group, $varName ) { return $this->has($group, $varName);}
function read_var ( $group, $varName ) {	return $this->get($group, $varName);}
function read_array( $group, $varName ) {  return $this->get_array($group, $varName);}
function set_var( $group, $varName, $varValue ){  $this->set($group, $varName, $varValue);}
function set_vars( $group, $varsArray ) { $this->set( $group, $varsArray);}
function has($group, $varName=false)
{$group = strtolower($group);
if (!isset($this->groups[$group ])) return false;
return  ($varName===false || $varName==='' || isset($this->groups[$group][$varName]));
}function read_groups()
{$groups = array();
foreach ($this->groups as $k=>$v) $groups[]=$k;
return $groups;
}function read_group($group)
{$group = strtolower( $group );
if( !isset($this->groups[$group]) ) {
$this->_error(5,'W',__LINE__,array('file'=>$this->iniFileName,'group'=>$group)); return array();
}return $this->groups[$group];
}function get($group, $varName)
{if ($this->has($group, $varName)) return ($this->groups[strtolower($group)][$varName]);
else{
$par=array('file'=>$this->iniFileName,'group'=>$group,'var'=>$varName);
$this->_error(7,'W',__LINE__,$par);
return false;
}}function get_array( $group, $varName )
{$value=$this->get($group,$varName);
if ($value===false) return array();
else return explode(';', $value);
}function set($group, $varName, $varValue=false)
{if ($this->has($group)) {
$group = strtolower( $group );
if (is_array($varName))
foreach ($varName as $k=>$v) $this->groups[$group][$k]=$v;
else  $this->groups[$group][$varName] = $varValue;
}}function add_group($name)
{if ($this->has($name)) {
$this->_error(6,'N',__LINE__,array('file'=>$this->iniFileName,'group'=>$name));
} else $this->groups[ strtolower($name) ]=array();
}function clear_group($name)
{$name=strtolower($name);
if ( isset($this->groups[$name]) ) $this->groups[$name]=array();
}function _parse_row( $row )
{if ($this->multiLineVar!==false) {
if ($row==='>>>') {
$key=$this->multiLineVar;
$this->groups[ $this->currGroup ][ $key ] = $this->multiLineValue;
$this->currVar=$this->currGroup.'['.$key.']';
$this->multiLineVar=false;
} else $this->multiLineValue.= ($this->multiLineValue==='' ? '':"\n").$row;
return;
}if ($row=='' || $row{0}=='#') {
$cid=$this->currVar;
if (isset($this->comments[$cid])) $this->comments[$cid].="\n".$row;
else $this->comments[$cid]=$row;
return;
}if (($pos=strpos($row,'@include '))===0) {
$incFile=trim(substr($row,$pos+8));
if (basename($this->iniFileName)===$incFile) return;
$ini=new moon_ini(dirname($this->iniFileName).'/'.$incFile);
$gr=$ini->read_groups();
foreach ($gr as $g) {
if (!$this->has($g)) $this->add_group($g);
$x=$ini->read_group($g);
foreach($x as $k=>$v) $this->set($g,$k,$v);
}return;
}if( preg_match( '/^\[([[:alnum:]_ \.-]+)\]/', $row, $is ) ) {
$this->currVar=$this->currGroup = strtolower( trim($is[1]) );
if (!isset($this->groups[ $this->currGroup ]))
$this->groups[ $this->currGroup ]=array();
}else{
$pos=strpos($row,'=');
if ($pos) {
$key=rtrim(substr($row,0,$pos));
$value=str_replace('\n',"\n", ltrim(substr($row,$pos+1)) );
$this->groups[ $this->currGroup ][ $key ] = $value;
$this->currVar=$this->currGroup.'['.$key.']';
} elseif (substr($row,-3)==='<<<') {
$this->multiLineVar=rtrim(substr($row,0,-3));
$this->multiLineValue='';
}}}function _error($code,$tipas,$line,$m='')
{if (is_callable('moon::error')) {
moon :: error(array("@ini.$code",$m), $tipas);
} else echo 'ini['.$code.'] '.serialize($m);
}}class moon_cfg {
var $memory;
var $simLink;
function moon_cfg()
{$this->memory=array();
$this->simLink=array();
}function read_cfg($path)
{if (!$path) return;
if (substr($path,-4)==='.php') {
$this->read_cfg_php($path);
return;
}include_class('moon_ini');
$ini=new moon_ini();
$ini->load_file(MOON_MODULES.$path);
$groups=$ini->read_groups();
foreach ($groups as $gr) {
if (isset($this->simLink[$gr])) unset($this->simLink[$gr]);
$ext= $ini->has($gr,'sys.extends') ? $ini->get($gr,'sys.extends'):'';
if ($ext) {
if (isset($this->simLink[$ext])) $this->read_cfg($this->simLink[$ext]);
if (isset($this->memory[$ext])) $this->memory[$gr]=$this->memory[$ext];
$m=$ini->read_group($gr);
foreach ($m as $k=>$v) {
if ($v!=='' && $v{0}=='^') {
$v=ltrim(substr($v,1));
$d1=explode('.',$k);
$d2=explode('.',$v);
if (count($d1)>1 && count($d2)>1) {
$v= isset($this->memory[$d2[0]][$d1[0].'.'.$d2[1]]) ? $this->memory[$d2[0]][$d1[0].'.'.$d2[1]]:'';
}}$this->memory[$gr][$k]=$v;
}} else {
if (isset($this->memory[$gr])) {
$this->memory[$gr]=array_merge($ini->read_group($gr),$this->memory[$gr]);
} else {
$this->memory[$gr]=$ini->read_group($gr);
$sim=$ini->has($gr,'sys.cfgFile') ? trim($ini->get($gr,'sys.cfgFile')):'';
if ($sim) $this->simLink[$gr]=$sim;
}}if (!isset($this->memory[$gr]['sys.moduleDir']) && !isset($this->simLink[$gr])) $this->memory[$gr]['sys.moduleDir']=$gr.'/';
}}function read_cfg_php($path)
{include(MOON_MODULES.$path);
if (!isset($cfg) || !is_array($cfg)) {
return;
} else {
foreach ($cfg as $g=>$vars) {
$gr=strtolower($g);
if (isset($this->simLink[$gr])) unset($this->simLink[$gr]);
$ext= isset($cfg[$g]['sys.extends']) ? $cfg[$g]['sys.extends']:'';
if ($ext) {
if (isset($this->simLink[$ext])) $this->read_cfg($this->simLink[$ext]);
if (isset($this->memory[$ext]))
$this->memory[$gr]=array_merge($this->memory[$ext],$cfg[$g]);
} else {
if (isset($this->memory[$gr])) {
$this->memory[$gr]=array_merge($cfg[$g],$this->memory[$gr]);
} else {
$this->memory[$gr]=$cfg[$g];
$sim=isset($cfg[$g]['sys.cfgFile']) ? trim($cfg[$g]['sys.cfgFile']):'';
if ($sim) $this->simLink[$gr]=$sim;
}}if (!isset($this->memory[$gr]['sys.moduleDir']) && !isset($this->simLink[$gr])) $this->memory[$gr]['sys.moduleDir']=$gr.'/';
}}}private $getCache = array();
function get($module,$group='',$name=false) {
if (isset($this->getCache[$module][$group][$name])) {
return $this->getCache[$module][$group][$name];
}return ($this->getCache[$module][$group][$name] = $this->realGet($module,$group,$name));
}function realGet($module,$group='',$name=false)
{$module=strtolower($module);
if (isset($this->simLink[$module])) {
$this->read_cfg($this->simLink[$module]);
if (isset($this->simLink[$module])) {
unset($this->simLink[$module]);
}}$m = isset($this->memory[$module]) ? $this->memory[$module] : array();
if ($group=='') {
return $m;
}elseif ($name===false) {
$gr=array();
$group.='.';
$ilg=strlen($group);
foreach ($m as $k=>$v) {
if (substr($k,0,$ilg)==$group) $gr[substr($k,$ilg)]=$v;
}return $gr;
} else {
if (($vname=rtrim($group.'.'.$name,'.')) && isset($m[$vname])) return $m[$vname];
else return '';
}}function has($module, $varName=false)
{$module = strtolower($module);
$group=$this->get($module);
if (is_array($group) && count($group)) {
if  ($varName===false || isset($group[$varName])) return true;
}return false;
}}class moon_module {
var $moonMyLocation;
var $db;
var $moonModuleName;
var $moonObjectName='';
function moon_module($name)
{$this->moonModuleName=$name;
$ini=&moon::cfg();
$conn=$ini->get($name,'sys','db');
$this->db=&moon::db($conn, $this->moonObjectName);
if ($this->moonMyLocation===false)
$this->moonMyLocation=$ini->get($name,'sys','moduleDir');
}function &db()
{return $this->db;
}function table($name)
{return $this->_get_mycfg('tb',$name);
}function _get_mycfg($what,$name)
{$ini=&moon::cfg();
if ($this->moonObjectName!=='')
$c=$ini->get($this->moonModuleName,$what,$name.'{'.$this->moonObjectName.'}');
else $c='';
if ($c==='') $c=$ini->get($this->moonModuleName,$what,$name);
return $c;
}}class moon_com extends moon_module {
var $moonMyModule,$moonMyName,$moonMyID,$moonRealModule,$moonRealName;
var $moonMyVars;
var $moonSaveVars;
var $moonTemplate;
var $moonEvAlias;
var $moonDbConn;
var $moonMultiLang;
var $moonWasOnload=false;
function moon_com($module,$name,$location=false)
{$this->moonMyModule=$module;
$this->moonMyName=$name;
$this->moonRealModule=$module;
$this->moonRealName=$name;
$this->moonMyID=0;
$this->moonMyLocation=$location;
$this->moonTemplate=array();
$this->moonMyVars=array();
$this->moonSaveVars='';
$this->moonDbConn=false;
$this->moonEvAlias=array();
$this->moonForms=array();
$this->reinit();
}function init($module,$name,$id)
{$this->moonMyModule=$module;
$this->moonMyName=$name;
$this->moonMyID=$id;
if (!isset($this->moonMyVars[$id])) $this->moonMyVars[$id]=array();
if ($this->moonMyModule!==$this->moonRealModule) $this->reinit();
if (!$this->moonWasOnload) {
$this->moonWasOnload=true;
$this->onload();
}}function reinit()
{$module=&$this->moonMyModule;
$ini=&moon::cfg();
$a=$ini->get($module,'ev');
if (is_array($a)) {
foreach ($a as $k=>$v) {
$d=explode('#',$v);
$d[1]=(isset($d[1])) ? trim($d[1]):'';
if (!strpos($d[0],'.')) $d[0]=$module.'.'.$d[0];
if (!strpos($k,'.') && $module!='sys') $k=$module.'.'.$k;
$this->moonEvAlias[$k]=$d[0].( ($d[1]!='') ? ('#'.$d[1]):'' );
}}( $this->moonMultiLang = $ini->get($this->moonMyModule,'vocabulary',''))
|| ($this->moonMultiLang = $ini->get($this->moonMyModule,'sys','multiLang') );
if ($zod = $this->_get_mycfg('vocabulary','')) {
$zod = rtrim($zod, '; ');
if (substr($zod,-1) === '*') {
$this->moonMultiLang = substr($zod, 0, -1) . ';' . $this->moonMultiLang;
}else {
$this->moonMultiLang = $zod;
}}$this->moonObjectName=$this->my('fullname');
parent::moon_module($module);
}function &load_template($htmName='', $txtFile='')
{$fName = $htmName==='' ? $this->moonRealName : $htmName;
if (!isset($this->moonTemplate[$fName])) {
$engine=&moon::engine();
$htmFile=MOON_MODULES.$this->my('location').$fName;
if ('.htm' != substr($htmFile,-4)) {
$htmFile .= '.htm';
}if ($txtFile) {
$langFile = $txtFile;
}elseif ($this->moonMultiLang) {
switch ($this->moonMultiLang) {
case 1:
$langFile=MOON_MODULES.$this->my('location').$fName.'.txt';
break;
case 2:
$langFile = $engine->ini('dir.multilang') . $this->moonRealModule.'.'.$fName.'.txt';
break;
case 3:
$langFile = $engine->ini('dir.multilang') . $this->my('location').$fName.'.txt';
break;
default:
$locale = &moon::locale();
$a = array(
'{dir.multilang}' => $engine->ini('dir.multilang'),
'{dir.modules}' => MOON_MODULES,
'{dir.current}' => MOON_MODULES.$this->my('location'),
'{module}' => $this->my('module'),
'{name}' => $fName,
'{language}' => $locale->language(),
'{locale}' => $locale->current_locale(),
);
$langFile = str_replace(
array_keys($a),
array_values($a),
$this->moonMultiLang
);
}}else {
$langFile = '';
}$this->moonTemplate[$fName]= moon::template($htmFile,$langFile, moon::locale()->current_locale());
}return $this->moonTemplate[$fName];
}function link($ev='',$par='',$arrayGET=false)
{return htmlspecialchars($this->url($ev,$par,$arrayGET));
}function linkas($ev='',$par='',$arrayGET=false)
{return htmlspecialchars($this->url($ev,$par,$arrayGET));
}function url($ev='',$par='',$arrayGET=false)
{$d=explode('#',$ev);
if (count($d)>1) {
$ev=trim($d[0]);
$sev=trim($d[1]);
if ($ev==='') $ev=$this->my('fullname');
elseif (!strpos($ev,'.')) $ev=$this->my('module').'.'.$ev;
if ($sev!=='') $ev.='#'.$sev;
foreach ($this->moonEvAlias as $k=>$v)
if ($ev==$v) {$ev=$k;break;}
}$p=&moon::page();
return $p->make_url($ev,$par,$arrayGET);
}function redirect($ev='',$par='',$arrayGET=false)
{$p=&moon::page();
$p->redirect($this->url($ev,$par,$arrayGET),303);
}function &object($name)
{if (!strpos($name,'.')) $name=$this->my('module').'.'.$name;
$eng=&moon::engine();
$com=&$eng->_load_component($name);
return $com;
}function &form()
{$nm = $a = '';
if (func_num_args()) {
$a=func_get_args();
if (count($a)==1) {
if (is_array($a[0])) {
$a = $a[0];
}elseif (is_string($a[0])) {
$a = array($a[0]);
}}$nm = md5(implode(' ', $a));
}if (!isset($this->moonForms[$nm]) || !is_object($this->moonForms[$nm])) {
$this->moonForms[$nm]=new moon_com_form;
if (is_array($a)) {
$this->moonForms[$nm]->names($a);
}}return $this->moonForms[$nm];
}function my($what) {
switch (strtolower($what)) {
case 'id': $s=$this->moonMyID; break;
case 'name': $s=$this->moonMyName; break;
case 'module': $s=$this->moonMyModule; break;
case 'fullname':
$s=$this->moonMyModule.'.'.$this->moonMyName;
if ($this->moonMyID) $s.='.'.$this->moonMyID;
break;
case 'location': $s=$this->moonMyLocation; break;
case 'page': $eng=&moon::engine();$s=$eng->pg; break;
case 'vars':
$vars = array();
if (isset($this->moonMyVars[$this->moonMyID])) {
$vars += $this->moonMyVars[$this->moonMyID];
}$p=&moon::page();
$e=&moon::engine();
$tvars=$p->get_stack($e->pg,$this->my('fullname'));
if (is_array($tvars)){
$vars += $tvars;
};
if (is_array($tvars = $this->properties())){
$vars += $tvars;
};
$s = $vars;
break;
case 'file': $s=MOON_MODULES.$this->my('location').$this->moonRealName.'.php'; break;
default: $s='';
}return $s;
}function set_var($name,$value)
{$this->moonMyVars[$this->moonMyID][$name]=$value;
}function save_vars($mas=false)
{if ($mas===false) return $this->moonSaveVars;
$tvars=$this->properties();
if (is_array($tvars) && is_array($mas)) {
foreach ($mas as $k=>$v) if (isset($tvars[$k]) && $tvars[$k]==$v) unset($mas[$k]);
}$this->moonSaveVars=$mas;
}function forget()
{$p=&moon::page();
$p->forget();
}function use_page($xml,$replaceComponent=false)
{$engine=&moon::engine();
if ($xml=='') $engine->pg='#'.$this->my('fullname');
elseif ($xml=='#') $engine->pg=$this->my('module').'.'.$this->my('name');
else {
$pg=$this->_get_mycfg('page',$xml);
if ($pg!=='') {
if (strpos($pg,',')) {
list($pg,$replace)=explode(',',$pg);
$pg=rtrim($pg);
if ($replaceComponent===false) $replaceComponent=ltrim($replace);
}if (!strpos($pg,'.')) $pg=$this->my('module').'.'.$pg;
$engine->pg=$pg;
}}if ($replaceComponent!==false) {
$engine->pgCompRemove=$replaceComponent==='' ? 'fake':$replaceComponent;
$engine->pgCompInsert=$this->my('fullname');
}}function table($name)
{return $this->_get_mycfg('tb',$name);
}function get_dir($name)
{return $this->_get_mycfg('dir',$name);
}function get_var($name)
{return $this->_get_mycfg('var',$name);
}function message($msgID,$info='')
{moon :: error( 'Method "message" is removed.', 'N');
return $msgID;
}function __call($name, $arguments) {
$name = strtolower($name);
switch ($name) {
case 'main':
case 'events':
case 'onload':
return '';
case 'properties':
return array();
default:
trigger_error("Calling undefined method '$name'");
}}function _get_mycfg($what,$name)
{$ini=&moon::cfg();
if ($name === '') {
$c = $ini->get($this->moonMyModule, $what . '{'. $this->moonMyName . '}', '' );
} else {
$c = $ini->get($this->moonMyModule, $what, $name . '{' . $this->moonMyName . '}' );
}if ($c === '') {
$c = $ini->get($this->moonMyModule,$what,$name);
}return $c;
}}class moon_com_form {
var $names;
var $values;
function moon_com_form()
{$this->names=$this->values=array();
}function names()
{if (!func_num_args()) $this->names=array();
else {
$a=func_get_args();
if (count($a)==1 && is_array($a[0])) {
$a = $a[0];
}foreach ($a as $k=>$v) $this->names[$k]=(string)$v;
}}function fill($a=false, $fillUndefined=true)
{if (is_array($a))
foreach($a as $k=>$v) $this->values[$k] = $fillUndefined && is_string($v) ? trim($v) : $v;
if ($fillUndefined)
foreach($this->names as $v)
if (!isset($this->values[$v])) $this->values[$v]='';
}function clear()
{$this->values=array();
}function get_values()
{$a=array();
$args= func_num_args() ? func_get_args() : $this->names;
foreach($args as $name)
if (array_key_exists($name,$this->values))
$a[$name]=$this->values[$name];
return $a;
}function get($name)
{return (array_key_exists($name,$this->values) ? $this->values[$name] : null);
}function was_refresh()
{$p=&moon::page();
return $p->was_refresh();
}function html_values($name=false)
{if ($name===false) $name=$this->names;
$isArray = is_array($name);
$args = $isArray ? $name : array($name);
$a=array();
foreach($args as $name)
$a[$name]= array_key_exists($name,$this->values) && is_scalar($this->values[$name]) ?
htmlspecialchars($this->values[$name]) : '';
return ($isArray ? $a : $a[$name]);
}function options($name,$optMas)
{$selected=$this->get($name);
if (!is_array($selected)) $selected=array((string)$selected);
$o='';
if (is_array($optMas)){
$complex=false;
foreach($optMas as $v) { $complex=is_array($v); break;}
if ($complex) {
foreach($optMas as $arr) {
$oth=isset($arr[2]) ? (' '.$arr[2]):'';
if (isset($arr[1]) && is_array($arr[1])) {
$o.='<optgroup label="'.htmlspecialchars($arr[0]).'"'.$oth.'>'."\n";
foreach($arr[1] as $k=>$v) $o.='<option value="'.$k.'"'. (in_array((string)$k,$selected) ? ' selected="selected"':'').'>'.htmlspecialchars($v).'</option>'."\n";
$o.='</optgroup>'."\n";
} else $o.='<option value="'.$arr[0].'"'. (in_array((string)$arr[0],$selected) ? ' selected="selected"':'').$oth.'>'.htmlspecialchars($arr[1]).'</option>'."\n";
}} else {
foreach($optMas as $k=>$v) $o.='<option value="'.$k.'"'. (in_array((string)$k,$selected) ? ' selected="selected"':'').'>'.htmlspecialchars($v).'</option>'."\n";
}if ($o==='') $o="<option value=\"\" selected=\"selected\">---</option>\n";
}return $o;
}function checked($name,$value)
{$checked=$this->get($name);
if (is_array($checked)) $check=in_array($value,$checked);
else $check=($value==$checked);
return (htmlspecialchars($value).( $check ? '" checked="checked':''));
}}class moon_error{
var $sysMessages;
var $err_msgs;
var $kiek_f=0;
var	$kiek_w=0;
var	$kiek_n=0;
function moon_error()
{$this->err_msgs=array();
}function warning($error,$failas='',$line='')
{$other=($failas!=='' && $line!=='') ? (' File:'.basename($failas)." Line:".$line):'';
$this->_add_msg('W',$error,$other);
}function fatal($error,$failas='',$line='')
{$other=($failas!=='' && $line!=='') ? (' File:'.basename($failas)." Line:".$line):'';
$this->_add_msg('F',$error,$other);
}function notice($error,$failas='',$line='')
{$other=($failas!=='' && $line!=='') ? (' File:'.basename($failas)." Line:".$line):'';
$this->_add_msg('N',$error,$other);
}function error($msg, $type = 'W') {
if (is_array($msg)) {
$arr = isset($msg[1]) ? $msg[1] : '';
$msg = !empty($msg[0]) ? $msg[0] : '?';
if ($msg{0} === '@') {
$msg = $this->_sysMessage(substr($msg,1), $arr);
}if (is_array($arr)) {
foreach($arr as $k=>$v) {
$msg=str_replace('{'.$k.'}', $v, $msg);
}}}$this->_add_msg($type, $msg, $this->_whereError());
}function count_errors($type='')
{$type=strtoupper(trim($type));
$r=0;
$ilgis=strlen($type);
for($i=0;$i<$ilgis;$i++){
$t=$type{$i};
switch($t){
case 'N': $r+=$this->kiek_n; break;
case 'W': $r+=$this->kiek_w; break;
case 'F': $r+=$this->kiek_f; break;
}}return $r;
}function get_errors()
{return $this->err_msgs;
}function show() {
$s='';
foreach ($this->err_msgs as $msg) {
$d=explode(';',$msg);
$s.='<br>('.$d[0].') '.$d[1].'<i>'.$d[2].'</i>';
}echo $s;
}function save($file=false,$event='')
{if (count($this->err_msgs) && $file!==false) {
$s='';
$chmod=(file_exists($file)) ? false:true;
if (strlen($file) && $f=fopen($file,'ab')){
if (is_object($user = & moon :: user())) {
$ip = $user->get_ip();
}else {
$ip=isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR']:'';
}$uri=isset($_SERVER['REQUEST_URI']) ? urldecode($_SERVER['REQUEST_URI']):'';
$s="\r\n".date('Y-m-d H:i:s O').' '.$uri." $event IP: $ip";
if (isset($_SERVER['SERVER_ADDR'])) $s.=", Server: ".$_SERVER["SERVER_ADDR"];
if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER']) $s.=", Referer: ".urldecode($_SERVER["HTTP_REFERER"]);
if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT']) $s.=", Agent: ".$_SERVER["HTTP_USER_AGENT"];
$s.="\r\n";
foreach ($this->err_msgs as $msg) $s.=' * '.$msg."\r\n";
$r=fputs($f,$s);
fclose($f);
if ($chmod) moon::chmod($file);
}}}function _add_msg($tipas,$err,$where)
{$err=str_replace(array("\r","\n"),array('',' '),$err);
switch($tipas){
case 'N': $this->kiek_n++; break;
case 'W': $this->kiek_w++; break;
case 'F': $this->kiek_f++; break;
}$this->err_msgs[]=$tipas.';'.$err.';'.$where;
}function _whereError()
{$a = debug_backtrace();
$failas = $line = '';
foreach ($a as $v) {
$c = empty($v['class']) ? '' : $v['class'];
$f = empty($v['function']) ? '' : $v['function'];
if ($c == __CLASS__ || $c == get_class($this) || (($c == 'moon' || $c == 'moon_core') && $f == 'error')) {
$failas = $v['file'];
$line = $v['line'];
continue;
}break;
}$other=($failas!=='' && $line!=='') ? (' File:'.basename($failas)." Line:".$line):'';
return $other;
}function _sysMessage($msgID, $m = '') {
if (is_null($this->sysMessages)) {
$this->sysMessages = FALSE;
if (!isset($GLOBALS['MoonLocale']) || get_class($GLOBALS['MoonLocale'])!='moon_locale') {
$this->_add_msg('F', __CLASS__ . ': Locale object not found!', basename(__FILE__));
}else {
$file = MOON_CLASSES . 'messages.txt';
if (file_exists($file)){
$this->sysMessages = new moon_ini($file);
}else{
$this->_add_msg('F', __CLASS__ . ': Messages file ' . $file . ' not found!', basename(__FILE__));
}}}if ($this->sysMessages) {
$loc =&moon::locale();
$group=$loc->current_locale();
if (!$this->sysMessages->has($group) && count($groups = $this->sysMessages->read_groups())) {
$group = $groups[0];
}if ($this->sysMessages->has($group,$msgID)) {
$s = $this->sysMessages->get($group, $msgID);
}else {
$s=' ? ['.$group.'.'.$msgID.']'.serialize($m);
}}else {
$s=' ! ['.$msgID.']'.serialize($m);
}return $s;
}}class moon_locale
{var $info;
var $langCode='';
var $iso;
var $dateFormat;
var $cache;
var $now;
function moon_locale()
{$this->info=new moon_ini;
$this->cache=array();
$this->now= isset($_SERVER['REQUEST_TIME']) ? (int)$_SERVER['REQUEST_TIME'] : time();
}function load_file($fname)
{$this->info->load_file($fname);
}function current_locale()
{return $this->iso;
}function set_locale($iso)
{if (get_class($this->info)===false) return;
$ini=&$this->info;
if (!$ini->has($iso,false))
$iso= count($groups=$ini->read_groups()) ? $groups[0]:'?';
$this->iso=$iso;
$this->langCode=$ini->get($iso, 'LanguageCode' );
$df=$ini->read_group($iso);
$this->dateFormat =array();
foreach ($df as $kk=>$v)
if ($kk!=='' && $kk{0}==='_') {
$d=explode(',',$kk);
foreach ($d as $k)
if (($k=trim($k))!=='' && $k{0}==='_')
$this->dateFormat[substr($k,1)]=$v;
}}function language()
{return $this->langCode;
}function get($name)
{return ($this->info->get($this->iso,$name));
}function get_array($name)
{$m=explode(';',$this->info->get($this->iso,$name));
return $m;
}function gmdatef($value,$templ='',$addTimeZoneS=false)
{$ofset=-date('Z',(is_numeric($value) ? $value:$this->now));
if ($addTimeZoneS!==false) $ofset+=$addTimeZoneS;
return $this->datef($value,$templ,$ofset);
}function datef($value,$templ='',$addTimeZoneS=false)
{$d=$this->split_date($value,$addTimeZoneS);
if ($d===false) return '';
if (isset($this->dateFormat[$tpl=ltrim($templ,'_')])) $tpl=$this->dateFormat[$tpl];
else $tpl= strpos($templ,'%')!==false ? $templ : '%Y-%M-%D';
if (isset($this->cache[$tpl])) $mas=$this->cache[$tpl];
else{
preg_match_all('/%({[mMwW]([^}]*)}|.?)/', $tpl, $m);
$this->cache[$tpl] = $mas = isset($m[1]) ? array_unique($m[1]) : array();
}foreach ($mas as $v) {
switch($v){
case 'Y':$r = $d['y'] ? $d['y'] : '0000'; break;
case 'y':$r=substr($d['y'], 2 ); break;
case 'D':$r=$this->zero( $d['d'] ); break;
case 'd':$r=(int)$d['d']; break;
case 'M':$r=$this->zero($d['m'] ); break;
case 'm':$r=(int)$d['m']; break;
case 'H':$r=$this->zero($d['h']); break;
case 'h':$r=$d['h']; break;
case 'I':$r=$this->zero($d['i']); break;
case 'i':$r=$d['i']; break;
case 'S':$r=$this->zero($d['s']); break;
case 's':$r=$d['s']; break;
case 'T':$r=$this->zero($d['h']).':'.$this->zero($d['i']).':'.$this->zero($d['s']) ; break;
case 't':$r=$this->zero($d['h']).':'.$this->zero($d['i']); break;
case 'U':$r=isset($d['u']) ? $d['u'] : mktime((int)$d['h'], (int)$d['i'], (int)$d['s'], $d['m'], $d['d'], $d['y']); break;
default: $nm=substr($v,1,-1);
if (strtolower($nm{0})==='m') $r=$this->month_name($d['m'],$nm);
elseif (strtolower($nm{0})==='w') {
if (!isset($d['wday'])) $d['w']=$this->find_week_day($d['y'],$d['m'],$d['d']);
$r=$this->day_name($d['w'],$nm);
} else $r='?';
}$tpl=str_replace("%$v",$r,$tpl);
}return $tpl;
}function timef($value,$seconds=false,$addTimeZoneS=false)
{$t=$this->split_date($value,$addTimeZoneS);
if ($t===false) return '';
$time=$this->zero($t['h']).':'.$this->zero($t['i']);
if ($seconds) $time.=':'.$this->zero($t['s']);
return $time;
}function day_name( $day, $version='w' )
{$n=$this->get_array($version);
$day= $day ? ($day-1):6;
return ( isset($n[$day]) ? $n[$day]:'?');
}function month_name($month, $version='m' )
{$n=$this->months_names($version);
return ( isset($n[$month]) ? $n[$month]:'?');
}function months_names($version='m')
{$n=$this->get_array($version);
$m=array();
if (count($n)>11)
for($i=0;$i<12;$i++) $m[$i+1]=$n[$i];
return $m;
}function zodiak($date)
{if (($v=$this->split_date($date))===false || !count($v)) return '';
$starts=array(3=>21,4=>21,5=>22,6=>22,7=>23,8=>24,9=>23,10=>23,11=>22,12=>22,1=>21,2=>20);
$m=$v['m'];
$zod= $v['d']<$starts[$m] ? ($m-1):$m;
$zod=($zod-3+12)%12;
$n=$this->get_array('Zodiacs');
return (isset($n[$zod]) ? $n[$zod]:'?');
}function find_week_day($y,$m,$d)
{$M= $m>2 ? ($m-2):($m+10) ;
$c=floor($y/100);
$Y=$y % 100;
if ($m<=2) $Y-- ;
$s=( $d+floor( (13*$M-1)/5 )+$Y+floor($c/4)+floor($Y/4)-2*$c ) % 7;
return ($s>0 ? $s : ($s+7));
}function split_date($value,$addTimeZoneS=false)
{if ($value==='now') $value=$this->now;
if (!is_numeric($value)) {
if (strpos($value,':')) {
$value = strtotime($value);
if ($value === -1 || $value === FALSE) {
return FALSE;
}}elseif (preg_match( "/^([0-9]{4}-[0-9]{1,2}-[0-9]{1,2})[\s]*/", $value, $m )) {
$dm=explode('-',$m[1]);
$r=array('y'=>intval($dm[0]), 'm'=>intval($dm[1]), 'd'=>intval($dm[2]) );
$r['h'] = $r['i'] = $r['s'] = 0;
return $r;
}else {
return false;
}}if ( (is_int($value) || is_numeric($value)) && ($value=intval($value))) {
if ($addTimeZoneS!==false) $value += $addTimeZoneS;
$mas=getdate($value);
$r=array('y'=>$mas['year'], 'm'=>$mas['mon'], 'd'=>$mas['mday'], 'w'=>($mas['wday'] ? $mas['wday']:7), 'h'=>$mas['hours'], 'i'=>$mas['minutes'],	's'=>$mas['seconds'],'u'=>$value);
return $r;
}return false;
}function zero($num)
{if ((int)$num < 10 ) $num='0'.$num;
return (string)$num;
}function twelveHour( $value, $seconds=false )
{$t=$this->split_date($value);
if($t['h']==='') return '';
$h=$t['h'] % 12;
if ($h==0) $h=12;
$r=$this->zero($h).':'.$this->zero($t['m']).($seconds ? ':'.$this->zero($t['s']) : '').' ';
if ($h==12 && ($t['m']+$t['s'])==0) $r.= $t['h']<12 ? 'midnight':'noon';
else $r.= $t['h']<12 ? 'am':'pm';
return $r;
}function to_days($date)
{$v=explode('-',$date);
if (sizeof($v)!=3) return 0;
$y=(int)$v[0]; $m=(int)$v[1]; $d=(int)$v[2];
if (!checkdate($m,$d,$y)) return 0;
$c=floor(($y-1)/100);
$k=$y*365+floor(($y-1)/4)+floor($c/4)-$c;
$mon=array('', 0,31,59,90,120,151,181,212,243,273,304,334);
$k+=$mon[$m]+$d;
if ($m>2 && ($y % 4 == 0)) {
if	($y % 100 !=0 || (floor($y/100) % 4 ==0) ) $k+=1;
}return $k;
}function from_days($days)
{$days=abs(intval($days));
if ($days<366 || $days>3652424) return '0000-00-00';
$mon=array('', 0,31,59,90,120,151,181,212,243,273,304,334);
$y=floor($days*100/36525);
$c=floor(($y-1)/100);
$d=$days-$y*365-floor(($y-1)/4)-floor($c/4)+$c;
while ($d>$hasd=(($y % 4==0 && ($y % 400==0 || $y % 100)) ? 366:365)) {
$d-=$hasd;	$y++;
}while ($d<1) $d+=(--$y % 4==0 && ($y % 400==0 || $y % 100)) ? 366:365;
if ($hasd===366) for ($i=3;$i<=12;$i++) $mon[$i]+=1;
for ($i=12;$i>=1;$i--) {
if ($mon[$i]<$d) {
$m=$i;
$d=$d-$mon[$i];
break;
}}if ($y<1000) $y=str_repeat('0',4-strlen($y)).$y;
return $y.'-'.($m<10 ? '0':'').$m.'-'.($d<10 ? '0':'').$d;
}function now()
{return $this->now;
}function is_est($time,$tzID=0) {return $this->is_dst($time,$tzID);}
function is_dst($time,$tzID=0)
{$nuo=1167609600; $iki=1483228800;
if ($time<$nuo || $time>$iki || !$tzID) return false;
$b=$this->tzdata($tzID);
if (!isset($b[3]) || ($r=$b[3])==0) return false;
if ($r==4) {
if ($b[1] ==='') {
$b[1] = '00:00';
}list($h,$m)=explode(':',$b[1]);
$shift=abs($h)*3600+$m*60;
if ($b[1][0]==='-') $shift=-$shift;
$time-=$shift;
} elseif ($r==3) $time--;
switch ($r) {
case 1: $a=array(1658,7369,10394,16105,19130,24841,28034,33745,36770,42481,45506,51217,54242,59953,62978,68689,71714,77425,80618,86329,89354,95065); break;
case 2: $a=array(2162,7201,11066,15937,19802,24673,28538,33577,37274,42313,46010,51049,54914,59785,63650,68521,72386,77257,81122,86161,89858,94897); break;
case 3: $a=array(1656,7367,10392,16103,19128,24839,28032,33743,36768,42479,45504,51215,54240,59951,62976,68687,71712,77423,80616,86327,89352,95063); break;
case 4: $a=array(1993,7201,10897,15937,19633,24673,28369,33577,37105,42313,45841,51049,54745,59785,63481,68521,72217,77257,80953,86161,89689,94897); break;
case 5: $a=array(2784,6479,11520,15215,20256,23951,29160,32855,37896,41591,46632,50327,55368,59063,64104,67799,72840,76535,81744,85439,90480,94175); break;
case 6: $a=array(2114,6193,10850,15433,19586,24001,28322,32401,37226,41641,45962,50209,54698,58609,63434,67849,72170,76417,81074,85657,89810,94057); break;
case 7: $a=array(2163,6555,10947,15339,19707,24099,28467,32859,37227,41619,46011,50403,54771,59163,63531,67923,72291,76683,81075,85467,89835,94227); break;
case 8: $a=array(1994,7202,10898,15938,19634,24674,28370,33578,37106,42314,45842,51050,54746,59786,63482,68522,72218,77258,80954,86162,89690,94898); break;
case 9: $a=array(1996,7204,10900,15940,19636,24676,28372,33580,37108,42316,45844,51052,54748,59788,63484,68524,72220,77260,80956,86164,89692,94900); break;
case 10: $a=array(7202,10898,15938,19634,24674,28370,33578,37106,42314,45842,51050,54746,59786,63482,68522,72218,77258,80954,86162,89690,94898); break;
case 11: $a=array(6698,10898,15434,19634,24170,28370,32906,37106,41642,45842,50546,54746,59282,63482,68018,72218,76754,80954,85490,89690,94226); break;
case 12: $a=array(6530,11066,15266,19802,24002,28538,32738,37274,41474,46010,50378,54914,59114,63650,67850,72386,76586,81122,85322,89858,94058); break;
}foreach ($a as $k=>$v)
if (($nuo+$v*3600)>$time)
return ($k % 2 ? true:false);
return ($k % 2 ? false:true);
}function select_timezones()
{$b=$this->tzdata();
$a=array();
foreach ($b as $k=>$v) $a[$k]='(GMT'.$v[1].') | '.$v[2];
return $a;
}function timezone($id, $timeStamp = TRUE)
{$b=$this->tzdata($id);
if ($b===false) $b=$this->tzdata(0);
if ($b[1]=='' && empty($b[3])) {
$shift=0; $gmt='GMT';
} else {
if ($b[1] ==='') {
$b[1] = '00:00';
}list($h,$m)=explode(':',$b[1]);
if ($timeStamp !== FALSE) {
if ($timeStamp === TRUE) {
$timeStamp = $this->now;
}if ($this->is_dst($timeStamp, $id)) {
$h++;
}}$shift=abs($h)*3600+$m*60;
$gmt=abs($h);
if ($m>0 && $h<>0) {
$gmt .= ':' . $m;
}if ($h<0) {
$shift=-$shift;
$gmt='GMT-'.$gmt;
} elseif ($h>0) {
$gmt='GMT+'.$gmt;
}else {
$gmt='GMT';
}}return array($shift,$gmt,$b[1],$b[2],$b[0]);
}function tzdata($id=false)
{$a = array(
-120=>array('ENIWE', '-12:00', 'Eniwetok, Kwajalein', 0),
-110=>array('SAMOA', '-11:00', 'Midway Island, Samoa', 0),
-100=>array('HAWAI', '-10:00', 'Hawaii', 1),
-90=>array('ALASK', '-09:00', 'Alaska', 1),
-80=>array('PACIF', '-08:00', 'Pacific Time (US and Canada)', 1),
-81=>array('TIJUA', '-08:00', 'Tijuana', 2),
-71=>array('ARIZO', '-07:00', 'Arizona', 0),
-70=>array('MOUNT', '-07:00', 'Mountain Time (US and Canada)', 1),
-60=>array('CENTR', '-06:00', 'Central Time (US and Canada)', 1),
-61=>array('MEXIC', '-06:00', 'Mexico City, Tegucigalpa', 2),
-62=>array('TEGUC', '-06:00', 'Tegucigalpa', 0),
-63=>array('SASKA', '-06:00', 'Saskatchewan', 0),
-51=>array('BOGOT', '-05:00', 'Bogota, Lima, Quito', 0),
-50=>array('EASTE', '-05:00', 'Eastern Time (US and Canada)', 1),
-52=>array('USAIN', '-05:00', 'Indiana (East)', 0),
-40=>array('CANAD', '-04:00', 'Atlantic Time (Canada)', 1),
-41=>array('CARAC', '-04:00', 'Caracas, La Paz', 0),
-30=>array('BRASI', '-03:00', 'Brazil', 0),
-31=>array('BUENO', '-03:00', 'Buenos Aires, Georgetown', 0),
-32=>array('NEWFO', '-03:30', 'Newfoundland', 3),
-20=>array('MIDAT', '-02:00', 'Mid-Atlantic', 0),
-10=>array('AZORE', '-01:00', 'Azores', 4),
-11=>array('CAPEV', '-01:00', 'Cape Verde Is.', 0),
0=>array('_UTC_', '', 'Universal Time Coordinated', 0),
1=>array('CASAB', '', 'Casablanca, Monrovia', 0),
2=>array('LONDO', '', 'Dublin, Edinburgh, Lisbon, London', 4),
10=>array('AMSTE', '+01:00', 'Amsterdam, Copenhagen, Madrid, Paris', 4),
11=>array('SKOPJ', '+01:00', 'Belgrade, Sarajevo, Skopje, Zagreb', 4),
12=>array('BUDAP', '+01:00', 'Bratislava, Budapest, Ljubljana, Prague, Warsaw', 4),
13=>array('BRUSS', '+01:00', 'Brussels, Berlin, Bern, Rome, Stockholm, Vienna', 4),
21=>array('ATHEN', '+02:00', 'Athens, Istanbul, Minsk', 4),
22=>array('BUCHA', '+02:00', 'Bucharest', 4),
23=>array('CAIRO', '+02:00', 'Cairo', 5),
24=>array('PRETO', '+02:00', 'Harare, Pretoria', 0),
20=>array('HELSI', '+02:00', 'Helsinki, Sofija, Riga, Tallinn, Vilnius', 4),
25=>array('TELAV', '+02:00', 'Israel', 6),
31=>array('BAGHD', '+03:00', 'Baghdad, Kuwait, Riyadh', 7),
30=>array('MOSCO', '+03:00', 'Moscow, St. Petersburg, Volgograd', 8),
32=>array('NAIRO', '+03:00', 'Nairobi', 0),
33=>array('TEHRA', '+03:30', 'Tehran', 4),
41=>array('ABUDA', '+04:00', 'Abu Dhabi, Muscat', 0),
40=>array('TBILI', '+04:00', 'Baku, Tbilisi', 9),
42=>array('KABUL', '+04:30', 'Kabul', 0),
50=>array('EKATE', '+05:00', 'Ekaterinburg', 8),
51=>array('ISLAM', '+05:00', 'Islamabad, Karachi, Tashkent', 0),
52=>array('BOMBA', '+05:30', 'Bombay, Calcutta, Madras, New Delhi', 0),
60=>array('ALMAT', '+06:00', 'Almaty, Dhaka', 0),
61=>array('COLOM', '+06:00', 'Colombo', 0),
70=>array('BANKO', '+07:00', 'Bangkok, Hanoi, Jakarta', 0),
80=>array('BEIJI', '+08:00', 'Beijing, Chongqing, Hong Kong, Urumqi', 0),
81=>array('PERTH', '+08:00', 'Perth', 10),
82=>array('SINGA', '+08:00', 'Singapore', 0),
83=>array('TAIPE', '+08:00', 'Taipei', 0),
91=>array('TOKYO', '+09:00', 'Osaka, Sapporo, Tokyo', 0),
90=>array('SEOUL', '+09:00', 'Seoul', 0),
92=>array('YAKUT', '+09:00', 'Yakutsk', 8),
93=>array('ADELA', '+09:30', 'Adelaide', 10),
94=>array('DARWI', '+09:30', 'Darwin', 0),
101=>array('BRISB', '+10:00', 'Brisbane', 0),
100=>array('SYDNE', '+10:00', 'Canberra, Melbourne, Sydney', 10),
102=>array('MORES', '+10:00', 'Guam, Port Moresby', 0),
103=>array('HOBAR', '+10:00', 'Hobart', 11),
104=>array('VLADI', '+10:00', 'Vladivostok', 8),
110=>array('MAGAD', '+11:00', 'Magadan, Solomon Is., New Caledonia, Kamchatka', 8),
111=>array('CALED', '+11:00', 'Solomon Is., New Caledonia', 0),
120=>array('AUCKL', '+12:00', 'Auckland, Wellington', 12),
121=>array('FIJIS', '+12:00', 'Fiji, Marshall Is.', 0),
);
return ($id===false ? $a : (isset($a[$id]) ? $a[$id] : false));
}}class moon_log{
var $data;
var $event='';
function moon_log()
{$this->data=array();
}function log_it($name,$value)
{$this->data[$name]=$this->_encode($value);
}function event($ev)
{$this->event=$this->_encode($ev);
}function save($filename='',$sys_info=true)
{if (empty($filename)) return;
$chmod=(file_exists($filename)) ? false:true;
$fp = @fopen($filename, 'a');
if ($fp){
$s='';
if ($sys_info) $s=$this->_build_sys_line($sys_info);
foreach($this->data as $name=>$value)	$s.=$name.'='.$value.';';
flock($fp,2);
$re = fputs($fp, "$s\r\n");
flock($fp,3);
fclose($fp);
if ($chmod) moon::chmod ($filename);
} else {
moon :: error( array("@log.cant_write", array('file'=>$filename)) );
}}function _encode($s)
{return $s;
}function _build_sys_line($level)
{$tmp=array();
$tmp['T']=date('H:i:s');
$u=&moon::user();
$p=&moon::page();
$tmp['SID']=$u->cookie_id();
if ($this->event) {
$d=explode('|',$this->event);
if (strlen($d[0])) $tmp['EV']=$d[0];
if (isset($d[1]) && strlen($d[1])) $tmp['EVP']=$d[1];
}$user_id=$u->get_user_ID();
if ($user_id) $tmp['UID']=&$user_id;
if (!$p->history_step()) {
$tmp['IP']=$u->get_ip();
if (isset($_SERVER['HTTP_USER_AGENT'])) $tmp['UA']=$this->_encode($_SERVER['HTTP_USER_AGENT']);
} else $tmp['ST']=$p->history_step();
$referer=$p->referer();
if ($referer) $tmp['REF']=$this->_encode($referer);
$s='';
foreach($tmp as $name=>$value)	$s.=$name.'='.$value.';';
return $s;
}}class moon_user{
var $id=0;
var $udata;
var $voter_id='';
var $voternew=false;
var $logins_number=0;
function moon_user()
{$this->udata=array();
$p=&moon::page();
$u=$p->get_from_memory('user');
if ($u===FALSE)
$u=array('id'=>'', 'voter_id'=>'','logins_number'=>0);
$this->id=$u['id'];
$this->voter_id=$u['voter_id'];
$this->logins_number=$u['logins_number'];
$this->udata=$u;
if (!strlen($this->voter_id)) {
$this->voter_id=substr($this->cookie_id(),-13);
}}function dump_to_session()
{$u=$this->udata;
$u['id']=$this->id;
$u['voter_id']=$this->voter_id;
$u['logins_number']=$this->logins_number;
$p=&moon::page();
$p->save_in_memory('user',$u);
}function voter(){return $this->cookie_id();}
function cookie_id($setValue = NULL) {
if (!is_null($setValue)) {
$this->voter_id = $setValue;
setcookie('voter2',$setValue,time()+86400*500,'/');
}if (strlen($this->voter_id)) {
return $this->voter_id;
}elseif (!empty($_COOKIE['voter2'])) {
return $_COOKIE['voter2'];
}$this->voternew=true;
$id = '';
$a = array('HTTP_USER_AGENT', 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR');
foreach ($a as $v) {
if (!empty($_SERVER[$v])) {
$id .= $_SERVER[$v];
}}return ($this->voter_id=substr(md5($id), -13));
}function is_newbie()
{return $this->voternew;
}function get_language()
{$loc=&moon::locale();
return $loc->language();
}function id()
{return (int)$this->id;
}function get_user_id(){	return (int)$this->id;}
function set_user_id($id)
{$this->id=intval($id);
}function set($name,$value)
{$this->udata[$name]=$value;
}function set_user($name,$value){$this->udata[$name]=$value;}
function get($name)
{return ( isset($this->udata[$name]) ? $this->udata[$name] : '' );
}function get_user($name){return ( isset($this->udata[$name]) ? $this->udata[$name] : '' );}
function logins_per_session()
{return $this->logins_number;
}function name_hello($vardas)
{if ($this->get_language()!='lt') return $vardas;
$vMas=explode(' ',$vardas);
foreach ($vMas as $k=>$name) {
$name=rtrim($name);
$root = substr($name,0,-2);
$end = strtolower(substr($name,-2));
switch($end) {
case 'as': $root.='ai'; break;
case 'us': $root.='au'; break;
case 'is': $root.='i'; break;
case 'ys': $root.='y'; break;
case 'Ä'.'—': $root.='e'; break;
default:
$end = substr($name,-1);
$root= ord($end)===235 ? (substr($name,0,-1).'e'):$name;
}$vMas[$k]=$root;
}return implode(' ',$vMas);
}function get_ip()
{static $ip;
if (is_null($ip)) {
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != $ip) {
$localhost = '~^((0|10|172\.16|192\.168|255|127\.0)\.|unknown)~';
if (empty($ip) || preg_match($localhost, $ip)) {
$ips = array_reverse(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
foreach ($ips as $i => $xIP) {
$xIP = trim($xIP);
if (!preg_match($localhost, $xIP)) {
$ip = $xIP;
break;
}}}}}return $ip;
}function login($m)
{if (isset($m['id'])) $n='id';
elseif (isset($m['ID'])) $n='ID';
elseif (isset($m['Id'])) $n='Id';
else return;
$this->id=$m[$n];
unset($m[$n]);
$this->logins_number++;
$this->udata=$m;
}function logout()
{$this->id='';
$this->udata=array();
}function i_admin($key=false)
{$a=$this->get_user('admin');
if (empty($a)) {
return FALSE;
}elseif (is_array($a)) {
return ($key===FALSE ? TRUE : in_array($key, $a));
} else {
return ($key===FALSE || '*'===$a ? TRUE : ($key == $a));
}}}class moon_page{
var $title,$home_url;
var $device;
var $php_script;
var $was_refresh;
var $dirHtml;
var $varLocal;
var $saveHistory;
var $eventGET, $eventPOST, $eventVars;
var $histStep;
var $cssArray,$jsArray,$metaArray,$alerts,$headLinkArray;
var $history=array();
var $histTag=false;
function moon_page()
{$this->title='';
$this->eventPOST=false;
$this->saveHistory=true;
$this->is404=false;
$this->device='page';
$this->was_refresh=false;
$this->varLocal=$this->cssArray=$this->jsArray=$this->metaArray=$this->headLinkArray=array();
$this->info=$this->vars=array();
$eng=&moon::engine();
$this->urlFunction=$eng->ini('url.function');
if ('' == ($this->home_url = $eng->ini('home_url')) && !empty($_SERVER['SERVER_NAME'])) {
$this->home_url = 'http://' . $_SERVER['SERVER_NAME'] . '/';
}$this->php_script= $eng->ini('php_script');
$this->dirHtml=$eng->ini('dir.html');
$this->lastModified=0;
$this->memory = isset($_SESSION['moonMemory']) ? $_SESSION['moonMemory'] : array();
if (!isset($this->memory['globals'])) $this->memory['globals']=array();
if (!isset($this->memory['pagevars'])) $this->memory['pagevars']=array();
$this->history = isset($this->memory['history']) && is_array($h=$this->memory['history']) ? $h : array();
$this->histStep= count($this->history) ? $this->history[0][0] : 0;
$this->alerts= isset($this->memory['savedAlerts']) && is_array($a=$this->memory['savedAlerts']) ? $a : array();
$this->last_time= isset($this->memory['lastRequestTime']) ? $this->memory['lastRequestTime']:0;
}function close()
{if ($this->saveHistory) {
$title=$this->title();
$this->set_history_tag($this->histTag);
$url=$_SERVER['REQUEST_URI'];
$tag=$this->histTag;
foreach ($this->history as $i=>$v)
if ($v[1]===$url || $v[2]===$tag) {
if ($title=='') $title=$this->history[$i][3];
unset($this->history[$i]);
}array_unshift($this->history,array($this->histStep+1,$url,$tag,$title));
if (count($this->history)>6) array_pop ($this->history);
}$this->memory['history']=$this->history;
$this->memory['savedAlerts']= count($a=$this->alerts) ? $a : '';
$this->memory['lastRequestTime']=time();
$_SESSION['moonMemory']=$this->memory;
}function pause_lasted()
{$r=($this->last_time) ? (time()-$this->last_time):false;
return $r;
}function save_stack($pg,$name,$value)
{if (isset($this->memory['pagevars'][$pg][$name])) unset($this->memory['pagevars'][$pg][$name]);
if (is_array($value) && count($value)) $this->memory['pagevars'][$pg][$name]=$value;
}function get_stack($page,$comName)
{if (isset($this->memory['pagevars'][$page]) && isset($this->memory['pagevars'][$page][$comName])) return $this->memory['pagevars'][$page][$comName];
else return FALSE;
}function save_in_memory($sritis, $values)
{if ($sritis=='') return;
if ( isset($this->memory[$sritis]) ) unset($this->memory[$sritis]);
$this->memory[$sritis]=$values;
}function get_from_memory($sritis)
{if ( isset($this->memory[$sritis]) ) return $this->memory[$sritis];
else return FALSE;
}function get_history($nr=false)
{$hist=array();
if ($nr===false) {
foreach ($this->history as $v)
$hist[]=array('step'=>$v[0],'title'=>$v[3],'url'=>$v[1],'other'=>$v[2]);
} elseif ($nr=abs($nr)) {
$i=1;
foreach ($this->history as $v)
if ($nr==$i++) {
$hist=array('step'=>$v[0],'title'=>$v[3],'url'=>$v[1],'other'=>$v[2]);
break;
}} elseif ($this->saveHistory) {
$hist=array('step'=>$this->histStep+1,'title'=>$this->title(),'url'=>$_SERVER['REQUEST_URI'],'other'=>$this->requested_event().'|'.$this->requested_event('param'));
}return $hist;
}function set_history_tag($tag=false)
{$this->histTag = $tag===false ? $this->requested_event().'|'.$this->requested_event('param') : $tag;
}function home_url($home=false)
{if (is_string($home)) $this->home_url=$home;
return $this->home_url;
}function title($title=false)
{if (is_string($title)) $this->title=$title;
return $this->title;
}function css($value=false,$add=true)
{if ( $value===false ) return array_unique($this->cssArray);
elseif ($add===false) {
$this->cssArray=array_unique($this->cssArray);
$key=array_search($value,$this->cssArray);
if ($key!==false) unset($this->cssArray[$key]);
} else $this->cssArray[]=$value;
}function js($value=false,$add=true)
{if ( $value===false ) return array_unique($this->jsArray);
elseif ($add===false) {
$this->jsArray=array_unique($this->jsArray);
$key=array_search($value,$this->jsArray);
if ($key!==false) unset($this->jsArray[$key]);
} else $this->jsArray[]=$value;
}function last_modified($timeStamp=false)
{if ($timeStamp!==false)
$this->lastModified = (int)$timeStamp==0 ? 0 : max((int) $timeStamp, $this->lastModified);
return $this->lastModified;
}function meta($name=false,$value=false)
{if ( is_string($name) ) $this->metaArray[$name]=$value;
return $this->metaArray;
}function head_link($href=false,$rel='alternate',$title='',$attributes=false)
{if ( $href===false ) return array_unique($this->headLinkArray);
else {
$a=array();
switch ($rel) {
case 'atom': $a['type']='application/atom+xml'; $rel='alternate'; break;
case 'rss': $a['type']='application/rss+xml'; $rel='alternate'; break;
case 'css': $a['type']='text/css'; $rel='stylesheet'; break;
case 'favicon': $rel='shortcut icon'; break;
}if (is_array($attributes)) $a=array_merge($a,$attributes);
if ($title!=='') $a['title']=$title;
$s='';
foreach ($a as $k=>$v) $s.=' '.$k.'="'.htmlspecialchars($v).'"';
$this->headLinkArray[]='<link rel="'.$rel.'" href="'.$href.'"'.$s.' />';
}}function alert($value=false,$type='w')
{if ( $value===false ) {
$r=$this->alerts;
$this->alerts=array();
return $r;
} else $this->alerts[]=array($value,strtolower($type));
}function set_local($name,$value)
{if ( substr($name,-2)==='[]' ) {
$name=substr($name,0,-2);
if (isset($this->varLocal[$name])) {
if (!is_array($this->varLocal[$name])) $this->varLocal[$name]=array($this->varLocal[$name]);
$this->varLocal[$name][]=$value;
} else $this->varLocal[$name]=array($value);
} else $this->varLocal[$name]=$value;
}function get_local($name){
$v=(isset($this->varLocal[$name])) ? $this->varLocal[$name]:'';
return $v;
}function set_global($name,$value)
{if (strlen($name)){
if ($value!=='') $this->memory['globals'][$name]=$value;
elseif (isset($this->memory['globals'][$name])) unset($this->memory['globals'][$name]);
}}function get_global($name)
{return (isset($this->memory['globals'][$name]) ? $this->memory['globals'][$name]:'');
}function php_script()
{return $this->php_script;
}function requested_event($need='event')
{$need=strtolower($need);
switch($need){
case 'event': return ($this->eventPOST===false ? $this->eventGET : $this->eventPOST);
case 'get': return $this->eventGET;
case 'post': return ($this->eventPOST===false ? '' : $this->eventPOST);
case 'vars':
case 'param': return $this->eventVars;
case 'mask': return (isset($this->requestMask) ? $this->requestMask : '');
case 'rest': return (isset($this->requestRest) ? $this->requestRest : '');
case 'segments':
if (isset($this->requestRest)) {
$d=explode('/',$this->requestRest);
foreach ($d as $k=>$v) {
$d[$k]=urldecode($v);
}}else {
$d = array();
}return $d;
}}function redirect($url,$responseCode=302)
{$this->forget();
moon_close();
if (!headers_sent()) {
switch ((int)$responseCode) {
case 301: header("HTTP/1.1 301 Moved Permanently",true,301);break;
case 303: header("HTTP/1.1 303 See Other",true,303);break;
case 307: header("HTTP/1.1 307 Temporary Redirect",true,307);break;
}header("Location: $url");
}exit();
}function page404()
{$this->forget();
$this->meta('robots','noindex');
$e=&moon::engine();
if (($ev=$e->ini('event404')) && !$this->is404) {
$e->eventDisabled=false;
$this->is404=true;
$e->call_event($ev);
$e->halt='';
$e->components=array();
$s=$e->call_component($e->ini('output'), $e->_construct_content());
} else $s='';
moon_close();
if (!headers_sent()) header("HTTP/1.0 404 Not Found");
die($s);
}function sys_linkas($ev,$par,$arrayGET=false)
{return htmlspecialchars($this->make_url($ev,$par,$arrayGET));
}function make_url($ev,$par,$arrayGET=false)
{$get='';
if (is_array($arrayGET) && count($arrayGET)) {
$g=array();
foreach ($arrayGET as $k=>$v) $g[]=$k.'='.urlencode($v);
$get='?'.implode('&',$g);
}elseif (is_string($arrayGET)) {
$get = '?' . ltrim($arrayGET, '?');
}$url=false;
if ($this->urlFunction && function_exists($this->urlFunction))
$url=call_user_func($this->urlFunction,$ev.$get,$par);
if (is_string($url)) return $url;
$e=&moon::engine();
$url=$e->url_construct($ev.$get,$par);
return $url;
}function analyze_request($url)
{$parsed=false;
if ($this->urlFunction && function_exists($this->urlFunction))
$parsed=call_user_func($this->urlFunction);
if (is_array($parsed)) {
$this->eventGET=isset($parsed[0]) ? $parsed[0] : '';
$this->eventVars=isset($parsed[1]) ? $parsed[1] : '';
} else {
$e=&moon::engine();
list($this->eventGET,$this->eventVars)=$e->url_parse($url);
}$this->_post_handler();
}function refresh_field(){
$id=uniqid('');
return "<input type=\"hidden\" name=\"_refresh_\" value=\"$id\" />";
}function was_refresh()
{return $this->was_refresh;
}function forget()
{$this->saveHistory=false;
}function back($redirect=false)
{if (count($h=$this->get_history(-1))) {
if ($redirect) {
$this->redirect($h['url'], 303);
} else {
$d=explode('|',$h['other']);
if (isset($d[1]) && $d[0]!=='') $this->call_event($d[0],$d[1]);
}}}function call_event($event,$par='')
{$e=&moon::engine();
return $e->call_event($event,$par);
}function history_step()
{return $this->histStep;
}function halt($event,$par='')
{$eng=&moon::engine();
$eng->halt($event,$par);
}function html($failas,$links=false)
{if ($failas=='' || $this->dirHtml===false) return '';
$failas=$this->dirHtml.$failas;
if (!file_exists($failas)){return '';}
$s=moon_template::get_file_content($failas);
if ($links) moon_template::parse_links($s);
return $s;
}function insert_html($html,$layoutPosition)
{$eng=&moon::engine();
if (isset($eng->content[$layoutPosition])) $eng->content[$layoutPosition].=$html;
else $eng->content[$layoutPosition]=$html;
}function &load_component($name)
{$eng=&moon::engine();
$com=&$eng->_load_component($name);
return $com;
}function _post_handler()
{if (isset($_POST['_event_'])) $ev=trim($_POST['_event_']);
else $ev= isset($_POST['event']) ?  trim($_POST['event']) : '';
if (strlen($ev))	{
$this->eventPOST=strtolower($ev);
if (isset($_POST['_refresh_'])) $r=$_POST['_refresh_'];
elseif (isset($_POST['refresh'])) $r=$_POST['refresh'];
else $r='';
if ($r!='') $this->was_refresh=$this->_post_was_refresh($r);
$this->forget();
}}function _post_was_refresh($id)
{$ini=&moon::cfg();
if (!$ini->has('sys','tb.Refresh') || '' == ($table=$ini->get('sys','tb','Refresh'))) {
return FALSE;
}$db=&moon::db($ini->get('sys','sys','db'));
$id=$db->escape(substr($id,0,13));
$laikas=moon::locale()->now();
$m=$db->single_query("SELECT 1 FROM $table WHERE ID_='$id' LIMIT 1");
if (rand(1,100)==1) {
$db->query("DELETE FROM $table WHERE TIME_<".($laikas-86400));
}if (isset($m[0])){
return TRUE;
}else{
$db->query("INSERT INTO $table(ID_,TIME_) VALUES('$id','$laikas')");
return FALSE;
}}function referer($showLocal=false)
{$referer=(isset($_SERVER['HTTP_REFERER'])) ? trim($_SERVER['HTTP_REFERER']):'';
if ($referer==='') return '';
$pos=strpos(strtolower($referer), $this->home_url);
if ($pos===false) return $referer;
elseif ($showLocal) return substr($referer,strlen($this->home_url));
else return '';
}function uri_segments($no=false)
{if (!empty($_GET['replace_'])) $uri = $_GET['replace_'];
else $uri=(isset($_SERVER['REQUEST_URI'])) ? trim($_SERVER['REQUEST_URI']):'';
list($uri)=explode('?',$uri);
$d=explode('/',$uri);
foreach ($d as $k=>$v) $d[$k]=urldecode($v);
$d[0]=$uri;
if ($no===false) {
return $d;
} else {
return (isset($d[(int)$no]) ? (string)$d[(int)$no] : '');
}}function set_request_mask($mask, $rest='')
{$this->requestMask = $mask;
$this->requestRest = $rest;
}}class moon_template{
var $isMoon;
var $bodies;
var $zodLang,$zodFile, $words;
var $oldVersion;
var $file;
var $wasError=false;
function __construct($tplFile, $zodFile='', $zodLang = FALSE)
{$this->isMoon = is_callable('moon::error');
$this->bodies = array();
$this->zodLang = $zodLang === FALSE && $this->isMoon ? moon::locale()->current_locale() : $zodLang;
$this->zodFile = $zodFile;
$this->words = array();
if ($tplFile != '') {
$this->file = $tplFile;
$this->load($tplFile);
}}function load() {
$this->wasError=false;
if ($this->isMoon && ($dirCache = moon::engine()->ini('dir.chtm'))) {
$cName = rtrim($this->file . substr(md5($this->zodFile),-8) . $this->zodLang, '.');
$cache = moon::cache($dirCache);
if (FALSE !== ($str = $cache->get($cName, $this->getContextTS())) && is_array($str)) {
$this->bodies = $str;
return;
}}$str = $this->get_file_content($this->file);
if ('' != $this->zodFile) {
$this->use_language_pack($this->zodFile,$this->zodLang);
$this->_translate($str);
}$this->_load_text($str);
if ($this->isMoon && !$this->wasError && $dirCache) {
$cache->save($cName, $this->bodies, '24h');
}}function load_file($file,$fromCache=false)
{$this->file = $file;
$this->load();
}protected function getContextTS()
{$maxTS = 0;
$files = '' != $this->zodFile ? explode(';', $this->zodFile) : array();
$files[] = $this->file;
foreach ($files as $file) {
if ('' === ($file = trim($file))) {
continue;
}if (file_exists($file)) {
$maxTS = max($maxTS,filemtime(trim($file)));
}else {
$this->error('file404','N',__LINE__, array('failas'=>$file, 'forFile'=>$this->file));
}}return $maxTS;
}function use_language_pack($fileName,$lang)
{if ($this->isMoon || class_exists('moon_ini') ) {
$this->words = array();
$files = explode(';', $fileName);
foreach ($files as $file) {
if ( '' === ($file = trim($file)) ) {
continue;
}if (file_exists($file)) {
$zod=new moon_ini($file);
if (!$zod->has($lng = $lang, FALSE)) {
$groups=$zod->read_groups();
if (isset($groups[0])) {
$lng = isset($groups[0]) ? $groups[0] : FALSE;
}}if ($lng !== FALSE) {
$this->words += $zod->read_group($lng);
}}}$this->zodLang=$lang;
}$this->zodLang=$lang;
$this->zodFile=$fileName;
}function explode_ini($name) {return $this->parse_array($name);}
function parse_array($name,$vars='')
{$s=$this->parse($name,$vars);
$p=explode("\n",$s);
$mas=array();
foreach ($p as $v) {
$pos=strpos($v,'=');
if ($pos!==false){
$n0=trim( substr($v,0,$pos) );
$n1=trim( substr($v,$pos+1) );
if (strlen($n0.$n1)) $mas[$n0]=$n1;
}}return $mas;
}function parse($name,$vars='')
{if ($this->wasError) return '';
if (isset($this->bodies[$name])) $s=$this->bodies[$name];
else{
$this->error(5,'N',__LINE__,array('file'=>$this->file,'match'=>$name));
return '';
}$isVars=is_array($vars);
if (is_array($s)) {
$ss='';
$blocks=count($s);
for ($i=0;$i<$blocks;$i++) {
$block=$s[$i];
if (is_array($block)) {
$v=$block[0];
if ($v===true || !($isVars && isset($vars[$v]) && $vars[$v]) ) {
$i=$block[1]-1;
}} else $ss.=$block;
}$s=$ss;
}if ($isVars)
foreach ($vars as $k=>$v) $s=str_replace('{'.$k.'}',$v,$s);
return $s;
}function save_parsed($name,$vars='',$as='')
{if ($this->wasError) return '';
if (isset($this->bodies[$name])) $s=$this->bodies[$name];
else{
$this->error(5,'N',__LINE__,array('match'=>$name));
return '';
}if ($as==='') $as=$name;
if (is_array($vars)) {
if (is_array($s)) {
$blocks=count($s);
for ($i=0;$i<$blocks;$i++) {
$block=$s[$i];
if (!is_array($block) )
foreach ($vars as $k=>$v) $block=str_replace('{'.$k.'}',$v,$block);
$this->bodies[$as][$i]=$block;
}} else {
$block=$this->bodies[$name];
foreach ($vars as $k=>$v) $block=str_replace('{'.$k.'}',$v,$block);
$this->bodies[$as]=$block;
}} else $this->bodies[$as]=$s;
}function ready_js($text)
{return str_replace(array("\n", "\r", '"', '</'), array('\n', '', '\"', '<\/'), addcslashes((string) $text, "\0..\37'\\"));
}function remove_garbage($str,$replaceto='')
{return preg_replace('/\{([^\}]+)\}/',$replaceto, $str);
}function has_part($name)
{return isset($this->bodies[$name]);
}function &get_file_content($file)
{if ( file_exists( $file ) ) {
$s = file_get_contents($file);
}else{
$s = FALSE;
moon_template::error(1,'F',__LINE__,array("file"=>$file));
}return $s;
}function save($fileName='')
{}function _load_text_old(&$str)
{$this->parse_links($str);
$reg = '/<!--([!]*)begin ([.\w]+)([!\s]*)-->(.*)<!--([!]*)end \\2([!\s]*)-->/sm';
preg_match_all($reg, $str, $m);
if (!count($m[2])){
$this->error(2,'W',__LINE__,'');
return FALSE;
}foreach ($m[2] as $i=>$v) $this->bodies[$v]=$m[4][$i];
return TRUE;
}function _load_text(&$str)
{$str=preg_replace("/<!--#(.*?)-->/s",'',$str);
$this->parse_links($str);
$reg = '/<%([^%]+)%>/smU';
$m=preg_split($reg,$str,-1,PREG_SPLIT_DELIM_CAPTURE);
if (count($m)<2) {
if ($this->_load_text_old($str)) {
$this->error('old_template_file','N',__LINE__,array('file'=>$this->file));
return;
}$this->wasError=true;
$this->error('empty_template','N',__LINE__,array('file'=>$this->file));
return;
}$name='';
$noTrim=$parts=array();
$skip=true;
$ifs=array();
$restorePoint=array();
$blockNo=0;
foreach ($m as $k=>$v) {
if ($k % 2) {
$a=trim($v);
if ($a==='/end' || $a==='end' ) $a.='{}';
if ( ($pos1=strpos($a,'{'))!==false && ($pos2=strrpos($a,'}'))!==false) {
$tag=strtolower(rtrim(substr($a,0,$pos1)));
$a=trim(substr($a,$pos1+1,$pos2-$pos1-1));
switch ($tag) {
case 'if':
$ifs[]=array($a,$blockNo);
$block=array($a,$blockNo+1);
break;
case 'else':
$ifNo=count($ifs)-1;
if ($ifNo>=0) $el=$ifs[$ifNo];
if ($ifNo<0 || $el[0]!=$a) {
$this->error('bad_else','W',__LINE__,array('name'=>$a,'block'=>$name));
$block=false;
break;
}$parts[$name][$el[1]][1]=$blockNo+1;
$ifs[$ifNo][1]=$blockNo;
$block=array(true,$blockNo+1);
break;
case '/end':
if (count($restorePoint)) {
$noTrim[]=$name;
}case '':
case 'end':
if (count($ifs)) {
$el=array_pop($ifs);
$parts[$name][$el[1]][1]=$blockNo;
} elseif (count($restorePoint)) {
list($name,$ifs,$blockNo)=array_pop($restorePoint);
}$block=false ;
break;
case 'loop':
case 'insert':
$ta=explode('+=',$a);
if (!isset($ta[1])) $ta[1]=trim($ta[0]);
$a=trim($ta[1]);
$parts[$name][]='{'. trim($ta[0]) .'}';
$blockNo++;
$block=false ;
$restorePoint[]=array($name,$ifs,$blockNo);
$name=$a;
$ifs=array();
$blockNo=0;
if (isset($parts[$a])) $this->error('dublicate_block','W',__LINE__,array('block'=>$a));
break;
default:
$block=false ;
}if ($block!==false) $parts[$name][$blockNo++]=$block;
continue;
}if ($a{0}==='/') {
$a=ltrim(substr($a,1));
$skip=true;
if ($a===$name) $noTrim[]=$name;
$name=$a;
} else {
$skip=false;
if (count($ifs)) $this->error('ifs_not_closed','W',__LINE__,array('block'=>$name));
if (isset($parts[$a])) $this->error('dublicate_block','W',__LINE__,array('block'=>$a));
$parts[$name=$a]=array();
$blockNo=0;
$ifs=array();
}} else {
if (!$skip) {
$parts[$name][]=$v;
$blockNo++;
}}}if (count($ifs)) $this->error('ifs_not_closed','W',__LINE__,array('block'=>$name));
$toTrim=array_diff(array_keys($parts) , $noTrim );
foreach ($toTrim as $name) {
$count=count($parts[$name])-1;
$parts[$name][0]=ltrim($parts[$name][0]);
$parts[$name][$count]=rtrim($parts[$name][$count]);
}foreach ($parts as $k=>$v) if (count($v)==1) $parts[$k]=$v[0];
$this->bodies=$parts;
}function _translate(&$str)
{$e = &moon::engine();
$words = $e->txt_constants();
if (!empty($this->words)) {
$words += $this->words;
}foreach($words as $k => $v) {
$str = str_replace("{!$k}", (string)$v, $str);
}return true;
}function parse_links(&$str)
{static $tplCache = array();
if (strpos($str,"{!link:")===FALSE || !$this->isMoon) return false;
$p=&moon::page();
preg_match_all('/\{!link:([^\}]+)\}/', $str, $m);
$m[1] = array_unique($m[1]);
foreach ($m[1] as $j=>$v) {
$i = (string)$m[0][$j];
if (isset($tplCache[$i])) {
$replaceto = $tplCache[$i];
}else {
$parts=explode('|',$v);
if (!isset($parts[1])) $parts[1]='';
$tplCache[$i] = $replaceto=$p->sys_linkas($parts[0],$parts[1]);
}$str=str_replace( $i,$replaceto,$str);
}return true;
}function error($code,$tipas,$line,$m='')
{$m['file']=$this->file;
if (is_callable('moon::error')) {
moon :: error(array("@template.$code",$m), $tipas);
} else echo 'template['.$code.'] '.serialize($m);
}}class moon_xml {
var $info;
var $comp;
var $dirCxml;
function load_xml($name)
{if ($dirCache = moon::engine()->ini('dir.cxml')) {
$xmlF = $this->_get_path($name);
$ts = $xmlF && file_exists($xmlF) ? filemtime($xmlF) : 0;
$cache = moon::cache($dirCache);
if (FALSE !== ($str = $cache->get($name.'.cxml', $ts)) && is_array($str)) {
return $str;
}}$this->_reset();
if ($name != '') {
libxml_use_internal_errors(true);
$this->_add_xml($name);
}$this->info['name']=$name;
$this->info['com']=$this->comp;
if ($dirCache){
$cache->save($name.'.cxml', $this->info, '24h');
}return $this->info;
}function _reset()
{$this->info=array('name'=>'','title'=>'','layout'=>'','parent'=>'','set_local'=>'','forget'=>'');
$this->comp=array();
}function _add_xml($xmlName,$may_extend=true)
{if ('' == ($filePath = $this->_get_path($xmlName))) {
return;
}$xml = simplexml_load_file($filePath);
if ($xml === FALSE) {
foreach (libxml_get_errors() as $error) {
moon::error(trim($error->message) . " in $error->file ($error->line) ");
break;
}return;
}if ('page' !==$xml->getName()) return false;
if (!empty($xml->{'extends'}) && $may_extend) {
$v = (string)$xml->{'extends'};
$delayed=$this->_add_xml($v,false);
$this->info['parent']=$v;
} else $delayed=false;
if (!empty($xml->{'clear'})) {
$dp=explode(',',(string)$xml->{'clear'});
foreach ($dp as $v) $this->_delete_part($v);
}if ($xml->com) {
$this->_get_components($xml->com);
}if ($xml->template) $this->info['layout'] = (string)$xml->template;
elseif ($xml->layout) $this->info['layout'] = (string)$xml->layout;
if ($xml->no_history) $this->info['forget']= (int)$xml->no_history;
if ($xml->title) $this->info['title'] = (string)$xml->title;
if($delayed!==false) {
$this->_get_components($delayed);
}if ($may_extend && $xml->delayed->count()) $this->_get_components($xml->delayed->com);
if($xml->delayed && $xml->delayed->com) return $xml->delayed->com;
else return false;
}function _get_components($xmlCom)
{foreach ($xmlCom as $com) {
$vars = array();
if ($com->{'var'}) {
foreach ($com->{'var'} as $var) {
$vars[(string)$var['name']] = (string)$var;
}}$this->comp[]=array('part'=>(string)$com->part,'name'=>(string)$com->name,'vars'=>$vars);
}}function _delete_part($name)
{$name=trim($name);
foreach ($this->comp as $k=>$v)
if ($v['part']==$name) unset($this->comp[$k]);
}function _get_path($name)
{$fname=explode('.',$name);
if (count($fname)==2){
$ini=&moon::cfg();
$d=$ini->get($fname[0],'sys');
$filename=MOON_MODULES.$d['moduleDir'].$fname[1].'.xml';
}else{
$eng=&moon::engine();
$eng->_error('noxml','F',__LINE__,array('name'=>$name));
$filename='';
}return $filename;
}}class moon_cache {
var $cacheOn = TRUE;
var $now = 0;
var $fileName = '';
var $dirCache;
var $memcache = FALSE;
var $memcachePrefix = '';
function moon_cache($location = '') {
static $memcacheConnections;
$this->dirCache = FALSE;
$this->now = isset ($_SERVER['REQUEST_TIME']) ? (int) $_SERVER['REQUEST_TIME']:time();
if ('default' === $location) {
$eng = & moon :: engine();
$this->dirCache = $eng->ini('dir.cache');
}elseif ($location) {
$this->dirCache = $location;
}if ('memcache' == substr($this->dirCache, 0, 8) && moon :: moon_ini()->has($this->dirCache)) {
$cfg = moon :: moon_ini()->read_group($this->dirCache);
$this->dirCache = empty ($cfg['failover']) ? FALSE:$cfg['failover'];
if (!empty ($cfg['server']) && function_exists('memcache_connect')) {
$port = isset ($cfg['port']) && '' != $cfg['port'] ? 11211:$cfg['port'];
if (isset($memcacheConnections[$conID = $cfg['server'].':'.$port])) {
$this->memcache = $memcacheConnections[$conID];
}else {
$this->memcache = @memcache_connect($cfg['server'], $port);
if (!is_object($this->memcache)) {
moon :: error('Can not connect to memcache: ' . $cfg['server'] . ':' . $port);
$this->memcache = FALSE;
}$memcacheConnections[$conID] = $this->memcache;
}}}$this->memcachePrefix = isset ($cfg['prefix']) && '' != $cfg['prefix'] ? $cfg['prefix'] : '';
if (isset($_SERVER['SERVER_NAME'])) {
$this->memcachePrefix .= $_SERVER['SERVER_NAME'];
}$this->memcachePrefix .= ':'.$location.':';
if ($this->dirCache != FALSE && !file_exists($this->dirCache)) {
moon :: error('Cache directory ' . $this->dirCache . ' does not exist.');
$this->dirCache = FALSE;
}$this->cacheOn = $this->dirCache === false && $this->memcache == FALSE ? false:true;
}function file($name) {
$name = trim($name);
if ($name !== '') {
$dir = dirname($name);
if ($dir) {
$name = basename($name) . '-' . substr(md5($this->memcachePrefix . $dir), - 20);
}}$this->fileName = $name . '.cache';
}function get($name = false, $changed = 0) {
if ($name != false) {
$this->file($name);
}if ($this->cacheOn && '' != $this->fileName) {
if ($this->memcache) {
$s = memcache_get($this->memcache, $this->fileName);
}elseif (file_exists($file = $this->dirCache . $this->fileName)) {
$s = file_get_contents($file);
}else {
$s = FALSE;
}if ($s !== FALSE) {
$ts = explode('+', trim($this->memcache && is_array($s) ? $s[0] : substr($s, 0, 17)));
$saved = (int) $ts[0];
$expires = isset ($ts[1]) ? $saved + $ts[1]:$saved;
if ($saved && $saved > abs($changed + 3) && $this->now < $expires) {
return $this->memcache && is_array($s) ? $s[1] : unserialize(substr($s, 17));
}}}return false;
}function save($content, $expires = '24h') {
if (func_num_args()===3) {
list($name, $content, $expires) = func_get_args();
if ($name != false) {
$this->file($name);
}}if ($this->cacheOn && $this->fileName) {
if (($expires = $this->_laikas($expires))) {
$ts = str_pad($this->now . '+' . $expires, 17);
if ($this->memcache) {
$ok = memcache_set($this->memcache, $this->fileName, array($ts, $content), MEMCACHE_COMPRESSED, $expires);
if (!$ok) {
$this->memcache = FALSE;
$this->cacheOn = $this->dirCache === false ? false:true;
}}else {
$r = file_put_contents($file = $this->dirCache . $this->fileName, $ts . serialize($content));
if (FALSE !== $r) {
moon :: chmod($file);
}}}}}function delete($name = false) {
if ($name != false) {
$this->file($name);
}if ($this->cacheOn && '' != $this->fileName) {
if ($this->memcache) {
memcache_delete($this->memcache, $this->fileName);
}elseif (file_exists($file = $this->dirCache . $this->fileName)) {
unlink($file);
}}}function clean($name) {
if ($this->memcache) {
memcache_flush($this->memcache);
}elseif (FALSE !== $this->dirCache) {
foreach (glob($this->dirCache . $name . '.cache') as $file) {
unlink($file);
}}}function on($isOn = true) {
return ($this->cacheOn = $isOn);
}function _laikas($in) {
$l = strtoupper(substr($in = trim($in), - 1));
$in = intval($in);
if ($l === 'H') {
$in = 3600 * $in;
}elseif ($l === 'M') {
$in = 60 * $in;
}return ($in > 86400 ? 86400 : $in);
}}class moon_file{
var $curlOptions;
var $mode;
var $path;
var $fileName;
var $fileTime;
var $fileSize;
var $fileWH;
var $myContentTypes;
var $browser;
var $isClone;
function moon_file( )
{$this->curlOptions=array();
if (defined('CURLOPT_CONNECTTIMEOUT')) {
$this->curlOptions[CURLOPT_CONNECTTIMEOUT] = 0;
}$this->mode='';
$this->isClone=false;
$this->path=null;
$this->myTypes=array();
$user_agent=(isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT']:'';
$this->browser=(strpos($user_agent,'MSIE')!=false) ? 'IE':'OTHER';
}function is_upload($name,&$err)
{$this->mode='upload';
if(isset($_FILES[$name])) {
$df=$_FILES[$name];
$err= isset($df['error']) ? $df['error']:0;
if ($err || empty($df['size'])) {
if ($err != 4) {
moon::error(array("@upload.error",array('error' => $err)), 'W');
}return FALSE;
}if (isset($df['tmp_name']) && is_uploaded_file($df['tmp_name'])) {
$this->path = $df['tmp_name'];
$this->fileName = $df['name'];
$this->fileSize = $df['size'];
$this->fileTime=time();
$i =@getimagesize($this->path);
if (is_array($i)) $this->fileWH=$i[0].'x'.$i[1];
else $this->fileWH='';
return true;
}} else $err=1;
return false;
}function is_file($path)
{$this->mode='file';
if (is_file($path)) {
$this->path=$path;
$this->fileName=basename($this->path);
$this->fileSize=-1;
$this->fileTime=filemtime($path);
$this->fileWH='';
return true;
}return false;
}function is_info($dir, $infoString)
{$this->mode='info';
$d=$this->info_unpack($infoString);
if ($d===false || !is_file($dir.$d['name_saved'])) return false;
$this->path=$dir.$d['name_saved'];
$this->fileName = $d['name_original'];
$this->fileSize = $d['size'];
$this->fileTime=$d['time'];
$this->fileExt=$d['ext'];
$this->fileWH=$d['wh'];
return true;
}function is_url_content($url,$saveAs,$timeout=20)
{$this->get_url_content($url, $saveAs, $timeout);
return $this->is_file($saveAs);
}function get_url_content($url,$saveAs=false, $timeout=20)
{if ($saveAs) {
if( !($f = @fopen($saveAs, 'wb')) ) return false;
flock($f,LOCK_EX);
}if (function_exists('curl_init')) {
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_FAILONERROR, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Moon');
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
if ($timeout > 0) {
curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeout);
}foreach ($this->curlOptions as $k=>$v) curl_setopt($ch, $k, $v);
if ($saveAs) curl_setopt($ch, CURLOPT_FILE, $f);
else curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$s=curl_exec($ch);
$err=curl_errno($ch);
curl_close($ch);
} else {
$s=@file_get_contents($url);
$err= $s===false ? 1:0;
if ($saveAs && !$err) $r = fputs($f, $s);
}if ($saveAs) {
flock($f,LOCK_UN);
fclose($f);
moon::chmod($saveAs);
if ($err) @unlink($saveAs);
return ($err ? false : true);
} else return $s;
}function file_name($newName=false)
{if ($newName!==false) $this->fileName=$newName;
return $this->fileName;
}function file_path()
{return $this->path;
}function file_ext($filename=false)
{if ($filename===false) $filename=$this->fileName;
$ext=strrchr($filename,'.');
if ($ext===false) $ext='';
else $ext=strtolower(substr($ext, 1));
return $ext;
}function file_size()
{if ($this->fileSize===-1) $this->update_size();
return $this->fileSize;
}function file_wh()
{if ($this->fileSize===-1) $this->update_size();
return $this->fileWH;
}function file_info()
{$d=array('time'=>$this->fileTime, 'size'=>$this->file_size(), 'ext'=>$this->file_ext(),
'wh'=>$this->fileWH, 'name_saved'=>basename($this->path), 'name_original'=>$this->fileName);
return $this->info_pack($d);
}function copy($pathTo)
{$r=copy($this->path, $pathTo);
if ($r && $this->is_file($pathTo)) {
moon::chmod($pathTo);
return true;
}else return false;
}function save_as($pathTo)
{$oldName=$this->file_name();
if ($this->isClone) $r=copy( $this->path, $pathTo );
elseif ($this->mode==='upload') $r=move_uploaded_file( $this->path, $pathTo );
else {
$r=rename($this->path, $pathTo);
}if ($r && $this->is_file($pathTo)) {
moon::chmod($pathTo);
$this->file_name($oldName);
$this->isClone=false;
return true;
}else return false;
}function delete()
{if (is_file($this->path)) unlink($this->path);
$this->path=null;
$this->mode='';
}function class_copy()
{$a=$this;
$a->isClone=true;
return $a;
}function info_unpack($infoString)
{$d=explode('|',$infoString);
if (($k=count($d))<6) return false;
$m=array('time'=>$d[0],'size'=>$d[1],'ext'=>$d[2],'name_saved'=>$d[3], 'wh'=>$d[4], 'name_original'=>$d[5]);
if ($k==7) {
$m['wh']=$d[4].'x'.$d[5];
$m['name_original']=$d[6];
}if ($m['name_original']==='') $m['name_original']=$m['name_saved'];
return $m;
}function info_pack($infoArray)
{$names=array('time','size','ext','name_saved','wh','name_original');
$d=array();
foreach($names as $v) $d[$v]=isset($infoArray[$v]) ? $infoArray[$v]:'';
if ($d['name_saved']===$d['name_original']) $d['name_original']='';
$sLen=strlen($d['name_saved']);
if ($sLen>55) {
$d['name_saved']=$this->shorten_filename($d['name_saved'],55);
$sLen=strlen($d['name_saved']);
}$d['name_original']=$this->shorten_filename($d['name_original'],110-$sLen);
return implode('|',$d);
}function has_extension($extList)
{$curExt=$this->file_ext();
if ($extList=='') return $curExt;
$d=explode(',',str_replace('.','',$extList));
foreach ($d as $v) if (trim($v)==$curExt) return $curExt;
return false;
}function strip_extension($name=false)
{$curExt=$this->file_ext($name);
if ($curExt!=='') $curExt='.'.$curExt;
if ($name===false) $name=$this->fileName;
return substr($name,0,strlen($name)-strlen($curExt));
}function format_size($size,$dec=0,$skirk=' ')
{if ($size=='') return '';
elseif ($size<1024) $size.=$skirk.'B';
elseif ($size<1048576) $size=round($size/1024,$dec).$skirk.'KB';
else $size=round($size/1048576,$dec).$skirk.'MB';
return $size;
}function shorten_filename($filename,$maxLen)
{$maxLen=abs($maxLen);
$nLen=strlen($filename);
if ($nLen<=$maxLen || $maxLen<8) return $filename;
$ext=strrchr($filename,'.');
if ($ext===false) return rtrim(substr($filename,0,$maxLen));
else {
$maxLen--;
$eLen=strlen($ext);
if ($eLen>=$maxLen) {
$ext=substr($ext,0,$maxLen-4);
$eLen=strlen($ext);
}return rtrim(substr($filename,0,$maxLen-$eLen),' .').'~'.$ext;
}}function update_size()
{if (is_null($this->path)) {
$this->fileWH=$this->fileSize='';
}$i =@getimagesize($this->path);
if (is_array($i)) $this->fileWH=$i[0].'x'.$i[1];
else $this->fileWH='';
$i=@filesize($this->path);
$this->fileSize= $i ? (int)$i : 0;
}function send_headers($filename=false,$size=false)
{if (headers_sent()) return;
if ($filename===false) $filename=$this->fileName;
if ($size===false) $size=$this->file_size();
header('Content-Type: '.$this->content_type($this->file_ext($filename)));
if ($size) header("Content-Length: ".$size);
if ($this->fileTime>0) header("Last-Modified: ".date('r',$this->fileTime));
if ($this->browser=='IE') {
header('Content-Disposition: inline; filename="'.$filename.'"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
} else {
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Expires: 0');
header('Pragma: no-cache');
}}function content_type($ext)
{$ext=strtolower($ext);
if (isset($this->myContentTypes[$ext])) return $this->myContentTypes[$ext];
$repl=array('dot'=>'doc','xla'=>'xls','midi'=>'mid','jpeg'=>'jpg','tiff'=>'tif','html'=>'htm','mpeg'=>'mpg','qt'=>'mov','pps'=>'ppt','eps'=>'ps');
if (isset($repl[$ext])) $ext=$repl[$ext];
$repl=array(
'doc'=>'application/msword',
'ppt'=>'application/vnd.ms-powerpoint',
'xls'=>'application/vnd.ms-excel',
'pdf'=>'application/pdf',
'ps'=>'application/postscript',
'zip'=>'application/zip',
'csv'=>'text/x-csv',
'gif'=>'image/gif',
'jpg'=>'image/jpeg',
'tif'=>'image/tiff',
'png'=>'image/png',
'htm'=>'text/html',
'xml'=>'text/xml',
'js'=>'text/javascript',
'txt'=>'text/plain',
'mpg'=>'video/mpeg',
'mov'=>'video/quicktime',
'avi'=>'video/x-msvideo',
'mid'=>'audio/midi',
'rtf'=>'application/rtf',
'tar'=>'application/x-tar',
'gz'=>'application/x-gzip'
);
return (isset($repl[$ext]) ? $repl[$ext] : 'application/octet-stream');
}function define_content_type($ext,$tipas)
{$this->myContentTypes[$ext]=$tipas;
}function download()
{$this->send_headers();
return @readfile($this->file_path());
}function show_image()
{header('Content-Type: '.$this->content_type($this->file_ext()));
if ($size=$this->file_size()) header("Content-Length: ".$size);
if ($this->fileTime>0) header("Last-Modified: ".date('r',$this->fileTime));
header('Content-Disposition: inline; filename="'.$this->file_name().'"');
return @readfile($this->file_path());
}}class moon_xml_read{
var $doc;
var $gylis=0;
var $parser;
var $hist,$tags;
var $isArray;
var $fileName='n/a';
function __construct() {
$this->_error('moon_xml_read class is DEPRECATED','F',__LINE__);
}function &parse_data($simple)
{$this->doc=array();
$this->hist=array(0=>-1);
$this->tags=array();
$this->gylis=0;
$this->parser = xml_parser_create();
xml_set_object($this->parser,$this);
xml_parser_set_option ($this->parser,XML_OPTION_CASE_FOLDING, 0);
xml_set_element_handler($this->parser, '_startElement', '_endElement');
xml_set_character_data_handler($this->parser, '_characterData');
if (!xml_parse($this->parser, $simple)) $this->_error( 'xml_error','F',__LINE__,array(
'file'=> $this->fileName,
'error'=>xml_error_string(xml_get_error_code($this->parser)),
'line'=>xml_get_current_line_number($this->parser)  ));
xml_parser_free($this->parser);
$this->doc=$this->_build_branch(-1,'');
return $this->doc;
}function &parse_file($file)
{$this->fileName = $file;
$s=''==$file ? FALSE : file_get_contents($file);
if ($s===false) {
$this->_error( 'not_found','F',__LINE__,array('file'=>$file));
$this->doc=array();
} else $this->parse_data($s);
$this->fileName = 'n/a';
return $this->doc;
}function define_arrays($a)
{if (is_array($a)) $this->isArray=$a;
}function _startElement($parser, $name, $attribs)
{$id=count($this->tags);
$pid=$this->hist[$this->gylis];
$kiek=count($attribs);
if ($kiek==1) foreach ($attribs as $k=>$v) $attr=$v;
else $attr='';
$this->tags[$id]=array('name'=>$name,'data'=>'','parent'=>$pid,'attr'=>$attr);
if ($kiek>1) {
$i=0;
foreach ($attribs as $k=>$v) $this->tags[$id+(++$i)]=array('name'=>$k,'data'=>$v,'parent'=>$id, 'attr'=>'');
}$this->gylis++;
$this->hist[$this->gylis]=$id;
}function _endElement($parser, $name)
{$id=$this->hist[$this->gylis];
$this->tags[$id]['data']=trim($this->tags[$id]['data']);
$this->gylis--;
}function _characterData($parser, $data)
{$this->tags[$this->hist[$this->gylis]]['data'].=$data;
}function _build_branch($father,$sHist)
{$maxNr=count($this->tags);
$out=array();
$brothers=array();
for ($i=$father+1;$i<$maxNr;$i++) {
$tg=&$this->tags[$i];
if ($tg['parent']<$father) break;
elseif ($father==$tg['parent']) {
$name=&$tg['name'];
if (isset($brothers[$name])) $brothers[$name]++;
else $brothers[$name]=1;
$hist=$sHist.$name;
$tmp=$this->_build_branch($i,$hist.'.');
$hasData=(strlen($tg['data'])) ? true:false;
$hasChild=(count($tmp)) ? true:false;
if ($hasChild) {
if ($hasData) {
if (isset($tmp['?'])) $tmp['?'].=rtrim($tg['data']);
else $tmp['?']=rtrim($tg['data']);
}$dat=$tmp;
} else $dat=$tg['data'];
if ($tg['attr']!=='') $out[$name][$tg['attr']]=$dat;
else {
if ($this->_is_array($hist)) {
if (!isset($out[$name])) $out[$name]=array();
$out[$name][]=$dat;
} elseif ($brothers[$name]==1) $out[$name]=$dat;
else {
if ($brothers[$name]==2) $out[$name]=array($out[$name]);
$out[$name][]=$dat;
}}}}return $out;
}function _is_array($path)
{if (is_array($this->isArray)) {
foreach ($this->isArray as $v) {
if ($v==$path) return true;
elseif (strpos($v,'*')){
$d=explode('.',$v);
$ds=explode('.',$path);
if (count($d)==count($ds)) {
foreach ($d as $k=>$r) if ($r!=='*' && $r!=$ds[$k]) return false;
return true;
}}}}return false;
}function _error($code,$tipas,$line,$m='')
{if (is_callable('moon::error')) {
moon :: error(array("@xml.$code",$m), $tipas);
} else echo 'xml['.$code.'] '.serialize($m);
}}class moon_xml_write{
var $eol="\n";
var $level=0;
var $body;
var $failas;
var $encoding;
function moon_xml_write(){
$this->body='';
$this->failas='';
$this->encoding='';
}function encoding($encoding='')
{$this->encoding=$encoding;
}function open_xml()
{$this->body='<?xml version="1.0"';
if (strlen($this->encoding)) $this->body.=' encoding="'.$this->encoding.'"';
$this->body.='?>'.$this->eol;
}function &close_xml()
{return $this->body;
}function start_node($name,$atrib='')
{$att='';
if (is_array($atrib)) foreach($atrib as $k=>$v) $att.=" $k=\"".htmlspecialchars($v)."\"";
$this->body.=str_repeat("  ",$this->level)."<".$name.$att.">".$this->eol;
$this->level++;
}function end_node($name)
{if($this->level) $this->level--;
$this->body.=str_repeat("  ",$this->level)."</".$name.">".$this->eol;
}function node($name,$atrib='',$txt)
{$att='';
if (is_array($atrib)) foreach($atrib as $k=>$v) $att.=" $k=\"".htmlspecialchars($v)."\"";
$this->body.=str_repeat("  ",$this->level)."<".$name.$att;
if (strlen($txt)) $this->body.=">".htmlspecialchars($txt)."</$name>";
else $this->body.=" />";
$this->body.=$this->eol;
}function node_nl($name,$atrib='',$txt)
{if (strlen($txt)){
$this->start_node($name,$atrib);
$this->body.=str_repeat("  ",$this->level).htmlspecialchars($txt).$this->eol;
$this->end_node($name);
}else $this->node($name,$atrib,'');
}function text($txt)
{$this->body.=str_repeat("  ",$this->level).htmlspecialchars($txt).$this->eol;
}function comment($txt)
{$this->body.=str_repeat("  ",$this->level)."<!-- ".htmlspecialchars($txt)." -->".$this->eol;
}}class moon_paginate{
var $itemsInPage;
var $countItems;
var $countPages;
var $curPage;
var $url,$urlFirst;
var $ordering;
var $tpl,$skinID;
function moon_paginate()
{$this->itemsInPage=0;
$this->countItems=0;
$this->curPage=0;
$this->url=$this->urlFirst='';
$this->countPages=0;
$this->skinID='default';
$this->ordering=new moon_ordering;
}function set_curent_all_limit($curPage,$countItems,$itemsInPage=0)
{$this->countItems=abs(intval($countItems));
$itemsInPage=abs(intval($itemsInPage));
$this->itemsInPage=($itemsInPage) ? $itemsInPage:50;
$this->countPages=ceil($this->countItems/$this->itemsInPage);
$curPage=abs(intval($curPage));
if ($curPage > $this->countPages) $curPage=$this->countPages;
$this->curPage=($curPage) ?  $curPage:1;
}function set_url($url,$urlFirst=false)
{$this->url=str_replace('%7Bpg%7D','{pg}',$url);
$this->urlFirst= $urlFirst===false ? $this->url : str_replace('%7Bpg%7D','{pg}',$urlFirst);
}function get_info()
{$from=1+($this->itemsInPage*($this->curPage-1));
if ($from>$this->countItems) $from=$this->countItems;
$to=$from+$this->itemsInPage-1;
if ($to>$this->countItems) $to=$this->countItems;
$m=array();
$m['sqlLimit']=$m['sqllimit']=' LIMIT '.(($from>1)? ($from-1).',':'').$this->itemsInPage;
$m['from']=$from;
$m['to']=$to;
$m['curPage']=$m['page']=$this->curPage;
$m['countPages']=$m['allpages']=$this->countPages;
return $m;
}function &ordering()
{return $this->ordering;
}function sql_limit() {
$a = $this->get_info();
return $a['sqlLimit'];
}function paginate($po)
{if ($this->countItems<1) return '';
$inf=$this->get_info();
$halfPo=ceil($po/2)-1;
$start=max($inf['curPage']-$halfPo,1);
$end=min($start+$po-1, $inf['countPages']);
$start=max($end-$po+1,1);
$t=$this->_get_template($this->url);
$t1=$this->_get_template($this->urlFirst);
$res='';
for ($i=$start;$i<=$end;$i++){
$a = $i==1 ? $t1['active'] : $t['active'];
$ct=($i==$inf['curPage']) ? $t['inactive']:$a;
$res.=str_replace( array('{pgv}','{pg}'), $i,$ct);
}if ($end<$this->countPages){
if ($this->countPages==$end+1)
$res.=str_replace(array('{pgv}','{pg}'),$end+1,$t['active']);
else {
$res.=str_replace(array('{pgv}','{pg}'),array('...',$end+1),$t['active']);
$res.=str_replace( array('{pgv}','{pg}'), $this->countPages,$t['active']);
}}if ($start>1){
$r1=str_replace( array('{pgv}','{pg}'), 1,$t1['active']);
if ($start>2) $r1.=str_replace( array('{pgv}','{pg}'), array('...',$start-1),$t['active']);
$res=$r1.$res;
}$res=$this->_add_navigation($res,$inf['curPage'],$inf['curPage']);
return $res;
}function _get_template($url)
{$tpl=&$this->tpl;
$t=$tpl->explode_ini($this->skinID);
$t['active']=str_replace('{url}',$url,$t['active']);
$t['inactive']=str_replace('{url}',$url,$t['inactive']);
return $t;
}function _add_navigation($txt,$left,$right)
{if ($left>1 || $right<$this->countPages) {
$t=$this->_get_template($this->url);
$t1=$this->_get_template($this->urlFirst);
}if ($left>1){
$a= $left==2 ? $t1['active']:$t['active'];
$r1=str_replace('{pg}',$left-1,$a);
$txt=str_replace('{pgv}',$t['left'],$r1).$txt;
}if ($right<$this->countPages){
$r1=str_replace('{pg}',$right+1,$t['active']);
$txt.=str_replace('{pgv}',$t['right'],$r1);
}return $txt;
}}class moon_ordering{
var $fields;
var $default=1;
var $sqlorder='';
function shared_ordering()
{$this->default=1;
$this->sqlorder='';
$this->fields=array();
}function set_values($mas,$default=1){
$this->fields=$mas;
$i=0;
foreach ($this->fields as $k=>$v)
if (++$i==$default) {
$this->default=($v ? 10:0)+$default;
break;
}}function get_links($link,&$current){
$sort=empty($current) ? $this->default : $current;
$current=0+$sort;
$i=1;
$on= $current % 10;
$m=array();
foreach ($this->fields as $k=>$v) {
$sn= (($v && $i!=$on) || ($i==$on && $sort<10) ? 10:0) + $i;
$m['orderby'.$i]=str_replace(array('{pg}','%7Bpg%7D'),$sn,$link);
if ($i==$on) {
$m['orderby'.$i].='" class="order-'.($sort<10 ? 'desc':'asc');
$this->sqlorder=$k.($sort<10 ? ' Desc':'');
}$i++;
}return $m;
}function sql_order(){
return $this->sqlorder;
}}class mysql {
protected $ready = false;
protected $dblink;
protected $connectInfo = NULL;
protected $error = FALSE;
protected $throwExceptions = FALSE;
function __destruct() {
$this->close();
}function moon_connect($vars) {
$this->connectInfo = $vars;
}protected function handshake() {
$this->ready = TRUE;
$vars = $this->connectInfo;
$check = array('server', 'user', 'password', 'database');
foreach ($check as $v) {
if (!isset ($vars[$v])) {
$this->dblink = FALSE;
return;
}}$this->dblink = $this->connect($vars['server'], $vars['user'], $vars['password'], $vars['database']);
if (!$this->dblink) {
$this->_error(1, 'F', array('server' => $vars['server'], 'error' => mysqli_connect_error()));
$pg503 = isset ($vars['page503']) ? $vars['page503'] : '';
if ($pg503 == 0 || strtolower($pg503) == 'false') {
return;
}header('HTTP/1.1 503 Service Temporarily Unavailable', true, 503);
header('Status: 503 Service Temporarily Unavailable');
if (function_exists('moon_close')) {
moon_close();
}if ($pg503 && file_exists($pg503)) {
require ($pg503);
}exit;
}if (!empty ($vars['charset'])) {
mysqli_set_charset($this->dblink, trim($vars['charset']));
}if (!empty ($vars['query'])) {
$this->query($vars['query']);
}}function connect($server, $user, $password, $dbname) {
if (strpos($server,':')) {
list($server, $port) = explode(':', $server, 2);
}else {
$port = ini_get('mysqli.default_port');
}$r = @ mysqli_connect($server, $user, $password, $dbname, $port);
if (mysqli_connect_errno()) {
$this->_error(1, 'F', array('server' => $server, 'error' => mysqli_connect_error()));
$r = FALSE;
}return $r;
}function select_db($dbname) {
$this->ready || $this->handshake();
if ($this->dblink) {
if (mysqli_select_db($this->dblink,$dbname)) {
return TRUE;
}$this->_error(2, 'F', array('database' => $dbname, 'error' => mysqli_error($this->dblink)));
}return FALSE;
}function query($sql, $unbuffered = FALSE) {
$this->ready || $this->handshake();
$r = $this->error = FALSE;
if ($this->dblink) {
$r = $unbuffered ? mysqli_query($this->dblink, $sql, MYSQLI_USE_RESULT) : mysqli_query($this->dblink, $sql );
if (!$r) {
$this->error = mysqli_errno($this->dblink) . ': ' . mysqli_error($this->dblink);
if ($this->throwExceptions) {
throw new Exception('Bad query: ' . $sql . ' Error: ' . $this->error);
}else {
$this->_error(3, 'F', array('sql' => $sql, 'error' =>$this->error));
}}}else {
$this->error = 'Connection does not exist';
if ($this->throwExceptions) {
throw new Exception($this->error);
}}return $r;
}function escape($s, $escapeSpec = FALSE) {
$this->ready || $this->handshake();
if ($this->dblink) {
$s = mysqli_real_escape_string($this->dblink, $s);
}else {
$s = addslashes($s);
}if ($escapeSpec) {
$s = addcslashes($s, '%_');
}return $s;
}function insert($m, $table, $returnID = FALSE) {
if (count($m)) {
foreach ($m as $k => $v) {
$m[$k] = is_null($v) ? 'NULL' : (is_array($v) ? $v[0] : "'" . $this->escape($v) . "'");
}$sql = "INSERT INTO `$table` (`" . implode("`, `", array_keys($m)) . "`) VALUES (" . implode(',', array_values($m)) . ')';
$r = $this->query($sql);
return ($returnID && $r ? $this->insert_id() : FALSE);
}else {
$this->error = 'Invalid query.';
}return FALSE;
}function update($m, $table, $id = FALSE) {
if (!count($m)) {
$this->error = 'Invalid query.';
return FALSE;
}$set = array();
foreach ($m as $k => $v) {
$set[] = "`$k`=" . (is_null($v) ? 'NULL' : (is_array($v) ? $v[0] : "'" . $this->escape($v) . "'"));
}$where = '';
if (is_array($id)) {
foreach ($id as $k => $v) {
$where .= $where === '' ? ' WHERE ' : ' AND ';
$where .= "(`$k`='" . $this->escape($v) . "')";
}}elseif (is_numeric($id)) {
$where = " WHERE `id`='" . $this->escape($id) . "'";
}elseif (is_string($id)) {
$where = ' WHERE ' . $id;
}$this->query("UPDATE `$table` SET " . implode(',', $set) . $where);
}function replace($m, $table) {
if (count($m)) {
foreach ($m as $k => $v) {
$m[$k] = is_null($v) ? 'NULL' : ("'" . $this->escape($v) . "'");
}$sql = "REPLACE INTO `$table` (`" . implode('`, `', array_keys($m)) . '`) VALUES (' . implode(',', array_values($m)) . ')';
$r = $this->query($sql);
return ($r ? $this->affected_rows() : 0);
}else {
$this->error = 'Invalid query.';
}return 0;
}function insert_query($mas, $table, $returnID = FALSE) {
return $this->insert($mas, $table, $returnID);
}function update_query($mas, $table, $id = FALSE) {
return $this->update($mas, $table, $id);
}function replace_query($mas, $table) {
return $this->replace($mas, $table);
}function array_query($sql, $indexField = FALSE) {
$array = array();
if (is_object($sql) && 'mysqli_result' === get_class($sql)) {
$r = & $sql;
}else {
$r = $this->query($sql, TRUE);
}if (is_object($r)) {
$first = TRUE;
while ($row = mysqli_fetch_row($r)) {
if ($first) {
if ($indexField !== FALSE) {
if (($indexField === TRUE && !isset ($row[1])) || ($indexField !== TRUE && !isset ($row[$indexField]))) {
$indexField = FALSE;
}}$first = FALSE;
}if ($indexField === FALSE) {
$array [] = $row;
}elseif ($indexField === TRUE) {
$array [$row[0]] = $row[1];
}else {
$array [$row[$indexField]] = $row;
}}mysqli_free_result($r);
}return $array;
}function array_query_assoc($sql, $indexField = FALSE) {
$array = array();
if (is_object($sql) && 'mysqli_result' === get_class($sql)) {
$r = & $sql;
}else {
$r = $this->query($sql, TRUE);
}if (is_object($r)) {
$first = TRUE;
while ($row = mysqli_fetch_assoc($r)) {
if ($first) {
if (!isset ($row[$indexField])) {
$indexField = FALSE;
}$first = FALSE;
}if ($indexField === FALSE) {
$array [] = $row;
}else {
$array [$row[$indexField]] = $row;
}}mysqli_free_result($r);
}return $array;
}function single_query($sql) {
$array = $this->array_query($sql);
if ($kiek = count($array)) {
$array = $array [0];
if ($kiek > 1) {
$this->_error(4, 'N', array('sql' => $sql));
}}return $array;
}function single_query_assoc($sql) {
$array = $this->array_query_assoc($sql);
if ($kiek = count($array)) {
$array = $array [0];
if ($kiek > 1) {
$this->_error(4, 'N', array('sql' => $sql));
}}return $array;
}function free_result($result) {
mysqli_free_result($result);
}function fetch_row_assoc($result) {
return ($result ? mysqli_fetch_assoc($result) : FALSE);
}function num_rows($result) {
return ($result ? mysqli_num_rows($result) : 0);
}function affected_rows() {
return ($this->dblink ? mysqli_affected_rows($this->dblink) : 0);
}function insert_id() {
return ($this->dblink ? mysqli_insert_id($this->dblink) : '');
}function ping() {
if ($this->dblink && !@mysqli_ping($this->dblink)) {
$this->handshake();
}}function close() {
if ($this->dblink) {
mysqli_close($this->dblink);
}$this->dblink = FALSE;
}function connection($info = FALSE) {
if ($info === FALSE) {
$this->ready || $this->handshake();
return $this->dblink;
}else {
return (isset($this->connectInfo[$info]) ? $this->connectInfo[$info] : FALSE);
}}function error() {
return $this->error;
}function exceptions($on) {
$this->throwExceptions = $on;
}protected function _error($code, $tipas, $m = '') {
$m['where'] = $this->_whereError();
if (is_callable('moon::error')) {
moon :: error(array("@mysql.$code",$m), $tipas);
} else {
echo 'mysql['.$code.'] '.serialize($m);
}}protected function _whereError()
{$a = version_compare(PHP_VERSION, '5.3.6', '>=') ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) : debug_backtrace();
$failas = $line = '';
foreach ($a as $v) {
$c = empty($v['class']) ? '' : $v['class'];
if ($c == __CLASS__ || $c == get_class($this) ) {
$failas = isset($v['file']) ? $v['file'] : '?';
$line =  isset($v['line']) ? $v['line'] : '?';
continue;
}break;
}return $failas . ':' .  $line;
}}?>