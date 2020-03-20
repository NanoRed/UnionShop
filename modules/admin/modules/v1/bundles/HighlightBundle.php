<?php

namespace app\modules\admin\modules\v1\bundles;

class HighlightBundle extends \yii\apidoc\templates\bootstrap\assets\HighlightBundle
{
    public $sourcePath = '@vendor/scrivo/highlight.php/styles';
    public $css = [
        'solarized-light.css'
    ];
}
