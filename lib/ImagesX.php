<?php

	require_once("Base.php");
	require_once("Parse.php");

	Class GZImagesXInvalidArgumentException extends Exception
	{
		function __construct($description, $value)
		{
			parent::__construct("GZImagesInvalidArgumentException - $description [$value]"); 
		}
	}
	
	Class GZImagesXDatabaseException extends Exception
	{
		function __construct($description, $errno, $error)
		{
			parent::__construct("MYSQL Database error: " . $description . " Error Number: " . $errno . " Error: " . $error); 
		}
	}

	Class GZImageX extends GZField
	{
		//NOTE if any of these values change update addGZImage below
		const IMAGES = "IMAGES"; //Table Name
		const IMAGE_ID = "image_id"; // INT UNSIGNED DEFAULT NULL AUTO_INCREMENT,
		const AUTHOR_ID = "author_id"; // SMALLINT UNSIGNED NOT NULL ,
		const CATEGORY_ID = "category_id"; // TINYINT UNSIGNED NOT NULL ,
		const PATH_ID = "path_id"; // TINYINT UNSIGNED NULL ,
		const DIMENSION_ID = "dimension_id"; // INT UNSIGNED DEFAULT NULL
		const FILE_NAME = "file_name"; // VARCHAR( 255 ) NOT NULL ,
		const NAME = "name"; // VARCHAR( 255 ) NOT NULL ,
		const EXT = "ext"; // VARCHAR( 8 ) NOT NULL ,
		const CAPTION = "caption"; // VARCHAR( 128 ) ,
		const ALT = "alt"; // VARCHAR( 128 ) ,
		const TITLE = "title"; // VARCHAR( 128 ) ,
		const DESCRIPTION = "description"; // TEXT( 1024 )
		
		const HEIGHT = 'height';
		const WIDTH = 'width';
		const UNIT = 'unit';
		
		const TAGS = 'tags';

		private $path;
		private $height;
		private $width;
		private $unit;
		
		//Minimum requirement is a file name ie someImage.jpg
		//NOTE if no extension is provide then jpg is assumed
		public function __construct($in_file_name = null)
		{
			parent::__construct();
			if(! is_null($in_file_name))
			{ 
				$parts = explode(".", $in_file_name);
				if(count($parts) == 1)
				{
					//No extension - use jpg
					$this->set(self::FILE_NAME, $in_file_name . ".jpg");
					$this->set(self::NAME,$in_file_name);
					$this->set(self::EXT, "jpg");
				}
				elseif(count($parts) == 2)
				{
					$this->set(self::FILE_NAME,$in_file_name);
					$this->set(self::NAME,$parts[0]);
					$this->set(self::EXT,$parts[1]);
				}
				else
				{
					//More than 2 parts
					$this->set(self::FILE_NAME,$in_file_name);
					//Pop the extension off the end
					$this->set(self::EXT,array_pop($parts));
					//Put the remaining parts back together
					$this->set(self::NAME,implode(".", $parts));
				}
				
				//Check for valid image extensions
				switch(strtoupper($this->get(self::EXT)))
				{
					case "JPG":	case "JPEG": case "PNG": case "GIF":
					break;
					default:
						throw new GZImagesXInvalidArgumentException("Illegal File Extension", $this->get(self::EXT));
					break;
				}
				//Initialize default values
				$this->set(self::CAPTION, $this->get(self::NAME));
				$this->set(self::ALT, $this->get(self::NAME));
				$this->set(self::TITLE, $this->get(self::NAME));
			}
		}
		
		static public function cast(GZField $field)
		{
			$image = new GZImageX();
			return $field->copyCast($image);
		}
		
		public function getFileName()
		{
			return $this->get(self::FILE_NAME);
		}
		public function getId()
		{
			return $this->getPrimary();
		}
		public function setId($in_id)
		{
			$this->setPrimary(self::IMAGE_ID,$in_id);
		}
		public function getExtension()
		{
			return $this->get(self::EXT);
		}
		public function getName()
		{
			return $this->get(self::NAME);
		}
		public function getAuthorId()
		{
			return $this->get(self::AUTHOR_ID);
		}
		public function setAuthorId($in_author_id)
		{
			$this->set(self::AUTHOR_ID,$in_author_id);
		}
		public function getCategoryId()
		{
			return $this->get(self::CATEGORY_ID);
		}
		public function setCategoryId($in_category_id)
		{
			$this->set(self::CATEGORY_ID,$in_category_id);
		}
		public function getCaption()
		{
			return $this->get(self::CAPTION);
		}
		public function setCaption($in_caption)
		{
			$this->set(self::CAPTION,$in_caption);
		}
		public function getAlt()
		{
			return $this->get(self::ALT);
		}
		public function setAlt($in_alt)
		{
			$this->set(self::ALT,$in_alt);
		}
		public function getTitle()
		{
			return $this->get(self::TITLE);
		}
		public function setTitle($in_title)
		{
			$this->set(self::TITLE,$in_title);
		}
		public function getDescription()
		{
			return $this->get(self::DESCRIPTION);
		}
		public function setDescription($in_description)
		{
			$this->set(self::DESCRIPTION,$in_description);
		}
		public function getPathId()
		{
			return $this->get(self::PATH_ID);
		}
		public function setPathId($in_pathId)
		{
			$this->set(self::PATH_ID,$in_pathId);
		}
		public function getDimensionId()
		{
			return $this->get(self::DIMENSION_ID);
		}
		public function setDimensionId($in_dimId)
		{
			$this->set(self::DIMENSION_ID,$in_dimId);
		}
		public function getPath()
		{
			return $this->path;
		}
		public function setPath($in_path)
		{
			$this->path = $in_path;
		}
		public function getUnit()
		{
			return $this->unit;
		}
		public function setUnit($in_unit)
		{
			$this->unit = $in_unit;
		}
		public function getHeight()
		{
			return $this->height;
		}
		public function setHeight($in_height)
		{
			$this->height = $in_height;
		}
		public function getWidth()
		{
			return $this->width;
		}
		public function setWidth($in_width)
		{
			$this->width = $in_width;
		}
		public function getTags()
		{
			return $this->get(self::TAGS);
		}
		public function setTags($in_tags)
		{
			$this->set(self::TAGS,$in_tags);
		}
	}
	
	Class GZImagesX extends GZFields
	{
		public function __construct($in_dao, $isLoggedIn=false)
		{
			parent::__construct($in_dao,$isLoggedIn);
		}
		
		public function setOrderByField($fieldName = "")
		{
			$this->dao->orderByField($fieldName);
		}
		
		public function initialize($isDesc, $where = "",$from = null,$to = null)
		{
   			$this->dao->isDescending($isDesc);
			$this->dao->selectFields($where,$from,$to);
		}
		
		public function makeSilverXML()
		{
			for($i = 0; $i < $this->dao->getCount(); $i++)
			{
				$image = $this->dao->getObjectByIndex($i);
				$str .= '<image id="' . $image->getPrimary() . '" path="' . $image->getPath() . '">' . $image->getFileName() . '</image>';
			}
			return $str;
		}

		public function makeJson()
		{
			$str = '{"images":[';
			for($i = 0; $i < $this->dao->getCount(); $i++)
			{
				$image = $this->dao->getObjectByIndex($i);
				$path = $image->getPath();
                if($path != "")
				{
					$img = 'http://www.surrealranch.ca/' . $path . '/cards/' . $image->getFileName();
					$thumb = 'http://www.surrealranch.ca/' . $path . '/thumbs/' . $image->getFileName();
					//Note title and description do not need to be quoted - this is performed by json_encode
					$str .= '{"id":"' . $image->getPrimary() . '", "title":' . json_encode($image->getTitle()) . ', "thumb":"' . $thumb . '", "img":"' . $img . '", "description":' .  json_encode($image->getDescription()) . '},';
				}
			}
			$str = rtrim($str,",");
			$str .= ']}';
			return $str;
		}

		//path to directory containing images, cards, and thumbs directories ie "/document/"
	    public function makeListView($hasLabels, $scale=false)
    	{
			define(NWLN,"\n");	
		    $count = $this->dao->getCount();
			
			$this->logger->debug("Table: " . $this->dao->getTableName() . " item count: " . $count);

			for($i = 0; $i < $count; $i++)
			{ 
	            $image = $this->dao->getObjectByIndex($i);
                $path = $image->getPath();
                if($path != "")
				{
					$path = "/" . $path . "/";
				}
				$this->logger->debug("path[" . $path . "]");
				$hAttr = "";
				$divStyle = "";
				$folder = "thumbs";
				if($scale)
				{
					$ratio = $image->getWidth()/$image->getHeight();
					$percent = $image->getHeight()/24;
					$height = 200 * $percent;
					$width = $height * $ratio;
					$hAttr = 'height="' . $height . '" width="' . $width . '"';
					
					$height2 = 260 * $percent;
					$width2 = $height2 * $ratio;
					if($width2 != 0)
					{
						$divStyle = 'style="width: ' . $width2 . 'px;"';// height: ' . $height2 . 'px;"';
						if($height > 250)
						{
							$divStyle = 'style="width: ' . $width2 . 'px; height: ' . $height2 . 'px;"';
						}
					}
					if($height > 200)
					{
						$folder = "cards";
					}
				}
				
				
				echo '<div class="img_container" id="' . $image->getPrimary() . 'Container" ' . $divStyle . '>' . NWLN;
				echo '	<div class="thumbStyle">' . NWLN;
					echo '<a id="' . $image->getPrimary() . '" href="' . $path . 'cards/' . $image->getFileName() . '" title="' . $image->getTitle() . '">' . NWLN;
					echo '<img id="img_' . $image->getPrimary() . '" ' . $hAttr . ' class="list_view" alt="' . $image->getAlt() . '" src="' . $path . $folder . '/' . $image->getFileName() . '" />' .NWLN;
						if($hasLabels)
						{
							echo '<p id="cap_' . $image->getPrimary() . '" class="caption" style="word-wrap: break-word;">' . NWLN;
							echo '<i>' . $image->getCaption() . '</i>' . NWLN;
							echo '</p>' . NWLN;
						}
					echo '</a>' . NWLN;
					echo '</div>' . NWLN;
				echo '</div>' . NWLN;
			}
	    }

		//path to directory containing images, cards, and thumbs directories ie "/document/"
	    public function makeMicroListView($hasLabels)
    	{
			define(NWLN,"\n");	
		    $count = $this->dao->getCount();
			
			$this->logger->debug("Table: " . $this->dao->getTableName() . " item count: " . $count);

			for($i = 0; $i < $count; $i++)
			{ 
	            $image = $this->dao->getObjectByIndex($i);
                $path = $image->getPath();
                if($path != "")
				{
					$path = "/" . $path . "/";
				}
				$this->logger->debug("path[" . $path . "]");
				$hAttr = "";
				
				echo '<div class="img_container" id="' . $image->getPrimary() . 'Container">' . NWLN;
				echo '	<div class="thumbStyle">' . NWLN;
					echo '<a id="' . $image->getPrimary() . '" href="' . $path . 'cards/' . $image->getFileName() . '" title="' . $image->getTitle() . '">' . NWLN;
					echo '<img ' . $hAttr . ' class="list_view" alt="' . $image->getAlt() . '" src="' . $path . 'micro/' . $image->getFileName() . '" />' .NWLN;
						if($hasLabels)
						{
							echo '<p class="caption" style="word-wrap: break-word;">' . NWLN;
							echo '<i>' . $image->getCaption() . '</i>' . NWLN;
							echo '</p>' . NWLN;
						}
					echo '</a>' . NWLN;
					echo '</div>' . NWLN;
				echo '</div>' . NWLN;
			}
	    }

	    public function makeTable($hasLabels)
	    {
		    $COLS = 4;
		    $index = 0;
			define(NWLN,"\n");	
		    $count = $this->dao->getCount();
		    $rows = $count / $COLS;
		    if ($count > $rows * $COLS) { $rows++; }
		
		    echo "<table style=\"width: 100%\">" . NWLN;
		    for ($i = 0; $i < $rows; $i++)
		    {
		        $strImgCol = "<tr>" . NWLN;
		        $strTitleCol = "<tr>" . NWLN;
		        for ($j = 0; $j < $COLS; $j++)
		        {
		            $strLabel = "";
		            if ($index < $count)
		            {
	                	$image = $this->dao->getObjectByIndex($index++);
		                $path = $image->getPath();
		                if($path != "")
						{
							$path = "/" . $path . "/";
						}
		                $this->logger->debug("path[" . $path . "]");
		                $strImgCol .= "<td class=\"thumbStyle\">" . NWLN;
		                $strImgCol .= "<a id=\"" . $image->getPrimary() . "\" href=\"" . $path . "cards/" . $image->getFileName() . "\" title=\"" . $image->getTitle() . "\">" . NWLN;
		                $strImgCol .= "<img class=\"imgStyle\" src=\"" .$path . "thumbs/" . $image->getFileName() . "\" alt=\"" . $image->getAlt() . "\" /></a></td>" . NWLN;
		                if ($hasLabels)
		                {
		                    $strLabel = $image->getCaption();
		                }
		            }
		            else
		            {
		                $strImgCol .= "<td class=\"thumbStyle\" ></td>" . NWLN;
		            }
		            $strTitleCol .= "<td class=\"imgTitle\" style=\"word-wrap: break-word;\" >" . $strLabel . "</td>" . NWLN;
		        }
		        $strImgCol .= "</tr>" . NWLN;
		        $strTitleCol .= "</tr>" . NWLN;
		        echo $strImgCol;
		        echo $strTitleCol;
		    }
		    echo "</table>" . NWLN;
	    }

		//Updates all values of a given field
		public function updateAll($column, $value)
		{
			$this->dao->selectFields();
			
			$count = $this->dao->getCount();
			//$this->logger->debug("updateAll count: " . $count);			
			for ($i = 0; $i < $count; $i++)
			{
				$obj = $this->dao->getObjectByIndex($i);
				$field = $obj->cloneEmpty();
				$field->set($column,$value);
				//$this->logger->debug(print_r($field,true));
				$this->updateField($field);
			}
		}

	}
	
	class GZMYSQLImageXDAO extends GZMYSQLFieldsDAO
	{
		private $errMsg;
		private $useDimensions;
		public function __construct(GZDBConn $dbConn, $useDimensions=false)
		{
			$this->useDimensions = $useDimensions;
			$table_name = "IMAGES";
			parent::__construct($dbConn, $table_name);
			
			$this->logger->debug("GZMYSQLImageXDAO constructer useDimensions[" . $useDimensions . "]");

		}
		
		public function selectFields($where = "", $from = null, $to = null)
		{
			//empty the array first
			$this->obj_list = array();
			if(is_null($to) && is_null($from))
			{
				$limit = "";
			}
			elseif(is_null($to))
			{
				$limit = ' LIMIT ' . $from . ' , ' . self::MAX_ROWS;
			}
			else
			{
				$limit = ' LIMIT ' . $from . ' , ' . $to;
			}
			$join1 = "";
			$join2 = "";
			if($this->useDimensions)
			{
				$join1 = ', d.unit, d.height, d.width';
				$join2 = 'INNER JOIN `DIMENSIONS` d USING ( dimension_id )';
			}
			
			$this->logger->debug("join1[" . $join1 . "] join2[" . $join2 . "]");
			
			$fieldName = $this->getPrimaryKeyName();
			if($this->orderByField != "")
			{
				$fieldName = $this->orderByField;
			}

			$sql = 'SELECT * , p.path ' . $join1 . ' FROM `' . $this->table_name . '` INNER JOIN `PATHS` p USING ( path_id ) ' . $join2 . ' ' .  $where . ' ORDER BY `' . $fieldName . '` ' . $this->direction . $limit;
			//echo "[" . $sql . "]";
			$result = $this->query($sql);
			while(($row_data = $result->fetch_assoc()) !== NULL)
			{
				$obj = new GZField();
				$this->loadObject($obj, $row_data);
				$image = GZImageX::cast($obj);
				$xpath = $row_data['path'];
				//$this->logger->debug("path[" . $xpath . "]");
				$image->setPath($xpath);
				$image->setUnit($row_data['unit']);
				$image->setHeight($row_data['height']);
				$image->setWidth($row_data['width']);
				$this->obj_list[] = $image;
			}
		}
		
		public function getObjectByIndex($index)
		{
			return $this->obj_list[$index];
		}

		public function getFieldXmlById($id)
		{
			$this->logger->debug("Call ImagesX::getFieldXmlById");
			$tableNamesArray = array();
			$join1 = ', p.path';
			$join2 = 'INNER JOIN `PATHS` p USING ( path_id )';
			$tableNamesArray[] = "PATHS";
			if($this->useDimensions)
			{
				$join1 .= ', d.unit, d.height, d.width';
				$join2 .= ' INNER JOIN `DIMENSIONS` d USING ( dimension_id )';
				$tableNamesArray[] = "DIMENSIONS";
			}
			return parent::getFieldXmlById($id, $join1, $join2, $tableNamesArray);
		}
		
		public function getFieldById($id)
		{
			$join1 = "";
			$join2 = "";
			if($this->useDimensions)
			{
				$join1 = ', d.unit, d.height, d.width';
				$join2 = 'INNER JOIN `DIMENSIONS` d USING ( dimension_id )';
			}
			$sql = 'SELECT * , p.path ' . $join1 . ' FROM `' . $this->table_name . '` INNER JOIN `PATHS` p USING ( path_id ) ' . $join2 . ' WHERE `' . $this->getPrimaryKeyName() . '` = ' . $this->check_input($id);
			$result = $this->query($sql);
			if($result === FALSE)
			{
				$errno = $this->conn->errno; 
				$errmsg = $this->conn->error;
				throw new GZDatabaseException($sql . "Failed ",$errno,$errmsg);
			}
			else
			{
				if(($row_data = $result->fetch_assoc()) !== NULL)
				{
					$meta = $this->fieldMetaData();
					$count = count($meta);
					$obj = new GZField();
					$obj->setPrimary($this->getPrimaryKeyName(),$row_data[$this->getPrimaryKeyName()]);
					for($i = 0; $i < $count; $i++)
					{
						$name = $meta[$i]->getName();
						$obj->set($name,$row_data[$name]);
					}
					//cast to GZImageX type to add path value
					$image = GZImageX::cast($obj);
					$xpath = $row_data['path'];
					$this->logger->debug("path[" . $xpath . "]");
					$image->setPath($xpath);
					
					$image->setUnit($row_data['unit']);
					$image->setHeight($row_data['height']);
					$image->setWidth($row_data['width']);

					return $image;
				}
				else
				{
					throw new GZInvalidArgumentException("ImagesX.php getFieldById: Could not retreive Information for", $id);
				}
			}
		}
	}
	
	//TODO What To do with this?
	Class GZFilesXDAO
	{
		private	$filename_list;
		public function __construct($in_path, $in_image_extensions)
		{
			$this->filename_list = $this->file_array($in_path,$in_image_extensions);
		}
		
		public function getCount()
		{
			return count($this->filename_list);
		}
		
		public function getObjectByIndex($index)
		{
			$image = new GZImageX($this->filename_list[$index]);
			$image->setPrimary(GZImageX::IMAGE_ID,$index);
			$image->setPath(""); //TODO Hack
			return $image;
		}
		
		private function file_array($path, $include, $exclude = ".|..")
		{
	    	$path = rtrim($path, "/") . "/";
	    	$result = array();
	   	 	if($folder_handle = opendir($path))
	   	 	{
		    	$exclude_array = explode("|", $exclude);
		    	$include_array = explode("|", $include);
		    	while(false !== ($filename = readdir($folder_handle)))
		    	{
		        	if(!in_array(strtolower($filename), $exclude_array))
		        	{
		            	if(!is_dir($path . $filename . "/"))
		            	{
		                	$parts = explode(".",$filename);
		                	if(count($parts) > 1)
		                	{
		                		$extension = array_pop($parts);
		                		if(in_array(strtolower($extension), $include_array))
		                		{
		                		   	$result[] = $filename;
		                		}
		                	}
		            	}
		        	}
		    	}
		    	closedir($folder_handle);
		    	return $result;
		    }	
		    else
		    {
		    	//TODO throw Exception
		    	echo "FAIL";
		    	$result[] = "Coot.jpg";
		    	$result[] = "Coot.jpg";
		    }	
		}
		
		//////////////////////////// NOT Implemented //////////////////////
		public  function __destruct()
		{
		}
		public  function closeConnection()
		{
		}
		public function isDescending($isDesc)
		{
		}
		public function selectFields($from,$to)
		{
		}
		public function getTableName()
		{
		}
		public function getMetaDataArray()
		{
		}
		public function getPrimaryKeyName()
		{
		}
		public function deleteField($id)
		{
		}
		public function deleteBlock($from,$to)
		{
		}
		public function addField($obj)
		{
		}
		public function updateField($field)
		{
		}
		public function getFieldById($id)
		{
		}
		public function getFieldByName($col_name,$value)
		{
		}
		public function check_input($value)
		{
		}
	}

	
?>