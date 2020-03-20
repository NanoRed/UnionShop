<?php

namespace app\modules\user\modules\v1\bases;

use yii\filters\Cors;
use yii\base\Controller;
use yii\helpers\ArrayHelper;

/**
 * 跨域处理继承类
 * Class CorsController
 * @package app\modules\user\modules\v1\bases
 */
class CorsController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors = ArrayHelper::merge([
            'corsFilter' => [ // ajax请求跨域处理
                'class' => Cors::className(),
                'cors' => [
                    'Origin' => [
                        'http://localhost',
                        'https://api.sample.com',
                        'https://app.sample.com',
                    ],
                    'Access-Control-Request-Headers' => [
                        'X-Api-Key',
                        'X-Unionsystem-Channel',
                        'X-Unionsystem-Referer',
                        'X-Unionsystem-Os',
                        'X-Unionsystem-List-Page',
                        'X-Unionsystem-List-Pagesize',
                    ],
                    'Access-Control-Expose-Headers' => [
                        'X-Unionsystem-Script',
                    ]
                ],
            ],
        ], $behaviors);

        return $behaviors;
    }
}
