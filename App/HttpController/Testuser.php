<?php
namespace App\HttpController;
use EasySwoole\Http\Request;
use App\Model\Model;
use App\Model\BaseWithRedis;


class Testuser extends Auth
{	
	private $table = 'test';

	protected function onRequest(?string $action): ?bool
	{
		return true;
	}

	public function index()
	{
		// $db = new Model();
		// $info = $db->getDB()->where('id', 1)->getOne($this->table);
		// $user = new Test($info);
		// $user->setName('renyuxiongshuai');
		// var_dump($user);
		$redis = new BaseWithRedis();
		$redis = $redis->getRedis();
		var_dump($redis);
		
	}

	public function store()
	{
		echo 2131;
	}
}