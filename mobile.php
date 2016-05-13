<?php
require($_SERVER['DOCUMENT_ROOT'] . "/lib/Session.php");
$dbConn = new GZDBConn(DB_SERVER, DB_USERNAME, DB_PW, DB_NAME);
$sessionWrap = new GZSessionsWrapper($dbConn);
$user = $sessionWrap->validate();
?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Mobile Upload Prototype</title>
</head>
<body>
	<input type="file" id="upload_files" name="files[]" multiple>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
    <!--File Upload -->
    <script src="js/file_upload/vendor/jquery.ui.widget.js"></script>
    <script src="js/file_upload/jquery.iframe-transport.js"></script>
    <script src="js/file_upload/jquery.fileupload.js"></script>
    <script type="text/javascript">
	$(function()
	{
		$.getJSON('lib/MenuOps.php?operation=get_node',{id:'giz/Places'},function(json)
		{
			var t = json;
		});
		$('#upload_files').fileupload(
		{
			dataType: 'json',
			add: function (e, data) {
				 data.url = 'plugin_manager.php?folder=giz/exif_images'; //g_url;			
				data.context = $('<p/>').text('Uploading...').appendTo("#msg");
				data.submit();
			},
			done: function (e, data) {
				data.context.text('Upload finished.');
			}
		});
		

    });
	</script>
</body>
</html>
