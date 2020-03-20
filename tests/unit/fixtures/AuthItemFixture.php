<?php

namespace tests\unit\fixtures;

use yii\test\BaseActiveFixture;

class AuthItemFixture extends BaseActiveFixture
{
    public $tableName = '{{%auth_item}}';
    public $dataFile = '@app/tests/unit/fixtures/data/auth_item.php';

    public function load()
    {
        $fixtureData = $this->getData();
        $this->data = [];
        foreach ($fixtureData as $alias => $row) {
            $this->db->createCommand()->insert($this->tableName, $row)->execute();
            $this->data[$alias] = $row;
        }
    }

    public function unload()
    {
        $fixtureData = $this->getData();
        $name = array_column($fixtureData, 'name');
        $this->db->createCommand()->delete($this->tableName, ['IN', 'name', $name])->execute();

        parent::unload();
    }
}
