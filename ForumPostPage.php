<?php

class ForumPostPage extends Scraper
{
	var $log;
	
	var $forum_post;
	var $first;
	
	function __construct(ForumPost $forum_post, $url, $first = false)
	{
		$this->log = KLogger::instance();
		
		$this->forum_post = $forum_post;
		$this->first = $first;
		
		$this->url = $url;
		
		// Figure out domain part of url.
		$url_parts = parse_url($url);
		$this->domain  = $url_parts['scheme'] . '://';
		$this->domain .= $url_parts['host'];
		$this->domain .= isset($url_parts['port']) ? ':' . $url_parts['port'] : '';
		
		$this->post_comments = array();
	}
	
	public function scrape($html = false)
	{
		// fetch url if needed
		$cleanup = true;
		if (!$html)
		{
			$cleanup = false;
			$html = file_get_html($this->url);
		}
		
		// The pagiation changes if more then a certain number of pages exists. So
		// do a scan and add any we've missed.
		$this->_findExtraPages($html);
		
		foreach($html->find('ul#comments li div.forum-post') as $post) {
			$comment = new Comment();
			
			$index = $post->find('span.post-index a', 0);
			if (!$index)
			{
			    $this->log->logError("Error parsing: $post");
			}
			else
			{
			    $comment->comment_index = str_replace('#', '', trim($index->plaintext));
    			$comment->comment_author = trim($post->find('div.forum-post-author div.username span', 0)->plaintext);
    			$comment->comment = trim($post->find('div.forum-post-body-content', 0)->innertext);
    			$comment->isOp = false;

    			$comment->author_class = 'unknown';
    			$class = $post->find('div.forum-post-author div.username span', 0)->class;
    			$matches = array();
    			$found = preg_match('#character-class-wow-([\w]+)#', $class, $matches);
    			if ($found)
    			    $comment->author_class = $matches[1];

    			$date = DateTime::createFromFormat('D, j M Y H:i:s e', trim($post->find('span.post-date abbr', 0)->title));
    			$comment->comment_time = $date->format('U');

    			$this->post_comments[] = $comment;
			}
		}
		
		// If we enter this function with a passed in object we don't necessarily 
		// want to be doing any cleanup on it as it might interfere with the function
		// that called it.
		if ($cleanup)
		{
			$html->clear();
	    	unset($html);
		}
	
		if ($this->first)
			$this->post_comments[0]->isOp = true;
	}
	
	public function persist()
	{
		// authors first since we need to reference them in both the comments and post.
		// also manipulates the comment data to include any new id's
		$this->_persistAuthors();
		
		// then the post information.
		$this->_persistPost();
		
		// finally all the comments on that post.
		$this->_persistComments();
	}
	
	private function _persistAuthors()
	{
		// iterate through comments making author list.
		$authors = array();
		foreach($this->post_comments as $comment)
		{
			$authors[$comment->comment_author] = $comment->author_class;
		}
		
		// add to db if needed.
		$db = DB::instance()->db;
		
		$saved_count = 0;
		
		foreach($authors as $author => $class)
		{
			$check_query = "SELECT * FROM User WHERE name = '$author'";

			$result = $db->query($check_query);
			if ($obj = $result->fetchObject())
			{
				$authors[$author] = $obj->id;
			}
			else
			{
				$insert_query = "INSERT INTO User (name, class) VALUES ('$author', '$class')";
				$db->exec($insert_query);
				$authors[$author] = $db->lastInsertId();
				
				$saved_count++;
			}
		}
		
		// replace author in comment with id
		foreach($this->post_comments as $comment)
		{
			$comment->comment_author = intval($authors[$comment->comment_author]);
		}
		
		$this->log->logDebug("   Found and saved $saved_count distinct new author/s");
	}
	
	private function _persistPost()
	{
		// For ease
		$fp = $this->forum_post;
		
		// Some data comes from the first comment. Populate this first
		if ($this->first)
		{
			$fp->post_author = $this->post_comments[0]->comment_author;
			$fp->post_time = $this->post_comments[0]->comment_time;
		}
		
		$db = DB::instance()->db;
		
		$forum_id = $fp->parent_forum->forum_id;
		$check_query = "SELECT * FROM ForumPost WHERE forum = $forum_id AND title = '$fp->post_title'";
		
		$result = $db->query($check_query);
		if ($post = $result->fetchObject())
		{
			$fp->post_id = $post->id;
		}
		else
		{
			$stmt = $db->prepare(
				"INSERT INTO ForumPost (forum, url, author, title, type, view_count, time) VALUES (?, ?, ?, ?, ?, ?, ?);");
			
			$stmt->execute(array(
				$forum_id, 
				$this->url, 
				$fp->post_author,
				$fp->post_title,
				$fp->post_type,
				str_replace(',', '', $fp->post_view_count), 
				$fp->post_time
			));
			
			$fp->post_id = $db->lastInsertId();
			
			$this->log->logDebug("   Saved post with id $fp->post_id");
		}
	}
	
	private function _persistComments()
	{
		// For ease
		$fp = $this->forum_post;
		
		$db = DB::instance()->db;
		$saved_count = 0;
		
		foreach ($this->post_comments as $comment)
		{
			$check_query = "SELECT * FROM Comment WHERE post = $fp->post_id AND comment_index = $comment->comment_index";

			$result = $db->query($check_query);
			if (!$result->fetchObject())
			{
				$stmt = $db->prepare(
					"INSERT INTO Comment (post, comment_index, author, comment, time, op) VALUES (?, ?, ?, ?, ?, ?);");
					
				$stmt->execute(array(
					$fp->post_id, 
					$comment->comment_index, 
					$comment->comment_author, 
					$comment->comment,
					$comment->comment_time, 
					$comment->isOp
				));
				
				$saved_count++;
			}
		}
		
		$this->log->logDebug("   Saved $saved_count comment/s");
	}
	
	private function _findExtraPages($html)
	{
		$page_count = 0;
		
		foreach($html->find('div.listing-header ul.paging-list a') as $page_link) 
		{
			$clean_url = $this->clean_url($this->domain . 
				trim($page_link->href));
			$added = ForumScraper::instance()->addScrapeObject(new ForumPostPage($this->forum_post, $clean_url), true);
			
			if ($added) $page_count++;
		}
		
		if ($page_count > 0)
			$this->log->logDebug(" Added $page_count new pages to the post");
	}
}