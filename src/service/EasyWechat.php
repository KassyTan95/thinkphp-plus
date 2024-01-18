<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2023/6/6
 * @Time: 21:16
 * @describe:
 */

namespace Kassy\ThinkphpPlus\service;

use Closure;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Exceptions\Exception;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use EasyWeChat\Kernel\Exceptions\InvalidConfigException;
use GuzzleHttp\Exception\GuzzleException;

class EasyWechat
{
    /**
     * 实例
     * @var EasyWechat|null
     */
    private static ?EasyWechat $instance = null;
    /**
     * 配置
     * @var array
     */
    private array $config;
    /**
     * 选项
     * @var array
     */
    private array $option = [];
    /**
     * 类型
     * @var string
     */
    private string $tradeType;

    /**
     * 实例化入口
     * @return EasyWechat
     */
    public static function Instance(): EasyWechat
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->config = config('wechat');
    }

    private function __clone(): void {}

    /**
     * 选定配置
     * @param string $mode
     * @param bool $isPayment
     * @return $this
     */
    public function setConfig(string $mode = 'applet', bool $isPayment = false): EasyWechat
    {
        $this->option = $this->config[$mode];
        $isPayment && $this->option = array_merge($this->option, $this->config['payment']);
        $this->tradeType = ['app' => 'APP', 'applet' => 'JSAPI'][$mode];
        return $this;
    }

    /**
     * 小程序授权
     * @param $code
     * @return array
     * @throws InvalidConfigException
     */
    public function appletAuth($code): array
    {
        $app = Factory::miniProgram($this->config['applet']);
        $session = $app->auth->session($code);
        isset($session['errmsg']) && $session['errmsg'] !== 'ok' && abort(-1, $session['errmsg']);
        return ['openId' => $session['openid'], 'session' => $session];
    }

    /**
     * 获取手机号
     * @param $phoneCode
     * @return mixed
     * @throws GuzzleException
     * @throws InvalidConfigException
     */
    public function getPhone($phoneCode): mixed
    {
        $app = Factory::miniProgram($this->config['applet']);
        $ret = $app->phone_number->getUserPhoneNumber($phoneCode);
        isset($session['errmsg']) && $session['errmsg'] !== 'ok' && abort(-1, $session['errmsg']);
        return $ret['phone_info']['purePhoneNumber'];
    }

    /**
     * 验签
     * @param $orderNumber
     * @return array|object|string
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public function verify($orderNumber): array|object|string
    {
        return Factory::payment($this->option)->order->queryByOutTradeNumber($orderNumber);
    }

    /**
     * 支付回调
     * @param Closure $c
     * @return void
     * @throws Exception
     */
    public function notify(Closure $c): void
    {
        Factory::payment($this->option)->handlePaidNotify($c)->send();
    }

    /**
     * 是否需要重新下单
     * @param $out_trade_no
     * @return bool
     * @throws GuzzleException
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public function isRePrePay($out_trade_no): bool
    {
        $app = Factory::payment($this->option);

        $find = $app->order->queryByOutTradeNumber($out_trade_no);
        if (isset($find['trade_state']) && in_array($find['trade_state'], ['CLOSED', 'NOTPAY'])) {
            $app->order->close($out_trade_no);
            return true;
        }
        return false;
    }

    /**
     * 预下单
     * @param $param
     * @return array|string
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws GuzzleException
     */
    public function preOrder($param): array|string
    {
        $app = Factory::payment($this->option);

        $args = [
            'body'         => $param['body'],
            'out_trade_no' => $param['orderNumber'],
            'total_fee'    => $this->config['cent'] ? '1' : strval($param['totalFee'] * 100),
            'notify_url'   => $this->option['notify_url'][$param['notifyType']],
            'trade_type'   => $this->tradeType
        ];
        if ($this->tradeType == 'JSAPI') {
            $args['openid'] = $param['openId'];
        }

        $result = $app->order->unify($args);
        if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
            if ($this->tradeType == 'APP') {
                return $app->jssdk->appConfig($result['prepay_id']);
            } else {
                return $app->jssdk->bridgeConfig($result['prepay_id'], false);
            }
        }
        if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'FAIL') {
            abort(-1, $result['err_code_des']);
        }
        return [];
    }

    /**
     * 退款
     * @param string $trade_no
     * @param string $refund_order_number
     * @param $total_fee
     * @param $refund_fee
     * @param array $refund_options
     * @param int $abort
     * @return array
     * @throws InvalidConfigException
     */
    public function refund(string $trade_no, string $refund_order_number, $total_fee, $refund_fee,
                           array  $refund_options, int $abort = 1): array
    {
        $this->config['cent'] && $total_fee = 1 && $refund_fee = 1;
        $refund_res = Factory::payment($this->option)->refund->byTransactionId($trade_no, $refund_order_number,
            $total_fee, $refund_fee, $refund_options);
        if ($refund_res['return_code'] !== 'SUCCESS' || $refund_res['result_code'] !== 'SUCCESS') {
            if ($abort) {
                abort(-1, '微信订单退款失败');
            } else {
                abort(-1, $refund_res['err_code_des'] ?? '微信订单退款失败');
            }
        }
        return [];
    }

    /**
     * 企业付款到零钱
     * @param string $orderNo
     * @param string $openId
     * @param float $amount
     * @param string $desc
     * @return void
     */
    public function withdraw(string $orderNo, string $openId, float $amount, string $desc = '提现'): void
    {
        $app = Factory::miniProgram($this->option);
        $app->transfer->toBalance([
            'partner_trade_no' => $orderNo,
            'openid'           => $openId,
            'check_name'       => 'NO_CHECK',            // NO_CHECK：不校验真实姓名, FORCE_CHECK：强校验真实姓名
            're_user_name'     => '',                    // 如果 check_name 设置为FORCE_CHECK，则必填用户真实姓名
            'amount'           => intval($amount * 100), // 企业付款金额，单位为分
            'desc'             => $desc,                 // 企业付款操作说明信息。必填
        ]);
    }
}
