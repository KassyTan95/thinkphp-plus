<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2024/1/18
 * @Time: 10:37
 * @describe:
 */

use think\response\Json;

if (!function_exists('apiResp')) {
    /**
     * 接口输出格式
     * @param mixed $data 数据
     * @param string $msg 提示信息
     * @param int $code 状态码
     * @param bool $is_show_time 是否显示当前时间
     * @return Json
     */
    function apiResp(mixed $data = [], string $msg = 'success', int $code = 0,
                     bool  $is_show_time = true): Json
    {
        if ($code == 0) {
            $result = [
                'code' => $code,
                'msg'  => $msg,
                'data' => $data,
            ];
        } elseif ($code < 0) {
            $result = [
                'code' => $code,
                'msg'  => $msg
            ];
        } else {
            $result = [
                'code' => $code,
                'msg'  => $msg
            ];
        }
        if ($is_show_time) {
            $result['timestamp'] = time();
        }

        return json($result);
    }
}

if (!function_exists('randomStr')) {
    /**
     * 随机字符串
     * @param int $len
     * @param bool $isCapital
     * @return string
     */
    function randomStr(int $len = 6, bool $isCapital = false): string
    {
        $len > 32 && abort(-1, '随机字符串不能大于32位');
        $str = substr(md5(uniqid(microtime(true))), 0, $len - 1);

        return $isCapital ? strtoupper($str) : $str;
    }
}

if (!function_exists('datetime')) {
    /**
     * 将时间戳转换为日期时间
     * @param int|string $time 时间戳
     * @param string $format 日期时间格式
     * @return string
     */
    function datetime(int|string $time, string $format = 'Y-m-d H:i:s'): string
    {
        $time = is_numeric($time) ? $time : strtotime($time);
        return date($format, $time);
    }
}

if (!function_exists('buildMenuChild')) {
    /**
     * 递归获取子菜单
     * @param $data
     * @param $pid
     * @return array
     */
    function buildMenuChild($data, $pid): array
    {
        $treeList = [];
        foreach ($data as $item) {
            if ($pid == $item['pid']) {
                $node = $item;
                $child = buildMenuChild($data, $item['id']);
                if (!empty($child)) {
                    $node['children'] = $child;
                }
                $treeList[] = $node;
            }
        }

        return $treeList;
    }
}

if (!function_exists('filePathJoin')) {
    /**
     * 拼接文件路径
     * @param string $url
     * @param string $type
     * @return string
     */
    function filePathJoin(string $url = '', string $type = ''): string
    {
        if (!$url) {
            return $url;
        }

        if (preg_match("/^http*/", $url)) {
            return $url;
        }

        $type = $type ?: env('filesystem.driver', 'local');

        switch ($type) {
            case 'alioss':
                $url = config('filesystem.disks.aliyun.url', '') . $url;
                break;
            case 'qcloud':
                $domain = config('filesystem.disks.qcloud.scheme', '') . '://' .
                    config('filesystem.disks.qcloud.cdn', '');
                $url = $domain . $url;
                break;
            case 'qiniu':
                $url = config('filesystem.disks.qiniu.url', '') . $url;
                break;
            default:
                $url = env('app.domain', request()->domain()) . $url;
        }

        return $url;
    }
}

if (!function_exists('sysConfig')) {
    /**
     * 获取系统配置
     * @param string $group
     * @param string|null $var
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    function sysConfig(string $group, ?string $var = null): mixed
    {
        $key = 'sysConfig';
        $conversion = function (string $data, string $type = 'string') {
            if ($type === 'bool') {
                return boolval($data);
            } elseif ($type === 'number') {
                return floatval($data);
            } else {
                return $data;
            }
        };

        $where = [
            ['group', '=', $group]
        ];
        $val = is_null($var) ? cache("{$key}_{$group}") : cache("{$key}_{$group}_{$var}");
        if (!$val) {
            if ($var) {
                $where[] = ['var', '=', $var];
                $value = \common\model\SysConfig::where($where)->find();
                $val = $value['val'];
                \think\facade\Cache::tag($key)->set("{$key}_{$group}_{$var}", $conversion($val, $value['type']), 3600);
            } else {
                $val = [];
                $value = \common\model\SysConfig::where($where)->select();
                foreach ($value as $item) {
                    $val[$item['var']] = $conversion($item['val'], $item['type']);
                }
                \think\facade\Cache::tag($key)->set("{$key}_{$group}", $val, 3600);
            }
        }
        return $val;
    }
}

if (!function_exists('limit_flow')) {
    /**
     * 限流
     * @param string $suffix
     * @param string $value
     * @param string $ttl
     * @param string $errmsg
     * @param string $limit_prefix
     * @return bool
     */
    function limit_flow(string $suffix, string $value = '', string $ttl = '', string $errmsg = '',
                        string $limit_prefix = 'limit_flow_'): bool
    {
        $ttl = $ttl ?: env('limit_ttl', 600);
        if ($value === '') {
            $cache = cache($limit_prefix . $suffix);
            if (!$cache) {
                return true;
            }
            abort(-1, $errmsg ?: "发送频率过快, 请{$ttl}秒后重试");
        }
        if (is_null($value)) {
            // 删除限流
            cache($limit_prefix . $suffix, null);
        }
        if ($value) {
            cache($limit_prefix . $suffix, $value, $ttl);
        }
        return true;
    }
}

