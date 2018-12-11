<?php

namespace App\Model;

use App\Utility\Pool\MysqlObject;
use App\Model\ModelWithDb;

class BaseModel
{
	private $db;

	function __construct()
	{
		$db = new ModelWithDb;
		$this->db = $db->getDb();
	}

	function getDbConnection(): MysqlObject
	{
		return $this->db;
	}
}