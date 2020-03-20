<?php

use yii\db\Migration;

/**
 * Class M190802073323CreatePayTables
 */
class M190802073323CreatePayTables extends Migration
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

        // 支付方式表
        $tableName = '{{%payments}}';
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'name' => $this->string(64)->notNull()->comment('支付方式名称'),
            'alias' => $this->string(16)->notNull()->comment('支付方式代号'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('alias', $tableName, ['alias'], true);
        $this->addCommentOnTable($tableName, '支付方式表');

        // 结算账户表
        // 此表以及us_channel_payments的意义在于，若账户由于不可抗力原因被冻结可以迅速切换备用账号
        // 扩展：可从业务战略出发将不同业务的收入导到不同的账户（us_channel_payments加字段判断）
        $tableName = '{{%payment_accounts}}';
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'payment_id' => $this->integer()->unsigned()->notNull()->comment('支付方式（us_payments->id）'),
            'merchant_id' => $this->string(32)->notNull()->comment('商户号'),
            'depend_on' => $this->string(32)->notNull()->comment('从属的商户号'),
            'binding_bank_name' => $this->string(32)->notNull()->comment('绑定银行'),
            'binding_bank_account' => $this->string(32)->notNull()->comment('绑定银行账户'),
            'account_info' => $this->text()->notNull()->comment('（可选，请存JSON）账户信息'),
            'remark' => $this->string(256)->notNull()->comment('备注'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->addCommentOnTable($tableName, '结算账户表');

        // 渠道支付配置表
        // 此表配置不同渠道的具体支付方式
        $tableName = '{{%channel_payments}}';
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'channel_id' => $this->integer()->unsigned()->notNull()->comment('渠道ID（us_channels->id）'),
            'account_id' => $this->integer()->unsigned()->notNull()->comment('结算账户（us_accounts->id）'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
            'stat' => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('状态：0不可用，1正常'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('channel_id,account_id', $tableName, ['channel_id', 'account_id'], true);
        $this->addCommentOnTable($tableName, '渠道支付配置表');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "M190802073323CreatePayTables cannot be reverted.\n";
        echo "included tables:\n";
        echo "\t" . static::getDb()->schema->getRawTableName('{{%payments}}');
        echo "\t" . static::getDb()->schema->getRawTableName('{{%payment_accounts}}');
        echo "\t" . static::getDb()->schema->getRawTableName('{{%channel_payments}}');

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "M190802073323CreatePayTables cannot be reverted.\n";

        return false;
    }
    */
}
