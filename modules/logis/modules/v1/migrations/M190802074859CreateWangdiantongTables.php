<?php

use yii\db\Migration;

/**
 * Class M190802074859CreateWangdiantongTables
 */
class M190802074859CreateWangdiantongTables extends Migration
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

        // 旺店通ERP推送订单记录表
        $tableName = 'erp_wdt_pushed_orders';
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'channel_id' => $this->integer()->unsigned()->notNull()->comment('渠道ID（us_channels->id）'),
            'wdt_tid' => $this->string(40)->notNull()->comment('旺店通原始订单号（us_[channel_id]_orders->order_no）'),
            'wdt_push_data' => $this->text()->notNull()->comment('（JSON）推送给旺店通的订单数据'),
            'wdt_push_time' => $this->dateTime()->notNull()->comment('推送旺店通时间'),
            'wdt_rec_id' => $this->integer(11)->notNull()->comment('旺店通查询物流同步主键'),
            'wdt_shop_no' => $this->string(20)->notNull()->comment('旺店通店铺编号'),
            'wdt_logistics_no' => $this->string(100)->notNull()->comment('旺店通物流单号'),
            'wdt_logistics_type' => $this->char(10)->notNull()->comment('旺店通物流方式'),
            'wdt_consign_time' => $this->dateTime()->notNull()->comment('旺店通发货时间'),
            'wdt_platform_id' => $this->tinyInteger(1)->notNull()->comment('旺店通平台ID'),
            'wdt_trade_id' => $this->integer(11)->notNull()->comment('旺店通订单ID'),
            'wdt_logistics_code_erp' => $this->string(20)->notNull()->comment('旺店通erp物流编号'),
            'wdt_logistics_name_erp' => $this->string(40)->notNull()->comment('旺店通erp物流公司名称'),
            'wdt_logistics_name' => $this->string(40)->notNull()->comment('旺店通物流公司名称'),
            'wdt_sync_data' => $this->text()->notNull()->comment('（JSON）查询物流同步数据'),
            'wdt_sync_time' => $this->dateTime()->notNull()->comment('最新物流同步时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('wdt_tid', $tableName, ['wdt_tid']);
        $this->createIndex('wdt_logistics_no', $tableName, ['wdt_logistics_no']);
        $this->createIndex('wdt_trade_id', $tableName, ['wdt_trade_id']);
        $this->addCommentOnTable($tableName, '旺店通ERP推送订单记录表');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "M190802074859CreateWangdiantongTables cannot be reverted.\n";
        echo "included tables:\n";
        echo "\terp_wdt_pushed_orders";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "M190802074859CreateWangdiantongTables cannot be reverted.\n";

        return false;
    }
    */
}