if (!function_exists('desensitize')) {
    /**
     * 数据脱敏
     * @param string $str
     * @param int $start
     * @param int $len
     * @param string $re
     * @return string
     */
    function desensitize(string $str, int $start = 0, int $len = 0, string $re = '*'): string
    {
        if (empty($str) || empty($len) || empty($re)) return $str;
        $end = $start + $len;
        $strlen = mb_strlen($str);
        $str_arr = [];
        for ($i = 0; $i < $strlen; $i++) {
            if ($i >= $start && $i < $end) {
                $str_arr[] = $re;
            } else {
                $str_arr[] = mb_substr($str, $i, 1);
            }
        }
        return implode('', $str_arr);
    }
}

if (!function_exists('pwdEncrypt')) {
    /**
     * 密码加密
     * @param string $pwd
     * @return string
     */
    function pwdEncrypt(string $pwd): string
    {
        // 盐值
        $salt = env('app.salt', '');
        return $pwd ? substr(md5(hash('sha256', $pwd) . $salt), 8, 16) : '';
    }
}

if (!function_exists('fmtPrice')) {
    /**
     * 格式化金额
     * @param $num
     * @return string
     */
    function fmtPrice($num): string
    {
        if ($num == 0) {
            return "0.00";
        }
        return number_format($num, 2, '.', '');
    }
}

if (!function_exists('getOrderNumber')) {
    /**
     * 生成新的订单号
     * @return string
     */
    function getOrderNumber(): string
    {
        //订单号码主体（YYYYMMDDHHIISSNNNNNNNN）
        $order_id_main = date('YmdHis') . rand(10000000, 99999999);
        //订单号码主体长度
        $order_id_len = strlen($order_id_main);
        $order_id_sum = 0;
        for ($i = 0; $i < $order_id_len; $i++) {
            $order_id_sum += (int)(substr($order_id_main, $i, 1));
        }
        //唯一订单号码（YYYYMMDDHHIISSNNNNNNNNCC）
        return $order_id_main . str_pad((100 - $order_id_sum % 100) % 100, 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('writeLog')) {
    /**
     * 写日志
     * @param string $dir
     * @param array $msg
     * @return void
     */
    function writeLog(string $dir, array $msg): void
    {
        $path = runtime_path() . $dir . '/' . date('Ym') . '/' . date('d') . '.log';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        array_unshift($msg, "==" . date('Y-m-d H:i:s') . "==");
        file_put_contents($path, implode(PHP_EOL, $msg) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('getCurMonthLastDay')) {
    /**
     * 获取月最后一天
     * @param string $date
     * @return string
     */
    function getCurMonthLastDay(string $date): string
    {
        return date('Y-m-d', strtotime(date('Y-m-01', strtotime($date)) . ' +1 month -1 day'));
    }
}

if (!function_exists('pwdEncrypt')) {
    /**
     * 密码加密
     * @param string $pwd
     * @return string
     */
    function pwdEncrypt(string $pwd): string
    {
        // 盐值
        $salt = env('app.salt', '');
        return $pwd ? substr(md5(hash('sha256', $pwd) . $salt), 8, 16) : '';
    }
}

if (!function_exists('ip2Address')) {
    /**
     * IP转地址
     * @return array
     * @throws Exception
     */
    function ip2Address(): array
    {
        $ip = request()->ip();

        $info = (new \Ip2Region())->btreeSearch($ip);
        $regionArr = explode('|', $info['region']);

        return ['ip' => $ip, 'region' => $regionArr];
    }
}
