<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2024/1/18
 * @Time: 11:02
 * @describe:
 */

namespace Kassy\ThinkphpPlus\model;

use think\exception\ValidateException;
use think\Model;
use think\model\concern\SoftDelete;

class BaseModel extends Model
{
    use SoftDelete;

    protected string $deleteTime = 'deleteAt';

    protected $hidden = ['deleteAt'];

    /**
     * 验证器
     * @param array $data
     * @param string $scene
     * @param string $prefix
     * @return void
     */
    public function valid(array $data, string $scene = '', string $prefix = ''): void
    {
        $validInstance = app("common\\validate\\{$this->getName()}");
        $res = $validInstance->scene($scene)->check($data);
        if (!$res) {
            throw new ValidateException($prefix . $validInstance->getError());
        }
    }
}
