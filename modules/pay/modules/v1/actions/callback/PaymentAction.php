<?php
namespace app\modules\pay\modules\v1\actions\callback;

use Yii;
use yii\base\Action;
use app\modules\pay\modules\v1\events\ErrorEvent;

/**
 * 接收支付通知控制器独立动作类
 * Class PaymentAction
 * @package app\modules\pay\modules\v1\actions\callback
 */
class PaymentAction extends Action
{
    public function init()
    {
        parent::init();

        $routeParts = explode('/', $this->id);
        $this->id = array_shift($routeParts);
        $this->mask = implode('/', $routeParts);
    }

    private $mask;

    const EVENT_CALLBACK_SUCCESS = 'callback_success'; // 支付通知成功
    const EVENT_CALLBACK_FAILURE = 'callback_failure'; // 支付通知失败

    public function run()
    {
        // 开启事务，指定 READ_UNCOMMITTED 隔离级别（最低级别），因为涉及渠道公共使用的表
        $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_UNCOMMITTED);
        try {
            // 获取对应渠道以及支付方法
            list($channelAlias, $paymentAccount) = explode(',', Yii::$app->security->unmaskToken($this->mask));

            // 设置渠道进程
            Yii::$app->getModule('channel/v1')->processor = $channelAlias;

            // 获取支付实例
            $payment = Yii::$app->getModule('pay/v1')->getPayment($paymentAccount);

            // 执行支付通知处理
            $payment->run('callback');

            // 成功执行事件
            if ($this->hasEventHandlers(static::EVENT_CALLBACK_SUCCESS)) {
                $this->trigger(static::EVENT_CALLBACK_SUCCESS);
            }

            // 提交事务
            $task->commit();
        } catch (\Exception $e) { // 异常请查看日志

            // 回滚
            $task->rollBack();

            // 记录日志
            $errorMessage = $e->getMessage() . ';';
            $errorMessage .= Yii::$app->security->unmaskToken($this->mask) . ';';
            $errorMessage .= json_encode(Yii::$app->request->get()) . ';';
            $errorMessage .= json_encode(Yii::$app->request->post()) . ';';
            $errorMessage .= Yii::$app->request->getRawBody();
            $errorMessage .= PHP_EOL . $e->getTraceAsString();
            Yii::info($errorMessage, __METHOD__);

            // 失败执行事件
            if ($this->hasEventHandlers(static::EVENT_CALLBACK_FAILURE)) {
                $event = new ErrorEvent();
                $event->errorMessage = $e->getMessage();
                $this->trigger(static::EVENT_CALLBACK_FAILURE, $event);
            }
        } catch (\Throwable $e) { // 异常请查看日志

            // 回滚
            $task->rollBack();

            // 记录日志
            $errorMessage = $e->getMessage() . ';';
            $errorMessage .= Yii::$app->security->unmaskToken($this->mask) . ';';
            $errorMessage .= json_encode(Yii::$app->request->get()) . ';';
            $errorMessage .= json_encode(Yii::$app->request->post()) . ';';
            $errorMessage .= Yii::$app->request->getRawBody();
            $errorMessage .= PHP_EOL . $e->getTraceAsString();
            Yii::info($errorMessage, __METHOD__);

            // 失败执行事件
            if ($this->hasEventHandlers(static::EVENT_CALLBACK_FAILURE)) {
                $event = new ErrorEvent();
                $event->errorMessage = $e->getMessage();
                $this->trigger(static::EVENT_CALLBACK_FAILURE, $event);
            }
        }
    }
}