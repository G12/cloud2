<?php
require_once("lib/Session.php");

require_once("lib/Log4me.php");
require_once("lib/FileSys.php");

$dbConn = new GZDBConn(DB_SERVER, DB_USERNAME, DB_PW, DB_NAME);
$sessionWrap = new GZSessionsWrapper($dbConn);
$user = $sessionWrap->validate();
$user_name = $user->get(GZUser::NAME);
$security_level = $user->get(GZUser::SECURITYLEVEL);
$user_id = $user->getPrimary();
$strLogout = '<a href="http://' . $sessionWrap->getLogOutURL() . '" >Log Out</a>';

$logger = new Log4Me(Log4me::INFO,"log.txt");
$logger->setContext("utilities", $_SERVER['PHP_SELF']);

//Create the Users object
$users = new Users();

$email = "";
$msg = "";
$successMsg = "";
$logger->debug("DEBUG OKAY");
if(isset($_POST['email'])) {
    //$logger->debug("email = " . $_POST['email']);
    $email = $_POST['email'];
    $row = array('email' => $email);
    if(empty($email) && $email != "0") //The value "0" is considered empty weird eh!
    {
        $msg = "email is empty";
    }
    else
    {
        $retArray = $users->addUser($_POST);
        //Returns an array with ('user_id' => $user_id, 'status' => 'SUCCESS 0r ERROR', 'msg' => 'Some error message')
        if($retArray['status'] == "ERROR")
        {
            $msg = $retArray['msg'];
        }
        else if($retArray['status'] == "SUCCESS")
        {
            $successMsg = $retArray['msg'];
        }
    }
}

$userRecordSet = $users->getUsers();
$groupsRecordSet = $users->getGroups();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="favicon.ico">

    <title>Utilities</title>

    <!-- Bootstrap core CSS -->
    <link href="bootstrap-3.3.6-dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <link href="bootstrap-3.3.6-dist/assets/css/ie10-viewport-bug-workaround.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="bootstrap-3.3.6-dist/sticky-footer-navbar.css" rel="stylesheet">

    <!-- Just for debugging purposes. Don't actually copy these 2 lines! -->
    <!--[if lt IE 9]><script src="bootstrap-3.3.6-dist/assets/js/ie8-responsive-file-warning.js"></script><![endif]-->
    <script src="bootstrap-3.3.6-dist/assets/js/ie-emulation-modes-warning.js"></script>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body>

<!-- Fixed navbar -->
<nav class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="#">Utilities(<?php echo $user_name; ?>)</a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li class="active"><a href="index.php">Explorer</a></li>
                <li><?php echo $strLogout; ?></li>
                <li><a href="#contact">Help</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Dropdown <span class="caret"></span></a>
                    <ul class="dropdown-menu">
                        <li><a href="#">Action</a></li>
                        <li><a href="#">Another action</a></li>
                        <li><a href="#">Something else here</a></li>
                        <li role="separator" class="divider"></li>
                        <li class="dropdown-header">Nav header</li>
                        <li><a href="#">Separated link</a></li>
                        <li><a href="#">One more separated link</a></li>
                    </ul>
                </li>
            </ul>
        </div><!--/.nav-collapse -->
    </div>
</nav>

