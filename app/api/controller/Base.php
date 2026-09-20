<?php
declare (strict_types=1);

namespace app\api\controller;

use app\common\model\Configure;
use think\App;
use think\exception\HttpResponseException;
use think\facade\Cache;
use think\facade\Lang;
use think\Response;
use think\facade\Request;

/**
 * 控制器基础类
 */
abstract class Base
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 控制器中间件x
     * @var array
     */
    protected $middleware = ['app\middleware\CheckApi','app\middleware\AllowCros'];
    //   protected $middleware = [];

    protected $config = [];

    /**
     * 构造方法
     * @access public
     * @param App $app 应用对象
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;
        // 控制器初始化
        $this->initialize();
        // $this->request->setHost('admin4.guo-tai-9009.com');
		// $this->request->setHost('127.0.0.2');
    }

    // 初始化
    protected function initialize()
    {
        $system_basic = Cache::get('systemBasic');
        if (empty($system_basic)) {
            $info = Configure::find(1);
            $system_basic = json_decode($info['content'], true);
            Cache::set('systemBasic', $system_basic, 86400);
        }
        $system_home = Cache::get('systemHome');
        if (empty($system_home)) {
            $info = Configure::find(2);
            $system_home = json_decode($info['content'], true);
            Cache::set('systemHome', $system_home, 86400);
        }
        $system_link = Cache::get('systemLink');
        if (empty($system_link)) {
            $info = Configure::find(3);
            $system_link = json_decode($info['content'], true);
            Cache::set('systemLink', $system_link, 86400);
        }
        $system_bank = Cache::get('systemBank');
        if (empty($system_bank)) {
            $info = Configure::find(4);
            $system_bank = json_decode($info['content'], true);
            Cache::set('systemBank', $system_bank, 86400);
        }
        $this->config = [
            'system_basic' => $system_basic,
            'system_home' => $system_home,
            'system_link' => $system_link,
            'system_bank' => $system_bank,
        ];
    }

    /**
     *返回客户端json数据
     * @param bool $success 状态
     * @param string $msg 说明
     * @param array $data 扩展数据
     * @param array $extend 额外参数
     * @return Response
     */
    protected function result(bool $success = true, string $msg = '操作成功', array $data = [], array $extend = []): Response
    {
        $result = [
            'success' => $success,
            'msg' => Lang::get($msg),
            'time' => time(),
            'data' => $data,
        ];
        if (!$success) {
            $result['msg'] .= $data['var'] ?? '';
        }
        ob_clean();
        ob_end_clean();
        $result = array_merge($result, $extend);
        $response = Response::create($result, 'json');
        throw new HttpResponseException($response);
    }

}
