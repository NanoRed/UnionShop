<?php

namespace app\modules\admin\modules\v1\controllers;

class RuleController extends \mdm\admin\controllers\RuleController
{
    public function init()
    {
        parent::init();

        $this->viewPath = '@vendor/mdmsoft/yii2-admin/views/' . $this->id;
    }
}
