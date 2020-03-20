<?php

namespace app\modules\logis\modules\v1\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\modules\channel\modules\v1\models\Channel;

class TrackController extends Controller
{
    private $provider; // 当前使用的物流跟踪服务

    public function init()
    {
        parent::init();

        $this->provider = 'kdniao';
    }

    /**
     * 加载待订阅订单
     * @return int
     */
    public function actionLoadOrder()
    {
        // 获取Track实例
        $track = Yii::$app->getModule('logis/v1')->getTrack($this->provider);

        $channels = Channel::findAllChannel();
        foreach ($channels as $channel) {
            // 设置渠道进程
            Yii::$app->getModule('channel/v1')->processor = $channel['alias'];

            $loopLimit = 100; // 每个渠道一次限制循环100次防死循环，也可理解为顺利情况下查询最多10000个订单进行取消操作

            // 每100个订单循环
            while ($shippingOrders = $track->retrieve(100)) {
                $loopLimit--;
                if ($loopLimit < 0) break;

                try {
                    $track->identify($shippingOrders); // 识别并放入待订阅列
                } catch (\Exception $e) {
                    Yii::info(
                        "[{$channel['alias']}]" . $e->getMessage() . "\n" . json_encode($shippingOrders),
                        __METHOD__
                    );
                } catch (\Throwable $e) {
                    Yii::info(
                        "[{$channel['alias']}]" . $e->getMessage() . "\n" . json_encode($shippingOrders),
                        __METHOD__
                    );
                }
            }
        }

        return ExitCode::OK;
    }

    /**
     * 订阅物流跟踪
     * @return int
     */
    public function actionSubscribe()
    {
        // 获取Track实例
        $track = Yii::$app->getModule('logis/v1')->getTrack($this->provider);

        try {
            $track->subscribe(); // 订阅
        } catch (\Exception $e) {
            Yii::info($e->getMessage(), __METHOD__);
        } catch (\Throwable $e) {
            Yii::info($e->getMessage(), __METHOD__);
        }

        return ExitCode::OK;
    }

    /**
     * 物流状态同步
     * @return int
     */
    public function actionSynchronize()
    {
        // 获取Track实例
        $track = Yii::$app->getModule('logis/v1')->getTrack($this->provider);

        $channels = Channel::findAllChannel();
        foreach ($channels as $channel) {
            // 设置渠道进程
            Yii::$app->getModule('channel/v1')->processor = $channel['alias'];

            try {
                $track->synchronize(); // 同步
            } catch (\Exception $e) {
                Yii::info("[{$channel['alias']}]" . $e->getMessage(), __METHOD__);
            } catch (\Throwable $e) {
                Yii::info("[{$channel['alias']}]" . $e->getMessage(), __METHOD__);
            }
        }

        return ExitCode::OK;
    }
}