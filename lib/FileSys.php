<?php

require_once("Log4me.php");
require_once("ImagesX.php");
require_once('User.php');

require_once("image.php");
require_once("DbHelper.class.php");
require_once("dbinfo.php");

define('DEFAULT_LANG','en');

class FileSys
{
	private $logger;
	
	private $user_id; //id of the person accessing the file system
	private $mods;		//mask containing modules user wishes to use
	private $folders; //Folders object
	private $images;	//Images object
	//private $artworks; //Artwork object
	private $image_pages;
	
	protected $base = null; //ie /hermes/.../nf.surrealranch/public_html/surrealranch.ca/cloud/pics
	protected $base_path; //ie /hermes/.../nf.surrealranch/public_html/surrealranch.ca/cloud
	protected $folder; //ie pics
	protected $base_folder; //ie cloud

	//realpath() expands all symbolic links and resolves references to '/./', '/../' and extra '/' characters in the input path and returns the canonicalized absolute pathname.
	protected function real($path) {
		$temp = realpath($path);
		if(!$temp) { throw new Exception('Path does not exist: ' . $path); }
		return $temp;
	}
	//Replace all / forward slashs with the directory seperator on this server
	protected function path($id) {
		$id = str_replace('/', DIRECTORY_SEPARATOR, $id);
		$id = trim($id, DIRECTORY_SEPARATOR);
		$id = $this->real($this->base . DIRECTORY_SEPARATOR . $id);
		return $id;
	}
	//Replace all directory seperator symbols with / forward slash
	protected function id($path) {
		$path = $this->real($path);
		$path = substr($path, strlen($this->base));
		$path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
		$path = trim($path, '/');
		return strlen($path) ? $path : '/';
	}

	public function __construct($base_folder, $folder, GZField $user) {
		
		$this->logger = new Log4Me(Log4me::DEBUG,"log.txt");
		$this->logger->setContext("FileSys", $_SERVER['PHP_SELF']); 
		$this->user_id = $user->getPrimary();
		
		$this->base_folder = $base_folder;
		//$this->logger->debug("base_folder = " . $base_folder);

		$local_dir = "";
		if(strlen(LOCAL_DIR) != 0) {
			$local_dir = LOCAL_DIR . DIRECTORY_SEPARATOR;
		}
		$base = $_SERVER['DOCUMENT_ROOT'] . $local_dir . $base_folder;
		//$this->logger->debug("base = " . $base);

		$this->base = $this->real($base . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR);
		//$this->logger->debug("real base = " . $this->base);

		$this->base_path = $this->real($base);
		//$this->logger->debug("base_path = " . $this->base_path);
		
		$this->folder = $folder;
		//$this->logger->debug("folder = " . $folder);

		//Create the Folders object
		$this->folders = new Folders($this->user_id);
		
		//Create the Images object
		$this->images = new Images($this->user_id, $this->base_folder); //, $folder);
		
		//Create the Artworks object
		//$this->artworks = new Artworks($this->user_id);
		
		//Create ImagePages object
		$this->image_pages = new ImagePages($this->user_id, DEFAULT_LANG);
		
		//TODO Redundant see function def of real(...) above
		if(!$this->base) { throw new Exception('Base directory does not exist'); } 
	}
	
	public function getBasePath()
	{
		return $this->base_path;
	}
	
	private function executeUserModulesOnImage($image, $path)
	{

	}
	
	//MenuOps case get_node:
	//Returns json representation of the directory specified by id.
	public function lst($id, $with_root = false) {
		
		$dir = $this->path($id);
		$folder = $this->folders->getFolderFor($id, NULL);
		$permissions = $this->folders->getEffectivePermissionsFor($folder);
		if($permissions && AC_VIEW)
		{
			$lst = @scandir($dir);
			if(!$lst) { throw new Exception('Could not list path: ' . $dir); }
			$res = array();
			foreach($lst as $item) {
				if($item == '.' || $item == '..' || $item === null) { continue; }
				
				$tmp = preg_match('([^ ,a-zа-я-_0-9.]+)ui', $item);
				//$tmp = preg_match('/[^ ,a-zA-Z-_0-9.]+/', $item);
				if($tmp === false || $tmp === 1)
				{
					continue;
				}
				
				if(is_dir($dir . DIRECTORY_SEPARATOR . $item))
				{
					$id_ = $this->id($dir . DIRECTORY_SEPARATOR . $item);
					$folder_ = $this->folders->getFolderFor($id_, $id);
					$permissions_ = $this->folders->getEffectivePermissionsFor($folder_);

					//$testStr = "id_ " . $id_ . " folder_ " . $folder_ . " permissions_ " . $permissions_;
					//$this->logger->debug("DIR PERMISSIONS: " . $testStr);

					if($permissions_ && AC_VIEW)
					{
						$res[] = array('text' => $item, 'children' => true,  'id' => $id_, 'icon' => 'folder', 'permissions' => $permissions_, 'mods' => $folder_['mods']);
					}
				}
				else
				{
					//don't show files starting with .
					if(!preg_match('/^\./',$item))
					{
						$id_ = $this->id($dir . DIRECTORY_SEPARATOR . $item);
						$dir_ = $this->path($id_);
						$file_obj = new File($dir_);
	
						$res[] = array('text' => $item, 'children' => false, 'id' => $id_, 'type' => 'file', 'icon' => 'file file-'.strtolower(substr($item, strrpos($item,'.') + 1)), 'permissions' => $permissions);
						
						if($file_obj->isImage())
						{
							//Update database
							if(!$this->images->testFor($id_))
							{
								$img = new Image($this->base_path,$dir_,$id_);
								//If we want XMP and Exif data call
								$img->getFileInfo();
								$count = $this->images->addImage($img, $id_, $folder);
							}
						}
					}
				}
			}
			if($with_root && $this->id($dir) === '/') {
				
				//$this->logger->debug("WITH ROOT basename: " . basename($this->base));
				
				$res = array(array('text' => basename($this->base), 'children' => $res, 'id' => '/', 'icon'=>'folder', 'permissions' => $permissions, 'state' => array('opened' => true, 'disabled' => true)));
				
			}
			
			//$this->logger->debug("DEBUG1: " . json_encode($res));
			return json_encode($res);
		}
		else
		{
			if($folder['path'] == '/')//$id == "#")
			{
				$this->logger->debug("YES NO View permission Folder path: " . $folder['path']);	
				//$res = array(array(('id' => '@@@'));
				//return json_encode($res);
			}
			else
			{
				$this->logger->debug("NO View permission Folder path: " . $folder['path']);	
			}
		}
	}
	
