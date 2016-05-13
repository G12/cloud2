<?php
require_once("Session.php");
require_once("FileSys.php");

$dbConn = new GZDBConn(DB_SERVER, DB_USERNAME, DB_PW, DB_NAME);
$session_dao = new GZMYSQLSessionDAO($dbConn);
$sessions = new GZSessions($session_dao);
$user = $sessions->statusHelper();

$logger = new Log4Me(Log4me::DEBUG,"log.txt");
$logger->setContext("Download File", $_SERVER['PHP_SELF']); 

if(!is_null($user))
{
	if(isset($_GET['id']))
	{
		$logger->debug("id[" . $_GET['id'] . "]");
		//$fs = new FileSys(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'pics' . DIRECTORY_SEPARATOR);
		$fs = new FileSys('cloud', 'pics', $user);
		try
		{
			$node = $_GET['id'];
			$logger->debug("download_file(" . $node . ")");
			$rslt = $fs->download_file($node);
		}
		catch (Exception $e) {
			header($_SERVER["SERVER_PROTOCOL"] . ' 500 Server Error');
			header('Status:  500 Server Error');
			echo "<h1>" . $e->getMessage() . "</h1>";
		}
	}
	else
	{
		echo "<h1>Missing Param</h1>";
	}
}
else
{
	echo "<h1>Access Denied</h1>";
}
?>