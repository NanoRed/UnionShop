<?php

namespace app\modules\logis\modules\v1\controllers;

use Yii;
use yii\web\Response;
use yii\filters\ContentNegotiator;
use app\modules\user\modules\v1\bases\AuthController;
use app\modules\logis\modules\v1\exceptions\LogisException;

class TrackController extends AuthController
{
    private $provider; // 当前使用的物流跟踪服务

    public function init()
    {
        parent::init();

        $this->provider = 'kdniao';
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::className(),
            'only' => ['info'],
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
                'application/xml' => Response::FORMAT_XML,
            ]
        ];
        return $behaviors;
    }

    /**
     * 查询订单物流信息
     * @return mixed
     */
    public function actionInfo()
    {
        try {
            // 验证参数
            if (Yii::$app->request->post('orderNo') == null) {
                throw new LogisException('参数错误');
            }

            // 订单号
            $orderNo = Yii::$app->request->post('orderNo');

            // 获取Track实例
            $track = Yii::$app->getModule('logis/v1')->getTrack($this->provider);

            // 返回数据
            return $track->trace($orderNo);

        } catch (LogisException $e) {
            Yii::$app->response->statusCode = 403;
            Yii::$app->response->statusText = urlencode($e->getMessage());
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (\Exception $e) {
            Yii::$app->response->statusCode = 500;
            Yii::$app->response->statusText = urlencode('无法获取物流信息');
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 500;
            Yii::$app->response->statusText = urlencode('无法获取物流信息');
            Yii::$app->response->data = Yii::$app->response->content = null;
        }
    }
}
