<?php

namespace app\modules\logis\modules\v1\bases;

use yii\base\Component;

/**
 * ERP基础继承类
 * Class Erp
 * @package app\modules\logis\modules\v1\bases
 */
abstract class Erp extends Component
{
    public $params; // 配置参数

    /**
     * 实现检索订单
     * 找到符合条件推送ERP的orderNo
     * 即dispatch方法的参数
     * @param int $limit 限制查找订单数量
     * @return array
     */
    abstract function retrieve($limit);

    /**
     * 实现发货
     * @param $orderNo
     * @return mixed
     */
    abstract function dispatch($orderNo);

    /**
     * 实现数据同步
     * 状态同步等
     * @return mixed
     */
    abstract function synchronize();
}