<?php
class google extends moon_com {

	function onload() {
		//$dir = $this->get_var('dirGoogleSitemaps');
		$dir = _W_DIR_ . 'google/';
		$this->dirFiles = is_writable($dir) ? $dir : FALSE;
	}

	function events($event) {

		$this->use_page('Common');

		switch ($event) {

		case 'cron':
			$r = '';
			//$r .= "\n<br />" . $this->sitemapNews();
			$r .= "\n<br />" . $this->sitemapMain();
			//$r .= "\n<br />" . $this->sitemapIndex();

			$page = &moon::page();
			$page->set_local('cron', $r);

			$this->set_var('result', $r);
			$this->forget();
			break;

		default:
		}
	}

	function main($vars) {
		$tpl = $this->load_template();
		$win = &moon::shared('admin');
		$win->active($this->my('fullname'));

		$res = isset($vars['result']) ? $vars['result'] : '';

		return '<h1>Google Sitemaps</h1><div style="clear:both">
		<ol>
			<li><a href="' . $this->linkas('#cron') . '">Generate sitemaps</a></li>
		</ol></div>' . $res;
	}


	/*function sitemapIndex() {
		//init
        if (empty($this->dirFiles)) {
			return 'Error: not writable directory';
		}
		$fname = $this->dirFiles . 'sitemap.xml';
		$gz = fopen($fname, 'w9');
		flock($gz, LOCK_EX);
		$s = '<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd"
         xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
';
		fwrite($gz, $s);

		// *begin Items
		$page = & moon :: page();
		$homeURL = $page->home_url();
        $now = date('c');

		$s = '
		<sitemap>
	      <loc>' . $homeURL . 'sitemap-main.xml</loc>
	      <lastmod>' . $now . '</lastmod>
	   </sitemap>
	   <sitemap>
	      <loc>' . $homeURL . 'sitemap-news.xml</loc>
	      <lastmod>' . $now . '</lastmod>
	   </sitemap>';
		fwrite($gz, $s);

		// *end Items

		// Close
		$s = '</sitemapindex>';
		fwrite($gz, $s);
        flock($gz, LOCK_UN);
		fclose($gz);
		@chmod($fname, 0666);
		return 'OK: sitemap index updated';
	}*/


	/*function sitemapNews() {
		//init
        if (empty($this->dirFiles)) {
			return 'Error: not writable directory';
		}
		$fname = $this->dirFiles . 'sitemap-news.xml';
		$gz = fopen($fname, 'w9');
		flock($gz, LOCK_EX);
		$s = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
';
		fwrite($gz, $s);

		// *begin Items
		$page = & moon :: page();
		$homeURL = $page->home_url();
        $sitemap = & moon :: shared('sitemap');
		$homeURL .= ltrim($sitemap->getLink('news'), '/');

		//kategorijos
		fwrite($gz, "\n\n<!-- News Categories -->\n");
		$r = $this->db->query('SELECT uri FROM news_categories WHERE hide=0');
		while ($d = $this->db->fetch_row_assoc($r)) {
			$s = '
	<url><loc>' . $homeURL . htmlspecialchars($d['uri']) . '/</loc>
		<changefreq>never</changefreq>
		<priority>0.4</priority>
	</url>';
			fwrite($gz, $s);
		}

		//naujienos
		fwrite($gz, "\n\n<!-- News -->\n");
		$locale = & moon :: locale();
		$now = floor($locale->now() / 300) * 300;
		$r = $this->db->query('SELECT uri, updated, date FROM news WHERE hide=0 AND date<' . $now);
		$count = $this->db->num_rows($r);
		while ($d = $this->db->fetch_row_assoc($r)) {
			$s = '
	<url><loc>' . $homeURL . htmlspecialchars($d['uri']) . '.htm</loc>
		<lastmod>' . date('c', max($d['date'], $d['updated'])) . '</lastmod>
		<changefreq>never</changefreq>
	</url>';
			fwrite($gz, $s);
		}
		// *end Items

		// Close
		$s = '</urlset>';
		fwrite($gz, $s);
        flock($gz, LOCK_UN);
		fclose($gz);
		@chmod($fname, 0666);
		return 'OK: sitemap-news.xml updated (indexed ' . $count . ' files)';
	}*/