	//gets directory list for the selected folder
	//displays in listview
	private function getDirectoryData($dir, $folder)
	{
		$lst = @scandir($dir);
		if(!$lst) { throw new Exception('Could not list path: ' . $dir); }
		$res = array();
		foreach($lst as $item) {
			if($item == '.' || $item == '..' || $item === null) { continue; }
			
			$tmp = preg_match('([^ ,a-zа-я-_0-9.]+)ui', $item);
			//$tmp = preg_match('/[^ ,a-zA-Z-_0-9.]+/', $item);
			if($tmp === false || $tmp === 1)
			{
				continue;
			}
			
			if(is_dir($dir . DIRECTORY_SEPARATOR . $item))
			{

				//Only show images that can be displayed with fancybox
				/*
				$res[] = array('name' => $item, 'imgURL' => 'http://surrealranch.ca/cloud/thumbs/folder.png', 'text' => $item, 'children' => true,  'id' => $this->id($dir . DIRECTORY_SEPARATOR . $item), 'icon' => 'folder');
				*/
			}
			else
			{
				//don't show files starting with .
				if(!preg_match('/^\./',$item))
				{
					$id_ = $this->id($dir . DIRECTORY_SEPARATOR . $item);
					$dir_ = $this->path($id_);
					
					$file_obj = new File($dir_);
					if($file_obj->isImage())
					{
						$img = new Image($this->base_path,$dir_,$id_);
						$res[] = array('height' => $img->getHeight(), 'width' => $img->getWidth(), 'name' => $img->getName(), 'imgURL' => $img->getThumbURL(), 'cardURL' => $img->getCardURL(), 'text' => $item, 'children' => false, 'id' => $id_, 'type' => 'file', 'icon' => 'file file-'.strtolower(substr($item, strrpos($item,'.') + 1)));
						
						//Update database
						if(!$this->images->testFor($id_))
						{
							//If we want XMP and Exif data call
							$img->getFileInfo();
							$count = $this->images->addImage($img, $id_, $folder);
/*							if($count != -1)
							{
								$this->artworks->addArtWork($img, $id_, $folder['mods']);
							}
*/
						}
					}
					else if($file_obj->isPdf())
					{
						//Only show images that can be displayed with fancybox
						/*
						$pdf = new GenericFile($this->base_path,$dir,$id);
						$res[] = array('name' => $item, 'imgURL' => 'http://surrealranch.ca/cloud/thumbs/default.png', 'cardURL' => 'http://surrealranch.ca/cloud/cards/default.png', 'text' => $item, 'children' => false, 'id' => $id_, 'type' => 'file', 'icon' => 'file file-'.strtolower(substr($item, strrpos($item,'.') + 1)));
						*/
					}
					else
					{
						//Only show images that can be displayed with fancybox
						/*
						$res[] = array('name' => $item, 'imgURL' => 'http://surrealranch.ca/cloud/thumbs/default.png', 'cardURL' => 'http://surrealranch.ca/cloud/cards/default.png', 'text' => $item, 'children' => false, 'id' => $id_, 'type' => 'file', 'icon' => 'file file-'.strtolower(substr($item, strrpos($item,'.') + 1)));
						*/
					}
				}
			}
		}
		//TODO sort this with_root thing out - probably just a cut and paste err
		//if($with_root && $this->id($dir) === '/') {
		//	$res = array(array('text' => basename($this->base), 'children' => $res, 'id' => '/', 'icon'=>'folder', 'state' => array('opened' => true, 'disabled' => true)));
		//}
		//return json_encode($res);
		return $res;
	}

	//get_content
	public function data($id) {
		if(strpos($id, ":")) {
			$id = array_map(array($this, 'id'), explode(':', $id));
			return json_encode(array('type'=>'multiple', 'content'=> 'Multiple selected: ' . implode(' ', $id)));
		}
		$dir = $this->path($id);
		if(is_dir($dir)) {
			$folder = $this->folders->getFolderFor($id, NULL);
			$dat = array('type' => 'folder', 'content' => '');
			$dat['content'] = $this->getDirectoryData($dir, $folder);
			return json_encode($dat);
			//return json_encode(array('type'=>'folder', 'content'=> $id));
		}
		if(is_file($dir)) {
			$folder = $this->folders->getFolderFor($id, NULL, true);
			$permissions = $this->folders->getEffectivePermissionsFor($folder);
			$ext = strpos($dir, '.') !== FALSE ? substr($dir, strrpos($dir, '.') + 1) : '';
			$dat = array('type' => $ext, 'permissions' => $permissions, 'content' => '');
			switch(strtolower($ext)) {
				case 'txt':
				case 'text':
				case 'md':
				case 'js':
				case 'json':
				case 'css':
				case 'html':
				case 'htm':
				case 'xml':
				case 'c':
				case 'cpp':
				case 'h':
				case 'sql':
				case 'log':
				case 'py':
				case 'rb':
				//case 'htaccess':
				case 'php':
					$dat['content'] = file_get_contents($dir);
					break;
				case 'jpg':
				case 'jpeg':
				case 'gif':
				case 'png':
				case 'bmp':
					try
					{
						$img = new Image($this->base_path,$dir,$id);
						//Update database
						if(!$this->images->testFor($id))
						{
							//If we want XMP and Exif data call
							$img->getFileInfo();
							$count = $this->images->addImage($img, $id, $folder);
						}
						$dat['content'] = $this->image_pages->getArrayData($this->images,$id);
					}
					catch (Exception $e) {
						$dat['content'] = $e->getMessage();
					}					
					break;
				case 'pdf':
					//$name = basename($id);
					//$directory = dirname($id);
					//$root_url = ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
					//$url = $root_url . '/cloud/cards/' . $directory . '/' . $name;
					$pdf = new GenericFile($this->base_path,$dir,$id);
					//scrollbar=1&amp;
					$dat['content'] = '<div id="pdf">' .
					'<object data="' . $pdf->getCardURL() . '#toolbar=1&amp;navpanes=0&amp;page=1&amp;view=FitH"' . 
        			'type="application/pdf" width="100%" height="100%">' .
					'<p>It appears you don`t have a PDF plugin for this browser. No biggie... you can ' .
					' <a href="' . $pdf->getCardURL() . '">click here to download the PDF file.</a></p>' .
					'</object></div>';
					break;
				default:
					$dat['content'] = 'File not recognized: '.$this->id($dir);
					break;
			}
			return json_encode($dat);
		}
		throw new Exception('Not a valid selection: ' . $dir);
	}
	public function create($id, $name, $mkdir = false) {

		if($id)
		{
			$dir = $this->path($id);
		}
		else //creating a user directory
		{
			$dir = $this->real($this->base);
		}
		$this->logger->debug("id: " . $id . " name: " . $name . " dir: " . $dir);

		if(preg_match('([^ a-zа-я-_0-9.]+)ui', $name) || !strlen($name)) {
			throw new Exception('Invalid name: ' . $name);
		}
		if($mkdir) {
			mkdir($dir . DIRECTORY_SEPARATOR . $name);
		}
		else {
			file_put_contents($dir . DIRECTORY_SEPARATOR . $name, '');
		}
		return json_encode(array('id' => $this->id($dir . DIRECTORY_SEPARATOR . $name)));
	}
	public function rename($id, $name) {
		$dir = $this->path($id);
		if(preg_match('([^ a-zа-я-_0-9.]+)ui', $name) || !strlen($name)) {
			throw new Exception('Invalid name: ' . $name);
		}
		$new = explode(DIRECTORY_SEPARATOR, $dir);
		array_pop($new);
		array_push($new, $name);
		$new = implode(DIRECTORY_SEPARATOR, $new);
		if(is_file($new) || is_dir($new)) { throw new Exception('Path already exists: ' . $new); }
		
		rename($dir, $new);
		
		$thumb_dir = str_replace("surrealranch.ca/cloud/pics","surrealranch.ca/cloud/thumbs",$dir);
		$thumb_new = str_replace("surrealranch.ca/cloud/pics","surrealranch.ca/cloud/thumbs",$new);
		rename($thumb_dir,$thumb_new);
		
		$card_dir = str_replace("surrealranch.ca/cloud/pics","surrealranch.ca/cloud/cards",$dir);
		$card_new = str_replace("surrealranch.ca/cloud/pics","surrealranch.ca/cloud/cards",$new);
		rename($card_dir,$card_new);
	
		//Remove Image data
		$file_obj = new File($new);
		if($file_obj->isImage())
		{
			//Remove data for old name
			$this->images->removeData($id);
		}
		//TODO rename Folder data
		return json_encode(array('id' => $this->id($new)));
	}
	public function remove($id) {
		$dir = $this->path($id);
		if(is_dir($dir)) {
			foreach(array_diff(scandir($dir), array(".", "..")) as $f) {
				$this->remove($this->id($dir . DIRECTORY_SEPARATOR . $f));
			}
			//calc this path first because this->id(dir) throws on nonexistent dir ?
			$thumbs = $this->base_path . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . $this->id($dir);
			$cards = $this->base_path . DIRECTORY_SEPARATOR . 'cards' . DIRECTORY_SEPARATOR . $this->id($dir);

			rmdir($dir);
			rmdir($thumbs);
			rmdir($cards);
			$this->folders->deleteFoldersFrom($id);
			
		}
		if(is_file($dir)) {
			unlink($dir);
			$thumb = $this->base_path . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . $id;
			unlink($thumb);
			$card = $this->base_path . DIRECTORY_SEPARATOR . 'cards' . DIRECTORY_SEPARATOR . $id;
			unlink($card);
			$this->images->deleteImage($id);
		}
		return json_encode(array('status' => 'OK'));
	}
	public function move($id, $par) {
		$dir = $this->path($id);
		$par = $this->path($par);
		$new = explode(DIRECTORY_SEPARATOR, $dir);
		$new = array_pop($new);
		$new = $par . DIRECTORY_SEPARATOR . $new;
		rename($dir, $new);
		return json_encode(array('id' => $this->id($new)));
	}
	
