##查询物流跟踪信息
<br>

#### 接口功能
> 获取对应订单的物流跟踪信息

#### URL
> [https://api.sample.com/logis/v1/track/info](https://api.sample.com/logis/v1/track/info)

#### 请求方式
> POST

#### 请求header参数
> |参数|必选|类型|说明|
|:-----|:-----|:-----|-----|
|Accept|true|string|application/json或application/xml，将返回json或xml格式|
|X-Api-Key|true|string|登陆完成后获取的接口key或token，将在渠道完成登陆跳转时传给web端，每个key有效期为6小时|
|X-Unionsystem-Channel|true|string|渠道代号|
|X-Unionsystem-Referer|false|string|请求时的当前页地址|
|X-Unionsystem-Os|false|string|客户端操作系统，ios或android|

#### 请求body参数
> |参数|必选|类型|说明|
|:-----|:-----|:-----|-----|
|orderNo|true|string|订单号|

#### 响应header参数
> |参数|类型|说明|
|:-----|:-----|-----|
|X-Unionsystem-Script|string|待执行脚本|

#### 响应body参数
```
[
    {
        "time": "2016-10-26 18:31:38",
        "message": "【北京环铁站】的【互优图书】已收件"
    }, 
    {
        "time": "2016-10-26 19:53:50",
        "message": "快件在【北京环铁站】装车,正发往【北京分拨中心】"
    },
    {
        "time": "2016-10-26 21:00:13",
        "message": "快件到达【北京分拨中心】,上一站是【北京环铁站】"
    },
    {
        "time": "2016-10-26 21:06:27",
        "message": "快件在【北京分拨中心】装车,正发往【青州分拨中心】"
    }
]
```

> |参数|类型|说明|
|:-----|:-----|-----|
|time|datetime|时间日期|
|message|string|物流信息|

#### 接口示例
``` javascript
<script type="text/javascript">
    $.ajax({
        url: "https://api.sample.com/logis/v1/track/info",
        headers: {
            "Accept": "application/json",
            "X-Api-Key": "c3f73e5df26e1740ae71e64cfa779d27",
            "X-Unionsystem-Channel": "channel1",
            "X-Unionsystem-Referer": "https://app.sample.com/module/comtroller/action",
            "X-Unionsystem-Os": "ios",
        },
        type: "POST",
        dataType: "JSON",
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
            var script = XMLHttpRequest.getResponseHeader('X-Unionsystem-Script');
            if (script) {
                script = $(script).html();
                if (window.execScript) {
                    window.execScript(script);
                } else {
                    window.eval(script);
                }
            }
        }
    });
</script>