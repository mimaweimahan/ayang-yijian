<?php

namespace app\admin\controller;

use app\common\model\Admin;
use app\common\model\Bank;
use app\common\model\BankLog;
use app\common\model\Configure;
use app\common\model\Goods;
use app\common\model\Goods2;
use app\common\model\Level;
use app\common\model\Message;
use app\common\model\Order;
use app\common\model\Recharge;
use app\common\model\Schedule;
use app\common\model\User;
use app\common\model\UsersOrderRepeat;
use app\common\model\WalletLog;
use app\common\model\Graborders;
use app\common\model\Grabordersa;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;

class Member extends Base
{
    //会员等级列表
    public function levelList()
    {
        $list = Level::paginate($this->pageSize);
        View::assign(['list' => $list]);
        return View::fetch();
    }

    //会员等级添加
    public function levelAdd()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            $id = $data['id'] ?? 0;
            unset($data['id']);
            if ($id)
                $res = Level::update($data, ['id' => $id]);
            else
                $res = Level::create($data);
            if ($res)
                $this->result(200, '操作成功');
            else
                $this->result(500, '操作失败');
        }
        $id = $this->request->get('id', 0);
        $info = [];
        if ($id) {
            $info = Level::find($id);
        }
        View::assign(['info' => $info, 'id' => $id]);
        return View::fetch();
    }

    //会员列表
    public function userList()
    {
        $nickname = $this->request->get('nickname', '', 'trim');
        $mobile = $this->request->get('mobile', '', 'trim');
        $type = $this->request->get('type', 0, 'intval');
        $status = $this->request->get('status', 0, 'intval');
        $ag_id = $this->request->get('ag_id', 0, 'intval');
        $start_time = $this->request->get('start_time', '', 'trim');
        $login_ip = $this->request->get('login_ip', '', 'trim');
        $where = [];
        if ($this->agent_id)
            $where[] = ['u.agent_id', '=', $this->agent_id];
        else {
            if ($ag_id)
                $where[] = ['u.agent_id', '=', $ag_id];
        }
        if ($nickname)
            $where[] = ['u.username|nickname|invite_code', 'like', "%$nickname%"];
        if ($type == 1) {
            $where[] = ['u.is_test', '=', 1];
        } elseif ($type == 2) {
            $where[] = ['u.is_test', '=', 0];
        } elseif ($type == 3) {
            $where[] = ['u.is_agent', '=', 1];
        }
        if ($status)
            $where[] = ['u.status', '=', $status];
        if ($start_time)
            $where[] = ['u.create_time', '>', strtotime($start_time)];

        if ($login_ip) {
            $where[] = ['u.login_ip', '=', $login_ip];
        }
        
        // 优化1：使用LEFT JOIN替代子查询
        $list = User::alias('u')
            ->leftJoin('mod_level l', 'u.level_id=l.id')
            ->where($where)
            ->field('u.*,l.name as level_name,l.order_num')
            ->order('u.id desc')
            ->paginate($this->pageSize);
            
        $list_arr = $list->getCollection()->toArray();
        
        // 优化2：批量查询用户重复订单数量（保持原功能）
        $user_ids = array_column($list_arr, 'id');
        $user_repeat_map = [];
        if (!empty($user_ids)) {
            $user_repeats = Db::name('users_order_repeat')
                ->where('uid', 'in', $user_ids)
                ->where('day', date('Ymd'))
                ->field('uid, COUNT(*) as count')
                ->group('uid')
                ->select()
                ->toArray();
            $user_repeat_map = array_column($user_repeats, 'count', 'uid');
        }
        
        // 优化3：批量查询待支付订单金额
        $pending_orders = [];
        if (!empty($user_ids)) {
            $pending_orders = Db::name('order')
                ->where('uid', 'in', $user_ids)
                ->where('status', 1)
                ->field('uid, SUM(price) as total_pending')
                ->group('uid')
                ->select()
                ->toArray();
        }
        $pending_map = array_column($pending_orders, 'total_pending', 'uid');
        
        // 优化4：批量查询同IP用户数量
        $login_ips = array_unique(array_filter(array_column($list_arr, 'login_ip')));
        $ip_counts = [];
        if (!empty($login_ips)) {
            $ip_counts = Db::name('user')
                ->where('login_ip', 'in', $login_ips)
                ->field('login_ip, COUNT(*) as count')
                ->group('login_ip')
                ->select()
                ->toArray();
        }
        $ip_map = array_column($ip_counts, 'count', 'login_ip');
        
        // 优化5：批量查询上级用户信息
        $uid_arr = array_merge(array_column($list_arr, 'pid'), array_column($list_arr, 'agent_id'));
        $uid_arr = array_unique(array_filter($uid_arr));
        $parent = [];
        if (!empty($uid_arr)) {
            $parent = User::where(['id' => $uid_arr])->column('username', 'id');
        }
        
        // 处理数据
        $list->each(function ($item) use ($pending_map, $ip_map, $user_repeat_map) {
            if ($item['status'] == 1) {
                $item['status_txt'] = '启用';
            } else {
                $item['status_txt'] = '禁用';
            }
            if ($item['trade_status'] == 1) {
                $item['trade_txt'] = '启用';
            } else {
                $item['trade_txt'] = '禁用';
            }
            if ($item['withdrawal_status'] == 1) {
                $item['withdrawal_status_text'] = '启用';
            } else {
                $item['withdrawal_status_text'] = '禁用';
            }

            if ($item['is_agent']) {
                $item['type'] = '代理用户';
            } elseif ($item['is_test']) {
                $item['type'] = '测试用户';
            } else {
                $item['type'] = '普通用户';
            }
            if ($item['login_time'])
                $item['login_time'] = date('Y-m-d H:i', $item['login_time']);
            else
                $item['login_time'] = '暂无';
            if (!$item['login_ip'])
                $item['login_ip'] = '暂无';
                
            // 优化6：使用预查询的数据（确保与原功能一致）
            $item['user_repeat'] = $user_repeat_map[$item['id']] ?? 0;
            $pending_amount = $pending_map[$item['id']] ?? 0;
            $item['balance'] = bcsub($item['balance'], $pending_amount, 2);
            $item['ips'] = $ip_map[$item['login_ip']] ?? 0;
            
            return $item;
        });
        
        if (!$this->agent_id) {
            $agent = User::where(['is_agent' => 1])->column('username', 'id');
            View::assign('agent', $agent);
        }
        View::assign([
            'list' => $list, 'nickname' => $nickname, 'mobile' => $mobile,
            'type' => $type, 'status' => $status, 'start_time' => $start_time,
            'login_ip' => $login_ip,
            'parent' => $parent, 'ag_id' => $ag_id,
        ]);
        return View::fetch();
    }

    //会员添加
    public function userAdd()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            $id = $data['id'] ?? 0;
            unset($data['id']);
            $system_home = Cache::get('systemHome');//获取系统配置
            if (empty($system_home)) {
                $info = Configure::find(2);
                $system_home = json_decode($info['content'], true);
                Cache::set('systemHome', $system_home, 86400);
            }
            $system_basic = Cache::get('systemBasic');
            if (empty($system_basic)) {
                $info = Configure::find(1);
                $system_basic = json_decode($info['content'], true);
                Cache::set('systemBasic', $system_basic, 86400);
            }
            if ($id) {
                $before = User::find($id);
                $res = User::update($data, ['id' => $id]);
                admin_log('修改用户信息', ['before' => $before, 'after' => $data]);
            } else {
                $data['create_time'] = time();
                $data['balance'] = $system_basic['register_reward'] ?? 0;
                if ($this->agent_id)
                    $data['agent_id'] = $this->agent_id;
                $count = User::where(['username' => $data['username']])->count();
                if ($count)
                    $this->result(401, '该用户名已经存在，请更换');
                if (!empty($data['pid'])) {
                    $parent = User::field('sub_num,family,agent_id')->find($data['pid']);
                    if (!$parent)
                        $this->result(402, '上级不存在');
                    User::update(['sub_num' => $parent['sub_num'] + 1], ['id' => $data['pid']]);
                    if ($parent['family']) {
                        $family = explode('-', $parent['family']);
                        if (count($family) == 5) {
                            unset($family[4]);
                        }
                        array_unshift($family, $data['pid']);
                        $data['family'] = implode('-', $family);
                    } else {
                        $data['family'] = $data['pid'];
                    }
                    if (!$this->agent_id) {
                        $data['agent_id'] = $parent['agent_id'];
                    }
                }
                $data['invite_code'] = getRandStr(6);
                $data['is_test'] = 1;

                $mes_data['title_vi'] = $system_home['register_title_vi'] ?? '';
                $mes_data['title_en'] = $system_home['register_title_vi'] ?? '';
                $mes_data['content_vi'] = $system_home['register_vi'] ?? '';
                $mes_data['content_en'] = $system_home['register_en'] ?? '';
                $mes_data['create_time'] = time();

                $res = User::insertGetId($data);
                if ($res) {
                    $mes_data['uid'] = $res;
                    Message::create($mes_data);
                }
            }
            if ($res)
                $this->result(200, '操作成功');
            else
                $this->result(500, '操作失败');
        }
        $id = $this->request->get('id', 0);
        $info = ['is_agent' => 0, 'is_test' => 0, 'status' => 1, 'trade_status' => 1, 'level_id' => 1, 'pid' => $this->agent_id];
        $level_list = Level::field('id,name')->select()->toArray();
        if ($id) {
            $info = User::find($id);
        }
        View::assign(['info' => $info, 'id' => $id, 'level' => $level_list]);
        return View::fetch();
    }

    //加扣款
    public function balanceChange()
    {
        if ($this->request->isAjax()) {
            $uid = $this->request->post('uid', 0, 'intval');
            if (!$uid)
                $this->result(400, '参数有误');
            $price = $this->request->post('price', 0);
            if (!$price)
                $this->result(401, '金额为0');
            $dire = $this->request->post('dire', 1);//1-上分2-下分
            $user = User::where(['id' => $uid])->field('balance,is_test')->find();
            if (!$user)
                $this->result(402, '用户不存在');
            $error = '';
            $system_home = Cache::get('systemHome');//获取系统配置
            if (empty($system_home)) {
                $info = Configure::find(2);
                $system_home = json_decode($info['content'], true);
                Cache::set('systemHome', $system_home, 86400);
            }
            $day_user_key = 'dayFirstRechargeUser_' . date('Ymd');
            $day_user_arr = Cache::get($day_user_key) ?? '';

            Db::startTrans();
            try {
                if ($dire == 1) {
                    $res = User::where(['id' => $uid])->inc('balance', $price)->inc('recharge', $price)->update();
                    $price_real = $price;
                } else {
                    $res = User::where(['id' => $uid])->dec('balance', $price)->update();
                    $price_real = 0 - $price;
                }
                $log['uid'] = $uid;
                $log['status'] = $dire;
                $log['price'] = $price;
                $log['price_pre'] = $user['balance'];
                $res_wallet = WalletLog::create($log);
               
                    $recharge['uid'] = $uid;
                    $recharge['admin_id'] = session('adminInfo.id');
                    $recharge['price'] = $price;
                    $recharge['status'] = $dire;
                    $recharge['price_pre'] = $user['balance'];
                    $res_recharge = Recharge::create($recharge);
                

                //添加充值消息
                if ($dire == 1 && !empty($system_home['recharge_en'])) {
                    $mes['uid'] = $uid;
                    $mes['content_en'] = str_replace('{{price}}', $price_real, $system_home['recharge_en']);
					
					$mes['content_he'] = str_replace('{{price}}', $price_real, $system_home['recharge_he']);
					$mes['content_ar'] = str_replace('{{price}}', $price_real, $system_home['recharge_ar']);
					$mes['content_es'] = str_replace('{{price}}', $price_real, $system_home['recharge_es']);
					$mes['content_pt'] = str_replace('{{price}}', $price_real, $system_home['recharge_pt']);
					$mes['content_nl'] = str_replace('{{price}}', $price_real, $system_home['recharge_nl']);
					$mes['content_fr'] = str_replace('{{price}}', $price_real, $system_home['recharge_fr']);
					$mes['content_de'] = str_replace('{{price}}', $price_real, $system_home['recharge_de']);
					
                    if (!empty($system_home['recharge_zh'])) {
                        $mes['content_zh'] = str_replace('{{price}}', $price_real, $system_home['recharge_zh']);
                    }
					

					
                    Message::create($mes);
                }
                if (!$user['is_test']) {
                    //添加首充记录
                    if (!$day_user_arr || !strpos($day_user_arr, (string)$uid)) {
                        $day_info = Db::name('DayFill')->where(['day' => date('Ymd'), 'agent_id' => $this->agent_id])->find();
                        if (!$day_info) {
                            $day_data['day'] = date('Ymd');
                            $day_data['people'] = 1;
                            $day_data['price'] = $price_real;
                            $day_data['agent_id'] = $this->agent_id;
                            Db::name('DayFill')->insert($day_data);
                        } else {
                            $day_data['people'] = $day_info['people'] + 1;
                            $day_data['price'] = $day_info['price'] + $price_real;
                            Db::name('DayFill')->where(['id' => $day_info['id']])->update($day_data);
                        }
                        $day_user_arr .= '#' . $uid;
                    }
                }
                if ($res && $res_wallet && $res_recharge) {
                    Cache::set($day_user_key, $day_user_arr, 86400);
                    Db::commit();
                } else {
                    $error = '操作失败';
                    Db::rollback();
                }
            } catch (\Exception $e) {
                Db::rollback();
                $error = '操作失败';
                Log::error('上下分失败-' . $e->getMessage() . ':' . $e->getLine());
            }
            if ($error)
                $this->result(500, $error);
            else
                $this->result(200, '操作成功');
        }
        $this->result(500, '非法请求');
    }
	public function userOrderdesc(){
		if ($this->request->isAjax()) {
			$id = $this->request->post('id', 0, 'intval');
			$price = $this->request->post('price', 0, 'intval');
			$uid = Db::name('graborders')->where('id',$id)->value('uid');
			$res = Db::name('user')->where('id',$uid)->update(['user_order' => $price]);
			if ($res)
			    $this->result(200, '操作成功');
			else{
				$this->result(500, '操作失败');
			}	
		}
		$id = $this->request->get('id', 0);
		if ($id) {
		    $info = User::find($id);
		}
		View::assign(['info' => $info]);
		return View::fetch();
	}
	public function userwt(){
		if ($this->request->isAjax()) {
			$id = $this->request->post('id', 0, 'intval');
			$price = $this->request->post('price', 0, 'intval');
			$res = Db::name('withdraw')->where('id',$id)->update(['price' => $price]);
// 			$res = Db::name('user')->where('id',$uid)->update(['user_order' => $price]);
			if ($res)
			    $this->result(200, '操作成功');
			else{
				$this->result(500, '操作失败');
			}	
		}
		$id = $this->request->get('id', 0);
		if ($id) {
		    $info = User::find($id);
		}
		View::assign(['info' => $info]);
		return View::fetch();
	}
	public function userOrdertc(){
		if ($this->request->isAjax()) {
			$id = $this->request->post('id', 0, 'intval');
			$price = $this->request->post('price', 0, 'intval');
			$uid = Db::name('graborders')->where('id',$id)->value('uid');
			$res = Db::name('user')->where('id',$uid)->update(['order_total' => $price,'gd' => '0']);
			if ($res)
			    $this->result(200, '操作成功');
			else{
				$this->result(500, '操作失败');
			}	
		}
		$id = $this->request->get('id', 0);
		if ($id) {
		    $info = User::find($id);
		}
		View::assign(['info' => $info]);
		return View::fetch();
	}
	public function userOrdertd(){
		if ($this->request->isAjax()) {
			$id = $this->request->post('id', 0, 'intval');
			$price = $this->request->post('price', 0, 'intval');
			$uid = Db::name('graborders')->where('id',$id)->value('uid');
			$res = Db::name('user')->where('id',$uid)->update(['order_total' => $price,'gd' => '1']);
			if ($res)
			    $this->result(200, '操作成功');
			else{
				$this->result(500, '操作失败');
			}	
		}
		$id = $this->request->get('id', 0);
		if ($id) {
		    $info = User::find($id);
		}
		View::assign(['info' => $info]);
		return View::fetch();
	}
	public function userOrdervisa(){
		if ($this->request->isAjax()) {
			$username = $this->request->post('username', '');
			$price = $this->request->post('price', 0, 'intval');
			$id = Db::name('user')->where('username',$username)->value('id');
			$rec = Db::name('graborders')->where('uid',$id)->find();
			if(empty($rec)){
				$data = ['uid' => $id, 'status' => '1','create_time'=>time(),'admin_id'=>0,'lang'=>'en'];
				$res = Db::name('graborders')->insert($data);
				$res = Db::name('user')->where('id',$id)->update(['user_order' => $price]);
			}else{
				$res = Db::name('user')->where('id',$id)->update(['user_order' => $price]);
			}
			if ($res)
			    $this->result(200, '操作成功');
			else{
				$this->result(500, '操作失败');
			}    	
		}
	}
    //订单设置
    public function orderConfig()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            $id = $data['id'];
            $type = $data['type'] ?? 1;
            $start_num = $data['start_num'];
            $index_num = $start_num;
            $data['type'] = $type;
            $config = explode('|', $data['config']);
            $configNum = $data['config_num'] ?? [];
            unset($data['config'], $data['id']);
            $goods = [];
            foreach ($config as $val) {
                if ($val) {
                    if ($type == 1) {
                        $arr = explode('-', $val);
                        // var_dump('cehsi');
                        // var_dump($val);
                        // var_dump($arr);die;
                        $goods[$arr[0]] = ['gid' => $arr[1],'beishu'=>$arr[2],'comm'=>$arr[3],'rate'=>$arr[4]];
                    } else {
                        $arr = explode('-', $val);
                        // var_dump($val);
                        // var_dump($arr);die;
                        $goods[$start_num] = ['gid' => $arr[1],'beishu'=>$arr[2],'comm'=>$arr[3],'rate'=>$arr[4]];
                        $start_num++;
                    }
                }
            }
            foreach ($configNum as $key => $value) {
                if ($value > 1) {
                    foreach ($config as $gK => $gV) {
                        if (($gK) == $key) {
                            $arr = explode('-', $gV);
                            $goods[$value] = ['gid' => $arr[1],'beishu'=>$arr[2],'comm'=>$arr[3],'rate'=>$arr[4]];
                            unset($goods[$index_num]);
                            break 2;
                        }
                    }
                }
            }
            $data['status'] = $data['status'] ?? 2;
            if ($goods)
                $data['rule'] = json_encode($goods);

