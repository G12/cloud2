<?php

require_once('Log4me.php');
require_once('Session.php');
require_once('User.php');
require_once("FileSys.php");

require_once("CloudRecordSetHelper.class.php");
require_once("dbinfo.php");


$dbConn = new GZDBConn(DB_SERVER, DB_USERNAME, DB_PW, DB_NAME);
$session_dao = new GZMYSQLSessionDAO($dbConn);
$sessions = new GZSessions($session_dao);
$user = $sessions->statusHelper();

$logger = new Log4Me(Log4me::DEBUG,"log.txt");
$logger->setContext("Add Directory", $_SERVER['PHP_SELF']);

if(!is_null($user))
{
	$logger->debug("post array: " . print_r($_POST,true));
	if(isset($_POST['user_id']) && isset($_POST['path']) && isset($_POST['permissions']))
	{
		$rslt = json_encode(array('status' => '200'));
		$pdo = NULL;
		try
		{
			$errMsg = "";
			$acl_table = new CloudRecordSetHelper(DB_NAME, 'ACL');
			$pdo = $acl_table->getPDO();
			$folder_table = new CloudRecordSetHelper(DB_NAME, 'FOLDERS', $pdo);

			$path = $_POST['path'];
			$user_id = $_POST['user_id'];
			$user_permission = $_POST['permissions'];
			$mods = 0;

			//Start the transaction
			$pdo->beginTransaction();

			//Get the next acl_id to use
			$sql = 'SELECT MAX(`acl_id`) AS `acl_id` FROM ACL;';
			$ret = $acl_table->getRowSet($sql);
			if($ret)
			{
				$arr_val = current($ret);
				$arr_val = $arr_val['acl_id'];
				$acl_id = $arr_val + 1;
				$logger->debug("NEW aclid: " . $acl_id);

				$recordset = array();

				//Make the group_set array
				$sql = 'SELECT * FROM `GROUPS`';
				$rs = $folder_table->getRecordSet($sql);
				foreach($rs as $row)
				{
					$groupRow = array('id' => NULL, 'acl_id' => $acl_id, 'user_id' => NULL, 'group_id' => $row['group_id'],
						'permissions' => $row['role']);
					$recordset[] = $groupRow;
				}

				$row = array('id' => NULL, 'acl_id' => $acl_id, 'user_id' => $user_id,
					'group_id' => NULL, 'permissions' => $user_permission);
				$recordset[] = $row;

				$logger->debug("Insert ACL recordset: " . print_r($recordset, true));
				$ret = $acl_table->insertFullRecordSet($recordset);
				$logger->debug("Insert ACL recordset return val: " . $ret);
				if($ret == -1)
				{
					$logger->debug("Error inserting recordset: " . print_r($recordset, true));
					$rslt = json_encode(array('status' => '500', 'errorMsg' => "Error inserting recordset: " . print_r($recordset, true)));
					$pdo->rollBack();
				}
				else
				{
					//Add the new Folder
					//inserts one row of data into the table
					//parameters: $row an associative array containg name value pairs to insert
					//Note primary key value or NULL must be included in $row
					//$primaryKeyName
					//For MYSQL returns primary key value on success SQLite returns true, All return false on failure.
					$row = array('folder_id' => NULL, 'parent_id' => 1, 'user_id' => $user_id,
						'path' => $path, 'acl_id' => $acl_id, 'mods' => $mods);

					$ret = $folder_table->insertRow($row, 'folder_id');
					if($ret)
					{
						$logger->debug("Added Folder " . $path);
						$fs = new FileSys('cloud', 'pics', $user);
						try {
							$id = $fs->create(NULL, $path, true);
							$logger->debug("New directory created: " . $id);
							$pdo->commit();
							$rslt = json_encode(array('status' => 'SUCCESS', 'msg' => "Added Directory " . $path .
								" with ACL id: " . $acl_id));
						}						
						catch (Exception $e)
						{
							$pdo->rollBack();
							$rslt = json_encode(array('status' => '500', 'errorMsg' => "FAILED to get create directory " . $path));
						}
					}
					else
					{
						$logger->debug("Error inserting row: " . print_r($row, true));
						$rslt = json_encode(array('status' => '500', 'errorMsg' => "Error inserting row: " . print_r($row, true)));
						$pdo->rollBack();
					}
				}
			}
			else
			{
				$rslt = json_encode(array('status' => '500', 'errorMsg' => "FAILED to get aclid"));
				$pdo->rollBack();
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
		$rslt = json_encode(array('status' => '500', 'errorMsg' => 'Missing parameters user_id path permissions'));
	}
	header('Content-Type: application/json; charset=utf8');
	echo $rslt;

}
?>
