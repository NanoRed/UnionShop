##重新支付
<br>

#### 接口功能
> 客户对已存在的订单进行重新支付

#### 请求频率
> `100times/10min`

#### URL
> [https://api.sample.com/order/v1/generate/re-payment](https://api.sample.com/order/v1/generate/re-payment)

#### 请求方式
> POST

#### 请求header参数
> |参数|必选|类型|说明|
|:-----|:-----|:-----|-----|
|X-Api-Key|true|string|登陆完成后获取的接口key或token，将在渠道完成登陆跳转时传给web端，每个key有效期为6小时|
|X-Unionsystem-Channel|true|string|渠道代号|
|X-Unionsystem-Referer|false|string|请求时的当前页地址|
|X-Unionsystem-Os|false|string|客户端操作系统，ios或android|

#### 请求body参数
> |参数|必选|类型|说明|
|:-----|:-----|:-----|-----|
|orderNo|true|string|订单号|
|paymentAccount|true|int|支付的账户ID|

#### 响应header参数
> |参数|类型|说明|
|:-----|:-----|-----|
|X-Unionsystem-Script|string|待执行脚本|

#### 接口示例
``` javascript
<script type="text/javascript">
    $.ajax({
        url: "https://api.sample.com/order/v1/generate/re-payment",
        headers: {
            "X-Api-Key": "c3f73e5df26e1740ae71e64cfa779d27",
            "X-Unionsystem-Channel": "channel1",
            "X-Unionsystem-Referer": "https://app.sample.com/module/comtroller/action"
            "X-Unionsystem-Os": "ios",
        },
        data: {
            "orderNo": "SP2019154522987942",
            "paymentAccount": 1,
        },
        type: "POST",
        success: function (data) {
            console.log(data);
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            if (errorThrown) {
                alert(decodeURI(errorThrown));
            } else {
                alert("未知错误");
            }
        },
        complete: function(XMLHttpRequest){
            var execute = XMLHttpRequest.getResponseHeader('X-Unionsystem-Script');
            if (execute) {
                if (window.execScript) {
                    window.execScript(execute);
                } else {
                    window.eval(execute);
                }
            }
        }
    });
</script>