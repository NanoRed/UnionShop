##用户地址 - 列表
<br>

#### 接口功能
> 获取用户地址列表

#### URL
> [https://api.sample.com/user/v1/address/list](https://api.sample.com/user/v1/address/list)

#### 请求方式
> POST

#### 请求header参数
> |参数|必选|类型|说明|
|:-----|:-----|:-----|-----|
|Accept|true|string|application/json或application/xml，将返回json或xml格式|
|X-Api-Key|true|string|登陆完成后获取的接口key或token，将在渠道完成登陆跳转时传给web端，每个key有效期为6小时|
|X-Unionsystem-Channel|true|string|渠道代号|
|X-Unionsystem-List-Page|true|int|页码，默认1|
|X-Unionsystem-List-Pagesize|true|int|每页数据量，默认15|
|X-Unionsystem-Referer|false|string|请求时的当前页地址|
|X-Unionsystem-Os|false|string|客户端操作系统，ios或android|

#### 响应header参数
> |参数|类型|说明|
|:-----|:-----|-----|
|X-Unionsystem-List-Currentcount|int|当页数据量|
|X-Unionsystem-List-Page|int|当前页码|
|X-Unionsystem-List-Pagesize|int|每页数据容量|
|X-Unionsystem-List-Totalcount|int|总数据量|
|X-Unionsystem-Script|string|待执行脚本|

#### 响应body参数
```
[
    {
        "id":"14",
        "consignee":"李四",
        "phoneNo":"12345678910",
        "zipCode":null,
        "districtTable":"cn2017",
        "districtInfo":[
            {
                "id":"440000000000",
                "name":"广东省",
                "type":"1"
            },
            {
                "id":"440100000000",
                "name":"广州市",
                "type":"2"
            },
            {
                "id":"440106000000",
                "name":"天河区",
                "type":"3"
            }
        ],
        "addressDetail":"XX街道XX号",
        "createTime":"2019-07-12 11:08:01"
    },
    {
        "id":"13",
        "consignee":"张三",
        "phoneNo":"12345678910",
        "zipCode":null,
        "districtTable":"cn2017",
        "districtInfo":[
            {
                "id":"440000000000",
                "name":"广东省",
                "type":"1"
            },
            {
                "id":"440100000000",
                "name":"广州市",
                "type":"2"
            },
            {
                "id":"440106000000",
                "name":"天河区",
                "type":"3"
            }
        ],
        "addressDetail":"XX花园XX号",
        "createTime":"2019-07-12 10:39:45"
    },
]
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
|createTime|string|地址创建时间|

> |参数|类型|说明|
|:-----|:-----|-----|
|id|int|地区ID|
|name|string|地区名称|
|type|int|地区类型，1省，2市，3镇县区|

#### 接口示例
``` javascript
<script type="text/javascript">
    $.ajax({
        url: "https://api.sample.com/user/v1/address/list",
        headers: {
            "Accept": "application/json",
            "X-Api-Key": "c3f73e5df26e1740ae71e64cfa779d27",
            "X-Unionsystem-Channel": "channel1",
            "X-Unionsystem-Referer": "https://app.sample.com/module/comtroller/action",
            "X-Unionsystem-Os": "ios",
            "X-Unionsystem-List-Page": 1,
            "X-Unionsystem-List-Pagesize": 15,
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