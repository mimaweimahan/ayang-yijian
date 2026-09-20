<?php

namespace app\api\controller;

use app\common\model\Bank;
use app\common\model\BankLog;
use app\common\model\Level;
use app\common\model\Order;
use app\common\model\User;
use app\common\model\Withdraw;
use app\common\model\WalletLog;
use app\common\model\Sign;
use app\common\service\BankService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Log;

class Account extends Base
{
    //获取用户基本数据
    public function getUserInfo()
    {
        $domain = $this->request->domain();
        $uid = $this->request->user['id'];

        $field = 'user_order,username,nickname,level_id,credit_score,invite_code,deal_pass,bind_card,status,balance,trade_status,order_total,commission,is_test,today_profit,deal_date,jiangk';
        $info = User::field($field)->find($uid)->toArray();
        if ($info['deal_date'] != date('Ymd'))
            $info['today_profit'] = '0.00';
        $level_info = Level::find($info['level_id']);
		if($info['user_order'] > 0){
			$level_info['order_num'] = $info['user_order'];
		}
		
        if ($level_info['img'])
            $level_info['img'] = $domain . $level_info['img'];
        $info['level'] = $level_info;
        $info['today_yong'] = WalletLog::where('type', 5)->where('uid', $uid)->where('create_time', '>', strtotime(date('Y-m-d 00:00:00')))->sum('price') ?? '0.00';
        $info['ios'] = $this->config['system_basic']['ios'] ?? '';
        $info['usdt_krw'] = $this->config['system_basic']['usdt_krw'] ?? '';
        $firstday = date('Y-m-01 00:00:00');
        $info['android'] = $this->config['system_basic']['android'] ?? '';
        $d = date('d', strtotime("$firstday +1 month -1 day"));

        $arr = [];
//        for ($i = 1; $i <= $d; $i++) {
//            $res = (new Sign)
//                ->whereDay('create_time', date('Y-m-' . $i))->where('uid', $uid)
//                ->find();
//            if ($res) {
//                $arr[] = [$i, 1];
//            } else {
//                $arr[] = [$i, 2];
//            }
//        }
        //待支付订单金额
        $dfk = Order::where('uid', $uid)
            ->where('status', 1)
            ->sum('price');
        $info['balance'] = bcsub($info['balance'], $dfk, 2);
        $info['team_comm']  = WalletLog::where('uid',$uid)->where('type',5)->sum('price');
        $info['tx']  = Withdraw::where('uid',$uid)->where('status',1)->sum('price');
        $info['days'] = $arr;
        // 邀请注册链接：前后端分离时用 FRONTEND_INVITE_BASE（.env），否则回退为当前请求域名；路径对齐 newfront 注册页
        $invitePath = '/#/pages/userPages/register/register?inviteCode=' . rawurlencode((string) $info['invite_code']);
        $frontBase = (string) config('app.frontend_invite_base', '');
        $info['invite_url'] = $frontBase !== ''
            ? rtrim($frontBase, '/') . $invitePath
            : (request()->domain() . $invitePath);
        //是否可以修改绑定地址
        $info['can_modify_bind_bank'] = config('user.can_modify_bind_bank');
        $this->result(true, '操作成功', $info);
    }

    //签到
    public function sign()
    {
        $domain = $this->request->domain();
        $uid = $this->request->user['id'];

        $field = 'order_total,level_id';
        $info = User::field($field)->find($uid)->toArray();

        $level_info = Level::find($info['level_id']);
        if ($info['order_total'] < $level_info['order_num']) {
            $this->result(false, '请联系客服');
        }
        $re = (new Sign)
            ->whereDay('create_time')->where('uid', $uid)
            ->find();
        if ($re) $this->result(false, '请联系客服');
        $arr = (new Sign)->save(['uid' => $uid]);
        $this->result(true, '操作成功');
    }

    //更改密码
    public function changePass()
    {
        $old_pass = $this->request->post('old_pass', '', 'trim');
        $new_pass = $this->request->post('new_pass', '', 'trim');
        $type = $this->request->post('type', 1, 'intval');//类型1-登录密码2-交易密码
        if (!$old_pass || !$new_pass)
            $this->result(false, '参数有误');
        $uid = $this->request->user['id'];
        $user_info = User::field('password,deal_pass')->find($uid);
        if ($type == 1) {
            if ($old_pass != $user_info['password'])
                $this->result(false, '旧密码有误');
            $data['password'] = $new_pass;
        } else {
            if ($old_pass != $user_info['deal_pass'])
                $this->result(false, '旧密码有误');
            $data['deal_pass'] = $new_pass;
        }
        $res = User::update($data, ['id' => $uid]);
        if ($res)
            $this->result(true);
        else
            $this->result(false, '操作失败');
    }

