<?php

include_once "DbHelper.class.php";

class CloudRecordSetHelper extends RecordSetHelper
{
	function __construct($dbName, $tableName, $pdo = NULL)
	{
		parent::__construct(new MySQLTable($dbName, $tableName, $pdo), false);
	}
}