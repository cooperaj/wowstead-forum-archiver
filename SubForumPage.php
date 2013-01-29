<?php

class SubForumPage extends Scraper
{
	var $log;
	
	var $sub_forum;
	
	function __construct(SubForum $sub_forum, $url)
	{
		$this->log = KLogger::instance();
		
		$this->url = $url;
		$this->sub_forum = $sub_forum;
		
		// Figure out domain part of url.
		$url_parts = parse_url($url);
		$this->domain  = $url_parts['scheme'] . '://';
		$this->domain .= $url_parts['host'];
		$this->domain .= isset($url_parts['port']) ? ':' . $url_parts['port'] : '';
	}
	
	public function scrape()
	{
		$this->log->logDebug("Processing forum page $this->url");
		
		// fetch url
		$html = file_get_html(preg_replace('/#forum-threads/i', '', $this->url));
		
		// The pagiation changes if more then a certain number of pages exists. So
		// do a scan and add any we've missed.
		$this->_findExtraPages($html);
		
		foreach($html->find('tr.forum-thread-row') as $post)
		{
			$title = trim($post->find('span.thread-title a', 0)->plaintext);
			
			$this->log->logDebug("Processing thread $title");
			
			// Broken forum items need to be checked for
			$author = $post->find('div.thread-author span', 0);
			if (isset($author))
			{
				$clean_url = $this->clean_url($this->domain . 
					trim($post->find('span.thread-title a', 0)->href));
				$author = trim($author->plaintext);
				$view_count = trim($post->find('td.col-count', 1)->plaintext);
				
				$last_update = DateTime::createFromFormat('D, j M Y H:i:s e', trim($post->find('td.col-last-post div.post-date abbr', 0)->title));
				
				$type = ForumPost::PT_NORMAL;
				if (stristr($post->class, 'forum-thread-row-announcement')) $type = ForumPost::PT_ANNOUNCE;
				if (stristr($post->class, 'forum-thread-row-pinned')) $type = ForumPost::PT_PINNED;

                if ($this->_isUpdated($clean_url, $last_update))
				    ForumScraper::instance()->addScrapeObject(
					    new ForumPost($this->sub_forum, $clean_url, $author, $title, $type, $view_count));
			}
		}
		
		$html->clear();
	    unset($html);
	}
	
	public function persist()
	{
		// This doesnt need to persist anything.
	}
	
	private function _findExtraPages($html)
	{
		$page_count = 0;
		
		foreach($html->find('div.listing-header ul.paging-list a') as $page_link) 
		{
			$clean_url = $this->clean_url($this->domain . 
				trim($page_link->href));
			$added = ForumScraper::instance()->addScrapeObject(new SubForumPage($this->sub_forum, $clean_url), true);
			
			if ($added) $page_count++;
		}
		
		if ($page_count > 0)
			$this->log->logDebug("Added $page_count new pages to the forum");
	}
	
	private function _isUpdated($url, $time)
	{
	    $db = DB::instance()->db;
	    
	    // Do SQL query to figure out this.
	    $check_query = "SELECT time FROM ForumPost WHERE url ='$url'";
		
		$result = $db->query($check_query);
		if ($post = $result->fetchObject())
		{
		    if ($post->time > $time)
			    return true;
			    
			// Post in database and not updated.
			return false;    
	    }
	    
	    // Post not in database so needs update
	    return true;
	}
}
