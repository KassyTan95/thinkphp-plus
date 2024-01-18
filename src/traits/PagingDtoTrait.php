<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2023/6/9
 * @Time: 13:17
 * @describe:
 */

namespace Kassy\ThinkphpPlus\traits;

use think\route\annotation\Rule;

trait PagingDtoTrait
{
    /**
     * 页码
     * @var int
     */
    #[Rule('require', ['page.require' => '页码不能为空'])]
    public int $page;

    /**
     * 每页条数
     * @var int
     */
    #[Rule('require|between:1,100', ['pageSize.require' => '每页条数不能为空', 'pageSize.between' => '每页条数必须在1-100之间'])]
    public int $pageSize;
}
