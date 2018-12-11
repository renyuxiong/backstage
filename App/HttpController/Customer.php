<?php
namespace App\HttpController;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;
use App\Utility\Pool\MysqlObiect;
use EasySwoole\Validate\Validate;
use EasySwoole\Http\Request;
use EasySwoole\EasySwoole\ServerManager;

class Customer extends Auth
{
	protected $route_type = array(
		'store' => 'post',
		'index' => 'get',
		'update' => 'patch',
		'delete' => 'delete',
		'info' => 'get',
		'update' => 'patch',
		'transfer' => 'patch'
	);
	
	protected function onRequest(?string $action): ?bool
	{
		if (!$this->is_method_allow() || !parent::onRequest($action)) 
			return false;

		return true;
	}


	/**
	 * 添加患者
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @return   [type]     [description]
	 */
	public function store()
	{
		$post = $this->request()->getRequestParam('name', 'birthday', 'email', 'address', 'phone', 'qq', 'wechat', 'age', 'sex', 'type', 'ad_time', 'app_time', 'is_jiuzhen', 'channel', 'price', 'hospital', 'spread', 'intentionality', 'beizhu');
		$valitor = $this->valt($post);
		if (!is_bool($valitor)) 
			return $this->writeJson(10002, [], $valitor);

		$post['adminstor'] = $this->info['name'];
		$post['sex'] = $post['sex'] ?? 2;
		$post['create_time'] = date('Y-m-d H:i:s');
		if ($post['address'] == 'undefined') {
			$post['address'] = '';
		}

		try{
			$this->db->startTransaction();
			$this->db->insert('customers', $post);
			$this->db->commit();
		} catch (Exception $e) {
			$this->db->rollback();
			return $this->writeJson(10003, [], $e->getMessage());
		}

		return $this->writeJson(200, [], '添加患者成功');
	}


	/**
	 * 查询患者
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @return   [type]     [description]
	 */
	public function index()
	{
		// 分页
		$page_info = $this->request()->getRequestParam('page', 'page_size', 'key_word', 'sort', 'start_time', 'end_time', 'today_total', 'self_app_total', 'self_today_total', 'today_wait_backvist', 'app_total');
		$is_page = null;
		if ($page_info['page'] && $page_info['page_size']) {
			$is_page = [($page_info['page'] - 1) * $page_info['page_size'], $page_info['page_size']];
		}

		$start_date = date('Y-m-d') . ' 00:00:00';
		$end_date = date('Y-m-d') . ' 23:59:59';
		if ($page_info['today_total'] || $page_info['app_total']) {
			// 获取总预约或者今日总预约  需要  验证权限
			$role = $this->db->where('admin_id', $this->info['id'])->where('action_id', 14)->getOne('roles');
			if (NULL === $role) {
				return $this->writeJson(10004, [], '没有权限进行查看');
			}

			if ($page_info['today_total']) {
				$this->db->where('create_time', $start_date, '>', 'and')->where('create_time', $end_date, '<', 'and');
			}
		} else {
			$this->db->where('adminstor', $this->info['name'], '=', 'and');
		}

		// 模糊查询
		if ($page_info['key_word'])
			$this->db->where('name', '%' . $page_info["key_word"] . '%', 'like', 'and');

		if ($page_info['start_time'])
			$this->db->where('create_time', $page_info['start_time'] . ' 00:00:00', '>', 'and');

		if ($page_info['end_time'])
			$this->db->where('create_time', $page_info['end_time'] . ' 23:59:59', '<', 'and');

		if ($page_info['self_app_total'])
			$this->db->where('adminstor', $this->info['name']);

		if ($page_info['self_today_total'])
			$this->db->where('create_time', $start_date, '>', 'and')->where('create_time', $end_date, '<', 'and')->where('adminstor', $this->info['name']);

		if ($page_info['today_wait_backvist']) {
			$customer_ids = $this->db->where('b_time', $start_date, '>', 'and')->where('b_time', $end_date, '<', 'and')->where('adminstor', $this->info['name'])->where('status', 0)->get('backvisit', null, 'customer_id');
			$ids = [];
			foreach ($customer_ids as $val) {
				$ids[] = $val['customer_id'];
			}
			if (count($ids) == 0) {
				return $this->writeJson(200, [], 'success', 0);
			}

			$this->db->whereIn('id', $ids);
		}

		//排序
		if ($page_info['sort']) {
			switch ($page_info['sort']) {
				case 'intentionality':
					$this->db->orderBy('intentionality', 'desc');
					break;
				case 'ad_time':
					$this->db->orderBy('ad_time', 'desc');
					break;
				default:
					$this->db->orderBy('id', 'desc');
					break;
			}
		} else {
			$this->db->orderBy('id', 'desc');
		}

		$data = $this->db->get('customers', $is_page, 'id,name,adminstor,intentionality,ad_time');

		return $this->writeJson(200, $data ?: [], 'success', count($data));
	}

