<?php

require("lib/Session.php");
$dbConn = new GZDBConn(DB_SERVER, DB_USERNAME, DB_PW, DB_NAME);
$sessionWrap = new GZSessionsWrapper($dbConn);
$user = $sessionWrap->validate();
$user_name = $user->get(GZUser::NAME);
$security_level = $user->get(GZUser::SECURITYLEVEL);
$user_id = $user->getPrimary();
$strLogout = '<a href="http://' . $sessionWrap->getLogOutURL() . '" >Log Out</a>';

?>
<!DOCTYPE html>
<html>
<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="favicon.ico">

    <title>File Manager</title>

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



    <link rel="stylesheet" href="dist/themes/default/style.min.css" />
    <link rel="stylesheet" href="css/tree.css" />
    <link rel="stylesheet" href="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css" />

    <!-- Add fancyBox -->
    <link rel="stylesheet" href="../fancybox/source/jquery.fancybox.css?v=2.1.5" type="text/css" media="screen" />
    <!-- Optionally add helpers - button, thumbnail and/or media -->
    <link rel="stylesheet" href="../fancybox/source/helpers/jquery.fancybox-buttons.css?v=1.0.5" type="text/css" media="screen" />
    <link rel="stylesheet" href="../fancybox/source/helpers/jquery.fancybox-thumbs.css?v=1.0.7" type="text/css" media="screen" />

    <link rel="stylesheet" href="css/index.css" />
    <link rel="stylesheet" href="css/table.css" />

    <link href="css/navbar.css" rel="stylesheet" type="text/css" />


    <style>
    </style>
    <script type="text/javascript">
        var user_name = "<?=$user_name?>";
        var user_id = "<?=$user_id?>";
    </script>
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
            <a class="navbar-brand" href="#">Explorer(<?php echo $user_name; ?>)</a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li class="active"><a href="../index.html">Home</a></li>
                <li><?php echo $strLogout; ?></li>
                <?php
                if($security_level > 1)
                {
                    echo "<li><a href=\"utilities.php\">Utilities</a></li>";
                }
                ?>
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



<div id="container" role="main">
    <div id="tree"></div>
    <div id="slider"></div>
    <div id="data">
        <div class="content code" style="display:none;"><textarea id="code" readonly></textarea></div>
        <div class="content folder" style="display:none;"></div>
        <div id="default_content" class="content default" >Select a file from the tree.</div>
        <div class="content profile" >
            <form id=image_form method="post" action="">
                <fieldset class="vertical_list">
                    <legend>Artwork Information</legend>
                    <div>
                        <button type="button" id="profile_edit">Edit</button>
                        <button type="submit" id="profile_save_changes">Save Changes</button>
                    </div>
                    <div class="img_box">
                        <a id="profile_card" class="fancybox" rel="group" href="cards/giz/Places/Early Cape Breton/Freaky Sunset Over The Barachoi.jpg">
                            <figure>
                                <img id="profile_thumb" class="portrait" alt="test" src="thumbs/giz/Places/Early Cape Breton/Freaky Sunset Over The Barachoi.jpg">
                            </figure>
                            <figcaption id="profile_caption">giz/Places/Cape Breton/Whacky Sunset.jpg</figcaption>
                        </a>
                    </div>
                    <div class="img_info">
                        <ul>
                            <li>
                                <label for="artwork_title" id="artwork_title_label">Title</label>
                                <input id="artwork_title" name="artwork_title" type="text" required data-dirty="false">
                            </li>
                            <li>
                                <label for="artwork_medium" id="artwork_medium_label">Medium</label>
                                <input id="artwork_medium" name="artwork_medium" type="text" required data-dirty="false">
                            </li>
                            <li>
                                <label for="artwork_year" id="artwork_year_label">Year</label>
                                <input id="artwork_year" name="artwork_year" type="text" data-dirty="false">
                            </li>
                            <li>
                                <label for="artwork_height" id="artwork_height_label" >Height</label>
                                <input id="artwork_height" name="artwork_height" type="number" data-dirty="false">
                            </li>
                            <li>
                                <label for="artwork_width" id="artwork_width_label" >Width</label>
                                <input id="artwork_width" name="artwork_width" type="number" data-dirty="false">
                            </li>
                            <li>
                                <label for="artwork_units" id="artwork_units_label">Units</label>
                                <select id="artwork_units" name="artwork_units">
                                    <option value="pixels">Pixels</option>
                                    <option value="inches">Inches</option>
                                    <option value="centimeters">Centimeters</option>
                                </select>
                            </li>
                        </ul>
                    </div>
                </fieldset>
                <fieldset class="vertical_list">
                    <legend>Extra Information</legend>
                    <ul>
                        <li>
                            <label for="keywords" id="keywords_label">Key Words</label>
                            <textarea id="keywords" name="keywords" data-dirty="false"></textarea>
                        </li>
                        <li>
                            <label for="description" id="description_label">Description</label>
                            <textarea id="description" name="description" data-dirty="false"></textarea>
                        </li>
                    </ul>
                </fieldset>
            </form>
        </div>
    </div>
