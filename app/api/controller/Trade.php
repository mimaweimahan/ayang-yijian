<?php

namespace app\api\controller;

use app\common\model\Order;
use app\common\model\Goods2;
use think\facade\Db;
use think\facade\Log;

class Trade extends Base
{
    //生成订单
    public function setOrder()
    {
        $uid = $this->request->user['id'];
        $lang = $this->request->post('lang','');
		$graborders = Db::name('graborders')->where('uid',$uid)->find();
	    $userb = Db::name('user')->where('id',$uid)->find();
	    if($userb['balance'] < 10){
                if($lang == 'zh')$lang = '余额不足，请充值';//中文
                if($lang == 'pt')$lang = 'Se o saldo for insuficiente, por favor, recarregue';//葡萄牙语
                if($lang == 'nl')$lang = 'Als het saldo onvoldoende is, gelieve dan op te waarderen';//荷兰语
                if($lang == 'he')$lang = 'אם היתרה אינה מספקת, אנא מלא';//希伯来语
                if($lang == 'fr')$lang = 'Si le solde est insuffisant, veuillez recharger';//法语
                if($lang == 'es')$lang = 'Si el saldo es insuficiente, recargue';//西班牙语
                if($lang == 'en')$lang = 'If the balance is insufficient, please top up';//英语
                if($lang == 'de')$lang = 'Sollte das Guthaben nicht ausreichen, bitte aufladen';//德语
                if($lang == 'ar')$lang = 'إذا كان الرصيد غير كاف ، فيرجى تعبئة الرصيد';//阿拉伯语
			    $a = array(
			        'info' => '2',
					'auto' => $lang,
				);
                $this->result(true, '操作成功',$a);
	    }
		if(empty($graborders)||$graborders['status'] == '2'){
			if($graborders['status'] == '2'){
				$data = ['uid' => $uid, 'status' => '2','lang' => $lang , 'create_time' => time() , 'admin_id' => '0'];
				Db::name('graborders')->where('uid',$uid)->update($data);
				$a = array(
					'auto' => 1,
				);
				$this->result(true, '操作成功',$a);
			}else{
				$data = ['uid' => $uid, 'status' => '2','lang' => $lang , 'create_time' => time() , 'admin_id' => '0'];
				Db::name('graborders')->insert($data);
				$a = array(
					'auto' => 1,
				);
				$this->result(true, '操作成功',$a);
			}

		}
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
        $lang = $this->request->post('lang','');
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

    //提交订单-分佣
    public function commitOrder()
    {
        $order_id = $this->request->post('order_id', 0, 'intval');
        $uid = $this->request->user['id'];
        if (!$order_id) {
            $this->result(false, '参数有误');
        }
        if ($this->request->has('guest_review')) {
            $guest_review = (string) $this->request->param('guest_review', '', 'trim');
            if ($guest_review !== '' && function_exists('mb_substr')) {
                if (mb_strlen($guest_review, 'UTF-8') > 2000) {
                    $guest_review = mb_substr($guest_review, 0, 2000, 'UTF-8');
                }
            } elseif ($guest_review !== '' && strlen($guest_review) > 2000) {
                $guest_review = substr($guest_review, 0, 2000);
            }
            try {
                Db::name('Order')
                    ->where('id', $order_id)
                    ->where('uid', $uid)
                    ->where('status', 1)
                    ->update(['guest_review' => $guest_review]);
            } catch (\Throwable $e) {
                Log::warning('order guest_review column missing or update failed: ' . $e->getMessage());
            }
        }
        $level_arr = [
            $this->config['system_basic']['comm_reward_0'] ?? '0.01',
            $this->config['system_basic']['comm_reward_1'] ?? '0.01',
            $this->config['system_basic']['comm_reward_2'] ?? '0.01',
            $this->config['system_basic']['comm_reward_3'] ?? '0.01',
            $this->config['system_basic']['comm_reward_4'] ?? '0.01',
            $this->config['system_basic']['comm_reward_daili'] ?? '0.01',
        ];
        try {
            $mod = new Order();
            $mod->doOrder($order_id, $uid, $level_arr);
        } catch (\Exception $e) {
            if ($e->getCode()) {
                Log::error('订单分佣失败-' . $e->getMessage() . ':' . $e->getLine());
                $this->result(false, '系统繁忙，请重试');
            } else {
                Log::error('提交订单失败-' . $e->getMessage() . ':' . $e->getLine());
                $this->result(false, $e->getMessage());
            }
        }
        $this->result(true, '操作成功');
    }

    //订单列表
    public function getOrderList()
    {
        $uid = $this->request->user['id'];
        $web_time = $this->request->post('web_time', '', 'trim');
        $lang = $this->request->post('lang','');
        if ($web_time)
            $diff_time = time() - strtotime($web_time);
        else
            $diff_time = 0;
        $page = $this->request->post('page', 1, 'intval');
        $status = $this->request->post('status', 0, 'intval');
        $domain = $this->request->domain();
        $where['o.uid'] = $uid;
        if ($status)
            $where['o.status'] = $status;
        $list = Db::name('Order')->alias('o')
            ->leftJoin('mod_goods g', 'o.goods_id=g.id')
//            ->leftJoin('mod_wallet_log wallet',
//                'o.id = wallet.order_id and wallet.status = 1 and wallet.type = 4')
            ->where($where)
            ->field('o.*,g.'.$lang.'_name as goods_name,g.img as goods_img, g.room_name as goods_room_name,
            g.rating as goods_rating, g.rating_tag as goods_rating_tag,
             g.comments_number as goods_comments_number')
            ->order('o.id desc')
            ->paginate(['list_rows' => 10, 'page' => $page])->toArray();
        foreach ($list['data'] as &$item) {
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time'] - $diff_time);
            if ($item['end_time'])
                $item['end_time'] = date('Y-m-d H:i:s', $item['end_time'] - $diff_time);
            if ($item['goods_img'])
                $item['goods_img'] = $domain . $item['goods_img'];
            $item['goods_comments_number'] = ($item['goods_comments_number'] ?: 0) . ' reviews';
            $item['money'] = $item['price'];
            $item['wallet_after'] = bcadd($item['price'], $item['commission'], 2);
            $item['wallet_before'] = round($item['price'],2);
			$item['priceplus'] = round($item['price'] + $item['commission'],2);
        }
        $this->result(true, '操作成功', $list['data'], ['total' => $list['total'], 'last_page' => $list['last_page']]);
    }

    //订单详情
    public function getOrderInfo()
    {
        $web_time = $this->request->post('web_time', '', 'trim');
        $lang = $this->request->post('lang','');
        if ($web_time)
            $diff_time = time() - strtotime($web_time);
        else
            $diff_time = 0;
        $domain = $this->request->domain();
        $order_id = $this->request->post('order_id', 0, 'intval');
        if (!$order_id)
            $this->result(false, '参数有误');
        $order_info = Db::name('Order')->alias('o')->leftJoin('mod_goods g', 'o.goods_id=g.id')->where(['o.id' => $order_id])->field('o.*,g.'.$lang.'_name as goods_name,g.img as goods_img')->find();
        if (!$order_info)
            $this->result(false, '数据不存在');
        $order_info['create_time'] = date('Y-m-d H:i:s', $order_info['create_time'] - $diff_time);
        if ($order_info['end_time'])
            $order_info['end_time'] = date('Y-m-d H:i:s', $order_info['end_time'] - $diff_time);
        if ($order_info['goods_img'])
            $order_info['goods_img'] = $domain . $order_info['goods_img'];
        $this->result(true, '操作成功', $order_info);
    }

}
