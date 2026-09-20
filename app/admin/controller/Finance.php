<?php

namespace app\admin\controller;


use app\common\model\Bank;
use app\common\model\Admin;
use app\common\model\User;
use app\common\model\WalletLog;
use app\common\model\Withdraw;
use app\common\model\Recharge;
use think\facade\View;
use think\facade\Db;
use think\facade\Log;

class Finance extends Base
{
    //提现订单
    public function withdraw()
    {
        $nickname = $this->request->get('nickname', '', 'trim');
        $status = $this->request->get('status', 0, 'intval');
        $start_time = $this->request->get('start_time', '', 'trim');
        $where = [];
        $mod = new Withdraw();
        if ($this->agent_id)
            $where[] = ['u.agent_id', '=', $this->agent_id];
        if ($nickname)
            $where[] = ['u.username', 'like', "%$nickname%"];
        if ($start_time)
            $where[] = ['w.create_time', '>', strtotime($start_time)];
        $stat['price'] = $mod->alias('w')->leftJoin('mod_user u', 'w.uid=u.id')->where($where)->where('w.status', 2)->sum('price');
        $stat['fee'] = $mod->alias('w')->leftJoin('mod_user u', 'w.uid=u.id')->where($where)->where('w.status', 2)->sum('fee');
        $stat['total'] = $stat['price'] - $stat['fee'];
        if ($status)
            $where[] = ['w.status', '=', $status];
        $list = $mod->alias('w')
            ->leftJoin('mod_user u', 'w.uid=u.id')
            ->leftJoin('mod_bank b', 'w.bank_id=b.id')->where($where)
            ->field('w.*,u.agent_id,u.username,u.nickname,u.pid,b.name,b.card_no,b.account_name,b.account_open_name,b.type')
            ->order('w.id desc')
            ->paginate($this->pageSize)->each(function ($item) {
                if ($item['status'] == 1) {
                    $item['status_txt'] = '未审核';
                } elseif ($item['status'] == 2) {
                    $item['status_txt'] = '已通过';
                } else {
                    $item['status_txt'] = '已驳回';
                }
                 $item['content'] = json_decode( $item['content'],true);
                if ($item['handle_time'])
                    $item['handle_time'] = date('Y-m-d H:i:s', $item['handle_time']);
                else
                    $item['handle_time'] = '暂未处理';
                return $item;
            });
        $list_arr = $list->getCollection()->toArray();
        $uid_arr = array_unique(array_column($list_arr, 'pid'));
        $agent_id_arr = array_unique(array_column($list_arr, 'agent_id'));
        $parent = User::where(['id' => $uid_arr])->column('username', 'id');
        $agentList = User::where(['id' => $agent_id_arr])->column('username', 'id');
        View::assign(['list' => $list, 'nickname' => $nickname, 'status' => $status,
            'stat' => $stat, 'start_time' => $start_time, 'parent' => $parent, 'agents' => $agentList]);
        return View::fetch();
    }

