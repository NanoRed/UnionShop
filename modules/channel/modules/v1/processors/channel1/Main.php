<?php

namespace app\modules\channel\modules\v1\processors\channel1;

use Yii;
use yii\helpers\ArrayHelper;
use app\modules\channel\modules\v1\bases\Processor;
use app\modules\channel\modules\v1\processors\channel1\libs\behaviors\Crypt;
use app\modules\rewrite\modules\v1\behaviors\ActivityLimitTool;
use app\modules\rewrite\modules\v1\behaviors\AdditionalPurchaseTool;
use app\modules\rewrite\modules\v1\behaviors\CartItemBox;
use app\modules\rewrite\modules\v1\behaviors\CouponTool;
use app\modules\rewrite\modules\v1\behaviors\DistrictLimitTool;
use app\modules\rewrite\modules\v1\behaviors\FlashSaleTool;
use app\modules\rewrite\modules\v1\behaviors\InvitationCodeTool;
use app\modules\rewrite\modules\v1\behaviors\ListSaleTool;
use app\modules\rewrite\modules\v1\behaviors\LotteryTool;
use app\modules\rewrite\modules\v1\behaviors\PriceBreakTool;
use app\modules\rewrite\modules\v1\behaviors\PrivilegeCodeTool;
use app\modules\rewrite\modules\v1\behaviors\PurchaseLimitTool;
use app\modules\login\modules\v1\exceptions\LoginException;
use app\modules\login\modules\v1\behaviors\LoginResolve;
use app\modules\logis\modules\v1\behaviors\LogisResolve;
use app\modules\market\modules\v1\behaviors\MarketResolve;
use app\modules\order\modules\v1\behaviors\OrderNumberMaker;
use app\modules\order\modules\v1\behaviors\OrderResolve;
use app\modules\pay\modules\v1\behaviors\PayResolve;
use app\modules\user\modules\v1\models\User;

use phpseclib\Crypt\RSA;

/**
 * 渠道实例一
 * Class Main
 * @package app\modules\channel\modules\v1\processors\channel1
 */
class Main extends Processor
{
    public function init()
    {
        $this->params = require __DIR__ . '/libs/config/params.php';

        $this->appName = '品牌特惠'; // 应用名称
        $this->appSchema = $this->params['appSchema']; // 品牌特惠内嵌APP唤起协议

        $this->isWap = true; // 网页应用
        $this->wapHomePage = $this->params['homePage']; // 品牌特惠URL
        $this->wapPaidGuide = $this->params['paidGuide']; // 品牌特惠支付引导页

        parent::init();
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['crypt'] = ['class' => Crypt::className()];                       // 渠道实例一对接
        $behaviors['cartItemBox'] = ['class' => CartItemBox::className()];           // 购物车盒
        $behaviors['orderNumberMaker'] = ['class' => OrderNumberMaker::className()]; // 订单号生成器
        return ArrayHelper::merge([
            'login' => ['class' => LoginResolve::className()],                       // 登陆模块
            'order' => ['class' => OrderResolve::className()],                       // 订单模块
            'pay' => ['class' => PayResolve::className()],                           // 支付模块
            'logis' => ['class' => LogisResolve::className()],                       // 物流模块
            'market' => [                                                            // 营销模块
                'class' => MarketResolve::className(),
                'enableTools' => [
                    'lottery' => LotteryTool::className(),                           // 抽奖工具
                    'flashSale' => FlashSaleTool::className(),                       // 秒杀工具
                    'purchaseLimit' => PurchaseLimitTool::className(),               // 商品限购
                    'activityLimit' => ActivityLimitTool::className(),               // 活动限购
                    'districtLimit' => DistrictLimitTool::className(),               // 地区限购
                    'invitationCode' => InvitationCodeTool::className(),             // 邀请码工具
                    'privilegeCode' => PrivilegeCodeTool::className(),               // 特权码工具
                    'coupon' => CouponTool::className(),                             // 现金券工具
                    'priceBreak' => PriceBreakTool::className(),                     // 满减工具
                    'listSaleTool' => ListSaleTool::className(),                     // N元N件工具
                    'additionalPurchase' => AdditionalPurchaseTool::className(),     // 加价购工具
                ]
            ],
        ], $behaviors);
    }

    /**
     * 登陆调起
     * @return string
     */
    public function loginCall()
    {
        if ($BackUrl = Yii::$app->request->headers->get('X-Unionsystem-Referer')) {
            $BackUrl = Yii::$app->security->maskToken($BackUrl);
        } else {
            $BackUrl = '';
        }

        Yii::$app->response->statusCode = 200;
        Yii::$app->response->headers->set(
            'X-Unionsystem-Script',
            str_replace(
                ["\n", "\r"], '',
                Yii::$app->controller->render(
                    '@app/modules/channel/modules/v1/processors/channel1/libs/views/location',
                    [
                        'href' => $this->params['login']['url'] . '?' . http_build_query([
                            'TransCode' => $this->params['login']['transCode'],
                            'ChannelId' => $this->params['login']['channelId'],
                            'BackUrl' => $BackUrl,
                        ])
                    ]
                )
            )
        );
        return Yii::$app->response;
    }

