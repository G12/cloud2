<?php
/**
 * User: thomaswiegand
 * Date: 2017-05-14
 * Time: 1:28 PM
 */

$path = $_GET['path'];

$base = $_SERVER['DOCUMENT_ROOT'] . 'cloud';

$fullpath = $base . DIRECTORY_SEPARATOR . $path;

return readfile($fullpath);



