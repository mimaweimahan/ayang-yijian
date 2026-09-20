<?php

namespace app\common\model;

use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\Model;

class Order extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = false;
    protected $errType = 0; //1可用余额不足

    /**
     * @param int $uid
     * @param array $extra_data 额外参数
     * @return array
     * @throws \Exception
     */
    public function createNewOrder($uid, $extra_data,$lang)
    {
        $user_info = User::alias('u')->leftJoin('mod_level l', 'u.level_id=l.id')->where(['u.id' => $uid])
            ->field('u.user_order,u.credit_score,u.status,u.balance,u.trade_status,u.order_total,u.deal_date,l.order_num,l.order_rate,l.limit_price')->find();
        $langs = $lang;
        if ($user_info['credit_score'] < 100){
            throw new \Exception('信用分低于100');
        }
            
        if ($user_info['status'] != 1){
            throw new \Exception('该账号被禁用了，请联系客服');
        }
            
        if ($user_info['trade_status'] != 1){
            throw new \Exception('交易被禁用，请联系客服');
        }
            
        if ($user_info['balance'] < 0){
                
                if($langs == 'zh')$langs = '余额不足，请充值';//中文
                if($langs == 'pt')$langs = 'Se o saldo for insuficiente, por favor, recarregue';//葡萄牙语
                if($langs == 'nl')$langs = 'Als het saldo onvoldoende is, gelieve dan op te waarderen';//荷兰语
                if($langs == 'he')$langs = 'אם היתרה אינה מספקת, אנא מלא';//希伯来语
                if($langs == 'fr')$langs = 'Si le solde est insuffisant, veuillez recharger';//法语
                if($langs == 'es')$langs = 'Si el saldo es insuficiente, recargue';//西班牙语
                if($langs == 'en')$langs = 'If the balance is insufficient, please top up';//英语
                if($langs == 'de')$langs = 'Sollte das Guthaben nicht ausreichen, bitte aufladen';//德语
                if($langs == 'ar')$langs = 'إذا كان الرصيد غير كاف ، فيرجى تعبئة الرصيد';//阿拉伯语
            throw new \Exception($langs . $user_info['limit_price']);
        }
        if ($user_info['limit_price'] > $user_info['balance']) {
            throw new \Exception('会员等级余额不足' . $user_info['limit_price']);
        }
        if ($user_info['order_total'] >= $user_info['user_order']) {
                if($langs == 'zh')$langs = '可做单数已用完';//中文
                if($langs == 'pt')$langs = 'Pode ser feito singular foi usado';//葡萄牙语
                if($langs == 'nl')$langs = 'Kan enkelvoud is opgebruikt';//荷兰语
                if($langs == 'he')$langs = 'ניתן לעשות יחיד נוצל';//希伯来语
                if($langs == 'fr')$langs = 'Peut être fait singulier a été utilisé';//法语
                if($langs == 'es')$langs = 'Se puede hacer singular se ha agotado';//西班牙语
                if($langs == 'en')$langs = 'Can be done singular has been used up';//英语
                if($langs == 'de')$langs = 'Kann gemacht werden, wenn singulär aufgebraucht ist';//德语
                if($langs == 'ar')$langs = 'يمكن القيام به مفرد تم استخدامه';//阿拉伯语
            throw new \Exception($langs . $user_info['order_total']);
        }
        //开启事务
        $this->startTrans();
        // $rule_info = Schedule::where(['uid' => $uid, 'status' => 1])->find();//搜索相关连单
		$rule_info2 = Db::name('hotsales')->where(['uid' => $uid, 'status' => 1])->select();
        $goods_info = [];
        $order_no = $user_info['order_total'] + 1;//已经做的单加一
		if(!empty($rule_info2)){
			$userhotsales = [];
			foreach ($rule_info2 as $v=>$k){
					if($k['appointment'] == $order_no){
						$userhotsales = $k;
					}
			}
			if(!empty($userhotsales)){
				Db::name('hotsales')->where('id',$userhotsales['id'])->update(['status' => '2']);
			}
			
			
		}
			$field = 'id,price,'.$lang.'_name,img';
        if (!empty($rule_info2)) {
			//新
            if (!empty($userhotsales)) {
					$goods = Db::name('goods')->select();
					$goodsdatas = [];
					foreach ($goods as $a=>$t){
						$goodsdatas[] = $t['id'];
					}
				$randid = array_rand($goodsdatas); // 获取一个随机索引
				$randidnbr = $goodsdatas[$randid];//获取一个随机id
                $goods_info = Db::name('goods')->where(['id' => $randidnbr])->field($field)->find();//搜索相关单子
                
				}
			}


        if (empty($goods_info)) {
            $goods_info = Goods::where('price', '<', $user_info['balance'])->field($field)->orderRaw('rand()')->find();
            $extra_data['back_comm_multiple'] = 0;
            if (!$goods_info){
                if($langs == 'zh')$langs = '符合商品不存在，请联系客服';//中文
                if($langs == 'pt')$langs = 'Se o produto correspondente não existir, entre em contato com o atendimento ao cliente';//葡萄牙语
                if($langs == 'nl')$langs = 'Als het passende product niet bestaat, neem dan contact op met de klantenservice';//荷兰语
                if($langs == 'he')$langs = 'אם המוצר התואם אינו קיים, אנא פנה לשירות הלקוחות';//希伯来语
                if($langs == 'fr')$langs = 'Si le produit correspondant n’existe pas, veuillez contacter le service client';//法语
                if($langs == 'es')$langs = 'Si el producto correspondiente no existe, póngase en contacto con el servicio de atención al cliente';//西班牙语
                if($langs == 'en')$langs = 'If the matching product does not exist, please contact customer service';//英语
                if($langs == 'de')$langs = 'Sollte das passende Produkt nicht vorhanden sein, wenden Sie sich bitte an den Kundendienst';//德语
                if($langs == 'ar')$langs = 'إذا لم يكن المنتج المطابق موجودا ، فيرجى الاتصال بخدمة العملاء';//阿拉伯语
                
                // if($langs == 'zh')$langs = '';//中文
                // if($langs == 'pt')$langs = '';//葡萄牙语
                // if($langs == 'nl')$langs = '';//荷兰语
                // if($langs == 'he')$langs = '';//希伯来语
                // if($langs == 'fr')$langs = '';//法语
                // if($langs == 'es')$langs = '';//西班牙语
                // if($langs == 'en')$langs = '';//英语
                // if($langs == 'de')$langs = '';//德语
                // if($langs == 'ar')$langs = '';//阿拉伯语
                throw new \Exception($langs);
				}
        }
		
        if ($user_info['deal_date'] != date('Ymd')) {
            $user_data['deal_date'] = date('Ymd');
            $user_data['today_profit'] = 0;
        }
        $user_data['order_total'] = $order_no;
		
        // if($new_rule_order!=''){
        //     $goods_info['price'] = round($new_rule_order['beishu']* $goods_info['price'],2);
        // }

		if(!empty($userhotsales)){//新
		    $goods_info['price'] = round($userhotsales['amount'] + $user_info['balance'],2);
		}
        if (!empty($userhotsales['number'])){
			$comm = round($goods_info['price'] * $user_info['order_rate'] * $userhotsales['number'], 2);//新
        }else{
			$comm = round($goods_info['price'] * $user_info['order_rate'], 2);
		}
			$order['uid'] = $uid;
			$order['order_no'] = getSn('SC');
			$order['goods_id'] = $goods_info['id'];
			$order['num'] = 1;
			$order['price'] = $goods_info['price'];
			$order['commission'] = $comm;
			$order['create_time'] = time();
			$order['lang'] = $lang;
	
        // if($new_rule_order!=''){
        //     $order['beishu'] = $new_rule_order['beishu'];
        //     $order['comm'] = $new_rule_order['comm'];
        //     $order['is_fuli'] = 1;
        //     $order['rate'] = $new_rule_order['rate'];
        // }

        if(!empty($userhotsales)){
            $order['beishu'] = $userhotsales['number'];
            $order['comm'] = $userhotsales['number'];
            $order['is_fuli'] = 1;
            $order['rate'] = rand(3,5);
        }

        $log = [
            'uid' => $uid,
            'type' => 3,
            'status' => 2,
            'price' => $goods_info['price'],
            'price_pre' => $user_info['balance'],
            'explain' => '生成订单扣款',
            'extra_id' => $goods_info['id'],
        ];

        $info = [
            'goods_name' => $goods_info[$lang.'_name'],
            'price' => $goods_info['price'],
            'goods_img' => $goods_info['img'],
            'num' => 1,
            'order_no' => $order['order_no'],
            'commission' => $comm,
			'priceplus' => round($goods_info['price'] + $comm,2),
            'create_time' => $order['create_time'],
            'is_fuli' => 0,
            'beishu' => 1,
            'rate' => 1,
            
        ];			
        if(!empty($userhotsales)){
            $info['is_fuli'] = 1;
            $info['beishu'] = $userhotsales['number'];
            $info['rate'] = $order['rate'];
        }

        try {
            $log['order_id'] = $info['order_id'] = $this->insertGetId($order);
            User::where(['id' => $uid])->update($user_data);
            $this->commit();
        } catch (\Exception $e) {
            $this->rollback();
            $info = [];
            Log::error('购买订单-' . $e->getMessage() . ':' . $e->getLine());
        }
        return $info;
    }

    /**
     * @param int $order_id
     * @param int $uid
     * @param array $level_arr 分佣比例
     * @throws \Exception
     */
    public function doOrder($order_id, $uid, $level_arr)
    {
        $order_info = $this->find($order_id);
        $langs = $order_info['lang'];
        if (!$order_info)
            throw new \Exception('数据不存在');
        if ($order_info['uid'] != $uid)
            throw new \Exception('参数错误，请确认订单号!');
        if ($order_info['status'] != 1) {
            return 1;
//            throw new \Exception('订单已处理过，请刷新');
        }
        //获取上级族谱
        $user_info = User::field('family,balance,freeze_balance')->find($uid);
        if ($user_info['balance'] < $order_info['price']) {
			if($langs == 'zh'){//中文
				$langs = '可用余额不足';
			}
			if($langs == 'pt'){//葡萄牙语
				$langs = 'Saldo disponível insuficiente';
			}
			if($langs == 'nl'){//荷兰语
				$langs = 'Onvoldoende beschikbaar saldo';
			}
			if($langs == 'he'){//希伯来语
				$langs = 'יתרה פנויה לא מספקת';
			}
			if($langs == 'fr'){//法语
				$langs = 'Solde disponible insuffisant';
			}
			if($langs == 'es'){//西班牙语
				$langs = 'Saldo disponible insuficiente';
			}
			if($langs == 'en'){//英语
				$langs = 'Insufficient available balance';
			}
			if($langs == 'de'){//德语
				$langs = 'Unzureichendes verfügbares Guthaben';
			}
			if($langs == 'ar'){//阿拉伯语
				$langs = 'الرصيد المتاح غير كافٍ';
			}
            throw new \Exception($langs);
        }
        $this->startTrans();
        try {
            //未付款才扣款
            if ($order_info['status'] != 2) {
                //扣款
                Db::table('mod_user')
                    ->where(['id' => $uid])
                    ->inc('freeze_balance', $order_info['price'] + $order_info['commission']) ////冻结商品金额 + 佣金
                    ->dec('balance', $order_info['price'])->update();
                //扣款记录
                $log = [
                    'uid' => $uid,
                    'type' => 3,
                    'status' => 2,
                    'price' => $order_info['price'],
                    'price_pre' => $user_info['balance'],
                    'explain' => '生成订单扣款',
                    'extra_id' => $order_info['goods_id'],
                ];
                WalletLog::create($log);
            }

            //订单返佣状态
            $c_status = $order_info['com_status']; //返佣状态1-未返佣2-已返佣
            $liandan_setting = 0;
            $liandan_id = [];
            //查询用户连单设置
            $rule_info = Schedule::where(['uid' => $uid, 'status' => 1])->find();
            if (!empty($rule_info)) {
                $liandan_setting = $rule_info['start_num'];
                $liandan_id = json_decode($rule_info['rule'], true) ?? [];
            }

            //获取用户当前单数
            $user_info = User::field('id,family,balance,order_total')->find($uid);
            $day_d_count = $user_info['order_total'];
            //最后一单
            if ($liandan_setting > 0 && $day_d_count == $liandan_setting && empty($liandan_id)) {
                Log::info('最后一单 ' . $order_info['id'],
                    [
                        '$day_d_count' => $day_d_count,
                        '$liandan_setting' => $liandan_setting,
                        '$liandan_id' => $liandan_id
                    ]);
                //返佣
                if ($c_status === 1) $this->deal_reward($order_info, $user_info, 1);
                $oLists = $this
                    ->where('uid', $uid)
                    ->where('com_status', 1)
                    ->select();
                foreach ($oLists as $key => $order_item) {
                    $order_item['commission'] = 0;
                    $user_info = User::field('id,family,balance')->find($uid);
                    $this->deal_reward($order_item, $user_info, 1);
                }

            } else if (!empty($liandan_id) && $day_d_count == $liandan_setting) {
                Log::info('连单 ' . $order_info['id'],
                    [
                        '$day_d_count' => $day_d_count,
                        '$liandan_setting' => $liandan_setting,
                        '$liandan_id' => $liandan_id
                    ]);
                Schedule::where(['id' => $rule_info['id']])
                    ->update(['start_num' => $rule_info['start_num'] + 1]);
                //返佣
                if ($c_status === 1) $this->deal_reward($order_info, $user_info, 2);
            } else {
                Log::info('正常做单 ' . $order_info['id'],
                    [
                        '$day_d_count' => $day_d_count,
                        '$liandan_setting' => $liandan_setting,
                        '$liandan_id' => $liandan_id
                    ]);
                //返佣金和本金
                if ($c_status === 1) $this->deal_reward($order_info, $user_info, 1);

                $oLists = $this
                    ->where('uid', $uid)
                    ->where('com_status', 1)
                    ->select();
                foreach ($oLists as $key => $order_item) {
                    $order_item['commission'] = 0;
                    $user_info = User::field('id,family,balance')->find($uid);
                    $this->deal_reward($order_item, $user_info, 1);
                }
            }

            $this->commit();
        } catch (\Exception $e) {
            $this->rollback();
            Log::error('做单失败-' . $e->getMessage() . ':' . $e->getLine());
        }

    }

    protected function deal_reward($order_info, $user_info, $type = 1)
    {
        //$type 1 返回本金+佣金 2 返回佣金
        $level_arr = $this->getLevelArr();
        $uid = $user_info['id'];
        $order_id = $order_info['id'];
        $log = [];
        //返回佣金+本金
        if ($type === 1) {
            $add_price = $order_info['price'] + $order_info['commission'];
            //返佣
            Db::table('mod_user')
                ->where(['id' => $uid])
                ->inc('balance', $add_price)
                ->dec('freeze_balance', $add_price)
                ->inc('commission', $order_info['commission'])
                ->inc('today_profit', $order_info['commission'])
                ->update();
            //修改订单状态
            $this->where(['id' => $order_id])->update(['status' => 2, 'com_status' => 2, 'end_time' => time()]);
            //返佣记录
            $log[] = [
                'uid' => $uid,
                'order_id' => $order_info['id'],
                'type' => 8,
                'status' => 1,
                'price' => $order_info['price'],
                'price_pre' => $user_info['balance'],
                'explain' => '交易金额返回',
                'create_time' => time(),
                'extra_id' => $order_info['goods_id'],
            ];
            if ($order_info['commission'] != 0) {
                $log[] = [
                    'uid' => $uid,
                    'order_id' => $order_info['id'],
                    'type' => 4,
                    'status' => 1,
                    'price' => $order_info['commission'],
                    'price_pre' => $user_info['balance'] + $order_info['price'],
                    'explain' => '交易返佣',
                    'create_time' => time(),
                    'extra_id' => $order_info['goods_id'],
                ];
            }
            $log[] = [
                'uid' => $uid,
                'order_id' => $order_info['id'],
                'type' => 9,
                'status' => 1,
                'price' => $user_info['balance'] + $add_price,
                'price_pre' => $user_info['balance'] + $add_price,
                'explain' => '交易完成后余额',
                'create_time' => time(),
                'extra_id' => $order_info['goods_id'],
            ];
        } else {
            $add_price = $order_info['commission'];
            Db::table('mod_user')
                ->where(['id' => $uid])
                ->inc('balance', $add_price)
                ->dec('freeze_balance', $add_price)
                ->inc('commission', $order_info['commission'])
                ->inc('today_profit', $order_info['commission'])
                ->update();
            //修改订单状态
            $this->where(['id' => $order_id])->update(['status' => 2, 'end_time' => time()]);

            //返回佣金
            $log[] = [
                'uid' => $uid,
                'order_id' => $order_info['id'],
                'type' => 4,
                'status' => 1,
                'price' => $order_info['commission'],
                'price_pre' => $user_info['balance'] + $order_info['price'],
                'explain' => '交易返佣',
                'create_time' => time(),
                'extra_id' => $order_info['goods_id'],
            ];
        }
        if (!empty($user_info['family'])) {

            $family = explode('-', $user_info['family']);
            $child_id = $uid;
            $xiaji_comm=false;
            // if($order_info['comm']&&!is_null($order_info['comm'])){
            //     $xiaji_comm = explode(',',$order_info['comm']);
            //                   dump($xiaji_comm);die;     
            //  }
            foreach ($family as $level => $pid) {
                $parent = User::field('balance,is_agent')->find($pid);
                if(!$parent){
                    continue;
                }
                if ($parent['is_agent'] == 1 && $level == 0) {
                    $comm = round($order_info['commission'] * $level_arr[5], 2);
                    $text = '代理直推返佣金';
                } else {
                    // if($xiaji_comm){
                    //     $comm=round($xiaji_comm[$level],2);
                        
                    // }else{
                        
                    // }
                   $comm = round($order_info['commission'] * $level_arr[$level], 2);
                    $text = ($level + 1) . '级返佣金';
                }
                if ($comm > 0) {
                    $log[] = [
                        'uid' => $pid,
                        'order_id' => $order_info['id'],
                        'type' => 5,
                        'status' => 1,
                        'price' => $comm,
                        'price_pre' => $parent['balance'],
                        'explain' => $text,
                        'create_time' => time(),
                        'extra_id' => $child_id,
                    ];
                    Db::table('mod_user')
                        ->where(['id' => $pid])
                        ->inc('balance', $comm)
                        ->inc('commission', $comm)
                        ->update();
                }
                $child_id = $pid;
            }
        }
        WalletLog::insertAll($log);
    }

    /**
     * 获取分佣比例
     * @return string[]
     */
    protected function getLevelArr()
    {
        $system_basic = Cache::get('systemBasic');
        if (empty($system_basic)) {
            $info = Configure::find(1);
            $system_basic = json_decode($info['content'], true);
            Cache::set('systemBasic', $system_basic, 86400);
        }
        $level_arr = [
            $system_basic['comm_reward_0'] ?? '0.01',
            $system_basic['comm_reward_1'] ?? '0.01',
            $system_basic['comm_reward_2'] ?? '0.01',
            $system_basic['comm_reward_3'] ?? '0.01',
            $system_basic['comm_reward_4'] ?? '0.01',
            $system_basic['comm_reward_daili'] ?? '0.01',
        ];
        return $level_arr;
    }
}