    /**
     * 登陆完成回调
     * @throws LoginException
     * @throws \yii\db\Exception
     */
    public function loginCallback()
    {
        $TokenId = Yii::$app->request->get('TokenId'); // 密文数据，使用商户的公钥明文进行加密
        $Signature = Yii::$app->request->get('Signature'); // 签名数据，使用渠道实例一的私钥对摘要进行签名
        $BackUrl = Yii::$app->request->get('BackUrl'); // 透传参数，记录历史页面

        if (!empty($TokenId) && !empty($Signature)) {
            if ($plainText = $this->loginTokenDecrypt($TokenId, $this->params['login']['privateKey'])) { // 解密
                if ($this->loginSignVerify($plainText, $Signature, $this->params['login']['publicKey'])) { // 验签
                    $data = json_decode($plainText, true); // 数据
                    if (!empty($data['CustomerId'])) { // 成功

                        // 生成用户数据，并注册AccessToken闭包
                        $userInfo = User::findIdentityByCuid(
                            $data['CustomerId'], User::FIBC_IF_NOT_GENERATE | User::FIBC_SET_ACCESSTOKEN
                        );

                        $RSA = new RSA();
                        $RSA->loadKey($this->formatPublicKey($this->params['rewrite']['publicKey']));

                        $plainText = $userInfo->accessToken; // 生成用户AccessToken
                        $cipherText = $RSA->encrypt($plainText);
                        $cipherText = strtr(base64_encode($cipherText), ['+' => '-', '/' => '_', '=' => '']);

                        // 解密参照：
                        // $RSA->loadKey($this->params['rewrite']['privateKey']);
                        // $cipherText = strtr($cipherText, '-_', '+/');
                        // $mod4 = strlen($cipherText) % 4;
                        // if ($mod4 > 0) {
                        //     $cipherText .= substr('====', $mod4);
                        // }
                        // $cipherText = base64_decode($cipherText);
                        // $plainText = $RSA->decrypt($cipherText);
                        // Yii::info($plainText, __METHOD__);

                        if (empty($BackUrl)) {
                            $uri = $this->params['homePage'];
                        } else {
                            $uri = Yii::$app->security->unmaskToken($BackUrl);
                        }

                        $resolveHash = explode('#', $uri, 2);
                        $resolveParam = explode('?', $resolveHash[0], 2);
                        if (!empty($resolveParam[1])) {
                            parse_str($resolveParam[1], $params);
                        }
                        $params['token'] = $cipherText;
                        $resolveHash[0] = $resolveParam[0] . '?' . urldecode(http_build_query($params));
                        $uri = implode('#', $resolveHash);

                        return Yii::$app->controller->render(
                            '@app/modules/channel/modules/v1/processors/channel1/libs/views/location',
                            [ 'href' => $uri]
                        );

                    } else {
                        throw new LoginException('CustomerId为空！');
                    }
                } else {
                    throw new LoginException('Signature验签失败！');
                }
            } else {
                throw new LoginException('TokenId解密失败！');
            }
        } else {
            throw new LoginException('TokenId或Signature为空！');
        }
    }

    /**
     * 送货订单号生成
     * @param string $prefix
     * @param int $digit
     * @return string
     */
    public function logisShippingOrderNumberGenerate($prefix = 'SPS', $digit = 16)
    {
        return $prefix . $this->getOrderNumber(__CLASS__ . $this->channelId, $digit);
    }

    /**
     * 订单号生成
     * @param string $prefix
     * @param int $digit
     * @return string
     */
    public function orderNumberGenerate($prefix = 'SP', $digit = 16)
    {
        return $prefix . $this->getOrderNumber(__CLASS__ . $this->channelId, $digit);
    }

    /**
     * 提取购物车商品
     * @return mixed
     */
    public function orderCartItemExtract()
    {
        return $this->getOrderItems();
    }

    /**
     * 交易订单号生成
     * @param string $prefix
     * @param int $digit
     * @return string
     */
    public function payTransferOrderNumberGenerate($prefix = 'SPT', $digit = 16)
    {
        return $prefix . $this->getOrderNumber(__CLASS__ . $this->channelId, $digit);
    }

    /**
     * 退款订单号生成
     * @param string $prefix
     * @param int $digit
     * @return string
     */
    public function payRefundOrderNumberGenerate($prefix = 'SPR', $digit = 16)
    {
        return $prefix . $this->getOrderNumber(__CLASS__ . $this->channelId, $digit);
    }
}
