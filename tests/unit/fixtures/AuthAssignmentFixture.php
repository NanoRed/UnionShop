<?php

namespace tests\unit\fixtures;

use yii\test\BaseActiveFixture;

class AuthAssignmentFixture extends BaseActiveFixture
{
    public $tableName = '{{%auth_assignment}}';
    public $dataFile = '@app/tests/unit/fixtures/data/auth_assignment.php';

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
            unset($row['created_at']);
            $this->db->createCommand()->delete($this->tableName, $row)->execute();
        }

        parent::unload();
    }
}
