##接收支付回调
<br>

#### 接口功能
> 物流跟踪的订阅回调接收

#### 接口说明
> 物流跟踪订阅后提供给服务商推送数据的接口，注意给出链接时要将\[masked_code\]替换为Yii::$app->security->maskToken(\[服务商代号\])，服务商代号如“kdniao”。

#### URL
> [https://api.sample.com/logis/v1/callback/track/\[masked_code\]](https://api.sample.com/logis/v1/callback/track/\[masked_code\])

#### 请求方式
> 按服务商约定

#### 请求header参数
> 按服务商约定

#### 请求body参数
> 按服务商约定

#### 接口示例
> 无示例