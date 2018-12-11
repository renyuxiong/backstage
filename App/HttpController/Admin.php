<?php
namespace App\HttpController;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;
use App\Utility\Pool\MysqlObiect;
use EasySwoole\Validate\Validate;
use EasySwoole\Http\Request;
use App\Model\Model;

class Admin extends Auth
{
	protected $route_type = array(
		'store' => 'post',
		'index' => 'get',
		'update' => 'patch',
		'delete' => 'delete',
		'adminInfo' => 'get'
	);
	
	protected function onRequest(?string $action): ?bool
	{
		if (!$this->is_method_allow() || !parent::onRequest($action)) 
			return false;

		return true;
	}


	/**
	 * 添加管理员
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @return   [type]     [description]
	 */
	public function store()
	{
		$post = $this->request()->getRequestParam('name', 'password', 'role');
		$valitor = $this->valt($post);

		if (!is_bool($valitor)) 
			return $this->writeJson(10002, [], $valitor);

		$post['password'] = md5($post['password']);
		$role = $post['role'];
		unset($post['role']);
		try{
			$this->db->startTransaction();
			$this->db->insert('adminstors', $post);
			$admin_id = $this->db->getInsertId();
			foreach ($role as $val) {
				$this->db->insert('roles', ['admin_id' => $admin_id, 'action_id' => $val]);
			}

			$this->db->commit();
			return $this->writeJson(200, [], '添加管理员成功');
		} catch (Exception $e) {
			$this->db->rollback();
			return $this->writeJson(10003, [], $e->getMessage());
		}
	}


	/**
	 * 查询管理员
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @return   [type]     [description]
	 */
	public function index()
	{
		$admin_id = $this->request()->getRequestParam('admin_id');
		
		if ($admin_id === null) {
			$data = $this->db->get('adminstors', null, 'id,name');
		} else {
			$this->db->where('id', $admin_id, '=', 'and');
			$data = $this->db->getOne('adminstors', null, 'id,name');
			$data['role'] = $this->db->where('admin_id', $data['id'])->get();
		}

		return $this->writeJson(200, $data ?: [], 'success', count($data));
	}

	/**
	 *  修改管理员信息
	 *  @param   $[name] [<description>]
	 */
	public function update()
	{
		$data = $this->request()->getRequestParam('id');
		
		return $this->writeJson(200, [], '修改权限成功');
	}

	/**
	 * 获取管理员信息
	 * @Author   author
	 * @DateTime 2018-11-30
	 * @return   [type]     [description]
	 */
	public function adminInfo()
	{
		return $this->writeJson(200, $this->info, 'success');
	}

	/**
	 * 管理员删除
	 * @param   $[id] [<description>]
	 */
	public function delete()
	{
		$admin_id = $this->request()->getRequestParam('id');
		$this->db->where('id', $admin_id)->delete('adminstors');

		return $this->writeJson(200, [], '删除管理员成功');
	}


	/**
	 * 添加管理员验证
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @param    array      $data [用户输入数据]
	 * @return   [bool/array]     [true验证成功，数组失败信息]
	 */
	private function valt(array $data)
	{
		$valitor = new Validate();
		$valitor->addColumn('name', '名字不为空')
				->required('名字不为空')
				->lengthMin(6, '名字最小长度不少于6位');
		$valitor->addColumn('password', '密码不为空')
				->required('密码不为空')
				->lengthMin(6, '密码最小长度不少于6位');
		$valitor->addColumn('role', '权限不能为空')
				->required('权限不为空');

		$result = $valitor->validate($data);

		return $result ?: $valitor->getError()->getErrorRuleMsg();
	}
}