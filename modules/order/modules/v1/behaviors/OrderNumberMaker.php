<?php

namespace app\modules\order\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use yii\base\InvalidConfigException;

/**
 * 唯一订单号生成器
 * 注意：只适用于10位或以上订单号
 * 格式：时间（4位年份3位年日5位当天秒数）+随机数（3位）
 * 说明：15位或以上可保证订单号不含字母，否则会进制压缩产生字母
 * Class OrderNumberMaker
 * @package app\modules\order\modules\v1\behaviors
 */
class OrderNumberMaker extends Behavior
{
    /**
     * @param string $label 池标签，会根据此标签分池
     * @param int $digit 订单位数
     * @return string
     * @throws InvalidConfigException
     */
    public function getOrderNumber($label, $digit = 15)
    {
        if ($digit < 10) {
            throw new InvalidConfigException(__METHOD__ . ' 此唯一编号生成器只适用于10位或以上订单号');
        }

        do {
            if (isset($loopCount) && $loopCount > 10) { // 已循环超过10次，失败抛出错误
                throw new InvalidConfigException(__METHOD__ . ' 订单生成失败，请稍后再试');
            }

            // 时间序列 4位年，3位当年第几日，5位当天第几秒 固定共12位
            $timestamp = time();
            $year = date('Y', $timestamp);
            $day = str_pad(date('z', $timestamp), 3, '0', STR_PAD_LEFT);
            $second = str_pad(
                ($timestamp - strtotime(date('Y-m-d'))), 5, '0', STR_PAD_LEFT
            );
            $timeString = $year . $day . $second;

            // 随机序列 固定3位
            $randString = str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT);

            $poolKey = Yii::$app->id . md5($label . date('YzH', $timestamp)); // 按标签按时分池
            $serialNumber = $timeString . $randString; // 本次循环生成的订单流水号

            if ($digit > 15) {
                for ($i = ($digit - strlen($serialNumber)); $i > 0; $i--) {
                    $serialNumber .= mt_rand(0, 9);
                }
            } elseif ($digit < 15) {
                $system = (int)pow($serialNumber, 1/$digit) + 1;
                $serialNumber = strtoupper(base_convert($serialNumber, 10, $system));
            }

            $result = Yii::$app->redis->sadd($poolKey, $serialNumber);
            if (Yii::$app->redis->exists($poolKey)
                && Yii::$app->redis->ttl($poolKey) < 0) {
                    Yii::$app->redis->expire($poolKey, 3600*2); // 分池两个小时后回收空间
            }

            isset($loopCount) ? $loopCount++ : $loopCount = 1;

        } while (!$result);

        return $serialNumber;
    }
}
