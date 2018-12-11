<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/24
 * Time: 下午11:16
 */

namespace EasySwoole\Http\AbstractInterface;

use EasySwoole\Http\Message\Status;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Http\Session\Session;
use EasySwoole\Validate\Validate;

abstract class Controller
{
    private $request;
    private $response;
    private $actionName;
    private $session;

    private $allowMethods = [];
    private $defaultProperties = [];

    function __construct()
    {
        //支持在子类控制器中以private，protected来修饰某个方法不可见
        $list = [];
        $ref = new \ReflectionClass(static::class);
        $public = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($public as $item){
            array_push($list,$item->getName());
        }
        $this->allowMethods = array_diff($list,
            [
                '__hook','__destruct',
                '__clone','__construct','__call',
                '__callStatic','__get','__set',
                '__isset','__unset','__sleep',
                '__wakeup','__toString','__invoke',
                '__set_state','__clone','__debugInfo'
            ]
        );

        //获取，生成属性默认值
        $ref = new \ReflectionClass(static::class);
        $properties = $ref->getProperties();
        foreach ($properties as $property){
            //不重置静态变量
            if(($property->isPublic() || $property->isProtected()) && !$property->isStatic()){
                $name = $property->getName();
                $this->defaultProperties[$name] = $this->$name;
            }
        }
    }

    abstract function index();

    protected function gc()
    {
        // TODO: Implement gc() method.
        if($this->session instanceof Session){
            $this->session->writeClose();
            $this->session = null;
        }
        //恢复默认值
        foreach ($this->defaultProperties as $property => $value){
            $this->$property = $value;
        }
    }

    protected function actionNotFound(?string $action):void
    {
        $this->response()->withStatus(Status::CODE_NOT_FOUND);
    }

    protected function afterAction(?string $actionName):void
    {

    }

    protected function onException(\Throwable $throwable):void
    {
        throw $throwable ;
    }

    protected function onRequest(?string $action):?bool
    {
        return true;
    }

    protected function getActionName():?string
    {
        return $this->actionName;
    }

    protected function getMethod():?string
    {
        return $this->method;
    }

    protected function getRequestPath()
    {
        return $this->uri;
    }

    public function __hook(?string $actionName,Request $request,Response $response):void
    {
        $this->request = $request;
        $this->response = $response;
        $this->actionName = $actionName;
        $this->method = $request->method;
        $this->uri = $request->uri;
        try{
            if($this->onRequest($actionName) !== false){
                if(in_array($actionName,$this->allowMethods)){
                    $this->$actionName();
                }else{
                    $this->actionNotFound($actionName);
                }
            }
        }catch (\Throwable $throwable){
            //若没有重构onException，直接抛出给上层
            $this->onException($throwable);
        }finally{
            $this->afterAction($actionName);
        }
        try{
            $this->gc();
        }catch (\Throwable $throwable){
            $this->onException($throwable);
        }

    }

    protected function request():Request
    {
        return $this->request;
    }

    protected function response():Response
    {
        return $this->response;
    }

    protected function writeJson($statusCode = 200,$result = null,$msg = null,$count = 0){
        if(!$this->response()->isEndResponse()){
            $data = Array(
                "status_code"=>$statusCode,
                "data"=>$result,
                "message"=>$msg,
                "count" => $count
            );
            $this->response()->write(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            $this->response()->withHeader('Content-type','application/json;charset=utf-8');
            $this->response()->withStatus($statusCode);
            return true;
        }else{
            return false;
        }
    }

    protected function json():?array
    {
        return json_decode($this->request()->getBody()->__toString(),true);
    }

    protected function xml($options = LIBXML_NOERROR,string $className = 'SimpleXMLElement')
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        return simplexml_load_string($this->request()->getBody()->__toString(), $className,$options);
    }

    protected function validate(Validate $validate)
    {
        return $validate->validate($this->request()->getRequestParam());
    }

    protected function session(\SessionHandlerInterface $sessionHandler = null):Session
    {
        if($this->session == null){
            $this->session = new Session($this->request,$this->response,$sessionHandler);
        }
        return $this->session;
    }

    /*
     * 对象回收时，注意释放Session
     */
    function __destruct()
    {
        // TODO: Implement __destruct() method.
        if($this->session instanceof Session){
            $this->session->writeClose();
            $this->session = null;
        }
    }

    protected function create_token(array $info): string
    {
        unset($info['password']);
        return base64_encode(json_encode($info));
    }

    protected function auth_token($db)
    {
        $header = $this->request()->getHeaders()['app-auth'][0] ?? false;
        if (!$header){
            $this->writeJson(30002, [], 'token参数无效呢');
            return false;
        }

        $token = str_replace('ADGN ', '', $header);
        $info = json_decode(base64_decode($token), true);
        if ($info === null || count($info) === 0) {
            $this->writeJson(30002, [], 'token参数无效');
            return false;
        }

        $data = $db->where('id', $info['id'])->getOne('adminstors');
        if (!hash_equals($info['access_token'] , $data['access_token'])) {
            $this->writeJson(30001, [], 'token过期，请重新登陆');
            return false;
        }

        unset($info['access_token']);
        return $info;
    }

    protected function is_method_allow(): bool
    {
        $action = $this->getActionName();
        $method = $this->getMethod();
        if (!array_key_exists($action, $this->route_type)) {
            $this->writeJson(404, [], 'not found');
            return false;
        }

        if ($this->route_type[$action] !== strtolower($method)) {
            $this->writeJson(20001, [], '请求方式不允许');
            return false;
        }

        return true;
    }
}