	public function update_info($id) {
		$dir = $this->path($id);
		if(is_file($dir)) {
			//logImageData($dir);	
		}
		//Uncomment this to populate the language table HACK
		//$lng = new Language();
		//$lng->populateTable();
		return json_encode(array('status' => 'DONE'));
	}
	
	public function permission_info($id)
	{
		$folder = $this->folders->getFolderFor($id,NULL);
		$permission_set = $this->folders->getPermissionsFor($folder);
		$result = array('status' => 'DONE', 'path' => $folder['path'], 'acl_id' => $folder['acl_id'], 'permission_set' => $permission_set);
		print_r($result,true);
		return json_encode($result);
	}

	public function download_file($id) {
		$file = $this->path($id);
		$stat = "FAIL";
		if(is_file($file)) {
			//Php code to force file download
			header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
			if(readfile($file))
			{
				$stat = "SUCCESS";
			}
			else
			{
				$this->logger->debug("FAIL download_file");
			}
		}
		return json_encode(array('status' => $stat));
	}


	public function copy($id, $par) {
		$dir = $this->path($id);
		$par = $this->path($par);
		$new = explode(DIRECTORY_SEPARATOR, $dir);
		$new = array_pop($new);
		$new = $par . DIRECTORY_SEPARATOR . $new;
		if(is_file($new) || is_dir($new)) { throw new Exception('Path already exists: ' . $new); }

		if(is_dir($dir)) {
			mkdir($new);
			foreach(array_diff(scandir($dir), array(".", "..")) as $f) {
				$this->copy($this->id($dir . DIRECTORY_SEPARATOR . $f), $this->id($new));
			}
		}
		if(is_file($dir)) {
			copy($dir, $new);
		}
		return json_encode(array('id' => $this->id($new)));
	}

	public function update_mods($id, $mods)
	{
		if($this->folders->updateMods($id, $mods))
		{
			return json_encode(array('status' => 'DONE', 'mods' => $mods));
		}
		return json_encode(array('status' => 'ERROR'));
	}
}

define('MOD_RUBY_PARSE', 1);

define('AC_VIEW', 1);
define('AC_CREATE', 2);
define('AC_RENAME', 4);
define('AC_DELETE', 8);
define('AC_UPLOAD', 16);
define('AC_DOWNLOAD', 32);

define('ROLE_ADMIN', AC_VIEW + AC_CREATE + AC_RENAME + AC_DELETE + AC_UPLOAD + AC_DOWNLOAD);
define('ROLE_GUEST', AC_VIEW);


/*
`folder_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`parent_id` INT NULL DEFAULT NULL ,
`user_id` INT NOT NULL ,
`path` VARCHAR( 256 ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL ,
`acl` INT UNSIGNED NOT NULL ,
*/

class Folders
{
	//private $tablespec;
	private $user_id;
	private $folders = NULL;
	private $logger;
	
	public function __construct($user_id)
	{
		$this->user_id = $user_id;
		$this->logger = new Log4Me(Log4me::INFO,"log.txt");
		$this->logger->setContext("Folders", $_SERVER['PHP_SELF']);
	}
	
	private function lazyLoad()
	{
		//Lazy load objects
		if(!$this->folders)
		{
			//Implements methods for working with a MYSQL database
			//$mysqlTable = new  MySQLTable(DB_NAME, "FOLDERS", $this->tablespec);
			$mysqlTable = new  MySQLTable(DB_NAME, "FOLDERS");
			$this->folders = new RecordSetHelper($mysqlTable, false);
		}
	}
	
	private function getRowByPath($id)
	{
		//Get folder information for id
		$sql = 'SELECT * FROM `FOLDERS` WHERE `path` = "' .$id . '"';
		$rs = $this->folders->getRecordSet($sql);
		return $rs->fetch(PDO::FETCH_ASSOC);
	}
	
