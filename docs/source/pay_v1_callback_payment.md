##接收支付回调
<br>

#### 接口功能
> 交易服务商的支付回调接收

#### 接口说明
> 按照交易服务商约定进行对接并提供接口给交易方，注意给出链接时要将\[masked_code\]替换为Yii::$app->security->maskToken(\[渠道代号\],\[支付账户ID\])，将我方的渠道代码以及支付账户ID对交易服务提供商进行保密

#### URL
> [https://api.sample.com/pay/v1/callback/payment/\[masked_code\]](https://api.sample.com/pay/v1/callback/payment/\[masked_code\])

#### 请求方式
> 按服务商约定

#### 请求header参数
> 按服务商约定

#### 请求body参数
> 按服务商约定

#### 接口示例
> 无示例