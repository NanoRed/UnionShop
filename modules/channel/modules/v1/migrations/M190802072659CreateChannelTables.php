<?php

use yii\db\Migration;

/**
 * Class M190802072659CreateChannelTables
 */
class M190802072659CreateChannelTables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 原则上，所有表使用InnoDB引擎
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        // 渠道表
        $tableName = '{{%channels}}';
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'name' => $this->string(64)->notNull()->comment('渠道名称'),
            'alias' => $this->char(8)->notNull()->comment('渠道代号'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('alias', $tableName, ['alias'], true);
        $this->addCommentOnTable($tableName, '渠道列表');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "M190802072659CreateChannelTables cannot be reverted.\n";
        echo "included tables:\n";
        echo "\t" . static::getDb()->schema->getRawTableName('{{%channels}}');

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "M190802072659CreateChannelTables cannot be reverted.\n";

        return false;
    }
    */
}
