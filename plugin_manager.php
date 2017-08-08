<?php
/*
 * jQuery File Upload Plugin PHP Example 5.14
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2010, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 */

error_reporting(E_ALL | E_STRICT);
require('UploadHandler.php');
$folder = 'public';
if(isset($_GET['folder']))
{
	$folder= $_REQUEST['folder'];
	$user_name = $_REQUEST['user_name'];
}
$upload_handler = new UploadHandler($user_name, $folder);