	private function addFolder($parent_id, $user_id, $path, $acl)
	{
		$recordset = array();
		$arr = array();
		$arr["parent_id"] = $parent_id;
		$arr["user_id"] = $user_id;
		$arr["path"] = $path;
		$arr["acl_id"] = $acl;
		$recordset[] = $arr;
		return $this->folders->insertFullRecordSet($recordset);
	}
	
	private function addFolderUsing($folder)
	{
		$recordset = array();
		$recordset[] = $folder;
		return $this->folders->insertFullRecordSet($recordset);
	}

	public function updateMods($id, $mods)
	{
		$this->lazyLoad();
		$where_clause = ' WHERE `path` = "' . $id . '"';
		$row = array();
		$row["mods"] = $mods;
		return $this->folders->updateRow($row, $where_clause);
	}

	public function deleteFoldersFrom($id)
	{
		//TODO wrap this up in a transaction
		$this->lazyLoad();
		//get all acl_ids for this branch
		//Delete all occurences of above from ACL table
		//DELETE FROM `ACL` WHERE `acl_id` in (SELECT DISTINCT `acl_id` FROM `FOLDERS` WHERE `path` LIKE "giz/ACL/%" OR `path` LIKE "giz/ACL")
		//$sql = 'DELETE FROM `ACL` WHERE `acl_id` in (SELECT DISTINCT `acl_id` FROM `FOLDERS` WHERE `path` LIKE "' . $id . '/%" OR `path` LIKE "' . $id . '")';
		//$this->folders->executeSQL($sql);

		$sql = 'DELETE FROM `FOLDERS` WHERE `path` LIKE "' . $id . '/%" OR `path` LIKE "' . $id . '"';
		$this->folders->executeSQL($sql);
		$sql = 'DELETE FROM `IMAGES` WHERE `path` LIKE "' . $id . '/%"';
		$this->folders->executeSQL($sql);
		$sql = 'DELETE FROM `ARTWORKS` WHERE `path` LIKE "' . $id . '/%"';
		$this->folders->executeSQL($sql);
	}
	
	public function getFolderFor($path, $parent_path, $is_file = false)
	{
		if($is_file) //parse the folder path out 0f path
		{
			$path = dirname($path);
		}
		$this->lazyLoad();
		$row = $this->getRowByPath($path);
		if(!$row) //If no information for this id use parent id to construct new information and save it
		{
			if(!$parent_path)
			{
				//parse id to get parent
				$arr = explode("/",$path);
				if(count($arr) > 1)
				{
					array_pop($arr);
					$parent_path = implode("/",$arr);
				}
				else
				{
					$parent_path = "/";
				}
			}
			else
			{
				
			}
			$row = $this->getRowByPath($parent_path);
			$row['parent_id'] = $row['folder_id'];
			$row['folder_id'] = NULL;
			$row['path'] = $path;
			if(!$this->addFolderUsing($row))
			{
				throw new Exception("ERROR inserting " . $path . " Folder information.");
			}
		}
		return $row;
	}
	
	public function getEffectivePermissionsFor($folder)
	{
		$this->lazyLoad();
		//$sql = 'SELECT * FROM `ACL` WHERE `acl_id` = 2 LIMIT 0, 30 ';
		//$sql = 'SELECT `group_id` FROM `GROUP_MEMBERS` WHERE `user_id` = 112 LIMIT 0, 30 ';
		//$sql = 'SELECT * FROM `ACL` WHERE `acl_id` = 2 AND ( `group_id` = 1 OR `group_id` = 0 OR `user_id` = 112 ) LIMIT 0, 30 ';
		$sql = 'SELECT `permissions` FROM `ACL` WHERE `acl_id` = ' . $folder["acl_id"] . ' AND ( `group_id` IN ( SELECT `group_id` FROM `GROUP_MEMBERS` WHERE `user_id` = ' . $this->user_id . ' ) OR `user_id` = ' . $this->user_id . ' );';
		
		$this->logger->debug($sql);
		
		$rs = $this->folders->getRecordSet($sql);
		$strTest = "user_id: " . $this->user_id . " path: " . $folder['path'] . " permissions[";
		$retVal = 0;
		foreach($rs as $row)
		{
			$strTest .= $row['permissions'] . ", ";
			$retVal |= $row['permissions'];
		}
		$strTest .= "]";
		$this->logger->debug($strTest);
		return $retVal;
	}
	
	public function getPermissionsFor($folder)
	{
		$this->lazyLoad();
		//$sql = 'SELECT `user_id`, `group_id`, `permissions` FROM `ACL` WHERE `acl_id` = ' . $folder["acl_id"] . ' AND ( `group_id` IN ( SELECT `group_id` FROM `GROUP_MEMBERS` WHERE `user_id` = ' . $this->user_id . ' ) OR `user_id` = ' . $this->user_id . ' );';
		$sql = 'SELECT g . name, a . group_id , a . permissions  FROM `ACL` AS a INNER JOIN `GROUPS` AS g ON g . group_id = a . group_id WHERE a . acl_id = ' . $folder["acl_id"] .'; ';
		$rs = $this->folders->getRecordSet($sql);
		$group_set = array();
		foreach($rs as $row)
		{
			//Use associative values
			$group_set[] = array('name' => $row['name'], 'group_id' => $row['group_id'], 'permissions' => $row['permissions']);
			//$group_set[] = $row;
		}

		$sql = 'SELECT u . name , a . user_id , a . permissions FROM `ACL` AS a INNER JOIN `USERS` AS u ON u . user_id = a . user_id WHERE a . acl_id = ' . $folder["acl_id"] .'; ';
		$rs = $this->folders->getRecordSet($sql);
		$user_set = array();
		foreach($rs as $row)
		{
			//Use associative values
			$user_set[] = array('name' => $row['name'], 'user_id' => $row['user_id'], 'permissions' => $row['permissions']);
			//$user_set[] = $row;
		}
		$permission_set = array('group_set' => $group_set, 'user_set' => $user_set);		
		$this->logger->debug(print_r($permission_set,true));
		return $permission_set;
	}

	public function getUserIdFor($id)
	{
		$this->lazyLoad();
		$sql = 'SELECT `user_id` FROM `FOLDERS` WHERE `path` = "' .$id . '"';
		$rs = $this->folders->getRecordSet($sql);
		if($rs)
		{
			$col_val = $rs->fetchColumn();
			if(strlen($col_val))
			{
				return $col_val;
			}
		}
		return false;
	}

	public function getTablespec()
	{
		$this->lazyLoad();
		return $this->folders->getTableSpecs();
	}

}

class Users
{
	private $users_table = NULL;
	private $group_members_table = NULL;
	private $pdo;
	private $logger;
	private $errlog;
	
