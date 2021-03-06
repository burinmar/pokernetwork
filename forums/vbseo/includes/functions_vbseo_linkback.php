<?php

/************************************************************************************
* vBSEO 3.6.0 for vBulletin v3.x & v4.x by Crawlability, Inc.                       *
*                                                                                   *
* Copyright ? 2011, Crawlability, Inc. All rights reserved.                         *
* You may not redistribute this file or its derivatives without written permission. *
*                                                                                   *
* Sales Email: sales@crawlability.com                                               *
*                                                                                   *
*----------------------------vBSEO IS NOT FREE SOFTWARE-----------------------------*
* http://www.crawlability.com/vbseo/license/                                        *
************************************************************************************/

function vbseo_do_trackback($turl, $vbseo_url_, $title, $bbtitle, $snippet)
{
$query_string = "title=" . urlencode($title) . "&url=" . urlencode($vbseo_url_) . "&blog_name=" . urlencode($bbtitle) . "&excerpt=" . urlencode($snippet);
$pret = vbseo_http_query_full($turl, 'POST', $query_string, 0, 'application/x-www-form-urlencoded');
return strstr($pret['content'], '<response>') && !strstr($pret['content'], '<error>1');
}
function vbseo_do_service_update($url_to_check)
{
global $vboptions;
$res_success = false;
$xmlrpc_query = '<?xml version="1.0"?>
<methodCall>
<methodName>weblogUpdates.extendedPing</methodName>
<params>
<param><value>' . $vboptions['bbtitle'] . '</value></param>
<param><value>' . $vboptions['bburl2'] . '/</value></param>
<param><value>' . ($url_to_check?$url_to_check:'') . '</value></param>
<param><value>' . ($vboptions['bburl2'] . '/external.' . VBSEO_VB_EXT . '?type=RSS2') . '</value></param>
</params></methodCall>';
$xmlrpc_query_simple = '<?xml version="1.0"?>
<methodCall>
<methodName>weblogUpdates.ping</methodName>
<params>
<param><value>' . $vboptions['bbtitle'] . '</value></param>
<param><value>' . $vboptions['bburl2'] . '/</value></param>
</params></methodCall>';
$serv_list = explode('|', VBSEO_PINGBACK_SERVICE);
foreach($serv_list as $serv_url)
{
$pret = vbseo_http_query_full($serv_url, 'POST', $xmlrpc_query, 0, 'text/xml');
if (strstr($pret['content'], '<boolean>1</boolean>'))
$pret = vbseo_http_query_full($serv_url, 'POST', $xmlrpc_query_simple, 0, 'text/xml');
}
return $res_success;
}
function vbseo_do_pingback($vbseo_url_, $ulin)
{
$res_success = false;
$pret = vbseo_http_query_full($ulin);
$ptext = $pret['content'];
$pback_url = '';
if (preg_match("#x-pingback\s*:\s*(\S+)#im", $pret['headers'], $locm))
$pback_url = $locm[1];
else
if (preg_match('#<link rel="pingback" href="([^"]+)"#is', $ptext, $locm))
$pback_url = $locm[1];
if (!$pback_url)
return -1;
$xmlrpc_query = '<?xml version="1.0"?>
<methodCall>
<methodName>pingback.ping</methodName>
<params>
<param><value><string>' . $vbseo_url_ . '</string></value></param>
<param><value><string>' . $ulin . '</string></value></param>
</params></methodCall>';
$pret = vbseo_http_query_full($pback_url, 'POST', $xmlrpc_query, 0, 'text/xml');
$res_success = !strstr($pret['content'], '<name>faultCode</name>');
return $res_success;
}
function vbseo_linkback_hit($l_id)
{
$db = vbseo_get_db();
$db->vbseodb_query($q = "UPDATE " . vbseo_tbl_prefix('vbseo_linkback') . "
SET t_hits = t_hits + 1
WHERE t_id = '" . intval($l_id) . "'"
);
}
function vbseo_pingback_exists($dest_url, $threadid)
{
$db = vbseo_get_db();
if ($dest_url[strlen($dest_url)-1] == '/')$dest_url = substr($dest_url, 0, strlen($dest_url)-1);
$rq = $db->vbseodb_query_first($q = "SELECT t_id as id FROM " . vbseo_tbl_prefix('vbseo_linkback') . "
WHERE t_dest_url LIKE \"" . vbseo_db_escape(preg_replace('|#.*$|', '', $dest_url)) . "%\" AND
t_threadid = \"$threadid\""
);
if ($rq['id'])
{
vbseo_linkback_hit($rq['id']);
return true;
}
return false;
}
function vbseo_store_pingback($src_url, $dest_url, $pingtype, $postid, $postcount,
$threadid, $page, $title, $snippet, $incoming, $approve, $checkexists, $wait = 0)
{
$db = vbseo_get_db();
$db->vbseodb_query($q = "INSERT INTO " . vbseo_tbl_prefix('vbseo_linkback') . "
(t_time, t_src_url, t_dest_url, t_type, t_postid, t_postcount, t_threadid, t_page, 
t_title, t_text, t_approve, t_incoming, t_wait, t_hits)
VALUES (
\"" . time() . "\", \"" . vbseo_db_escape($src_url) . "\", \"" . vbseo_db_escape($dest_url) . "\", \"$pingtype\",
\"$postid\",\"$postcount\",\"$threadid\",\"$page\",\"" . vbseo_db_escape($title) . "\",\"" . vbseo_db_escape($snippet) . "\","
. ( ($approve && !$wait) ? 1 : 0) . ", \"$incoming\"," . ($wait?1:0) .", 1)"
);
if ($incoming)
{
vbseo_linkback_approve(0, $threadid);
vbseo_send_notification_pingback($threadid, $postid, $src_url, $title, $snippet, ($approve && !$wait) ? 1 : 0, (VBSEO_PINGBACK_NOTIFY_BCC && !$wait) ? 1 : 0);
}
}
function vbseo_trackback_proc()
{
$error_msg = vbseo_ping_proc($_POST['url'], VBSEO_TOPREL_FULL . VBSEO_REQURL, 1,
$_POST['title'], $_POST['excerpt']);
echo
"<\?xml version=\"1.0\" encoding=\"utf-8\"?>
<response>
<error>" . ($error_msg['code']?1:0) . "</error>" .
($error_msg['msg']?"\n<message>" . $error_msg['msg'] . "</message>":"") . "
</response>
";
exit;
}
function vbseo_xmlrpc_proc()
{
global $HTTP_RAW_POST_DATA, $vboptions;
$xrdata = trim($HTTP_RAW_POST_DATA);
if (preg_match('#<methodName>pingback\.ping</methodName>#i', $xrdata))
{
preg_match_all('#<param>(.*?)</param>#i', $xrdata, $parmatch);
$src_url = strip_tags($parmatch[1][0]);
$dest_url = strip_tags($parmatch[1][1]);
$error_msg = vbseo_ping_proc($src_url, $dest_url, 0);
}
else
$error_msg = array('code' => 0, 'msg' => 'Wrong method name provided');
if ($error_msg)
echo
'<methodResponse>
<fault>
<value>
<struct>
<member>
<name>faultCode</name>
<value><int>' . $error_msg['code'] . '</int></value>
</member>
<member>
<name>faultString</name>
<value><string>' . $error_msg['msg'] . '</string></value>
</member>
</struct>
</value>
</fault>
</methodResponse>
';
else
echo "Pingback has been registered from $src_url to $dest_url";
}
function vbseo_linkback_recalc()
{
global $vbseo_banned, $vbseo_banned_regexp;
$db = vbseo_get_db();
$db->vbseodb_query("UPDATE " . vbseo_tbl_prefix('thread') . " SET vbseo_linkbacks_no = 0");
$get_query = $db->vbseodb_query("SELECT count(*) as cnt,t_threadid FROM " . vbseo_tbl_prefix('vbseo_linkback') . " 
WHERE t_deleted=0  AND t_incoming=1 AND t_approve=1
GROUP BY t_threadid");
while($lthread = $db->vbseodb_fetch_array($get_query))
{
$db->vbseodb_query("UPDATE " . vbseo_tbl_prefix('thread') . " 
SET vbseo_linkbacks_no = '".$lthread['cnt']."'
WHERE threadid = '".$lthread['t_threadid']."'
");
}
$db->vbseodb_free_result($lthread);
}
function vbseo_linkback_approve($linkback_id, $threadid = 0)
{
$db = vbseo_get_db();
if($linkback_id)
{
$getthread = $db->vbseodb_query_first("SELECT t_threadid FROM " . vbseo_tbl_prefix('vbseo_linkback') . " 
WHERE t_id = ".intval($linkback_id));
$threadid = $getthread['t_threadid'];
}
$getcount = $db->vbseodb_query_first("SELECT count(*) as cnt FROM " . vbseo_tbl_prefix('vbseo_linkback') . " 
WHERE t_deleted=0 AND t_incoming=1 AND t_approve=1 AND t_threadid = ".intval($threadid));
$db->vbseodb_query("UPDATE " . vbseo_tbl_prefix('thread') . " 
SET vbseo_linkbacks_no = '".$getcount['cnt']."'
WHERE threadid = '".intval($threadid)."'
");
}
function vbseo_linkback_unbandomain($bdomain, $ltype)
{
$db = vbseo_get_db();
$db->vbseodb_query("UPDATE " . vbseo_tbl_prefix('vbseo_blacklist') . " 
SET l_deleted = 1 
WHERE ".($bdomain ? "l_domain = '".$db->vbseodb_escape_string($bdomain)."' AND":"")." l_type = ".$ltype);
}
function vbseo_linkback_banhit($bdomain, $ltype)
{
$db = vbseo_get_db();
$db->vbseodb_query("UPDATE " . vbseo_tbl_prefix('vbseo_blacklist') . " 
SET l_hits = l_hits + 1 
WHERE l_domain = '".$db->vbseodb_escape_string($bdomain)."' AND l_type = ".$ltype);
}
function vbseo_linkback_getbandomains($ltype, $retarray = true)
{
global $vbseo_banned, $vbseo_banned_regexp;
if(!$vbseo_banned)
$vbseo_banned = $vbseo_banned_regexp = array();
if(!$vbseo_banned[$ltype])
{
$db = vbseo_get_db();
$get_query = $db->vbseodb_query($q="SELECT l_domain FROM " . vbseo_tbl_prefix('vbseo_blacklist') . " 
WHERE l_deleted = 0  AND l_type = ".$ltype);
$vbseo_banned[$ltype] = array();
$bregs = array();
while($bdom = $db->vbseodb_fetch_array($get_query))
{
$vbseo_banned[$ltype][] = $bdom['l_domain'];
$bregs[] = preg_quote($bdom['l_domain'],'#');
}
$vbseo_banned_regexp[$ltype] = $bregs ? implode('|', $bregs) : '';
$db->vbseodb_free_result($lthread);
}
return $retarray ? $vbseo_banned[$ltype] : $vbseo_banned_regexp[$ltype];
}
function vbseo_linkback_bandomain($bdomain, $ltype)
{
$db = vbseo_get_db();
$bdom = trim($bdomain);
$domexists = $db->vbseodb_query_first("SELECT l_domain FROM " . vbseo_tbl_prefix('vbseo_blacklist') . " 
WHERE l_domain = '".$db->vbseodb_escape_string($bdom)."' AND l_type = ".$ltype);
if($domexists['l_domain'])
{
$db->vbseodb_query($q="UPDATE " . vbseo_tbl_prefix('vbseo_blacklist') . " 
SET l_deleted = 0
WHERE l_domain = '".$db->vbseodb_escape_string($bdom)."' AND l_type = ".$ltype);
return false;
}else
{
$db->vbseodb_query("INSERT INTO " . vbseo_tbl_prefix('vbseo_blacklist') . "
SET l_domain = '".$db->vbseodb_escape_string($bdom)."',
l_deleted = 0,
l_type = ".$ltype.",
l_dateline = ".time()
);
return true;
}
}
function vbseo_ping_proc($src_url, $dest_url, $pingtype = 0, $title = '', $snippet = '')
{
global $vboptions, $vbseo_gcache;
$ping_type_str = (($pingtype == 1)?'track':(($pingtype == 2)?'ref':'ping')) . 'backs';
$error_msg = array();
$link_confirm = false;
vbseo_get_options(false);
vbseo_prepare_seo_replace();
vbseo_get_forum_info();
$db = vbseo_get_db();
$matchfull = $vboptions['bburl2'];
$postid = $postcount = $threadid = $page = 0;
if ($GLOBALS['vbseo_linkback_cleanup'])
{
$src_url = preg_replace('#' . (implode('|', $GLOBALS['vbseo_linkback_cleanup'])) . '#', '', $src_url);
$dest_url = preg_replace('#' . (implode('|', $GLOBALS['vbseo_linkback_cleanup'])) . '#', '', $dest_url);
}
$arr = $arr2 = $arr3 = array();
list($dest_url_pre, $dest_url_post) = explode('#', $dest_url);
if (strstr($dest_url_pre, $matchfull))
{
$dest_url_pre = substr($dest_url_pre, strlen($matchfull) + 1);
$dest_url_pre2 = preg_replace('#\?.*#', '', $dest_url_pre);
if (!($arrt = vbseo_check_url('VBSEO_URL_THREAD_PAGENUM', $dest_url_pre2)))
$arrt = vbseo_check_url('VBSEO_URL_THREAD', $dest_url_pre2);
if (isset($arrt['thread_id']))
$threadid = $arrt['thread_id'];
if (($arr = vbseo_check_url('VBSEO_URL_POST_SHOW', $dest_url_pre2)) || preg_match('#showpost\.' . VBSEO_VB_EXT . '\?[^"]*?p(?:ostid)?=([0-9]+)[^/"]*$#i', $dest_url_pre, $arr2) || preg_match('#showthread\.' . VBSEO_VB_EXT . '\?[^"]*?p=([0-9]+)[^/"]*$#i', $dest_url_pre, $arr2) || ($arrt && preg_match('#post(\d+)#', $dest_url_post, $arr3))
)
{
global $found_object_ids;
$postid = $arr ? $arr['post_id'] : ($arr2 ? $arr2[1] : $arr3[1] + 0);
if ($postid)
{
$found_object_ids['prepostthread_ids'] = array($postid);
vbseo_get_post_thread_info($postid);
$threadid = $vbseo_gcache['post'][$postid]['threadid'];
$postcount = $vbseo_gcache['post'][$postid]['preposts'];
$page = vbseo_thread_pagenum($postcount);
$link_confirm = true;
}
}
else
if (preg_match('#showthread\.' . VBSEO_VB_EXT . '\?[^"]*?t=([0-9]+)[^/"]*$#i', $dest_url_pre, $arr2) || ($arrt && !$dest_url_post)
)
{
$threadid = $arr2 ? $arr2[1] : $arrt['thread_id'];
$page = $arrt['thread_page'] ? $arrt['thread_page'] : 1;
$link_confirm = true;
}
vbseo_get_thread_info($threadid);
$threadinfo = $vbseo_gcache['thread'][$threadid];
$forumid = $threadinfo['forumid'];
if ($threadinfo['visible'] != 1)
$link_confirm = false;
}
$put_wait = false;
if ($link_confirm)
{
$c_src_url = preg_replace('|.*?://(www\.)?|', '', preg_replace('|#.*$|', '', $src_url));
$pret = vbseo_http_query_full($src_url);
$pcont = $pret['content'];
$purl = parse_url($src_url);
if(!$title)
{
$title = vbseo_get_page_title($pcont, 0, true);
}
$qf = $db->vbseodb_query_first($q = "SELECT * FROM " . vbseo_tbl_prefix('vbseo_linkback') . "
WHERE (t_src_url LIKE \"%" . vbseo_db_escape($c_src_url) . "%\" 
".((VBSEO_LINKBACK_IGNOREDUPE && $purl['host']) ? "OR (
t_src_url LIKE \"%" . vbseo_db_escape(str_replace('www.','',$purl['host'])) . "/%\" 
AND t_title LIKE \"". vbseo_db_escape($title) . "\" 
)":"")."
)
AND t_threadid = \"$threadid\""
);
if(VBSEO_LINKBACK_REQUIRE_REF && $pingtype==2 && $qf['t_wait'])
{
$pingtype = $qf['t_type'];
$approve = $vbseo_gcache['forum'][$forumid]['vbseo_moderatepingback'] ? 1 : 0;
$db->vbseodb_query("UPDATE " . vbseo_tbl_prefix('vbseo_linkback') . "
SET t_wait = 0, t_approve = " . $approve . "
WHERE t_id = ".$qf['t_id']);
vbseo_linkback_approve($qf['t_id']);
vbseo_send_notification_pingback($threadid, $postid, $src_url, 
$qf['t_title'], $qf['t_text'], $approve, VBSEO_PINGBACK_NOTIFY_BCC);
}else
if(VBSEO_LINKBACK_REQUIRE_REF && $pingtype==0 && !$qf['t_id'])
{
$put_wait = true;
}
if ($qf['t_id'])
{
vbseo_linkback_hit($qf['t_id']);
$error_msg = array('code' => 48, 'msg' => 'The pingback has already been registered.');
}
else
{
if ($pingtype == 1 && VBSEO_TRACKBACK_IPCHECK)
{
$parsed_src_url = @parse_url($src_url);
$hostip = gethostbyname($parsed_src_url['host']);
if ($hostip != $_SERVER['REMOTE_ADDR'])
$error_msg = array('code' => 47, 'msg' => 'The target server IP address doesn\'t match request host IP.');
}
if (!$error_msg && ($pingtype == 2))
{
$qf2 = $db->vbseodb_query_first($q = "SELECT count(*) as cnt FROM " . vbseo_tbl_prefix('vbseo_linkback') . "
WHERE t_dest_url LIKE \"%" . vbseo_db_escape($c_src_url) . "%\" AND
t_threadid = \"$threadid\""
);
if ($qf2['cnt'] > 0)
$error_msg = array('code' => 4, 'msg' => 'The outgoing linkback has already been registered, refback skipped.');
}
if (!$error_msg && ($pingtype != 1))
{
if (!preg_match('#<a[^>]*?' . preg_quote($dest_url, '#') . '.*?>#', $pcont, $lm) && !preg_match('#<a[^>]*?' . preg_quote($matchfull . '/showthread.php?t=' . $threadid, '#') . '.*?>#', $pcont, $lm))
$error_msg = array('code' => 17, 'msg' => 'The source URI does not contain a link to the target URI, and so cannot be used as a source.');
else
{
$sn = '%vbseo_snippet%';
$snippet = preg_replace('#[ \t]+#s', " ", preg_replace('#<.*?>#is', '', str_replace($lm[0], $sn, $pcont)));
$snippet = preg_replace('#(<\/?(div|p).*?\>)#m', "\n$1", $snippet);
$snippet = preg_replace('#^\s+#m', "", $snippet);
$snippet = preg_replace('#[\r\n]+#', "\n ", $snippet);
$pcharset = vbseo_get_page_charset($pcont);
$snippet = vbseo_convert_charset($snippet, $pcharset);
$halflen = (int)(VBSEO_SNIPPET_LENGTH / 2);
preg_match('#\s(.{0,' . $halflen . '}' . preg_quote($sn) . '.{0,' . $halflen . '})\b#', $snippet, $sm);
$snippet = trim(str_replace($sn, '', $sm[0]));
}
}
if (VBSEO_PINGBACK_STOPWORDS &&
(preg_match($pbsw = '#\b' . VBSEO_PINGBACK_STOPWORDS . '\b#', $snippet) ||
preg_match($pbsw, $title) ||
preg_match($pbsw, $threadinfo['title'])
))
$error_msg = array('code' => 1, 'msg' => 'The request has been rejected due to anti-SPAM policy.');
else
{
if($purl['host'])
{
$banned_domains = vbseo_linkback_getbandomains(1, false);
if ($banned_domains && preg_match('#(' . $banned_domains . ')#i', $purl['host'], $pm))
{
vbseo_linkback_banhit($pm[1], 1);
$error_msg = array('code' => 2, 'msg' => 'The request is originated from blacklisted domain.');
}
}
}
if (!$error_msg)
{
if(VBSEO_MAX_TITLE_LENGTH)
$title = vbseo_substr($title, 0, VBSEO_MAX_TITLE_LENGTH);
vbseo_store_pingback($src_url, $dest_url, $pingtype, $postid, $postcount,
$threadid, $page, $title, $snippet, 1,
$vbseo_gcache['forum'][$forumid]['vbseo_moderate' . $ping_type_str ]?0:1,
0,
$put_wait
);
}
}
}
else
$error_msg = array('code' => 3, 'msg' => 'The link is not confirmed.');
return $error_msg;
}
function vbseo_get_email_templates($emailtpl)
{
global $vbulletin;
$evalemail = array();
$email_texts = $vbulletin->db->query_read("
SELECT text, languageid, varname
FROM " . vbseo_tbl_prefix('phrase') . "
WHERE varname LIKE '$emailtpl%'
");
while ($email_text = $vbulletin->db->fetch_array($email_texts))
{
$emails["$email_text[languageid]"]["$email_text[varname]"] = $email_text['text'];
}
require_once(DIR . '/includes/functions_misc.' . VBSEO_VB_EXT);
foreach ($emails AS $languageid => $email_text)
{
$text_message = str_replace("\\'", "'", vbseo_db_escape(iif(empty($email_text[$emailtpl . '_msg']), $emails['-1'][$emailtpl . '_msg'], $email_text[$emailtpl . '_msg'])));
$text_message = replace_template_variables($text_message);
$text_subject = str_replace("\\'", "'", vbseo_db_escape(iif(empty($email_text[$emailtpl . '_subj']), $emails['-1'][$emailtpl . '_subj'], $email_text[$emailtpl . '_subj'])));
$text_subject = replace_template_variables($text_subject);
$evalemail["$languageid"] = '
$msg = "' . $text_message . '";
$subj = "' . $text_subject . '";
';
}
return $evalemail;
}
function vbseo_send_notification_pingback($threadid, $postid, $vbseo_linkback_uri, $title, $message, $approve = 1, $sendtocp = 1)
{
global $vbulletin, $show;
if (!$vbulletin)
return;
if (!$vbulletin->options['enableemail'])
{
return;
}
@define('VBSEO_PREPROCESSED', true);
$threadinfo = fetch_threadinfo($threadid);
$foruminfo = fetch_foruminfo($threadinfo['forumid']);
$threadinfo['title'] = unhtmlspecialchars($threadinfo['title']);
$foruminfo['title_clean'] = unhtmlspecialchars($foruminfo['title_clean']);
vbmail_start();
$evalemail = array();
if ($approve && VBSEO_PINGBACK_NOTIFY)
{
$useremails = $vbulletin->db->query_read("
SELECT user.*, subscribethread.emailupdate
FROM " . vbseo_tbl_prefix('subscribethread') . " AS subscribethread
INNER JOIN " . vbseo_tbl_prefix('user') . " AS user ON (subscribethread.userid = user.userid)
LEFT JOIN " . vbseo_tbl_prefix('usergroup') . " AS usergroup ON (usergroup.usergroupid = user.usergroupid)
LEFT JOIN " . vbseo_tbl_prefix('usertextfield') . " AS usertextfield ON (usertextfield.userid = user.userid)
WHERE subscribethread.threadid = $threadid AND
subscribethread.emailupdate IN (1, 4) AND
user.usergroupid <> 3 AND
(usergroup.genericoptions & " . ($vbulletin->bf_ugp_genericoptions['isbannedgroup'] + 0) . ") = 0
");
while ($touser = $vbulletin->db->fetch_array($useremails))
{
if ($vbulletin->usergroupcache["$touser[usergroupid]"]['genericoptions'] &($vbulletin->bf_ugp_genericoptions['isbannedgroup'] + 0))
{
continue;
}
$touser['username'] = unhtmlspecialchars($touser['username']);
$touser['languageid'] = iif($touser['languageid'] == 0, $vbulletin->options['languageid'], $touser['languageid']);
if (empty($evalemail)) $evalemail = vbseo_get_email_templates('vbseo_notify_linkbacks');
eval(iif(empty($evalemail["$touser[languageid]"]), $evalemail["-1"], $evalemail["$touser[languageid]"]));
if ($touser['emailupdate'] == 4 AND !empty($touser['icq']))
{
$touser['email'] = $touser['icq'] . '@pager.icq.com';
}
vbmail($touser['email'], $subj, $msg);
}
}
$evalemail = vbseo_get_email_templates('vbseo_notify_linkbacks_mod');
$more_emails = explode(' ', VBSEO_PINGBACK_NOTIFY_BCC);
if ($sendtocp)
foreach($more_emails as $email)
{
eval($evalemail["-1"]);
vbmail($email, $subj, $msg);
}
vbmail_end();
}
?>