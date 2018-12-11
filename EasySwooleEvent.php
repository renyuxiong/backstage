<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/28
 * Time: 下午6:33
 */

namespace EasySwoole\EasySwoole;


use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\Socket\Dispatcher;
use App\Socket\WebSocketParser;
use App\Socket\Websocket;

class EasySwooleEvent implements Event
{

    public static function initialize()
    {
        // TODO: Implement initialize() method.
        date_default_timezone_set('Asia/Shanghai');
        PoolManager::getInstance()->register(MysqlPool::class, Config::getInstance()->getConf('MYSQL.POOL_MAX_NUM'));
    }

    public static function mainServerCreate(EventRegister $register)
    {
        // TODO: Implement mainServerCreate() method.
        // 创建一个 Dispatcher 配置
        $conf = new \EasySwoole\Socket\Config();
        // 设置 Dispatcher 为 WebSocket 模式
        $conf->setType($conf::WEB_SOCKET);
        // 设置解析器对象
        $conf->setParser(new WebSocketParser());

        // 创建 Dispatcher 对象 并注入 config 对象
        $dispatch = new Dispatcher($conf);

        // 给server 注册相关事件 在 WebSocket 模式下  message 事件必须注册 并且交给 Dispatcher 对象处理
        $register->set(EventRegister::onMessage, function(\swoole_websocket_server  $server, \swoole_websocket_frame $frame) use ($dispatch){
            $dispatch->dispatch($server, $frame->data, $frame);
        });

        $register->set(EventRegister::onOpen, function (\swoole_websocket_server  $server, $fd) use ($dispatch) {
            $dispatch->dispatch($server, json_encode(['action' => 'op', 'content' => $fd->fd]));
        });

        $register->set(EventRegister::onClose, function (\swoole_websocket_server  $server, $fd) use ($dispatch) {
            $dispatch->dispatch($server, json_encode(['action' => 'cl', 'content' => $fd]));
        });
    }

    public static function onRequest(Request $request, Response $response): bool
    {
        $response->withHeader('Access-Control-Allow-Origin', '*');
        $response->withAddedHeader('Access-Control-Allow-Headers', 'app-auth');
        $response->withAddedHeader('Access-Control-Allow-Methods', 'POST,GET,PATCH,DELETE');
        // TODO: Implement onRequest() method.
        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
        // TODO: Implement afterAction() method.
    }

    public static function onReceive(\swoole_server $server, int $fd, int $reactor_id, string $data):void
    {

    }

}