<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2023/6/7
 * @Time: 20:51
 * @describe:
 */

namespace Kassy\ThinkphpPlus\service;

use Overtrue\EasySms\EasySms;
use think\facade\Cache;

class Sms
{
    /**
     * 短信实例
     * @var mixed|null
     */
    private static mixed $instance = null;
    /**
     * 0 缺省场景
     * 1 注册
     * 2 忘记密码
     * 3 短信登录
     * 4 绑定手机号
     * 5 修改密码
     * @var int
     */
    private static int $type;
    /**
     * 配置
     * @var array
     */
    private static array $config = [];
    /**
     * 模板信息
     * @var array
     */
    private static array $template = [];
    /**
     * 手机号
     * @var string
     */
    private string $phone = '';
    /**
     * 短信实例
     * @var EasySms
     */
    private static EasySms $easySms;
    /**
     * 验证码id
     * @var string|null
     */
    private ?string $code_id = null;

    /**
     * 实例化入口
     * @param bool $isNewInstance
     * @return Sms
     */
    public static function Instance(bool $isNewInstance = false): Sms
    {
        if (!self::$instance instanceof self || $isNewInstance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        self::$config = config('sms');
        self::$easySms = new EasySms(self::$config);
    }

    private function __clone(): void {}

    /**
     * 设置短信类型
     * @param int $type
     * @return $this
     */
    public function setType(int $type): Sms
    {
        self::$type = $type;
        self::$template = self::$config['template'][$type]['sms_id'] ? self::$config['template'][$type] : self::$config['template'][0];
        return $this;
    }

    /**
     * 设置手机号
     * @param string $phone
     * @return $this
     */
    public function setPhone(string $phone): Sms
    {
        $this->phone = $phone;
        return $this;
    }

    /**
     * 生成验证码
     * @return string
     */
    private function genCode(): string
    {
        $code = mt_rand(0, 999999);
        if (strlen($code) < 6) {
            $code = str_pad($code, 6, '0', STR_PAD_LEFT);
        }
        // 生成验证码缓存
        if (self::$config['is_check']) {
            $phone = $this->phone;
            $type = self::$type;
            Cache::set("SMS_{$phone}_{$type}", $code, self::$config['expire']);
        }
        return $code;
    }

    /**
     * 验证码验证
     * @param string $phone
     * @param int $type
     * @param string $code
     * @return bool
     */
    public function verify(string $phone, int $type, string $code): bool
    {
        if (self::$config['is_check']) {
            $old = Cache::get("SMS_{$phone}_{$type}", '');
            if ($old != $code) {
                // 记录重试次数, 达到3次删除
                $retry = Cache::get("SMS_retry_{$phone}_{$type}", 0);
                if ($retry == 2) {
                    Cache::delete("SMS_{$phone}_{$type}");
                }
                Cache::set("SMS_retry_{$phone}_{$type}", ++$retry, self::$config['expire']);
                abort(-1, '验证码不正确');
            }
            $this->code_id = "SMS_{$phone}_{$type}";
        }
        return true;
    }

    /**
     * 发短信
     * @return array
     * @throws \Overtrue\EasySms\Exceptions\InvalidArgumentException
     * @throws \Overtrue\EasySms\Exceptions\NoGatewayAvailableException
     */
    public function send(): array
    {
        $res = [];
        if (self::$config['is_check']) {
            $res = self::$easySms->send($this->phone, [
                'template' => self::$template['sms_id'],
                'data'     => [
                    'code' => $this->genCode()
                ]
            ]);
        }
        return $res;
    }

    public function __destruct()
    {
        if (!is_null($this->code_id)) {
            Cache::delete($this->code_id);
        }
    }
}
