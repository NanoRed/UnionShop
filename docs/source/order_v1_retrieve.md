##订单列表
<br>

#### 接口功能
> 获取客户的订单列表数据

#### URL
> [https://api.sample.com/order/v1/retrieve](https://api.sample.com/order/v1/retrieve)

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
|orderStatus|false|int|筛选该订单状态的订单，默认为所有订单，-3已全额退款订单，-2已部分退款订单，-1已取消（释放库存）订单，0等待支付订单，1已支付订单，2已收货订单，3已评价订单|

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
[{
	"orderId": "18",
	"orderNo": "SP2019154523157402",
	"orderAmt": "50.00",
	"orderCyUnit": "元",
	"orderStat": "0",
	"orderStatDes": "等待支付",
	"orderCTime": "2019-06-04 14:31:38",
	"orderItems": [{
		"itemId": "5750",
		"itemName": "商品1",
		"itemCost": "80.00",
		"itemNum": "6",
		"isVirt": false,
		"virtCode": null
	}],
	"leavingMessage": "速度发货哦",
	"shippingWay": "顺丰快递",
	"shippingSn": "10000000000000000",
	"shipmentConsignee": "小明",
	"consigneePhoneNo": "12345678910",
	"shippingZipCode": "510000",
	"shippingAddress": "广东省 广州市 XX街道XX号",
	"shippingStat": "0",
	"shippingStatDes": "未付款"
}, {
	"orderId": "17",
	"orderNo": "SP2019154523132408",
	"orderAmt": "50.00",
	"orderCyUnit": "元",
	"orderStat": "0",
	"orderStatDes": "等待支付",
	"orderCTime": "2019-06-04 14:31:53",
	"orderItems": [{
		"itemId": "5750",
		"itemName": "商品1",
		"itemCost": "80.00",
		"itemNum": "6",
		"isVirt": false,
		"virtCode": null
	}],
	"leavingMessage": "速度发货哦",
	"shippingWay": "顺丰快递",
	"shippingSn": "10000000000000000",
	"shipmentConsignee": "小明",
	"consigneePhoneNo": "12345678910",
	"shippingZipCode": "510000",
	"shippingAddress": "广东省 广州市 XX街道XX号",
	"shippingStat": "0",
	"shippingStatDes": "未付款"
}]
```

> |参数|类型|说明|
|:-----|:-----|-----|
|orderId|int|订单ID|
|orderNo|string|订单号|
|orderAmt|float|订单金额|
|orderCyUnit|string|订单金额币种单位，如“元”|
|orderStat|int|订单状态码|
|orderStatDes|string|订单状态码说明|
|orderCTime|string|订单创建时间|
|orderItems|array|订单商品信息|
|leavingMessage|string|会员下单时留言|
|shippingWay|string|快递商家，如“顺丰快递”|
|shippingSn|string|快递单号|
|shipmentConsignee|string|收货人|
|consigneePhoneNo|string|收货人联系电话|
|shippingZipCode|string|收货人邮编|
|shippingAddress|string|收货人地址|
|shippingStat|int|收货状态码|
|shippingStatDes|string|收货状态说明|

> |参数|类型|说明|
|:-----|:-----|-----|
|itemId|int|商品ID|
|itemName|string|商品名称|
|itemCost|float|商品价钱|
|itemNum|int|商品数量|
|isVirt|boolean|是否虚拟商品|
|virtCode|array|虚拟码数组，如“\["sample001","sample002"]”|

#### 接口示例
``` javascript
<script type="text/javascript">
    $.ajax({
        url: "https://api.sample.com/order/v1/retrieve",
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
            "orderStatus": 0, // 待支付订单
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