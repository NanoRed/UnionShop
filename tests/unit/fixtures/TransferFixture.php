<?php

namespace tests\unit\fixtures;

use Yii;
use yii\test\BaseActiveFixture;
use yii\base\InvalidArgumentException;

class TransferFixture extends BaseActiveFixture
{
    public $modelClass = 'app\modules\pay\modules\v1\models\Transfer';
    public $dataFile = '@app/tests/unit/fixtures/data/transfer.php';

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

        $sqlMode = $this->db->createCommand('SELECT @@sql_mode')->queryScalar();
        if (strpos($sqlMode, 'NO_ZERO_IN_DATE') !== false ||
            strpos($sqlMode, 'NO_ZERO_DATE') !== false) {
            $sqlMode = preg_replace('/NO_ZERO_IN_DATE,?\s*/', '', $sqlMode);
            $sqlMode = preg_replace('/NO_ZERO_DATE,?\s*/', '', $sqlMode);
            $this->db->createCommand('SET sql_mode = \'' . $sqlMode . '\'')->execute();
        }

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
