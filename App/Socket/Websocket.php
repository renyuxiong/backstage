<?php
namespace App\Socket;

use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\EasySwoole\Swoole\Time\Timer;
use EasySwoole\Mysqli\Mysqli;

class Websocket extends Controller
{
    /**
     * 客户端连接成功
     * @Author   author
     * @DateTime 2018-12-06
     * @return   [type]     [description]
     */
    function login()
    {
        $info = $this->auth_token($this->caller()->getArgs()[0]);
        if (!$info) {
            $this->response()->setMessage(json_encode(['action' => 'logout']));
        }

        $this->create_db()->where('id', $info['id'])->update('adminstors', ['fd' => $this->caller()->getClient()->getFd()]);
        
    }

    function delay()
    {
        $this->response()->setMessage('this is delay action');
        $client = $this->caller()->getClient();

        // 异步推送, 这里直接 use fd也是可以的
        // TaskManager::async 回调参数中的代码是在 task 进程中执行的 默认不含连接池 需要注意可能出现 getPool null的情况
        TaskManager::async(function () use ($client){
            $server = ServerManager::getInstance()->getSwooleServer();
            $i = 0;
            while ($i < 5) {
                sleep(1);
                $server->push($client->getFd(),'push in http at '.time());
                $i++;
            }
        });
    }

    /**
     * 客户端连接成功回调
     * @Author   author
     * @DateTime 2018-12-06
     * @return   [type]     [description]
     */
    public function op()
    {
        $fd = $this->caller()->getArgs()[0];
        $server = ServerManager::getInstance()->getSwooleServer();
        $server->push($fd, json_encode(['action' => 'connect_success']));
    }

    /**
     * 客户端关闭回调
     * @Author   author
     * @DateTime 2018-12-06
     * @return   [type]     [description]
     */
    public function cl()
    {
        $fd = $this->caller()->getArgs()[0];
        $this->create_db()->where('fd', $fd)->update('adminstors', ['fd' => 0]);
    }

    /**
     * 创建数据库连接对象
     * @Author   author
     * @DateTime 2018-12-06
     * @return   [type]     [description]
     */
    public function create_db()
    {
        $conf = new \EasySwoole\Mysqli\Config(\EasySwoole\EasySwoole\Config::getInstance()->getConf('MYSQL'));
        $db = new Mysqli($conf);
        return $db;
    }

    /**
     * token验证
     * @Author   author
     * @DateTime 2018-12-06
     * @param    [type]     $token [description]
     * @return   [type]            [description]
     */
    public function auth_token($token)
    {
        $token = str_replace('ADGN ', '', $token);
        $info = json_decode(base64_decode($token), true);
        if ($info === null || count($info) === 0) {
            return false;
        }

        $data = $this->create_db()->where('id', $info['id'])->getOne('adminstors');
        if (!hash_equals($info['access_token'] , $data['access_token'])) {
            return false;
        }

        return $info;
    }
}