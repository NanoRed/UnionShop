<?php
namespace app\modules\pay\modules\v1\actions\callback;

use Yii;
use yii\base\Action;

/**
 * 渲染器
 * 接收加罩的HTML代码并返回给浏览器渲染
 * Class RenderAction
 * @package app\modules\pay\modules\v1\actions\callback
 */
class RenderAction extends Action
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
        return Yii::$app->security->unmaskToken($this->mask);
    }
}