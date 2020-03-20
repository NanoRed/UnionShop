# B2B2C聚合商城系统（UnionShop）

此系统已将分发的渠道聚合通用（Channel模块，也可将分发渠道理解为“商店”），已将各个业务逻辑代码模块化，并且各渠道达成解耦，项目结构相对清晰。对接渠道时，请加载各个模块的Resolve类并实现相关方法以对该渠道启用对应模块。

#### 下是一些要点说明：
* 此系统使用 Yii2 框架

* 对接第三方一般使用这样的目录结构
 <br>┌─&emsp;kdniao（第三方缩写）
 <br>│&emsp;&emsp;&emsp;├──&emsp;libs（Main.php使用到的可以放此目录）
 <br>│&emsp;&emsp;&emsp;│&emsp;&emsp;&emsp;├── config（一般放对接配置参数）
 <br>│&emsp;&emsp;&emsp;│&emsp;&emsp;&emsp;│&emsp;&emsp;&emsp;├── dev_params.php
 <br>│&emsp;&emsp;&emsp;│&emsp;&emsp;&emsp;│&emsp;&emsp;&emsp;├── prod_params.php
 <br>│&emsp;&emsp;&emsp;│&emsp;&emsp;&emsp;│&emsp;&emsp;&emsp;└── test_params.php
 <br>│&emsp;&emsp;&emsp;│&emsp;&emsp;&emsp;└── models（放对接使用到的对接模型）
 <br>│&emsp;&emsp;&emsp;│&emsp;&emsp;&emsp;&nbsp;&emsp;&emsp;&emsp;└── TableName.php
 <br>│&emsp;&emsp;&emsp;└──&emsp;Main.php（第三方插件调用入口）

* 添加Callbak接口时，可创建CallbackController，然后actions目录创建callback目录，放入相关action类作为callback接口，注意Module.php需先设置$this->controllerMap['callback']为对应CallbackController类

* 同理，需要对渠道提供API时，可创建ApiController，并在对应渠道的libs下创建actions目录，再创建api子目录，放入相关actions作为api接口

<br>**我的博客：https://blog.tobeaver.com/**
<br>**我的网站：https://www.tobeaver.com/**
