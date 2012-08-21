<?php

class SubForum extends Scraper
{
	var $log;
	
	var $forum_id;
	var $forum_name;
	var $forum_description;
	
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
		
		// get our list of pages to scrape.
		$forum_pages[] = $this->url;
		// check for > 1 page
		foreach($html->find('div.listing-header ul.paging-list a') as $page_link) 
		{
			$forum_pages[] = $this->clean_url($this->domain . trim($page_link->href));
		}
		$this->log->logDebug('Found ' . count($forum_pages) . ' pages in forum');
		
		$this->forum_name = trim($html->find('div.forum-threads h2.caption span.right', 0)->plaintext);
		$this->forum_description = trim($html->find('div.forum-threads p.sub-title', 0)->plaintext);
		
		foreach($forum_pages as $page_url)
		{
			ForumScraper::instance()->addScrapeObject(new SubForumPage($this, $page_url));
		}
		
		$html->clear();
	    unset($html);
	}
	
	public function persist()
	{
		$db = DB::instance()->db;
		
		$check_query = "SELECT * FROM SubForum WHERE name = '$this->forum_name'";
		$result = $db->query($check_query);
		
		if ($forum = $result->fetchObject())
		{
			$this->forum_id = $forum->id;
		}
		else
		{
			$insert_query = "INSERT INTO SubForum (name, description) VALUES ('$this->forum_name', '$this->forum_description')";
			$db->exec($insert_query);
			$this->forum_id = $db->lastInsertId();
		}
	}
}