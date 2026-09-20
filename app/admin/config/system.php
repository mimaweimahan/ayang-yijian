<?php
return [
    'page_size' => 50,
    'system_name' => '排单系统',
    //后台目录菜单
    'admin_menu_list' => [
        [
            'name' => '系统设置',
            'icon' => '&#xe6ae;',
            'modular' => 'System',
            'is_open' => 1,
            'url' => '',
            'children' => [
                [
                    'name' => '管理员列表',
                    'url' => '/admin/System/manageList',
                    'icon' => '&#xe726;',
                    'is_open' => 2,
                    'children' => [
                        [
                            'name' => '管理员添加-编辑',
                            'url' => '/admin/System/manageAdd',
                            'is_open' => 0
                        ],
                        [
                            'name' => '管理员禁用',
                            'url' => '/admin/System/manageDisable',
                            'is_open' => 0
                        ],
                    ]
                ],
                [
                    'name' => '角色列表',
                    'url' => '/admin/System/groupList',
                    'icon' => '&#xe6e1;',
                    'is_open' => 2,
                    'children' => [
                        [
                            'name' => '角色添加-编辑',
                            'url' => '/admin/System/groupAdd',
                            'is_open' => 0
                        ],
                        [
                            'name' => '角色禁用',
                            'url' => '/admin/System/groupDisable',
                            'is_open' => 0
                        ],
                    ]
                ],
            ],
        ],
        [
            'name' => '站点设置',
            'modular' => 'System',
            'icon' => '&#xe6ce;',
            'is_open' => 1,
            'url' => '',
            'children' => [
                [
                    'name' => '基础数据',
                    'url' => '/admin/System/systemConfig',
                    'is_open' => 2,
                    'icon' => '',
                ],
                [
                    'name' => '配置保存',
                    'url' => '/admin/System/configSubmit',
                    'is_open' => 0,
                    'icon' => '',
                ],
                [
                    'name' => '多语言内容',
                    'url' => '/admin/System/homeInfo',
                    'is_open' => 2,
                    'icon' => '',
                ],
                [
                    'name' => '客服链接',
                    'url' => '/admin/System/customerLink',
                    'is_open' => 2,
                    'icon' => '',
                ],
//                [
//                    'name' => '银行卡类型',
//                    'url' => '/admin/System/bankType',
//                    'is_open' => 2,
//                    'icon' => '',
//                ],
                [
                    'name' => '协议',
                    'url' => '/admin/System/agreement',
                    'is_open' => 2,
                    'icon' => '',
                ],
                // [
                //     'name' => '公告1',
                //     'url' => '/admin/System/agreement2',
                //     'is_open' => 2,
                //     'icon' => '',
                // ],
                [
                    'name' => '公告列表',
                    'url' => '/admin/Notice/list',
                    'is_open' => 2,
                    'icon' => '',
                    'children' => [
                        [
                            'name' => '公告添加-编辑',
                            'url' => '/admin/Notice/add',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '公告删除',
                            'url' => '/admin/Notice/del',
                            'is_open' => 0,
                        ],
                    ]
                ]
            ]
        ],
        [
            'name' => '产品管理',
            'modular' => 'Product',
            'icon' => '&#xe6f6;',
            'is_open' => 1,
            'url' => '',
            'children' => [
                [
                    'name' => '产品列表',
                    'url' => '/admin/Product/list',
                    'is_open' => 2,
                    'icon' => '',
                    'children' => [
                        [
                            'name' => '产品添加-编辑',
                            'url' => '/admin/Product/add',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '产品删除',
                            'url' => '/admin/Product/del',
                            'is_open' => 0,
                        ],
                    ]
                ],
//                [
//                    'name' => '奖品设置',
//                    'url' => '/admin/Jiang/list',
//                    'is_open' => 2,
//                    'icon' => '',
//                    'children' => [
//                        [
//                            'name' => '奖品添加-编辑',
//                            'url' => '/admin/Jiang/add',
//                            'is_open' => 0,
//                        ],
//                        [
//                            'name' => '奖品删除',
//                            'url' => '/admin/Jiang/del',
//                            'is_open' => 0,
//                        ],
//                    ]
//                ],
                [
                    'name' => 'APP滚动',
                    'url' => '/admin/Product/appList',
                    'is_open' => 2,
                    'icon' => '',
                    'children' => [
                        [
                            'name' => 'APP添加-编辑',
                            'url' => '/admin/Product/appAdd',
                            'is_open' => 0,
                        ],
                        [
                            'name' => 'APP删除',
                            'url' => '/admin/Product/appDel',
                            'is_open' => 0,
                        ],
                    ]
                ],
            ]
        ],
        [
            'name' => '会员管理',
            'icon' => '&#xe6b8;',
            'modular' => 'Member',
            'is_open' => 1,
            'url' => '',
            'children' => [
                [
                    'name' => '会员等级列表',
                    'url' => '/admin/Member/levelList',
                    'icon' => '',
                    'is_open' => 2,
                    'children' => [
                        [
                            'name' => '会员等级添加-编辑',
                            'url' => '/admin/Member/levelAdd',
                            'is_open' => 0,
                        ],
                    ]
                ],
                [
                    'name' => '会员列表',
                    'url' => '/admin/Member/userList',
                    'icon' => '',
                    'is_open' => 2,
                    'children' => [
                        [
                            'name' => '会员添加-编辑',
                            'url' => '/admin/Member/userAdd',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '加扣款',
                            'url' => '/admin/Member/balanceChange',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '查看团队',
                            'url' => '/admin/Member/team',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '订单设置',
                            'url' => '/admin/Member/orderConfig',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '重置任务量',
                            'url' => '/admin/Member/resetOrder',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '状态修改',
                            'url' => '/admin/Member/userDisable',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '用户类型修改',
                            'url' => '/admin/Member/typeChange',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '密码修改',
                            'url' => '/admin/Member/changePass',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '删除用户',
                            'url' => '/admin/Member/deleteUser',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '提现状态修改',
                            'url' => '/admin/Member/changeWithdrawal',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '修改钱包地址',
                            'url' => '/admin/Member/bankEdit',
                            'is_open' => 0,
                        ],
                    ]
                ],
//                [
//                    'name' => '奖品记录',
//                    'url' => '/admin/Records/list',
//                    'is_open' => 2,
//                    'icon' => '',
//                    'children' => [
//                        [
//                            'name' => '奖品添加-编辑',
//                            'url' => '/admin/Records/add',
//                            'is_open' => 0,
//                        ],
//                        [
//                            'name' => '奖品删除',
//                            'url' => '/admin/Records/del',
//                            'is_open' => 0,
//                        ],
//                    ]
//                ],
                [
                    'name' => '下分记录',
                    'url' => '/admin/Member/rechargeList',
                    'icon' => '',
                    'is_open' => 2,
                ],
                [
                    'name' => '用户钱包',
                    'url' => '/admin/Member/bankList',
                    'icon' => '',
                    'is_open' => 2,
                ],
                [
                    'name' => '钱包修改记录',
                    'url' => '/admin/Bank/logList',
                    'icon' => '',
                    'is_open' => 2,
                ],
            ]
        ],
        [
            'name' => '财务管理',
            'icon' => '&#xe702;',
            'modular' => 'Finance',
            'is_open' => 1,
            'url' => '',
            'children' => [
                [
                    'name' => '提现订单',
                    'url' => '/admin/Finance/withdraw',
                    'icon' => '',
                    'is_open' => 2,
                    'children' => [
                        [
                            'name' => '提现审核',
                            'url' => '/admin/Finance/withdrawProcess',
                            'is_open' => 0,
                        ],
						[
						    'name' => '提现添加',
						    'url' => '/admin/Finance/withadd',
						    'is_open' => 0,
						],
						[
						    'name' => '提现删除',
						    'url' => '/admin/Finance/withdrawDel',
						    'is_open' => 0,
						],
						[
						    'name' => '设置金额',
						    'url' => '/admin/Member/userwt',
						    'is_open' => 0,
						],
                    ]
                ],
                [
                    'name' => '充值订单',
                    'url' => '/admin/Finance/recharge',
                    'icon' => '',
                    'is_open' => 2,
                    'children' => [
						[
						    'name' => '充值审核',
						    'url' => '/admin/Finance/rechargeProcess',
						    'is_open' => 0,
						],
                        [
                            'name' => '充值添加',
                            'url' => '/admin/Finance/investadd',
                            'is_open' => 0,
                        ],
                        [
                            'name' => '充值删除',
                            'url' => '/admin/Finance/rechargeDel',
                            'is_open' => 0,
                        ],
                    ]
                ],
                [
                    'name' => '钱包明细',
                    'url' => '/admin/Finance/wallet',
                    'icon' => '',
                    'is_open' => 2,
                ],
            ]
        ],
        [
            'name' => '订单管理',
            'modular' => 'Product',
            'icon' => '&#xe6f6;',
            'is_open' => 1,
            'url' => '',
            'children' => [
                [
                    'name' => '订单列表',
                    'url' => '/admin/Product/orderList',
                    'is_open' => 2,
                    'icon' => '',
                    'children' => []
                ],
				[
				    'name' => '派单列表',
				    'url' => '/admin/Product/grabOrdersa',
				    'is_open' => 2,
				    'icon' => '',
				    'children' => [
						[
						    'name' => '自动派单',
						    'url' => '/admin/Member/Automaticdispatcha',
						    'is_open' => 0,
						],
						[
						    'name' => '抢单删除',
						    'url' => '/admin/Member/grabordersdel',
						    'is_open' => 0,
						],
					]
				],
				[
				    'name' => '抢单列表',
				    'url' => '/admin/Product/grabOrders',
				    'is_open' => 2,
				    'icon' => '',
				    'children' => [
						[
						    'name' => '自动派单',
						    'url' => '/admin/Member/Automaticdispatch',
						    'is_open' => 0,
						],
						[
						    'name' => '取消派单',
						    'url' => '/admin/Member/Canceltheorder',
						    'is_open' => 0,
						],
						[
						    'name' => '预约爆单',
						    'url' => '/admin/Product/bdAdd',
						    'is_open' => 0,
						],
						[
						    'name' => '设置单数',
						    'url' => '/admin/Member/userOrderdesc',
						    'is_open' => 0,
						],
					    [
						    'name' => '填充重置单数',
						    'url' => '/admin/Member/userOrdertc',
						    'is_open' => 0,
						],
					    [
						    'name' => '填充非重置单数',
						    'url' => '/admin/Member/userOrdertd',
						    'is_open' => 0,
						],
						[
						    'name' => '抢单删除',
						    'url' => '/admin/Product/grabordersdel',
						    'is_open' => 0,
						],
						[
						    'name' => '先锋抢单',
						    'url' => '/admin/Member/userOrdervisa',
						    'is_open' => 0,
						],
					]
				],
				[
				    'name' => '爆单列表',
				    'url' => '/admin/Product/hotsales',
				    'is_open' => 2,
				    'icon' => '',
				    'children' => [
						[
						    'name' => '爆单编辑',
						    'url' => '/admin/Product/hotsalesadd',
						    'is_open' => 0,
						],
						[
						    'name' => '爆单删除',
						    'url' => '/admin/Product/hotsalesdel',
						    'is_open' => 0,
						],
					]
				],
            ]
        ],
    ],
];
