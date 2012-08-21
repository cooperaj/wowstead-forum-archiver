<?php

class ForumPost extends Scraper
{
	const PT_ANNOUNCE = 'announcement';
	const PT_PINNED = 'pinned';
	const PT_NORMAL = 'typical';
	
	var $log;
	
	var $parent_forum;
	
	var $post_type;
	
	var $post_id;
	var $post_title;
	var $post_author;
	var $post_time;
	var $post_view_count;
	
	var $post_pages;
	
	function __construct(SubForum $sub_forum, $url, $author, $title, $type, $view_count)
	{
		$this->log = KLogger::instance();
		
		$this->parent_forum = $sub_forum;
		
		$this->url = $url;
		$this->post_author = $author;
		$this->post_title = $title;
		$this->post_type = $type;
		$this->post_view_count = $view_count;
		
		// Figure out domain part of url.
		$url_parts = parse_url($url);
		$this->domain  = $url_parts['scheme'] . '://';
		$this->domain .= $url_parts['host'];
		$this->domain .= isset($url_parts['port']) ? ':' . $url_parts['port'] : '';
		
		$this->post_pages = array();
	}
	
	public function scrape()
	{
		// fetch url
		$html = file_get_html($this->url);
		
		// get our list of pages to scrape.
		// check for > 1 page
		foreach($html->find('div.listing-header ul.paging-list a') as $page_link) 
		{
			$this->post_pages[] = $this->clean_url($this->domain . trim($page_link->href));
		}
		
		$this->log->logDebug(" Found " . (count($this->post_pages) + 1) . ' pages in post ');
		
		// Process this page immediately using the html we already have.
		$fpp = new ForumPostPage($this, $this->url, true);
		$fpp->scrape($html);
		$fpp->persist();
		
		foreach($this->post_pages as $page_url)
		{
			ForumScraper::instance()->addScrapeObject(new ForumPostPage($this, $page_url));
		}
		
		$html->clear();
	    unset($html);
	}
	
	public function persist()
	{
		// This does nothing here.
	}
}