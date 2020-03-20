<?php

namespace app\modules\rewrite\modules\v1\behaviors;

use Yii;
use yii\base\Behavior;
use app\modules\rewrite\modules\v1\models\GoodSaleRegion;
use app\modules\rewrite\modules\v1\models\Region;
use app\modules\user\modules\v1\models\UserAddress;
use app\modules\user\modules\v1\models\District;
use app\modules\rewrite\modules\v1\exceptions\RewriteException;
use app\modules\user\modules\v1\exceptions\UserException;
use yii\helpers\ArrayHelper;

/**
 * 地区限购
 * Class DistrictLimitTool
 * @package app\modules\rewrite\modules\v1\behaviors
 */
class DistrictLimitTool extends Behavior
{
    public $rewriteDistricts = null;

    /**
     * 地区限购验证
     * @param $good
     * @throws RewriteException
     * @throws UserException
     */
    public function marketCartItemValidate($good)
    {
        if (!empty($good['sale_region'])) {
            // 获取商品允许送货地区
            $permittedAreas = explode(',', $good['sale_region']);
            $areaList = GoodSaleRegion::find()
                ->select(['province', 'city', 'area'])
                ->where(['IN', 'ID', $permittedAreas])
                ->asArray()
                ->all();
            $nameNeeded = [];
            if ($this->rewriteDistricts == null) $this->rewriteDistricts = [];
            foreach ($areaList as $key => $row) {
                $tmp = [];
                if ($row['province'] > 0) {
                    if (!isset($this->rewriteDistricts[$row['province']])) {
                        $nameNeeded[] = $row['province'];
                    }
                    $tmp[1] = $row['province'];
                }
                if ($row['city'] > 0) {
                    if (!isset($this->rewriteDistricts[$row['city']])) {
                        $nameNeeded[] = $row['city'];
                    }
                    $tmp[2] = $row['city'];
                }
                if ($row['area'] > 0) {
                    if (!isset($this->rewriteDistricts[$row['area']])) {
                        $nameNeeded[] = $row['area'];
                    }
                    $tmp[3] = $row['area'];
                }
                $areaList[$key] = $tmp;
            }

            // 根据region_id获取region_name
            if (!empty($nameNeeded)) {
                $this->rewriteDistricts = ArrayHelper::merge(
                    $this->rewriteDistricts,
                    Region::find()
                        ->select(['region_name', 'region_id'])
                        ->where(['IN', 'region_id', $nameNeeded])
                        ->indexBy('region_id')
                        ->column()
                );
            }

            // 获取用户送货地址信息
            $addressInfo = UserAddress::findUserAddressById(Yii::$app->request->post('shippingAddress'));
            if (empty($addressInfo))
                throw new UserException('无效地址，请联系客服');

            District::$table = $addressInfo['dist_table'];
            $district = District::findDetailedDistrictById($addressInfo['dist_id']);

            if (empty($district))
                throw new UserException('无效地区，请联系客服');

            unset($addressInfo['dist_table'], $addressInfo['dist_id']);
            $addressInfo['district'] = $district;

            // 校验送货范围
            $isPass = false;
            foreach ($areaList as $area) {
                if (empty($area)) continue;
                $isMatch = true;
                foreach ($area as $type => $rid) {
                    foreach ($addressInfo['district'] as $dist) {
                        if ($type == $dist['type'] && strpos($dist['name'], $this->rewriteDistricts[$rid]) === false) {
                            $isMatch = false; break 2;
                        }
                    }
                }
                if ($isMatch) {
                    $isPass = true; break;
                }
            }
            if (!$isPass) {
                throw new RewriteException(
                    '抱歉，商品【' . $good['goodExtra']['goods_name'] . '】送货范围不支持所选的送货地址'
                );
            }
        }
    }
}
