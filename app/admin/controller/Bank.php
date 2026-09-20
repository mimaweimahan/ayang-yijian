<?php

namespace app\admin\controller;

use app\common\model\Admin;
use app\common\model\BankLog;
use app\common\model\User;
use think\facade\View;

class Bank extends Base
{
    /**
     * 钱包修改记录
     * @return string
     * @throws \think\db\exception\DbException
     */
    public function logList()
    {
        $nickname = $this->request->get('nickname', '', 'trim');
        $start_time = $this->request->get('start_time', '', 'trim');
        $ip = $this->request->get('ip', '', 'trim');
        $where = [];
        if ($this->agent_id)
            $where[] = ['u.agent_id', '=', $this->agent_id];
        if ($nickname)
            $where[] = ['u.username', 'like', "%$nickname%"];
        if ($ip)
            $where[] = ['log.action_ip', '=', $ip];
        if ($start_time)
            $where[] = ['log.action_time', '>', strtotime($start_time)];
        $list = BankLog::alias('log')
            ->order('id', 'desc')
            ->where($where)
            ->field('log.*, u.username')
            ->leftJoin('mod_user u', 'u.id=log.uid')
            ->paginate($this->pageSize)->each(function ($item) {
                $action_name = '';
                if ($item['action_type'] === 0) {
                    $action_name = User::where('id', $item['action_id'])->value('username');
                } else {
                    $action_name = Admin::where('id', $item['action_id'])->value('username');
                }
                if($item['before']){
                    $item['before'] = json_decode($item['before'],true);
                }
                $item['after'] = json_decode($item['after'],true);
                $item['action_name'] = $action_name;
                $item['action_type_text'] = $item['action_type'] === 0 ? '前台用户' : '后台修改';
                if ($item['action_time'])
                    $item['action_time'] = date('Y-m-d H:i', $item['action_time']);
                else
                    $item['action_time'] = '';
            });

        View::assign(['list' => $list, 'nickname' => $nickname,
            'ip' => $ip,
            'start_time' => $start_time]);
        return View::fetch();
    }
}