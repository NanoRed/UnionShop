<?php
namespace app\modules\logis\modules\v1\actions\callback;

use Yii;
use yii\base\Action;
use app\modules\logis\modules\v1\events\ErrorEvent;

/**
 * 物流追踪订阅回调
 * Class TrackAction
 * @package app\modules\logis\modules\v1\actions\callback
 */
class TrackAction extends Action
{
    public function init()
    {
        parent::init();

        $routeParts = explode('/', $this->id);
        $this->id = array_shift($routeParts);
        $this->mask = implode('/', $routeParts);
    }

    private $mask;

    const EVENT_CALLBACK_SUCCESS = 'callback_success'; // 订阅数据回调成功
    const EVENT_CALLBACK_FAILURE = 'callback_failure'; // 订阅数据回调失败

    public function run()
    {
        // 使用READ_COMMITTED级别事务
        $task = Yii::$app->db->beginTransaction(\yii\db\Transaction::READ_COMMITTED);
        try {
            $provider = Yii::$app->security->unmaskToken($this->mask);
            $track = Yii::$app->getModule('logis/v1')->getTrack($provider); // 获取Track实例

            $track->callback();

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