<?php

namespace tests\unit\fixtures;

use yii\test\BaseActiveFixture;

class AuthItemChildFixture extends BaseActiveFixture
{
    public $tableName = '{{%auth_item_child}}';
    public $dataFile = '@app/tests/unit/fixtures/data/auth_item_child.php';

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
        foreach ($fixtureData as $alias => $row) {
            $this->db->createCommand()->delete($this->tableName, $row)->execute();
        }

        parent::unload();
    }
}
