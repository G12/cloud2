<?php
	require_once($_SERVER['DOCUMENT_ROOT'] . "/lib/User.php");
	require_once($_SERVER['DOCUMENT_ROOT'] . "/lib/Session.php");
	require_once($_SERVER['DOCUMENT_ROOT'] . "/lib/UUID.php");
	require_once($_SERVER['DOCUMENT_ROOT'] . "/lib/Path.php");

	$dbConn = new GZDBConn(DB_SERVER, DB_USERNAME, DB_PW, DB_NAME);

	$instructions = "";
	$logmsg = "";
	$submitButtonVal = "Register";
	$nickname = $_POST['nickname'];
	$email = $_POST['email'];
	$pswrd = $_POST['pswrd'];
	$pswrd2 = $_POST['pswrd2'];
	
	$confirmcode = $_REQUEST['confirmcode'];
	$target = $_REQUEST['target'];
	$action = $_REQUEST['action'];

	$mode = $_GET['mode'];
	
	if($action != "confirm")
	{
		$sessionWrap = new GZSessionsWrapper($dbConn);
		$test = $sessionWrap->validate();
	}

	$logmsg = $action;
	
	function goToHere($target)
	{
		$host  = $_SERVER['HTTP_HOST'] . LOCAL_DIR;
		$path = $host . "/" . $target;
		$msg .= "Path: [" . $path . "]";
		header("Location: http://$path");
		//echo $msg;
		exit;
	}

	function  checkEmail($email)
	{
 		if (!preg_match("/^( [a-zA-Z0-9] )+( [a-zA-Z0-9\._-] )*@( [a-zA-Z0-9_-] )+( [a-zA-Z0-9\._-] +)+$/" , $email))
 		{
  			return true;
 		}
 		return false;
	}
	
	function checkPswd($pswrd,$pswrd2)
	{
		if($pswrd2 == $pswrd)
		{
			if(strlen($pswrd) < 6)
			{
				return " Password should be at least 6 characters in length!";
			}
		}
		else
		{
			return " Passwords do not match!";
		} 
		return "";
	}
	
	function checkName($name)
	{
		if(strlen($name))
		{
 			if (preg_match('/^[a-z0-9 ]+$/i', $name))
 			{
  				return "";
 			}
 			else
 			{
 				return "Nick Name must contain only alpha numeric characters and spaces";
 			}
 		}
		return "Nick Name must be at least 1 character long!";
	}
	
	$logger = new Log4Me(Log4Me::INFO,"log.txt");
	$logger->setContext("", $_SERVER['PHP_SELF']);

	switch($action)
	{
		case "register":
		{
			$logmsg = checkName($nickname);
			$logmsg .= checkPswd($pswrd,$pswrd2);
			if(!strlen($logmsg))
			{
				if(!checkEmail($email))
				{ 
					$logmsg .= " Invalid email address"; 
				}
				else
				{
					$confirmKey = UUID::confirmKey();
					$user = new GZUser();
					$user->set(GZUser::NAME,$nickname);
					$user->set(GZUser::PSWD,$pswrd);
					$user->set(GZUser::EMAIL,$email);
					//$user->set(QUESTION,$question);
					//$user->set(ANSWER,$answer);
					$user->set(GZUser::SECURITYLEVEL,0);
					$user->set(GZUser::START_DATE,time());
					$user->set(GZUser::CONFIRM_KEY,$confirmKey);
					$dao = new  GZMYSQLUserDAO($dbConn);
					$users = new GZUsers($dao);
					$user_id = NULL;
					try
					{
						$user_id = $users->addNewField($user);
						
						$message = "Hi " . $nickname 
								. "\n\nYou recently registered for a new account at http://surrealranch.ca\n\n"
								. "There are two ways to complete your registration.\n\n"
								. "[1] Follow the link below to complete your registration\n"
								. "http://surrealranch.ca/Registration.php?action=confirm&mode=email&confirmcode=" . $confirmKey . "\n\n"
								. "[2] Paste the folowing value into the Confirmation Code area of the Registration Page.\n\n"
								. $confirmKey . "\n\n"
								. "After confirmation you will be required to log on to continue.";  
																
						$message = wordwrap($message, 70);
						$headers = 'From: SurrealRanch<admin@surrealranch.ca>';
						if(mail($email, 'New Registration', $message, $headers))
						{
							$instructions = "Registration in progress. An email has been sent to: " . $email . ". Follow the instructions in this email to complete the registration.";
							$action = "confirm";
							$submitButtonVal = "Confirm";
						}
						else
						{
							$logmsg .= " FAILED confirmation email";
							$action = "register";
							$logger->error($logmsg . " for " . $email);
						}
						
						$message = "User: " . $nickname . " with email: " . $email . " Just Registered\n"; 
						$message = wordwrap($message, 70);
						$headers = 'From: twiegand@surrealranch.ca';
						$subject = 'New Member: ' . $nickname;
						if(mail('twiegand@rogers.com', $subject, $message, $headers))
						{
						}
						else
						{
							echo "FAILED mail";
						}

						
						//$dao->closeConnection();
						
						try
						{
							$pathDAO = new GZMYSQLPathDAO($dbConn);
							$paths = new GZPaths($pathDAO, DOC_FOLDER);
							$path_id = $paths->addNewPath($user_id, $nickname);
							$logger->debug("path_id[" . $path_id . "]");
						}
						catch(Exception $x)
						{
							$logger->error($x->getMessage());
						}
					}
					catch(Exception $ex)
					{
						$str = $ex->getMessage();
						if(strstr($str,"Duplicate entry"))
						{
							$logmsg = "The email account: " . $email . " is already registered.";
						}
						else
						{
							$logmsg = $str;
						}
						$action = "register";
					}
				}
			}
			break;
		}
		case "confirm":
		{
			$logger->debug("confirmcode: " . $confirmcode);
			if(strlen($confirmcode))
			{
				$dao = new  GZMYSQLUserDAO($dbConn);
				$users = new GZUsers($dao);
				if($users->processConfirmationKey($confirmcode))
				{
					$logger->debug("goToHere(" . $target . ")");
					goToHere($target);
				}
				else
				{
					$logger->debug("mode:" . $mode);
					if($mode == "email")
					{
						$logger->debug("goToHere(" . $target . ")");
						goToHere($target);
					}
				}
			}
			else
			{
				$logger->debug("DEBUG1");

				$instructions = "Registration in progress. An email has been sent to: " . $email . ". Follow the instructions in this email to complete the registration.";
				$logmsg = "Paste Confirmation code from email into the empty text area above.";
				$submitButtonVal = "Confirm";
			}
			break;
		}
		default:
		{
			$action = "register";
			break;
		}
	}

	
	

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
<link href="/ranch.ico" rel="shortcut icon" type="image/x-icon" />
<title>Surreal Ranch Registration</title>
<link href="css/navbar.css" rel="stylesheet" type="text/css" />
<style type="text/css">
body {
	background-color: #000000;
}
.page {
	margin: 10px auto 10px auto;
	border: thin solid #C0C0C0;
	background-color: #FFFFFF;
	font-family: Georgia, "Times New Roman", Times, serif;
	font-size: 1.3em;
	padding: 30px;
	top: 0;
	left: 0;
	width: 900px;
	height: auto;
}
i {
	color: #6666FF;
	font-size: small;
}
.login_form td {
	padding: 10px;
	max-width:150px;
}
#login_container {
	float: left;
	padding: 10px;
	margin: 40px;
	background-color: #FFF2FF;
	border: 1px #9966FF solid;
	
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

