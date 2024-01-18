<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2023/6/8
 * @Time: 11:23
 * @describe:
 */

namespace Kassy\ThinkphpPlus\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class IgnoreTokenRouting
{
    public function __construct() {}
}
