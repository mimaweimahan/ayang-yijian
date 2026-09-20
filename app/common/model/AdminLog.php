<?php

namespace app\common\model;

use think\facade\Session;
use think\Model;

class AdminLog extends Model
{
    protected $autoWriteTimestamp = false;

    /** 写入日志
     * @param $action
     * @param $content
     * @return int|string
     */
    public static function write($action = '行为', $content = '内容描述')
    {

        return self::insert([
            'geoip' => request()->ip(),
            'action' => $action,
            'content' => $content,
            'username'=> Session::get("adminInfo.username"),
            'create_at' => date('Y-m-d H:i:s')
        ]);
    }
}
