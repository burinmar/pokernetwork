<?php
//if(_SITE_ID_ == 'kr') ini_set('memory_limit','256M');

class shared_text{
var $tpl;//sablonu klase
var $smiles,$tag2htm;
var $parseError;

var $now;
var $objIds;
var $ourDomains = array(
	'pokernews.com',
	'pokerworks.com',
	'chipmeup.com',
	'online-poker.ru',
	'neverwinpoker.com',
	'allpokersites.com',
	'fulltiltrakeback.com',
	'online-pokernews.com',
	'pokernetwork.com',
	'pokerworks.com',
	'spywarevoid.com',
	'overcards.de',
	'fulltiltpokerbonuscodes.net',
	'myhands.com',
	'bingonews.info',
	'tonygpoker.com',
	'healthnews.com',
	'casinogrinder.com'
);

function shared_text($path)
{
	$moon=&moon::engine();
	$this->tpl=&$moon->load_template(
		$path.'shared_text.htm',
		$moon->ini('dir.multilang').'shared.txt'
		);
	$this->tag2htm= $this->tpl->explode_ini('tags');

	//pakraunam smiles
	$this->smilesK=array();
	$this->smilesV=array();
	$tags=$this->tpl->explode_ini('smiles');
	//funkcija padaranti trim kiekvienam masyvo elementui
	$trimArray=create_function('&$v,$k', '$v = trim($v);');
	//pasiruosiam smiles masyva
	foreach ($tags as $k=>$v) {
		$to=explode(',',$v);
		array_walk($to,$trimArray);
		$this->smilesK[] = '~' . preg_quote(trim($k)) . '~';
		$this->smilesV[] = str_replace(array('{0}','{1}'),$to,$this->tag2htm['smile']);;
	}
	//fico masyvas
    //kokias ico turim tipui file
	$info = $this->tpl->parse_array('fico');
	$tmp = explode(',', $info['fico_show']);
	$this->ficos = array('default' => $info['fico_default']);
	foreach ($tmp as $v) {
		$v = explode('|',$v);
		$ico = trim($v[0]);
		foreach ($v as $vv) $this->ficos[trim($vv)] = $ico;
	}


	//funkcijai ago
	$ago=$this->tpl->parse_array('ago');
	$tmLoc=array();
	$tmLoc['longs'] = isset($ago['longs']) ? explode(',',$ago['longs']) :  array(' minute', ' minutes', ' hour', ' hours', ' day', ' days');
	$tmLoc['shorts'] = isset($ago['shorts']) ? explode(',',$ago['shorts']) :  array(' min.', ' min.', ' hour', ' hours', ' day', ' days ');
	foreach ($tmLoc['longs'] as $k=>$v) $tmLoc['longs'][$k]=' '.$v;
	foreach ($tmLoc['shorts'] as $k=>$v) $tmLoc['shorts'][$k]=' '.$v;
	$locale = &moon :: locale();
	$lang = $locale->language();
	$tmLoc['ago'] = isset($ago['ago']) ? rtrim($ago['ago']) : 'ago';
	if (in_array($lang,array('bg', 'lt', 'de', 'cs', 'si', 'sr'))) {
		$tmLoc['_ago'] = $tmLoc['ago'] . ' ';
		$tmLoc['ago'] = '';
	}
	else {
		$tmLoc['_ago'] = '';
		$tmLoc['ago'] = ' ' . $tmLoc['ago'];
	}

	$tmLoc['and'] = isset($ago['and']) ? ' '.$ago['and'] . ' ' : ' ';
	$tmLoc['now'] = isset($ago['now']) ? trim($ago['now']) : 'Now';
	$this->ago=$tmLoc;
	$this->now=time();

	$this->init();
}

function init()
{
	$this->objects('');
	$this->agoMaxMin=10080;//kiek laiko max turi rodyti funkcija ago, default 7d.
	$this->features = array(
		'noBadWords'=>false, // Ijungti keiksmazodziu filtra
		'liveUrl'=>false, // Ijungti automatini urlu atpazinima
		'allowScript'=>false,
		'nofollow'=>FALSE,//ar ant linku uzdeti noffollow

        //kokius tagus bandyti atpazinti
		'tags' => 'b|i|u|h|quote|list|url|code|sub|sup|strike|table|timer|img|video|game|twitter|hand',
		'replace'=>array(), // 'h'=>'h2'
		'smiles'=>false, //Ijungti smiles konvertavima
		'cards'=>true, //Ijungti kortu simboliu atpazinima
        //galimi intarpai su {id:}
		'image'=>false, //Leisti imges prisegima
		'video'=>false, //Leisti video prisegima
		'html'=>false, //Leisti html prisegima
		'file'=>false, //Leisti failo prisegima
	);
}
//***** PAGRINDINES FUNKCIJOS *****
function ago($s, $short = false, $show_ago = true, $show_hours = true)
{
	$s=$this->now-$s;
	$tmLoc=$this->ago;
	$longs = $tmLoc['longs'];
	$shorts = $tmLoc['shorts'];
	$ago=$tmLoc['ago'];
	$_ago=$tmLoc['_ago'];
	$and=$tmLoc['and'];
	$now=$tmLoc['now'];

	if ($short) $arstr = $shorts; else $arstr = $longs;
	$max = $this->agoMaxMin;
	$s=abs($s);
	$m = floor($s / 60);
	if ($m > $max) return '';
	else {
		$h = 0;
		$d = 0;
		if ($m < 1) return $now;
		else if ($m >= 60) {
			$h = floor($m / 60);
			if ($h >= 24) $d = floor($h / 24);
			$h = $h - $d * 24;
			$m = $m - $d * 1440 - $h * 60;
		}
		$res = '';
		if ($d > 0) {
			if ($d == 1) $res = $d . $arstr['4'];
			else $res = $d . $arstr['5'];
		}
		if ($h > 0 && ($show_hours || $d == 0)) {
			if ($res != '') $res .= $and;
			if ($h == 1) $res .= $h . $arstr['2'];
			else $res .= $h . $arstr['3'];
		}
		if ($m > 0 && $d == 0) {
			if ($res != '') $res .= $and;
			if ($m == 1) $res .= $m . $arstr['0'];
			else $res .= $m . $arstr['1'];
		}
		if ($res != '' && $show_ago) {
            $res = $_ago . $res . $ago;
		}
	}
	return $res;
}

const dateRangeStd = 'std';
const dataRangeShorter = 'short';
function dateRange($from, $to, $variant = self::dateRangeStd)
{
	list($locale, $fmtMonthDay, $fmtYearDate) = $this->dateRangeEnv($variant);
	$fromDate = $locale->gmdatef($from, $fmtMonthDay);
	$fromYear = $locale->gmdatef($from, $fmtYearDate);
	$toDate   = $locale->gmdatef($to, $fmtMonthDay);
	$toYear   = $locale->gmdatef($to, $fmtYearDate);

	if ($fromYear != $toYear)
		return sprintf('%s - %s',
			str_replace('{date}', $fromDate, $fromYear),
			str_replace('{date}', $toDate,   $toYear)
		);
	if (gmdate('m', $from) != gmdate('m', $to))
		return str_replace('{date}',
			sprintf('%s - %s', $fromDate, $toDate),
			$fromYear);
	if ($fromDate != $toDate && $toDate = $locale->gmdatef($to, '%D'))
		return str_replace('{date}',
			sprintf('%s - %s', $fromDate, $toDate),
			$fromYear);
	return str_replace('{date}', $fromDate, $fromYear);
}
private $dateRangeEnvs = array();
private function dateRangeEnv($variant) {
	if (!isset($this->dateRangeEnvs[$variant])) {
		$zone = 'west'; // could depend on _SITE_ID_ or locale.lang()
		$conf = $this->tpl->parse_array('dateRange');
		$return = array(
			'locale' => moon::locale()
		);
		foreach (array('fmtMonthDay', 'fmtYearDate') as $var) {
			$return[$var] = '';
			foreach (array(
				sprintf('%s.%s.%s', $var, $zone, $variant),
				sprintf('%s.%s.%s', $var, $zone, self::dateRangeStd),
				// sprintf('%s.%s.%s', $var, 'west', $variant), // while one zone
				// sprintf('%s.%s.%s', $var, 'west', self::dateRangeStd), // while one zone
			) as $candidate)
				if (isset($conf[$candidate])) {
					$return[$var] = $conf[$candidate];
					break;
				}
		}
		$this->dateRangeEnvs[$variant] = array_values($return);
	}
	return $this->dateRangeEnvs[$variant];
}

//Tekste aptinka linkus ir juos padaro "gyvus"
function make_urls($txt,$wordsize=50)
{
	//uzkabinam http, kur nera
	//$txt = preg_replace("/([^\w@\/])(www\.[a-z0-9\-]+\.[a-z0-9\-]+)/i",	"$1http://$2", 	$txt);
	//istraukiam visus urlus
	preg_match_all("/([\w]+:\/\/[\w-?&;%:#~=\.\/\@]+[\w\/])/i",$txt,$d);
	$m=array_unique($d[0]);
	rsort($m,SORT_STRING);
	foreach ($m as $k=>$url) {
		$txt=str_replace($url,'<<url'.$k.'>>',$txt);
		$tn = $this->_follow($url) ? 'url_our' : 'url';
        $st=str_replace('{url}',$url,$this->tag2htm[$tn]);
		$m[$k]=str_replace('{txt}',$this->short_words($url,$wordsize,true),$st);
	}
	//istatom visus urlus
	foreach ($m as $k=>$v) $txt=str_replace('<<url'.$k.'>>',$v,$txt);
	return $txt;
}

function _follow($url) {
	if (strpos($url, '/ext/') !== FALSE) {
		//PN-2833
		return FALSE;
	}
	elseif ($this->features['nofollow'] && $url!=='' && $url[0]!=='/') {
		$our = $this->ourDomains;
		foreach ($our as $v) {
			if (($is = strpos($url, $v)) && $is<19) {
				return TRUE;
			}
		}
		//jeigu iki cia atejom, vadinasi reikia nofollow
		return FALSE;
	}
	return TRUE;
}

function excerpt($txt,$size=200)
{
    if (strlen($txt)>$size) {
		$txt = substr($txt,0,$size);
		if (($pos = strrpos($txt,' ')) && $pos>floor($size/2) ) $txt = trim(substr($txt,0,$pos));
		$txt = rtrim($txt,' ;.,-!?').'...';
	}
	return $txt;
}


//"Sulauzo" ilgus zodzius
function short_words($txt,$size=30,$url=false)
{
	if (!preg_match_all("/[^\s\xE0-\xFF]{".$size.",}/s",$txt,$d)) return $txt;
	$m=array_unique($d[0]);
	arsort($m,SORT_STRING);
	foreach ($m as $k=>$v) {
		if (!$url && preg_match("~(http|https)://[\w\-\?&;%:#\~=\./\@]+[\w/]~iu",$v)) continue;
		$to='';
		while (strlen($v)) {
			if (($pos = strpos($v, '}{')) || ($pos = strpos($v, '|'))) {
				$pos = min($size,$pos + 1);
				$to.=substr($v,0,$pos).' ';
				$v=substr($v,$pos);
			}
			else {
        		$to.=substr($v,0,$size).' ';//($url ? ' ':'&shy;');
				$v=substr($v,$size);
			}
		}
		$txt=str_replace($m[$k],$to,$txt);
	}
	return $txt;
}

function nesikeik($txt){
	$mas=array('fuck');
	foreach ($mas as $v) {
		if (strpos($txt,$v)!==false)
			$txt=preg_replace('/\b'.$v.'/si',str_repeat('*',strlen($v)),$txt);
	}
	return $txt;
}

function smiles($txt)
{
	return preg_replace($this->smilesK, $this->smilesV, $txt, 9);
}

function cards($s)
{
	//kodas, kad lauztiniuose skliaustuose atpazintu  [ah], [8d 9c] arba [9h, 8d] (arba riestiniuose (ah))
	preg_match_all('/(?:\[|\()((10|[02-9AKQJtx]{1})(s|c|h|d|x)([,|\s]*))+(?:\]|\))/i', $s, $m);
    foreach($m[0] as $k => $v) {
        //echo $v." ->";
        $t = preg_replace('/(10|[02-9AKQJtx]{1})(s|c|h|d|x)/i', '{$1$2}', $v);
        $t = str_replace(array("[", "]", ",", " ", "(", ")"), "", $t);
        $s = str_replace($v, $t, $s);
    }
    preg_match_all('/\{(10|[02-9AKQJtx]{1})(s|c|h|d|x)\}/i',$s,$m);
	$altn=array('s'=>'Spades','c'=>'Clubs','h'=>'Hearts','d'=>'Diamonds','x'=>'');
	foreach ($m[0] as $k=>$v) {
		$rusis=strtolower($m[2][$k]);
		if (!isset($altn[$rusis])) continue;
		if (strtoupper($m[1][$k])=='T' || $m[1][$k]=='0') $m[1][$k]='10';
		$alt= '{'.$m[1][$k].'-'.$altn[$rusis].'}' ;
		$s=str_replace($v,'<img src="/img/cards/'.strtolower($m[1][$k].$rusis).'.gif" border="0" alt="'.htmlspecialchars($alt).'" style="margin-bottom:-3px;" width="25" height="15" />',$s);
	}
	return str_replace('/img/cards/ad.gif','/img/cards/da.gif',$s);
}


function available_smiles() {
	$m=array();
	foreach ($this->smiles as $img=>$find)
		foreach ($find as $k=>$v) {	$m[$v]=$img;break;	}
	return $m;
}

//**********************************
//**********************************
function message($txt)
{
    //naudojam article verciau
	return $this->article($txt);
}

function article($txt) {
	if (strpos($txt, "\n---PageBreak---")) {
		$s = '';
		$d = explode("\n---PageBreak---", $txt);
		$err = '';
		foreach ($d as $k=>$v) {
			if ('' != ($v = trim($v))) {
				$title = $this->excerpt($this->strip_tags($v), 90);
				$title = str_replace(array("\r", "\n", '-->'), array('', '', '--&#124;'), $title);
				$s .= "\n\n<!--PageBreak:" . $title . "-->\n\n" . $this->article($v);
				if ($this->parseError !== FALSE) {
					$err .= $this->parseError . ' (page '.($k+1).')';
				}
			}
		}
		if ($err) {
			$this->parseError = $err;
		}
		return $s;
	}

	$txt=str_replace("\r",'',$txt);
	$txt=preg_replace('/(\n){3,}/si',"\n\n",$txt);//pasalinti didelius tarpus
	$txt=$this->_parse($txt);
	$txt=$this->_addP($txt);
	if (!$this->parseError && strpos($txt,'{') !== FALSE && preg_match('/\{(?:id|img)\:([0-9]+)\}/', $txt, $a)) {
		$this->parseError = "The object " . $a[0] . " has not been found.";
	}
	//$this->error();
	return $txt;
}

function break_pages($html) {
	$a = array();
	if (strpos($html, '<!--PageBreak:') !== FALSE) {
		$d = preg_split('/<!--PageBreak:([^>]*)-->/s', $html, NULL, PREG_SPLIT_DELIM_CAPTURE);
		foreach ($d as $k=>$v) {
			if ($k) {
				$n = floor(($k + 1)/2);
				$a[$n][($k + 1) % 2] = $v;
				//echo '<hr>', $k, ': ', htmlspecialchars($v);
			}
		}
	}
	return $a;
}

function preview($txt,$size=200)
{
    $txt=str_replace("\r",'',$txt);
	$txt=preg_replace('/(\n){3,}/si',"\n\n",$txt);//pasalinti didelius tarpus

	$txt=$this->nesikeik($txt);
	$txt=htmlspecialchars($this->strip_tags($txt));
	$txt=$this->short_words($txt);
	$txt=$this->excerpt($txt, $size);
	$txt=$this->_nl2br($txt);
	$txt=$this->_addP($txt);
	return $txt;
}

function objects($path,$objArray='')
{
	if(is_array($path)) {
    	$this->dirObj=$path[0];
    	$this->srcDirObj=$path[1];
	}
	else {
		$this->dirObj=$path;
		$this->srcDirObj = $path;
	}
	$f=&moon::file();
	$this->objArray=array();
	if (is_array($objArray))
		foreach ($objArray as $id=>$v) {
			if (!empty($v['id'])) {
				//seniem
				$id = $v['id'];
			}
			switch ($v['content_type']) {
			case 3:
            case 'file':
				$tipas='file';
				$a=array();
                $b=$f->info_unpack($v['file']);
				$a = array(
					'{fico}' => isset($this->ficos[$b['ext']]) ? $this->ficos[$b['ext']] : $this->ficos['default'],
					'{fsize}' => $f->format_size($b['size']),
					'{fname}' => $b['name_original'],
					'{comment}' => htmlspecialchars($v['comment']),
					'{url}' => $this->srcDirObj . $b['name_saved']
					);
				$s='</p>'.str_replace(array_keys($a), array_values($a), $this->tag2htm['file']).'<p>';
				//$s=$v['comment'];
				break;
			case 2:
			case 'html':
				$tag = 'html';
            case 1:
			case 'video':
				if (empty($tag)) {
					$tag = 'video';
				}
				$tipas='html';
				if (!$this->features['allowScript']) {
					$s=preg_replace('/script|on(blur|c(hange|lick)|dblclick|focus|keypress|(key|mouse)(down|up)|(un)?load|mouse(move|o(ut|ver))|reset|s(elect|ubmit))/is','[\0]',$v['comment']);
					if ($this->features['nofollow']) {
						$s=preg_replace('/ href=/is',' rel="nofollow" href=',$s);
					}
				}
				else {
					$s=$v['comment'];
				}
				$s='</p>'.str_replace('{html}', $s, $this->tag2htm[$tag]).'<p>';
                $tag = '';
				//$s=$v['comment'];
				break;
            case 0:
			case 'img':
				$tipas='img';
				if (isset($v['wh'])) {
					//nauji
					$comment = isset($v['comment']) ? $v['comment'] : '';
					$align = isset($v['options']) ? $v['options'] : '';
					$s=$this->construct_img($v['file'],$v['wh'],$comment, $align);
					$this->objArray['{img:url-'.$id.'}'] = $this->construct_img($v['file'],$v['wh'],$comment, $align, TRUE);
				}
				else {
					$s=$this->construct_img_old($v['file'],$v['thumbnail'],$v['comment'],$v['options']);
					$this->objArray['{img:url-'.$id.'}'] = $this->construct_img_old($v['file'],$v['thumbnail'],$v['comment'],$v['options'], TRUE);
				}

				break;
			#case 'img' : $tipas='html'; $s=$v['comment'];break;
			default: continue 2;
			}
            $obj_code = '{'.$tipas.':'.$id.'}';
        	$this->objArray[$obj_code]=$this->objArray['{id:'.$id.'}']=$s;
            $this->objIds[$obj_code] = $id;
		}
}

function construct_img($file,$size,$comment,$options,$forURL = FALSE)
{
	switch ($options) {
	case 'center': $divClass='img-center';break;
	case 'left': $divClass='img-left';break;
	case 'right': $divClass='img-right';break;
	default: $divClass='img';
	}
	list($x,$y)=explode('x',$size);
	$storage = moon::shared('storage')->location($this->dirObj);
	$src =  $storage->url($file,'1');
    $s='<img src="' . $src . '"  alt="'.htmlspecialchars($comment).'" class="content-img" />';
	if ($forURL) {
		$s='<a href="{url.img.object}" onclick="window.open(this.href);return false;" class="ignore">'.$s.'</a>';
	}
	else {
		//gal orig didesnis yra
		$whT = $this->srcDirObj;
		if (!empty($whT) && count($wh = explode('x', $whT))==2 && ($wh[0]<$x || $wh[1]<$y)) {
			//echo $whT, ' ', $forURL, '<br/>';
			$s='<a href="'.$storage->url($file).'" onclick="window.open(this.href);return false;" class="ignore">'.$s.'</a>';
		}
	}
    $width= $comment==='' ? '' : ' style="width: '.max($x,100).'px"';
	return '</p><div class="' . $divClass . '">'.$s.'<div'.$width.'>'.htmlspecialchars($comment).'</div></div><p>';
}
function construct_img_old($file,$thumb,$comment,$options,$forURL = FALSE)
{
	switch ($options) {
	case 'center': $divClass='img-center';break;
	case 'left': $divClass='img-left';break;
	case 'right': $divClass='img-right';break;
	default: $divClass='img';
	}
    $f=new moon_file;
    if ($thumb && $f->is_info($this->dirObj,$thumb)) $isThumb=true;
	else if ($f->is_info($this->dirObj,$file)) $isThumb=false;
	else return '';
	list($x,$y)=explode('x',$f->file_wh());
    $s='<img src="' . $this->srcDirObj . $f->file_name() . '" width="' . min($x,530) . '" alt="'.htmlspecialchars($comment).'" class="content-img" />';
	if ($forURL) {
		$s='<a href="{url.img.object}" onclick="window.open(this.href);return false;" class="ignore">'.$s.'</a>';
	}
    elseif ($isThumb && $f->is_info($this->dirObj,$file)) {
    	$s='<a href="'.$this->srcDirObj . $f->file_name() . '" onclick="window.open(this.href);return false;" class="ignore">'.$s.'</a>';
	} else {

	}
    $width= $comment==='' ? '' : ' style="width: '.max($x,100).'px"';
	return '</p><div class="' . $divClass . '">'.$s.'<div'.$width.'>'.htmlspecialchars($comment).'</div></div><p>';
}

function _parse($txt)
{
	if (!$this->features['tags']) {
		return $txt;
	}
	$d=preg_split('/\[([\/]{0,1}(?:'.$this->features['tags'].')(?:=[^\]]*)*)\]/is', $txt,1000,PREG_SPLIT_DELIM_CAPTURE);
	$tgInfo=$s=array();
	$level=$isTag=0;
	$isCodeTag=$isHtmlTag=false;
	$closingTag=false;
	$isTag=true;
	$s[0]='';
	$parseError = FALSE;
	foreach ($d as $k=>$v) {
		$isTag=($k % 2 ==1);
		if ($isTag) {
			$closingTag=($v{0}==='/');//ar tai uzsidarantis tagas
			if ($closingTag) $tg=strtolower(ltrim($v,'/'));
			else {
				if (preg_match('/([^=]+)(=.*)*/is', $v,$tgd)) $tg=strtolower($tgd[1]);
				else $isTag=false;
			}
		}
		if ($isTag) $v='['.$v.']';//apgaubiam kabutem
		if ($isTag && $tg=='html') {
			if ($isHtmlTag && $closingTag) {
				$isHtmlTag=false;
				continue;
			}
			else if (!$isHtmlTag && !$closingTag) {
				$isHtmlTag=true;
				continue;
			}
		}
		if ($isTag && !$isHtmlTag) {
			if ($closingTag) {//uzsidarantis tagas
				if ($tg=='code') $isCodeTag=false;
				if ($level>0 && !$isCodeTag) {
					$tgc=$tgInfo[$level][0];
					$toLevel=$level;
					if ($tgc!==$tg) {//isivele siuksle, gal parent pries tai kur yra
						$parseError = "Closing tag '$tg' does not match opening tag '$tgc'";
						if ($at = trim(substr($s[$level-1], -30) .$tgInfo[$level][2]. substr($s[$level], 0, 30))) $parseError .= ' at \'...' . $at . "...'";
						for($i=$level-1;$i>0;$i--) if ($tgInfo[$i][0]===$tg) {$toLevel=$i;break;}
					}
					if ($toLevel<$level) {
						for ($i=$toLevel+1;$i<=$level;$i++) $s[$toLevel].=$tgInfo[$i][2].$s[$i];
						$tgc=$tgInfo[$level=$toLevel][0];
					}
					if ($tgc===$tg) {
						$s[$level-1].=$this->_tag($tgc,$tgInfo[$level][1],$s[$level]);
						$level--;
					} else $s[$level].=$v;
				} else $s[$level].=$v;

			} else {//atsidarantis tagas
				if ($tg=='code') $isCodeTag=true;
				elseif ($tg=='url' && $this->features['liveUrl']) {
					//kad nesigautu dvigubi a
					$this->features['liveUrl'] = FALSE;
				}
				$tgInfo[++$level]=array($tg,((isset($tgd[2])) ? $tgd[2]:''),$v);
				$s[$level]='';
			}
		} else {
			$insideTag = !isset($tgInfo[$level]) ? 0 : $tgInfo[$level][0];
			$s[$level].=($isHtmlTag || $isCodeTag) ? $v:$this->_apdorok($v,$insideTag);
		}

	}
	if ($level>0) {
		$parseError = "The tag '".$tgInfo[1][2]."' has not been closed";
		if ($at = trim(substr($s[0], -30) .$tgInfo[1][2]. substr($s[1], 0, 30))) $parseError .= ' at \'...' . $at . "...'";

	}
	for ($i=1;$i<=$level;$i++) $s[0].=(($i) ? $tgInfo[$i][2]:'').$s[$i];
	$this->parseError = $parseError;
	return $s[0];
}

function _tag($tag,$param,$txt){
	$tags=$this->tag2htm;
	if (is_array($this->features['replace']) && isset($this->features['replace'][$tag]))
		$tag=$this->features['replace'][$tag];

	switch ($tag){
	case 'code': $txt=htmlspecialchars($txt);
		$txt=str_replace("\t","  ",$txt);
		$txt=str_replace("\n","\r\r\r",$txt);
		if (isset($tags[$tag])) $txt=str_replace('{txt}',$txt,$tags[$tag]);
		break;
	case 'img':
		$txt = trim($txt);
		$txt = '<img src="' . htmlspecialchars($txt) . '" class="simple-embed" alt="" />';
		break;
	case 'video':
		// gal pokernews video embed
		$txt = trim($txt);
		if (preg_match("#.*?(:?pokernews|pokernetwork)\.(:?com|dev)/.+([0-9]+)\.htm$#is", $txt)) {
			$txt = str_replace('{url}', htmlspecialchars($txt), $tags['pnvideo']);
			$txt = str_replace('{id}', uniqid('x'), $txt);
			break;
		}
		//cia ne pokernews video, einam toliau

		//$txt = htmlspecialchars($txt);
		$patterns = array();
		$replacements = array();

		$rand = rand();
		$tpls = array();
		$tpls['video_youtube'] ='<iframe title="YouTube video player" width="640" height="390" src="http://www.youtube.com/embed/\1?wmode=transparent" frameborder="0" allowfullscreen></iframe>';
		$tpls['video_vimeo'] ='<iframe src="http://player.vimeo.com/video/\1" width="600" height="338" frameborder="0"></iframe>';
		$tpls['video_pokertube'] = '<object><embed src="http://www.pokertube.com/ext-player/\1" wmode="transparent" allowfullscreen="true" width="444" height="383" base="http://www.pokertube.com/"></embed></object>';
		$tpls['video_pokertube2']= '<object name="streamplayer\1" id="streamplayer\1" type="application/x-shockwave-flash" data="http://pokertube.streamingbolaget.se/players/streamingbolaget/article.swf" width="600" height="363"><param name="movie" value="http://pokertube.streamingbolaget.se/players/streamingbolaget/article.swf" /><param name="FlashVars" value="fqdn=pokertube.streamingbolaget.se&amp;content=\1&amp;autostart=0&amp;volume=1" /><param name="allowFullScreen" value="true" /><param name="wmode" value="transparent" /><param name="quality" value="high" /><param name="scale" value="exactfit" /></object>';
		$tpls['video_brightcove'] = '<object width="486" height="412" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,47,0"><param name="movie" value="\1" /><param name="flashVars" value="\2" /><param name="base" value="http://admin.brightcove.com" /><param name="seamlesstabbing" value="false" /><param name="allowFullScreen" value="true" /><param name="swLiveConnect" value="true" /><embed src="\1" wmode="transparent" flashVars="\2" base="http://admin.brightcove.com" name="flashObj" width="486" height="412" seamlesstabbing="false" type="application/x-shockwave-flash" allowFullScreen="true" swLiveConnect="true" pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash"></embed></object>';
		$tpls['video_brightcove2'] = '<iframe title="BrightCove video player" width="440" height="405" src="http://bcove.me/\1?wmode=transparent" frameborder="0"></iframe>';
		$tpls['video_dailymotion'] = '<object width="480" height="337"><param name="movie" value="http://www.dailymotion.com/swf/video/\1?theme=none" /><param name="allowFullScreen" /><param name="wmode" value="transparent" /><embed type="application/x-shockwave-flash" src="http://www.dailymotion.com/swf/video/\1?theme=none" width="480" height="337" wmode="transparent" allowfullscreen="true"></embed></object>';
		$tpls['video_myhands'] = '<object width="620" height="450"><param name="movie" value="http://www.myhands.com/handplayer/v3/preloader.swf?random=' . $rand . '" /><param name="menu" value="false" /><param name="scale" value="noscale" /><param name="quality" value="high" /><param name="wmode" value="transparent" /><param name="allowFullScreen" value="true" /><param name="flashvars" value="handId=\1&amp;listId=undefined&amp;searchId=undefined&amp;embedMode=0&amp;devMode=0"><embed swLiveConnect="true" type="application/x-shockwave-flash" width="620" height="450" src="http://www.myhands.com/handplayer/v3/preloader.swf?random=' . $rand . '" menu="false" scale="noscale" quality="high" wmode="transparent" allowFullScreen="true" flashvars="handId=\1&amp;listId=undefined&amp;searchId=undefined&amp;embedMode=0&amp;devMode=0" /></object><br /><small>This Hand History Player is supported by <em><a href="http://www.myhands.com" target="_blank">www.myhands.com</a></em></small>';
		$tpls['video_handreplays'] = '<object width="600" height="400"><param name="movie" value="http://www.pokerhandreplays.com/flash/replayer.swf?pokerhandid=\1" /><param name="wmode" value="transparent" /><embed type="application/x-shockwave-flash" width="600" height="400" src="http://www.pokerhandreplays.com/flash/replayer.swf?pokerhandid=\1" wmode="transparent" /></object>';
		$tpls['video_pokerreplay'] = '<object data="http://www.pokerreplay.com/assets/swf/PokerReplayVideoPlayer_External.swf?code=\1&amp;type=external&amp;l=en" type="application/x-shockwave-flash" width="480" height="385"><param name="allowFullScreen" value="true" /><param name="wmode" value="transparent" /><param name="movie" value="http://www.pokerreplay.com/assets/swf/PokerReplayVideoPlayer_External.swf?code=\1&amp;type=external&amp;l=en" /><embed type="application/x-shockwave-flash" src="http://www.pokerreplay.com/assets/swf/PokerReplayVideoPlayer_External.swf?code=\1&amp;type=external&amp;l=en" width="480" height="385" wmode="transparent" allowfullscreen="true"></embed></object>';
		$tpls['video_soundcloud'] = '<iframe width="100%" height="166" scrolling="no" frameborder="no" src="http://w.soundcloud.com/player/?url=http%3A%2F%2Fapi.soundcloud.com%2Ftracks%2F\1&show_artwork=true"></iframe>';

		if (isset($this->features['mobile'])) {
			$tpls['video_youtube'] = str_replace(array('{url}', '{txt}'), 'http://www.youtube.com/watch?v=\1', $tags['url_newwin']);
			$tpls['video_vimeo'] = str_replace(array('{url}', '{txt}'), 'http://vimeo.com/\1', $tags['url_newwin']);
		}
		
		$patterns[] = "#.*?youtube\.com.*?watch\?v=([^&\[]+).*#is"; // link
		$replacements[] = $tpls['video_youtube'];

		$patterns[] = "#.*?youtu\.be/([^&\[]+).*#is"; // link
		$replacements[] = $tpls['video_youtube'];

		$patterns[] = "#.*?youtube\.com/(?:embed|v)/([^&\[\"]+).*#is"; // embed
		$replacements[] = $tpls['video_youtube'];

		$patterns[] = "#.*?vimeo\.com/([0-9]+).*#is"; // link
		$replacements[] = $tpls['video_vimeo'];

		$patterns[] = "#.*?pokertube\.com/ext-player/([0-9a-z]+).*#is"; // embed
		$replacements[] = $tpls['video_pokertube'];
		
		$patterns[] = "#.*?pokertube\.streamingbolaget\.se.*?content=([0-9]+).*#is"; // embed
		$replacements[] = $tpls['video_pokertube2'];

		//$patterns[] = "#.*?bcove.me/([a-z0-9]+).*#is";
		//$replacements[] = $tpls['video_brightcove2'];

		$patterns[] ="#.*?value=&quot;(.+?brightcove\.com.+?)&quot;.*?&quot;(videoId.+?)&quot;.*#is"; // embed
		$replacements[] = $tpls['video_brightcove'];
		
		$patterns[] = "#.*?dailymotion\.com/video/([a-z0-9]+).*#is"; // link
		$replacements[] = $tpls['video_dailymotion'];
		
		$patterns[] = "#.*?myhands\.com/.+(?:handId=|nid=)([0-9]+).*#is"; // link
		$replacements[] = $tpls['video_myhands'];
		
		$patterns[] = "#.*?pokerhandreplays\.com/.+id[=/]([0-9]+).*#is"; //link
		$replacements[] = $tpls['video_handreplays'];
		
		$patterns[] = "#.*?pokerreplay\.com/video/([0-9a-z]+).*#is"; //link
		$replacements[] = $tpls['video_pokerreplay'];
		
		foreach ($patterns as $k => $pattern) {
			if (preg_match($pattern, $txt)) {
				$txt = preg_replace($pattern, $replacements[$k], $txt);
				$txt = '</p><div class="video-object">' . $txt . '</div><p>';
				break;
			}
		}
		break;
	case 'quote':
		$param = trim($param);
		preg_match('~^=?[\'"]?(.+?)[\'"]?$~', $param, $match);
		$txt = trim(str_replace("\r", "\n", $txt));
		if (!empty($match[1])) {
			$txt = str_replace('{txt}',$txt, $tags['quote_named']);
			$txt = str_replace('{src}', htmlspecialchars($match[1]), $txt);
		} else {
			$txt=str_replace('{txt}',$txt, $tags['quote']);
		}
		break;
	case 'timer':
		$param=strtolower(str_replace( array('"',"'","\n"),'', substr($param,1) ));
		$txt = trim($txt);
		list($from) = ($a = explode('|', $param));
		$to = isset($a[1]) ? $a[1] : $from;
		$locale = & moon :: locale();
		$now = $locale->now();
		if (!$from || !($from = strtotime($from))) {
			$from = $now;
		}
        	$to = isset($a[1]) ? $a[1] : ($from + 86400);
		if (!$to || !($to = strtotime($to))) {
			$to = $now;
		}
		$to = isset($a[0]) && !isset($a[1]) ? 1893477600 : $to; // if no 'to' - set it to 2030
		$id = uniqid('');
        	$txt = '<!--timer:' . $id . '['. $from . '|' . $to . ']-->' . $txt . '<!--' . $id . ':timer-->';

		break;
	case 'url+':
    	if (substr($param,-1) != '+') {
			$param .= '+';
		}
	case 'url':
		$newWin = FALSE;
		$param = trim($param);
		if (substr($param,-1) == '+') {
			$newWin = TRUE;
			$param = substr($param,0,-1);
		}
		$url=trim(str_replace( array('"',"'","\n"),'', substr($param,1) ));
		if ($url === '') {$url = $txt;}
		if ($url!=='' && $url[0]!=='/' && !preg_match("/^((ftp|http|https):\/\/)/i",$url)) $url='http://'.$url;
		$url = str_replace('&amp;amp;', '&amp;', str_replace('&', '&amp;', $url));
		if ($this->_follow($url)) {
			$tag = $newWin ? 'url_newwin' : 'url_our';
		}
		else {
			$tag = 'url';
		}
		if (strpos($txt,'{') !== FALSE && preg_match('/\{(?:id|img)\:([0-9]+)\}/', $txt, $a)) {
            if (isset($this->objArray['{img:url-'.$a[1].'}'])) {
				$txt = $this->objArray['{img:url-'.$a[1].'}'];
				$txt = str_replace('{url.img.object}', $url, $txt);
			}
			break;
		}
		$s=(isset($tags[$tag])) ? $tags[$tag]:'{txt}';
		$txt=str_replace('{txt}',$txt,str_replace('{url}',$url,$s));
		break;
    case 'list':
        $param=strtolower(str_replace( array('"',"'","\n"),'', substr($param,1) ));
       	if ($param=='1') $tag='list-1';
		elseif ($param=='a') $tag='list-a';
		$s=(isset($tags[$tag])) ? str_replace('\n',"\r\r\r",$tags[$tag]):'{txt}';
		$txt=str_replace("\n","\r\r",trim($txt));
		$d=explode('[*]',$txt);
		foreach ($d as $k=>$v) {
			if ($k) {
				$v = trim($v);
				if (strpos($v,'</p>')) {
					$v = '<p>' . $v . '</p>';
				}
				$d[$k]= $k ? str_replace('{txt}',$v,$tags['li']) : $v;
			}
		}
		$txt=implode("\r\r\r",$d);
		//$txt=str_replace('[*]','<li>',$txt);
		$txt=str_replace('{txt}',$txt,$s);
		break;
    case 'table':
		$param=strtolower(str_replace( array('"',"'","\n"),'', substr($param,1) ));
		$txt=str_replace("\t","|",$txt);
		$txt=str_replace("\n","\r\r",trim($txt));
		$rows=explode("\r\r", $txt);
		$countCol = 0;
		$s=array('');
		$tbI = 0;
		foreach ($rows as $r) {
			if (($r=trim($r))==='') continue;
			if (substr($r,0,3)==='---' && trim($r,'-') === '') {
				//split table
				$countCol = 0;
				$s[++$tbI] = '';
				continue;
			}
			if (substr($r,0,1)==='*' && substr($r,-1)==='*') {
				$tgR='th';
				$r = substr($r,1,-1);
			} else $tgR = 'td';
			$rm=explode('|',$r);
			if (!$countCol) $countCol = count($rm);
			$s[$tbI] .= '<tr>';
			for ($i=0;$i<$countCol;$i++) {
				$el = isset($rm[$i]) ? trim($rm[$i]) : '';
				if (substr($el,0,1)==='*' && substr($el,-1)==='*') {
					$tg='th';
					$el = trim(substr($el,1,-1));
				} else $tg = $tgR;
				$s[$tbI] .= '<'.$tg.'>' . ($el==='' ? '&nbsp;' : $el) . '</'.$tg.'>';
			}
			$s[$tbI] .= "</tr>\r\r";
		}
		$a = explode(' ', trim($param));
		$width = '';
		$align = '';
		$tbcenter = false;
		foreach ($a as $v) {
			if (is_numeric($v)) {
				$v =intval($v);
				if ($v>30 && $v<101) $width='style="width:'.$v.'%" width="'.$v.'%"';
			}
			else {
				switch ($v) {
					case 'left': $align .= ' fl'; break;
					case 'center': $tbcenter = true; break;
					default:
						if (substr($v,0,6) ==='class:') {
							$align .= ' ' . substr($v, 6);
						}
				}
			}
		}
		if ($tbI) {
			$cols = $tbI+1;
			$colW = floor(100/$cols);
			foreach ($s as $k=>$v) {
				$s[$k] = '<td'.($k==$tbI ? '':' width="'.$colW.'%"').' valign="top">'.($v!=='' ? '<table width="100%" class="usertable">' . $v . '</table>':'').'</td>';
			}
			$txt = '</p><table width="100%" class="grouptable"><tr>' . implode('', $s) . '</tr></table><p>';
		}
		elseif ($s[0] !== '') {
			if ($tbcenter) {
				$txt = '</p><div class="table-center"><table '.$width.' class="usertable">' . $s[0] . '</table></div><p>';
			}
			else {
				$txt = '</p><table '.$width.' class="usertable'.$align.'">' . $s[0] . '</table><p>';
			}

		}
		break;
	case 'hand':
		if (!isset($this->dev)) $this->dev = is_dev();
		$rand = rand();
		$pos = strpos($param, ':') - 1;
		$sId = substr($param, 1, $pos);
		$id = substr($param, $pos + 2);
		$p = &moon::page();
		if (FALSE !== strpos($p->home_url(), 'beta.')) $u = 'http://beta.pokernews.dev.ntsg.lt/img/v.swf?random=' . $rand;
		else {
			if ('com' === $sId) {if ($this->dev) $sId = ''; else $sId = 'www.';} else $sId .= '.';
			$u = 'http://' . $sId . 'pokernews.' . ($this->dev ? 'dev' : 'com') . '/img/v.swf?random=' . $rand;
		}
		if ('*' === $id[0]) {
			$id = substr($id, 1);
			if ('' !== $txt) $txt = '<b>' . $txt . '</b><br/>';
			$txt .= '<object width="620" height="450"><param name="movie" value="' . $u . '"/><param name="menu" value="false"/><param name="scale" value="noscale"/><param name="quality" value="high"/><param name="wmode" value="window"/><param name="allowFullScreen" value="true"/><param name="allowScriptAccess" value="always"/><param name="flashvars" value="listId=' . $id . '"><embed swLiveConnect="true" allowScriptAccess="always" type="application/x-shockwave-flash" width="620" height="450" src="' . $u . '" menu="false" scale="noscale" quality="high" wmode="window" allowFullScreen="true" allowScriptAccess="always" flashvars="listId=' . $id . '"/></object>';
		} else {
			if ('' !== $txt) $txt = '<b>' . $txt . '</b><br/>';
			$txt .= '<object width="620" height="450"><param name="movie" value="' . $u . '"/><param name="menu" value="false"/><param name="scale" value="noscale"/><param name="quality" value="high"/><param name="wmode" value="window"/><param name="allowFullScreen" value="true"/><param name="allowScriptAccess" value="always"/><param name="flashvars" value="handId=' . $id . '"><embed swLiveConnect="true" allowScriptAccess="always" type="application/x-shockwave-flash" width="620" height="450" src="' . $u . '" menu="false" scale="noscale" quality="high" wmode="window" allowFullScreen="true" allowScriptAccess="always" flashvars="handId=' . $id . '"/></object>';
		}
		break;
	case 'twitter':
		$url=trim(str_replace( array('"',"'","\n"),'', substr($param,1) ));
		if ($url!=='' && $url[0]!=='/' && !preg_match("/^((ftp|http|https):\/\/)/i",$url)) $url='http://'.$url;
		$url = str_replace('&amp;amp;', '&amp;', str_replace('&', '&amp;', $url));
		$txt=str_replace("\n","\r\r",trim($txt));
		$d=explode("\r\r", $txt);
		$a = array();
		foreach ($d as $v) {
			if (strpos($v,'=')!==false){
				list($k, $v) = explode('=', $v, 2);
				$a[trim($k)]=trim($v);
			}
	    }
		if (!empty($a['nick']) && !empty($a['text'])) {
			$date = '';
			if (!empty($a['date']) && ($ts=strtotime($a['date']))) {
				$locale = & moon :: locale();
				$date = $locale->datef($ts,'News');
			}
			if (!$this->features['liveUrl']) {
				$a['text'] = $this->make_urls($a['text']);
			}
			//$txt = (empty($a['img']) ? '' : '<img src="' . $a['img'] . '" />') . '<p><span><a href="' . $url . '" onclick="window.open(this.href);return false;">' . $a['nick'] . '</a>'.(empty($a['name']) ? '' : ' '.$a['name']).'</span> <q cite="' . $url . '">' . $a['text'] . '</q>' . ($date ? ' <i>' . $date . '</i>' : '') .'</p>';
			$txt = '<div class="author">'.(empty($a['img']) ? '' : '<img src="' . $a['img'] . '" />').'<p><a href="' . $url . '" onclick="window.open(this.href);return false;"><b>'.(empty($a['name']) ? '' : $a['name']).'</b></a><br /><a href="' . $url . '" onclick="window.open(this.href);return false;">@' . $a['nick'] . '</a></p></div><span class="clr"></span><q cite="' . $url . '" class="tweetMessage">' . $a['text'] . '</q>' . ($date ? '<span class="date">' . $date . '</span>' : ''). (empty($a['id']) ? '' : '<div class="tweetActions"><a href="https://twitter.com/intent/tweet?in_reply_to=' . $a['id'] . '" class="tweetReply">Reply</a><a href="https://twitter.com/intent/retweet?tweet_id=' . $a['id'] . '" class="tweetRetweet">Retweet</a><a href="https://twitter.com/intent/favorite?tweet_id=' . $a['id'] . '" class="tweetFavorite">Favorite</a></div>').'<a href="https://twitter.com/' . $a['nick'] . '" class="twitter-follow-button" data-show-count="false" data-show-screen-name="false">Follow</a>';
			$txt = str_replace('{txt}', $txt, $tags[$tag]);
		}
		break;
	default:
		if (isset($tags[$tag])) $txt=str_replace('{txt}',$txt,$tags[$tag]);
	}
	return $txt;
}

function strip_tags($txt, $nicerUrls = false)
{
	if ($nicerUrls) {
		$txt= trim(preg_replace_callback('~\[url="?([^\]]*?)"?\](.*?)\[/url\]~is',create_function('$m', 'return trim($m["1"])==trim($m["2"]) ? $m["1"] : $m["2"]." (".$m["1"].")" ;'), $txt));
	}
	$txt= trim(preg_replace('~\[[/]?('.$this->features['tags'].'|\*)(=[^\]]*)?\]~is','', $txt));
	$txt= trim(preg_replace('/\{(img|html|id):([0-9]*)\}/is','', $txt));
	return $txt;
}

function _apdorok($txt,$insideTag=false)
{
	if ($txt !== '') {
		if ($this->features['noBadWords']) $txt=$this->nesikeik($txt);
		if ($insideTag !='video') {
			$txt = $this->short_words($txt);
		}
		$txt = htmlspecialchars($txt);

		$txt=str_replace(array('&amp;shy;',' -- '),array('&shy;',' — '),$txt);
		if ($this->features['smiles']) $txt=$this->smiles($txt);
		if ($this->features['cards']) $txt=$this->cards($txt);
		if ($this->features['liveUrl']) $txt=$this->make_urls($txt);
		$txt=$this->_nl2br($txt,$insideTag);
		//foreach ($this->objArray as $k=>$v) $txt=str_replace($k,$v,$txt);
	}
	return $txt;
}

function _nl2br($txt,$insideTag=false)
{
	if ($insideTag && $insideTag != 'timer'  && $insideTag != 'game') {
		$txt=str_replace(array("\n\n","\n"),"\r\r ",$txt);
	}
	return $txt;
}

function _addP($txt)
{
	$tArr = explode("\n", $txt);
	$txt = '';
	$empty = false;
	foreach($tArr as $row){
		$row=trim($row);
		if($empty && $row == '') {
			//praita ir dabartine tuscia - skip
			continue;
		}elseif($empty){
			//praita tuscia, dabartine - ne
			$txt.="</p>\n<p>".$row;
			$empty = false;
		}elseif($row == ''){
			//tik dabartine tuscia
			$empty = true;
		}else{
			//pilna eilute
			$txt.=($txt?"<br/>\n":'').$row;
		}
	}
	//$txt.=print_r($tArr,true);
	foreach ($this->objArray as $k=>$v) $txt=str_replace($k,$v,$txt);
	$txt=str_replace("\r\r\r","\n",$txt);
	$txt=str_replace("\r\r </p>","</p>",$txt);
	$txt=str_replace("\r\r ","<br/>\n",$txt);
	$txt=str_replace('<p></p>','','<p>'.$txt.'</p>');
	$txt=str_replace("<p><br/>\n",'<p>',$txt);
	return $txt;
}

function check_timer($txt) {
	if (strpos($txt, '<!--timer:')) {
		static $now = 0;
		if (!$now) {
			$locale = & moon :: locale();
			$now = $locale->now();
			//$f = 'Y-m-d H:i';
		}
		$reg = '/<!--timer:(.{13})\[([^\]]+)\]-->(.*)<!--\\1:timer-->?/sm';
		preg_match_all($reg, $txt, $m);
		if (count($m[1])){
			foreach ($m[1] as $i => $id) {
				list($from, $to) = explode('|', $m[2][$i]);
				//$x = '<!-- timer:' . date($f,$from) . ' to ' . date($f,$to) . '. Now: ' . date($f,$now) . '. Status ' . $on .' -->';
				if ($from < $now && $now < $to) {
					$txt = str_replace('<!--timer:' . $id .'[' .$m[2][$i].']-->', '', $txt);
					$txt = str_replace('<!--timer:' . $id . '-->', '', $txt);
				}
				else {
					$txt = str_replace($m[0][$i], '', $txt);
				}
			}
	    }
		$txt = str_replace('<p></p>', '', $txt);
	}
	return $txt;
}

function used_objects($txt) {
    $res = array();
    if(is_array($this->objIds)) {
        foreach ($this->objIds as $k => $v) {
            if(strpos($txt, $k) !== false) $res[] = $v;
        }
    }
    return array_unique($res);
}

// URI suformavimas
function make_uri($s, $maxLength = 60) {
	if ($k = strpos($s, '(')) {
		$s = substr($s, 0, $k);
	}
	$doubles = array(
	//DE
	'ß' => 'ss',
	// RU
	'а' => 'a', 'к' => 'k', 'х' => 'kh', 'б' => 'b', 'л' => 'l', 'ц' => 'ts', 'в' => 'v', 'м' => 'm', 'ч' => 'ch', 'г' => 'g', 'н' => 'n', 'ш' => 'sh', 'д' => 'd', 'о' => 'o', 'щ' => 'shch', 'е' => 'e', 'п' => 'p', 'ъ' => "", 'ё' => 'jo', 'р' => 'r', 'ы' => 'y', 'ж' => 'zh', 'с' => 's', 'ь' => "", 'з' => 'z', 'т' => 't', 'э' => 'eh', 'и' => 'i', 'у' => 'u', 'ю' => 'ju', 'й' => 'j', 'ф' => 'f', 'я' => 'ja',
	//SERBIAN
	'ђ' => 'dj', 'љ' => 'lj', 'њ' => 'nj', 'ћ' => 'c', 'џ' => 'dz',
	// LT
	'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
	// EE
	'õ' => 'o', 'ä' => 'a', 'ö' => 'o', 'ü' => 'u',
	// IT
	'à' => 'a', 'é' => 'e', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
	// SE
	'å' => 'a',
	// ES
	'á' => 'a', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
	//NO
	'å' => 'a', 'æ' => 'a', 'ø' => 'o',
	//TR
	'ı' => 'i', 'ğ' => 'g', 'ş' => 's', 'ç' => 'c', 'ć' => 'c');
	$loc = & moon :: locale();
	if ($loc->language() == 'de') {
		$doubles['ä'] = 'ae';
		$doubles['ü'] = 'ue';
		$doubles['ö'] = 'oe';
	}
	$s = mb_strtolower($s, 'UTF-8');
	$s = str_replace(array_keys($doubles), array_values($doubles), $s);
	//$s=str_replace(array(" the "," or "," a "," of "),' ',$s);
	$s = preg_replace("/[^a-z0-9-]/", '-', strtolower($s));
	$s = preg_replace('/-{2,}/', '-', $s);
	$s = preg_replace('/^-*(.*?)-*$/', '\\1', substr($s, 0, $maxLength));
	return $s;
}

function js_html($str) {
    $res = str_replace('/', '\/', $str);
    $res = str_replace("'", "\'", $res);
    return $res;
}

function countries($us = FALSE) {
	if ('us' === $us) {
		//valstijos
		return $this->tpl->parse_array("states");
	}
	else {
		return $this->tpl->parse_array("countries");
	}
}

function languages() {
	return $this->tpl->parse_array("languages");
}

function error($alert=TRUE) {
	$err = $this->parseError === FALSE ? FALSE : 'Possible text parse error: "'.$this->parseError.'"';
	if ($alert && $this->parseError) {
		moon :: page()->alert(htmlspecialchars($err));
	}
	return $err;
}

// diff functions
function _diff($old, $new){
	$maxlen = 0;
	foreach($old as $oindex => $ovalue){
		$nkeys = array_keys($new, $ovalue);
		foreach($nkeys as $nindex){
			$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1])
				? $matrix[$oindex - 1][$nindex - 1] + 1
				: 1;
			if($matrix[$oindex][$nindex] > $maxlen){
				$maxlen = $matrix[$oindex][$nindex];
				$omax = $oindex + 1 - $maxlen;
				$nmax = $nindex + 1 - $maxlen;
			}
		}
	}
	if($maxlen == 0) {
		return array(array('d'=>$old, 'i'=>$new));
	}
	return array_merge(
			$this->_diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
			array_slice($new, $nmax, $maxlen),
			$this->_diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
}

function htmlDiff($old, $new){
	//$old = str_replace(array("\r\n", "\n"), " \n ", htmlspecialchars($old));
	//$new = str_replace(array("\r\n", "\n"), " \n ", htmlspecialchars($new));
	$diff = $this->_diff(explode(' ', $old), explode(' ', $new));
	$ret = '';
	foreach($diff as $k){
		if(is_array($k)) {
			$ret .= (!empty($k['d'])?'<span style="background-color:#FFD8D3; text-decoration: line-through;">'.str_replace("\n","&para;\n",implode(' ',$k['d']))."</span> ":'').
					(!empty($k['i'])?'<span style="background-color:#CCFFCC;">'.str_replace("\n","&para;\n",implode(' ',$k['i']))."</span> ":'');
		}
		else {
			$ret .= $k . ' ';
		}
	}
	$ret = nl2br(str_replace(" \n ", "\n", $ret));
	return $ret;
}

}
?>