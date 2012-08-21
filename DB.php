<?php

class DB
{
	static $m_instance;
	
	var $db;
	
	function __construct()
	{
		$this->db = new PDO('sqlite:db/export.sqlite');
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$this->_checkExistant();
	}	
	
	static function instance()
	{
		if (self::$m_instance == null)
			self::$m_instance = new DB();
			
		return self::$m_instance;
	}
	
	private function _checkExistant()
	{
		$check = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' and name='User'"); 
		
		if (count($check->fetchall()) < 1)
		{ 
			$query = "CREATE TABLE User (id INTEGER PRIMARY KEY,
					name CHAR(255),
					class CHAR (255));
				CREATE TABLE SubForum (id INTEGER PRIMARY KEY,
					name CHAR(255),
					description CHAR(255));
				CREATE TABLE ForumPost (id INTEGER PRIMARY KEY,
				    url CHAR(512),
					forum INTEGER,
					author INTEGER,
					title CHAR(255),
					type CHAR(255),
					view_count INTEGER,
					time CHAR(255));
				CREATE TABLE Comment (id INTEGER PRIMARY KEY,
				    post INTEGER,
					comment_index INTEGER,
					author INTEGER,
					comment TEXT,
					time CHAR(255),
					op INTEGER);";

			$this->db->beginTransaction();
			$this->db->exec($query);
			$this->db->commit();
		}
	}
}