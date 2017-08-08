<?php
	require_once("Log4me.php");
	
	define('LOCATION_PRECISION',8);
	//////////////////////////////////////////////// Utility Functions ///////////////////////////////////////////
	function rationalToFloat($rational)
	{
		$arr = explode("/", $rational);
		if(count($arr) > 1)
		{
			return trim($arr[0]) / trim($arr[1]);
		}
		else
		{
			return $rational;
		}
	}
	
	function toDecimal($deg, $min, $sec, $hem, $precision) 
	{
		$d = $deg + ((($min/60) + ($sec/3600))/100);
		$dec = ($hem=='S' || $hem=='W') ? $d*=-1 : $d;
		return round($dec, $precision);
	}
	
	function toLocationString($deg, $min, $sec, $hem)
	{
		$hem = trim($hem);
		$longName = array('N' => 'NORTH', 'S' => 'SOUTH', 'E' => 'EAST', 'W' => 'WEST');
		return $longName[$hem] . " " . $deg . "° " . $min . "' " . $sec . '"';
	}

	function getXmpData($filename, $chunkSize)
	{
		if (!is_int($chunkSize)) {
			throw new RuntimeException('Expected integer value for argument #2 (chunkSize)');
		}
	
		if ($chunkSize < 12) {
			throw new RuntimeException('Chunk size cannot be less than 12 argument #2 (chunkSize)');
		}
	
		if (($file_pointer = fopen($filename, 'r')) === FALSE) {
			throw new RuntimeException('Could not open file for reading');
		}
	
		$startTag = '<x:xmpmeta';
		$endTag = '</x:xmpmeta>';
		$buffer = NULL;
		$hasXmp = FALSE;
	
		while (($chunk = fread($file_pointer, $chunkSize)) !== FALSE) {
	
			if ($chunk === "") {
				break;
			}
	
			$buffer .= $chunk;
			$startPosition = strpos($buffer, $startTag);
			$endPosition = strpos($buffer, $endTag);
	
			if ($startPosition !== FALSE && $endPosition !== FALSE) {
				$buffer = substr($buffer, $startPosition, $endPosition - $startPosition + 12);
				$hasXmp = TRUE;
				break;
			} elseif ($startPosition !== FALSE) {
				$buffer = substr($buffer, $startPosition);
				$hasXmp = TRUE;
			} elseif (strlen($buffer) > (strlen($startTag) * 2)) {
				$buffer = substr($buffer, strlen($startTag));
			}
		}
	
		fclose($file_pointer);
		return ($hasXmp) ? $buffer : NULL;
	}

	function get_xmp_array( &$xmp_raw )
	{
		$xmp_arr = array();
		foreach ( array(
			'Creator_Email'	=> '<Iptc4xmpCore:CreatorContactInfo[^>]+?CiEmailWork="([^"]*)"',
			'Owner_Name'	=> '<rdf:Description[^>]+?aux:OwnerName="([^"]*)"',
			'Creation_Date'	=> '<rdf:Description[^>]+?xmp:CreateDate="([^"]*)"',
			'Modification_Date'	=> '<rdf:Description[^>]+?xmp:ModifyDate="([^"]*)"',
			'Label'		=> '<rdf:Description[^>]+?xmp:Label="([^"]*)"',
			'Credit'	=> '<rdf:Description[^>]+?photoshop:Credit="([^"]*)"',
			'Source'	=> '<rdf:Description[^>]+?photoshop:Source="([^"]*)"',
			'Headline'	=> '<rdf:Description[^>]+?photoshop:Headline="([^"]*)"',
			'City'		=> '<rdf:Description[^>]+?photoshop:City="([^"]*)"',
			'State'		=> '<rdf:Description[^>]+?photoshop:State="([^"]*)"',
			'Country'	=> '<rdf:Description[^>]+?photoshop:Country="([^"]*)"',
			'Country_Code'	=> '<rdf:Description[^>]+?Iptc4xmpCore:CountryCode="([^"]*)"',
			'Location'	=> '<rdf:Description[^>]+?Iptc4xmpCore:Location="([^"]*)"',
			'Title'		=> '<dc:title>\s*<rdf:Alt>\s*(.*?)\s*<\/rdf:Alt>\s*<\/dc:title>',
			'Description'	=> '<dc:description>\s*<rdf:Alt>\s*(.*?)\s*<\/rdf:Alt>\s*<\/dc:description>',
			'Creator'	=> '<dc:creator>\s*<rdf:Seq>\s*(.*?)\s*<\/rdf:Seq>\s*<\/dc:creator>',
			'Keywords'	=> '<dc:subject>\s*<rdf:Bag>\s*(.*?)\s*<\/rdf:Bag>\s*<\/dc:subject>',
			'Hierarchical_Keywords'	=> '<lr:hierarchicalSubject>\s*<rdf:Bag>\s*(.*?)\s*<\/rdf:Bag>\s*<\/lr:hierarchicalSubject>'
		) as $key => $regex ) {

			// get a single text string
			$xmp_arr[$key] = preg_match( "/$regex/is", $xmp_raw, $match ) ? $match[1] : '';

			// if string contains a list, then re-assign the variable as an array with the list elements
			$xmp_arr[$key] = preg_match_all( "/<rdf:li[^>]*>([^>]*)<\/rdf:li>/is", $xmp_arr[$key], $match ) ? $match[1] : $xmp_arr[$key];

			// hierarchical keywords need to be split into a third dimension
			if ( ! empty( $xmp_arr[$key] ) && $key == 'Hierarchical Keywords' ) {
				foreach ( $xmp_arr[$key] as $li => $val ) $xmp_arr[$key][$li] = explode( '|', $val );
				unset ( $li, $val );
			}
		}
		return $xmp_arr;
	}

	Class GZImageFileException extends Exception
	{
		function __construct($description, $value)
		{
			parent::__construct("GZImageFileException - $description [$value]"); 
		}
	}
	
	class Image
	{
		const THUMB_SIZE = '300';
		const CARD_SIZE = '800';
		private $base_path;
		private $filename;
		
		private $name; //The name of the id minus directory
		private $directory; //The dirctory of the id minus the name
		private $id; //The directory and name of the image starting at base ie 'pics'
		private $ext; //File extension
		private $base_name; //name - extension
		
		private $root_url;
		
		private $thumb_url;
		private $thumb_path;
		private $card_url;
		private $card_path;
		
		private $info_json;
		private $xmp_json = "{}";
		
		private $logger;
		private $file_exists = false;
		private $image_type;
		
		private $img_width = NULL;
		private $img_height = NULL;
		
		private $title;
		private $description;
		private $creator;
		private $keywords;
		
		//Exif data and objects
		private $exifObj;
		
		function __construct($base_path, $filename, $id)
		{
			$this->base_path = $base_path;
			$this->filename = $filename;
			$this->id = $id;
			$this->name = basename($id);
			$this->ext = pathinfo($id,PATHINFO_EXTENSION);
			$this->base_name = rtrim(basename($id,$this->ext),".");
			$this->directory = dirname($id);
			$this->root_url = ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
			$this->logger = new Log4Me(Log4Me::DEBUG,"log.txt");
			$this->logger->setContext("Image Class", $_SERVER['PHP_SELF']);
			$this->year = "";
			$this->fileDateTime = NULL;
			if (file_exists($filename))
			{
				//$this->logger->debug("imagick loaded: " . extension_loaded('imagick'));
				$file_exists = true;
				$this->image_type = exif_imagetype($filename);
				if($this->image_type)
				{
					$this->makeThumb();
					$this->makeCard();
				}
				else
				{
					$this->logger->debug("File: " . $filename . " Image type not supported");
					//throw new GZImageFileException("Image type not supported",$filename);
				}
			}
			else
			{
				$this->logger->debug("File: " . $filename . " does not exist");
				throw new GZImageFileException("File does not exist",$filename);
			}
			
		}
		
		function __destruct()
		{
		}
		
		public function getImageTypeString()
		{
			switch($this->image_type)
			{
				case IMAGETYPE_GIF:
					return "IMAGETYPE_GIF";
				case IMAGETYPE_JPEG:
					return "IMAGETYPE_JPEG";
				case IMAGETYPE_PNG:
					return "IMAGETYPE_PNG";
				case IMAGETYPE_SWF:
					return "IMAGETYPE_SWF";
				case IMAGETYPE_PSD:
					return "IMAGETYPE_PSD";
				case IMAGETYPE_BMP:
					return "IMAGETYPE_BMP";
				case IMAGETYPE_TIFF_II: //(intel byte order)
					return "IMAGETYPE_TIFF_II";
				case IMAGETYPE_TIFF_MM: //(motorola byte order)
					return "IMAGETYPE_TIFF_MM";
				case IMAGETYPE_JPC:
					return "IMAGETYPE_JPC";
				case IMAGETYPE_JP2:
					return "IMAGETYPE_JP2";
				case IMAGETYPE_JPX:
					return "IMAGETYPE_JPX";
				case IMAGETYPE_JB2:
					return "IMAGETYPE_JB2";
				case IMAGETYPE_SWC:
					return "IMAGETYPE_SWC";
				case IMAGETYPE_IFF:
					return "IMAGETYPE_IFF";
				case IMAGETYPE_WBMP:
					return "IMAGETYPE_WBMP";
				case IMAGETYPE_XBM:
					return "IMAGETYPE_XBM";
				case IMAGETYPE_ICO:
					return "IMAGETYPE_ICO";
				default:
					return "UNKNOWN";
			}
		}
		
		public function getWidth()
		{
			if(!$this->img_width)
			{
				list($this->img_width, $this->img_height) = getimagesize($this->filename);
			}
			return $this->img_width;
		}
		
		public function getHeight()
		{
			if(!$this->img_height)
			{
				list($this->img_width, $this->img_height) = getimagesize($this->filename);
			}
			return $this->img_height;
		}

		public function getName()
		{
			return $this->name;
		}
		
		public function getExtension()
		{
			return $this->ext;
		}
		
		public function getBaseName()
		{
			return $this->base_name;
		}

		public function getThumbURL()
		{
			return $this->thumb_url;
		}
		
		public function getCardURL()
		{
			return $this->card_url;
		}

		public function getInfoJson()
		{
			return $this->info_json;
		}
		
		public function getExifObj()
		{
			return $this->exifObj;
		}
		
		//public function getExifJson()
		//{
		//	return $this->exifObj->getJson();
		//}
		
		public function getXmpJson()
		{
			return $this->xmp_json;
		}
		
		public function getTitle()
		{
			return $this->title;
		}
		
		public function getDescription()
		{
			return $this->description;
		}
		
		public function getCreator()
		{
			return $this->creator;
		}
		
		public function getKeywords()
		{
			return $this->keywords;
		}
		
		public function getFileInfo()
		{
			//Get the XMP data
			try
			{
				//$this->logger->debug("filename " . $this->filename);
				if($xmp_data = getXmpData($this->filename, 4000))
				{
					$xmp_array = get_xmp_array($xmp_data);

					//$this->logger->debug("xmp_array: " . print_r($xmp_array. true));

					$this->title = implode(",", $xmp_array['Title']);
					$this->description = implode(",", $xmp_array['Description']);
					$this->creator = implode(",", $xmp_array['Creator']);
					$this->keywords = implode(",", $xmp_array['Keywords']);
					$this->xmp_json = json_encode($xmp_array);
				}
				else
				{
					//$this->logger->debug("getXmpData NULL");
				}
			}
			catch(RuntimeException $ex)
			{
				$this->logger->debug("Error: " . $ex->getMessage());
			}
			//Get the Exif data
			$this->exifObj = new Exif($this->filename);
						
			$this->info_json = '{"xmp":' . $this->xmp_json . '}'; // . ',"exif":' . $this->exifObj->getJson() . '}';
		}
		
		private function makeThumb()
		{
			$this->thumb_path = $this->makeRealPath("thumbs");
			if(!file_exists($this->thumb_path))
			{
				if(!$this->resizeImage(self::THUMB_SIZE,$this->thumb_path))
				{
					throw new GZImageFileException("Could not make Thumbnail: ", $this->thumb_path);
				}
			}
			$this->thumb_url = $this->root_url . '/cloud/thumbs/' . $this->directory . '/' . $this->name;
		}
		
		private function makeCard()
		{
			$this->card_path = $this->makeRealPath("cards");
			//$this->logger->debug("Test for Card:" . $this->card_path);
			if(!file_exists($this->card_path))
			{
				//$this->logger->debug("Make Card:" . $this->card_path);
				if(!$this->resizeImage(self::CARD_SIZE,$this->card_path))
				{
					throw new GZImageFileException("Could not make Card: ", $this->card_path);
				}
			}
			$this->card_url = $this->root_url . '/cloud/cards/' . $this->directory . '/' . $this->name;
		}

		private function makeRealPath($folder)
		{
			$path = str_replace('/', DIRECTORY_SEPARATOR, $this->id);
			//$this->logger->debug("dirname(" . $path . ") = [" . $this->directory . "] basename[" . $this->name . "]");
			$directory = $this->base_path . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $this->directory;
			//$this->logger->debug("directory[" . $this->directory . "]");
			if(!file_exists($directory))
			{
				if(!mkdir($directory, 0777, true))
				{
					throw new GZImageFileException("Could not create directory: ", $directory);
				}
			}
			$path = trim($path, DIRECTORY_SEPARATOR);
			$path = $this->base_path . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $path;
			return $path;
		}
		
		private function resizeImage($width, $new_file_path)
		{
			//$this->logger->debug("new file path(" . $new_file_path . ") resize image to: " . $width);
			$retVal = false;
			if($this->image_type == IMAGETYPE_JPEG || $this->image_type == IMAGETYPE_PNG)
			{
				//$size = getimagesize($this->filename);
				list($this->img_width, $this->img_height) = getimagesize($this->filename);
				if($this->img_width > $this->img_height)
				{
					//$height=round($width*$size[1]/$size[0]);
					$height=round($width*$this->img_height/$this->img_width);
				}
				else
				{
					$height=$width;
					$width=round($height*$this->img_width/$this->img_height);
				}
				$images_orig = NULL;
				switch($this->image_type)
				{
					case IMAGETYPE_JPEG:
						$images_orig = imagecreatefromjpeg($this->filename);
						break;
					case IMAGETYPE_PNG:
						$images_orig = imagecreatefrompng($this->filename);
						break;
				}
				if($images_orig)
				{
					$photoX = imagesx($images_orig);
					$photoY = imagesy($images_orig);
					//$this->logger->debug("imagecreatetruecolor(" . $width . "," . $height . ")");
					$images_fin = imagecreatetruecolor($width, $height);
					if($images_fin)
					{
						switch($this->image_type)
						{
							case IMAGETYPE_JPEG:
								if(imagecopyresampled($images_fin, $images_orig, 0, 0, 0, 0, $width+1, $height+1, $photoX, $photoY))
								{
									if(imagejpeg($images_fin, $new_file_path, 100))
									{
										//$this->logger->debug("SUCCESS JPEG image: " . $this->filename . " copied to " . $new_file_path);
										$retVal = true;
									}
									else
									{
										$this->logger->debug("ERROR imagejpeg returns FALSE for " . $this->filename . " copied to " . $new_file_path);
									}
								}
								else
								{
									$this->logger->debug("ERROR imagecopyresampled returns FALSE");
								}
								break;
							case IMAGETYPE_PNG:
								imagecolortransparent($images_fin, imagecolorallocate($images_fin, 0, 0, 0));
								imagealphablending($images_fin, false);
								imagesavealpha($images_fin,true);
								if(imagecopyresampled($images_fin, $images_orig, 0, 0, 0, 0, $width+1, $height+1, $photoX, $photoY))
								{
									if(imagepng($images_fin, $new_file_path))
									{
										//$this->logger->debug("SUCCESS PNG image: " . $this->filename . " copied to " . $new_file_path);
										$retVal = true;
									}
									else
									{
										$this->logger->debug("ERROR imagepng returns FALSE for " . $this->filename . " copied to " . $new_file_path);
									}
								}
								else
								{
									$this->logger->debug("ERROR imagecopyresampled returns FALSE");
								}
								break;
						}
					}
					else
					{
						$this->logger->debug("ERROR imagecreatetruecolor returns FALSE");
					}
					imagedestroy($images_orig);
					imagedestroy($images_fin);
				}
				else
				{
					$this->logger->debug("ERROR imagecreatefrompng(" . $this->filename . ") returns FALSE");
				}
			}
			else
			{
				throw new GZImageFileException("Image resize for type: " . $this->getImageTypeString() . " is not supported",$filename);
			}
			return $retVal;
		}
	}
	
	Class GZFileException extends Exception
	{
		function __construct($description, $value)
		{
			parent::__construct("GZFileException - $description [$value]"); 
		}
	}
	
	/*
	*/
	
	class Exif
	{
		private $logger;
		private $exif_json = "{}";
		
		//FILE
		private $fileDateTime;
		private $fileName;
		private $fileSize;
		private $fileType;
		private $mimeType;
		private $sectionsFound;

		//IFD0
		private $make;
		private $model;  
		private $orientation;
		private $xResolution;
		private $yResolution;
		private $resolutionUnit;
		private $software;                
		private $dateTime;
       	private $artist;
       	private $copyright;	
	   
		//EXIF
		private $dateTimeOriginal;
		private $dateTimeDigitized;
		
		//GPS
		private $gpsStat;
		private $gpsLat;
		private $gpsLng;
		private $gpsLatString;
		private $gpsLngString;
		private $gpsAlt;
		
		private $year;
		
		function __construct($filename)
		{
			$this->logger = new Log4Me(Log4Me::DEBUG,"log.txt");
			$this->logger->setContext("Exif Class", $_SERVER['PHP_SELF']);
			//$this->logger->debug("filename: " . $filename);
			if($exif = exif_read_data($filename, 0, true))
			{
				//$this->logger->debug(print_r($exif,true));
				
				$this->fileDateTime = $exif['FILE']['FileDateTime'];
				$this->fileName = $exif['FILE']['FileName'];
             	$this->fileSize = $exif['FILE']['FileSize'];
            	$this->fileType = $exif['FILE']['FileType'];
            	$this->mimeType = $exif['FILE']['MimeType'];
				$this->sectionsFound = $exif['FILE']['SectionsFound'];
				
				$this->make = $exif['IFD0']['Make'];
				$this->model = $exif['IFD0']['Model'];  
				$this->orientation = $exif['IFD0']['Orientation'];
				$this->xResolution = $exif['IFD0']['XResolution'];
				$this->yResolution = $exif['IFD0']['YResolution'];
				$this->resolutionUnit = $exif['IFD0']['ResolutionUnit'];
				$this->software = $exif['IFD0']['Software'];                
				$this->dateTime = $exif['IFD0']['DateTime'];
				$this->artist = $exif['IFD0']['Artist'];
       			$this->copyright = $exif['IFD0']['Copyright'];
				
				$this->dateTimeOriginal = $exif['EXIF']['DateTimeOriginal'];
				$this->dateTimeDigitized = $exif['EXIF']['DateTimeDigitized'];	
	   
				//$this->logger->debug("FILE:" . $filename);
				$this->calcGPSData($exif);
					
				$year = $exif['IFD0']['DateTime'];
				//$this->logger->debug("year[" . $year . "]");
				if($year == "")
				{
					$this->year = "Upload Date: " . date('Y',$this->fileDateTime);
					//$this->logger->debug("this->fileDateTime Y[" . $this->year . "]");
				}
				else
				{
					//2013:07:29 10:09:32
					$ar = explode(":",$year);
					$this->year = trim($ar[0]);
					//$this->logger->debug("ar[0][" . $this->year . "]");
				}
				//$this->exif_json = json_encode($exif);
			}
			else
			{
				$this->logger->debug("exif_read_data returns FALSE for: " . $filename);
				//throw new RuntimeException("exif_read_data returns FALSE for: " . $filename);
			}
		}

		private function calcGPSData($exif)
		{
		   	$this->gpsStat = false;
			//$this->logger->debug("exif['GPS'] = " . $exif['GPS']);
			if($exif['GPS'])
		   	{
				if($exif['GPS']['GPSLatitude'][0])
				{
					$this->gpsStat = true;
				}
				//[GPSVersion] => 
				$deg = rationalToFloat($exif['GPS']['GPSLatitude'][0]);
				$min = rationalToFloat($exif['GPS']['GPSLatitude'][1]);
				$sec = rationalToFloat($exif['GPS']['GPSLatitude'][2]);
				$hem = $exif['GPS']['GPSLatitudeRef'];
				$this->gpsLat = toDecimal($deg, $min, $sec, $hem, LOCATION_PRECISION);
				$this->gpsLatString = toLocationString($deg, $min, $sec, $hem);
				
				$deg = rationalToFloat($exif['GPS']['GPSLongitude'][0]);
				$min = rationalToFloat($exif['GPS']['GPSLongitude'][1]);
				$sec = rationalToFloat($exif['GPS']['GPSLongitude'][2]);
				$hem = $exif['GPS']['GPSLongitudeRef'];
				$this->gpsLng = toDecimal($deg, $min, $sec, $hem, LOCATION_PRECISION);
				$this->gpsLngString = toLocationString($deg, $min, $sec, $hem);
				
				$this->gpsAlt = rationalToFloat($exif['GPS']['GPSAltitude']);
				
				//if($this->gpsStat)
				//{
				//	$this->logger->debug($this->gpsLngString);
				//}
			}
		}
		
		//public function getJson()
		//{
		//	return $this->exif_json;
		//}

		//FILE
		public function getFileDateTime()
		{
			return $this->fileDateTime;
		}
		
		public function getFileName()
		{
			return $this->fileName;
		}
		
		public function getFileSize()
		{
			return $this->fileSize;
		}
		
		public function getFileType()
		{
			return $this->fileType;
		}
		
		public function getMimeType()
		{
			return $this->mime_type;
		}

		//IFD0
		public function getMake()
		{
			return $this->make;
		}

		public function getModel()
		{
			return $this->model;
		}

		public function getOrientation()
		{
			return $this->orientation;
		}

		public function getXResolution()
		{
			return $this->xResolution;
		}

		public function getYResolution()
		{
			return $this->yResolution;
		}

		public function getResolutionUnit()
		{
			return $this->resolutionUnit;
		}

		public function getSoftware()
		{
			return $this->software;
		}

		public function getDateTime()
		{
			return $this->dateTime;
		}
		
		public function getArtist()
		{
			return $this->artist;
		}
		
		public function getCopyright()
		{
			return $this->copyright;
		}

		//EXIF
		public function getDateTimeDigitized()
		{
			return $this->dateTimeDigitized;
		}
		
		public function getDateTimeOriginal()
		{
			return $this->dateTimeOriginal;
		}
		
		//GPS
		public function getGpsStat()
		{
			return $this->gpsStat;
		}
		
		public function getGpsLat()
		{
			return $this->gpsLat;
		}
		
		public function getGpsLng()
		{
			return $this->gpsLng;
		}
		
		public function getGpsAlt()
		{
			return $this->gpsAlt;
		}
		
		public function getGpsLatString()
		{
			return $this->gpsLatString;
		}
		
		public function getGpsLngString()
		{
			return $this->gpsLngString;
		}
		
		//ARTWORK defaults
		public function getYear()
		{
			return $this->year;
		}

	}

	class File
	{
		private $filename;
		private $logger;
		private $file_exists = false;
		private $finfo;
		private $mime_type;
		private $encoding;
		
		function __construct($filename)
		{
			$this->filename = $filename;
			
			
			$this->logger = new Log4Me(Log4Me::DEBUG,"log.txt");
			$this->logger->setContext("File Class", $_SERVER['PHP_SELF']);
			
			//$this->logger->debug("filename:" . $filename);
			
			if (file_exists($filename))
			{
				$file_exists = true;
				$this->finfo = new finfo(FILEINFO_MIME);
				$arr = explode(";",$this->finfo->file($filename));
				$this->mime_type = $arr[0];
				$this->encoding = ltrim($arr[1]);
			}
			else
			{
				$this->logger->debug("File: " . $filename . " does not exist");
				throw new GZFileException("File does not exist",$filename);
			}
		}
		
		function getMimeType()
		{
			//$this->logger->debug("MIME TYPE:" . $this->mime_type);
			return $this->mime_type;
		}
		
		function getEncoding()
		{
			//$this->logger->debug("ENCODING:" . $this->encoding);
			return $this->encoding;
		}
		
		function isImage()
		{
			return $this->mime_type == 'image/png' || $this->mime_type == 'image/jpeg';
		}
		
		function isPdf()
		{
			return $this->mime_type == 'application/pdf';
		}
		
		function __destruct()
		{
		}
	}
	
	class GenericFile
	{
		private $base_path;
		private $filename;
		
		private $name; //The name of the id minus directory
		private $directory; //The dirctory of the id minus the name
		private $id; //The directory and name of the image starting at base ie 'pics'
		private $ext; //File extension
		private $base_name; //name - extension
		
		private $root_url;
		
		private $card_url;
		private $card_path;
		
		private $logger;
		
		function __construct($base_path, $filename, $id)
		{
			$this->base_path = $base_path;
			$this->filename = $filename;
			$this->id = $id;
			$this->name = basename($id);
			$this->ext = pathinfo($id,PATHINFO_EXTENSION);
			$this->base_name = rtrim(basename($id,$this->ext),".");
			$this->directory = dirname($id);
			$this->root_url = ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
			$this->logger = new Log4Me(Log4Me::DEBUG,"log.txt");
			$this->logger->setContext("PdfFile Class", $_SERVER['PHP_SELF']);
			if (file_exists($filename))
			{
				$this->makeCard();					
			}
			else
			{
				$this->logger->debug("File: " . $filename . " does not exist");
				throw new GZImageFileException("File does not exist",$filename);
			}
		}
		
		function __destruct()
		{
		}
		
		private function makeRealPath($folder)
		{
			$path = str_replace('/', DIRECTORY_SEPARATOR, $this->id);
			//$this->logger->debug("dirname(" . $path . ") = [" . $this->directory . "] basename[" . $this->name . "]");
			$directory = $this->base_path . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $this->directory;
			//$this->logger->debug("directory[" . $this->directory . "]");
			if(!file_exists($directory))
			{
				if(!mkdir($directory, 0777, true))
				{
					throw new GZImageFileException("Could not create directory: ", $directory);
				}
			}
			$path = trim($path, DIRECTORY_SEPARATOR);
			$path = $this->base_path . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $path;
			return $path;
		}

		private function makeCard()
		{
			$this->card_path = $this->makeRealPath("cards");
			if(!file_exists($this->card_path))
			{
				//$ret = copy($this->filename,$this->card_path);
				//$this->logger->debug("copy(" . $this->filename . ", " . $this->card_path . ") ret:" . $ret);
				if(!copy($this->filename,$this->card_path))
				{
					throw new GZImageFileException("Could not make Card: ", $this->card_path);
				}
			}
			$this->card_url = $this->root_url . '/cloud/cards/' . $this->directory . '/' . $this->name;
		}

		public function getName()
		{
			return $this->name;
		}
		
		public function getCardURL()
		{
			return $this->card_url;
		}
		
		public function getExtension()
		{
			return $this->ext;
		}
		
		public function getBaseName()
		{
			return $this->base_name;
		}
	}

?>