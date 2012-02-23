<?php
/**
 * @package livereporting
 */
/**
 * Base class for all livereporting data models
 *
 * Not a descendant of moon_com class, but has access to all it's common attributes,
 * as well as public attributes of ../livereporting component (including $this->db and $this->mcd)
 * @package livereporting
 * @subpackage models
 */
class livereporting_model_pylon
{
	/**
	 * @var livereporting
	 */
	protected $parent = NULL;
	
	/**
	 * Basic constructor
	 * @param livereporting $parent Reference to ../livereporting instance
	 */
	function  __construct($parent)
	{
		$this->parent = $parent;
	}
	
	function &__get($name)
	{
		switch ($name) {
			default:
				$this->$name = $this->parent->$name;
		}
		
		return $this->$name;
	}
	
	function __call($name, $argv) 
	{
		return call_user_func_array(array($this->parent, $name), $argv);
	}
};