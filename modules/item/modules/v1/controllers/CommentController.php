<?php

namespace app\modules\item\modules\v1\controllers;

use Yii;
use yii\web\Response;
use yii\data\ActiveDataProvider;
use yii\filters\ContentNegotiator;
use app\modules\order\modules\v1\models\Order;
use app\modules\user\modules\v1\bases\AuthController;
use app\modules\item\modules\v1\models\ProductReview;
use app\modules\item\modules\v1\exceptions\ItemException;

class CommentController extends AuthController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::className(),
            'only' => ['list'],
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
                'application/xml' => Response::FORMAT_XML,
            ]
        ];
        return $behaviors;
    }

    /**
     * 获取商品评价列表
     * @return array
     * @throws ItemException
     */
    public function actionList()
    {
        $page = Yii::$app->request->headers->get('X-Unionsystem-List-Page', 1); // 页码
        $pageSize = Yii::$app->request->headers->get('X-Unionsystem-List-Pagesize', 10); // 每页数据容量

        if ($itemId = Yii::$app->request->post('itemId')) {
            $query = ProductReview::find()
                ->with([
                    'orderShipping' => function (\yii\db\ActiveQuery $query) {
                        $query->select(['order_no', 'consignee', 'phone_no']);
                    },
                ])
                ->where(['item_id' => $itemId, 'valid' => ProductReview::VALID])
                ->asArray();
        } else {
            throw new ItemException('参数错误');
        }
        $defaultOrder = ['grade' => SORT_DESC];
        if ($sort = Yii::$app->request->post('sort')) {
            if ($sort == 'datetime') {
                $defaultOrder = ['create_time' => SORT_DESC];
            } elseif ($sort == 'grade') {
                $defaultOrder = ['grade' => SORT_DESC];
            }
        }
        if ($order = Yii::$app->request->post('order')) {
            if ($order == 'asc') {
                foreach ($defaultOrder as &$value) {
                    $value = SORT_ASC;
                }
            } elseif ($order == 'desc') {
                foreach ($defaultOrder as &$value) {
                    $value = SORT_DESC;
                }
            }
        }

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'page' => $page - 1,
                'pageSize' => $pageSize
            ],
            'sort' => [
                'defaultOrder' => $defaultOrder
            ]
        ]);
        $productReviews = $provider->getModels();

        $result = [];
        foreach ($productReviews as $value) {
            if (empty($value['orderShipping']['phone_no'])) {
                $phoneNo = '***';
            } else {
                $headThree = substr($value['orderShipping']['phone_no'], 0, 3);
                $tailFour = substr($value['orderShipping']['phone_no'], -4);
                $phoneNo = $headThree . '***' . $tailFour;
            }
            if (empty($value['orderShipping']['consignee'])) {
                $buyer = '***';
            } else {
                $buyer = mb_substr($value['orderShipping']['consignee'], 0, 1) . '***';
            }
            $resRow = [
                'grade' => $value['grade'],
                'comment' => $value['comment'],
                'buyer' => $buyer,
                'phoneNo' => $phoneNo,
                'datetime' => date('Y-m-d', strtotime($value['create_time'])),
            ];
            $result[] = $resRow;
        }

        Yii::$app->response->headers->set('X-Unionsystem-List-Page', $page); // 页码
        Yii::$app->response->headers->set('X-Unionsystem-List-Pagesize', $pageSize); // 每页数据容量
        Yii::$app->response->headers->set('X-Unionsystem-List-Currentcount', $provider->getCount()); // 当页数据量
        Yii::$app->response->headers->set('X-Unionsystem-List-Totalcount', $provider->getTotalCount()); // 总数据量

        return $result;
    }

    /**
     * 添加商品评价
     */
    public function actionAdd()
    {
        try {
            $orderNo = null;
            if ($orderId = Yii::$app->request->post('orderId')) {
                $orderInfo = Order::find()
                    ->select(['order_no'])
                    ->with([
                        'orderItem' => function (\yii\db\ActiveQuery $query) {
                            $query->select(['order_no', 'item_id'])->where(['parent_id' => 0]);
                        }
                    ])
                    ->where([
                        'id' => $orderId,
                        'user_id' => Yii::$app->user->identity->id,
                        'order_stat' => Order::COMPLETE
                    ])
                    ->asArray()
                    ->one();
                if ($itemId = Yii::$app->request->post('itemId')) {
                    if (empty($orderInfo['orderItem']) ||
                        !in_array($itemId, array_column($orderInfo['orderItem'], 'item_id'))) {
                        throw new ItemException('你暂无法进行评价');
                    } else {
                        $orderNo = $orderInfo['order_no'];
                    }
                }
            }

            $data = [
                'order_no' => $orderNo,
                'item_id' => Yii::$app->request->post('itemId'),
                'comment' => Yii::$app->request->post('comment'),
                'grade' => Yii::$app->request->post('grade'),
            ];

            $model = new ProductReview();
            $model->scenario = $model::SCENARIO_ADD;
            $model->attributes = $data;

            if ($model->validate()) {
                $model->save();
            } else {
                throw new ItemException('缺少必须参数');
            }
        } catch (ItemException $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 403;
            Yii::$app->response->statusText = urlencode($e->getMessage());
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (\Exception $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            Yii::$app->response->statusText = urlencode('您的服务异常，请联系客服');
            Yii::$app->response->data = Yii::$app->response->content = null;
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            Yii::$app->response->statusText = urlencode('您的服务异常，请联系客服');
            Yii::$app->response->data = Yii::$app->response->content = null;
        }
    }
}