<?php

	define('PDO_STANDARD_TABLE_LOG_STATUS', Log4Me::DEBUG);
	define('PDO_STANDARD_TABLE_ERROR_LOG_STATUS', Log4Me::ERROR); //Log4Me::ERROR

	//define('LOCAL_DIR','/surrealranch/public_html/surrealranch.ca');
	define('LOCAL_DIR','');

	require_once("Log4me.php");

	define('DOC_FOLDER', 'documents');
	
	define('DB_SERVER', 'surrealranch.netfirmsmysql.com');
	define('DB_USERNAME', 'cloud_user'); //cloud_man
	define('DB_PW', 'Power2ThePeople'); //Power2TheMan
	define('DB_NAME', 'cloud');
	
	//define('DB_SERVER', 'localhost');
	//define('DB_USERNAME', 'root');
	//define('DB_PW', 'root');
	//define('DB_NAME', 'cloud');

	Class GZDBConn
	{
		public $server;
		public $username;
		public $pw;
		public $db;
		private $db_conn;
		private $logger;
		
		public function __construct($server, $username, $pw, $db)
		{
			$this->server = $server;
			$this->username = $username;
			$this->pw = $pw;
			$this->db = $db;
			$this->db_conn = NULL;
			
			$this->logger = new Log4Me(Log4me::INFO,"log.txt");
			$this->logger->setContext("GZDBConn", $_SERVER['PHP_SELF']);

		}
		
		public  function __destruct()
		{
			$this->logger->debug("Destructor Called");
			$this->closeConnection();
		}

		//Can specifically close Connection
		public  function closeConnection()
		{
			if(isset($this->db_conn))
			{
				$this->db_conn->close();
				$this->db_conn = NULL;
				$this->logger->debug("Connection Closed");
			}
		}

		public function getConn()
		{
			$this->logger->debug("Get Connection: [" . print_r($this->db_conn,true) . "]");
			return $this->db_conn;
		}

		public function setConn($conn)
		{
			$this->logger->debug("Set Connection: [" . print_r($conn,true). "]");
			$this->db_conn = $conn;
		}
		
	}

?>
