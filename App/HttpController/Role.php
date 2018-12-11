<?php
namespace App\HttpController;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;
use App\Utility\Pool\MysqlObiect;
use EasySwoole\Validate\Validate;
use EasySwoole\Http\Request;

class Role extends Auth
{
	protected $route_type = array(
		'update' => 'patch',
		'index' => 'get',
	);
	
	protected function onRequest(?string $action): ?bool
	{
		if (!$this->is_method_allow() || !parent::onRequest($action)) 
			return false;

		return true;
	}


	/**
	 * 角色权限修改
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @return   [type]     [description]
	 */
	public function update()
	{
		$params = $this->request()->getRequestParam('admin_id', 'actions');
		// var_dump($params);
		try {
			$this->db->startTransaction();
			$this->db->where('admin_id', $params['admin_id'])->delete('roles');
			foreach ($params['actions'] as $val) {
				$this->db->insert('roles', ['admin_id' => $params['admin_id'], 'action_id' => $val]);
			}

			$this->db->commit();
			return $this->writeJson(200, [], '修改成功');
		} catch (Exception $e) {
			$this->db->callback();
			return $this->writeJson(10003, [], $e->getMessage());
		}
	}


	/**
	 * 查询角色权限
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @return   [type]     [description]
	 */
	public function index()
	{
		$role_id = $this->request()->getRequestParam('role_id');
		$this->db->where('role_id', $role_id, '=', 'and');
		$actions = $this->db->get('roles', null, 'action_id');

        $routes = array();
        foreach ($actions as $val) {
            $this->db->where('id', $val['action_id']);
            $result = $this->db->get('actions');
            $routes[] = $result[0];
        }

		return $this->writeJson(200, $routes ?: [], 'success', count($routes));
	}


	/**
	 * 修改权限验证
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @param    array      $data [用户输入数据]
	 * @return   [bool/array]     [true验证成功，数组失败信息]
	 */
	private function valt(array $data)
	{
		$valitor = new Validate();
		$valitor->addColumn('admin_id', '角色id不能为空')
				->required('角色id不能为空');
		$valitor->addColumn('actions', '路由id不为空')
				->required('路由id不为空');

		$result = $valitor->validate($data);

		return $result ?: $valitor->getError()->getErrorRuleMsg();
	}

}