	function sitemapMain() {
		//init
        if (empty($this->dirFiles)) {
			return 'Error: not writable directory';
		}
		$fname = $this->dirFiles . 'sitemap-main.xml';
		$fname = $this->dirFiles . 'sitemap.xml';
		$gz = fopen($fname, 'w9');
		flock($gz, LOCK_EX);
		$s = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
';
		fwrite($gz, $s);

		// *begin Items
		$page = & moon :: page();
		$homeURL = $page->home_url();
        $sitemap = & moon :: shared('sitemap');

		// structure
		$priorities = array('sitemap' => '0.6', 'faq' => '0.6', 'news' => '0.6' );
		fwrite($gz, "\n\n<!-- Site Structure -->\n");
		$r = $this->db->query('SELECT uri, parent_id,page_id FROM pages WHERE is_deleted = 0 AND hide = 0 AND (options & 1 = 0)');
		while ($d = $this->db->fetch_row_assoc($r)) {
			if (isset($priorities[$d['page_id']])) {
				$priority = $priorities[$d['page_id']];
			}
			else {
				$priority = $d['parent_id'] ? '0.7' : '1.0';
			}
			$s = '
	<url><loc>' . $homeURL . htmlspecialchars(ltrim($d['uri'], '/')) . '</loc>
		<changefreq>daily</changefreq>
		<priority>' . $priority . '</priority>
	</url>';
			fwrite($gz, $s);
		}

		// Networks
		$rootURL = $homeURL . ltrim($sitemap->getLink('networks'), '/');
		fwrite($gz, "\n\n<!-- Networks -->\n");
		$r = $this->db->query('SELECT uri FROM rw2_networks WHERE hide=0');
		while ($d = $this->db->fetch_row_assoc($r)) {
			$s = '
	<url><loc>' . $rootURL . htmlspecialchars($d['uri']) . '/</loc>
		<changefreq>monthly</changefreq>
		<priority>0.4</priority>
	</url>';
			fwrite($gz, $s);
		}

		// Reviews
		fwrite($gz, "\n\n<!-- Reviews -->\n");
		$r = $this->db->query('SELECT alias as uri FROM rw2_rooms WHERE is_hidden=0');
		while ($d = $this->db->fetch_row_assoc($r)) {
			$s = '
	<url><loc>' . $homeURL . htmlspecialchars($d['uri']) . '/</loc>
		<changefreq>weekly</changefreq>
		<priority>1.0</priority>
	</url>';
			fwrite($gz, $s);
		}


		// Rules
		$rootURL = $homeURL . ltrim($sitemap->getLink('rules'), '/');
		fwrite($gz, "\n\n<!-- Rules -->\n");
		$r = $this->db->query('SELECT uri FROM games WHERE hide=0');
		while ($d = $this->db->fetch_row_assoc($r)) {
			$s = '
	<url><loc>' . $rootURL . htmlspecialchars($d['uri']) . '.htm</loc>
		<changefreq>monthly</changefreq>
		<priority>0.6</priority>
	</url>';
			fwrite($gz, $s);
		}


		// FreeGames
		$rootURL = $homeURL . ltrim($sitemap->getLink('free-games'), '/');
		fwrite($gz, "\n\n<!-- FreeGames -->\n");
		$r = $this->db->query('SELECT uri FROM games WHERE hide=0');
		while ($d = $this->db->fetch_row_assoc($r)) {
			$s = '
	<url><loc>' . $rootURL . htmlspecialchars($d['uri']) . '.htm</loc>
		<changefreq>monthly</changefreq>
		<priority>0.2</priority>
	</url>';
			fwrite($gz, $s);
		}

		// News
		$rootURL = $homeURL . ltrim($sitemap->getLink('news'), '/');
		fwrite($gz, "\n\n<!-- News -->\n");
		$r = $this->db->query('SELECT id,published,uri FROM articles WHERE is_hidden=0');
		while ($d = $this->db->fetch_row_assoc($r)) {
			$d['uri'] = date('Y', $d['published']) . '/' . date('m', $d['published']) . '/' . $d['uri'] . '-' . (1000 + $d['id']);
			$s = '
	<url><loc>' . $rootURL . htmlspecialchars($d['uri']) . '.htm</loc>
		<changefreq>never</changefreq>
		<priority>0.5</priority>
	</url>';
			fwrite($gz, $s);
		}
		// *end Items

		// Close
		$s = '</urlset>';
		fwrite($gz, $s);
        flock($gz, LOCK_UN);
		fclose($gz);
		@chmod($fname, 0666);
		return 'OK: ' . basename($fname) . ' updated';
	}

}
?>