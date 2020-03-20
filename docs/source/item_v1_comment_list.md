##商品评价 - 列表
<br>

#### 接口功能
> 获取商品评价列表

#### URL
> [https://api.sample.com/item/v1/comment/list](https://api.sample.com/item/v1/comment/list)

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

#### 请求body参数
> |参数|必选|类型|说明|
|:-----|:-----|:-----|-----|
|itemId|true|int|商品ID|
|sort|false|string|按某数据列排序，仅可选“grade”或“datetime”，分别代表以评分排列和按时间排列，默认以评分排列|
|order|false|string|仅可选“asc”或“desc”，分别代表正序和倒序，默认倒序|

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
        "grade":"10",
        "comment":"太赞了",
        "buyer":"陈***",
        "phoneNo":"126***8541",
        "datetime":"2019-12-05",
    },
    {
        "grade":"7",
        "comment":"还好吧",
        "buyer":"王***",
        "phoneNo":"137***1234",
        "datetime":"2019-12-05",
    },
]
```

> |参数|类型|说明|
|:-----|:-----|-----|
|grade|int|评分（星评），可以一分当作一星，也可以一分当作半星，如7分代表3.5星|
|comment|string|用户评价|
|buyer|string|买家加密收货人|
|phoneNo|string|买家加密手机号|
|datetime|string|评价创建时间|

#### 接口示例
``` javascript
<script type="text/javascript">
    $.ajax({
        url: "https://api.sample.com/item/v1/comment/list",
        headers: {
            "Accept": "application/json",
            "X-Api-Key": "c3f73e5df26e1740ae71e64cfa779d27",
            "X-Unionsystem-Channel": "channel1",
            "X-Unionsystem-Referer": "https://app.sample.com/module/comtroller/action",
            "X-Unionsystem-Os": "ios",
            "X-Unionsystem-List-Page": 1,
            "X-Unionsystem-List-Pagesize": 15,
        },
        data: {
            "itemId": "15291",
            "sort": "grade",
            "order": "desc",
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