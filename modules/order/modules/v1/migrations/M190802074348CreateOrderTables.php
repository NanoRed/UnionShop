<?php

use yii\db\Migration;

/**
 * Class M190802074348CreateOrderTables
 */
class M190802074348CreateOrderTables extends Migration
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

        // 归档订单表
        // 数据汇总归档作用，此表会较为庞大，业务逻辑不查询此表
        $tableName = '{{%gather_orders}}';
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'channel_id' => $this->integer()->unsigned()->notNull()->comment('渠道ID（us_channels->id）'),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('渠道唯一用户ID（us_[channel_id]_users->id）'),
            'uuid' => $this->integer()->unsigned()->notNull()->comment('全局唯一用户ID（us_[channel_id]_users->uuid）'),
            'cuid' => $this->integer()->unsigned()->notNull()->comment('渠道用户ID（us_[channel_id]_users->cuid）'),
            'order_no' => $this->char(20)->notNull()->comment('订单号（us_[channel_id]_orders->order_no）'),
            'order_amt' => $this->decimal(10, 2)->unsigned()->notNull()->comment('订单总金额（us_[channel_id]_orders->order_amt）'),
            'order_ctime' => $this->dateTime()->notNull()->comment('用户下单时间（us_[channel_id]_orders->create_time）'),
            'xfer_no' => $this->char(20)->notNull()->comment('支付订单号（us_[channel_id]_transfer->xfer_no）'),
            'xfer_amt' => $this->decimal(10, 2)->unsigned()->notNull()->comment('交易总金额（us_[channel_id]_transfer->xfer_amt）'),
            'xfer_ctime' => $this->dateTime()->notNull()->comment('用户支付时间（us_[channel_id]_transfer->create_time）'),
            'refund_no' => $this->char(20)->notNull()->comment('退款订单号（us_[channel_id]_refunds->refund_no）'),
            'refund_amt' => $this->decimal(10, 2)->unsigned()->notNull()->comment('退款总金额（us_[channel_id]_refunds->refund_amt）'),
            'refund_ctime' => $this->dateTime()->notNull()->comment('用户退款时间（us_[channel_id]_refunds->create_time）'),
            'ship_no' => $this->char(20)->notNull()->comment('物流订单号（us_[channel_id]_order_shipping->ship_no）'),
            'ship_sn' => $this->string(64)->notNull()->comment('快递单号（us_[channel_id]_order_shipping->ship_sn）'),
            'ship_ctime' => $this->dateTime()->notNull()->comment('物流单生成时间（us_[channel_id]_order_shipping->ship_ntime）'),
            'item_warehouse_id' => $this->integer()->unsigned()->notNull()->comment('仓库ID'),
            'item_supplier_id' => $this->integer()->unsigned()->notNull()->comment('供应商ID'),
            'item_list' => $this->text()->comment('订单商品列表，请存JSON，样本格式：[{"item_id":"1","item_sn":"SAMPLE001","item_name":"样本001","item_xfer_price":9.9,"item_number":1,"item_type":3,"item_is_virt":0,"virt_code":[],"child":[...]},{...}]'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('channel_id', $tableName, ['channel_id']);
        $this->createIndex('uuid', $tableName, ['uuid']);
        $this->createIndex('order_ctime', $tableName, ['order_ctime']);
        $this->createIndex('xfer_ctime', $tableName, ['xfer_ctime']);
        $this->createIndex('refund_ctime', $tableName, ['refund_ctime']);
        $this->createIndex('ship_ctime', $tableName, ['ship_ctime']);
        $this->createIndex('item_warehouse_id', $tableName, ['item_warehouse_id']);
        $this->createIndex('item_supplier_id', $tableName, ['item_supplier_id']);
        $this->addCommentOnTable($tableName, '归档订单表');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "M190802074348CreateOrderTables cannot be reverted.\n";
        echo "included tables:\n";
        echo "\t" . static::getDb()->schema->getRawTableName('{{%gather_orders}}');

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "M190802074348CreateOrderTables cannot be reverted.\n";

        return false;
    }
    */
}