<!-- Begin page content -->
<div class="container">
    <div class="page-header">
        <h1>Add new User</h1>
    </div>

    <?php
        if($msg != "") {
            echo '<div class="alert alert-danger" role="alert">' . $msg . '</div>';
        }
        if($successMsg != "")
        {
            echo '<div class="alert alert-success" role="alert">' . $successMsg . '</div>';
        }
    ?>

    <p class="lead">To add new user enter information; then submit.</p>
    <form name="submit_user" method="post" action="utilities.php">
    <div class="panel panel-default">
        <!-- Default panel contents -->
        <div class="panel-heading">Add New User</div>
        <div class="panel-body">

            <div class="input-group margin-bottom">
                <input type="submit" value="Submit" class="form-control" id="submit_user">
            </div>
            <div class="input-group margin-bottom">
                <div class="input-group-btn">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action <span class="caret"></span></button>
                    <ul class="dropdown-menu">
                        <li><a href="#">Member Status</a></li>
                        <li><a href="#">Editor</a></li>
                        <li><a href="#">Administrator</a></li>
                    </ul>
                </div>
                <input type="text" class="form-control" aria-label="...">
            </div>

            <div class="input-group margin-bottom">
                <span class="input-group-addon" id="name_label">User Name</span>
                <input type="text" class="form-control" id="name" name="name" aria-describedby="name_label" required>
            </div>
            <div class="input-group margin-bottom">
                <span class="input-group-addon" id="email_label">Email</span>
                <input type="email" class="form-control" id="email" name="email" aria-describedby="email_label" required>
            </div>
            <div class="input-group margin-bottom">
                <span class="input-group-addon" id="pswd_label">Password</span>
                <input type="password" class="form-control" id="pswd" name="pswd" aria-describedby="pswd_label" required>
            </div>
            <div class="input-group">
                <span class="small">Optional</span>
                <hr>
            </div>
            <div class="input-group margin-bottom">
                <span class="input-group-addon" id="question_label">Security Question</span>
                <input type="text" class="form-control" id="question" name="question" aria-describedby="question_label">
            </div>
            <div class="input-group margin-bottom">
                <span class="input-group-addon" id="answer_label">Security Answer</span>
                <input type="text" class="form-control" id="answer" name="answer" aria-describedby="answer_label">
            </div>
        </div>
    </div>
    <div class="panel panel-default">
        <!-- Default panel contents -->
        <div class="panel-heading">Select Groups this user will belong to.</div>
        <div class="panel-body">
            <?php
            if($groupsRecordSet)
            {
                foreach($groupsRecordSet as $row) {
                    $mandatory = "";
                    $group_id = $row['group_id'];
                    if($group_id == 0)
                    {
                        $mandatory = 'checked="checked"';
                    }
                    echo '<div class="input-group margin-bottom">' .
                    '<span class="input-group-addon">' .
                        '<input ' . $mandatory . ' id="' . $group_id . '" name="group_set[]" value = "' . $group_id . '" type="checkbox" aria-describedby="label' . $group_id . '">' .
                    '</span>' .
                    '<label id="label' . $group_id . '" class="form-control" for="' . $group_id . '">' . $row['name'] . ': ' . $row['description'] . '</label>' .
                    '</div>';
                }
            }
            ?>
        </div>
    </div>
    </form>

    <div class="page-header">
        <h1>Add new Directory for User</h1>
    </div>

    <p class="lead">To add a new directory; enter directory name then select user from list.</p>
    <form id="directory_vals" name="submit_directory" method="post" action="utilities.php">
        <div class="panel panel-default">
            <!-- Default panel contents -->
            <div class="panel-heading">Enter Directory Name</div>
            <div class="panel-body">
                <div class="input-group margin-bottom">
                    <span class="input-group-addon" id="directory_name_label">Directory Name</span>
                    <input type="text" class="form-control" id="directory_name" name="directory_name" aria-describedby="directory_name_label">
                </div>
            </div>
        </div>
        <div class="panel panel-default">
            <!-- Default panel contents -->
            <div class="panel-heading">Select root Permission for this user.</div>
            <div class="panel-body">

                <?php
                if($groupsRecordSet)
                {
                    foreach($groupsRecordSet as $row) {
                        $group_id = $row['group_id'];
                        if($group_id != 0)
                        {
                            $mandatory = "";
                            if($group_id == 3)
                            {
                                $mandatory = 'checked="checked"';
                            }
                            echo '<div class="input-group margin-bottom">' .
                                '<span class="input-group-addon">' .
                                '<input name="user_permission" id="uid_' . $group_id . '" ' . $mandatory .
                                    ' data-group_id="' . $group_id . '" value="' . $row['role'] .
                                    '" type="radio" aria-describedby="uid_label' . $group_id . '">' .
                                '</span>' .
                                '<label id="uid_label' . $group_id . '" class="form-control" for="uid_' . $group_id . '">' . $row['name'] . ': ' . $row['description'] . '</label>' .
                                '</div>';
                        }
                    }
                }
                ?>
             </div>
        </div>
        <div class="panel panel-default">
            <!-- Default panel contents -->
            <div class="panel-heading">Select Permission Groups with access to this directory.</div>
            <div class="panel-body">
                <?php
                if($groupsRecordSet)
                {
                    foreach($groupsRecordSet as $row) {
                        $mandatory = "";
                        $group_id = $row['group_id'];
                        if($group_id == 0)
                        {
                            $mandatory = 'checked="checked"';
                        }
                        echo '<div class="input-group margin-bottom">' .
                            '<span class="input-group-addon">' .
                            '<input ' . $mandatory . ' id="id_' . $group_id . '" name="group_set2[]" value = "' . $group_id .
                                '" data-role="' . $row['role'] . '" type="checkbox" aria-describedby="id_label' . $group_id . '">' .
                            '</span>' .
                            '<label id="id_label' . $group_id . '" class="form-control" for="id_' . $group_id . '">' . $row['name'] . ': ' . $row['description'] . '</label>' .
                            '</div>';
                    }
                }
                ?>
            </div>
        </div>

    </form>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Select User for new Directory</h3>
        </div>
        <div class="panel-body">
            <div id="user_list" class="list-group">
                <?php
                if($userRecordSet)
                {
                    foreach($userRecordSet as $row) {
                        echo '<a href="#" id="' . $row["user_id"] . '" href="#" class="list-group-item"><strong>' . $row["name"] . ': </strong><i>' . $row["email"] . '</i></a>';
                    }
                }
                else
                {
                    echo '<a class="list-group-item active">' .
                        'User List is Not available' .
                        '</a>';
                }
                ?>
            </div>
        </div>
    </div>

</div>

<footer class="footer">
    <div class="container">
        <p class="text-muted">Place sticky footer content here.</p>
    </div>
</footer>


<!-- Bootstrap core JavaScript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script>window.jQuery || document.write('<script src="bootstrap-3.3.6-dist/assets/js/vendor/jquery.min.js"><\/script>')</script>
<script src="bootstrap-3.3.6-dist/js/bootstrap.min.js"></script>
<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
<script src="bootstrap-3.3.6-dist/assets/js/ie10-viewport-bug-workaround.js"></script>
<script src="js/utilities.js"></script>
</body>
</html>
