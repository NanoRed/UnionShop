<?php

namespace app\modules\admin\modules\v1\controllers;

class RouteController extends \mdm\admin\controllers\RouteController
{
    public function init()
    {
        parent::init();

        $this->viewPath = '@vendor/mdmsoft/yii2-admin/views/' . $this->id;
    }
}
