<?php

namespace app\common\model;

use think\Model;

class Configure extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = false;
    protected $updateTime = 'update_time';

}