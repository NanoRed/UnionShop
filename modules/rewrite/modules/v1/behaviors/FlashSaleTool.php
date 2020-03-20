<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use app\modules\rewrite\modules\v1\models\User;
use app\modules\rewrite\modules\v1\models\Good;
use app\modules\rewrite\modules\v1\models\FlashSaleBlacklist;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;

/**
 * 秒杀工具
 * Class FlashSaleTool
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class FlashSaleTool extends Behavior
{
    public $userItemsBannedList = null;

    /**
     * 秒杀验证
     * @param $good
     * @param $cartItemsProperty
     * @return array
     */
    public function marketCartItemValidate($good, $cartItemsProperty)
    {
        $obj = $this;
        $index = 0; // 执行顺序，值越小越先执行，注意值不要重复
        $func = function ($func) use ($good, $cartItemsProperty, $obj) {
            return function ($isExec = true) use ($func, $good, $cartItemsProperty, $obj) {
                if ($isExec) {
                    if ($good['goodExtra']['is_seckill'] == 0) {
                        $func(); return;
                    } elseif ($good['goodExtra']['is_seckill'] == 1) {
                        // 秒杀黑名单检验
                        if ($obj->userItemsBannedList === null) { // 减少IO
                            $rewriteUserId = User::findRewriteUserIdByChannelUserId(Yii::$app->user->identity->cuid);
                            $obj->userItemsBannedList = FlashSaleBlacklist::find()
                                ->select(['goods_id'])
                                ->where(['user_id' => $rewriteUserId, 'status' => 1])
                                ->column();
                        }
                        if (in_array($good['goods_id'], $obj->userItemsBannedList)) {
                            throw new RewriteException('抱歉，已被别人抢先一步，请稍后再试');
                        }
                        // 扣除计划秒杀数量
                        $num = Good::updateAllCounters(
                            ['plan_kill_number' => $cartItemsProperty[$good['goods_id']][2] * -1],
                            [
                                'AND',
                                ['=', 'goods_id', $good['goods_id']],
                                ['>=', 'plan_kill_number', $cartItemsProperty[$good['goods_id']][2]]
                            ]
                        );
                        if ($num != 1) {
                            throw new RewriteException('抱歉，此商品已被抢购一空');
                        }
                    }

                    $func(false); return;
                }

                $func();
            };
        };

        return [$index => $func];
    }
}
