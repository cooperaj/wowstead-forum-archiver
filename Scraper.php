<?php

abstract class Scraper 
{
	var $url;
	var $domain;
	
	abstract public function scrape();
	abstract public function persist();
	
	public function clean_url($url_in = '')
	{
		if ($url_in == '') $url_in = $this->domain . $this->url;
		
		$clean_url = htmlspecialchars_decode($url_in);
		$clean_url = preg_replace('#cookieTest=1[\&]?#', '', $clean_url);
		$clean_url = preg_replace('#\?$#', '', $clean_url);
		
		return $clean_url;
	}
}