<?php
require_once('Log4me.php');
require_once('Session.php');
require_once('User.php');
require_once("DbHelper.class.php");
require_once("dbinfo.php");

$dbConn = new GZDBConn(DB_SERVER, DB_USERNAME, DB_PW, DB_NAME);
$session_dao = new GZMYSQLSessionDAO($dbConn);
$sessions = new GZSessions($session_dao);
$user = $sessions->statusHelper();

$logger = new Log4Me(Log4me::DEBUG,"log.txt");
$logger->setContext("Profile Update", $_SERVER['PHP_SELF']);

if(!is_null($user))
{
	//$logger->debug("post array: " . print_r($_POST,true));
	if(isset($_POST['item_id']))
	{
		$rslt = json_encode(array('status' => '200'));
		$artworks_array = array();
		$images_array = array();
		//$logger->debug("post array: " . print_r($_POST,true));
		//Seperate out the IMAGES and ARTWORKS values into seperate arrays
		foreach($_POST as $key => $value)
		{
			if(strpos($key, "artwork_") === false)
			{
				$images_array[$key] = $value;
				//$logger->debug("images_array[" . $key . "] = " . $value);
			}
			else
			{
				$artworks_array[$key] = $value;
				//Keep artwork_title and image title in synch
				if($key == "artwork_title")
				{
					$images_array['title'] = $value;
				}
				//$logger->debug("artworks_array[" . $key . "] = " . $value);
			}
		}
		$pdo = NULL;
		try
		{
			$errMsg = "";
			$images_mysqlTable = new  MySQLTable(DB_NAME, "IMAGES");
			$images_table = new RecordSetHelper($images_mysqlTable, false);
			$pdo = $images_table->getPDO();
			$artworks_mysqlTable = new  MySQLTable(DB_NAME, "ARTWORKS");
			$artworks_table = new RecordSetHelper($artworks_mysqlTable, false, $pdo);
			$pdo->beginTransaction();
			if(count($images_array) > 1)
			{
				if(!$images_table->updateRow($images_array))
				{
					$errMsg .= "IMAGES error: logged at [" . date("Y/m/d h:i:s", mktime()) . "]. Contact administrator if this problem persists.";
				}
			}
			if(count($artworks_array) > 1)
			{
				if(!$artworks_table->updateRow($artworks_array))
				{
					$errMsg .= "ARTWORKS error: logged at [" . date("Y/m/d h:i:s", mktime()) . "]. Contact administrator if this problem persists.";
				}
			}
			if($errMsg != "")
			{
				$rslt = json_encode(array('status' => '500', 'errorMsg' => $errMsg));
				$pdo->rollBack();
			}
			else
			{
				$pdo->commit();
			}
		}
		catch (Exception $e)
		{
			$rslt = json_encode(array('status' => '500', 'errorMsg' => $e->getMessage()));
		}
	}
	else
	{
		$rslt = json_encode(array('status' => '500', 'errorMsg' => 'Missing item_id'));
	}
	header('Content-Type: application/json; charset=utf8');
	echo $rslt;

}
?>
