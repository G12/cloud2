<?php

require_once('Log4me.php');
require_once('Session.php');
require_once('User.php');

require_once("CloudRecordSetHelper.class.php");
require_once("dbinfo.php");


$dbConn = new GZDBConn(DB_SERVER, DB_USERNAME, DB_PW, DB_NAME);
$session_dao = new GZMYSQLSessionDAO($dbConn);
$sessions = new GZSessions($session_dao);
$user = $sessions->statusHelper();

$logger = new Log4Me(Log4me::DEBUG,"permissions_log.txt");
$logger->setContext("Permission Update", $_SERVER['PHP_SELF']);

if(!is_null($user))
{
	$logger->debug("post array: " . print_r($_POST,true));
	if(isset($_POST['json_data']))
	{
		$rslt = json_encode(array('status' => '200'));
		$pdo = NULL;
		try
		{
			$errMsg = "";
			$acl_table = new CloudRecordSetHelper(DB_NAME, 'ACL');
			$pdo = $acl_table->getPDO();
			$folder_table = new CloudRecordSetHelper(DB_NAME, 'FOLDER', $pdo);
			
			$json_data = $_POST['json_data'];
			$data = json_decode($json_data);
			
			$logger->debug("data: " . print_r($data,true));
				
			$path = $data->path;
			$acl_id = $data->acl_id;
			$permission_set = $data->permission_set;
			$group_set = $permission_set->group_set;
			$user_set = $permission_set->user_set;
			
			//Get user_name and user_id defined onindex.php from session
			$user_name = $data->user_name;
			$user_id = $data->user_id;
			
			$logger->info("user_name: " . $user_name . " user_id: " . $user_id); 
			
			//Determine if current acl is root
			$isRoot = false;
			$arr = explode("/",$path);
			if(count($arr) == 1 || "/" == $path)
			{
				$isRoot = true;
				$logger->debug("isRoot");
			}
			else
			{
				//query database on parent folder
				$end = array_pop ($arr);
				$root_path = implode("/", $arr);
				$sql = 'SELECT `acl_id` FROM `FOLDERS` WHERE path="' . $root_path . '"';
				$logger->debug("query database on parent folder: " . $sql);
				$ret = $folder_table->getRowSet($sql);
				if($ret)
				{
					$logger->debug("ret = " . print_r($ret, true));
					
					$root_acl_id = current($ret);
					$root_acl_id = $root_acl_id['acl_id'];
					$logger->debug("root_acl_id = " . $root_acl_id);
					
					if($root_acl_id != $acl_id)
					{
						$logger->debug("root_acl_id != acl_id: " . $acl_id);
						$isRoot = true;
					}
				}
				$logger->debug("isRoot[" . $isRoot . "]");
			}
			//Start the transaction
			$pdo->beginTransaction();
			//If the current acl is a root then remove all acls containing that id
			if($isRoot)
			{
				$sql = 'DELETE FROM `ACL` WHERE `acl_id` = ' . $acl_id;
				$count = $acl_table->executeSQL($sql);
				if($count == -1)
				{
					$logger->debug("Error executing sql: " . $sql);
					$rslt = json_encode(array('status' => '500', 'errorMsg' => 'Failed to Delete for acl_id: ' . $acl_id));
					header('Content-Type: application/json; charset=utf8');
					echo $rslt;
					die();
				}
				else
				{
					$logger->debug("Deleted " . $count);
				}
			}
			else
			{
				//Get the next acl_id to use
				$sql = 'SELECT MAX(`acl_id`) AS `acl_id` FROM ACL;';
				$ret = $acl_table->getRowSet($sql);
				if($ret)
				{
					$arr_val = current($ret);
					$arr_val = $arr_val['acl_id'];
					$acl_id = $arr_val + 1;
					$logger->debug("NEW aclid: " . $acl_id);
				}
				else
				{
					$logger->debug("use old aclid: " . $acl_id);
				}
			}
			$recordset = array();
			//Add the group acls array
			for($i=0; $i < count($group_set); $i++)
			{
				$obj = $group_set[$i];
				//$logger->debug("obj: " . print_r($obj, true));
				$logger->debug("obj->group_id: " . print_r($obj->group_id, true));
				$row = array('id' => NULL, 'acl_id' => $acl_id, 'user_id' => NULL, 'group_id' => $obj->group_id, 'permissions' => $obj->permissions);
				$recordset[] = $row;	
			}
			//Add the user acls array
			for($i=0; $i < count($user_set); $i++)
			{
				$obj = $user_set[$i];
				$row = array('id' => NULL, 'acl_id' => $acl_id, 'user_id' => $obj->user_id, 'group_id' => NULL, 'permissions' => $obj->permissions);
				$recordset[] = $row;	
			}
			$logger->debug("Insert ACL recordset: " . print_r($recordset, true));
			$ret = $acl_table->insertFullRecordSet($recordset);
			if($ret == -1)
			{
				$logger->debug("Error inserting recordset: " . print_r($recordset, true));
				$rslt = json_encode(array('status' => '500', 'errorMsg' => "Error inserting recordset: " . print_r($recordset, true)));
				$pdo->rollBack();
			}
			else
			{
				//Update the Folders table with new acls if not root
				if(!$isRoot)
				{
					$sql = 'UPDATE `FOLDERS` SET `acl_id` = ' . $acl_id . ' WHERE `path` LIKE "' . $path .  '%";';
					$count = $folder_table->executeSQL($sql);
					if($count == -1)
					{
						$logger->debug("Error executing sql: " . $sql);
						$rslt = json_encode(array('status' => '500', 'errorMsg' => "Error executing sql: " . $sql));
						$pdo->rollBack();
					}
					else
					{
						$logger->debug("Updated " . $count);
						$pdo->commit();
					}
				}
				else
				{
					$pdo->commit();
				}
			}
			
		}
		catch (Exception $e)
		{
			$rslt = json_encode(array('status' => '500', 'errorMsg' => $e->getMessage()));
			$pdo->rollBack();
		}
	}
	else
	{
		$rslt = json_encode(array('status' => '500', 'errorMsg' => 'Missing json_data'));
	}
	header('Content-Type: application/json; charset=utf8');
	echo $rslt;

}
?>
