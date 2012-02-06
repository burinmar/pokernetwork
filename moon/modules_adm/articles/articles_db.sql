
/*Table structure for table `articles` */

CREATE TABLE `articles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` SMALLINT(6) NOT NULL DEFAULT '0',
  `article_type` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'news, strategy, etc - constant',
  `title` VARCHAR(128) NOT NULL,
  `uri` VARCHAR(60) NOT NULL,
  `meta_keywords` VARCHAR(255) DEFAULT NULL,
  `meta_description` VARCHAR(255) DEFAULT NULL,
  `summary` VARCHAR(255) DEFAULT NULL,
  `content` LONGTEXT COMMENT 'pseudo code',
  `content_html` LONGTEXT COMMENT 'html',
  `authors` VARCHAR(30) DEFAULT NULL COMMENT 'id atskirti kableliu',
  `img` VARCHAR(25) CHARACTER SET ASCII COLLATE ascii_bin DEFAULT NULL,
  `img_alt` VARCHAR(128) NOT NULL,
  `tags` VARCHAR(255) DEFAULT NULL,
  `published` INT(11) UNSIGNED NOT NULL COMMENT 'timestamp',
  `created` INT(11) UNSIGNED NOT NULL COMMENT 'timestamp',
  `updated` INT(11) NOT NULL COMMENT 'timestamp',
  `is_hidden` TINYINT(1) NOT NULL DEFAULT '0',
  `is_promo` TINYINT(1) NOT NULL DEFAULT '0',
  `room_id` INT(11) NOT NULL DEFAULT '0' COMMENT 'for banner rotation',
  `double_banner` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `comm_count` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `comm_last` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `recompile` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `content_type` ENUM('img','video') DEFAULT NULL,
  `geo_target` INT(11) DEFAULT NULL,
  `promo_text` TEXT,
  `has_broken_links` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `promo_box_on` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `homepage_promo` INT(1) NOT NULL DEFAULT '0',
  `short_title` VARCHAR(30) NOT NULL DEFAULT '',
  `twitter_text` VARCHAR(140) DEFAULT NULL,
  `facebook_summary` TEXT,
  `views_count` INT(11) UNSIGNED DEFAULT '0',
  `twitter_tags` VARCHAR(60) NOT NULL DEFAULT '',
  `is_turbo` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `turbo_sent_cnt` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
  `turbo_ic_message_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
  `turbo_ic_spam_score` DECIMAL(3,1) NOT NULL DEFAULT '0.0',
  `turbo_ic_spam_info` TEXT,
  PRIMARY KEY (`id`),
  KEY `i_articles` (`published`,`article_type`,`is_hidden`),
  KEY `uri` (`uri`),
  KEY `search` (`title`,`img_alt`,`published`,`is_hidden`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8;

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
  `uri` VARCHAR(128) NOT NULL,
  `description` TEXT NOT NULL,
  `is_hidden` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8;

/*Table structure for table `articles_turbo` */

CREATE TABLE `articles_turbo` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `parent_id` INT(11) NOT NULL,
  `title` VARCHAR(128) NOT NULL,
  `content` LONGTEXT COMMENT 'pseudo code',
  `content_html` LONGTEXT COMMENT 'html',
  `created` INT(11) UNSIGNED NOT NULL COMMENT 'timestamp',
  `updated` INT(11) NOT NULL COMMENT 'timestamp',
  `user_id` INT(11) NOT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8;

/*Table structure for table `articles_turbo_attachments` */

CREATE TABLE `articles_turbo_attachments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL DEFAULT '0',
  `layer` TINYINT(3) NOT NULL DEFAULT '0',
  `parent_id` INT(11) NOT NULL DEFAULT '0',
  `content_type` TINYINT(1) NOT NULL DEFAULT '0',
  `options` VARCHAR(20) DEFAULT NULL,
  `file` VARCHAR(150) DEFAULT NULL,
  `thumbnail` VARCHAR(150) DEFAULT NULL,
  `comment` TEXT,
  `created` INT(11) NOT NULL DEFAULT '0',
  `updated` INT(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `USER_ID_` (`user_id`),
  KEY `PARENT_` (`parent_id`,`layer`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8;
