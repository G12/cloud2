<?php

	require_once("lib/User.php");
	require_once("lib/Session.php");

	function goBack($target)
	{
		$host  = $_SERVER['HTTP_HOST'];// . LOCAL_DIR;
		$path = $host . "/" . $target;
		//$msg .= "Path: [" . $path . "]";
		header("Location: http://$path");
		//echo $msg;
		exit;
	}

	$strMsg = "";
	$logmsg = "";
	$target = $_REQUEST['target'];
	$action = $_REQUEST['action'];
	$error = "";
	if(isset($_REQUEST['error']))
	{
		$error = $_REQUEST['error'];
	}
	$confirmcode = "";
	if(isset($_REQUEST['confirmcode'])) {
		$confirmcode = $_REQUEST['confirmcode'];
	}
	$strMsg .= "Action: " . $action . "<br/>";
	
	$logmsg = $error;
	
	$logger = new Log4Me(Log4me::INFO,"log.txt");
	$logger->setContext("Credentials", $_SERVER['PHP_SELF']); 

	$dbConn = new GZDBConn(DB_SERVER, DB_USERNAME, DB_PW, DB_NAME);
	$dao = new  GZMYSQLUserDAO($dbConn);
	$users = new GZUsers($dao);

	$session_dao = new GZMYSQLSessionDAO($dbConn);
	$sessions = new GZSessions($session_dao);

	switch($action)
	{
		case "login":
		{
			$pswd = $_POST['pswrd'];
			$email = $_POST['email'];
			$keep = $_POST['keep_login'];
			try
			{
				$user = $users->login($email,$pswd);
				//Add a new session entry
				$session = $sessions->addNewSession($user->getPrimary(),$keep);
				goBack($target);
			}
			catch(GZLoginException $logex)
			{
				$logmsg = "Error Number: " . $logex->getCode() . " - " . $logex->getMessage();
			}
			break;
		}
		case "logout":
		{
			try
			{
				//Get the user name and email for info-logger
				$user = $sessions->statusHelper(); //returns NULL on fail
				if($user)
				{
					$logoutMsg = $user->get(GZUser::NAME) . " (" . $user->get(GZUser::EMAIL) . ") Logged Out";
				}
				else
				{
					$logoutMsg = "? Logged Out (could not determine user name and email)";
				}
				//Remove session from database first
				$sessions->removeSession();
				
				$logger->info($logoutMsg);

			}
			catch(GZInvalidArgumentException $ex)
			{
				$logmsg = $ex->getMessage();
			}
			break;
		}
		case "showform":
		{
			break;
		}
		case "confirm":
		{
			echo "<h1>target=" . $target . "</h1>";
			echo "<h1>confirmcode= " . $confirmcode . "</h1>";
			exit;
			break;
		}
		default:
		{
			try
			{
				$session = $sessions->checkStatus();
				if(strlen($target))
				{
					goBack($target);
				}
			}
			catch(Exception $ex)
			{
				//TODO What to do
				$logmsg = $ex->getMessage();
			}
			break;
		}
	}
	
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
<link href="/ranch.ico" rel="shortcut icon" type="image/x-icon" />
<title>Surreal Ranch Credentials</title>
<!--<link href="css/navbar.css" rel="stylesheet" type="text/css" />-->
<style type="text/css">
*{
	margin: 0px;
	padding: 0px;
}
body {
	background-color: #000000;
}
.page {
	width: 100%;
	max-width: 750px;
	min-height: 400px;
	max-height: 600px;
	margin: 10px auto 10px auto;
	border: thin solid #C0C0C0;
	background-color: #FFFFFF;
	font-family: Georgia, "Times New Roman", Times, serif;
	font-size: 1.3em;
	padding: 30px;
}
.nav-bar{
	margin-bottom: 20px;
}
.inputField {
	margin: 20px;
}
.inputField label{
	display: inline-block;
	width:30%;
}
.checkField{
	margin: 20px;
}
i {
	color: #6666FF;
	font-size: small;
}
#wrapper{
	position: relative;
}
#login_container {
	display: inline-block;
	float: left;
	width:48%;
	min-width: 400px;
	margin-right: 20px;
	margin-bottom: 20px;
	background-color: #FFF2FF;
	border: 1px #9966FF solid;
}
#logo_container {
	display: inline-block;
	float: left;
	width:40%;
}
.logo_img {
	height:300px;
	border-style: solid;
	border-color: #000000;
}
.loginmsg {
	font-style: italic;
	color: orange;
	padding-top:0px;
	margin-top:0px;
}
.check_font{
	color: black
}
.warning_font{
	color: #FFCC00;
}
</style>
</head>

<body>
<div class="page">
	<div class="nav-bar">
		<a href="../index.html">Home</a>
	</div>
	<div id="login_container">
		<div class="inputField">
			<i>To Access Image Library</i><br />
			<h5>Sign in now!</h5>
		</div>
		<form action="Credentials.php" enctype="application/x-www-form-urlencoded" method="post">
			<div class="inputField">
				<label>Email:</label>
				<input name="email" type="text" />
			</div>
			<div class="inputField">
				<label>Password:</label>
				<input name="pswrd" type="password" />
			</div>
			<div class="inputField">
				<input name="target" type="hidden" value="<?php echo $target; ?>" />
				<input name="action" type="hidden" value="login" />
			</div>
			<div class="checkField">
				<input id="keep_login" type="checkbox" name="keep_login"/>
				<label for="keep_login"><span class="check_font"><b>Keep me signed in<br/>
						</b>for 2 weeks unless I sign out.</span><span class="warning_font" ><br>
						[Uncheck if on a shared computer]</span></label>
			</div>
			<div class="inputField">
				<span class="loginmsg"><?php echo $logmsg ?></span>
				<input style="float: right" type="submit" value="Sign In" />
			</div>
		</form>
		<div class="inputField">
			<a href="../index.html">Help?</a></td></tr>
		</div>
	</div>
	<div id="logo_container">
		<img class="logo_img" alt="The Ham And Eggs" longdesc="The Ham And Eggs" src="images/TheHamAndEggs.jpg" />
		<div><i>The Ham and Eggs by Dan Wiegand</i></div>
	</div>
</div>
</body>

</html>
