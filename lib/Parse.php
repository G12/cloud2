<?php
class Parse {

	public static function getFileName($fullPath)
	{
		$parts = explode("/", $fullPath);
		return array_pop($parts);
	}
 
 	//TODO Add better parse functionality
	public static function removeExtension($originalFileName)
	{
		$parts = explode(".", $originalFileName);
		if(count($parts) == 1)
		{
			//No extension
			return $originalFileName;
		}
		elseif(count($parts) == 2)
		{
			return $parts[0];
		}
		else
		{
			array_pop($parts);
			return implode(".", $parts);
		}
	}
	
	//TODO Add better parse functionality
	public static function removeFirstPart($originalFileName, $seperator)
	{
		$parts = explode($seperator, $originalFileName);
		if(count($parts) == 1)
		{
			//No seperator
			return $originalFileName;
		}
		elseif(count($parts) == 2)
		{
			return $parts[1];
		}
		else
		{
			array_shift($parts);
			return implode("_", $parts);
		}
	}

	
	//TODO Add better parse functionality
	public static function removeFirstParts($originalFileName, $seperator, $count)
	{
		$parts = explode($seperator, $originalFileName);
		if(count($parts) == 1)
		{
			//No seperator
			return $originalFileName;
		}
		elseif(count($parts) == 2)
		{
			return $parts[1];
		}
		else
		{
			for($i = 0 ; $i < $count; $i++)
			{
				array_shift($parts);
			}
			return implode("_", $parts);
		}
	}

	public static function RubysParse($originalFileName)
	{
		$name = self::removeExtension($originalFileName);
		$parts = explode("-", $name);
		//for($i = 0; $i < count($parts); $i++)
		//{
		//	echo "<h1>" . trim($parts[$i]) . "</h1>";
		//}
		$retVal = array();
		//Name
		$retVal['Name'] = trim($parts[0]);
		//Description
		$retVal['Description'] = trim($parts[1]);
		//Date
		$retVal['Date'] = trim($parts[2]);
		
		$parts2 = explode("in.", trim($parts[3]));
		$dimensions = explode("x", trim($parts2[0]));
		
		//Height
		$retVal['Height'] = trim($dimensions[0]);
		//Width
		$retVal['Width'] = trim($dimensions[1]);
		
		return $retVal;
	}
  	
  	public static function is_valid($uuid)
  	{
    	return preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?'.
                      '[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $uuid) === 1;
  	}
  	
  	
}

?>