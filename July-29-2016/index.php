<?php

require_once("../lib/Log4me.php");
require_once("../lib/DbHelper.class.php");
require_once("../lib/dbinfo.php");

class QuickSample
{
	private $logger;
	private $table;
	
	public function __construct()
	{
		$this->logger = new Log4Me(Log4me::DEBUG,"log.txt");
		$this->logger->setContext("Pages Class", $_SERVER['PHP_SELF']);
 	}
	
	private function lazyLoad()
	{
		//Lazy load objects
		if(!$this->table)
		{
			$mysqlTable = new  MySQLTable(DB_NAME, "IMAGES");
			$this->table = new RecordSetHelper($mysqlTable, false);
		}
	}
	
	public function getImageArray()
	{
		$this->lazyLoad();
		
$sql = 'SELECT i.path, a.artwork_title, a.artwork_year, a.artwork_height, a.artwork_width, a.artwork_units '
        . ' FROM IMAGES AS i INNER JOIN ARTWORKS a ON i.artwork_id = a.artwork_id'
        . ' where a.artwork_height >= 30'
        . ' AND a.artwork_units = "inches"'
        . ' AND (i.path LIKE "ruby/2007%" OR i.path LIKE "ruby/2008%" OR i.path LIKE "ruby/2009%" OR i.path LIKE "ruby/2010%" OR i.path LIKE "ruby/2011%")'
        . ' ORDER BY if(a.artwork_height > a.artwork_width, a.artwork_height, a.artwork_width) DESC';
		
		$rs = $this->table->getRecordSet($sql);
		$info = $rs->fetchAll(PDO::FETCH_ASSOC);
		
		return $info;
		
	}
}

$quickSample = new QuickSample();

//die();

?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<title>Ruby Ewen Large Paintings 2016</title>

    <!-- Add fancyBox -->
    <link rel="stylesheet" href="../fancybox/jquery.fancybox.css" type="text/css" media="screen" />
    <!-- Optionally add helpers - button, thumbnail and/or media -->
    <link rel="stylesheet" href="../fancybox/helpers/jquery.fancybox-buttons.css?v=1.0.5" type="text/css" media="screen" />
    <link rel="stylesheet" href="../fancybox/helpers/jquery.fancybox-thumbs.css?v=1.0.7" type="text/css" media="screen" />
    
<style type="text/css">

	body {
		background-color:black;
	}
	
	figcaption {
		background-color:#D9D7D7;
		border:2px solid white;
		padding:4px 20px;
	}
	
	a {
		text-decoration:none;
		color:black;
	}
	.img_box{
		display:inline-block;
		height:300px;
		width:300px;
		border: 1px solid black;
		margin:10px;
		background-color:black;
	}
	
	/*
	.fancybox-skin {
 		background-color: black !important;
	}
	*/
	
</style>	


</head>

<body>
<div>
<?php
		//$page = array('fields' => '');
		//echo '<div class="content default" style="height: 350px; display: block;"><div>';
		echo "<div>";
		
		$info = $quickSample->getImageArray();
		foreach($info as $row)
		{
			
			$title = $row['artwork_title'];
			$dimensions = $row['artwork_height'] . " x " . $row['artwork_width'] . "  " . $row['artwork_units'];
			 
			
			$path = $row['path'];
			//echo "<h2>" . $title . "</h2><h4>" . $path . "</h4>";
			
			echo '<div class="img_box">';
			echo '<a class="fancybox" rel="group" title="' . $title . '" href="http://surrealranch.ca/cloud/cards/' . $path . '">';
			echo '<figure>';
			echo '<img class="portrait" alt="' . $title . '" title="' . $title . '"';
			echo ' src = "http://surrealranch.ca/cloud/thumbs/' . $path . '">';
			echo '</figure><figcaption><strong>'. $title . '</strong><br>' . $dimensions . '</figcaption></a></div>';
					
		}
		$html .= '</div></div>';
?>
</div>

<!-- Javascript Imports -->
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/jquery-ui.min.js"></script>

<!-- Add mousewheel plugin (this is optional) -->
<script src="../fancybox/lib/jquery.mousewheel.pack.js"></script>

<!-- Add fancyBox 2.1.5 -->
<script src="../fancybox/jquery.fancybox.pack.js"></script>

<!-- My javascript -->
<!--
<script src="../js/profile.js?ver=1.000.002"></script>
<script src="../js/index.js?ver=1.000.002"></script>
-->

<!-- Bootstrap core JavaScript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
<!--<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>-->
<!--<script>window.jQuery || document.write('<script src="bootstrap-3.3.6-dist/assets/js/vendor/jquery.min.js"><\/script>')</script>-->
<script src="../bootstrap-3.3.6-dist/js/bootstrap.min.js"></script>
<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
<script src="../bootstrap-3.3.6-dist/assets/js/ie10-viewport-bug-workaround.js"></script>

<script 
//Initialize fancybox for use on the list of thumbnails generated above.
    $(".fancybox").fancybox({
        helpers : {
            title : {
                type : 'inside'
            }
        }
    });
</script>

</body>
</html>