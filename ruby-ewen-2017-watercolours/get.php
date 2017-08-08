<?php
/**
 * Created by PhpStorm.
 * User: thomaswiegand
 * Date: 2017-03-09
 * Time: 4:19 PM
 */

$path = $_GET['path'];

$base = $_SERVER['DOCUMENT_ROOT'] . 'cloud';

$fullpath = $base . DIRECTORY_SEPARATOR . $path;

/*
//realpath() expands all symbolic links and resolves references to '/./', '/../' and extra '/' characters in the input path and returns the canonicalized absolute pathname.
function real($path) {
    $temp = realpath($path);
    if(!$temp) { throw new Exception('Path does not exist: ' . $path); }
    return $temp;
}

//Replace all / forward slashs with the directory seperator on this server
function path($id, $base) {
    $id = str_replace('/', DIRECTORY_SEPARATOR, $id);
    $id = trim($id, DIRECTORY_SEPARATOR);
    $id = real($base . DIRECTORY_SEPARATOR . $id);
    return $id;
}
*/

return readfile($fullpath);



