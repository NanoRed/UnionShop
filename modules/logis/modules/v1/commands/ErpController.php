<?php

namespace app\modules\logis\modules\v1\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\modules\channel\modules\v1\models\Channel;

class ErpController extends Controller
{
    private $provider; // 当前使用的ERP服务

    public function init()
    {
        parent::init();

        $this->provider = 'wangdiantong';
    }

    /**
     * 发货定时任务
     * @return int
     * @throws \Exception
     */
    public function actionDispatch()
    {
        // 获取ERP实例
        $erp = Yii::$app->getModule('logis/v1')->getErp($this->provider);

        $channels = Channel::findAllChannel();
        foreach ($channels as $channel) {
            // 设置渠道进程
            Yii::$app->getModule('channel/v1')->processor = $channel['alias'];

            $loopLimit = 100; // 每个渠道一次限制循环100次防死循环，也可理解为顺利情况下查询最多10000个订单进行取消操作

            // 每100个订单循环
            while ($shippingOrders = $erp->retrieve(100)) {
                $loopLimit--;
                if ($loopLimit < 0) break;

                try {
                    $erp->dispatch($shippingOrders); // 发货
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
     * 同步数据定时任务
     * @return int
     */
    public function actionSynchronize()
    {
        // 获取ERP实例
        $erp = Yii::$app->getModule('logis/v1')->getErp($this->provider);

        $channels = Channel::findAllChannel();
        foreach ($channels as $channel) {
            // 设置渠道进程
            Yii::$app->getModule('channel/v1')->processor = $channel['alias'];

            try {
                $erp->synchronize(); // 同步
            } catch (\Exception $e) {
                Yii::info("[{$channel['alias']}]" . $e->getMessage(), __METHOD__);
            } catch (\Throwable $e) {
                Yii::info("[{$channel['alias']}]" . $e->getMessage(), __METHOD__);
            }
        }

        return ExitCode::OK;
    }
}