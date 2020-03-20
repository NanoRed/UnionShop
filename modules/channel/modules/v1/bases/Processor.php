<?php

namespace app\modules\channel\modules\v1\bases;

use yii\base\Component;
use app\modules\channel\modules\v1\models\Channel;
use app\modules\channel\modules\v1\exceptions\ChannelException;

/**
 * processor基础继承类
 * Class Processor
 * @package app\modules\channel\modules\v1\bases
 */
abstract class Processor extends Component
{
    public $params;       // 配置参数

    public $appName;      // 应用名称
    public $appSchema;    // APP（或内嵌APP）唤起协议

    public $isWap = true; // 是否wap应用
    public $wapHomePage;  // wap应用主页
    public $wapPaidGuide; // wap应用支付引导页

    public function init()
    {
        parent::init();

        if (empty($this->appName))
            throw new ChannelException('请设置应用名称');
        if (empty($this->appSchema))
            throw new ChannelException('请设置应用（或内嵌应用）的唤起协议，如 weixin://');
        if ($this->isWap) {
            if (empty($this->wapHomePage))
                throw new ChannelException('wap应用请设置应用主页');
            if (empty($this->wapPaidGuide))
                throw new ChannelException('wap应用请设置支付引导页，如 #payfail');
        }
    }

    private $_channelId; // 渠道ID
    public function getChannelId()
    {
        if (empty($this->_channelId)) {
            $alias = $this->channelAlias;
            $this->_channelId = Channel::findChannelIdByChannelAlias($alias);
        }

        return $this->_channelId;
    }

    private $_channelAlias; // 渠道代号
    public function getChannelAlias()
    {
        if (empty($this->_channelAlias)) {
            $this->_channelAlias
                = basename(dirname(str_replace('\\', DIRECTORY_SEPARATOR, get_called_class())));
        }

        return $this->_channelAlias;
    }
}
