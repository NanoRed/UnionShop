##需执行的定时任务
<br>

####取消订单
> * 任务功能：定时自动取消订单
> * 注意说明：订单24小时未支付取消，注意一个渠道一次最多取消10000个订单，有需求可修改
> * 执行命令：`yii order/v1-console/cancel/index`
> * 执行周期：深夜执行1次

####ERP发货
> * 任务功能：订单推送ERP
> * 执行命令：`yii logis/v1-console/erp/dispatch`
> * 执行周期：每5分钟执行

####ERP同步
> * 任务功能：将ERP数据同步回系统
> * 执行命令：`yii logis/v1-console/erp/synchronize`
> * 执行周期：每2分钟执行

####加载待订阅物流跟踪
> * 任务功能：加载订单的待订阅物流跟踪任务
> * 执行命令：`yii logis/v1-console/track/load-order`
> * 执行周期：每1小时执行

####订阅物流跟踪
> * 任务功能：执行物流跟踪的订阅
> * 执行命令：`yii logis/v1-console/track/subscribe`
> * 执行周期：每1小时执行

####物流状态同步
> * 任务功能：物流送货状态的同步回写
> * 执行命令：`yii logis/v1-console/track/synchronize`
> * 执行周期：每1小时执行