	/**
	 *  查询患者详情
	 *  @param   $[name] [<description>]
	 */
	public function info()
	{
		$customer_id = $this->request()->getRequestParam('id');
		$this->db->where('id', $customer_id, '=', 'and');
		$info = $this->db->getOne('customers');
		
		return $this->writeJson(200, $info ?: [], 'success');
	}

	/**
	 * 患者转让
	 * @Author   author
	 * @DateTime 2018-11-30
	 * @return   [type]     [description]
	 */
	public function transfer()
	{
		$params = $this->request()->getRequestParam('customer_id', 'admin');

		if (!$this->admin_auth(['action' => 'customer', 'content' => $params['customer_id']]))
			return $this->writeJson(10005, [], '不能操作别人的信息');

		$info = $this->db->where('id', $params['customer_id'])->getOne('customers', null, 'name,origin');
		$this->db->where('id', $params['customer_id'])->update('customers', ['adminstor' => $params['admin'], 'origin' => $info['origin'] . '->' . $params['admin']]);

		$admin = $this->db->where('name', $params['admin'])->getOne('adminstors');

		$server = ServerManager::getInstance()->getSwooleServer();
		$server->push($admin['fd'], json_encode(['action' => 'transfer_remind', 'data' => array(
			'admin_name' => $this->info['name'],
			'customer_name' => $info['name'],
			'customer_id' => $params['customer_id']
		)]));

		return $this->writeJson(200, [], '转换成功');
	}

	/**
	 * 患者信息修改
	 * @Author   author
	 * @DateTime 2018-12-06
	 * @return   json     [description]
	 */
	public function update()
	{
		$data = $this->request()->getRequestParam('id','name', 'birthday', 'email', 'address', 'phone', 'qq', 'wechat', 'age', 'sex', 'type', 'ad_time', 'app_time', 'is_jiuzhen', 'channel', 'price', 'hospital', 'spread', 'intentionality', 'beizhu');
		$valitor = $this->valt($data);
		if (!is_bool($valitor)) 
			return $this->writeJson(10002, [], $valitor);

		if (!$this->admin_auth(['action' => 'customer', 'content' => $data['id']]))
			return $this->writeJson(10005, [], '不能操作别人的信息');

		$this->db->where('id', $data['id'], '=', 'and');

		$result = $this->db->update('customers', $data);
		return $this->writeJson(200, [], '修改成功');
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
		// 'name', 'birthday', 'email', 'address', 'phone', 'qq', 'wechat', 'age', 'sex', 'type', 'ad_time', 'app_time', 'is_jiuzhen', 'channel'渠道, 'price', 'hospital', 'spread', 'intentionality'
		$valitor = new Validate();
		$valitor->addColumn('name', '名字不为空')->required('名字不为空');

		$valitor->addColumn('email', '邮箱不为空')->required('邮箱不能为空');

		$valitor->addColumn('address', '地址不为空')->required('地址不能为空');

		$valitor->addColumn('birthday', '生日不为空')->required('生日不能为空');

		$valitor->addColumn('phone', '电话不为空')->required('电话不能为空');

		$valitor->addColumn('qq', 'QQ不为空')->required('QQ不能为空');

		$valitor->addColumn('wechat', '微信不为空')->required('微信不能为空');

		$valitor->addColumn('age', '年龄不为空')->required('年龄不能为空');

		$valitor->addColumn('sex', '性别不为空')->required('性别不能为空');

		$valitor->addColumn('type', '病种不为空')->required('病种不能为空');

		$valitor->addColumn('app_time', '预约时间不为空')->required('预约时间不能为空');

		$valitor->addColumn('is_jiuzhen', '是否就诊不为空')->required('是否就诊不能为空');

		$valitor->addColumn('channel', '渠道不为空')->required('渠道不能为空');

		$valitor->addColumn('price', '消费金额不为空')->required('消费金额不能为空');

		$valitor->addColumn('hospital', '医院不为空')->required('医院不能为空');

		$valitor->addColumn('intentionality', '意向度不为空')->required('意向度不能为空');

		$valitor->addColumn('ad_time', '咨询时间不为空')->required('咨询时间不为空');

		$valitor->addColumn('spread', '推广人员不能为空')->required('推广人员不能为空');

		$valitor->addColumn('beizhu', '备注不能为空')->required('备注不能为空');

		$result = $valitor->validate($data);

		return $result ?: $valitor->getError()->getErrorRuleMsg();
	}

}
//  _________
// |  _______|         
// |  |				   
// |  |______         
// |   _____|
// |  |
// |  |______
// |_________|