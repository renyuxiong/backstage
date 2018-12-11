<?php
namespace App\HttpController;
use EasySwoole\Http\AbstractInterface\Controller;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;
use App\Utility\Pool\MysqlObiect;
use EasySwoole\Validate\Validate;
use EasySwoole\Http\Request;
use EasySwoole\Utility\Random;
use App\Model\Model;

class Login extends Controller
{
	protected $route_type = array(
		'index' => 'post',
		'logout' => 'post',
		'test' => 'get'
	);

	protected function onRequest(?string $action): ?bool
	{
		if (!$this->is_method_allow()) {
			return false;
		}	

		$this->db = (new Model())->getDB();
		return true;
	}

	/**
	 * 处理管理员登陆
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @return   [type]     [description]
	 */
	public function index()
	{
		$post = $this->request()->getParsedBody();
		$valitor = $this->valt( $post );

		if (!is_bool($valitor)) 
			return $this->writeJson(10002, [], $valitor);

		$this->db->where('name', $post['name'], '=', 'and')
				 ->where('password', md5($post['password']), '=', 'and');
		$info = $this->db->getOne('adminstors');
		if ($info === NULL) {
			return $this->writeJson(10001, [], '账号或密码错误');
		}

		$info['access_token'] = Random::character($length = 128, $alphabet = 'AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz0123456789');
		$this->db->where('id', $info['id'])->update('adminstors', $info);

		return $this->writeJson(200, $this->create_token($info), '登陆成功');

	}

	/**
	 * 管理员退出登陆
	 * @Author   author
	 * @DateTime 2018-11-20
	 * @return   [type]     [description]
	 */
	public function logout()
	{
		if (!$info = $this->auth_token($this->db)) {
			return false;
		}

		$this->db->where('id', $info['id'])->update('adminstors', ['access_token' => Random::character($length = 128, $alphabet = 'AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz0123456789')]);

		return $this->writeJson(200, [], '退出登陆成功');
	}

	/**
	 * 用户登陆输入验证
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
		$valitor->addColumn('password', '密码不能为空')
				->required('密码不能为空')
				->lengthMin(6, '密码最小长度不少于6位');

		$result = $valitor->validate($data);

		return $result ?: $valitor->getError()->getErrorRuleMsg();
	}
}