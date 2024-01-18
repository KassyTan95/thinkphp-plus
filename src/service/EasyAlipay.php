<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2023/9/15
 * @Time: 10:54
 * @describe:
 */

namespace Kassy\ThinkphpPlus\service;

use Alipay\EasySDK\Kernel\Config;
use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Util\ResponseChecker;

class EasyAlipay
{
    /**
     * 实例
     * @var EasyAlipay|null
     */
    private static ?EasyAlipay $instance = null;

    /**
     * 配置
     * @var array
     */
    private array $config;

    /**
     * 响应检查器
     * @var ResponseChecker
     */
    private ResponseChecker $respCheck;

    /**
     * 实例化入口
     * @return EasyAlipay
     */
    public static function Instance(): EasyAlipay
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->config = config('alipay');
        $this->respCheck = new ResponseChecker();
        Factory::setOptions($this->getOption());
    }

    private function __clone(): void {}

    /**
     * 设置参数
     * @return Config
     */
    private function getOption(): Config
    {
        $options = new Config();
        $options->protocol = 'https';
        $options->gatewayHost = 'openapi.alipay.com';
        $options->signType = 'RSA2';

        // appid
        $options->appId = $this->config['appid'];
        // 商户私钥
        $options->merchantPrivateKey = $this->config['merchant_private_key'];
        // 支付宝公钥
        $options->alipayPublicKey = $this->config['alipay_public_key'];

        // 证书模式证书路径
        // $options->alipayCertPath = '<-- 请填写您的支付宝公钥证书文件路径，例如：/foo/alipayCertPublicKey_RSA2.crt -->';
        // $options->alipayRootCertPath = '<-- 请填写您的支付宝根证书文件路径，例如：/foo/alipayRootCert.crt" -->';
        // $options->merchantCertPath = '<-- 请填写您的应用公钥证书文件路径，例如：/foo/appCertPublicKey_2019051064521003.crt -->';

        // 可设置异步通知接收服务地址（可选）
        $options->notifyUrl = $this->config['notify_url'];
        // 可设置AES密钥，调用AES加解密相关接口时需要（可选）
        $options->encryptKey = $this->config['encrypt_key'];

        return $options;
    }

    /**
     * 支付能力
     * @return \Alipay\EasySDK\Kernel\Payment
     */
    public function payment(): \Alipay\EasySDK\Kernel\Payment
    {
        return Factory::payment();
    }

    /**
     * 基础能力
     * @return \Alipay\EasySDK\Kernel\Base
     */
    public function base(): \Alipay\EasySDK\Kernel\Base
    {
        return Factory::base();
    }

    /**
     * 会员能力
     * @return \Alipay\EasySDK\Kernel\Member
     */
    public function member(): \Alipay\EasySDK\Kernel\Member
    {
        return Factory::member();
    }

    /**
     * 安全能力
     * @return \Alipay\EasySDK\Kernel\Security
     */
    public function security(): \Alipay\EasySDK\Kernel\Security
    {
        return Factory::security();
    }

    /**
     * 营销能力
     * @return \Alipay\EasySDK\Kernel\Marketing
     */
    public function marketing(): \Alipay\EasySDK\Kernel\Marketing
    {
        return Factory::marketing();
    }

    /**
     * 辅助工具
     * @return \Alipay\EasySDK\Kernel\Util
     */
    public function util(): \Alipay\EasySDK\Kernel\Util
    {
        return Factory::util();
    }

    /**
     * 验签
     * @param $param
     * @return bool
     */
    public function verify($param): bool
    {
        return self::payment()->common()->verifyNotify($param);
    }

    /**
     * 创建支付订单
     * @param array $param
     * @return array
     * @throws \Exception
     */
    public function preOrder(array $param): array
    {
        $this->config['cent'] && $param['totalFee'] = '0.01';
        $request = self::payment()
            ->common()
            ->asyncNotify(request()->domain() . '/api/meter/aliNotify')
            ->create($param['subject'], $param['orderNumber'], $param['totalFee'], $param['userId']);
        if (!$this->respCheck->success($request)) {
            abort(-1, $request->msg);
        }
        return [
            'http_body'    => $request->httpBody,
            'out_trade_no' => $request->outTradeNo,
            'trade_no'     => $request->tradeNo
        ];
    }

    /**
     * 退款
     * @param $param
     * @return bool
     * @throws \Exception
     */
    public function refund($param): bool
    {
        $this->config['cent'] && $param['totalFee'] = '0.01';
        $request = self::payment()
            ->common()
            ->refund($param['tradeNo'], $param['totalFee']);

        if (!$this->respCheck->success($request)) {
            abort(-1, $request->msg);
        }
        return true;
    }

    /**
     * 授权
     * @param $code
     * @return array
     * @throws \Exception
     */
    public function auth($code): array
    {
        $request = self::base()->oauth()->getToken($code);
        if (!$this->respCheck->success($request)) {
            abort(-1, $request->msg);
        }
        return [
            'aliUserId'    => $request->userId,
            'accessToken'  => $request->accessToken,
            'refreshToken' => $request->refreshToken
        ];
    }


    /**
     * 获取手机号
     * @param $phoneCode
     * @return string
     */
    public function getPhone($phoneCode): string
    {
        $request = json_decode(self::util()->aes()->decrypt(json_decode($phoneCode, true)['response']), true);
        if ($request['code'] != 10000) {
            abort(-1, $request['msg']);
        }
        return $request['mobile'];
    }
}