<div class="page" style="height: 580px">
	<div class="page-container">
		<div class="nav-bar">
			<!-- Navigation Drop-down menus -->
			<div class="nav2">
				<ul>
					<li><a href="index.php">Home</a></li>
				</ul>
				<ul>
					<li><a href="#">Artists</a>
					<ul>
						<li><a href="http://wnpope.ca/" target="_blank">
						<img alt="BackOfMembrane28" src="images/028B.png" style="border: thin black solid" />
						<img alt="FrontOfMembrane28" src="images/028F.png" style="border: thin black solid" /> 
						W.N.Pope</a></li>
						<li><a href="http://www.rubyewen.ca/" target="_blank">
						<img alt="Toy Box 163" src="images/ToyBox163.jpg" style="border: thin black solid" />
						<img alt="Toy Box 167" src="images/ToyBox167.jpg" style="border: thin black solid" /> 
						Ruby Ewen</a></li>
					</ul>
					</li>
				</ul>
				<ul>
					<li><a href="DeepZoom/index.html">Silverlight Zone</a></li>
				</ul>
				<ul>
					<li><a href="#">Misc.</a>
					<ul>
						<li><a href="http://surrealranch.ca/WPBlog/">Blog</a></li>
					</ul>
					</li>
				</ul>
			</div>
		</div>
	</div>
	<div id="login_container">
		<?php if($action == "register")
		{
			echo "<i>To Access More</i><br />";
			echo "<h5>Register Now!</h5><br /><br /><br />";
		}?>
		<form action="Registration.php" enctype="application/x-www-form-urlencoded" method="post">
			<input name="target" type="hidden" value="<?php echo $target; ?>" />
			<input name="action" type="hidden" value="<?php echo $action; ?>" />
			<table class="login_form">
				<?php if(strlen($instructions))
				{
					echo '<tr><td colspan="2">' . $instructions . '</td></tr>';
				}?>
				<tr>
					<td>Nick Name:</td>
					<td><input name="nickname" type="text" value="<?php echo $nickname; ?>" /></td>
				</tr>
				<tr>
					<td>Email:</td>
					<td><input name="email" type="text" value="<?php echo $email; ?>" /></td>
				</tr>
				<?php
				if($action == "register")
				{
					echo <<<EON
					<tr style="margin-bottom:0px; padding-bottom:0px">
						<td style="margin-bottom:0px; padding-bottom:0px">Password:</td>
						<td style="margin-bottom:0px; padding-bottom:0px">
						<input id="pswrd" name="pswrd" type="password" /></td>
					</tr>
					<tr style="margin-top:0px; padding-top:0px">
						<td  style="margin-top:0px; padding-top:0px"><i>Confirm Password:</i></td>
						<td  style="margin-top:0px; padding-top:0px">
						<input  style="margin-top:0px; padding-top:0px" id="pswrd2" name="pswrd2" type="password" /></td>
					</tr>
EON;
				
				}
				elseif($action == "confirm")
				{
					echo <<<EOM
					<tr>
						<td>Confirmation Code:</td>
						<td><input name="confirmcode" type="text" /></td>
					</tr>
EOM;
				}
				?>
				<tr>
					<td colspan="2">
						<span class="loginmsg"><?php echo $logmsg ?></span>
						<input style="float: right" type="submit" value="<?php echo $submitButtonVal; ?>" />
					</td>
				</tr>
				<tr><td></td><td></td></tr>
				
			</table>
		</form>
	</div>
	<div style="float: right; width: 291px;">
		<img alt="Quilting Dreams" height="400" longdesc="Quilting Dreams" src="images/QuiltingDreams.jpg" style="border-style: solid; border-color: #000000; margin: 10px 10px 10px 0px" width="291" />
		<i>Quilting Dreams by Tom Wiegand</i> </div>
</div>

</body>

</html>
