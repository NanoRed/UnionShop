<?php

return [
    'test_auth_menu1' => [
        'id' => 1,
        'name' => '权限管理',
        'parent' => NULL,
        'route' => NULL,
        'order' => 1,
        'data' => NULL
    ],
    'test_auth_menu2' => [
        'id' => 2,
        'name' => '路由列表',
        'parent' => 1,
        'route' => '/admin/v1/route/index',
        'order' => 1,
        'data' => NULL
    ],
    'test_auth_menu3' => [
        'id' => 3,
        'name' => '用户列表',
        'parent' => 1,
        'route' => '/admin/v1/user/index',
        'order' => 2,
        'data' => NULL
    ],
    'test_auth_menu4' => [
        'id' => 4,
        'name' => '角色列表',
        'parent' => 1,
        'route' => '/admin/v1/role/index',
        'order' => 3,
        'data' => NULL
    ],
    'test_auth_menu5' => [
        'id' => 5,
        'name' => '权限列表',
        'parent' => 1,
        'route' => '/admin/v1/permission/index',
        'order' => 4,
        'data' => NULL
    ],
    'test_auth_menu6' => [
        'id' => 6,
        'name' => '渠道列表',
        'parent' => 1,
        'route' => '/admin/v1/channel/index',
        'order' => 5,
        'data' => NULL
    ],
    'test_auth_menu7' => [
        'id' => 7,
        'name' => '规则列表',
        'parent' => 1,
        'route' => '/admin/v1/rule/index',
        'order' => 6,
        'data' => NULL
    ],
    'test_auth_menu8' => [
        'id' => 8,
        'name' => '菜单列表',
        'parent' => 1,
        'route' => '/admin/v1/menu/index',
        'order' => 7,
        'data' => NULL
    ],
    'test_auth_menu9' => [
        'id' => 9,
        'name' => '授予权限',
        'parent' => 1,
        'route' => '/admin/v1/assignment/index',
        'order' => 8,
        'data' => NULL
    ],
    'test_auth_menu10' => [
        'id' => 10,
        'name' => '订单管理',
        'parent' => NULL,
        'route' => NULL,
        'order' => 2,
        'data' => NULL
    ],
    'test_auth_menu11' => [
        'id' => 11,
        'name' => '订单列表',
        'parent' => 10,
        'route' => '/admin/v1/order/index',
        'order' => 1,
        'data' => NULL
    ],
];