<?php

namespace tests\unit\fixtures;

use Yii;
use yii\test\BaseActiveFixture;
use yii\base\InvalidArgumentException;

class UserAddressFixture extends BaseActiveFixture
{
    public $modelClass = 'app\modules\user\modules\v1\models\UserAddress';
    public $dataFile = '@app/tests/unit/fixtures/data/user_address.php';

    public function init()
    {
        parent::init();

        Yii::$app->getModule('channel/v1')->processor = 'channel1';
    }

    public function load()
    {
        $fixtureData = $this->getData();
        array_walk($fixtureData, function ($value) {
            if (empty($value['id'])) {
                throw new InvalidArgumentException('fixture数据中id异常');
            }
        });

        $this->data = [];
        $modelClass = $this->modelClass;
        foreach ($fixtureData as $alias => $row) {
            $this->db->createCommand()->insert($modelClass::tableName(), $row)->execute();
            $this->data[$alias] = $row;
        }
    }

    public function unload()
    {
        $fixtureData = $this->getData();
        $id = array_column($fixtureData, 'id');
        if (empty($id)) {
            throw new InvalidArgumentException('fixture数据中id异常');
        }

        $modelClass = $this->modelClass;
        $this->db->createCommand()->delete($modelClass::tableName(), ['IN', 'id', $id])->execute();

        parent::unload();
    }
}
