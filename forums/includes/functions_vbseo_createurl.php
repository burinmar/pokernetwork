<?php

/************************************************************************************
* vBSEO 3.2.0 for vBulletin v3.x.x by Crawlability, Inc.
*                                                                                   *
* Copyright ? 2005-2008, Crawlability, Inc. All rights reserved.                    *
* You may not redistribute this file or its derivatives without written permission. *
*                                                                                   *
* Sales Email: sales@crawlability.com                                               *
*                                                                                   *
*----------------------------vBSEO IS NOT FREE SOFTWARE-----------------------------*
* http://www.crawlability.com/vbseo/license/                                        *
************************************************************************************/

function vbseo_append_a(&$title)
{
$ishort = false;
if ((preg_match('#[-_\s]\d+$#', $title) || !$title))
{
$title .= ($title?VBSEO_SPACER:'') . VBSEO_APPEND_CHAR;
$ishort = true;
}
return $ishort;
}
function vbseo_forum_seotitle(&$vbseo_gcache_forum)
{
if (!isset($vbseo_gcache_forum['seotitle']))
$vbseo_gcache_forum['seotitle'] =
isset($GLOBALS['vbseo_forum_slugs'][$vbseo_gcache_forum['forumid']]) ?
$GLOBALS['vbseo_forum_slugs'][$vbseo_gcache_forum['forumid']] :
vbseo_filter_text(
(isset($vbseo_gcache_forum['title_clean']) && $vbseo_gcache_forum['title_clean']) ? $vbseo_gcache_forum['title_clean'] :
strip_tags($vbseo_gcache_forum['title'])
);
return isset($GLOBALS['vbseo_forum_slugs'][$vbseo_gcache_forum['forumid']]) ?
$GLOBALS['vbseo_forum_slugs'][$vbseo_gcache_forum['forumid']] : '';
}
function vbseo_thread_url_row($thread_row, $page = 1)
{
global $vbseo_gcache;
$vbseo_gcache['thread'][$thread_row['threadid']] = $thread_row;
$url = vbseo_thread_url($thread_row['threadid'], $page);
unset($vbseo_gcache['thread'][$thread_row['threadid']]);
return $url;
}
function vbseo_thread_url_row_spec($thread_row, $spec)
{
global $vbseo_gcache;
$vbseo_gcache['thread'][$thread_row['threadid']] = $thread_row;
$url = vbseo_thread_url($thread_row['threadid'], 1, $spec);
unset($vbseo_gcache['thread'][$thread_row['threadid']]);
return $url;
}
function vbseo_poll_url_direct($thread_row, $poll_row)
{
global $vbseo_gcache;
$vbseo_gcache['thread'][$thread_row['threadid']] = $thread_row;
$poll_row['threadid'] = $thread_row['threadid'];
$vbseo_gcache['polls'][$poll_row['pollid']] = $poll_row;
$url = vbseo_poll_url($poll_row['pollid']);
unset($vbseo_gcache['thread'][$thread_row['threadid']]);
unset($vbseo_gcache['polls'][$poll_row['pollid']]);
return $url;
}
function vbseo_member_url_row($userid, $username)
{
global $vbseo_gcache, $vbseo_vars;
$vbseo_gcache['user'][$userid] = compact('userid', 'username');
$url = vbseo_member_url($userid);
unset($vbseo_gcache['user'][$userid]);
return $url;
}
function vbseo_post_url_row($thread_row, $post_row, $postcount)
{
global $vbseo_gcache;
$vbseo_gcache['post'][$post_row['postid']] = $post_row;
$vbseo_gcache['thread'][$thread_row['threadid']] = $thread_row;
$url = vbseo_post_url($post_row['postid'], $postcount);
unset($vbseo_gcache['post'][$post_row['postid']]);
unset($vbseo_gcache['thread'][$thread_row['threadid']]);
return $url;
}
function vbseo_post_url($postid, $post_count)
{
global $vbseo_gcache;
$pinfo = $vbseo_gcache['post'][$postid];
$threadid = $pinfo['threadid'];
$thread = &$vbseo_gcache['thread'][$threadid];
$forumid = $thread['forumid'];
if (!$thread['seotitle'])
$thread['seotitle'] = vbseo_filter_replace_text($thread['title'] ? $thread['title'] : $thread['threadtitle']);
$replace = array('%post_id%' => $postid,
'%post_count%' => $post_count,
'%thread_id%' => $threadid,
'%thread_title%' => $thread['seotitle'],
'%forum_id%' => $forumid,
'%forum_title%' => $vbseo_gcache['forum'][$forumid]['seotitle'],
'%forum_path%' => $vbseo_gcache['forum'][$forumid]['path'],
);
$rets = str_replace(
array_keys($replace),
$replace,
VBSEO_URL_POST_SHOW
);
return $rets;
}
function vbseo_page_size($cachedonly = false)
{
global $bbuserinfo, $vboptions, $vbulletin;
$vbo = $vboptions ? $vboptions : ($vbulletin?$vbulletin->options:array());
$bbu = $bbuserinfo ? $bbuserinfo : ($vbulletin?$vbulletin->userinfo:array());
if (!isset($bbu['maxposts']) && $bbu['userid'] && !$cachedonly)
{
$db = vbseo_get_db();
$getmaxposts = $db->vbseodb_query_first("
SELECT maxposts
FROM " . vbseo_tbl_prefix('user') . "
WHERE userid = '" . $bbu['userid'] . "'
LIMIT 1
");
$bbu['maxposts'] = $getmaxposts['maxposts'];
}
if ($bbu['maxposts'] > 0 && $vboptions['usermaxposts'])
$vbo['maxposts'] = $bbu['maxposts'];
return $vbo['maxposts'];
}
function vbseo_thread_pagenum($postcount, $div = true)
{
$maxposts = vbseo_page_size();
if($div)
return  ($maxposts != 0 ? @ceil($postcount / $maxposts) : 0);
else
return $postcount * $maxposts;
}
function vbseo_thread_url_postid($postid, $page = 1, $gotopost = false, $postcount = 0)
{
global $vbseo_gcache, $vboptions, $bbuserinfo, $found_object_ids;
if (!$vbseo_gcache['post'][$postid])
{
vbseo_get_post_thread_info($found_object_ids['postthread_ids']);
vbseo_get_thread_info($found_object_ids['postthreads']);
}
$pinfo = &$vbseo_gcache['post'][$postid];
if (!$pinfo)
return '';
$threadid = $pinfo['threadid'];
if (!$tinfo = $vbseo_gcache['thread'][$threadid])
return '';
if ($postcount>0)
{
$pinfo['preposts'] = $postcount;
}
$totr = str_replace(',', '', str_replace('.', '', $tinfo['replycount']));
if (($bbuserinfo['postorder'] == 1) && !$pinfo['prepostsproc'] && isset($pinfo['preposts']) )
{   
$pinfo['preposts'] = $totr - $pinfo['preposts'] + 2;
}
if ($postcount == -1 && !$pinfo['preposts'])
{
$pinfo['preposts'] = 1;
}
if (!$pinfo['preposts'] && isset($tinfo['replycount']) )
{   
if($tinfo['lastpostid'] == $pinfo['postid'] || vbseo_page_size(true) > $totr )
$pinfo['preposts'] = $totr + 1;
}
if (isset($pinfo['preposts']) && $page == 1 && !$gotopost)
{    
$page = vbseo_thread_pagenum($pinfo['preposts']);
return vbseo_thread_url($threadid, $page) . '#post' . $postid;
}
else 
return vbseo_thread_url($threadid, $page,
$page > 1 ? VBSEO_URL_THREAD_GOTOPOST_PAGENUM : VBSEO_URL_THREAD_GOTOPOST, $postid);
}
function vbseo_thread_url($threadid, $page = null, $special_format = '', $postid = '')
{
global $vbseo_gcache;
$forumid = $vbseo_gcache['thread'][$threadid]['forumid'];
if (!$forumid)
return '';
$thread = &$vbseo_gcache['thread'][$threadid];
if (!$thread['seotitle'])
$thread['seotitle'] = vbseo_filter_replace_text($thread['title'] ? $thread['title'] : $thread['threadtitle']);
$title = $thread['seotitle'];
$ishort = vbseo_append_a($title);
vbseo_forum_seotitle($vbseo_gcache['forum'][$forumid]);
$replace = array('%post_id%' => $postid,
'%thread_id%' => $threadid,
'%thread_title%' => $title,
'%thread_page%' => $page,
'%forum_id%' => $forumid,
'%forum_title%' => $vbseo_gcache['forum'][$forumid]['seotitle'],
'%forum_path%' => $vbseo_gcache['forum'][$forumid]['path'],
);
$uformat = $special_format ? $special_format :
(($page <= 1) ? VBSEO_URL_THREAD : VBSEO_URL_THREAD_PAGENUM);
$rets = str_replace(
array_keys($replace),
$replace,
$uformat
);
if ($ishort)
$rets = str_replace(VBSEO_SPACER . VBSEO_SPACER, VBSEO_SPACER, $rets);
return $rets;
}
function vbseo_poll_url($pollid)
{
global $vbseo_gcache;
$threadid = $vbseo_gcache['polls'][$pollid]['threadid'];
if (!$threadid || !$vbseo_gcache['thread'][$threadid])
{
vbseo_get_poll_info($pollid);
$db = vbseo_get_db();
$tar = $db->vbseodb_query_first($q = "
SELECT threadid
FROM " . vbseo_tbl_prefix('thread') . " AS thread
WHERE pollid = $pollid
LIMIT 1
");
$threadid = $tar['threadid'];
vbseo_get_thread_info($threadid);
}
$forumid = $vbseo_gcache['thread'][$threadid]['forumid'];
vbseo_forum_seotitle($vbseo_gcache['forum'][$forumid]);
$title = vbseo_filter_text(strip_tags($vbseo_gcache['polls'][$pollid]['question']));
$replace = array('%poll_id%' => $pollid,
'%poll_title%' => $title,
'%forum_id%' => $forumid,
'%forum_title%' => $vbseo_gcache['forum'][$forumid]['seotitle'],
'%forum_path%' => $vbseo_gcache['forum'][$forumid]['path'],
);
$rets = str_replace(
array_keys($replace),
$replace,
VBSEO_URL_POLL
);
return $rets;
}
function vbseo_attachment_url($attid, $reformat = '', $d = '', $thumb = '')
{
global $vbseo_gcache, $found_object_ids;
$atarr = $vbseo_gcache['attach'][$attid];
$postid = $atarr['postid'];
if (!$attid || !$postid)
return '';
if (!$vbseo_gcache['post'][$postid])
{
vbseo_get_post_thread_info($found_object_ids['postthread_ids']);
vbseo_get_thread_info($found_object_ids['postthreads']);
}
$threadid = $vbseo_gcache['post'][$postid]['threadid'];
if (!$threadid)
return '';
$forumid = $vbseo_gcache['thread'][$threadid]['forumid'];
vbseo_forum_seotitle($vbseo_gcache['forum'][$forumid]);
$t2 = &$vbseo_gcache['thread'][$threadid]['seotitle'];
if (!$t2)
{
$t2 = vbseo_filter_text($vbseo_gcache['thread'][$threadid]['threadtitle']);
}
if ($d)$attid .= 'd' . $d;
if ($thumb)$attid .= 't';
$replace = array('%attachment_id%' => $attid,
'%original_filename%' => vbseo_filter_text($atarr['filename'], '.'),
'%thread_title%' => $vbseo_gcache['thread'][$threadid]['seotitle'],
'%forum_title%' => $vbseo_gcache['forum'][$forumid]['seotitle'],
'%forum_path%' => $vbseo_gcache['forum'][$forumid]['path'],
'%forum_id%' => $forumid,
);
$rets = str_replace(
array_keys($replace),
$replace,
$reformat ? $reformat : VBSEO_ATTACHMENTS_PREFIX . VBSEO_URL_ATTACHMENT
);
return $rets;
}
function vbseo_announcement_url($forumid, $announcementid = 0)
{
global $vbseo_gcache;
if (!$vbseo_gcache['forum'][$forumid]['announcement'])
return '';
$aid = $announcementid;
if ($announcementid)
$ann_title = $vbseo_gcache['forum'][$forumid]['announcement'][$announcementid];
else
{
reset($vbseo_gcache['forum'][$forumid]['announcement']);
list($aid, $ann_title) = each($vbseo_gcache['forum'][$forumid]['announcement']);
}
$seo_title = vbseo_filter_replace_text($ann_title);
vbseo_forum_seotitle($vbseo_gcache['forum'][$forumid]);
$replace = array('%forum_id%' => $forumid,
'%forum_title%' => $vbseo_gcache['forum'][$forumid]['seotitle'],
'%announcement_title%' => $seo_title,
'%announcement_id%' => $aid,
'%forum_path%' => $vbseo_gcache['forum'][$forumid]['path'],
'%forum_page%' => $page,
);
$rets = str_replace(
array_keys($replace),
$replace,
$announcementid?VBSEO_URL_FORUM_ANNOUNCEMENT:VBSEO_URL_FORUM_ANNOUNCEMENT_ALL
);
return $rets;
}
function vbseo_album_url_row($urlformat, $arow)
{
global $vbseo_gcache, $vbseo_vars;
$vbseo_gcache['user'][$arow['userid']] = compact($arow['userid'], $arow['username']);
$vbseo_gcache['album'][$arow['albumid']] = $arow;
$vbseo_gcache['pic'][$arow['pictureid']] = $arow;
$url = vbseo_album_url($urlformat, $arow);
unset($vbseo_gcache['user'][$arow['userid']]);
unset($vbseo_gcache['album'][$arow['albumid']]);
unset($vbseo_gcache['pic'][$arow['pictureid']]);
return $url;
}
function vbseo_album_url($urlformat, $apars)
{
global $vbseo_gcache;
$repl = array();
if($apars['pictureid'])
{
$pic = $vbseo_gcache['pic'][$apars['pictureid']];
if(!$apars['albumid'])
$apars['albumid'] = $pic['albumid'];
$repl['%picture_title%'] = vbseo_filter_text($pic['caption']);
$repl['%picture_id%'] = $pic['pictureid'];
$repl['%original_ext%'] = $pic['extension'];
if ($apars['thumb'])$repl['%picture_id%'] .= 't';
}
if($apars['albumid'])
{
$alb = $vbseo_gcache['album'][$apars['albumid']];
if(!$alb) $alb = $apars;
$repl['%album_title%'] = vbseo_filter_text($alb['title'], false, true, true);
$repl['%album_id%'] = $apars['albumid'];
}
if(!$apars['u'])
$apars['u'] = $alb['userid'];
if($apars['page'])
$repl['%page%'] = $apars['page'];
if(!$apars['u'])
return '';
$newurl = vbseo_member_url($apars['u'], $apars['username'], $urlformat, $repl);
return $newurl;
}
function vbseo_member_url($userid, $username = '', $urlformat = '', $replace = array(), $apars = array())
{
global $vbseo_gcache;
if(!$urlformat)
$urlformat = 'VBSEO_URL_MEMBER';
if (!$userid && $username)
{
$tmpuser = &$vbseo_gcache['usernm'][strtolower($username)];
}
else
{
$tmpuser = &$vbseo_gcache['user'][$userid];
if (!$tmpuser['userid'])
$tmpuser['userid'] = $userid;
}
if (!isset($tmpuser['seoname']))
{
if ($username)
$tmpuser['username'] = $username;
$tmpuser['seoname'] =
vbseo_filter_text($tmpuser['username'], null, false, true, true);
}
if($apars['page'])
{
$replace['%page%'] = $apars['page'];
}
if($apars['u2'])
{
$tmpuser2 = &$vbseo_gcache['user'][$apars['u2']];
if (!isset($tmpuser2['seoname']))
{
$tmpuser2['seoname'] =
vbseo_filter_text($tmpuser2['username'], null, false, true);
}
$replace['%visitor_id%'] = $apars['u2'];
$replace['%visitor_name%'] = $tmpuser2['seoname'];
}
if (!isset($tmpuser[$urlformat]))
{
$replace['%user_id%'] = $tmpuser['userid'];
$replace['%user_name%'] = $tmpuser['seoname'];
$form = ($urlformat=='VBSEO_URL_AVATAR'?VBSEO_AVATAR_PREFIX:'') . constant($urlformat);
$ret = str_replace(array_keys($replace), $replace, $form);
if(!$replace)
$tmpuser[$urlformat] = $ret;
}else
$ret = $tmpuser[$urlformat];
return $ret;
}
function vbseo_forum_url($forumid, $page = 0, $special_format = '')
{
global $vbseo_gcache;
if ((VBSEO_FORUMLINK_DIRECT || $vbseo_gcache['forum'][$forumid]['nametitle']) && $vbseo_gcache['forum'][$forumid]['link'])
return $vbseo_gcache['forum'][$forumid]['link'];
vbseo_forum_seotitle($vbseo_gcache['forum'][$forumid]);
$replace = array('%forum_id%' => $forumid,
'%forum_title%' => $vbseo_gcache['forum'][$forumid]['seotitle'],
'%forum_path%' => $vbseo_gcache['forum'][$forumid]['path'],
'%forum_page%' => $page,
);
$rets = str_replace(
array_keys($replace),
$replace,
($special_format ? $special_format :
(($page <= 1) ? VBSEO_URL_FORUM : VBSEO_URL_FORUM_PAGENUM)
)
);
return $rets;
}
function vbseo_memberlist_url($letter = '', $page = 1)
{
if (!$page) $page = 1;
if ($letter == '%23') $letter = '0';
$replace = array('%letter%' => strtolower($letter),
'%page%' => (int)$page,
);
$url = VBSEO_URL_MEMBERLIST;
if ($letter != '') $url = VBSEO_URL_MEMBERLIST_LETTER;
if ($letter == '' && $page > 1) $url = VBSEO_URL_MEMBERLIST_PAGENUM;
$rets = str_replace(
array_keys($replace),
$replace,
$url
);
return $rets;
}
function vbseo_reverse_forumtitle($arr)
{
global $vbseo_gcache;
$fid = 0;
vbseo_prepare_seo_replace();
vbseo_get_forum_info();
if (isset($arr['forum_path']))
{
reset($vbseo_gcache['forum']);
while (list(, $forum) = each($vbseo_gcache['forum']))
{
if ($forum['path'] == $arr['forum_path'])
{
$fid = $forum['forumid'];
break;
}
}
}
else if (isset($arr['forum_title']))
{
reset($vbseo_gcache['forum']);
$ue_title = urlencode(($arr['forum_title']));
while (list(, $forum) = each($vbseo_gcache['forum']))
{
if (vbseo_forum_seotitle($forum))
{
}
if ($forum['seotitle'] == $ue_title)
{
$fid = $forum['forumid'];
break;
}
}
}
return $fid;
}
function vbseo_sanitize_url($url)
{
return $url;
}
function vbseo_tags_url($urlformat, $apars = array())
{
$replace = array();
if ($apars['tag'])
{
if(VBSEO_URL_TAGS_FILTER)
{
$apars['tag'] = urldecode($apars['tag']);
$replace['%tag%'] = vbseo_filter_text($apars['tag'], '', false);
}else
$replace['%tag%'] = $apars['tag'];
}
if ($apars['page'])
{
$replace['%page%'] = $apars['page'];
}
$returl = str_replace(array_keys($replace), $replace, $urlformat);
$returl = vbseo_sanitize_url($returl);
return $returl;
}
function vbseo_group_url_row($urlformat, $arow)
{
global $vbseo_gcache, $vbseo_vars;
$vbseo_gcache['groups'][$arow['groupid']] = $arow;
$vbseo_gcache['pic'][$arow['pictureid']] = $arow;
$url = vbseo_group_url($urlformat, $arow);
unset($vbseo_gcache['groups'][$arow['groupid']]);
unset($vbseo_gcache['pic'][$arow['pictureid']]);
return $url;
}
function vbseo_group_url($urlformat, $apars = array())
{
global $vbseo_gcache;
$groupid = $apars['groupid'] ;
$replace = array();
if($apars['pictureid'])
{
$pic = $vbseo_gcache['pic'][$apars['pictureid']];
$replace['%picture_title%'] = vbseo_filter_text($pic['caption']);
$replace['%picture_id%'] = $pic['pictureid'];
$replace['%original_ext%'] = $pic['extension'];
if ($apars['thumb'])$replace['%picture_id%'] .= 't';
}
if ($groupid)
{
$ginfo = &$vbseo_gcache['groups'][$groupid];
if (!isset($ginfo['seotitle']))
$ginfo['seotitle'] = vbseo_filter_text($ginfo['name'], false, true, true);
if(!$ginfo['name'])return '';
$replace['%group_id%'] = $ginfo['groupid'];
$replace['%group_name%'] = $ginfo['seotitle'];
}
if ($apars['page'])
{
$replace['%page%'] = $apars['page'];
}
$returl = str_replace(array_keys($replace), $replace, $urlformat);
return $returl;
}
function vbseo_blog_url($urlformat, $apars = array())
{
global $vbseo_gcache;
$userid = $apars['bloguserid'] ? $apars['bloguserid'] : $apars['u'];
$blogid = $apars['b'] ? $apars['b'] : $apars['blogid'];
$catid = $apars[VBSEO_BLOG_CATID_URI];
$attid = $apars['attachmentid'];
$comid = $apars['bt'];
$replace = array();
if ($comid)
{
$replace['%comment_id%'] = $comid;
}
if ($attid)
{
$replace['%original_filename%'] = vbseo_filter_text($vbseo_gcache['battach'][$attid]['filename'], '.');
$blogid = $vbseo_gcache['battach'][$attid]['blogid'];
if(!$blogid)
return '';
if ($apars['d'])$attid .= 'd' . $apars['d'];
if ($apars['thumb'])$attid .= 't';
$replace['%attachment_id%'] = $attid;
}
if ($blogid)
{
$bloginfo = &$vbseo_gcache['blog'][$blogid];
if (!isset($bloginfo['seotitle']))
$bloginfo['seotitle'] = vbseo_filter_text($bloginfo['title']);
$userid = $bloginfo['userid'];
$replace['%blog_id%'] = $bloginfo['blogid'];
$replace['%blog_title%'] = $bloginfo['seotitle'];
}
if ($catid)
{
if ($catid == -1)
$catinfo = array('blogcategoryid' => 0, 'title' => VBSEO_BLOG_CAT_UNDEF);
else
$catinfo = &$vbseo_gcache['blogcat'][$catid];
if (!isset($catinfo['seotitle']))
{
$catinfo['seotitle'] = vbseo_filter_text($catinfo['title'], '', false);
vbseo_append_a($catinfo['seotitle']);
}
$replace['%category_id%'] = $catinfo['blogcategoryid'];
$replace['%category_title%'] = $catinfo['seotitle'];
}
if ($userid)
{
$tmpuser = &$vbseo_gcache['user'][$userid];
$tmpuser['userid'] = $userid;
if (!$tmpuser['username'])
$tmpuser['username'] = $bloginfo['username'];
if (!isset($tmpuser['seoname']))
$tmpuser['seoname'] =
vbseo_filter_text($tmpuser['username'], null, false, true);
$replace['%user_id%'] = $tmpuser['userid'];
$replace['%user_name%'] = $tmpuser['seoname'];
}
if ($apars['y'])
{
$replace['%year%'] = $apars['y'];
$replace['%month%'] = $apars['m'];
$replace['%day%'] = $apars['d'];
}
if ($apars['page'])
{
$replace['%page%'] = $apars['page'];
}
$returl = str_replace(array_keys($replace), $replace, $urlformat);
if (VBSEO_URL_BLOG_DOMAIN)
$returl = VBSEO_URL_BLOG_DOMAIN . $returl;
return $returl;
}
function vbseo_any_url($url)
{
$re_url = vbseo_replace_urls('', $url);
return $re_url;
}
function vbseo_make_url($url)
{
$re_url = vbseo_replace_urls('', $url);
return $re_url;
}
function vbseo_reverse_username($username)
{
$db = vbseo_get_db();
$usrname_prep = VBSEO_REWRITE_MEMBER_MORECHARS ? $username : str_replace(VBSEO_SPACER, ' ', $username);
$queryId = $db->vbseodb_query($q = 'select userid from ' . vbseo_tbl_prefix('user') . ' where' . ' username like "' . $db->vbseodb_escape_like($usrname_prep) . '" limit 1');
$user = $db->vbseodb_fetch_array($queryId);
if (!$user)
{
$username2 = vbseo_unfilter_text(preg_quote(htmlspecialchars($username)));
$queryId = $db->vbseodb_query($q = 'select userid from ' . vbseo_tbl_prefix('user') . ' where username regexp "' . $db->vbseodb_escape_string($username2) . '" limit 1');
$user = $db->vbseodb_fetch_array($queryId);
}
$db->vbseodb_free_result($queryId);
return $user['userid'];
}
function vbseo_reverse_object($otype, $title, $linkedid = 0)
{
$whr = $fld = $tbl = $ttl2 = $ttl3 = '';
$ttl_app = false;
$ttl_unfilter = true;
$db = vbseo_get_db();
switch($otype)
{
case 'blogcat':
if ($title == VBSEO_BLOG_CAT_UNDEF)
return 0;
$fld = 'blogcategoryid';
$tbl = 'blog_category';
$whr = 'userid = "'.$linkedid.'"  AND title';
$ttl_app = true;
break;
case 'thread':
$fld = 'threadid';
$tbl = 'thread';
$whr = ($linkedid?'forumid = '.intval($linkedid).' AND ':'').'title';
$ttl_app = true;
$ttl_unfilter = false;
break;
case 'album':
$fld = 'albumid';
$tbl = 'album';
$whr = 'userid = "'.$linkedid.'"  AND title';
$ttl_app = true;
break;
case 'group':
$fld = 'groupid';
$tbl = 'socialgroup';
$whr = 'name';
break;
case 'tag':
$fld = 'tagtext';
$tbl = 'tag';
$whr = 'tagtext';
break;
}
if($ttl_app)
$title = preg_replace('#-a$#', '', $title);
$preq = 'select '.$fld.' as iid from  ' . vbseo_tbl_prefix($tbl) . ' where '.$whr.' ';
$queryId = $db->vbseodb_query($preq.' like "' . $db->vbseodb_escape_like(str_replace(VBSEO_SPACER, ' ', $title)) . '" limit 1');
$mg = $db->vbseodb_fetch_array($queryId);
if (!$mg)
{
if($ttl_unfilter)
{
$ttl2 = vbseo_unfilter_text(preg_quote(htmlspecialchars(VBSEO_SPACER.str_replace(' ', VBSEO_SPACER, $title).VBSEO_SPACER)));
$ttl3 = vbseo_unfilter_text(preg_quote(htmlspecialchars(VBSEO_SPACER.str_replace(' ', VBSEO_SPACER, $title).VBSEO_SPACER)), true);
}
else
$ttl2 = '%'.str_replace(VBSEO_SPACER, '%', $db->vbseodb_escape_like($title)).'%';
$queryId = $db->vbseodb_query($q=$preq.
($ttl_unfilter ? 'regexp' : 'like' ).
' "' . $db->vbseodb_escape_string($ttl2) . '" limit 1');
if($queryId)
$mg = $db->vbseodb_fetch_array($queryId);
if(!$mg && $ttl3)
{
$queryId = $db->vbseodb_query($q=$preq.' regexp "' . $db->vbseodb_escape_string($ttl3) . '" limit 1');
if($queryId)
$mg = $db->vbseodb_fetch_array($queryId);
}
}
$db->vbseodb_free_result($queryId);
return $mg['iid'];
}
function vbseo_reverse_formats()
{
if (!defined('VBSEO_FIND_T_FORMAT'))
{
$replace = array('#%thread_id%#' => '(\d+)',
'#%thread_page%#' => '\d+',
'#%post_id%#' => '(\d+)',
'#%post_count%#' => '\d+',
'#%[a-z_]+_id%#' => '\d+',
'#%[a-z_]+%#' => '[^/]+'
);
define('VBSEO_FIND_T_FORMAT', preg_replace(array_keys($replace), $replace, preg_quote(VBSEO_URL_THREAD, '#')));
define('VBSEO_FIND_MT_FORMAT', preg_replace(array_keys($replace), $replace, preg_quote(VBSEO_URL_THREAD_PAGENUM, '#')));
define('VBSEO_FIND_P_FORMAT', preg_replace(array_keys($replace), $replace, preg_quote(VBSEO_URL_POST_SHOW, '#')));
define('VBSEO_FIND_F_FORMAT', preg_replace(array_keys($replace), $replace, preg_quote(VBSEO_URL_FORUM, '#')));
}
}
?>