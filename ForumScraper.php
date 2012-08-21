<?php

include "lib/KLogger.php";
include "lib/simple_html_dom.php";

include "DB.php";
include "Scraper.php";
include "Forum.php";
include "SubForum.php";
include "SubForumPage.php";
include "ForumPost.php";
include "ForumPostPage.php";
include "Comment.php";
	
class ForumScraper
{
	static $m_instance;
	
	var $forum_url;
	
	private $_scanned_urls;
	private $_scrape_objects;
	
	function __construct($url)
	{
		$this->forum_url = $url;
		$this->_scanned_urls = array();
		$this->_scrape_objects = array();
	}
	
	static function instance($url = null)
	{
		if (!isset(self::$m_instance))
		{
			self::$m_instance = new ForumScraper($url);
		}
		
		return self::$m_instance;
	}
	
	public function startCrawl()
	{
		echo "Begining import of $this->forum_url. Please tail the log file for progess - this may take a while." . PHP_EOL;
		
		$forum = new Forum($this->forum_url);
		$this->addScrapeObject($forum);
		
		// Kick off the crawl.
		$this->scrapeQueue();
		
		echo PHP_EOL . PHP_EOL;
		echo "Finished crawl of $this->forum_url." . PHP_EOL;
		echo 'Crawled a total of ' . count($this->_scanned_urls) . ' urls.' . PHP_EOL;
	}
	
	public function addScrapeObject(Scraper $scraper, $high_priority = false)
	{
		// Only add if we've not scanned it yet.
		if (in_array($scraper->url, $this->_scanned_urls)) return false;
		
		// Only add it if we don't already have it.
		foreach($this->_scrape_objects as $scraperB)
		{
			if ($scraper->url == $scraperB->url) return false;
		}
			
		if ($high_priority)
		{
			array_unshift($this->_scrape_objects, $scraper);
		}
		else
		{
			$this->_scrape_objects[] = $scraper;
		}
			
		return true;

	}
	
	public function scrapeQueue()
	{
		$queue_count = 0;
		do
		{
			echo '.';
			
			$object = array_shift($this->_scrape_objects);
			if ($object)
			{
				KLogger::instance()->logInfo("Scraping $object->url");
				$object->scrape();
				$object->persist();
				
				$this->_scanned_urls[] = $object->url;
			}
			
			$queue_count = count($this->_scrape_objects);
		} while($queue_count > 0);
	}
}

$default_opts = array(
  'http'=>array(
    'proxy'=>'tcp://localhost:8228',
    'request_fulluri' => true,
	'user_agent'=>'RSSCreator/2.0'
  )
);

$default = stream_context_set_default($default_opts);

$log = KLogger::instance('logs', KLogger::DEBUG);
$db = DB::instance();

$url = 'http://www.janedoe.eu/forums';

ForumScraper::instance($url)->startCrawl();