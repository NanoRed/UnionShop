<?php
namespace app\modules\login\modules\v1\actions\callback;

use Yii;
use yii\base\Action;

/**
 * 渠道登陆完成回调接口
 * Class LoginAction
 * @package app\modules\login\modules\v1\actions\callback
 */
class LoginAction extends Action
{
    public function init()
    {
        parent::init();

        $routeParts = explode('/', $this->id);
        $this->id = array_shift($routeParts);
        $this->mask = implode('/', $routeParts);
    }

    private $mask;

    public function run()
    {
        $channelAlias = Yii::$app->security->unmaskToken($this->mask); // 渠道代号
        Yii::$app->getModule('channel/v1')->processor = $channelAlias; // 设置渠道进程

        $login = Yii::$app
            ->getModule('channel/v1')
            ->processor
            ->getBehavior('login');

        return $login->loginCallback();
    }
}