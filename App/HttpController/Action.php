<?php
namespace App\HttpController;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;
use App\Utility\Pool\MysqlObiect;
use EasySwoole\Validate\Validate;
use EasySwoole\Http\Request;
use EasySwoole\EasySwoole\Swoole\Time\Timer;
use App\Model\Model;

class Action extends Auth
{
	protected $route_type = array(
		'store' => 'post',
		'index' => 'get',
		'test' => 'get',
		'roleRoute' => 'get'
	);
	
	protected function onRequest(?string $action): ?bool
	{
		if (!$this->is_method_allow() || !parent::onRequest($action)) 
			return false;

		return true;
	}

	/**
	 * 添加路由
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @return   [type]     [description]
	 */
	public function store()
	{
		$post = $this->request()->getRequestParam('route', 'type', 'name');
		$valitor = $this->valt($post);

		if (!is_bool($valitor)) 
			return $this->writeJson(10002, [], $valitor);

		$this->db->insert('actions', $post);

		return $this->writeJson(200, [], '权限添加成功');
	}


	/**
	 * 查询路由
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @return   [type]     [description]
	 */
	public function index()
	{
		$data = $this->db->get('actions');

		return $this->writeJson(200, $data, 'success', count($data));
	}

	/**
	 * please input description
	 * @Author   author
	 * @DateTime 2018-12-04
	 * @return   [type]     [description]
	 */
	public function roleRoute()
	{
		$actions = $this->db->where('admin_id', $this->request()->getRequestParam('admin_id'))->get('roles', null, 'action_id');

        $routes = array();
        foreach ($actions as $val) {
            $result = $this->db->where('id', $val['action_id'])->get('actions', null, 'id,name,type,route');
            foreach ($result as $v) {
                $routes[] = $v;
            }
        }

        $data = $this->db->get('actions', null, 'id,name,type,route');
        foreach ($data as $key => $val) {
        	$data[$key]['is_selected'] = false;
        	foreach ($routes as $v) {
        		if ($val['id'] == $v['id']) {
        			$data[$key]['is_selected'] = true;
        		}
        	}
        }

		return $this->writeJson(200, $data, 'success', count($routes));
	}


	/**
	 * 添加路由验证
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @param    array      $data [用户输入数据]
	 * @return   [bool/array]     [true验证成功，数组失败信息]
	 */
	private function valt(array $data)
	{
		$valitor = new Validate();
		$valitor->addColumn('route', '路由不能为空')
				->required('路由不能为空');
		$valitor->addColumn('type', '类型不为空')
				->required('类型不为空');
		$valitor->addColumn('name', '权限名称不为空')
				->required('权限名称不为空');

		$result = $valitor->validate($data);

		return $result ?: $valitor->getError()->getErrorRuleMsg();
	}
}