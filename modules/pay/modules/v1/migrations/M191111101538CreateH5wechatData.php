<?php

use yii\db\Migration;

/**
 * Class M191111101538CreateH5wechatData
 */
class M191111101538CreateH5wechatData extends Migration
{
    private $paymentName = '微信H5支付';
    private $paymentAlias = 'h5wechat';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 添加支付方式
        $tableName = '{{%payments}}';
        $tableValue = [
            'name' => $this->paymentName,
            'alias' => $this->paymentAlias,
            'create_time' => date('Y-m-d H:i:s'),
        ];
        $this->insert($tableName, $tableValue);

        // 获取插入渠道的渠道ID
        $paymentId = (new \yii\db\Query())
            ->select(['id'])
            ->from($tableName)
            ->where(['alias' => $this->paymentAlias])
            ->scalar();

        // 获取配置
        $config = require __DIR__ . '/../payments/h5wechat/libs/config/params.php';
        foreach ($config['merchant'] as $merchantId => $value) {
            // 添加商户号
            $tableName = '{{%payment_accounts}}';
            $tableValue = [
                'payment_id' => $paymentId,
                'merchant_id' => $merchantId,
                'depend_on' => '',
                'binding_bank_name' => '招商银行',
                'binding_bank_account' => '6200000000000001',
                'account_info' => '',
                'remark' => '',
                'create_time' => date('Y-m-d H:i:s'),
            ];
            $this->insert($tableName, $tableValue);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "M190803062331CreateH5wechatDatas cannot be reverted.\n";
        $payment = (new \yii\db\Query())
            ->from('{{%payments}}')
            ->where(['alias' => $this->paymentAlias])
            ->one();
        echo "payment info:\n";
        foreach ($payment as $key => $value) {
            echo "\t" . $key . ': ' . $value . "\n";
        }
        $account = (new \yii\db\Query())
            ->from('{{%payment_accounts}}')
            ->where(['payment_id' => $payment['id']])
            ->all();
        echo "account info:\n";
        $message = '';
        foreach ($account as $row) {
            foreach ($row as $key => $value) {
                $message .= "\t" . $key . ': ' . $value . "\n";
            }
            $message .= "\n";
        }
        echo rtrim($message);

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "M191111101538CreateH5wechatData cannot be reverted.\n";

        return false;
    }
    */
}
