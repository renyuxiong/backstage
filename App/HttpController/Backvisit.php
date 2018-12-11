<?php
namespace App\HttpController;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;
use App\Utility\Pool\MysqlObiect;
use EasySwoole\Validate\Validate;
use EasySwoole\Http\Request;
use App\Model\Model;
use EasySwoole\EasySwoole\Swoole\Time\Timer;
use EasySwoole\EasySwoole\ServerManager;

class Backvisit extends Auth
{
	protected $route_type = array(
		'store' => 'post',
		'index' => 'get',
		'update' => 'patch'
	);
	
	protected function onRequest(?string $action): ?bool
	{
		if (!$this->is_method_allow() || !parent::onRequest($action)) 
			return false;

		return true;
	}


	/**
	 * 添加回访记录
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @param   $customer_id [<description>]
	 * @param   $content [<description>]
	 * @return   [json]     [description]
	 */
	public function store()
	{
		$post = $this->request()->getRequestParam('customer_id', 'content', 'status', 'result', 'b_time');

		$valitor = $this->valt($post);
		if (!is_bool($valitor)) 
			return $this->writeJson(10002, [], $valitor);

		if (!$this->admin_auth(['action' => 'customer', 'content' => $post['customer_id']]))
			return $this->writeJson(10005, [], '不能操作别人的信息');

		$post['adminstor'] = $this->info['name'];

		$admin_id = $this->info['id'];

		try{
			$this->db->startTransaction();
			$this->db->insert('backvisit', $post);
			// 获取提醒信息
			$customer = $this->db->where('id', $post['customer_id'])->getOne('customers', null, 'id,name');
			$this->db->commit();

			//生成单次定时器
			$b_time = strtotime($post['b_time']);
			$remind_time = ($b_time - time() - 600) * 1000;

			$this->create_timmer($remind_time, $admin_id, $customer, $post);

			return $this->writeJson(200, [], '添加回访记录成功');
		} catch (Exception $e) {
			$this->db->rollback();
			return $this->writeJson(10003, [], $e->getMessage());
		}
	}


	/**
	 * 查询回访记录
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @param   $customer_id [<description>]
	 * @return   [json]     [description]
	 */
	public function index()
	{
		$customer_id = $this->request()->getRequestParam('customer_id');
		$this->db->where('customer_id', $customer_id);
		$data = $this->db->get('backvisit');

		return $this->writeJson(200, $data ?: [], 'success');
	}

	/**
	 * 修改回访记录
	 * @Author   author
	 * @DateTime 2018-12-04
	 * @return   [type]     [description]
	 */
	public function update()
	{
		$data = $this->request()->getRequestParam('backvisit_id', 'content', 'status', 'result');

		if (!$this->admin_auth(['action' => 'backvisit', 'content' => $data['backvisit_id']]))
			return $this->writeJson(10005, [], '不能操作别人的信息');

		$id = $data['backvisit_id'];
		unset($data['backvisit_id']);

		$this->db->where('id', $id)->update('backvisit', $data);
		return $this->writeJson(200, [], '修改成功');
	}

	/**
	 * 添加回访记录验证
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @param    array      $data [用户输入数据]
	 * @return   [bool/array]     [true验证成功，数组失败信息]
	 */
	private function valt(array $data)
	{
		$valitor = new Validate();
		$valitor->addColumn('customer_id', '用户不为空')->required('用户不为空');
		$valitor->addColumn('content', '内容不为空')->required('内容不为空');
		$valitor->addColumn('status', '回访状态不为空')->required('回访状态不为空');
		$valitor->addColumn('result', '回访结果不为空')->required('回访结果不为空');

		$result = $valitor->validate($data);

		return $result ?: $valitor->getError()->getErrorRuleMsg();
	}

	private function create_timmer($remind_time, $admin_id, $customer, $post)
	{
		if ($remind_time > 666) {
			if ($remind_time > 86400000) {
				Timer::delay(86400000, function () use ($admin_id, $customer, $post) {
					$remind_time -= 86400000;
					$this->create_timmer();
				});
			} else {
				Timer::delay($remind_time, function () use ($admin_id, $customer, $post) {
					$info = (new Model())->getDB()->where('id', $admin_id)->getOne('adminstors');
					$customer['b_time'] = $post['b_time'];
					// 判断管理员是否在线
					if ($info['fd'] != 0) {
						// 在线的话，发送回访通知
						$server = ServerManager::getInstance()->getSwooleServer();
						$server->push($info['fd'], json_encode(['action' => 'backvisit_remind', 'data' => $customer]));
					}
				});
			}
		}
	}

}