<?php

require_once('Session.php');
require_once('User.php');
require_once("FileSys.php");

$dbConn = new GZDBConn(DB_SERVER, DB_USERNAME, DB_PW, DB_NAME);
$session_dao = new GZMYSQLSessionDAO($dbConn);
$sessions = new GZSessions($session_dao);
$user = $sessions->statusHelper();
if(!is_null($user))
{
	//Restrict access to only files in the directory this file is in
	//ini_set('open_basedir', dirname(__FILE__) . DIRECTORY_SEPARATOR);
	if(isset($_GET['operation'])) {
		
			//display files for the child directory pics 
			$fs = new FileSys('cloud', 'pics', $user);
		try {
			$rslt = null;
			switch($_GET['operation']) {
				case 'get_node':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? $_GET['id'] : '/';
					$rslt = $fs->lst($node, (isset($_GET['id']) && $_GET['id'] === '#'));
					break;
				case "get_content":
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? $_GET['id'] : '/';
					$rslt = $fs->data($node);
					break;
				case 'create_node':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? $_GET['id'] : '/';
					$rslt = $fs->create($node, isset($_GET['text']) ? $_GET['text'] : '', (!isset($_GET['type']) || $_GET['type'] !== 'file'));
					break;
				case 'rename_node':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? $_GET['id'] : '/';
					$rslt = $fs->rename($node, isset($_GET['text']) ? $_GET['text'] : '');
					break;
				case 'delete_node':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? $_GET['id'] : '/';
					$rslt = $fs->remove($node);
					break;
				case 'move_node':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? $_GET['id'] : '/';
					$parn = isset($_GET['parent']) && $_GET['parent'] !== '#' ? $_GET['parent'] : '/';
					$rslt = $fs->move($node, $parn);
					break;
				case 'copy_node':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? $_GET['id'] : '/';
					$parn = isset($_GET['parent']) && $_GET['parent'] !== '#' ? $_GET['parent'] : '/';
					$rslt = $fs->copy($node, $parn);
					break;
				case 'update_info':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? $_GET['id'] : '/';
					$rslt = $fs->update_info($node);
					break;
				case 'permission_info':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? $_GET['id'] : '/';
					$rslt = $fs->permission_info($node);
					break;
				case 'update_mods':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? $_GET['id'] : '/';
					$rslt = $fs->update_mods($node, isset($_GET['mods']) ? $_GET['mods'] : '');
					break;
				default:
					throw new Exception('Unsupported operation: ' . $_GET['operation']);
					break;
			}
			header('Content-Type: application/json; charset=utf8');
			echo $rslt;
			//echo json_encode($rslt);
		}
		catch (Exception $e) {
			header($_SERVER["SERVER_PROTOCOL"] . ' 500 Server Error');
			header('Status:  500 Server Error');
			echo $e->getMessage();
		}
	}
}
?>
