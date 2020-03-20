##登陆完成跳转
<br>

#### 接口功能
> 登陆完成后回调

#### 接口说明
> 登陆完成后由渠道客户端回调此接口进行跳转，注意给出链接时要将\[masked_code\]替换为Yii::$app->security->maskToken(\[渠道代号\])，将我方的渠道代码对渠道进行保密

#### URL
> [https://api.sample.com/login/v1/callback/login/\[masked_code\]](https://api.sample.com/login/v1/callback/login/\[masked_code\])

#### 请求方式
> 按渠道约定

#### 请求header参数
> 按渠道约定

#### 请求body参数
> 按渠道约定

#### 接口示例
> 无示例