<?php
declare (strict_types=1);

namespace app\admin\controller;

use think\App;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\facade\Config;
use think\facade\View;
use think\Response;
use think\Validate;

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
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = ['app\middleware\CheckAdmin'];

    /**
     * 分页数量
     * @var int
     */
    protected $pageSize = 0;

    protected $agent_id = 0;

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
    }

    // 初始化
    protected function initialize()
    {
        $this->pageSize = Config::get('system.page_size', 10);
        $sys_name = Config::get('system.system_name', '');
        $this->agent_id = session('adminInfo.user_id');
        View::assign('system_name', $sys_name);
        
        // 检查入口类型和权限
        $this->checkEntryPermission();
    }

    /**
     * 检查入口权限
     */
    protected function checkEntryPermission()
    {
        $entry_type = $this->request->get('entry_type');
        $proxy_entry = $this->request->get('proxy_entry');
        
        // 调试信息 - 可以临时开启查看
        // error_log("Entry Type: " . $entry_type . ", Proxy Entry: " . $proxy_entry);
        
        // 如果是代理入口，进行权限验证
        if ($entry_type === 'proxy') {
            // 检查是否已登录
            if (!session('adminInfo')) {
                return; // 未登录时允许访问登录页面
            }
            
            // 已登录时检查用户权限
            $admin_info = session('adminInfo');
            $user_id = $admin_info['user_id'] ?? 0;
            
            // 调试信息 - 可以临时开启查看
            // error_log("Admin Info: " . json_encode($admin_info));
            
            // 这里需要根据您的业务逻辑来判断是否为代理用户
            // 假设代理用户的role_id为2，超级管理员为1
            if (isset($admin_info['role_id']) && $admin_info['role_id'] == 1) {
                // 超级管理员通过代理入口访问，显示警告或重定向
                $this->result(403, '超级管理员请使用标准入口访问', [], []);
            }
            
            // 记录代理访问日志
            $this->logProxyAccess($proxy_entry, $user_id);
        }
    }
    
    /**
     * 记录代理访问日志
     */
    protected function logProxyAccess($entry, $user_id)
    {
        // 可以记录代理访问日志，用于审计
        $log_data = [
            'entry' => $entry,
            'user_id' => $user_id,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->header('user-agent'),
            'time' => date('Y-m-d H:i:s')
        ];
        
        // 这里可以写入日志文件或数据库
        // file_put_contents('proxy_access.log', json_encode($log_data) . "\n", FILE_APPEND);
    }

    /**
     * 验证数据
     * @access protected
     * @param array $data 数据
     * @param string|array $validate 验证器名或者验证规则数组
     * @param array $message 提示信息
     * @param bool $batch 是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    /**
     *返回客户端json数据
     * @param int $code 状态码
     * @param string $msg 说明
     * @param array $data 扩展数据
     * @param array $header header信息
     * @return Response
     */
    protected function result(int $code = 200, string $msg = '操作成功', array $data = [], array $header = []): Response
    {
        $result = [
            'status' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ];
        ob_clean();
        ob_end_clean();
        $response = Response::create($result, 'json')->header($header);

        throw new HttpResponseException($response);
    }

}