	public function __construct() 
	{
		$this->logger = new Log4Me(Log4me::DEBUG,"log.txt");
		$this->logger->setContext("Users Class", $_SERVER['PHP_SELF']);
		$this->errlog = new Log4Me(Log4Me::ERROR,"error.txt");
		$this->errlog->setContext("Users Class", $_SERVER['PHP_SELF']);
	}

	private function lazyLoad()
	{
		//Lazy load objects
		if(!$this->users_table)
		{
			$mysqlTableUsers = new  MySQLTable(DB_NAME, "USERS");
			$this->users_table = new RecordSetHelper($mysqlTableUsers, false);
			$this->pdo = $this->users_table->getPDO();
		}
	}

	private function lazyLoadGroupMembers()
	{
		//Lazy load objects
		if(!$this->group_members_table)
		{
			$mysqlTableGroupMembers = new  MySQLTable(DB_NAME, "GROUP_MEMBERS");
			$this->group_members_table = new RecordSetHelper($mysqlTableGroupMembers, false);
		}
	}

	//Returns an array with ('user_id' => $user_id, 'status' => 'SUCCESS 0r ERROR', 'msg' => 'Some error message')
	public function addUser($row)
	{
		$this->lazyLoad();

		//Check if email exists
		//executes a prepared statement using SQL query and parameters for query
		//returns associative array or false on failure
		$sql = 'SELECT `user_id` FROM `USERS` WHERE `email` = :email';
		$params = array('email' => $row['email']);
		$rs = $this->users_table->getRowSetUsing($sql, $params);
		$this->logger->debug(print_r($rs, true));
		if($rs)
		{
			$msg = 'The email ' . $row['email'] . ' already exists!';
			return array('user_id' => current($rs), 'status' => 'ERROR', 'msg' => $msg);
		}
		else
		{
			//Pull the group_set array out
			$group_set = NULL;
			if(isset($row['group_set'])){
				if (is_array($row['group_set'])) {
					$group_set = $row['group_set'];
				} else {
					$group_set = array($row['group_set']);
				}
			}
			$this->logger->debug(("group_set: " . print_r($group_set, true)));
			//now remove group_set
			unset($row['group_set']);

			$this->logger->debug("insertRow row = " . print_r($row, true));
			//inserts one row of data into the table
			//parameters: $row an associative array containg name value pairs to insert
			//Note primaryKey must contain a value or NULL for insert id to be returned
			//$primaryKeyName the primary key name.
			//returns primary key value(MYSQL only) or true on success, false on failure.
			//NOTE primary key must be auto_increment for MYSQL
			$row['user_id'] = NULL;
			$row['pswd'] = md5($row['pswd']);

			//Start the transaction
			$this->pdo->beginTransaction();

			$user_id = $this->users_table->insertRow($row,'user_id');
			$this->logger->debug("insertRow user_id  [" . print_r($user_id, true) . "]");
			if($user_id)
			{
				$this->lazyLoadGroupMembers();
				//Create group_members recordset
				$recordset = array();
				foreach($group_set as $group_id){
					$recordset[] = array('id' => NULL, 'group_id' => $group_id, 'user_id' => $user_id);
				}

				$this->logger->debug(("recordset: " . print_r($recordset, true)));

				$count = $this->group_members_table->insertFullRecordSet($recordset);
				if($count != -1)
				{
					$this->pdo->commit();
					$cap = "groups";
					if($count == 1)
					{
						$cap = "group";
					}
					return array('user_id' => $user_id, 'status' => 'SUCCESS', 'msg' => 'User ' . $row["name"] . ' added to the system in ' . $count . ' groups');
				}
				else
				{
					$this->pdo->rollBack();
					return array('user_id' => NULL, 'status' => 'ERROR', 'msg' => 'Error adding user');
				}
			}
			else
			{
				$this->pdo->rollBack();
				return array('user_id' => NULL, 'status' => 'ERROR', 'msg' => 'Error adding user');
			}
		}
	}

	public function getUsers()
	{
		$this->lazyLoad();

		$sql = 'SELECT `user_id` , `name` , `email` FROM `USERS` WHERE 1';
		return $this->users_table->getRowSet($sql);
	}

	public function getGroups()
	{
		$this->lazyLoad();

		$sql = $sql = 'SELECT * FROM `GROUPS` WHERE 1';
		return $this->users_table->getRowSet($sql);
	}

}

class Images
{
	private $user_id;
	private $images_table = NULL;
	private $artworks_table;
	private $pdo;
	private $root_url;
	private $base_folder;
	private $logger;
	private $errlog;
	public function __construct($user_id, $base_folder) //, $folder)
	{
		$this->user_id = $user_id;
		$this->logger = new Log4Me(Log4me::DEBUG,"log.txt");
		$this->logger->setContext("Images Class", $_SERVER['PHP_SELF']);
		$this->errlog = new Log4Me(Log4Me::ERROR,"error.txt");
		$this->errlog->setContext("Images Class", $_SERVER['PHP_SELF']);
		$this->base_folder = $base_folder;
		$this->root_url = ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . DIRECTORY_SEPARATOR . $this->base_folder . DIRECTORY_SEPARATOR;
	}
	
	private function lazyLoad()
	{
		//Lazy load objects
		if(!$this->images_table)
		{
			$mysqlTableImages = new  MySQLTable(DB_NAME, "IMAGES");
			$this->images_table = new RecordSetHelper($mysqlTableImages, false);
			$this->pdo = $this->images_table->getPDO();
			$mysqlTableArtworks = new  MySQLTable(DB_NAME, "ARTWORKS");
			$this->artworks_table = new RecordSetHelper($mysqlTableArtworks, false, $this->pdo);
		}
	}
	
	private function getDefault(Image $image, $path)
	{
		$name = $image->getBaseName();
		$row = array();
		$row['artwork_title'] = $name;
		$row['artwork_medium'] = "digital image";
		$row['artwork_year'] = $image->getExifObj()->getYear();
		$row['artwork_height'] = $image->getHeight();
		$row['artwork_width'] = $image->getWidth();
		$row['artwork_units'] = "pixels";
		$row['path'] = $path;
		return $row;
	}

	//returns ARTWORK row
	private function parseRubyFileName(Image $image, $path)
	{
		$name = $image->getBaseName();
		$parts = explode("-", $name);
		$row = null;
		if(count($parts) > 3)
		{
			$row = array();
			$row['artwork_title'] = trim($parts[0]);
			$row['artwork_medium'] = trim($parts[1]);
			if(count($parts) > 4) //dual year format
			{
				$row['artwork_year'] = trim($parts[2]) . "-" . trim($parts[3]);
				$parts2 = explode("in.", trim($parts[4]));
			}
			else
			{
				$row['artwork_year'] = trim($parts[2]);
				$parts2 = explode("in.", trim($parts[3]));
			}
			$dimensions = explode("x", trim($parts2[0]));
			$row['artwork_height'] = trim($dimensions[0]);
			$row['artwork_width'] = trim($dimensions[1]);
			
			$row['artwork_units'] = "inches";
			
			$row['path'] = $path;
		}
		return $row;
	}
	
