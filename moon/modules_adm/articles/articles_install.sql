
/*Table structure for table `articles` */

CREATE TABLE `articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` smallint(6) NOT NULL DEFAULT '0',
  `article_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'news, strategy, etc - constant',
  `title` varchar(128) NOT NULL,
  `uri` varchar(60) NOT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `content` longtext COMMENT 'pseudo code',
  `content_html` longtext COMMENT 'html',
  `authors` varchar(30) DEFAULT NULL COMMENT 'id atskirti kableliu',
  `img` varchar(25) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `img_alt` varchar(128) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `published` int(11) unsigned NOT NULL COMMENT 'timestamp',
  `created` int(11) unsigned NOT NULL COMMENT 'timestamp',
  `updated` int(11) NOT NULL COMMENT 'timestamp',
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `is_promo` tinyint(1) NOT NULL DEFAULT '0',
  `room_id` int(11) NOT NULL DEFAULT '0' COMMENT 'for banner rotation',
  `double_banner` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `comm_count` int(10) unsigned NOT NULL DEFAULT '0',
  `comm_last` int(10) unsigned NOT NULL DEFAULT '0',
  `recompile` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `content_type` enum('img','video') DEFAULT NULL,
  `geo_target` int(11) DEFAULT NULL,
  `promo_text` text,
  `has_broken_links` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `promo_box_on` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `homepage_promo` int(1) NOT NULL DEFAULT '0',
  `short_title` varchar(30) NOT NULL DEFAULT '',
  `twitter_text` varchar(140) DEFAULT NULL,
  `facebook_summary` text,
  `views_count` int(11) unsigned DEFAULT '0',
  `twitter_tags` varchar(60) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `i_articles` (`published`,`article_type`,`is_hidden`),
  KEY `uri` (`uri`),
  KEY `search` (`title`,`img_alt`,`published`,`is_hidden`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `articles_attachments` */

CREATE TABLE `articles_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0',
  `layer` tinyint(3) NOT NULL DEFAULT '0',
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `content_type` tinyint(1) NOT NULL DEFAULT '0',
  `options` varchar(20) DEFAULT NULL,
  `file` varchar(150) DEFAULT NULL,
  `thumbnail` varchar(150) DEFAULT NULL,
  `comment` text,
  `created` int(11) NOT NULL DEFAULT '0',
  `updated` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `USER_ID_` (`user_id`),
  KEY `PARENT_` (`parent_id`,`layer`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `articles_authors` */

CREATE TABLE `articles_authors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `nickname` varchar(255) DEFAULT NULL,
  `uri` varchar(60) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `about` text,
  `img` varchar(18) DEFAULT NULL,
  `twitter` varchar(15) NOT NULL,
  `created_on` int(11) NOT NULL DEFAULT '0',
  `duplicates` int(11) NOT NULL DEFAULT '0',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `gplus_url` varchar(60) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `articles_categories` */

CREATE TABLE `articles_categories` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `parent_id` smallint(6) NOT NULL DEFAULT '0',
  `category_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'news, strategy, etc - constant',
  `title` varchar(128) NOT NULL,
  `uri` varchar(60) NOT NULL DEFAULT '',
  `meta_keywords` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `description` text,
  `sort_order` tinyint(1) NOT NULL DEFAULT '0',
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `articles_comments` */

CREATE TABLE `articles_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `obj_id` int(10) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_ip` int(10) unsigned DEFAULT NULL,
  `comment` text NOT NULL,
  `created` int(10) unsigned NOT NULL,
  `spam` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `obj_id` (`obj_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

/*Table structure for table `articles_tags` */

CREATE TABLE `articles_tags` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `is_hidden` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8;