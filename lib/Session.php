<?php
	require_once("Base.php");
	require_once("UUID.php");
	require_once("User.php");

	Class GZSessionException extends Exception
	{
		const NO_ERROR = 0;
		const COOKIE_KEY_NOT_VALID = 1;
		const NO_COOKIE = 2;
		const SESSION_KEY_NOT_VALID = 3;
		
		const DATA_BASE_ERROR = 99;
		
		function __construct($message, $code)
		{
			parent::__construct($message, $code); 
		}
	}

	Class GZSession extends GZField
	{
		const USER_ID = 'user_id';
		const UUID_KEY = 'key';
		const START = 'start';
		const LAST_ACCESS = 'last_access';
		const SESSION_KEY = 'session_key';
		const COOKIE_NAME = '__gzmo';
		public function __construct($usir_id = NULL)
		{
			parent::__construct();
			if(!is_null($usir_id))
			{
				$time = time();
				$this->set(self::USER_ID,$usir_id);
				$this->set(self::UUID_KEY,UUID::v4());
				$this->set(self::START,$time);
				$this->set(self::LAST_ACCESS,$time);
			}
		}
		
		public function getKey()
		{
			return $this->get(self::UUID_KEY);
		}
		
	}
	
	Class GZSessionsWrapper
	{
		private $dbConn;
		private $sessions;
		private $fileName;
		private $user_id;
		private $user;
		private $users;
		private $filePath;
		private $logger;
		
		public function __construct($dbConn)
		{
			$this->dbConn = $dbConn;
			$session_dao = new GZMYSQLSessionDAO($dbConn);
			$this->sessions = new GZSessions($session_dao);
			$this->host  = $_SERVER['HTTP_HOST'] . LOCAL_DIR;
			$this->fileName = $_SERVER['PHP_SELF'];

			$this->logger = new Log4Me(Log4me::DEBUG,"log.txt");
			$this->logger->setContext("GZSessionsWrapper: ", $_SERVER['PHP_SELF']);

			//$this->logger->debug("this->fileName [" . $this->fileName . "]");

		}
		
		public function getLogOutURL()
		{
			return $this->host . "/cloud/Credentials.php?target=" . $this->fileName . "&action=logout";
		}
		
		public function getUsers()
		{
			return $this->users;
		}
		
		public function getUserId()
		{
			return $this->user_id;
		}

		public function validate()
		{
			try
			{
				$session = $this->sessions->checkStatus();
				$this->user_id = $session->get(GZSession::USER_ID);
				//Create a new GZMYSQLUserDAO using the current connection
				$user_dao = new  GZMYSQLUserDAO($this->dbConn);
				$this->user = $user_dao->getFieldById($this->user_id);
				$this->users = new GZUsers($user_dao);
				return $this->user;
			}
			catch(GZSessionException $gzex)
			{
				switch($gzex->getCode())
				{
					case GZSessionException::NO_COOKIE:
					{
						$str = ""; 
						break;
					}
					default:
					{
						$str = urlencode($gzex->getMessage());	
					}
				}
				$path = $this->host . "/cloud/Credentials.php?target=" . $this->fileName . "&action=showform&error=" . $str;
				//$this->logger->debug($path);
				header("Location: http://$path");
				exit;
			}
			catch(Exception $ex)
			{
				$str = urlencode($ex->getMessage());
				$path = $this->host . "/cloud/Credentials.php?target=" . $this->fileName . "&action=showform&error=" . $str;
				//$this->logger->debug($path);
				header("Location: http://$path");
				exit;
			}
		}
	}
	
	Class GZSessions extends GZFields
	{
		private $db_err_msg = "";
		public function __construct($in_dao, $isLoggedIn=false)
		{
 			if(strlen(LOCAL_DIR) == 0)
			{
				session_save_path("/home/users/web/b2030/nf.surrealranch/cgi-bin/tmp");
			}
			session_start();
			parent::__construct($in_dao,$isLoggedIn);
		}
		
		public function getErrMsg()
		{
			return $this->db_err_msg;
		}
		
		
		//Returns GZUser on SUCCESS
		//NULL on Failure
		public function statusHelper()
		{
			try
			{
				$session = $this->checkStatus();
				$user_id = $session->get(GZSession::USER_ID);
				
				//Create a new GZMYSQLUserDAO using the current connection
				$user_dao = new  GZMYSQLUserDAO($this->dao->getDbConn());
				//$users = new GZUsers($dao);
				return $user_dao->getFieldById($user_id);
			}
			catch(Exception $ex)
			{
				$this->db_err_msg = $ex->getMessage();
				return NULL;
			}
		}
		
		public function ajaxCheck()
		{
			$user_id = NULL;
			try
			{
				$session = $this->checkStatus();
				$user_id = $session->get(GZSession::USER_ID);
			}
			catch(GZSessionException $gzex)
			{
			}
			catch(Exception $ex)
			{
			}
			return $user_id;
		}

		public function simpleCheck($host, $page)
		{
			$user_id = NULL;
			try
			{
				$session = $this->checkStatus();
				$user_id = $session->get(GZSession::USER_ID);
			}
			catch(GZSessionException $gzex)
			{
				switch($gzex->getCode())
				{
					case GZSessionException::NO_COOKIE:
					{
						$str = ""; 
						break;
					}
					default:
					{
						$str = urlencode($gzex->getMessage());	
					}
				}
				$path = $host . "/cloud/Credentials.php?target=" . $page . "&action=showform&error=" . $str;
				header("Location: http://$path");
				exit;
			}
			catch(Exception $ex)
			{
				$str = urlencode($ex->getMessage());
				$path = $host . "/cloud/Credentials.php?target=" . $page . "&action=showform&error=" . $str;
				header("Location: http://$path");
				exit;
			}
			return $user_id;
		}

		//Throws:
		//	GZSessionException::COOKIE_KEY_NOT_VALID
		//	GZSessionException::NO_COOKIE
		//	GZSessionException::SESSION_KEY_NOT_VALID
		public function checkStatus()
		{
			$logger = new Log4Me(Log4Me::INFO,"log.txt");
			$logger->setContext("GZSessions checkLogin", $_SERVER['PHP_SELF']);

			$isCookie = false;
			if(isset($_SESSION[GZSession::SESSION_KEY]))
			{
				$key = $_SESSION[GZSession::SESSION_KEY];
				$logger->debug(" Session key [" . $key . "]");
			}
			else
			{
				if(isset($_COOKIE[GZSession::COOKIE_NAME])) {
					$key = $_COOKIE[GZSession::COOKIE_NAME];
					$logger->debug(" Cookie key [" . $key . "]");
					if (count($key)) {
						if (!UUID::is_valid($key)) {
							$str = "The Cookie Key: " . $key . " is not valid!";
							$logger->debug($str);
							throw new GZSessionException($str, GZSessionException::COOKIE_KEY_NOT_VALID);
						}
						$isCookie = true;
					} else {
						$str = "No Cookie Available!";
						$logger->debug($str);
						throw new GZSessionException($str, GZSessionException::NO_COOKIE);
					}
				}
				else{
					$str = "No Cookie Available!";
					$logger->debug($str);
					throw new GZSessionException($str, GZSessionException::NO_COOKIE);
				}
			}
			try
			{
				$logger->debug(" CALL getFieldByName");
				$session = $this->getFieldByName(GZSession::UUID_KEY,$key);
				$logger->debug(" SUCCESS user id[" . $session->get(GZSession::USER_ID) . "]");
				$logger->debug(" CALL updateSession");
				$this->updateSession($session);
				$logger->debug(" LAST_ACCESS[" . $session->get(GZSession::LAST_ACCESS) . "]");
				if($isCookie)
				{
					//Now add the key to a PHP session
					$_SESSION[GZSession::SESSION_KEY] = $key;
				}
				return $session;
			}
			catch(Exception $ex)
			{
				$str = "No longer logged in.";
				if($isCookie)
				{
					//Now remove invalid cookie
					$logger->debug("Now remove invalid cookie");
					if(setcookie(GZSession::COOKIE_NAME,'goofy',time() - 42000))
					{
						$logger->debug("SUCCESS");
					}
					else
					{
						$logger->debug("FAIL");
					}
					$logger->debug("CALL destroySession");
					$this->destroySession();
					$logger->debug("SUCCESS destroySession");

				}
				else
				{
					//End invalid session
					$logger->debug("End invalid session");
					$this->destroySession();
				}
				$logger->debug($str);
				throw new GZSessionException($str,GZSessionException::SESSION_KEY_NOT_VALID);
			}
		}
		
		//NOTE $session will be updated Class objects always passed by reference?
		public function updateSession($session)
		{
			$time = time();
			$session->set(GZSession::LAST_ACCESS,$time);
			$temp = $session->cloneEmpty();
			$temp->set(GZSession::LAST_ACCESS,$time);
			$this->updateField($temp);
		}
		
		//Returns new Session object
		public function addNewSession($user_id, $keep)
		{
			$session = new GZSession($user_id);
			$id = $this->addNewField($session);
			$session->setPrimary($this->primaryKeyName(),$id);
			$key = $session->getKey();
			$_SESSION[GZSession::SESSION_KEY] = $key;
			$days = 14;
			if($keep == "on")
			{
				$t = time()+60*60*24*$days; //Two weeks
				//Write cookie
				setcookie(GZSession::COOKIE_NAME,$key,$t);
			}
			return $session;
		}
		
		private function destroySession()
		{
			// Unset all of the session variables.
			$_SESSION = array();
			// Delete the session cookie.
			if (ini_get("session.use_cookies")) {
			    $params = session_get_cookie_params();
			    setcookie(session_name(), '', time() - 42000,
			        $params["path"], $params["domain"],
			        $params["secure"], $params["httponly"]
			    );
			}
			session_destroy();
		}
		
		public function removeSession()
		{
			$key = $_SESSION['session_key'];
			if(UUID::is_valid($key))
			{
				//remove from database
				$this->dao->removeSession($key);
				
				//clear keep logged on cookie
				setcookie(GZSession::COOKIE_NAME,'',time() - 42000);
				
				$this->destroySession();
			}
			else
			{
				throw new GZInvalidArgumentException("Incorrect format for: ", "session key[" . $key . "]");
			}
		}
	}
	
	class GZMYSQLSessionDAO extends GZMYSQLFieldsDAO
	{
		public function __construct(GZDBConn $dbConn)
		{
			parent::__construct($dbConn, "SESSION");
		}
		
		public function removeSession($key)
		{
			$sql = "DELETE FROM `" . $this->table_name . "` WHERE `key` = '" . $key . "' LIMIT 1";
			//execute query
			if($this->query($sql) === TRUE)
			{
				return TRUE; //TODO redundant eh
			}
			else
			{
				$errno = $this->conn->errno; 
				$errmsg = $this->conn->error;
				throw new GZDatabaseException("Error : for (" . $sql . ") Failed ",$errno,$errmsg);
			}
		}

	}

/*
$sql = 'CREATE TABLE `SESSION` ( `session_id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT, `key` VARCHAR( 40 ) NOT NULL , `user_id` SMALLINT UNSIGNED NOT NULL , `start` TIMESTAMP NOT NULL , `last_access` TIMESTAMP NOT NULL , PRIMARY KEY ( `session_id` ) , UNIQUE ( `key` ) ) COMMENT = \'Session Table Version 1\'; ';
*/
?>