	private function addArtWork(Image $image, $path, $mods)
	{
		$recordset = array();
		$row = NULL;
		$count = -1;
		if(MOD_RUBY_PARSE & $mods)
		{
			$row = $this->parseRubyFileName($image, $path);
		}
		else
		{
			$row = $this->getDefault($image, $path);
		}
		if($row)
		{
			$recordset[] = $row;
			$count = $this->artworks_table->insertFullRecordSet($recordset);
			if($count != -1)
			{
				$count = -1;
				$sql = 'SELECT `artwork_id` FROM `ARTWORKS` WHERE `path` = "' . $path . '"';
				$rs = $this->artworks_table->getRecordSet($sql);
				if($rs)
				{
					$artwork_id = $rs->fetchColumn();
					if(strlen($artwork_id))
					{
						$sql = 'UPDATE `IMAGES` SET `artwork_id` = ' . $artwork_id . ' WHERE `path` = "' . $path . '" LIMIT 1;';
						$count = $this->images_table->executeSQL($sql);
					}
				}
			}
		}
		return $count;
	}

	public function getRootUrl()
	{
		return $this->root_url;
	}
	
	public function deleteImage($id)
	{
		$this->lazyLoad();
		$sql = 'DELETE FROM `IMAGES` WHERE `path` = "' . $id . '"';
		$this->images_table->executeSQL($sql);
		$sql = 'DELETE FROM `ARTWORKS` WHERE `path` = "' . $id . '"';
		$this->images_table->executeSQL($sql);
	}

	//Returns number of rows inserted or -1 on failure
	public function addImage(Image $image, $path, $folder_row)
	{
		$this->lazyLoad();
		$recordset = array();
		$arr = array();
		$arr["path"] = $path;
		if(strlen($image->getTitle()))
		{
			$arr["title"] = $image->getTitle();
		}
		else
		{
			$arr["title"] = $image->getBaseName();
		}
		$arr["description"] = $image->getDescription();
		$arr["keywords"] = $image->getKeywords();
		$arr["creator"] = $image->getCreator();
		$arr["user_id"] = $this->user_id;
		//$arr["location_id"] = $image;
		//$arr["attachments"] = $image;
		$arr["height"] = $image->getHeight();
		$arr["width"] = $image->getWidth();
		$arr["acl_id"] = $folder_row["acl_id"]; //Folder acl is default
		$recordset[] = $arr;

		$this->logger->debug("DEBUG1 path: " . $path . " folder_row: " . $folder_row);
		$this->logger->debug("DEBUG2 recordset: " . print_r($recordset, true));

		//Start Transaction
		$this->pdo->beginTransaction();
		$count = $this->images_table->insertFullRecordSet($recordset);
		if($count == -1)
		{
			//Roll Back?
			$this->logger->debug("ROLL_BACK " . $path);
			$this->pdo->rollBack();
		}
		else
		{
			$count = $this->addArtWork($image, $path, $folder_row["mods"]);
			if($count == -1)
			{
				//Roll Back
				$this->logger->debug("ROLL_BACK " . $path);
				$this->pdo->rollBack();
			}
			else
			{
				//Commit Transaction
				$this->logger->debug("COMMIT " . $path);
				$this->pdo->commit();
			}
		}
		return $count;
	}
	
	//returns false if no image data
	public function testFor($path)
	{
		$this->lazyLoad();
		$sql = 'SELECT `item_id` FROM `IMAGES` WHERE `path` = "' .$path . '"';
		$rs = $this->images_table->getRecordSet($sql);
		return $rs->fetch(PDO::FETCH_ASSOC);
	}
	
	//returns false if no image data
	public function getImageDataRow($path)
	{
		$this->lazyLoad();
		$sql = 'SELECT i . * , a . * FROM `IMAGES` AS i INNER JOIN `ARTWORKS` AS a ON i . artwork_id = a . artwork_id WHERE i.path = "' .$path . '"';
		$rs = $this->images_table->getRecordSet($sql);
		$row = $rs->fetch(PDO::FETCH_ASSOC);
		return $row;
	}
	
/*	public function getImageDataRowAsHtml($path)
	{
		$this->lazyLoad();
		
		$sql = 'SELECT `artwork_id` FROM `ARTWORKS` WHERE `path` = "' .$path . '"';
		$rs = $this->images_table->getRecordSet($sql);
		$artwork_id = $rs->fetchColumn(); //gets value of first column in recordset
		if(strlen($artwork_id))
		{
			$sql = 'SELECT i . * , a . * FROM `IMAGES` AS i INNER JOIN `ARTWORKS` AS a ON i . artwork_id = a . artwork_id WHERE i.path = "' .$path . '"';
		}
		else
		{
			$sql = 'SELECT * FROM `IMAGES` WHERE `path` = "' .$path . '"';
		}
		$rs = $this->images_table->getRecordSet($sql);
		$row = $rs->fetch(PDO::FETCH_ASSOC);
		$html = "<ul>";
		foreach ($row as $key => $value)
		{
			$html .= "<li>" . $key . " [" . $value . "]</li>";
		}
		$html .= "</ul>";
		return $html;
	}
*/	
	public function getTablespec()
	{
		$this->lazyLoad();
		return $this->images_table->getTableSpecs();
	}
	
	public function removeData($id)
	{
		$retVal = true;
		$this->lazyLoad();
		$sql = 'DELETE FROM `IMAGES` WHERE `path` = "' . $id . '";';
		if($this->images_table->executeSQL($sql) == -1)
		{
			$retVal = false;
		}
		$sql = 'DELETE FROM `ARTWORKS` WHERE `path` = "' . $id . '";';
		if($this->images_table->executeSQL($sql) == -1)
		{
			$retVal = false;
		}
		return $retVal;
	}
}

class Artworks
{
	//private $tablespec;
	private $user_id;
	private $table = NULL;
	//private $mods;
	public function __construct($user_id)
	{
		$this->user_id = $user_id;
		//$this->mods = $mods;
		$this->logger = new Log4Me(Log4me::INFO,"log.txt");
		$this->logger->setContext("Artworks", $_SERVER['PHP_SELF']); 
	}
	
	private function lazyLoad()
	{
		//Lazy load objects
		if(!$this->table)
		{
			//Implements methods for working with a MYSQL database
			//$mysqlTable = new  MySQLTable(DB_NAME, "ARTWORKS", $this->tablespec);
			$mysqlTable = new  MySQLTable(DB_NAME, "ARTWORKS");
			$this->table = new RecordSetHelper($mysqlTable, false);
		}
	}
	