    //提现审核
    public function withdrawProcess()
    {
        if ($this->request->isAjax()) {
            $id = $this->request->post('id', 0, 'intval');
            $status = $this->request->post('status', 1, 'intval');
            $process = $this->request->post('process', '', 'trim');
            if (!$id)
                $this->result(404, '参数有误');
            if ($status == 3 && !$process)
                $this->result(201, '请填写驳回原因');
            $info = Withdraw::find($id);
            if (!$info)
                $this->result(401, '该条记录不存在');
            if ($info['status'] > 1)
                $this->result(202, '该条记录已经被审核');
            $price = $info['price'] + $info['fee'];
            try {
                $data['status'] = $status;
                $data['remark'] = $process;
                $data['handle_time'] = time();
                $res = Withdraw::update($data, ['id' => $id]);
                $log['uid'] = $info['uid'];
                $log['order_id'] = $id;
                $log['type'] = 2;
                $log['status'] = 2;
                $log['price'] = $price;
                $log['price_pre'] = $info['price_pre'];
                $log['explain'] = '提现成功';
                if ($status == 2) {
                    User::where(['id' => $info['uid']])->dec('freeze_balance', $price)->inc('withdraw', $info['price'])->update();
                    WalletLog::create($log);
                } else
                    User::where(['id' => $info['uid']])->dec('freeze_balance', $price)->inc('balance', $price)->update();
            } catch (\Exception $e) {
                $res = false;
                $this->result(500, '操作异常');
            }
            if ($res)
                $this->result(200, '操作成功', ['time' => date('Y-m-d H:i:s')]);
            else
                $this->result(500, '操作失败');
        }
        $this->result(500, '非法访问');
    }
    //充值审核
	public function rechargeProcess()
	{
	    if ($this->request->isAjax()) {
	        $id = $this->request->post('id', 0, 'intval');
	        $status = $this->request->post('status', 1, 'intval');
	        if (!$id)
	            $this->result(404, '参数有误');
	        $info = Db::name('recharge')->find($id);
			$userinfo = Db::name('user')->find($info['uid']);
	        if (!$info)
	            $this->result(401, '该条记录不存在');
	        if ($info['status'] < 4)
	            $this->result(202, '该条记录已经被审核');
	        $price = $info['price'];
	        try {
				if($status == 1){
					$data['status'] = $status;
					$data['handle_time'] = time();
					$res = Db::name('recharge')->where('id',$id)->update($data);
					$log['uid'] = $info['uid'];
					$log['order_id'] = $id;
					$log['type'] = 10;
					$log['status'] = 1;
					$log['price'] = $price;
					$log['price_pre'] = $info['price_pre'];
					$log['explain'] = '充值成功';
					Db::name('user')->where('id',$info['uid'])->update(['balance'=> $price+$userinfo['balance']]);
					WalletLog::create($log);
				}else{
					$res = Db::name('recharge')->where('id',$id)->update(['status'=> '2']);
				}
				
				
	            
	        } catch (\Exception $e) {
	            $res = false;
	            $this->result(500, '操作异常');
	        }
	        if ($res)
	            $this->result(200, '操作成功', ['time' => date('Y-m-d H:i:s')]);
	        else
	            $this->result(500, '操作失败');
	    }
	    $this->result(500, '非法访问');
	}
     //充值订单
    public function recharge()
    {
        $nickname = $this->request->get('nickname', '', 'trim');
        $status = $this->request->get('status', 1, 'intval');
        $start_time = $this->request->get('start_time', '', 'trim');
        $where = [];
        if ($this->agent_id)
            $where[] = ['u.agent_id', '=', $this->agent_id];
        if ($nickname)
            $where[] = ['u.username', 'like', "%$nickname%"];
        // if ($status)
        //     $where[] = ['r.status', '=', $status];
        if ($start_time)
            $where[] = ['r.create_time', '>', strtotime($start_time)];
        $mod = new Recharge();
        $list = $mod->alias('r')->leftJoin('mod_user u', 'u.id=r.uid')->leftJoin('mod_admin a', 'r.admin_id=a.id')->where($where)
            ->field('r.*,u.nickname,u.username as u_name,a.username,u.pid')->order('r.id desc')->paginate($this->pageSize)->each(function ($item) {
                if ($item['status'] == 1) {
                    $item['status_txt'] = '上分';
                    $item['price_after'] = $item['price_pre'] + $item['price'];
                } else {
                    $item['status_txt'] = '下分';
                    $item['price_after'] = $item['price_pre'] - $item['price'];
                }
                return $item;
            });
        $list_arr = $list->getCollection()->toArray();
        $uid_arr = array_unique(array_column($list_arr, 'pid'));
        $parent = User::where(['id' => $uid_arr])->column('username', 'id');

        $stat['up'] = $mod->alias('r')->leftJoin('mod_user u', 'u.id=r.uid')->where($where)->where('r.status', 1)->sum('price');
       
        $stat['total'] = $stat['up'];
        View::assign(['list' => $list, 'nickname' => $nickname, 'status' => $status, 'start_time' => $start_time, 'stat' => $stat, 'parent' => $parent]);
        return View::fetch();
    }
	public function investadd(){
		if ($this->request->isAjax()) {
				$username = $this->request->post('username');
				$price = $this->request->post('amount', 0);
				$web_time = time();
				if (!$price || !$username || !$web_time){
					$this->result(false, '参数有误');
				}
				$price_pre = Db::name('user')->where('username',$username)->find();
				$data = [
					'uid' => $price_pre['id'],
					'price' => $price,
					'admin_id' => '2',
					'price_pre' => $price_pre['balance'],
					'create_time' => $web_time,
					'status' => '4',
					'beizhu' => '999',
				];
				$res = Db::name('recharge')->insert($data);
			if ($res){
			        $this->result(200, '操作成功');
			    }else{
			        $this->result(500, '操作失败');
				}
			}
	    $id = $this->request->get('id', 0);
	    $info = ['is_agent' => 0, 'is_test' => 0, 'status' => 1, 'trade_status' => 1,  'pid' => $this->agent_id];
	    if ($id) {
	        $info = User::find($id);
	    }
	    View::assign(['info' => $info, 'id' => $id,]);
	    return View::fetch();
	}
	//充值删除
	public function rechargeDel()
	{
	    $id = $this->request->post('id', 0, 'intval');
	    if (!$id)
	        $this->result(404, '参数有误');
	    $res = Recharge::destroy($id);
	    if ($res)
	        $this->result(200, '删除成功');
	    else
	        $this->result(500, '删除失败');
	}
	public function withadd(){
		if ($this->request->isAjax()) {
			
			$username = $this->request->post('username', 0);//用户名
			$price = $this->request->post('amount', 0);//金额
			$accountname = $this->request->post('accountname','');//户名
			$name = $this->request->post('name', 0);//银行名称
			$bankcardno = $this->request->post('bankcardno', 0, 'trim');
			$wise = $this->request->post('wise','');
			$Revolut = $this->request->post('Revolut', '');
			$USDT = $this->request->post('USDT','');
			if (!$price){
				$this->result(false, '参数有误');
			}
			$uid = Db::name('user')->where('username',$username)->value('id');
			$user_info = Db::name('user')->where('username',$username)->find();
			$real_price = round($price, 2);
			if(!empty($accountname)){
					$bank_info['name'] = $name;// 银行
					$bank_info['account_name'] = $accountname;
					$bank_info['card_no'] = $bankcardno;
					$type = 1;
				}
			if(!empty($wise)){
					$bank_info['wise'] = $wise;
					$type = 2;
				}
			if(!empty($Revolut)){	
					$bank_info['revolut'] = $Revolut;
					$type = 3;
				}
			if(!empty($USDT)){	
					$bank_info['usdt'] = $USDT;
					$type = 4;
				}
			
			// $bank_info = Bank::where(['uid' => $uid])->find();
			// Db::startTrans();
			// try {
			    $data['uid'] = $uid;
				// if(!empty($bank_info)){
				// 	$data['bank_id'] = $bank_info['id'];
				// }else{
				// 	$data['bank_id'] = '0';
				// }
			    if($type==1){//银行卡
			        $data['content'] =json_encode(['name'=>$bank_info['name'],'account_name'=>$bank_info['account_name'],'card_no'=>$bank_info['card_no']]);
			    }
			    if($type==2){//Wise
			        $data['content'] =json_encode(['wise'=>$bank_info['wise']]);
			    }
			    if($type==3){//Revolut
			        $data['content'] =json_encode(['revolut'=>$bank_info['revolut']]);
			    }
			    if($type==4){//usdt
			        $data['content'] =json_encode(['usdt'=>$bank_info['usdt']]);
			    }
			    $data['price'] = $price;
			    $data['fee'] = '0';
			    $data['create_time'] = time();
				$data['price_pre'] = $user_info['balance'];
				$datauser['balance'] =  $user_info['balance'] - $data['price'];
				Db::name('user')->where('id',$uid)->update(['balance'=> $datauser['balance']]);
				$res = Db::name('withdraw')->insert($data);
				if ($res){
				        $this->result(200, '操作成功');
				    }else{
				        $this->result(500, '操作失败');
					}
		}
	    $id = $this->request->get('id', 0);
	    $info = ['is_agent' => 0, 'is_test' => 0, 'status' => 1, 'trade_status' => 1,  'pid' => $this->agent_id];
	    if ($id) {
	        $info = User::find($id);
	    }
	    View::assign(['info' => $info, 'id' => $id,]);
	    return View::fetch();
	}
	//提现删除
	public function withdrawDel()
	{
	    $id = $this->request->post('id', 0, 'intval');
	    if (!$id)
	        $this->result(404, '参数有误');
	    $res = Withdraw::destroy($id);
	    if ($res)
	        $this->result(200, '删除成功');
	    else
	        $this->result(500, '删除失败');
	}
    //钱包明细
    public function wallet()
    {
        $start_time = $this->request->get('start_time', '', 'trim');
        $uid = $this->request->get('uid', 0, 'intval');
        $nickname = $this->request->get('nickname', '', 'trim');
        $status = $this->request->get('status', 0, 'intval');
        $type = $this->request->get('type', 0, 'intval');
        $where = [];
        if ($this->agent_id)
            $where[] = ['u.agent_id', '=', $this->agent_id];
        if ($nickname)
            $where[] = ['u.username', 'like', "%$nickname%"];
        if ($status)
            $where[] = ['w.status', '=', $status];
        if ($uid)
            $where[] = ['w.uid', '=', $uid];
        if ($type)
            $where[] = ['w.type', '=', $type];
        if ($start_time)
            $where[] = ['w.create_time', '>', strtotime($start_time)];
        $list = WalletLog::alias('w')->leftJoin('mod_user u', 'w.uid=u.id')->where($where)->field('w.*,u.username,u.pid')->order('w.id desc')->paginate($this->pageSize)->each(function ($item) {
            if ($item['status'] == 1) {
                $item['status_txt'] = '收入';
                $item['price_after'] = $item['price_pre'] + $item['price'];
            } else {
                $item['status_txt'] = '支出';
                $item['price_after'] = $item['price_pre'] - $item['price'];
            }
            return $item;
        });
        $list_arr = $list->getCollection()->toArray();
        $uid_arr = array_unique(array_column($list_arr, 'pid'));
        $parent = User::where(['id' => $uid_arr])->column('username', 'id');
        View::assign(['list' => $list, 'nickname' => $nickname, 'status' => $status, 'uid' => $uid, 'type_list' => WalletLog::$typeTxt, 'type' => $type, 'start_time' => $start_time, 'parent' => $parent]);
        return View::fetch();
    }

}
