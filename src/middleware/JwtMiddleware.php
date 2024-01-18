<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2023/6/5
 * @Time: 21:26
 * @describe:
 */

namespace Kassy\ThinkphpPlus\middleware;


use thans\jwt\exception\TokenBlacklistGracePeriodException;
use thans\jwt\exception\TokenExpiredException;
use thans\jwt\JWTAuth as Auth;
use think\facade\Config;
use think\facade\Cookie;

class JwtMiddleware
{
    protected Auth $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function handle($request, \Closure $next)
    {
        $url = $request->baseUrl();
        if ($request->ignoreRoutes && !in_array($url, $request->ignoreRoutes)) {
            // OPTIONS请求直接返回
            if ($request->isOptions()) {
                return response();
            }

            // 验证token
            try {
                $request->payload = $this->auth->auth();
            } catch (TokenExpiredException $e) {
                // 尝试刷新token
                try {
                    $this->auth->setRefresh();
                    $token = $this->auth->refresh();

                    $request->payload = $this->auth->auth(false);
                    $response = $next($request);

                    return $this->setAuthentication($response, $token);
                } catch (TokenBlacklistGracePeriodException $e) {
                    $request->payload = $this->auth->auth(false);
                    return $next($request);
                }
            } catch (TokenBlacklistGracePeriodException $e) {
                $request->payload = $this->auth->auth(false);
                return $next($request);
            }
        }
        return $next($request);
    }

    protected function setAuthentication($response, $token = null)
    {
        $token = $token ?: $this->auth->refresh();
        $this->auth->setToken($token);

        if (in_array('cookie', Config::get('jwt.token_mode'))) {
            Cookie::set('token', $token);
        }

        if (in_array('header', Config::get('jwt.token_mode'))) {
            $response = $response->header(['Authorization' => 'Bearer ' . $token]);
        }

        return $response;
    }
}