	private function getDefault(Image $image, $path)
	{
		$name = $image->getBaseName();
		$row = array();
		$row['artwork_title'] = $name;
		$row['artwork_medium'] = "digital image";
		$row['artwork_year'] = $image->getExifObj()->getYear();
		$row['artwork_height'] = $image->getHeight();
		$row['artwork_width'] = $image->getWidth();
		$row['artwork_units'] = "pixels";
		$row['path'] = $path;
		return $row;
	}

	//returns ARTWORK row
	private function parseRubyFileName(Image $image, $path)
	{
		$name = $image->getBaseName();
		$parts = explode("-", $name);
		$row = null;
		if(count($parts) > 3)
		{
			$row = array();
			$row['artwork_title'] = trim($parts[0]);
			$row['artwork_medium'] = trim($parts[1]);
			if(count($parts) > 4) //dual year format
			{
				$row['artwork_year'] = trim($parts[2]) . "-" . trim($parts[3]);
				$parts2 = explode("in.", trim($parts[4]));
			}
			else
			{
				$row['artwork_year'] = trim($parts[2]);
				$parts2 = explode("in.", trim($parts[3]));
			}
			$dimensions = explode("x", trim($parts2[0]));
			$row['artwork_height'] = trim($dimensions[0]);
			$row['artwork_width'] = trim($dimensions[1]);
			
			$row['artwork_units'] = "inches";
			
			$row['path'] = $path;
		}
		return $row;
	}
	
	//Returns number of rows inserted or -1 on failure
	public function addArtWork(Image $image, $path, $mods)
	{
		$this->lazyLoad();
		$recordset = array();
		$row = NULL;
		$count = -1;
		if(MOD_RUBY_PARSE & $mods)
		{
			$row = $this->parseRubyFileName($image, $path);
		}
		else
		{
			$row = $this->getDefault($image, $path);
		}
		if($row)
		{
			$recordset[] = $row;
			$count = $this->table->insertFullRecordSet($recordset);
			if($count != -1)
			{
				$sql = 'SELECT `artwork_id` FROM `ARTWORKS` WHERE `path` = "' . $path . '"';
				$rs = $this->table->getRecordSet($sql);
				if($rs)
				{
					$artwork_id = $rs->fetchColumn();
					if(strlen($artwork_id))
					{
						$sql = 'UPDATE `IMAGES` SET `artwork_id` = ' . $artwork_id . ' WHERE `path` = "' . $path . '" LIMIT 1;';
						$count = $this->table->executeSQL($sql);
					}
				}
			}
		}
		return $count;
	}

	public function getTablespec()
	{
		$this->lazyLoad();
		return $this->table->getTableSpecs();
	}
}

class Language
{
	private $language_codes;
	private $table;
	
	public function __construct()
	{
		$this->logger = new Log4Me(Log4me::DEBUG,"log.txt");
		$this->logger->setContext("Language", $_SERVER['PHP_SELF']); 
		$this->init();
	}
	
	private function lazyLoad()
	{
		//Lazy load objects
		if(!$this->table)
		{
			$mysqlTable = new  MySQLTable(DB_NAME, "LANGUAGE_CODES");
			$this->table = new RecordSetHelper($mysqlTable, false);
		}
	}

	private function init()
	{
		$this->language_codes = array(
        'en' => 'English' , 
        'aa' => 'Afar' , 
        'ab' => 'Abkhazian' , 
        'af' => 'Afrikaans' , 
        'am' => 'Amharic' , 
        'ar' => 'Arabic' , 
        'as' => 'Assamese' , 
        'ay' => 'Aymara' , 
        'az' => 'Azerbaijani' , 
        'ba' => 'Bashkir' , 
        'be' => 'Byelorussian' , 
        'bg' => 'Bulgarian' , 
        'bh' => 'Bihari' , 
        'bi' => 'Bislama' , 
        'bn' => 'Bengali/Bangla' , 
        'bo' => 'Tibetan' , 
        'br' => 'Breton' , 
        'ca' => 'Catalan' , 
        'co' => 'Corsican' , 
        'cs' => 'Czech' , 
        'cy' => 'Welsh' , 
        'da' => 'Danish' , 
        'de' => 'German' , 
        'dz' => 'Bhutani' , 
        'el' => 'Greek' , 
        'eo' => 'Esperanto' , 
        'es' => 'Spanish' , 
        'et' => 'Estonian' , 
        'eu' => 'Basque' , 
        'fa' => 'Persian' , 
        'fi' => 'Finnish' , 
        'fj' => 'Fiji' , 
        'fo' => 'Faeroese' , 
        'fr' => 'French' , 
        'fy' => 'Frisian' , 
        'ga' => 'Irish' , 
        'gd' => 'Scots/Gaelic' , 
        'gl' => 'Galician' , 
        'gn' => 'Guarani' , 
        'gu' => 'Gujarati' , 
        'ha' => 'Hausa' , 
        'hi' => 'Hindi' , 
        'hr' => 'Croatian' , 
        'hu' => 'Hungarian' , 
        'hy' => 'Armenian' , 
        'ia' => 'Interlingua' , 
        'ie' => 'Interlingue' , 
        'ik' => 'Inupiak' , 
        'in' => 'Indonesian' , 
        'is' => 'Icelandic' , 
        'it' => 'Italian' , 
        'iw' => 'Hebrew' , 
        'ja' => 'Japanese' , 
        'ji' => 'Yiddish' , 
        'jw' => 'Javanese' , 
        'ka' => 'Georgian' , 
        'kk' => 'Kazakh' , 
        'kl' => 'Greenlandic' , 
        'km' => 'Cambodian' , 
        'kn' => 'Kannada' , 
        'ko' => 'Korean' , 
        'ks' => 'Kashmiri' , 
        'ku' => 'Kurdish' , 
        'ky' => 'Kirghiz' , 
        'la' => 'Latin' , 
        'ln' => 'Lingala' , 
        'lo' => 'Laothian' , 
		 'lt' => 'Lithuanian' ,
        'lv' => 'Latvian/Lettish' , 
        'mg' => 'Malagasy' , 
        'mi' => 'Maori' , 
        'mk' => 'Macedonian' , 
        'ml' => 'Malayalam' , 
        'mn' => 'Mongolian' , 
        'mo' => 'Moldavian' , 
        'mr' => 'Marathi' , 
        'ms' => 'Malay' , 
        'mt' => 'Maltese' , 
        'my' => 'Burmese' , 
        'na' => 'Nauru' , 
        'ne' => 'Nepali' , 
        'nl' => 'Dutch' , 
        'no' => 'Norwegian' , 
        'oc' => 'Occitan' , 
        'om' => '(Afan)/Oromoor/Oriya' , 
        'pa' => 'Punjabi' , 
        'pl' => 'Polish' , 
        'ps' => 'Pashto/Pushto' , 
        'pt' => 'Portuguese' , 
        'qu' => 'Quechua' , 
        'rm' => 'Rhaeto-Romance' , 
        'rn' => 'Kirundi' , 
        'ro' => 'Romanian' , 
        'ru' => 'Russian' , 
        'rw' => 'Kinyarwanda' , 
        'sa' => 'Sanskrit' , 
        'sd' => 'Sindhi' , 
        'sg' => 'Sangro' , 
        'sh' => 'Serbo-Croatian' , 
        'si' => 'Singhalese' , 
        'sk' => 'Slovak' , 
        'sl' => 'Slovenian' , 
        'sm' => 'Samoan' , 
        'sn' => 'Shona' , 
        'so' => 'Somali' , 
        'sq' => 'Albanian' , 
        'sr' => 'Serbian' , 
        'ss' => 'Siswati' , 
        'st' => 'Sesotho' , 
        'su' => 'Sundanese' , 
        'sv' => 'Swedish' , 
        'sw' => 'Swahili' , 
        'ta' => 'Tamil' , 
        'te' => 'Tegulu' , 
        'tg' => 'Tajik' , 
        'th' => 'Thai' , 
        'ti' => 'Tigrinya' , 
        'tk' => 'Turkmen' , 
        'tl' => 'Tagalog' , 
        'tn' => 'Setswana' , 
        'to' => 'Tonga' , 
        'tr' => 'Turkish' , 
        'ts' => 'Tsonga' , 
        'tt' => 'Tatar' , 
        'tw' => 'Twi' , 
        'uk' => 'Ukrainian' , 
        'ur' => 'Urdu' , 
        'uz' => 'Uzbek' , 
        'vi' => 'Vietnamese' , 
        'vo' => 'Volapuk' , 
        'wo' => 'Wolof' , 
        'xh' => 'Xhosa' , 
        'yo' => 'Yoruba' , 
        'zh' => 'Chinese' , 
        'zu' => 'Zulu' , 
        );
	}
	
