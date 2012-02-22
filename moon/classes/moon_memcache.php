<?php
if (!class_exists('Memcache', false)) {
define('MEMCACHE_PREFER_IGNORE', 1);
class Memcache {
	public function __call($name, $arguments) 
	{
		return FALSE;
	}
}}

class moon_memcache extends Memcache 
{
	private static $instance;
	protected $hits = 0;
	protected $misses = 0;
	
	public static function getInstance()
	{
		if (NULL !== self::$instance) {
			return self::$instance;
		}
		
		self::$instance = new moon_memcache;
		
		if (defined('MEMCACHE_PREFER_IGNORE')) {
			// do nothing, log nothing
			$connected = true;
		} elseif (is_dev()) {
			$connected = @self::$instance->pconnect('192.168.0.12', 11211);
		} elseif (in_array(_SITE_ID_, array('com', 'br', 'china', 'kr', 'nj', 'tw', 'jp', 'asia'))) {
			$connected = self::$instance->pconnect('memcache-ha', 11211);
		} else {
			$connected = self::$instance->pconnect('euweb-2-ha', 11211);
		}
		
		if (false == $connected) {
			moon::error('Memcached inaccessible');
		}

		// { debug
		//register_shutdown_function(array(self::$instance, 'shutdown'));
		// }

		return self::$instance;
	}
	
	public static function getRecommendedPrefix()
	{
		static $prefix;
		if (!isset($prefix)) {
			$prefix = !is_dev()
				? _SITE_ID_ . '_mtpl'
				: _SITE_ID_ . '_mtpl' . md5(php_uname() . phpversion());
		}
		return $prefix;
	}
	
	protected function __construct() {}
	
	// { debug
	//public function get($name) 
	//{
	//	$result = parent::get($name);
	//	if (false == $result) {
	//		$this->misses++;
	//	} else {
	//		$this->hits++;
	//	}
	//}
	//
	//public function shutdown() 
	//{
	//	if ($this->misses) {
	//		if (false === parent::get(_SITE_ID_ . 'mcd_misses')) {
	//			parent::set(_SITE_ID_ . 'mcd_misses', 0);
	//		}
	//		parent::increment(_SITE_ID_ . 'mcd_misses', $this->misses);
	//	}
	//	if ($this->hits) {
	//		if (false === parent::get(_SITE_ID_ . 'mcd_hits')) {
	//			parent::set(_SITE_ID_ . 'mcd_hits', 0);
	//		}
	//		parent::increment(_SITE_ID_ . 'mcd_hits', $this->hits);
	//	}
	//}
	// }
}