<?php
class shared_countries{
var $tpl;//sablonu klase
var $countries = array();

function shared_countries($path)
{
	$moon=&moon::engine();
	$this->tpl=&$moon->load_template($path.'shared_countries.htm');

	$this->countries = $this->tpl->parse_array('countries');
	$this->init();
}

function init()
{
}
//***** PAGRINDINES FUNKCIJOS *****
function getCountries()
{
	return $this->countries;
}

function getCountry($alias)
{
	$alias=strtolower($alias);
	if(array_key_exists($alias, $this->countries))
		return $this->countries[$alias];
	return '';
}

}
?>