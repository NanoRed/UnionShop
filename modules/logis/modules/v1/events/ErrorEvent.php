<?php
namespace app\modules\logis\modules\v1\events;

use yii\base\Event;

/**
 * 异常事件
 * Class ErrorEvent
 * @package app\modules\logis\modules\v1\events
 */
class ErrorEvent extends Event
{
    public $errorMessage;
}