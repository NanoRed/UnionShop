<?php

namespace app\modules\admin\modules\v1\bundles;

class DaterangepickerBundle extends \yii\web\AssetBundle
{
    public $sourcePath = '@app/modules/admin/modules/v1/assets';
    public $depends = [
        'yii\web\JqueryAsset',
    ];
    public $css = [
        'css/daterangepicker.css',
    ];
    public $js = [
        'js/moment.min.js',
        'js/daterangepicker.min.js',
    ];
}
