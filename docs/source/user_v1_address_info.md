##用户地址 - 信息
<br>

#### 接口功能
> 获取用户地址信息

#### URL
> [https://api.sample.com/user/v1/address/info](https://api.sample.com/user/v1/address/info)

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
|id|true|int|地址ID|

#### 响应header参数
> |参数|类型|说明|
|:-----|:-----|-----|
|X-Unionsystem-Script|string|待执行脚本|

#### 响应body参数
```
{
    "id":"15",
    "consignee":"张三",
    "phoneNo":"12345678910",
    "zipCode":null,
    "districtTable":"cn2017",
    "districtInfo":[
        {
            "id":"130000000000",
            "name":"河北省",
            "type":"1"
        },
        {
            "id":"130100000000",
            "name":"石家庄市",
            "type":"2"
        },
        {
            "id":"130184000000",
            "name":"新乐市",
            "type":"3"
        }
    ],
    "addressDetail":"XX花园XX号"
}
```

> |参数|类型|说明|
|:-----|:-----|-----|
|id|int|地址ID|
|consignee|string|收货人姓名|
|phoneNo|string|收货人手机号|
|zipCode|string|邮编，非必须参数|
|districtTable|string|地区表版本|
|districtInfo|array|地址地区信息|
|addressDetail|string|详细地址|

> |参数|类型|说明|
|:-----|:-----|-----|
|id|int|地区ID|
|name|string|地区名称|
|type|int|地区类型，1省，2市，3镇县区|

#### 接口示例
``` javascript
<script type="text/javascript">
    $.ajax({
        url: "https://api.sample.com/user/v1/address/info",
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