</div>



<!-- Dialog Boxes -->
<div id="upload_form" title="Upload Images" class="hide">
    <input type="file" id="upload_files" name="files[]" multiple>
    <div id="msg"></div>
</div>
<div id="busy_box" title="Getting Information">
    <div id="wait_msg"></div>
    <img alt="Loading" src="ajax-loader.gif">
</div>

<!-- Permissions -->
<div id="permissions_form" title="Permissions" class="hide">
    <div id="permission_info"></div>
    <div id="permission_table">
        <table class="table table-striped table-header-rotated">
            <thead>
            <tr>
                <!-- First column header is not rotated -->
                <th></th>
                <!-- Following headers are rotated -->
                <th class="rotate-45"><div><span>View</span></div></th>
                <th class="rotate-45"><div><span>Create</span></div></th>
                <th class="rotate-45"><div><span>Rename</span></div></th>
                <th class="rotate-45"><div><span>Delete</span></div></th>
                <th class="rotate-45"><div><span>Upload</span></div></th>
                <th class="rotate-45"><div><span>Download</span></div></th>
                <th class="rotate-45"><div><span>Permissions</span></div></th>
                <th></th>
            </tr>
            </thead>
            <tbody id="permissions_data">
            <tr id="groups_top" class="seperator">
                <th class="row-header">Groups</th>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td><button id="add_group_btn">Add</button></td>
            </tr>
            <tr>
                <th>&nbsp;</th>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr id="users_top" class="seperator">
                <th class="row-header">Users</th>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td><button id="add_user_btn">Add</button></td>
            </tr>
            </tbody>
        </table>
    </div>

    <button id="save_permissions_btn" class="hide">Save Changes</button>

</div>
<!-- Javascript Imports -->
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<!-- JSTree distribution -->
<script src="dist/jstree.min.js"></script>
<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/jquery-ui.min.js"></script>
<!--File Upload -->
<script src="js/file_upload/vendor/jquery.ui.widget.js"></script>
<script src="js/file_upload/jquery.iframe-transport.js"></script>
<script src="js/file_upload/jquery.fileupload.js"></script>
<!-- Add mousewheel plugin (this is optional) -->
<script src="../fancybox/lib/jquery.mousewheel-3.0.6.pack.js"></script>
<!-- Add fancyBox -->
<script src="../fancybox/source/jquery.fancybox.pack.js?v=2.1.5"></script>
<!-- Optionally add helpers - button, thumbnail and/or media -->
<script src="../fancybox/source/helpers/jquery.fancybox-buttons.js?v=1.0.5"></script>
<script src="../fancybox/source/helpers/jquery.fancybox-media.js?v=1.0.6"></script>
<script src="../fancybox/source/helpers/jquery.fancybox-thumbs.js?v=1.0.7"></script>
<!-- My javascript -->
<script src="js/profile.js?ver=1.000.002"></script>
<script src="js/index.js?ver=1.000.002"></script>


<!-- Bootstrap core JavaScript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
<!--<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>-->
<!--<script>window.jQuery || document.write('<script src="bootstrap-3.3.6-dist/assets/js/vendor/jquery.min.js"><\/script>')</script>-->
<script src="bootstrap-3.3.6-dist/js/bootstrap.min.js"></script>
<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
<script src="bootstrap-3.3.6-dist/assets/js/ie10-viewport-bug-workaround.js"></script>

</body>
</html>