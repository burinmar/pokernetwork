<?php

class moon_vb_bridge extends moon_com
{
	function properties()
	{
		return array(
			'render' => null
		);
	}

	function events($event, $par) 
	{
		switch($event) {
		case 'vb-mock-noexist':
			$this->use_page('');
			break;
		}
	}

	function main($argv)
	{
		switch ($argv['render']) {
		case 'index-widget':
			return $this->renderIndexWidget();
		}
	}

	private function renderIndexWidget()
	{
		$tpl = $this->load_template();
		$baseUrl = $this->linkas('#');
		$tplArgv = array(
			'url.forum' => $baseUrl,
			'list.threads' => '',
		);

		$threads = $this->db->array_query_assoc('
			SELECT thread.threadid, thread.title, 
				thread.lastposter AS postusername, thread.lastpost AS dateline,
				thread.replycount, forum.forumid
			FROM pokernetwork_forum.vb_thread AS thread
			INNER JOIN pokernetwork_forum.vb_forum AS forum ON(forum.forumid = thread.forumid)
			WHERE 1=1
				AND thread.forumid IN(1,8,12,13,14,10,11,15,2,3,4,9,7,6)
				AND thread.visible = 1
				AND OPEN <> 10
				AND thread.lastpost > ' . ((floor(time() / 3600) * 3600) - 30*24*3600) . '
			ORDER BY thread.lastpost DESC
			LIMIT 9
		');
		foreach ($threads as $thread) {
			$tplArgv['list.threads'] .= $tpl->parse('index_widget:threads.item', array(
				'title' => $thread['title'],
				'nrposts' => $thread['replycount'] + 1,
				'poster' => $thread['postusername'],
				'url' => $baseUrl . 'showthread.php/' . $thread['threadid'] . '?goto=newpost',
				'postword' => $thread['replycount'] == 0
					? 'Post'
					: 'Posts'
			));
		}

		return $tpl->parse('index_widget:main', $tplArgv);
	}
}