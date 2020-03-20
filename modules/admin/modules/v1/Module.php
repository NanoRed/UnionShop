<?php

namespace app\modules\admin\modules\v1;

use Yii;

/**
 * admin_v1 module definition class
 */
class Module extends \mdm\admin\Module
{
    /**
     * @var string 页面标题
     */
    public $pageTitle = '聚合商城管理系统';

    /**
     * @var array 左侧菜单数据
     */
    public $leftMenu;

    public function init()
    {
        parent::init();

        // 注册authManager组件
        Yii::$app->set('authManager', [
            'class' => 'app\modules\admin\modules\v1\rbac\DbManager'
        ]);

        if (Yii::$app->id != 'UnionSystem-console') { // console不进行设置

            // 注册user组件
            Yii::$app->set('user', [
                'class' => get_class(Yii::$app->user),
                'identityClass' => 'mdm\admin\models\User',
                'enableAutoLogin' => false,
                'enableSession' => true,
                'loginUrl' => ['admin/v1/account/login']
            ]);

            // 指定菜单表和管理员表
            Yii::$container->set('mdm\admin\components\Configs',[
                'menuTable' => '{{%auth_menu}}',
                'userTable' => '{{%auth_user}}',
            ]);

            // 设置语言
            Yii::$app->language = 'zh-CN';

            // 设置字符集
            Yii::$app->charset = 'utf-8';

            // 设置home页
            Yii::$app->homeUrl = '/admin/v1/default/index';

            // 模块设置
            $this->layout = '@app/modules/admin/modules/v1/views/layouts/admin.php';
            $this->setViewPath('@app/modules/admin/modules/v1/views');

            // 顶部导航栏设置
            if (empty(Yii::$app->user->identity)) {
                $this->navbar = [];
            } else {
                $this->navbar = [
                    [
                        'dropDownCaret' => '&emsp;<span class="glyphicon glyphicon-edit"></span>',
                        'options' => ['class' => 'nav navbar-nav navbar-right'],
                        'items' => [
                            [
                                'label' => '你好，' . Yii::$app->user->identity->username,
                                'items' => [
                                    ['label' => '修改密码', 'url' => ['account/change-password']],
                                    ['label' => '注销登陆', 'url' => ['account/logout']],
                                ],
                                'options' => ['style' => 'margin-right: 16px'], // 移出少少
                            ]
                        ],
                        'params' => [],
                    ]
                ];
            }

            // 左侧菜单设置
            $menuItems = \mdm\admin\components\MenuHelper::getAssignedMenu(Yii::$app->user->id);
            $pathInfo = Yii::$app->request->getPathInfo();
            $currentRoute = explode('/', $pathInfo);
            $currentRoute = implode('/', array_slice($currentRoute, 0, 3));
            foreach ($menuItems as &$value) {
                if (isset($value['items'])) {
                    foreach ($value['items'] as &$value2) {
                        $value2['active'] = false;
                        if (isset($value2['url'])) {
                            foreach ($value2['url'] as $value3) {
                                if (strpos($value3, $currentRoute) !== false) {
                                    $value2['active'] = true;
                                }
                            }
                        }
                    }
                }
            }
            $this->leftMenu = ['items' => $menuItems];
        }
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = [
            'class' => 'mdm\admin\components\AccessControl',
            'allowActions' => [
                //'*' // 调试时解开注释
            ]
        ];
        return $behaviors;
    }
}
