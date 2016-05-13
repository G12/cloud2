<?php
	
	require_once("Log4me.php");
	require_once("dbinfo.php");
	//require_once($_SERVER['DOCUMENT_ROOT'] . "cloud/lib/dbinfo.php");
	
	define('BASE_LOGGER_LEVEL',Log4me::INFO);
	define('BASE_LOGGER_FILE',"log.txt");

	Class GZInvalidArgumentException extends Exception
	{
		function __construct($description, $value)
		{
			parent::__construct("GZInvalidArgumentException - $description [$value]"); 
		}
	}
	
	Class GZDatabaseException extends Exception
	{
		private $errnumber;
		function __construct($description, $errno, $error)
		{
			$this->errnumber = $errno;
			parent::__construct("MYSQL Database error: " . $description . " Error Number: " . $errno . " Error: " . $error); 
		}
		
		function getErrorNumber()
		{
			return $this->errnumber;
		}
	}


	//TINYINT		4
	//SMALLINT	6
	//MEDIUMINT	8
	//INT		11
	//BIGINT		20
	//DATE 	0000-00-00
	//DATETIME 0000-00-00 00:00:00
	//"boolean" (or, since PHP 4.2.0, "bool")
	//"integer" (or, since PHP 4.2.0, "int")
	//"float" (only possible since PHP 4.2.0, for older versions use the deprecated variant "double")
	//"string"
	//"array"
	//"object"
	//"null" (s
	//see floatval ( mixed $var )
	//date_create (
	//see settype ( mixed &$var , string $type )

	Class GZTypeCon
	{
		private $length;
		private $type;
		private $modifier;
		public function __construct()
		{
		}
		public function parseType($typeDef)
		{
			$pattern = '/\(/';
			$ar1 = preg_split($pattern,$typeDef);
			$this->type = trim($ar1[0]);
			if(count($ar1) > 1)
			{
				$pattern = '/\)/';
				$ar2 = preg_split($pattern,$ar1[1]);
				$this->length = trim($ar2[0]);
				if(count($ar2) > 1)
				{
					$this->modifier = trim($ar2[1]);			
				}
			}
		}
		public function getType()
		{
			return $this->type;
		}
		public function getLength()
		{
			return $this->length;
		}
		public function getModifier()
		{
			return $this->modifier;
		}
		public function convert($value)
		{
			//TODO Add conversion functions here?
		}
	}
	
 	//["Field"]["Type"]["Null"]["Key"]["Default"]["Extra"]
	Class GZMetaData
	{
		private $row_data;
		private $converter;
		private $typeCon;
		private $isNull;
		private $isPrimary;
		private $isUnique;
		private $readonly;
		private $auto_increment;
		public function __construct($row_data, $typeCon = null)
		{
			$this->row_data = $row_data;
			$this->isNull = ($row_data["Null"] == "YES"); 
			$this->isPrimary = ($row_data["Key"] == "PRI");
			$this->isUnique = ($row_data["Key"] == "UNI");
			$this->auto_increment = ($row_data["Extra"] == "auto_increment");
			$this->readonly = false;
			if($this->isPrimary){ $this->readonly = true; }
			if(is_null($typeCon))
			{
				$this->typeCon = new GZTypeCon();
			}
			else
			{
				$this->typeCon = $typeCon;
			}
			$this->typeCon->parseType($row_data["Type"]);
		}
		public function getName()
		{
			return $this->row_data["Field"];
		}
		public function getType()
		{
			return $this->typeCon->getType();
		}
		public function getLength()
		{
			return $this->typeCon->getLength();
		}
		public function getModifier()
		{
			return $this->typeCon->getModifier();
		}
		public function isNull()
		{
			return $this->isNull;
		}
		public function isReadOnly()
		{
			return $this->readonly;
		}
		public function getKey()
		{
			return $this->row_data["Key"];
		}
		public function getDefault()
		{
			return $this->row_data["Default"];
		}
		public function getExtra()
		{
			return $this->extra;
		}
		public function convert($value)
		{
			return $this->typeCon->convert($value);
		}
	}

	Class GZField
	{
		private $field;
		private $primary_key;
		private $primary_key_name;
		
		public function __construct()
		{
			$field = array();
		}
		
		public function set($name,$value)
		{
			$this->field[$name] = $value;
		}
		
		public function get($name)
		{
			return $this->field[$name];
		}
		
		public function setPrimary($name,$value)
		{
			$this->primary_key_name = $name;
			$this->primary_key = $value;			
		}

		public function getPrimary()
		{
			return $this->primary_key;
		}
		
		public function getPrimaryName()
		{
			return $this->primary_key_name;
		}
		
		public function getFieldNames()
		{
			return array_keys($this->field);
		}
		
		public function copyCast($newObj)
		{
			$newObj->setPrimary($this->primary_key_name,$this->primary_key);
			foreach ($this->field as $key => $value)
			{
    			$newObj->set($key,$value);
			}
			return $newObj;
		}
		
		public function cloneEmpty()
		{
			$obj = new GZField();
			$obj->setPrimary($this->primary_key_name,$this->primary_key);
			return $obj;
		}
	}
	
	Class GZFields
	{
		protected $dao;
		private $field_metaData_array;
		private $primary_key_name;
		protected $isLoggedIn;
		protected $logger;
		public function __construct($in_dao, $isLoggedIn=false)
		{
			$this->dao = $in_dao;
			$this->isLoggedIn = $isLoggedIn;
			$this->logger = new Log4Me(BASE_LOGGER_LEVEL,BASE_LOGGER_FILE);
			$this->logger->setContext("GZFields Table: " . $this->dao->getTableName(), $_SERVER['PHP_SELF']); 
		}
		public function loggedIn()
		{
			$this->isLoggedIn = true;
		}
		public function loggedOut()
		{
			$this->isLoggedIn = false;
		}
		public function fieldsMetaData()
		{
			if(is_null($this->field_metaData_array))
			{
				//Whoever gets here first initializes both
				$this->field_metaData_array = $this->dao->getMetaDataArray();
				$this->primary_key_name = $this->dao->getPrimaryKeyName();
			}
			return $this->field_metaData_array;
		}

		public function primaryKeyName()
		{
			if(is_null($this->primary_key_name))
			{
				//Whoever gets here first initializes both
				$this->field_metaData_array = $this->dao->getMetaDataArray();
				$this->primary_key_name = $this->dao->getPrimaryKeyName();
			}
			return $this->primary_key_name;
		}
		
		public function getCount()
		{
			return $this->dao->getCount();
		}
		
		public function getField($index)
		{
			return $this->dao->getField($index);
		}
		
		public function getFieldById($id)
		{
			return $this->dao->getFieldById($id); 
		}
		
		public function getFieldByName($col_name,$value)
		{
			return $this->dao->getFieldByName($col_name,$value);
		}
		
		public function addNewField($field)
		{
			return $this->dao->addField($field);
		}
		
		public function updateField($field)
		{
			return $this->dao->updateField($field);
		}
		
		public function deleteField($id)
		{
			return $this->dao->deleteField($id);
		}

		public function testObject()
		{
			$field = new GZField();
			$meta = $this->fieldsMetaData();
			for($j = 0; $j < count($meta); $j++)
			{
				$name = $meta[j]->getName();
				$field->set($name,"[" . $name . "]"); 
			}
			return $field;
		}
		
		public function makeTableHeader()
		{
			echo "<tr>";
			if($this->isLoggedIn)
			{
				echo "<th></th><th></th>";
			}
			echo "<th>" . $this->primaryKeyName() . "</th>";
			$meta = $this->fieldsMetaData();
			for($j = 0; $j < count($meta); $j++)
			{
				echo "<th>" . $meta[$j]->getName() . "</th>";
			}
			echo "</tr>";
		}
		
		public function makeTableRows($isDesc, $from = null,$to = null)
		{
			$this->dao->isDescending($isDesc);
			$this->dao->selectFields("",$from,$to);
			
			$meta = $this->fieldsMetaData();
			$nameCount = count($meta);
			$count = $this->dao->getCount();
			for ($i = 0; $i < $count; $i++)
			{
				$obj = $this->dao->getObjectByIndex($i);
				echo "<tr id='tr" . $obj->getPrimary() . "' >";
				//Don't show edit and delete if not logged in
				if($this->isLoggedIn)
				{
					echo "<td class='edit'><a href='#' id='" . $obj->getPrimary() . "'>Edit</a></td>";
					echo "<td class='delete'><a href='#' id='" . $obj->getPrimary() . "' name='" . $obj->getPrimaryName() ."'>Delete</a></td>";
				}
				echo "<td>" . $obj->getPrimary() . "</td>";
				for($j = 0; $j < $nameCount; $j++)
				{
					echo "<td>" . $obj->get($meta[$j]->getName()) . "</td>";
				}
				echo "</tr>";
			}
		}

		public function makeAddForm($action)
		{
			echo '<form id="update' . $this->dao->getTableName() . '" action="' . $action . '?action=add" enctype="application/x-www-form-urlencoded" method="post">';
			echo '<input id="submitbtn" type="submit" value="Submit" /><br />';
			echo '<table>';
			$meta = $this->fieldsMetaData();
			$nameCount = count($meta);
			for($i = 0; $i < $nameCount; $i++)
			{
				echo '<tr><td>' . $meta[$i]->getName() . '</td>';	
				echo '<td><input name="' . $meta[$i]->getName() . '" type="text" /></td></tr>';
			}
			echo '</table></form>';
		}
		
		public function addUsingFormData()
		{
			$obj = new GZField();
			$meta = $this->fieldsMetaData();
			$nameCount = count($meta);
			for($i = 0; $i < $nameCount; $i++)
			{
				$this->logger->debug("POST[" . $meta[$i]->getName() . "]");
				$val = $_POST[$meta[$i]->getName()];
				$obj->set($meta[$i]->getName(),$val);	
			}
			$this->addNewField($obj);
		}
		
		public function makeEditForm($action, $id)
		{
			$obj = $this->getFieldById($id);
			
			echo '<form id="update' . $this->dao->getTableName() . '" action="' . $action . '?id=' . $id . '&action=update" enctype="application/x-www-form-urlencoded" method="post">';
			echo '<input id="submitbtn" type="submit" value="Submit" /><br />';
			echo '<table>';
			echo '<tr><td>' . $this->primaryKeyName() . '</td>';	
			echo '<td><input type="text" value="' . $obj->getPrimary() . '" readonly /></td></tr>';

			$meta = $this->fieldsMetaData();
			$nameCount = count($meta);
			for($i = 0; $i < $nameCount; $i++)
			{
				$name = $meta[$i]->getName();
				$type = $meta[$i]->getType();
				$size = $meta[$i]->getLength();
				$maxlength = $size;
				if($size > 100){$size = 100;}
				if($type == "datetime")
				{
					$size = 20;
				}
				echo '<tr><td>' . $name . '</td>';	
				echo '<td><input name="' . $name . '" type="text" value="'
					 . $obj->get($name) . '" size="' . $size .
					 '" maxlength="' . $maxlength . '" /></td></tr>';
			}
			echo '</table></form>';
		}

		public function updateUsingFormData($obj)
		{
			$meta = $this->fieldsMetaData();
			$nameCount = count($meta);
			for($i = 0; $i < $nameCount; $i++)
			{
				$val = $_POST[$meta[$i]->getName()];
				$obj->set($meta[$i]->getName(),$val);	
			}
			//$obj->setPrimary($this->primaryKeyName(),$id);
			$this->updateField($obj);
		}

	}
	
	Class GZMYSQLFieldsDAO
	{
		const MAX_ROWS = 10000;
		//private	$db_host;
		//private	$db_user;
		//private	$db_pwd;
		//private	$db_name;
		private $db;
		private $field_metaData_array;
		private $primary_key_name;
		private $primary_key_metaData;
		
		protected $orderByField = "";
		protected $direction;
		protected $obj_list;

		protected $table_name;
		protected $conn;
		protected $logger;
		
		//public function __construct($db_host, $db_user, $db_pwd, $db_name, $table_name)
		public function __construct(GZDBConn $dbConn, $table_name)
		{
			$this->logger = new Log4Me(BASE_LOGGER_LEVEL,BASE_LOGGER_FILE);
			
			//$this->db_host = $db_host;
			//$this->db_user = $db_user;
			//$this->db_pwd = $db_pwd;
			//$this->db_name = $db_name;
			$this->db = $dbConn;
			$this->conn = $this->db->getConn();
			$this->table_name = $table_name;
			$this->obj_list = array();
			$this->direction = "";
			
			$this->logger->setContext("GZMYSQLFieldsDAO Table: " . $table_name, $_SERVER['PHP_SELF']);
		}
		
		public  function __destruct()
		{
			$this->logger->debug("Destructor Called");
			//$this->closeConnection();
		}

		//Can specifically close Connection
		public  function closeConnection()
		{
			//if(isset($this->conn))
			//{
			//	$this->conn->close();
			//	$this->conn = NULL;
			//	$this->logger->debug("Connection Closed");
			//}
		}

		public function getDbConn()
		{
			return $this->db;
		}
		
		//Lazy Connect
		private function connect()
		{
			if(!is_null($this->conn))
			{
				$this->logger->debug("Connect using CURRENT Connection");
				return;
			}
			$this->logger->debug("CREATE NEW Connection " . $this->db->server . " - username - pw - " . $this->db->db);
			$this->conn = @new mysqli($this->db->server, $this->db->username, $this->db->pw, $this->db->db);
			if(mysqli_connect_errno() != 0)
			{
				$errno = mysqli_connect_errno();
				$errmsg = mysqli_connect_error();
				$this->logger->fatal("CONNECT Failed ERROR[" . $errno . "] " . $errmsg);
				throw new GZDatabaseException('CONNECT Failed ',$errno,$errmsg);
			}
			$this->db->setConn($this->conn);
		}
		
		protected function query($sql)
		{
			$this->logger->debug("sql: " . $sql);
			
			$this->connect();
			/*
			if($result === FALSE)
			{
				$errno = $conn->errno;
				$errmsg = $conn->error;

				$this->logger->debug("FATAL ERROR in sql: " . $sql . " Error[" . $errno . "] " . $errmsg);

				$this->logger->fatal("FATAL ERROR in sql: " . $sql . " Error[" . $errno . "] " . $errmsg);
				throw new GZDatabaseException($sql . " Failed ",$errno,$errmsg);
			}
			*/
			return $this->conn->query($sql);
		}

		//Query the database for field names and MetaData
		protected function fieldMetaData($tableNamesArray = NULL)
		{
			if(is_null($this->field_metaData_array))
			{
				$this->field_metaData_array = array();
				$sql = "SHOW COLUMNS FROM `" . $this->table_name . "`";
				$result = $this->query($sql);
				while(($row_data = $result->fetch_assoc()) !== NULL)
				{
			        $this->logger->debug("Table: " . $this->table_name . " Field: " . $row_data['Field']);
			        if("PRI" == $row_data['Key'])
			        {
			        	$this->primary_key_name = $row_data['Field'];
			        	$this->primary_key_metaData = new GZMetaData($row_data);
			        }
			        else
			        {
			        	$this->field_metaData_array[] = new GZMetaData($row_data);
			        }
			    }
			}
			if($tableNamesArray != NULL)
			{
				$count = count($tableNamesArray);
				for($i = 0; $i < $count; $i++)
				{
					$sql = "SHOW COLUMNS FROM `" . $tableNamesArray[$i] . "`";
					$result = $this->query($sql);
					while(($row_data = $result->fetch_assoc()) !== NULL)
					{
				        if("PRI" != $row_data['Key'])
				        {
				        	$this->logger->debug("Table: " . $tableNamesArray[$i] . " Field: " . $row_data['Field']);
				        	$this->field_metaData_array[] = new GZMetaData($row_data);
				        }
				    }
				} 
			}	    
		    return $this->field_metaData_array;
		}

		protected function loadObject($obj, $row_data)
		{
			$meta = $this->fieldMetaData();
			$count = count($meta);
			$obj->setPrimary($this->primary_key_name,$row_data[$this->primary_key_name]);
			for($i = 0; $i < $count; $i++)
			{
				$name = $meta[$i]->getName();
				$obj->set($name, $row_data[$name]);
				//$this->logger->debug("obj->set(" . $name . ", " . $row_data[$name] . ")");

			}
		}
		
		public function getTableName()
		{
			return $this->table_name;
		}
		
		public function getMetaDataArray()
		{
			return $this->fieldMetaData();
		}
		
		public function getPrimaryKeyName()
		{
			//If the field metadata array is not initialized this will do it
			$this->fieldMetaData();
			return $this->primary_key_name;
		}

		public function isDescending($flag)
		{
			if($flag)
			{
				$this->direction = "DESC";
			}
		}
		
		public function orderByField($field = "")
		{
			$this->orderByField = $field;
		}
		
		public function selectFields($where = "", $from = null, $to = null)
		{
			//empty the array first
			$this->obj_list = array();
			
			if(is_null($to) && is_null($from))
			{
				$limit = "";
			}
			elseif(is_null($to))
			{
				$limit = ' LIMIT ' . $from . ' , ' . self::MAX_ROWS;
			}
			else
			{
				$limit = ' LIMIT ' . $from . ' , ' . $to;
			}
			
			$fieldName = $this->getPrimaryKeyName();
			if($this->orderByField != "")
			{
				$fieldName = $this->orderByField;
			}
			
			$sql = 'SELECT * FROM `' . $this->table_name . '` ' . $where . ' ORDER BY `' . $fieldName . '` ' . $this->direction . $limit;

			//echo "[" . $sql . "]";
			$result = $this->query($sql);
			while(($row_data = $result->fetch_assoc()) !== NULL)
			{
				$obj = new GZField();
				$this->loadObject($obj, $row_data);
				$this->obj_list[] = $obj;
			}
		}		
		
		public function getCount()
		{
			return count($this->obj_list);
		}

		public function getObjectByIndex($index)
		{
			return $this->obj_list[$index];
		}
		
		public function deleteField($id)
		{
			$sql = 'DELETE FROM `' . $this->table_name . '` WHERE `' . $this->getPrimaryKeyName() . '` = ' . $this->check_input($id) . ' LIMIT 1';
			//execute query
			if($this->query($sql) === TRUE)
			{
				return TRUE;
			}
			else
			{
				$errno = $this->conn->errno; 
				$errmsg = $this->conn->error;
				throw new GZDatabaseException("Error : for (" . $sql . ") Failed ",$errno,$errmsg);
			}
		}

		public function deleteBlock($from,$to)
		{
			$sql = 'DELETE QUICK FROM `' . $this->table_name . '` WHERE `' . $this->getPrimaryKeyName() . '` BETWEEN ' . $this->check_input($from) . ' AND ' . $this->check_input($to);
			//execute query
			if($this->query($sql) === TRUE)
			{
				return TRUE;
			}
			else
			{
				$errno = $this->conn->errno; 
				$errmsg = $this->conn->error;
				throw new GZDatabaseException("Error : for (" . $sql . ") Failed ",$errno,$errmsg);
			}
		}
		
		//returns primary key id
		public function addField($obj)
		{
			$meta = $this->fieldMetaData();
			$count = count($meta);
			$sql = "INSERT INTO `" . $this->table_name . "` ( ";
			$sql .= "`" . $this->getPrimaryKeyName() . "` ";
			for($i = 0; $i < $count; $i++)
			{
				$sql .= ",`" . $meta[$i]->getName() . "` ";
			}
			$sql .= ")";
			$sql .= " VALUES ( ''"; 
			for($i = 0; $i < $count; $i++)
			{
				$value = $this->check_input($obj->get($meta[$i]->getName()),$meta[$i]);
				$sql .= ", " . $value;
			}
			$sql .= " );"; 
			
			//echo "[" . $sql . "]";
			//return;
			
			$this->logger->info("SQL: " . $sql);

			//execute query
			if($this->query($sql) === TRUE)
			{
				//TODO possible race condition getting insert_id ??????
				$id =$this->conn->insert_id;
				$obj->setPrimary($this->getPrimaryKeyName(),$id);
				$this->job_list[] = $obj;
				return $id;
			}
			else
			{
				$errno = $this->conn->errno; 
				$errmsg = $this->conn->error;
				$this->errNumber = $errno;
				
				$this->logger->debug("INSERT INTO " . $this->table_name . " Failed ",$errno,$errmsg);

				throw new GZDatabaseException("INSERT INTO " . $this->table_name . " Failed ",$errno,$errmsg);
			}
		}
		
		public function updateField($field)
		{
			//$this->logger->debug("path_id[" . $field->get("path_id") . "]");
			$meta = $this->fieldMetaData();
			$count = count($meta);
			$hasContent = false;
			$sql = "UPDATE `" . $this->table_name . "` SET ";
			for($i = 0; $i < $count; $i++)
			{
				$name = $meta[$i]->getName();
				if(! is_null($field->get($name)))
				{
					$sql .= $this->comma($hasContent);
					$sql .= "`" . $name . "` = " . $this->check_input($field->get($name)) . " ";
					$hasContent = true;
					//$this->logger->debug("Update: " . $name . " value: " . $this->check_input($field->get($name)));
				}
			}
			if(!$hasContent)
			{
				return false; //Nothing to update
			}
			else
			{
				$sql .= "WHERE `" . $this->getPrimaryKeyName() . "` = '" . $field->getPrimary() . "'";
			}
			//execute query
			if($this->query($sql) === TRUE)
			{
				//TODO anything to do here?
			}
			else
			{
				$errno = $this->conn->errno; 
				$errmsg = $this->conn->error;
				$this->logger->error("FAILED sql: " . $sql . " Error[" . $errno . "] " . $errmsg);
				throw new GZDatabaseException("INSERT INTO " . $this->table_name . " Failed ",$errno,$errmsg);
			}

		}
		
		public function getFieldById($id)
		{
			$sql = "SELECT * FROM `" . $this->table_name . "` WHERE `" . $this->getPrimaryKeyName() . "` = " . $this->check_input($id);
			$result = $this->query($sql);
			if($result === FALSE)
			{
				$errno = $this->conn->errno; 
				$errmsg = $this->conn->error;
				throw new GZDatabaseException($sql . "Failed ",$errno,$errmsg);
			}
			else
			{
				if(($row_data = $result->fetch_assoc()) !== NULL)
				{
					$meta = $this->fieldMetaData();
					$count = count($meta);
					$obj = new GZField();
					for($i = 0; $i < $count; $i++)
					{
						$name = $meta[$i]->getName();
						$obj->set($name,$row_data[$name]);
					}
					$obj->setPrimary($this->getPrimaryKeyName(),$row_data[$this->getPrimaryKeyName()]);
					return $obj;
				}
				else
				{
					throw new GZInvalidArgumentException("Base.php getFieldById: Could not retreive Information for", $id);
				}
			}
		}

		//TODO keep improving
		public function getFieldXmlById($id, $join1 = "", $join2 = "", $tableNamesArray = NULL)
		{
			$this->logger->debug("join1[" . $join1 . "] join2[" . $join2 . "] tableNamesArray: " . var_export($tableNamesArray, true));
			
			$sql = 'SELECT * ' . $join1 . ' FROM `' . $this->table_name . '` ' . $join2 . ' WHERE `' . $this->getPrimaryKeyName() . '` = ' . $this->check_input($id);
			//$sql = "SELECT * FROM `" . $this->table_name . "` WHERE `" . $this->getPrimaryKeyName() . "` = " . $this->check_input($id);
			$result = $this->query($sql);
			if($result === FALSE)
			{
				$errno = $this->conn->errno; 
				$errmsg = $this->conn->error;
				throw new GZDatabaseException($sql . "Failed ",$errno,$errmsg);
			}
			else
			{
				if(($row_data = $result->fetch_assoc()) !== NULL)
				{
					$meta = $this->fieldMetaData($tableNamesArray);
					$count = count($meta);

					// create a new XML document
					$domDoc = new DOMDocument('1.0', "UTF-8");
					$row = $domDoc->createElement('row');
					$table_name = $domDoc->createAttribute('table_name');

					// Value for the created attribute
					$table_name->value = $this->table_name;

					// Don't forget to append it to the element
					$row->appendChild($table_name);

					// Append it to the document itself
					$domDoc->appendChild($row);

					for($i = 0; $i < $count; $i++)
					{
						$field_name = $meta[$i]->getName();
						$val = $row_data[$field_name];
						if($val != NULL)
						{
							$field = $domDoc->createElement('field',$val);
													
							//attributes
							$name = $domDoc->createAttribute('name');
							$name->value = $field_name;
							$field->appendChild($name);
							
							$type = $domDoc->createAttribute('type');
							$type->value = $meta[$i]->getType();
							$field->appendChild($type);
	
							$length = $domDoc->createAttribute('length');
							$length->value = $meta[$i]->getLength();
							$field->appendChild($length);
	
							$modifier = $domDoc->createAttribute('modifier');
							$modifier->value = $meta[$i]->getModifier();
							$field->appendChild($modifier);
	
							$key = $domDoc->createAttribute('key');
							$key->value = $meta[$i]->getKey();
							$field->appendChild($key);
							
							$row->appendChild($field);
							//isNull()
							//isReadOnly()
							//getDefault()
							//getExtra()
						}
					}
					return $domDoc->saveXML();
				}
				else
				{
					throw new GZInvalidArgumentException("Base.php getFieldById: Could not retreive Information for", $id);
				}
			}
		}

		public function getFieldByName($col_name,$value)
		{
			$sql = "SELECT * FROM `" . $this->table_name . "` WHERE `" . $col_name . "` = " . $this->check_input($value);
			$result = $this->query($sql);
			if($result === FALSE)
			{
				$errno = $this->conn->errno; 
				$errmsg = $this->conn->error;
				throw new GZDatabaseException($sql . "Failed ",$errno,$errmsg);
			}
			else
			{
				$name = "";
				if(($row_data = $result->fetch_assoc()) !== NULL)
				{
					$meta = $this->fieldMetaData();
					$count = count($meta);
					$obj = new GZField();
					for($i = 0; $i < $count; $i++)
					{
						$name = $meta[$i]->getName();
						$obj->set($name,$row_data[$name]);
					}
					$obj->setPrimary($this->getPrimaryKeyName(),$row_data[$this->getPrimaryKeyName()]);
					return $obj;
				}
				else
				{
					throw new GZInvalidArgumentException("Base.php getFieldByName: Could not retreive Information for", $name);
				}
			}
		}

		
		//////////////////////////Utilities////////////////////

		//NOTE puts single quotes around string values
		public function check_input($value, $metadata = NULL)
		{
			$this->connect();
			
			// Stripslashes
			if (get_magic_quotes_gpc())
			{
				$value = stripslashes($value);
			}
			if(is_null($metadata))
			{
				// Quote if not a number
				if (!is_numeric($value))
				{
					$value = "'" . $this->conn->real_escape_string($value) . "'";
				}
			}
			else
			{
				//$this->logger->info("Value: " . $value . " Type: " . $metadata->getType());
				// Quote if not a number
				if ($metadata->getType() === "varchar")
				{
					$value = "'" . $this->conn->real_escape_string($value) . "'";
				}
				elseif($metadata->getType() === "text")
				{
					$value = "'" . $this->conn->real_escape_string($value) . "'";
				}
				elseif($metadata->getType() === "datetime")
				{
					$value = "'" . $this->conn->real_escape_string($value) . "'";
				}
				else
				{
					if(empty($value) | is_null($value))
					{
						$value = "''";
					}
				}
			}
			return $value;
		}
		
		private function comma($hasContent)
		{
			if($hasContent)
			{
				return ", ";
			}
			return "";
		}

	}

?>