<?php
/**
 * Created by Sublime Text3
 * User: ryx
 * Data: 2018-11-29
 */

namespace App\Model;

use EasySwoole\Spl\SplBean;

class Test extends SplBean
{
	protected $id;
	protected $name;
	protected $yanzhi;

	public function getId()
	{
		return $this->id;
	}

	public function getName()
	{
		return $this->name;
	}

	public function setName($name): void
	{
		$this->name = $name;
	}

	public function getYanzhi()
	{
		return $this->yanzhi;
	}

	public function setYanzhi($yanzhi): void
	{
		$this->yanzhi = $yanzhi;
	}
}