    //获取银行信息
    public function getCard()
    {
        $uid = $this->request->user['id'];
        //检查用户钱包地址
        // BankService::checkUserAddress($uid);
        $info = Bank::where(['uid' => $uid])->find();
        if (!$info) {
            $info = ['name' => ''];
        } else {
            $info = $info->toArray();
        }
        $bank_list = $this->config['system_bank'];
        foreach ($bank_list as &$item) {
            if ($info['name'] == $item['name_vi'] || $info['name'] == $item['name_en']) {
                $item['check'] = true;
            } else {
                $item['check'] = false;
            }
        }
        $this->result(true, '操作成功', $info, ['bank_list' => $bank_list]);
    }

    //设置银行信息
    public function setCard()
    {
        $name = $this->request->post('name', '', 'trim');
        $card_no = $this->request->post('card_no', '', 'trim');
        $account_name = $this->request->post('account_name', '', 'trim');
        $account_open = $this->request->post('account_open_name', '', 'trim');
        $mobile = $this->request->post('mobile', '', 'trim');
        $wise = $this->request->post('wise', '', 'trim');
        $revolut = $this->request->post('revolut', '', 'trim');
        $usdt = $this->request->post('usdt', '', 'trim');
        
        $type = $this->request->post('type', 1, 'trim');
        $can_modify_bind_address = config('user.can_modify_bind_bank'); //是否可以修改绑定地址
        // if (!$card_no || !$type ||!$account_name||!$name )
        //     $this->result(false, '参数有误');
        $uid = $this->request->user['id'];
        $data['name'] = $name;
        $data['card_no'] = $card_no;
        $data['account_name'] = $account_name;
        $data['account_open_name'] = $account_open;
        $data['mobile'] = $mobile;
        
        $data['wise'] = $wise;
        $data['revolut'] = $revolut;
        $data['usdt'] = $usdt;
        
        $data['type'] = $type;
        $bank = Bank::where(['uid' => $uid])->find();
        $bankLog = [];
        
        if ($bank) {
            // if ($bank['card_no'] == $card_no) {
            //     $this->result(true);
            // }
            if ($can_modify_bind_address) {
                $res = Bank::update($data, ['id' => $bank['id']]);
            } else {
                $res = false;
            }
            $bankLog = [
                'uid' => $uid,
                'before' =>json_encode($bank),
                'after' => $card_no,
                'action_type' => 0,
                'action_id' => $uid,
                'action_time' => time(),
                'action_ip' => request()->ip(),
                'remark' => '修改地址'
            ];
        } else {
            $data['uid'] = $uid;
            $data['create_time'] = time();
            $res = Bank::insert($data);
            User::update(['bind_card' => 1], ['id' => $uid]);
            $bankLog = [
                'uid' => $uid,
                'before' => '',
                'after' => $card_no,
                'action_type' => 0,
                'action_id' => $uid,
                'action_time' => time(),
                'action_ip' => request()->ip(),
                'remark' => '绑定地址'
            ];
        }
       
        if ($res) {
             $bank = Bank::where(['uid' => $uid])->find();
             $bankLog['after']=json_encode($bank);
            BankLog::create($bankLog);
            $this->result(true);
        } else
            $this->result(false, '操作失败');
    }
	//充值
	public function invest(){
		$uid = $this->request->user['id'];
		$price = false;
		$price_numa = $this->request->post('price', 0);
		if($price_numa > 0){
			$price = $price_numa;
		}
		$price_numb = $this->request->post('price_num', 0);
		if($price_numb > 0){
			$price = $price_numb;
		}
		
		$web_time = $this->request->post('web_time', '', 'trim');
		$web_time = strtotime($web_time);
		if (!$price || !$uid || !$web_time){
			$this->result(false, 'false');
		}
		$price_pre = Db::name('user')->where('id',$uid)->value('balance');
		$data = [
			'uid' => $uid,
			'price' => $price,
			'admin_id' => '2',
			'price_pre' => $price_pre,
			'create_time' => $web_time,
			'status' => '4',
		];
		Db::name('recharge')->insert($data);
		$this->result(true, '操作成功');
	}
    //申请提现
    public function setWithData()
    {
        $price = $this->request->post('price', 0);
        $pass = $this->request->post('pass', '', 'trim');
        $type = $this->request->post('type', 1, 'trim');
		$langs = $this->request->post('langs', '', 'trim');
        $web_time = $this->request->post('web_time', '', 'trim');
        if (!$price || !$pass || !$web_time)
            $this->result(false, 'Incorrect parameters');
        if ($web_time) {
            $web_time = strtotime($web_time);
            $diff_time = time() - $web_time;
        } else {
            $web_time = 0;
            $diff_time = 0;
        }
        $open_date = $this->config['system_basic']['with_open_time'] ?? '';
        $close_date = $this->config['system_basic']['with_close_time'] ?? '';
        $start_time = strtotime($open_date);
        $close_time = strtotime($close_date);
        if ($start_time) {
            $start_time -= $diff_time;
            if ($start_time > $web_time)
                $this->result(false, '允许提现时间为：', ['var' => $open_date . ' - ' . $close_date]);
        }
        if ($close_time) {
            $close_time -= $diff_time;
            if ($close_time < $web_time)
                $this->result(false, '允许提现时间为：', ['var' => $open_date . ' - ' . $close_date]);
        }
		$startTime = date('Y-m-d 00:00:00');
		$endTime = date('Y-m-d 23:59:59');
		$uid = $this->request->user['id'];
		$posts = Db::name('withdraw')
			->where('uid',$uid)
			->whereTime('create_time', '>=', $startTime)
			->whereTime('create_time', '<=', $endTime)
			->count('id');
        if($posts == 1){
			$this->result(false, '每天只能取款一次');
		}
        $user_info = User::alias('u')->leftJoin('mod_level l', 'u.level_id=l.id')->where(['u.id' => $uid])
            ->field('u.user_order,u.credit_score,u.deal_pass,u.balance,u.freeze_balance,u.status,u.order_total,u.withdrawal_status,
            l.withdraw_min,l.withdraw_max,l.withdraw_rate,l.order_num')->find();
			
        if ($user_info['status'] != 1){
			$this->result(false, '该账号被禁用了，请联系客服');
		}
            
        if ($user_info['withdrawal_status'] != 1){
			$this->result(false, env('user.withdrawal_disable_msg', '需要重置一组任务才能提取资金'));
		}
            
        if ($user_info['deal_pass'] != $pass){
			if($langs == 'zh'){//中文
				$langs = '提款密码错误';
			}
			if($langs == 'pt'){//葡萄牙语
				$langs = 'Senha de levantamento errada';
			}
			if($langs == 'nl'){//荷兰语
				$langs = 'Verkeerd opnamewachtwoord';
			}
			if($langs == 'he'){//希伯来语
				$langs = 'סיסמת משיכה שגויה';
			}
			if($langs == 'fr'){//法语
				$langs = 'Mauvais mot de passe de retrait';
			}
			if($langs == 'es'){//西班牙语
				$langs = 'Contraseña de retiro incorrecta';
			}
			if($langs == 'en'){//英语
				$langs = 'Wrong withdrawal password';
			}
			if($langs == 'de'){//德语
				$langs = 'Falsches Auszahlungspasswort';
			}
			if($langs == 'ar'){//阿拉伯语
				$langs = 'خطأ في سحب كلمة المرور';
			}
			$this->result(false, $langs);
		}
            
        if ($user_info['credit_score'] < 1000){
			$this->result(false, '信用分低于100');
		}
            
		if($user_info['user_order'] == 0){
			if($langs == 'zh'){//中文
				$langs = '限制-未完成指定单数-进度为';
			}
			if($langs == 'pt'){//葡萄牙语
				$langs = 'Limite - Número ímpar especificado não concluído - Progresso é';
			}
			if($langs == 'nl'){//荷兰语
				$langs = 'Limiet - Opgegeven oneven getal niet voltooid - Voortgang is';
			}
			if($langs == 'he'){//希伯来语
				$langs = 'מגבלה - מספר אי זוגי שצוין לא הושלם - ההתקדמות היא';
			}
			if($langs == 'fr'){//法语
				$langs = 'Limite - Le nombre impair spécifié n est pas complété - La progression est';
			}
			if($langs == 'es'){//西班牙语
				$langs = 'Límite - Número impar especificado no completado - El progreso es';
			}
			if($langs == 'en'){//英语
				$langs = 'Limit - Unfinished specified number of items - Progress is';
			}
			if($langs == 'de'){//德语
				$langs = 'Limit – Angegebene ungerade Zahl nicht abgeschlossen – Fortschritt ist';
			}
			if($langs == 'ar'){//阿拉伯语
				$langs = 'الحد - الرقم الفردي المحدد غير مكتمل - التقدم جاري';
			}
			if ($user_info['order_total'] < $user_info['order_num']){
			    $this->result(false, $langs, ['var' => $user_info['order_total'] . '/' . $user_info['order_num']]);
			}
				
		}	
		if($user_info['order_total'] < $user_info['order_num']){
			if($langs == 'zh'){//中文
				$langs = '限制-未完成指定单数-进度为';
			}
			if($langs == 'pt'){//葡萄牙语
				$langs = 'Limite - Número ímpar especificado não concluído - Progresso é';
			}
			if($langs == 'nl'){//荷兰语
				$langs = 'Limiet - Opgegeven oneven getal niet voltooid - Voortgang is';
			}
			if($langs == 'he'){//希伯来语
				$langs = 'מגבלה - מספר אי זוגי שצוין לא הושלם - ההתקדמות היא';
			}
			if($langs == 'fr'){//法语
				$langs = 'Limite - Le nombre impair spécifié n est pas complété - La progression est';
			}
			if($langs == 'es'){//西班牙语
				$langs = 'Límite - Número impar especificado no completado - El progreso es';
			}
			if($langs == 'en'){//英语
				$langs = 'Limit - Unfinished specified number of items - Progress is';
			}
			if($langs == 'de'){//德语
				$langs = 'Limit – Angegebene ungerade Zahl nicht abgeschlossen – Fortschritt ist';
			}
			if($langs == 'ar'){//阿拉伯语
				$langs = 'الحد - الرقم الفردي المحدد غير مكتمل - التقدم جاري';
			}

		    $this->result(false, $langs, ['var' => $user_info['order_total'] . '/' . $user_info['order_num']]);
		
				
		}
			
			
        if ($price < $user_info['withdraw_min']){
			if($langs == 'zh'){//中文
				$langs = '提现金额小于';
			}
			if($langs == 'pt'){//葡萄牙语
				$langs = 'O valor da retirada é inferior a';
			}
			if($langs == 'nl'){//荷兰语
				$langs = 'Opnamebedrag is minder dan';
			}
			if($langs == 'he'){//希伯来语
				$langs = 'סכום המשיכה נמוך מ';
			}
			if($langs == 'fr'){//法语
				$langs = 'Le montant du retrait est inférieur à';
			}
			if($langs == 'es'){//西班牙语
				$langs = 'El monto de retiro es menor a';
			}
			if($langs == 'en'){//英语
				$langs = 'Withdrawal amount is less than';
			}
			if($langs == 'de'){//德语
				$langs = 'Der Auszahlungsbetrag ist kleiner als';
			}
			if($langs == 'ar'){//阿拉伯语
				$langs = 'مبلغ السحب أقل من';
			}
			$this->result(false, $langs, ['var' => $user_info['withdraw_min']]);
		}
            
        if ($price > $user_info['withdraw_max']){
			if($langs == 'zh'){//中文
				$langs = '提现金额大于';
			}
			if($langs == 'pt'){//葡萄牙语
				$langs = 'O valor da retirada é superior a';
			}
			if($langs == 'nl'){//荷兰语
				$langs = 'Opnamebedrag is groter dan';
			}
			if($langs == 'he'){//希伯来语
				$langs = 'סכום המשיכה גדול מ';
			}
			if($langs == 'fr'){//法语
				$langs = 'Le montant du retrait est supérieur à';
			}
			if($langs == 'es'){//西班牙语
				$langs = 'El monto del retiro es mayor que';
			}
			if($langs == 'en'){//英语
				$langs = 'Withdrawal amount is greater than';
			}
			if($langs == 'de'){//德语
				$langs = 'Der Auszahlungsbetrag ist größer als';
			}
			if($langs == 'ar'){//阿拉伯语
				$langs = 'مبلغ السحب أكبر من';
			}
			$this->result(false, $langs, ['var' => $user_info['withdraw_max']]);
		}
            
        $fee = round($price * $user_info['withdraw_rate'], 2);//手续费
        $dfk = Order::where('uid', $uid)
            ->where('status', 1)
            ->sum('price');
        $user_info['balance'] = bcsub($user_info['balance'], $dfk, 2);
        $real_price = round($price + $fee, 2);
        
        if ($real_price > $user_info['balance']){
			if($langs == 'zh')$langs = '提现金额大于可用余额';//中文
			if($langs == 'pt')$langs = 'O valor do levantamento é superior ao saldo disponível';//葡萄牙语
			if($langs == 'nl')$langs = 'Het opnamebedrag is groter dan het beschikbare saldo';//荷兰语
			if($langs == 'he')$langs = 'סכום המשיכה גדול מהיתרה הזמינה';//希伯来语
			if($langs == 'fr')$langs = 'Le montant du retrait est supérieur au solde disponible';//法语
			if($langs == 'es')$langs = 'El monto del retiro es mayor que el saldo disponible';//西班牙语
			if($langs == 'en')$langs = 'The withdrawal amount is greater than the available balance';//英语
			if($langs == 'de')$langs = 'Der Auszahlungsbetrag ist höher als das verfügbare Guthaben';//德语
			if($langs == 'ar')$langs = 'يمبلغ السحب أكبر من الرصيد المتوفر';//阿拉伯语
            $this->result(false, $langs); 
        }
           
        //检查用户钱包地址
        // BankService::checkUserAddress($uid);
        $bank_info = Bank::where(['uid' => $uid])->find();
        // if (!$bank_info)
        //     $this->result(false, '请先完善提款信息');
        
        // if($type==1&&(empty($bank_info['name'])||empty($bank_info['account_name'])||empty($bank_info['card_no']))){//银行卡
        //         $this->result(false, '请先完善提款信息');
        // }
        // if($type==2&&empty($bank_info['wise'])){//Wise
        //     $this->result(false, '请先完善提款信息');
        // }
        // if($type==3&&empty($bank_info['revolut'])){//Revolut
        //   $this->result(false, '请先完善提款信息');
        // }
        if($type==4&&empty($bank_info['usdt'])){//usdt
			if($langs == 'zh')$langs = '请先完善提款信息';//中文
			if($langs == 'pt')$langs = 'Por favor, preencha primeiro as informações de levantamento';//葡萄牙语
			if($langs == 'nl')$langs = 'Vul eerst de opnamegegevens in';//荷兰语
			if($langs == 'he')$langs = 'אנא מלא את פרטי המשיכה תחילה';//希伯来语
			if($langs == 'fr')$langs = 'Veuillez d abord compléter les informations de retrait';//法语
			if($langs == 'es')$langs = 'Por favor, complete primero la información de retiro';//西班牙语
			if($langs == 'en')$langs = 'Please complete the withdrawal information first';//英语
			if($langs == 'de')$langs = 'Bitte füllen Sie zuerst die Auszahlungsinformationen aus';//德语
			if($langs == 'ar')$langs = 'يرجى إكمال معلومات السحب أولاً';//阿拉伯语
				
			
            $this->result(false, $langs);
        }    
        Db::startTrans();
        try {
            $data['uid'] = $uid;
            $data['bank_id'] = $bank_info['id'];
            // if($type==1){//银行卡
            //     $data['content'] =json_encode(['name'=>$bank_info['name'],'account_name'=>$bank_info['account_name'],'card_no'=>$bank_info['card_no']]);
            // }
            // if($type==2){//Wise
            //     $data['content'] =json_encode(['wise'=>$bank_info['wise']]);
            // }
            // if($type==3){//Revolut
            //     $data['content'] =json_encode(['revolut'=>$bank_info['revolut']]);
            // }
            if($type==4){//usdt
                $data['content'] =json_encode(['usdt'=>$bank_info['usdt']]);
            }
            $data['price'] = intval($price);
            $data['fee'] = $fee;
            $data['price_pre'] = $user_info['balance'];
            Withdraw::create($data);
            User::where(['id' => $uid])->inc('freeze_balance', $real_price)->dec('balance', $real_price)->update();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('提现提交失败-' . $e->getMessage() . ":" . $e->getLine());
            $this->result(false, '系统繁忙，请重试');
        }
        $this->result(true);
    }

    //提现列表
    public function getWithList()
    {
        $uid = $this->request->user['id'];
        $web_time = $this->request->post('web_time', '', 'trim');
        if ($web_time)
            $diff_time = time() - strtotime($web_time);
        else
            $diff_time = 0;
        $page = $this->request->post('page', 1, 'intval');
        $list = Db::name('Withdraw')->where(['uid' => $uid])->order('id desc')->paginate(['list_rows' => 10, 'page' => $page])->toArray();
        foreach ($list['data'] as &$item) {
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time'] - $diff_time);
            if ($item['handle_time'])
                $item['handle_time'] = date('Y-m-d H:i:s', $item['handle_time'] - $diff_time);
        }
        $this->result(true, '操作成功', $list['data'], ['total' => $list['total'], 'last_page' => $list['last_page']]);
    }

    //钱包记录列表
    public function getWalletList()
    {
        $uid = $this->request->user['id'];
        $web_time = $this->request->post('web_time', '', 'trim');
        if ($web_time)
            $diff_time = time() - strtotime($web_time);
        else
            $diff_time = 0;
        $page = $this->request->post('page', 1, 'intval');
        $scope = $this->request->post('scope', '', 'trim');
        $walletQuery = Db::name('WalletLog')->where(['uid' => $uid]);
        // 前端「佣金记录」：仅交易佣金、多级返佣（与业务约定一致时可再扩展 type）
        if ($scope === 'commission') {
            $walletQuery->whereIn('type', [4, 5]);
        }
        $list = $walletQuery->order('id desc')->paginate(['list_rows' => 10, 'page' => $page])->toArray();
        foreach ($list['data'] as &$item) {
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time'] - $diff_time);
            if ($item['status'] == 2)
                $item['price'] = -$item['price'];
            $item['price_after'] = $item['price_pre'] + $item['price'];
            $item['title'] = '';
            if ($item['type'] == 1) {
                $item['title'] = Lang::get('充值余额');
            } elseif ($item['type'] == 2) {
                $item['title'] = Lang::get('提现');
            } elseif ($item['type'] == 3) {
                $info = Db::name('Goods')->where(['id' => $item['extra_id']])->field('name')->find();
                $item['title'] = Lang::get('生成订单') . $info['name'] ?? '';
            } elseif ($item['type'] == 4) {
                $info = Db::name('Goods')->where(['id' => $item['extra_id']])->field('name')->find();
                $item['title'] = Lang::get('交易佣金') . $info['name'] ?? '';
            } elseif ($item['type'] == 5) {
                if ($item['explain'][0] == 1) {
                    $item['title'] = Lang::get('一级返佣');
                } elseif ($item['explain'][0] == 2) {
                    $item['title'] = Lang::get('二级返佣');
                } elseif ($item['explain'][0] == 2) {
                    $item['title'] = Lang::get('三级返佣');
                } elseif ($item['explain'][0] == 2) {
                    $item['title'] = Lang::get('四级返佣');
                } else {
                    $item['title'] = Lang::get('五级返佣');
                }
            } elseif ($item['type'] == 6) {
                $item['title'] = Lang::get('等级购买升级至') . $item['extra_id'];
            } elseif ($item['type'] == 7) {
                $item['title'] = Lang::get('等级升级奖励');
            } elseif ($item['type'] == 8) {
                $info = Db::name('Goods')->where(['id' => $item['extra_id']])->field('name')->find();
                $item['title'] = Lang::get('返还本金') . $info['name'] ?? '';
            } elseif ($item['type'] == 9) {
                $item['title'] = Lang::get('当前余额');
            }
        }
        $this->result(true, '操作成功', $list['data'], ['total' => $list['total'], 'last_page' => $list['last_page']]);
    }

    /**
     * 后台充值记录
     * @return void
     * @throws \think\db\exception\DbException
     */
    public function getRechargeList()
    {
        $uid = $this->request->user['id'];
        $web_time = $this->request->post('web_time', '', 'trim');
        if ($web_time)
            $diff_time = time() - strtotime($web_time);
        else
            $diff_time = 0;
        $page = $this->request->post('page', 1, 'intval');
        $list = Db::name('recharge')
            ->where(['uid' => $uid,'status'=>1])
            ->where('beizhu', null)
            ->order('id desc')
            ->paginate(['list_rows' => 10, 'page' => $page])
            ->toArray();
        foreach ($list['data'] as &$item) {
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time'] - $diff_time);
        }
        $this->result(true, '操作成功', $list['data'], ['total' => $list['total'], 'last_page' => $list['last_page']]);
    }

}
