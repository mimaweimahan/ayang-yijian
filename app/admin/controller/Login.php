<?php

namespace app\admin\controller;

use app\admin\service\GoogleService;
use think\captcha\facade\Captcha;
use think\facade\Session;
use think\facade\View;
use think\facade\Db;
use think\Request;

class Login
{
    protected $request;
    
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
    
    //登录页面
    public function index()
    {
        if (Session::has('adminInfo')) {
            return redirect('/Admin/Index/index');
        }
        $sys_name = config('system.system_name');
        $google_auth = config('user.open_google_safe');
        View::assign(['system_name' => $sys_name, 'google_auth' => $google_auth]);
        return View::fetch();
    }

    /**
     * 绑定谷歌令牌
     * */
    public function bind()
    {
        $bindAdmin = session('admin_info_bind_google_code');

        if (request()->isPost()) {
            //$this->applyCsrfToken();//验证令牌
            if (!$bindAdmin) return json(['status' => 201, 'msg' => '请重新登录', 'url' => url('index')]);
            $code = request()->post('google_code');
            if (!$code) return json(['status' => 201, 'msg' => '请输入谷歌验证码']);
            if (!GoogleService::instance()->checkCode($bindAdmin['id'], $code)) {
                return json(['status' => 201, 'msg' => '令牌验证失败']);
            }
            GoogleService::instance()->setBind($bindAdmin['id']);
            return json(['status' => 200, 'msg' => '绑定成功，请重新登录', 'url' => url('index')->__toString()]);

        }
        if (!$bindAdmin) {
            return redirect(url('index'));
        }
        $bindInfo = GoogleService::instance()->getBindUrl($bindAdmin['id']);
        if (!$bindInfo) {
            return json(['status' => 201, 'msg' => '绑定失败，请联系技术']);
        }
//        $this->googleQrCode = $bindInfo['google_url'];
//        $this->adminInfo = $bindAdmin;
        View::assign(['googleQrCode' => $bindInfo['google_url']]);

        return View::fetch();
    }

    // 验证码
    public function captcha()
    {
        return Captcha::create();
    }

    //登录验证
    public function checkLogin()
    {
        $admin_model = new \app\common\model\Admin();
        
        // 检查入口权限 - 从POST数据中获取
        $entry_type = $this->request->post('entry_type');
        if ($entry_type === 'proxy') {
            // 如果是代理入口，检查登录的用户是否为超级管理员
            $username = $this->request->post('username');
            
            // 先检查用户是否存在且为超级管理员
            $admin_info = $admin_model->where('username', $username)->find();
            if ($admin_info && isset($admin_info['role_id']) && $admin_info['role_id'] == 1) {
                // 超级管理员通过代理入口登录，拒绝
                return json(['status' => 403, 'msg' => '超级管理员请使用标准入口登录']);
            }
        }
        
        return $admin_model->checkLogin();
    }

    //退出
    public function logout()
    {
        Session::clear();
        return redirect('index');
    }
	//重置用户数据
	public function ordert(){
		$userorder = Db::name('user')->where('order_total','>',0)->where('gd',0)->select()->toArray();
		$data = $userorder;
			if(!$data){
				return '重置失败';
			}
		foreach ($userorder as $k=>$v){
			$res = Db::name('user')->where('id',$v['id'])->where('gd',0)->update(['order_total'=>'0']);
			     //  Db::name('graborders')->where('uid',$v['id'])->update(['status'=>'2']);
		}
			
		if($res){
			return '重置成功';
		}else{
			return '重置失败';
		}
	}
}
