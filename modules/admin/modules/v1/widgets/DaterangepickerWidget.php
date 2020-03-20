<?php

namespace app\modules\admin\modules\v1\widgets;

use app\modules\admin\modules\v1\bundles\DaterangepickerBundle;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

class DaterangepickerWidget extends \yii\base\Widget
{
    public $model;
    public $attribute;
    public $options = [];

    public function run()
    {
        $hashId = '_' . uniqid(rand(1,100));
        $this->options = ArrayHelper::merge(
            [
                'id' => $hashId,
                'name' => basename(get_class($this->model)) . "[{$this->attribute}]",
                'autocomplete' => 'off',
            ],
            $this->options
        );
        DaterangepickerBundle::register($this->getView());
        $javaScript = <<<JS
$('#{$this->options['id']}').daterangepicker({
    opens: "left",
    autoUpdateInput: false,
    locale: {
        cancelLabel: '重置',
        applyLabel: '选择',
    }
});
$('#{$this->options['id']}').on('apply.daterangepicker', function(ev, picker) {
    $(this).val(picker.startDate.format('YYYY-MM-DD') + ' 至 ' + picker.endDate.format('YYYY-MM-DD')).trigger('change');
});
$('#{$this->options['id']}').on('cancel.daterangepicker', function(ev, picker) {
    $(this).val('').trigger('change');
});
JS;
        $attribute = $this->attribute;
        if (!empty($this->model->$attribute)) {
            $javaScript = <<<JS
{$javaScript}
$('#{$this->options['id']}').val('{$this->model->$attribute}');
JS;
        }
        $this->getView()->registerJs($javaScript);
        $this->options['type'] = 'text';
        return Html::tag('input', '', $this->options);
    }
}
