<?php

namespace tests\unit\fixtures;

use Yii;
use yii\test\BaseActiveFixture;
use yii\base\InvalidArgumentException;

class TransferRelationsFixture extends BaseActiveFixture
{
    public $modelClass = 'app\modules\pay\modules\v1\models\TransferRelation';
    public $dataFile = '@app/tests/unit/fixtures/data/transfer_relations.php';

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
