<?php

class Forum extends Scraper
{
	var $log;
	
	function __construct($url)
	{
		$this->log = KLogger::instance();
		
		$this->url = $url;
		
		// Figure out domain part of url.
		$url_parts = parse_url($url);
		$this->domain  = $url_parts['scheme'] . '://';
		$this->domain .= $url_parts['host'];
		$this->domain .= isset($url_parts['port']) ? ':' . $url_parts['port'] : '';
	}
	
	public function scrape()
	{
		// fetch url
		$html = file_get_html($this->url);
		
		// get our list of forums to scrape.
		$page_count = 0;
		foreach($html->find('div.forums-index tr.forum-row td.col-forum a') as $page_link) 
		{
			ForumScraper::instance()->addScrapeObject(new SubForum($this->clean_url($this->domain . trim($page_link->href))));
			$page_count++;
		}
		
		$this->log->logDebug("Found $page_count Sub Forums");
		
		$html->clear();
	    unset($html);
	}
	
	public function persist()
	{
		
	}
}