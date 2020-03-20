<?php

namespace app\modules\admin\modules\v1\bundles;

class AssetBundle extends \yii\apidoc\templates\bootstrap\assets\AssetBundle
{
    public function init()
    {
        parent::init();

        $this->depends = [
            'yii\web\JqueryAsset',
            'yii\bootstrap\BootstrapAsset',
            'yii\bootstrap\BootstrapPluginAsset',
            'app\modules\admin\modules\v1\bundles\HighlightBundle', // 重载此Bundle，第三方的文件命错误
        ];
    }
}
