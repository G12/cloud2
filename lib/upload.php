<?php

	require_once("Log4me.php");
	
	$logger = new Log4Me(Log4me::DEBUG,"log.txt");
	$logger->setContext("UploadTest", $_SERVER['PHP_SELF']); 
	$logger->debug("MADE IT HERE");
	
	$data = array();
	
	$logger->debug(print_r($_REQUEST,true));
 
	if(isset($_POST['files']))
	{  
		
		
		$error = false;
		$files = array();
	 
		$uploaddir = './uploads/';
		foreach($_FILES as $file)
		{
			$logger->debug("filename:" . $file['name']);
			//if(move_uploaded_file($file['tmp_name'], $uploaddir .basename($file['name'])))
			//{
			//	$files[] = $uploaddir .$file['name'];
			//}
			//else
			//{
			//	$error = true;
			//}
		}
		//$data = ($error) ? array('error' => 'There was an error uploading your files') : array('files' => $files);
	}
	else
	{
		//$data = array('success' => 'Form was submitted', 'formData' => $_POST);
	}
	 
	//echo json_encode($data);


	header('Content-Type: application/json; charset=utf8');
	echo json_encode(array('status' => 'OK'));
?>