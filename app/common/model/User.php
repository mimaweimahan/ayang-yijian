<?php

namespace app\common\model;

use think\Model;

class User extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    /**
     * 获取任务重置次数
     * @return \think\model\relation\HasMany
     */
    public function getUserOrderRepeatCount()
    {
        return $this->hasMany(UsersOrderRepeat::class, 'uid', 'id');
    }
}

