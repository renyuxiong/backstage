<?php
namespace App\HttpController;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Http\Request;
use App\Model\Model;

class Auth extends Controller
{
	// 不需要验证权限的路由白名单
	private $fill_routes = array(
		'/admin/adminInfo',   // 管理员查看自己的信息
		'/',				  // 首页
		'/index/daoru'
	);

	protected function onRequest(?string $action): ?bool
	{
		$this->db = (new Model())->getDB();
		if (!$this->info = $this->auth_token($this->db)) {
			return false;
		}

		if (!$this->role_auth($this->getRequestPath())) {
			$this->writeJson(10004, [], '你没有权限进行操作');
			return false;
		}

		return true;
	}

	public function index()
	{
		//
	}

	protected function role_auth(string $control)
	{
        $actions = $this->db->where('admin_id', $this->info['id'])->get('roles', null, 'action_id');

        if (in_array($control, $this->fill_routes)) 
        	return true;

        $routes = array();
        foreach ($actions as $val) {
            $result = $this->db->where('id', $val['action_id'])->get('actions', null, 'route');
            foreach ($result as $v) {
                $routes[] = $v['route'];
            }
        }

        if (in_array($control, $routes)) {
            return true;
        } else {
            return false;
        }
	}

	protected function admin_auth(array $array): bool
	{
		switch ($array['action']) {
			case 'backvisit':
				$backvisit = $this->db->where('id', $array['content'])->getOne('backvisit');
				$customer = $this->db->where('id', $backvisit['customer_id'])->getOne('customers');
				if ($customer['adminstor'] != $this->info['name']) {
					return false;
				}
				break;
			case 'customer':
				$customer = $this->db->where('id', $array['content'])->getOne('customers');
				if ($customer['adminstor'] != $this->info['name']) {
					return false;
				}
				break;
			default:
				# code...
				break;
		}

		return true;
	}
}