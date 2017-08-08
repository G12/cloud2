<?php
require_once("lib/Session.php");

$dbConn = new GZDBConn(DB_SERVER, DB_USERNAME, DB_PW, DB_NAME);
$session_dao = new GZMYSQLSessionDAO($dbConn);
$sessions = new GZSessions($session_dao);
$user = $sessions->statusHelper();

$logger = new Log4Me(Log4me::DEBUG,"log.txt");
$logger->setContext("Confirm Download File", $_SERVER['PHP_SELF']);

if(!is_null($user))
{
	if(isset($_GET['id']) && isset($_GET['name']))
	{
        $id = $_GET['id'];
        $name = $_GET['name'];
        $url = "lib/download.php?id=" . urlencode($id);
        $thumb = "thumbs/" . $id;

        $logger->debug("download_file(" . $id . ")");
	}
	else
	{
		echo "<h1>Missing Param</h1>";
		die;
	}
}
else
{
	echo "<h1>Access Denied</h1>";
	die;
}
?>

<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $name; ?></title>
    <link rel="stylesheet" href="css/index.css?ver=001" />
    </head>
<body>
<div class="download_box">
    <a href = "<?php echo $url; ?>"><h1>Download: <?php echo $name; ?></h1></a>
    <img src="<?php echo $thumb; ?>" class="thumb">
    </div>
</body>
</html>

