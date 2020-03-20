<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use yii\base\Behavior;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;

/**
 * 抽奖工具
 * Class LotteryTool
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class LotteryTool extends Behavior
{
    /**
     * 抽奖验证
     * @param $good 商品信息模型
     * @throws RewriteException
     */
    public function marketCartItemValidate($good)
    {
        // 是否抽奖商品
        if ($good['goodExtra']['is_reward'] == 1) {
            throw new RewriteException('无法购买活动商品');
        }
    }
}
