<?php

namespace app\common\model;

use think\Model;

class Role extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    public function getOpenTxtAttr($val, $data)
    {
        if ($data['status'] == 1)
            return '禁用';
        else
            return '启用';
    }

}
