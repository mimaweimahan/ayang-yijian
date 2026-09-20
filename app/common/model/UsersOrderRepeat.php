<?php

namespace app\common\model;

use think\Model;

/**
 * 用户任务重置次数模型
 */
class UsersOrderRepeat extends Model
{
    protected $table = 'mod_users_order_repeat';

    /**
     * 增加用户重置次数记录
     * @param $uid
     * @return UsersOrderRepeat
     */
    public static function addUserRepeat($uid)
    {
        $todayStr = date('Ymd');
        return self::create([
            'uid' => $uid,
            'day' => $todayStr,
            'add_time' => time(),
        ]);
    }

    /**
     * @param $uid
     * @return int
     * @throws \think\db\exception\DbException
     */
    public static function getUserRepeatToday($uid)
    {
        $todayStr = date('Ymd');
        return self::where('day', $todayStr)
            ->where('uid', $uid)
            ->count();
    }
}