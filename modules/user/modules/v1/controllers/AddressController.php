<?php

namespace app\modules\user\modules\v1\controllers;

use Yii;
use yii\web\Response;
use yii\data\ActiveDataProvider;
use yii\filters\ContentNegotiator;
use app\modules\user\modules\v1\models\District;
use app\modules\user\modules\v1\models\UserAddress;
use app\modules\user\modules\v1\bases\AuthController;
use app\modules\user\modules\v1\exceptions\UserException;

class AddressController extends AuthController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::className(),
            'only' => ['list', 'info', 'sel'],
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
                'application/xml' => Response::FORMAT_XML,
            ]
        ];
        return $behaviors;
    }

    /**
     * 获取用户地址列表
     * @return array
     */
    public function actionList()
    {
        $page = Yii::$app->request->headers->get('X-Unionsystem-List-Page', 1); // 页码
        $pageSize = Yii::$app->request->headers->get('X-Unionsystem-List-Pagesize', 15); // 每页数据容量

        $provider = new ActiveDataProvider([
            'query' => UserAddress::find()
                ->where([
                    'user_id' => Yii::$app->user->identity->id,
                    'addr_stat' => 1
                ])
                ->orderBy(['create_time' => SORT_DESC])
                ->asArray(),
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
        $addresses = $provider->getModels();

        $result = [];
        foreach ($addresses as $address) {
            $resRow = [
                'id' => $address['id'],
                'consignee' => $address['consignee'],
                'phoneNo' => $address['phone_no'],
                'zipCode' => $address['zip_code'],
                'districtTable' => $address['dist_table']
            ];
            District::$table = $address['dist_table'];
            $resRow['districtInfo'] = District::findDetailedDistrictById($address['dist_id']);
            array_multisort(
                array_column($resRow['districtInfo'], 'type'),
                SORT_ASC,
                $resRow['districtInfo']
            );
            $resRow['addressDetail'] = $address['addr_detail'];
            $resRow['createTime'] = $address['create_time'];

            $result[] = $resRow;
        }

        Yii::$app->response->headers->set('X-Unionsystem-List-Page', $page); // 页码
        Yii::$app->response->headers->set('X-Unionsystem-List-Pagesize', $pageSize); // 每页数据容量
        Yii::$app->response->headers->set('X-Unionsystem-List-Currentcount', $provider->getCount()); // 当页数据量
        Yii::$app->response->headers->set('X-Unionsystem-List-Totalcount', $provider->getTotalCount()); // 总数据量

        return $result;
    }

    /**
     * 用户新增地址
     */
    public function actionAdd()
    {
        try {
            $data = [
                'consignee' => Yii::$app->request->post('consignee'),
                'phone_no' => Yii::$app->request->post('phoneNo'),
                'zip_code' => Yii::$app->request->post('zipCode'),
                'dist_table' => Yii::$app->request->post('districtTable', District::$table),
                'dist_id' => Yii::$app->request->post('districtId'),
                'addr_detail' => Yii::$app->request->post('addressDetail')
            ];

            District::$table = $data['dist_table'];
            if (!District::checkIsTail($data['dist_id'])) {
                throw new UserException('请选择完整的地区');
            }

            $model = new UserAddress;
            $model->scenario = $model::SCENARIO_ADD;
            $model->attributes = $data;

            if ($model->validate()) {
                $model->save();
            } else {
                throw new UserException('缺少必须参数');
            }
        } catch (UserException $e) {
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

    /**
     * 用户地址信息
     */
    public function actionInfo()
    {
        $detail = UserAddress::findUserAddressById(Yii::$app->request->post('id'));

        if (empty($detail)) {
            throw new UserException('地址不存在');
        }

        $result = [
            'id' => $detail['id'],
            'consignee' => $detail['consignee'],
            'phoneNo' => $detail['phone_no'],
            'zipCode' => $detail['zip_code'],
            'districtTable' => $detail['dist_table'],
        ];
        District::$table = $detail['dist_table'];
        $result['districtInfo'] = District::findDetailedDistrictById($detail['dist_id']);
        array_multisort(
            array_column($result['districtInfo'], 'type'),
            SORT_ASC,
            $result['districtInfo']
        );
        $result['addressDetail'] = $detail['addr_detail'];

        return $result;
    }

    /**
     * 用户更新地址
     */
    public function actionEdit()
    {
        try {
            $model = UserAddress::findOne([
                'id' => Yii::$app->request->post('id'),
                'user_id' => Yii::$app->user->identity->id,
                'addr_stat' => 1
            ]);

            if (empty($model)) {
                throw new UserException('地址不存在');
            }

            $data = [
                'consignee' => Yii::$app->request->post('consignee'),
                'phone_no' => Yii::$app->request->post('phoneNo'),
                'zip_code' => Yii::$app->request->post('zipCode'),
                'dist_table' => Yii::$app->request->post('districtTable', District::$table),
                'dist_id' => Yii::$app->request->post('districtId'),
                'addr_detail' => Yii::$app->request->post('addressDetail')
            ];

            District::$table = $data['dist_table'];
            if (!District::checkIsTail($data['dist_id'])) {
                throw new UserException('请选择完整的地区');
            }

            $model->scenario = $model::SCENARIO_EDIT;
            $model->attributes = $data;

            if ($model->validate()) {
                $model->save();
            } else {
                throw new UserException('缺少必须参数');
            }
        } catch (UserException $e) {
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

    /**
     * 用户删除地址
     */
    public function actionDel()
    {
        try {
            $model = UserAddress::findOne([
                'id' => Yii::$app->request->post('id'),
                'user_id' => Yii::$app->user->identity->id,
                'addr_stat' => 1
            ]);

            if (empty($model)) {
                throw new UserException('地址不存在');
            }

            $model->scenario = $model::SCENARIO_DEL;
            $model->save();
        } catch (UserException $e) {
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

    /**
     * 获取地址选择列表
     * @return mixed
     */
    public function actionSel()
    {
        $districtTable = Yii::$app->request->post('districtTable', District::$table); // 地区表
        $districtId = Yii::$app->request->post('districtId', 0); // 地区ID

        District::$table = $districtTable;
        $result = District::findSubordinateListById($districtId);

        return $result;
    }
}
