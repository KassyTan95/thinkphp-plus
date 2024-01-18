<?php
/**
 * Created by PhpStorm.
 * User: Kassy
 * Date: 2021-07-07
 * Time: 11:21
 * Description:
 */

namespace Kassy\ThinkphpPlus\service;


use GuzzleHttp\Client;

/**
 * Class Requests
 * @package app\common\service
 */
class Requests
{
    private static ?self $instance = null;
    /**
     * @var array 默认请求配置
     */
    protected array $guzzle_option = [
        'timeout'     => 600,
        'http_errors' => true,
        'verify'      => false
    ];

    /**
     * 实例化入口
     * @return Requests|null
     */
    public static function instance(): ?Requests
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

    /**
     * 获取guzzle服务
     * @param $option
     * @return Client
     */
    private function getClientService($option): Client
    {
        return new Client();
    }

    /**
     * 设置参数
     * @param array $option
     * @return $this
     */
    public function setOption(array $option): self
    {
        $this->guzzle_option = array_merge($this->guzzle_option, $option);
        return $this;
    }

    /**
     * get 请求
     * @param string $url 链接
     * @param array $param 参数
     * @param array $headers 头
     * @param string $cookies cookie
     * @param bool $is_decode 是否输出解码
     * @return mixed|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get(string $url, array $param = [], array $headers = [], string $cookies = '',
                        bool $is_decode = true): mixed
    {
        return $this->request('GET', $url, $param, $headers, $cookies, $is_decode);
    }

    /**
     * post 请求
     * @param string $url 链接
     * @param array $param 参数
     * @param array $headers 头
     * @param string $cookies cookie
     * @param bool $is_decode 是否输出解码
     * @return mixed|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function post(string $url, array $param = [], array $headers = [], string $cookies = '',
                         bool $is_decode = true): mixed
    {
        return $this->request('POST', $url, $param, $headers, $cookies, $is_decode);
    }

    /**
     * put 请求
     * @param string $url 链接
     * @param array $param 参数
     * @param array $headers 头
     * @param string $cookies cookie
     * @param bool $is_decode 是否输出解码
     * @return mixed|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function put(string $url, array $param = [], array $headers = [], string $cookies = '',
                        bool $is_decode = true): mixed
    {
        return $this->request('PUT', $url, $param, $headers, $cookies, $is_decode);
    }

    /**
     * patch 请求
     * @param string $url 链接
     * @param array $param 参数
     * @param array $headers 头
     * @param string $cookies cookie
     * @param bool $is_decode 是否输出解码
     * @return mixed|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function patch(string $url, array $param = [], array $headers = [], string $cookies = '',
                          bool $is_decode = true): mixed
    {
        return $this->request('PATCH', $url, $param, $headers, $cookies, $is_decode);
    }

    /**
     * delete 请求
     * @param string $url 链接
     * @param array $param 参数
     * @param array $headers 头
     * @param string $cookies cookie
     * @param bool $is_decode 是否输出解码
     * @return mixed|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function delete(string $url, array $param = [], array $headers = [], string $cookies = '',
                           bool $is_decode = true): mixed
    {
        return $this->request('DELETE', $url, $param, $headers, $cookies, $is_decode);
    }

    /**
     * request请求
     * @param string $method 方法
     * @param string $url 链接
     * @param array $param 参数
     * @param array $headers 头
     * @param string $cookies cookie
     * @param bool $is_decode 是否输出解码
     * @return mixed|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request(string $method, string $url, array $param = [], array $headers = [], string $cookies = '',
                            bool $is_decode = true): mixed
    {
        if (stripos($url, 'https://') == 0) {
            $this->guzzle_option = array_merge($this->guzzle_option, ['verify' => true]);
        }

        $method = strtoupper($method);
        $parameter = [];

        if ($method == 'GET') {
            $parameter['query'] = $param;
        } else {
            $parameter['form_params'] = $param;
        }
        if ($cookies) {
            $parameter['cookies'] = $cookies;
        }
        if ($headers) {
            $parameter['headers'] = $headers;
        }
        // 原样参数
        if (isset($param['is_origin']) && $param['is_origin']) {
            $parameter = $param;
        }

        $requests = $this->getClientService($this->guzzle_option)
            ->request($method, $url, $parameter)
            ->getBody()
            ->getContents();

        return $is_decode ? json_decode($requests, true) : $requests;
    }
}
