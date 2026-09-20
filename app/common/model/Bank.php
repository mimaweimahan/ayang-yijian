<?php

namespace app\common\model;

use think\Model;

class Bank extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

}