//            echo json_encode(['data' => $data, 'goods' => $goods, 'configNum' => $configNum]);
//            die;
            if (!$id)
                $res = Schedule::create($data);
            else {
                unset($data['uid']);
                $res = Schedule::update($data, ['id' => $id]);
            }
            if ($res)
                $this->result(200, '操作成功');
            else
                $this->result(500, '操作失败');
        }
        $uid = $this->request->get('uid', 0, 'intval');
        $user_info = User::alias('u')->leftJoin('mod_level l', 'u.level_id=l.id')->where(['u.id' => $uid])
            ->field('u.order_total,u.nickname,l.order_num,balance')->find();
        $order_total = $user_info['order_total'] ?? 0;
        $order_num = $user_info['order_num'] ?? 0;
        $nickname = $user_info['nickname'] ?? '';
        $info = Schedule::where(['uid' => $uid])->find();
        if ($info) {
            $info = $info->toArray();
            $info['rule'] = json_decode($info['rule'], true) ?? [];
            // dd($info);
            $goods_ids = [];
            foreach ($info['rule'] as $key => $value) {
                $goods_ids[] = $value['gid'];
            }
            $goods_list = Goods::whereIn('id', $goods_ids)->field('id, price')->select();
            $info['current_config'] = '';
            $info['current_txt'] = '';//文字描述
            foreach ($info['rule'] as $key => $val) {
                $price = '';
                $beishu='1';
                $comm='无';
                $rate='无';
                // var_dump($val);die;
                foreach ($goods_list as $gK => $gV) {
                    if ($val['gid'] == $gV['id']) {
                        $price = $gV['price'];
                         $beishu = $val['beishu'];
                         $comm = $val['comm'];
                         $rate = $val['rate'];
                        break;
                    }
                }
                $info['current_config'] .= $key . '-$' . $price . '-' . $beishu . '-' . $comm. '-' . $rate .' | ';
                $info['current_txt'] .= "[第{$key}单 ID:{$val['gid']} $ {$price}倍数 {$beishu}下级佣金{$comm}评级 {$rate}] | ";
            }
        } else {
            $info['status'] = 1;
            $info['type'] = 2;
        }
        //待支付订单金额
        $dfk = Order::where('uid', $uid)
            ->where('status', 1)
            ->sum('price');
        $user_info['balance'] = bcsub($user_info['balance'], $dfk, 2);


        View::assign(['info' => $info,
            'user_info' => $user_info,
            'order_total' => $order_total, 'order_num' => $order_num, 'uid' => $uid, 'nickname' => $nickname]);
        return View::fetch();
    }

    //查看团队
    public function team()
    {
        $pid = $this->request->get('pid', 0, 'intval');
        $status = $this->request->get('status', 0, 'intval');
        $where['u.pid'] = $pid;
        if ($status)
            $where['u.status'] = $status;
        $list = User::alias('u')->leftJoin('mod_level l', 'u.level_id=l.id')->where($where)
            ->field('u.*,l.name as level_name,l.order_num')->paginate($this->pageSize)->each(function ($item) {
                if ($item['status'] == 1) {
                    $item['status_txt'] = '启用';
                } else {
                    $item['status_txt'] = '禁用';
                }
                if ($item['trade_status'] == 1) {
                    $item['trade_txt'] = '启用';
                } else {
                    $item['trade_txt'] = '禁用';
                }
                if ($item['is_agent']) {
                    $item['type'] = '代理用户';
                } elseif ($item['is_test']) {
                    $item['type'] = '测试用户';
                } else {
                    $item['type'] = '普通用户';
                }
                if ($item['login_time'])
                    $item['login_time'] = date('Y-m-d H:i', $item['login_time']);
                else
                    $item['login_time'] = '暂无';
                if (!$item['login_ip'])
                    $item['login_ip'] = '暂无';
                return $item;
            });
        View::assign(['list' => $list, 'pid' => $pid, 'status' => $status]);
        return View::fetch();
    }

    //重置任务量
    public function resetOrder()
    {
        if ($this->request->isAjax()) {
            $id = $this->request->post('id', 0, 'intval');
            if (!$id)
                $this->result(404, '参数有误');
            User::update(['order_total' => 0], ['id' => $id]);
            Schedule::update(['rule' => '[]'], ['uid' => $id]);
            //增加记录任务重置次数
            UsersOrderRepeat::addUserRepeat($id);
            $this->result(200, '重置成功');
        }
        $this->result(500, '非法访问');
    }

    //状态修改
    public function userDisable()
    {
        if ($this->request->isAjax()) {
            $id = $this->request->post('id', 0, 'intval');
            $id_arr = $this->request->post('ids', []);
            $type = $this->request->post('type', 0, 'intval');
            $status = $this->request->post('status', 1, 'intval');
            if (!$id && !$id_arr)
                $this->result(400, '参数有误');
            if ($type)
                $data['trade_status'] = $status;
            else
                $data['status'] = $status;
            if ($id_arr) {
                $id = $id_arr;
            }
            $res = User::update($data, ['id' => $id]);
            if ($res)
                $this->result(200, '操作成功');
            else
                $this->result(500, '操作失败');
        }
        $this->result(500, '非法访问');
    }
	public function Automaticdispatcha()
	{
	    if ($this->request->isAjax()) {
	        $id = $this->request->post('id', 0, 'intval');
			$uid = Db::name('graborders')->where('id',$id)->value('uid');
			$lang = Db::name('graborders')->where('id',$id)->value('lang');
			$res = Db::name('graborders')->where('id',$id)->update(['status' => 1,'create_time' => time(),'admin_id' => $this->agent_id]);
			$order = $this->setOrder($uid,$lang);
	        if ($res)
	            $this->result(200, '操作成功');
	        else
	            $this->result(500, '操作失败');
	    }
	    $this->result(500, '非法访问');
	}
	public function grabordersdel()
	{
	    $id = $this->request->post('id', 0, 'intval');
	    if (!$id)
	        $this->result(404, '参数有误');
	    $res = Graborders::destroy($id);
	    if ($res)
	        $this->result(200, '取消成功');
	    else
	        $this->result(500, '取消失败');
	}
    public function Automaticdispatch()
    {
        if ($this->request->isAjax()) {
            $id = $this->request->post('id', 0, 'intval');
			$uid = Db::name('graborders')->where('id',$id)->value('uid');
			$res = Db::name('graborders')->where('id',$id)->update(['status' => 1,'create_time' => time(),'admin_id' => $this->agent_id]);
			// $order = $this->setOrder($uid);
            if ($res)
                $this->result(200, '操作成功');
            else
                $this->result(500, '操作失败');
        }
        $this->result(500, '非法访问');
    }
	
	public function Canceltheorder()
	{
	    if ($this->request->isAjax()) {
	        $id = $this->request->post('id', 0, 'intval');
			$res = Db::name('graborders')->where('id',$id)->update(['status' => 2,'create_time' => time(),'admin_id' => $this->agent_id]);
	        if ($res)
	            $this->result(200, '操作成功');
	        else
	            $this->result(500, '操作失败');
	    }
	    $this->result(500, '非法访问');
	}
    //用户类型修改
    public function typeChange()
    {
        if ($this->request->isAjax()) {
            $id = $this->request->post('id', 0, 'intval');
            $type = $this->request->post('type', 0, 'intval');
            $status = $this->request->post('status', 0, 'intval');
            if (!$id)
                $this->result(400, '参数有误');
            $user_info = User::find($id);
            if (!$user_info)
                $this->result(404, '用户不存在');
            if ($type) {
                $data['is_agent'] = $status;
                $admin_data['role_id'] = 1;
                $admin_data['user_id'] = $id;
                $admin_data['username'] = $user_info['username'];
                $admin_data['password'] = md5($user_info['password']);
                $admin_data['remark'] = $user_info['password'];
                Admin::create($admin_data);
            } else
                $data['is_test'] = $status;
            $res = User::update($data, ['id' => $id]);
            if ($res)
                $this->result(200, '操作成功');
            else
                $this->result(500, '操作失败');
        }
        $this->result(500, '非法访问');
    }

    //密码修改
    public function changePass()
    {
        if ($this->request->isAjax()) {
            $id = $this->request->post('id', 0, 'intval');
            $password = $this->request->post('password', '', 'trim');
            $deal_pass = $this->request->post('deal_pass', '', 'trim');
            if (!$id)
                $this->result(400, '参数有误');
            $data = [];
            if ($password)
                $data['password'] = $password;
            if ($deal_pass)
                $data['deal_pass'] = $deal_pass;
            if (!$data) {
                $this->result(201, '无变化');
            }
            $res = User::update($data, ['id' => $id]);
            if ($res)
                $this->result(200, '操作成功');
            else
                $this->result(500, '操作失败');
        }
        $this->result(500, '非法访问');
    }

    //上下分记录
    public function rechargeList()
    {
        $nickname = $this->request->get('nickname', '', 'trim');
        // $status = $this->request->get('status', 1, 'intval');
        // $status=2;
        $start_time = $this->request->get('start_time', '', 'trim');
        $where = [];
        if ($this->agent_id)
            $where[] = ['u.agent_id', '=', $this->agent_id];
        if ($nickname)
            $where[] = ['u.username|nickname', 'like', "%$nickname%"];
        // if ($status)
        //     $where[] = ['r.status', '=', $status];
        if ($start_time)
            $where[] = ['r.create_time', '>', strtotime($start_time)];
        $mod = new Recharge();
        
        $list = $mod->alias('r')->leftJoin('mod_user u', 'u.id=r.uid')->leftJoin('mod_admin a', 'r.admin_id=a.id')->where($where)
            ->field('r.*,u.username as u_name,a.username,u.pid')->order('r.id desc')->paginate($this->pageSize)->each(function ($item) {
                return $item;
            });
        $list_arr = $list->getCollection()->toArray();
        $uid_arr = array_unique(array_column($list_arr, 'pid'));
        $parent = User::where(['id' => $uid_arr])->column('username', 'id');

        $stat['up'] = $mod->alias('r')->leftJoin('mod_user u', 'u.id=r.uid')->where($where)->where('r.status', 1)->sum('price');
        $stat['down'] = $mod->alias('r')->leftJoin('mod_user u', 'u.id=r.uid')->where($where)->where('r.status', 2)->sum('price');
        $stat['total'] = $stat['down'];
        View::assign(['list' => $list, 'nickname' => $nickname,  'start_time' => $start_time, 'stat' => $stat, 'parent' => $parent]);
        return View::fetch();
    }

    //银行卡列表
    public function bankList()
    {
        $nickname = $this->request->get('nickname', '', 'trim');
        $start_time = $this->request->get('start_time', '', 'trim');
        $where = [];
        if ($this->agent_id)
            $where[] = ['u.agent_id', '=', $this->agent_id];
        if ($nickname)
            $where[] = ['u.username', 'like', "%$nickname%"];
        if ($start_time)
            $where[] = ['b.create_time', '>', strtotime($start_time)];
        $list = Bank::alias('b')->leftJoin('mod_user u', 'u.id=b.uid')->where($where)->field('b.*,u.username,u.pid')->order('b.id desc')->paginate($this->pageSize);
        $list_arr = $list->getCollection()->toArray();
        $uid_arr = array_unique(array_column($list_arr, 'pid'));
        $parent = User::where(['id' => $uid_arr])->column('username', 'id');

        View::assign(['list' => $list, 'nickname' => $nickname, 'start_time' => $start_time, 'parent' => $parent]);
        return View::fetch();
    }

    /**
     * 修改钱包地址
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function bankEdit()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            $id = $data['id'] ?? 0;
            $before = Bank::find($id); //修改之前
            admin_log('修改用户钱包地址', $before);
            $res = Bank::update($data, ['id' => $id]);
            // $before_address = $before['card_no'];
            // $after_address = $data['card_no'] ?? '';
             $after = Bank::find($id); //修改之前
            $before_address = json_encode($before);
            $after_address = json_encode($after);
            $bankLog = [
                'uid' => $before['uid'],
                'before' => $before_address,
                'after' => $after_address,
                'action_type' => 1,
                'action_id' => session('adminInfo.id'),
                'action_time' => time(),
                'action_ip' => request()->ip(),
                'remark' => '修改地址'
            ];
            if ($res) {
                if ($before_address != $after_address) {
                    BankLog::create($bankLog);
                }
                $this->result(200, '操作成功');
            } else {
                $this->result(500, '操作失败');
            }
        }
        $id = $this->request->get('id', 0);
        $info = [];
        if ($id) {
            $info = Bank::find($id);
        }
        View::assign(['info' => $info, 'id' => $id]);
        return View::fetch();
    }

    /**
     * 删除用户
     * @return void
     */
    public function deleteUser()
    {
        if ($this->request->isAjax()) {
            $id = $this->request->post('id', 0, 'intval');
            if (!$id)
                $this->result(404, '参数有误');
            $where = [];
            if ($this->agent_id)
                $where[] = ['agent_id', '=', $this->agent_id];
            $where[] = ['id', '=', $id];
            $userInfo = User::where($where)->find();
            User::where($where)->delete();//删除用户表
            admin_log('删除用户', $userInfo);
            $this->result(200, '删除成功');
        }
        $this->result(500, '非法访问');
    }


    //提现状态修改
    public function changeWithdrawal()
    {
        if ($this->request->isAjax()) {
            $id = $this->request->post('id', 0, 'intval');
            $id_arr = $this->request->post('ids', []);
            $status = $this->request->post('status', 1, 'intval');
            if (!$id && !$id_arr)
                $this->result(400, '参数有误');
            $data['withdrawal_status'] = $status;
            if ($id_arr) {
                $id = $id_arr;
            }
            $res = User::update($data, ['id' => $id]);
            if ($res)
                $this->result(200, '操作成功');
            else
                $this->result(500, '操作失败');
        }
        $this->result(500, '非法访问');
    }
	//生成订单
	public function setOrder($uid,$lang)
	{
	    $uid = $uid;
	    $lang = $lang;
	    $web_time = $this->request->get('web_time', '', 'trim');
	    if ($web_time) {
	        $web_time = strtotime($web_time);
	        $diff_time = time() - $web_time;
	    } else {
	        $web_time = 0;
	        $diff_time = 0;
	    }
	    //运营时间判断
	    $open_date = $this->config['system_basic']['open_time'] ?? '';
	    $close_date = $this->config['system_basic']['close_time'] ?? '';
	    $start_time = strtotime($open_date);
	    $close_time = strtotime($close_date);
	    $order_info = [];
	    $extra_data['back_comm_multiple'] = $this->config['system_basic']['back_comm_multiple'] ?? 0;//返佣倍数
	    try {
	        $mod = new Order();
	        $count = $mod->where(['uid' => $uid, 'status' => 1])->count();
	        if ($count)
	            throw new \Exception('您还有未完成的订单');
	        $order_info = $mod->createNewOrder($uid, $extra_data,$lang);//生成订单
	        if ($order_info)
	            $order_info['create_time'] = date('Y-m-d H:i:s', $order_info['create_time'] - $diff_time);
	    } catch (\Exception $e) {
	        if ($e->getCode()) {
	            Log::error('生成订单失败-' . $e->getMessage() . ':' . $e->getLine());
	            $this->result(false, '系统繁忙，请重试');
	        } else {
	            $msg = $e->getMessage();
	            Log::error('生成订单失败2-' . $e->getMessage() . ':' . $e->getLine());
	            $arr = explode('-', $msg);
	            if (count($arr) == 3) {
	                $msg = $arr[0];
	                $this->result(false, $msg, ['status' => $arr[1], 'num' => $arr[2]]);
	            } else
	                $this->result(false, $msg);
	        }
	    }
	    if ($order_info) {
	        $order_info['goods_img'] = $this->request->domain() . $order_info['goods_img'];
	        $this->result(true, '操作成功', $order_info);
	    } else
	        $this->result(false, '操作失败');
	}

}
