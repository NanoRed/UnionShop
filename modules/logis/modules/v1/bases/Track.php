<?php

namespace app\modules\logis\modules\v1\bases;

use yii\base\Component;

/**
 * 物流跟踪服务继承类
 * Class Track
 * @package app\modules\logis\modules\v1\bases
 */
abstract class Track extends Component
{
    public $params; // 配置参数

    /**
     * 实现检索订单
     * 查找尚未进行物流跟踪的订单
     * 返回渠道订单送货表订单号ship_no
     * @param $limit
     * @return mixed
     */
    abstract function retrieve($limit);

    /**
     * 识别订单物流商编码
     * @param mixed $shipNo 渠道订单送货表订单号
     * @return mixed
     */
    abstract function identify($shipNo);

    /**
     * 订阅物流跟踪
     * @return mixed
     */
    abstract function subscribe();

    /**
     * 接收订阅推送回调
     * @return mixed
     */
    abstract function callback();

    /**
     * 实现数据同步
     * 状态同步等
     * @return mixed
     */
    abstract function synchronize();

    /**
     * 实现查询订单物流信息
     * @param $orderNo
     * @return array
     * e.g.
     * [
     *     [
     *         'time' => '2016-10-26 18:31:38',
     *         'message' => '【北京环铁站】的【互优图书】已收件'
     *     ],
     *     [
     *         'time' => '2016-10-26 19:53:50',
     *         'message' => '快件在【北京环铁站】装车,正发往【北京分拨中心】'
     *     ]
     * ]
     */
    abstract function trace($orderNo);
}