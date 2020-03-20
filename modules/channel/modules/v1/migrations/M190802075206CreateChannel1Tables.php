<?php

use yii\db\Migration;

/**
 * Class M190802075206CreateChannel1Tables
 */
class M190802075206CreateChannel1Tables extends Migration
{
    private $channelName = '渠道实例一';
    private $channelAlias = 'CHANNEL1';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 渠道表插入新建渠道信息
        $tableName = '{{%channels}}';
        $tableValue = [
            'name' => $this->channelName,
            'alias' => $this->channelAlias,
            'create_time' => date('Y-m-d H:i:s'),
        ];
        $this->insert($tableName, $tableValue);

        // 获取插入渠道的渠道ID
        $channelId = (new \yii\db\Query())
            ->select(['id'])
            ->from($tableName)
            ->where(['alias' => $this->channelAlias])
            ->scalar();

        // 原则上，所有表使用InnoDB引擎
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        // 用户表
        // id作为普通的uid，由于业务可能存在多渠道uid重叠问题，采用uuid的概念，将uuid为全局唯一用户ID
        $tableName = "{{%{$channelId}_users}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'uuid' => $this->char(32)->notNull()->comment('全局唯一用户ID'),
            'cuid' => $this->string(64)->notNull()->comment('渠道方用户ID'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('uuid', $tableName, ['uuid'], true);
        $this->createIndex('cuid', $tableName, ['cuid'], true);
        $this->createIndex('create_time', $tableName, ['create_time']);
        $this->addCommentOnTable($tableName, '渠道用户表');

        // 用户送货地址表
        // 不使用省市区字段设计而使用`dist_table`以及`dist_id`的设计是为了方便扩展及格式修改（比如外国不一样省市区划分区域）
        $tableName = "{{%{$channelId}_user_addresses}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('渠道唯一用户ID（us_[channel_id]_users->id）'),
            'consignee' => $this->string(32)->notNull()->comment('收件人'),
            'phone_no' => $this->string(32)->notNull()->comment('联系电话'),
            'zip_code' => $this->string(16)->notNull()->comment('邮政编码'),
            'dist_table' => $this->char(8)->notNull()->comment('对应地区编码表编号（us_[表号]_districts）'),
            'dist_id' => $this->bigInteger()->unsigned()->notNull()->comment('地区编码，一般为镇县区，递归出省市（us_[表号]_districts->id）'),
            'addr_detail' => $this->string(256)->notNull()->comment('详细地址'),
            'addr_stat' => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('地址状态：0不可用，1正常'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('user_id', $tableName, ['user_id']);
        $this->addCommentOnTable($tableName, '渠道用户送货地址表');

        // 订单表
        // `order_no`一般订单号，业务使用单号
        $tableName = "{{%{$channelId}_orders}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('渠道唯一用户ID（us_[channel_id]_users->id）'),
            'order_no' => $this->char(20)->notNull()->comment('订单号'),
            'order_amt' => $this->decimal(10, 2)->unsigned()->notNull()->defaultValue(0)->comment('订单总金额'),
            'order_stat' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('订单状态（状态定义注意，正数状态必须为支付过的状态，否则设为负数状态）：-2已退款（全额或部分），-1已取消（释放库存），0等待支付，1已支付，2已完成'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('order_no', $tableName, ['order_no'], true);
        $this->createIndex('user_id', $tableName, ['user_id']);
        $this->createIndex('create_time', $tableName, ['create_time']);
        $this->addCommentOnTable($tableName, '渠道订单表');

        // 订单商品表
        // `order_no`关联`us_[channel_id]_orders`（字段命名要统一）
        // 若商品为附赠商品或组合商品，插入子商品条项并填写`parent_id`
        // `virt_code`注意格式["sample001","sample002"]，不是虚拟商品则为''
        // 例，样本00001为2个样本00002和1个样本00003的组合商品，其中样本00003是虚拟商品
        // id|user_id| order_no  |parent_id|parent_type|item_id|item_sn|item_name|item_mkt_price|item_pur_price|item_xfer_price|item_number|item_type|item_is_virt|        virt_code        |create_time
        // 1 |   4   |SP123456789|    0    |     0     |   1   |SP00001|样本00001|     29.9     |     19.9     |     19.9      |     2     |    4    |      0     |                         |2019-01-30 16:55:26
        // 2 |   4   |SP123456789|    1    |     4     |   2   |SP00002|样本00002|      9.9     |      4.9     |       0       |     4     |    1    |      0     |                         |2019-01-30 16:56:32
        // 3 |   4   |SP123456789|    1    |     4     |   3   |SP00003|样本00003|     29.9     |     19.9     |       0       |     2     |    1    |      1     |["sample001","sample002"]|2019-01-30 16:57:23
        $tableName = "{{%{$channelId}_order_items}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('渠道唯一用户ID（us_[channel_id]_users->id）'),
            'order_no' => $this->char(20)->notNull()->comment('订单号（us_[channel_id]_orders->order_no）'),
            'parent_id' => $this->bigInteger()->unsigned()->notNull()->comment('所隶属的本表商品项的主键（us_[channel_id]_order_items->id），0为不隶属'),
            'parent_type' => $this->tinyInteger()->unsigned()->notNull()->comment('所隶属的本表商品项的商品类型，类型值[二进制值]，往后值请使用二进制值进位，目的是处理可能会有重叠类型的商品：0[0]不隶属，1[1]一般商品（一般没有），2[10]附赠商品，4[100]组合商品，8[1000]加价购'),
            'item_id' => $this->integer()->unsigned()->notNull()->comment('商品ID'),
            'item_sn' => $this->string(32)->notNull()->comment('商品编号'),
            'item_name' => $this->string(128)->notNull()->comment('商品名称'),
            'item_warehouse_id' => $this->integer()->unsigned()->notNull()->comment('仓库ID'),
            'item_supplier_id' => $this->integer()->unsigned()->notNull()->comment('供应商ID'),
            'item_mkt_price' => $this->decimal(10, 2)->unsigned()->notNull()->comment('商品市场单价'),
            'item_pur_price' => $this->decimal(10, 2)->unsigned()->notNull()->comment('商品购买单价'),
            'item_xfer_price' => $this->decimal(10, 2)->unsigned()->notNull()->comment('商品最终交易单价'),
            'item_number' => $this->smallInteger(5)->unsigned()->notNull()->comment('商品数量'),
            'item_type' => $this->tinyInteger()->unsigned()->notNull()->comment('商品类型，类型值[二进制值]，往后值请使用二进制值进位，目的是处理可能会有重叠类型的商品：1[1]一般商品，2[10]附赠商品，4[100]组合商品，8[1000]加价购'),
            'item_is_virt' => $this->tinyInteger(1)->notNull()->comment('是否虚拟商品：0实物商品，1虚拟商品'),
            'virt_code' => $this->text()->notNull()->comment('所包含的虚拟码，请存JSON，格式样本：["sample001","sample002"]'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('order_no,parent_id,item_id', $tableName, ['order_no', 'parent_id', 'item_id'], true);
        $this->createIndex('user_id', $tableName, ['user_id']);
        $this->createIndex('order_no', $tableName, ['order_no']);
        $this->createIndex('parent_id', $tableName, ['parent_id']);
        $this->createIndex('item_id', $tableName, ['item_id']);
        $this->createIndex('create_time', $tableName, ['create_time']);
        $this->addCommentOnTable($tableName, '渠道订单商品表');

        // 订单送货表
        // 这里重复`us_[channel_id]_user_addresses`的`consignee`等字段的原因是，用户的地址可能会有更新修改
        // `order_no`关联`us_[channel_id]_orders`（字段命名要统一）
        // `ship_no`送货订单号
        // `ship_sn`物流方的订单号，快递单号
        $tableName = "{{%{$channelId}_order_shipping}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('渠道唯一用户ID（us_[channel_id]_users->id）'),
            'consignee' => $this->string(32)->notNull()->comment('收件人'),
            'phone_no' => $this->string(32)->notNull()->comment('联系电话'),
            'zip_code' => $this->string(16)->notNull()->comment('邮政编码'),
            'dist_table' => $this->char(8)->notNull()->comment('对应地区编码表编号（us_[表号]_districts）'),
            'dist_id' => $this->bigInteger()->unsigned()->notNull()->comment('地区编码，一般为镇县区，递归出省市（us_[表号]_districts->id）'),
            'addr_detail' => $this->string(256)->notNull()->comment('详细地址'),
            'message' => $this->string(256)->notNull()->comment('会员下单留言'),
            'order_no' => $this->char(20)->notNull()->comment('订单号（us_[channel_id]_orders->order_no）'),
            'ship_no' => $this->char(20)->notNull()->comment('送货订单号'),
            'ship_sn' => $this->string(64)->notNull()->comment('快递单号（物流商物流单号）'),
            'ship_way' => $this->string(32)->notNull()->comment('发货方式（物流商名称）'),
            'ship_fee' => $this->decimal(10, 2)->unsigned()->notNull()->comment('快递费用（物流商物流费用）'),
            'ship_ntime' => $this->dateTime()->notNull()->comment('发货时间'),
            'ship_stat' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('快递状态：-1异常件，0未支付，1待发货，2已发货，3已签收'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
            'locked_time' => $this->dateTime()->notNull()->comment('锁定时间，事务处理中有值'), // 感兴趣请全局搜索，MySQL8.0以下不支持SKIP LOCKED，故以此实现
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('order_no', $tableName, ['order_no'], true);
        $this->createIndex('ship_no', $tableName, ['ship_no'], true);
        $this->createIndex('user_id', $tableName, ['user_id']);
        $this->createIndex('ship_sn', $tableName, ['ship_sn']);
        $this->createIndex('ship_ntime', $tableName, ['ship_ntime']);
        $this->createIndex('create_time', $tableName, ['create_time']);
        $this->createIndex('locked_time', $tableName, ['locked_time']);
        $this->addCommentOnTable($tableName, '渠道订单送货表');

        // 支付交易表
        // 用于记录各种支付方式的交易订单表
        // `xfer_no`支付订单号，对接支付商的的单号
        // `xfer_ext`用于记录请求后返回的可能需要记录的参数
        $tableName = "{{%{$channelId}_transfer}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('渠道唯一用户ID（us_[channel_id]_users->id）'),
            'payment_id' => $this->integer()->unsigned()->notNull()->comment('支付方式（us_payments->id）'),
            'account_id' => $this->integer()->unsigned()->notNull()->comment('结算账户（us_accounts->id）'),
            'xfer_no' => $this->char(20)->notNull()->comment('支付订单号'),
            'xfer_amt' => $this->decimal(10, 2)->unsigned()->notNull()->defaultValue(0)->comment('交易总金额'),
            'xfer_sn' => $this->string(64)->notNull()->comment('支付商交易流水号'),
            'xfer_ext' => $this->text()->notNull()->comment('（选填，请存JSON）记录支付额外参数'),
            'xfer_stat' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('订单支付状态：0等待支付，1支付成功'),
            'xfer_ntime' => $this->dateTime()->notNull()->comment('支付成功通知时间'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('xfer_no', $tableName, ['xfer_no'], true);
        $this->createIndex('user_id', $tableName, ['user_id']);
        $this->createIndex('xfer_sn', $tableName, ['xfer_sn']);
        $this->createIndex('xfer_ntime', $tableName, ['xfer_ntime']);
        $this->createIndex('create_time', $tableName, ['create_time']);
        $this->addCommentOnTable($tableName, '渠道支付交易表');

        // 订单支付关联表
        // 多对多关系，需要关联表
        // `xfer_id`支付表ID，`order_id`订单表ID
        $tableName = "{{%{$channelId}_transfer_relations}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'xfer_id' => $this->integer()->unsigned()->notNull()->comment('渠道支付交易表ID（us_[channel_id]_transfer->id）'),
            'order_id' => $this->integer()->unsigned()->notNull()->comment('渠道订单表ID（us_[channel_id]_orders->id）'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('xfer_id,order_id', $tableName, ['xfer_id', 'order_id'], true);
        $this->addCommentOnTable($tableName, '渠道订单支付关联表');

        // 交易退款表
        // 用于记录各种支付方式的退款交易订单表
        // `refund_no`退款订单号，对接支付商退款使用的单号（有些渠道不允许与请求支付的订单号重复）
        // `refund_ext`用于记录请求后返回的可能需要记录的参数
        $tableName = "{{%{$channelId}_refunds}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('渠道唯一用户ID（us_[channel_id]_users->id）'),
            'payment_id' => $this->integer()->unsigned()->notNull()->comment('支付方式（us_payments->id）'),
            'account_id' => $this->integer()->unsigned()->notNull()->comment('结算账户（us_accounts->id）'),
            'refund_no' => $this->char(20)->notNull()->comment('退款订单号'),
            'refund_amt' => $this->decimal(10, 2)->unsigned()->notNull()->comment('退款总金额'),
            'refund_sn' => $this->string(64)->notNull()->comment('支付商退款流水号'),
            'refund_ext' => $this->text()->notNull()->comment('（选填，请存JSON）记录退款额外参数'),
            'refund_stat' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('退款状态：-1取消申请，0申请退款，1退款成功'),
            'refund_ntime' => $this->dateTime()->notNull()->comment('退款成功通知时间'),
            'refund_remark' => $this->string(256)->notNull()->comment('退款备注'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('refund_no', $tableName, ['refund_no'], true);
        $this->createIndex('user_id', $tableName, ['user_id']);
        $this->createIndex('refund_sn', $tableName, ['refund_sn']);
        $this->createIndex('refund_ntime', $tableName, ['refund_ntime']);
        $this->createIndex('create_time', $tableName, ['create_time']);
        $this->addCommentOnTable($tableName, '渠道交易退款表');

        // 交易退款关联表
        // `xfer_id`支付表ID，`order_id`订单表ID，`order_item_id`订单商品表ID
        $tableName = "{{%{$channelId}_refund_relations}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'refund_id' => $this->integer()->unsigned()->notNull()->comment('渠道交易退款表ID（us_[channel_id]_refunds->id）'),
            'xfer_id' => $this->integer()->unsigned()->notNull()->comment('渠道支付交易表ID（us_[channel_id]_transfer->id）'),
            'order_id' => $this->integer()->unsigned()->notNull()->comment('渠道订单表ID（us_[channel_id]_orders->id）'),
            'order_item_id' => $this->integer()->unsigned()->notNull()->comment('渠道订单商品表ID（us_[channel_id]_order_items->id）'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('refund_id,xfer_id,order_id,order_item_id', $tableName, ['refund_id', 'xfer_id', 'order_id', 'order_item_id'], true);
        $this->addCommentOnTable($tableName, '渠道交易退款关联表');

        // 订单金额调节表
        $tableName = "{{%{$channelId}_order_adjustments}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'order_no' => $this->char(20)->notNull()->comment('订单号（us_[channel_id]_orders->order_no）'),
            'adjust_type' => $this->tinyInteger()->notNull()->comment('调整类型：1运费，2优惠'),
            'adjust_name' => $this->string(32)->notNull()->comment('调整名称'),
            'adjust_detail' => $this->string(128)->notNull()->comment('调整说明'),
            'adjust_behavior' => $this->string(256)->notNull()->comment('调整行为'),
            'pre_adjust_amt' => $this->decimal(10, 2)->notNull()->comment('预调整金额'),
            'act_adjust_amt' => $this->decimal(10, 2)->notNull()->comment('实际调整金额'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('order_no', $tableName, ['order_no']);
        $this->createIndex('adjust_behavior', $tableName, ['adjust_behavior']);
        $this->createIndex('create_time', $tableName, ['create_time']);
        $this->addCommentOnTable($tableName, '订单金额调节表');

        // 渠道商品评论表
        $tableName = "{{%{$channelId}_product_reviews}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('渠道唯一用户ID（us_[channel_id]_users->id）'),
            'order_no' => $this->char(20)->notNull()->comment('订单号（us_[channel_id]_orders->order_no）'),
            'item_id' => $this->integer()->unsigned()->notNull()->comment('商品ID'),
            'comment' => $this->text()->notNull()->comment('商品评价'),
            'grade' => $this->tinyInteger(2)->notNull()->comment('评分（星评）'),
            'valid' => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('是否有效：0无效，1有效'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('order_no,item_id', $tableName, ['order_no', 'item_id'], true);
        $this->createIndex('user_id', $tableName, ['user_id']);
        $this->addCommentOnTable($tableName, '渠道商品评论表');

        /* #优化#以后开发商品库时的表
        // 渠道商品表
        $tableName = "{{%{$channelId}_merchandises}}";
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'item_id' => $this->integer()->unsigned()->notNull()->comment('商品ID（SKU的ID）'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('item_id', $tableName, ['item_id']);
        $this->addCommentOnTable($tableName, '渠道商品表'); */
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "M190802075206CreateChannel1Tables cannot be reverted.\n";
        $channel = (new \yii\db\Query())
            ->from('{{%channels}}')
            ->where(['alias' => $this->channelAlias])
            ->one();
        echo "channel info:\n";
        foreach ($channel as $key => $value) {
            echo "\t" . $key . ': ' . $value . "\n";
        }
        echo "included tables:\n";
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_users}}");
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_user_addresses}}");
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_orders}}");
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_order_items}}");
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_order_shipping}}");
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_transfer}}");
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_transfer_relations}}");
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_refunds}}");
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_refund_relations}}");
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_order_adjustments}}");
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_product_reviews}}");
        /* #优化#以后开发商品库时的表
        echo "\t" . static::getDb()->schema->getRawTableName("{{%{$channel['id']}_merchandises}}"); */

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "M190802075206CreateChannel1Tables cannot be reverted.\n";

        return false;
    }
    */
}
