<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2023/9/1
 * @Time: 15:18
 * @describe:
 */

namespace Kassy\ThinkphpPlus\event;

use common\enums\AuthFlag;
use think\facade\Cache;

class AdminClear
{
    public function handle($param): void
    {
        if ($param && $param['aid']) {
            Cache::delete($param['module'] . config('permission.authFlag', '') . '_' . $param['aid']);
        } else {
            Cache::tag($param['module'] . config('permission.authFlag', ''))->clear();
        }
    }
}
