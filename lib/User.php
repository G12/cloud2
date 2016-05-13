<?php

	require_once("Base.php");
		
	Class GZLoginException extends Exception
	{
		const NO_ERROR = 0;
		const UNKNOWN_USER = 1;
		const BAD_PSWD = 2;
		const USER_NOT_CONFIRMED = 3;
		const NON_UNIQUE_EMAIL = 4;
		const DATA_BASE_ERROR = 99;
		
		function __construct($message, $code)
		{
			parent::__construct($message, $code); 
		}
	}

	Class GZUser extends GZField
	{
		const NAME = 'name';
		const PSWD = 'pswd';
		const EMAIL = 'email';
		const QUESTION = 'question';
		const ANSWER = 'answer';
		const SECURITYLEVEL = 'securitylevel';
		const START_DATE = 'start_date';
		const CONFIRM_KEY = 'confirm_key';
		const MODS = 'mods';
		
		public function __construct()
		{
			parent::__construct();
		}

		public function set($name,$value)
		{
			if($name == "pswd")
			{
				$value = md5($value);
			}
			parent::set($name,$value);
		}

	}
	
	Class GZUsers extends GZFields
	{
		public function __construct($in_dao, $isLoggedIn=false)
		{
			parent::__construct($in_dao,$isLoggedIn);
		}
		
		//Throws GZLoginException
		public function login($email, $pswd)
		{
			$user = $this->dao->login($email, $pswd);
			if($user->get('securitylevel'))
			{
				return $user;
			}
			else
			{
				$message = "Email confirmation for " . $user->get('name') . " has not been received!";
				throw new GZLoginException($message,GZLoginException::USER_NOT_CONFIRMED);
			}
		}
		
		//public function addNewField($field)
		//{
		//	$field->set('pswd',md5($field->get('pswd')));
		//	parent::addNewField($field);
		//}
		
		public function processConfirmationKey($confirmcode)
		{
			try
			{
				$user = $this->getFieldByName('confirm_key',$confirmcode);
				$clone = $user->cloneEmpty();
				$clone->set('confirm_key',"0");
				$clone->set('securitylevel',1);
				$this->updateField($clone);
				return true;
			}
			catch(Exception $ex)
			{
				return false;
			}
		}
		
		//SELECT  * , p.path FROM  `IMAGES`  INNER  JOIN  `PATHS` p USING ( path_id )  WHERE  `author_id`  = 97 ORDER  BY  `image_id` 
		
		public function makeAnchorList($user_id, $profileHref, $editHref, $viewHref, $isDesc, $from = null,$to = null)
		{
			$this->dao->isDescending($isDesc);
			$this->dao->selectFields($from,$to);
			
			//$meta = $this->fieldsMetaData();
			//$nameCount = count($meta);
			$count = $this->dao->getCount();
			
			echo '<ul id="member_list">'; 
			for ($i = 0; $i < $count; $i++)
			{
				$obj = $this->dao->getObjectByIndex($i);
				//TODO Hack
				if($obj->get(GZUser::NAME) != "admin")
				{
					echo '<li>&nbsp;</li>';
					echo '<li id="li_' . $obj->getPrimary() . '" >' . $obj->get(GZUser::NAME);
						echo '<ul>';
							if($user_id == $obj->getPrimary())
							{
								echo '<li><a href="' . $editHref . '&id=' . $obj->getPrimary() . '">Edit Images</a></li>';
								echo '<li><a href="' . $profileHref . '?id=' . $obj->getPrimary() . '">Edit Profile</a></li>';
							}
							echo '<li><a href="' . $viewHref . '&id=' . $obj->getPrimary() . '">View Images</a></li>';
						echo '</ul>';					
					echo '</li>';
				}
			}
			echo '</ul>';
		}
	}
	
	class GZMYSQLUserDAO extends GZMYSQLFieldsDAO
	{
		private $errMsg;
		public function __construct(GZDBConn $dbConn)
		{
			$table_name = "USERS";
			parent::__construct($dbConn, $table_name);
		}
		
		public function login($email, $pswd)
		{
			$sql = "SELECT * FROM `" . $this->table_name . "` WHERE `email` = '" . $email . "'";
			$result = $this->query($sql);
			if($result === FALSE)
			{
				//TODO tighten up error messages
				$errno = $this->conn->errno; 
				$errmsg = $this->conn->error;
				$message = "ERROR: " . $errno . " - " . $errmsg; //TODO add error logging . " QUERY: " . $sql;
				throw new GZLoginException($message,GZLoginException::DATA_BASE_ERROR);
			}
			else
			{
				if(($row_data = $result->fetch_assoc()) !== NULL)
				{
					//Check password
					if(md5($pswd) == $row_data['pswd'])
					{
						$meta = $this->fieldMetaData();
						$count = count($meta);
						$obj = new GZUser();
						for($i = 0; $i < $count; $i++)
						{
							$name = $meta[$i]->getName();
							if($name != 'pswd')
							{
								$obj->set($name,$row_data[$name]);
							}
						}
						$obj->setPrimary($this->getPrimaryKeyName(),$row_data[$this->getPrimaryKeyName()]);
						$this->logger->info($obj->get(GZUser::NAME) . " (" . $obj->get(GZUser::EMAIL) . ") Logged On");
						return $obj;
					}
					else
					{
						$message = "Incorrect Pass Word!";
						$this->logger->error("ERROR: " . $message . " sql:" . $sql);
						//Do not distinguish between pswd or email
						throw new GZLoginException("Incorrect Email or Password",GZLoginException::BAD_PSWD);
					}
				}
				else
				{
					$message = "The email address: " . $email . " is not registered!";
					$this->logger->error("ERROR: " . $message . " sql:" . $sql);
					throw new GZLoginException("Incorrect Email or Password",GZLoginException::UNKNOWN_USER);
				}
			}
		}
	}
?>