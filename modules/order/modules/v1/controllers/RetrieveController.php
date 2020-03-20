<?php

namespace app\modules\order\modules\v1\controllers;

use Yii;
use yii\web\Response;
use yii\data\ActiveDataProvider;
use yii\filters\ContentNegotiator;
use app\modules\order\modules\v1\models\Order;
use app\modules\user\modules\v1\models\District;
use app\modules\user\modules\v1\bases\AuthController;

/**
 * 检索数据
 * Class RetrieveController
 * @package app\modules\order\modules\v1\controllers
 */
class RetrieveController extends AuthController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::className(),
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
                'application/xml' => Response::FORMAT_XML,
            ]
        ];
        return $behaviors;
    }

    /**
     * 获取订单列表
     * @return array
     */
    public function actionIndex()
    {
        $page = Yii::$app->request->headers->get('X-Unionsystem-List-Page', 1); // 页码
        $pageSize = Yii::$app->request->headers->get('X-Unionsystem-List-Pagesize', 15); // 每页数据容量

        $status = Yii::$app->request->post('orderStatus', null); // 订单状态

        $query = Order::find()
            ->where(['user_id' => Yii::$app->user->identity->id])
            ->with('orderItem')
            ->with('orderShipping')
            ->asArray();
        if (isset($status)) {
            $query->andWhere(['order_stat' => $status]);
        }
        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'page' => $page - 1,
                'pageSize' => $pageSize
            ],
            'sort' => [
                'defaultOrder' => [
                    'create_time' => SORT_DESC,
                ]
            ]
        ]);
        $data = $provider->getModels();

        $orderStatDes = [
            '-3' => '已全额退款', '-2' => '已部分退款', '-1' => '已取消',
            '0' => '等待支付',
            '1' => '已支付', '2' => '已收货', '3' => '已评价'
        ];
        $ShipStatDes = [
            '-2' => '已退货', '-1' => '退货中',
            '0' => '未付款',
            '1' => '待发货', '2' => '已发货', '3' => '已签收'
        ];
        $result = [];
        foreach ($data as $row) {
            $resRow = [
                'orderId' => $row['id'],
                'orderNo' => $row['order_no'],
                'orderAmt' => $row['order_amt'],
                'orderCyUnit' => '元',
                'orderStat' => $row['order_stat'],
                'orderStatDes' => $orderStatDes[$row['order_stat']],
                'orderCTime' => $row['create_time'],
            ];
            foreach ($row['orderItem'] as $value) {
                if ($value['parent_id'] == 0) {
                    $resRow['orderItems'][] = [
                        'itemId' => $value['item_id'],
                        'itemName' => $value['item_name'],
                        'itemCost' => $value['item_pur_price'],
                        'itemNum' => $value['item_number'],
                        'isVirt' => (bool)$value['item_is_virt'],
                        'virtCode' => json_decode($value['virt_code'], true),
                    ];
                }
            }
            $resRow['leavingMessage'] = $row['orderShipping']['message'];
            $resRow['shippingWay'] = $row['orderShipping']['ship_way'];
            $resRow['shippingSn'] = $row['orderShipping']['ship_sn'];
            $resRow['shipmentConsignee'] = $row['orderShipping']['consignee'];
            $resRow['consigneePhoneNo'] = $row['orderShipping']['phone_no'];
            $resRow['shippingZipCode'] = $row['orderShipping']['zip_code'];
            District::$table = $row['orderShipping']['dist_table'];
            $district = District::findDetailedDistrictById($row['orderShipping']['dist_id']);
            $resRow['shippingAddress'] = implode(' ', array_column($district, 'name'));
            $resRow['shippingAddress'] .= ' ' . $row['orderShipping']['addr_detail'];
            $resRow['shippingStat'] = $row['orderShipping']['ship_stat'];
            $resRow['shippingStatDes'] = $ShipStatDes[$row['orderShipping']['ship_stat']];

            $result[] = $resRow;
        }

        Yii::$app->response->headers->set('X-Unionsystem-List-Page', $page); // 页码
        Yii::$app->response->headers->set('X-Unionsystem-List-Pagesize', $pageSize); // 每页数据容量
        Yii::$app->response->headers->set('X-Unionsystem-List-Currentcount', $provider->getCount()); // 当页数据量
        Yii::$app->response->headers->set('X-Unionsystem-List-Totalcount', $provider->getTotalCount()); // 总数据量

        return $result;
    }
}
