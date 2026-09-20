<?php


namespace app\common\model;

use app\admin\service\GoogleService;
use think\facade\Request;
use think\Model;

class Admin extends Model
{

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    public function getLoginTimeAttr($val)
    {
        if ($val)
            return date('Y-m-d H:i:s');
        else
            return '暂未登录';
    }

    /**
     * 登录数据验证
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function checkLogin()
    {
        $username = Request::post('username', '', 'trim');
        $password = request()->post('password', '', 'trim');
        $code = Request::post('code', '', 'trim');
        if (empty($username) || empty($password)) {
            return json(['status' => 101, 'msg' => '账号或者密码为空']);
        }
        //不打开谷歌验证码就打开图片验证码
        if (config('user.open_google_safe') == false) {
            if (!captcha_check($code)) {
                return json(['status' => 102, 'msg' => '验证码错误']);
            }
        }

        $info = $this->where(['username' => $username])->find();
        if (empty($info)) {
            return json(['status' => 103, 'msg' => '账号或者密码错误', 'type' => 1]);
        }
        if ($info['password'] != md5($password)) {
            return json(['status' => 104, 'msg' => '账号或者密码错误', 'type' => 2]);
        }
        if ($info['status'] != 1) {
            return json(['status' => 105, 'msg' => '该账号已被禁用']);
        }
        // if (!Request::checkToken('__token__')) {
        //     return json(['status' => 100, 'msg' => '表单验证有误']);
        // }
        if (config('user.open_google_safe') == true) {
            //判断是否绑定谷歌令牌
            if (GoogleService::instance()->isBind($info['id'])) {
                $googleCode = input('google_code');
                if (empty($googleCode)) return json(['status' => 102, 'msg' => '请输入谷歌验证码']);
                $gcResult = GoogleService::instance()->checkCode($info['id'], $googleCode);
                if (!$gcResult) return json(['status' => 102, 'msg' => '谷歌验证码错误']);
            } else {
                session('admin_info_bind_google_code', $info);
                return json(['status' => 200, 'msg' => '账号验证成功，请先绑定谷歌令牌，正在跳转...', 'url' => url('Login/bind')->__toString()]);
            }
        }
        $info['rules'] = Role::where(['id' => $info['role_id'], 'status' => 1])->value('rules');
        $data['login_time'] = time();
        $data['login_ip'] = Request::ip();
        $this->where(['id' => $info['id']])->save($data);
        session('adminInfo', $info);
        return json(['status' => 200, 'msg' => '登录成功', 'url' => url('Index/index')->__toString()]);
    }

}
