<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2024/1/18
 * @Time: 11:08
 * @describe:
 */

namespace Kassy\ThinkphpPlus;


use Kassy\ThinkphpPlus\traits\InteractsIgnoreRoute;
use think\annotation\Reader;

class AnnotationService extends \think\Service
{
    use InteractsIgnoreRoute;

    protected Reader $reader;

    public function boot(Reader $reader): void
    {
        $this->reader = $reader;

        $this->registerIgnoreRoute();
    }
}
