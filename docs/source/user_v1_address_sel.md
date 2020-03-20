##地区选择列表
<br>

#### 接口功能
> 获取地区下级选择列表

#### URL
> [https://api.sample.com/user/v1/address/sel](https://api.sample.com/user/v1/address/sel)

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
|districtTable|true|string|地区表版本，默认“cn2017”|
|districtId|true|int|地区ID|

#### 响应header参数
> |参数|类型|说明|
|:-----|:-----|-----|
|X-Unionsystem-Script|string|待执行脚本|

#### 响应body参数
```
[
    {
        "id":"440111000000",
        "name":"白云区",
        "type":"3"
    },
    {
        "id":"440117000000",
        "name":"从化区",
        "type":"3"
    },
    {
        "id":"440113000000",
        "name":"番禺区",
        "type":"3"
    },
    {
        "id":"440105000000",
        "name":"海珠区",
        "type":"3"
    },
    {
        "id":"440114000000",
        "name":"花都区",
        "type":"3"
    },
    {
        "id":"440112000000",
        "name":"黄埔区",
        "type":"3"
    },
    {
        "id":"440103000000",
        "name":"荔湾区",
        "type":"3"
    },
    {
        "id":"440115000000",
        "name":"南沙区",
        "type":"3"
    },
    {
        "id":"440101000000",
        "name":"市辖区",
        "type":"3"
    },
    {
        "id":"440106000000",
        "name":"天河区",
        "type":"3"
    },
    {
        "id":"440104000000",
        "name":"越秀区",
        "type":"3"
    },
    {
        "id":"440118000000",
        "name":"增城区",
        "type":"3"
    }
]
```

> |参数|类型|说明|
|:-----|:-----|-----|
|id|int|地区ID|
|name|string|地区名称|
|type|int|地区类型，1省，2市，3镇县区|

#### 接口示例
``` javascript
<script type="text/javascript">
    $.ajax({
        url: "https://api.sample.com/user/v1/address/sel",
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