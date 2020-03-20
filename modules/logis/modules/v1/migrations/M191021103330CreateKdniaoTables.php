<?php

use yii\db\Migration;

/**
 * Class M191021103330CreateKdniaoTables
 */
class M191021103330CreateKdniaoTables extends Migration
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

        // 快递鸟物流公司编码映射表
        $tableName = 'track_kdn_couriers_map';
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'courier_name' => $this->string(32)->notNull()->comment('物流商名称'),
            'courier_code' => $this->string(32)->notNull()->comment('物流商编码'),
            'rel_courier_name' => $this->string(32)->notNull()->comment('物流商关联名称'),
            'credibility' => $this->tinyInteger()->notNull()->comment('可信积分，达到50即可采用对应数据，不进行请求获取'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('courier_name', $tableName, ['courier_name']);
        $this->createIndex('courier_code', $tableName, ['courier_code']);
        $this->createIndex('rel_courier_name', $tableName, ['rel_courier_name']);
        $this->addCommentOnTable($tableName, '快递鸟物流公司编码映射表');

        // 快递鸟物流追踪信息表
        $tableName = 'track_kdn_shipping_information';
        $tableColumns = [
            'id' => $this->primaryKey()->unsigned()->comment('主键'),
            'courier_code' => $this->string(32)->notNull()->comment('物流商编码'),
            'shipment_number' => $this->string(64)->notNull()->comment('物流商单号'),
            'identification_way' => $this->tinyInteger(1)->unsigned()->notNull()->comment('识别物流商的方式：1映射表（mapping），2接口请求（request），3模糊（fuzzy）'),
            'identification_record' => $this->text()->notNull()->comment('（JSON）可能的物流商记录，单号识别API返回结果'),
            'subscription_stat' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('订阅状态：-1订阅异常，0待订阅，1订阅成功'),
            'subscription_err' => $this->string(32)->notNull()->comment('订阅异常信息'),
            'subscription_time' => $this->dateTime()->notNull()->comment('订阅时间'),
            'probably_abnormal' => $this->dateTime()->notNull()->comment('检测到可能为异常条项的时间'),
            'trace_call' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否已接收到推送：0尚未接收到推送，1已接收到推送'),
            'trace_state' => $this->tinyInteger(1)->notNull()->comment('推送物流状态：0无轨迹，1已揽收，2在途中，3签收，4问题件'),
            'trace_state_ex' => $this->smallInteger(3)->notNull()->comment('推送物流状态EX：1已揽收，2在途中（201到达派件城市，202派件中，211已放入快递柜或驿站），3已签收（301正常签收，302派件异常后最终签收，304代收签收，311快递柜或驿站签收），4问题件（401发货无信息，402超时未签收，403超时未更新，404拒收（退件），405派件异常，406退货签收，407退货未签收，412快递柜或驿站超时未取）'),
            'trace_detail' => $this->text()->notNull()->comment('推送物流跟踪信息'),
            'trace_update_time' => $this->dateTime()->notNull()->comment('推送最新更新时间'),
            'create_time' => $this->dateTime()->notNull()->comment('创建时间'),
        ];
        $this->createTable($tableName, $tableColumns, $tableOptions);
        $this->createIndex('courier_code,shipment_number', $tableName, ['courier_code', 'shipment_number'], true);
        $this->addCommentOnTable($tableName, '快递鸟物流追踪信息表');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "M191021103330CreateKdniaoTables cannot be reverted.\n";
        echo "included tables:\n";
        echo "\ttrack_kdn_couriers_map";
        echo "\ttrack_kdn_shipping_information";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "M191021103330CreateKdniaoTables cannot be reverted.\n";

        return false;
    }
    */
}
