<?php

namespace App\Model;

use App\Model\BaseModel;

class Model extends BaseModel
{
	public function getDB()
	{
		return $this->getDbConnection();
	}
}