<?php

namespace app\modules\admin\modules\v1\widgets;

use yii\helpers\Html;

class TimeWidget extends \yii\base\Widget
{
    public function run()
    {
        $hashId = '_' . uniqid(rand(1,100));
        $this->getView()->registerJs(<<<JS
var date = new Date();
document.getElementById('{$hashId}').innerHTML = date.toLocaleDateString();
document.getElementById('{$hashId}').innerHTML += " " + date.toLocaleTimeString();
window.setInterval(function () {
    var date = new Date
    document.getElementById('{$hashId}').innerHTML = date.toLocaleDateString();
    document.getElementById('{$hashId}').innerHTML += " " + date.toLocaleTimeString();
}, 1000);
JS
        );
        return Html::tag('span', '', ['id' => $hashId]);
    }
}