	public function populateTable()
	{
		$rs = array();
		foreach ($this->language_codes as $key => $value)
		{
			$row = array();
			$row['code'] = $key;
			$row['language'] = $value;
			$rs[] = $row;
		}
		$this->lazyLoad();
		$this->table->insertFullRecordSet($rs);
	}
}

class ImagePages
{
	//Page prototype
	//$page = array('name' => 'default', 'fieldSets' => '');
	private $logger;
	private $user_id;
	private $lang;
	private $table = NULL;
	public function __construct($user_id, $lang)
	{
		$this->user_id = $user_id;
		$this->lang = $lang;
		//$this->images = $images;
		$this->logger = new Log4Me(Log4me::DEBUG,"log.txt");
		$this->logger->setContext("Pages Class", $_SERVER['PHP_SELF']);
 	}
	
	private function lazyLoad()
	{
		//Lazy load objects
		if(!$this->table)
		{
			//Implements methods for working with a MYSQL database
			//$mysqlTable = new  MySQLTable(DB_NAME, "IMAGES", $this->tablespec);
			$mysqlTable = new  MySQLTable(DB_NAME, "COLUMN_SPECS");
			$this->table = new RecordSetHelper($mysqlTable, false);
		}
	}
	
	public function getJson(Images $images, $path)
	{
		return json_encode($this->getArrayData($images, $path));
	}
	
	public function getArrayData(Images $images, $path)
	{
		$this->lazyLoad();
		
		$data = $images->getImageDataRow($path);

		$sql = 'SELECT `spec_id`, `column`, `caption`, `column_type` FROM `COLUMN_SPECS` WHERE (`table_name` = "IMAGES" OR `table_name` = "ARTWORKS") AND `language` = "' . $this->lang . '";';
		
		$rs = $this->table->getRecordSet($sql);
		$col_specs = $rs->fetchAll(PDO::FETCH_ASSOC);
		$page = array('fields' => '');
		foreach($col_specs as $row)
		{
			$val = $data[$row['column']];
			$row['value'] = $val;
			if($row['column'] == 'path')
			{
				$row['root_url'] = $images->getRootUrl();
			}
			$page['fields'][$row['column']] = $row;
		}
		return $page;
	}

}

/*
{"fields":{"path":{"spec_id":"1","column":"path","caption":"File Path","column_type":"READ_ONLY_TEXT_AREA","value":"Foto Gwaii\/2012\/lol_b.jpg","root_url":"http:\/\/surrealranch.ca\/cloud\/"},"title":{"spec_id":"2","column":"title","caption":"Image Title","column_type":"TEXT_AREA","value":"lol_b"},"description":{"spec_id":"3","column":"description","caption":"Image Description","column_type":"TEXT_AREA","value":""},"keywords":{"spec_id":"4","column":"keywords","caption":"Image Key Words","column_type":"TEXT_AREA","value":"Abstract"},"creator":{"spec_id":"5","column":"creator","caption":"Image Creator","column_type":"TEXT","value":"Foto Gwaii"},"user_id":{"spec_id":"6","column":"user_id","caption":"User ID","column_type":"READ_ONLY_INTEGER","value":"112"},"location_id":{"spec_id":"7","column":"location_id","caption":"Image Location ID","column_type":"READ_ONLY_INTEGER","value":"0"},"attachments":{"spec_id":"8","column":"attachments","caption":"Image Attachments","column_type":"READ_ONLY_INTEGER","value":"0"},"height":{"spec_id":"9","column":"height","caption":"Image Height","column_type":"READ_ONLY_INTEGER","value":"854"},"width":{"spec_id":"10","column":"width","caption":"Image Width","column_type":"READ_ONLY_INTEGER","value":"1000"},"artwork_id":{"spec_id":"12","column":"artwork_id","caption":"Artwork Information","column_type":"READ_ONLY_INTEGER","value":"10"},"artwork_title":{"spec_id":"13","column":"artwork_title","caption":"Artwork Title","column_type":"TEXT_AREA","value":"lol_b"},"artwork_medium":{"spec_id":"14","column":"artwork_medium","caption":"Artwork Medium","column_type":"TEXT","value":"digital image"},"artwork_year":{"spec_id":"15","column":"artwork_year","caption":"Artwork Creation Year","column_type":"TEXT","value":"2012"},"artwork_units":{"spec_id":"16","column":"artwork_units","caption":"Artwork Units","column_type":"TEXT","value":"pixels"},"artwork_height":{"spec_id":"17","column":"artwork_height","caption":"Artwork Height","column_type":"INTEGER","value":"854"},"artwork_width":{"spec_id":"18","column":"artwork_width","caption":"Artwork Width","column_type":"INTEGER","value":"1000"},"item_id":{"spec_id":"0","column":"item_id","caption":"Unique Identification Number","column_type":"READ_ONLY_INTEGER","value":"10"}}}
*/
?>