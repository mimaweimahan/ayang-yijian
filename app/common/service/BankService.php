<?php

namespace app\common\service;

use app\common\model\Bank;
use app\common\model\BankLog;

class BankService
{
    /**
     * 检查用户钱包地址
     * @param $uid
     * @return void
     */
    public static function checkUserAddress($uid)
    {
        $check_user_address = config('user.check_user_address');
        if ($check_user_address == false) {
            //不做处理
        } else {
            $info = Bank::where(['uid' => $uid])->find();
            //查询最后一次修改记录
            $user_last_log = BankLog::where('uid', $uid)->order('id', 'desc')->find();
            if ($user_last_log) {
                $user_last_address = $user_last_log['after'];
                $info_address = $info['card_no'];
                if ($info_address != $user_last_address) {
                    Bank::where('id', $info['id'] ?? '')->update([
                        'card_no' => $user_last_address
                    ]);
                }
            }
        }

    }
}