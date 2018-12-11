<?php
namespace App\HttpController;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;
use App\Utility\Pool\MysqlObiect;
use EasySwoole\Validate\Validate;
use EasySwoole\Http\Request;
use App\Model\Model;

class Index extends Auth
{	
	protected $route_type = array(
		'index' => 'get',
		'daoru' => 'get'
	);
	protected function onRequest(?string $action): ?bool
	{
		if (!$this->is_method_allow() || !parent::onRequest($action)) 
			return false;
	
		return true;
	}

	public function index()
	{
		$start_date = date('Y-m-d') . ' 00:00:00';
		$end_date = date('Y-m-d') . ' 23:59:59';

		$data['app_total'] = $this->db->count('customers');
		$data['self_app_total'] = $this->db->where('adminstor', $this->info['name'])->count('customers');

		$data['today_total'] = $this->db->where('create_time', $start_date, '>', 'and')->where('create_time', $end_date, '<', 'and')->count('customers');
		$data['self_today_total'] = $this->db->where('create_time', $start_date, '>', 'and')->where('create_time', $end_date, '<', 'and')->where('adminstor', $this->info['name'])->count('customers');

		$data['today_wait_backvist'] = $this->db->where('b_time', $start_date, '>', 'and')->where('b_time', $end_date, '<', 'and')->where('adminstor', $this->info['name'])->where('status', 0)->count('backvisit', null, 'customer_id');

		return $this->writeJson(200, $data, 'success');
	}
}