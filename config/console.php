<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'createCensus' => 'app\command\Census',
        'userAddCredit' => 'app\command\UserAddCredit',
        'test' => 'app\command\Test',
        'monitor' => 'app\command\Monitor',
    ],
];
