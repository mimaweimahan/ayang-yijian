<?php

namespace app\common\model;

use think\Model;

class WalletLog extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = false;

    public static $typeTxt = [
        1 => '后台充值',
        2 => '提现',
        3 => '交易',
        4 => '交易佣金',
        5 => '分佣',
        6 => '升级购买',
        7 => '升级奖励',
        8 => '返还本金',
        9 => '当前余额',
		10 => '用户充值',
    ];

    public function getTypeTxtAttr($val, $data)
    {
        return self::$typeTxt[$data['type']] ?? '未定义';
    }

}
