<?php

namespace app\modules\admin\modules\v1\widgets;

use Yii;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use app\modules\admin\modules\v1\components\ChannelHelper;

class DropdownChannelListWidget extends \yii\base\Widget
{
    const PARAM_NAME = 'ChannelId'; // 传送参数名

    public $sessionKey;
    public $label = '渠道';
    public $options = [];

    public function run()
    {
        $hashId = '_' . uniqid(rand(1,100));
        $this->options = ArrayHelper::merge(
            [
                'id' => $hashId,
                'class' => 'form-control',
            ],
            $this->options
        );
        $paramName = static::PARAM_NAME;
        $this->getView()->registerJs(<<<JS
document.getElementById('{$hashId}').onchange = function () {
    window.location.href = '?{$paramName}=' + this.value;
};
JS
        );
        if (!isset($this->sessionKey)) {
            $this->sessionKey = (Yii::$app->controller)::className();
        }
        $selection = Yii::$app->session->get($this->sessionKey);
        $dropdownList = ChannelHelper::getAssignedChannel(Yii::$app->user->id);
        $dropdownList = ['' => '请选择'] + array_column($dropdownList, 'name', 'id');
        $dropdownList = Html::dropDownList(null, $selection, $dropdownList, $this->options);
        $label = Html::tag('span', $this->label, ['class' => 'input-group-addon']);
        return Html::tag('div', $label . $dropdownList, ['class' => 'input-group']);
    }
}
