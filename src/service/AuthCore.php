<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2023/9/1
 * @Time: 14:56
 * @describe:
 */

namespace Kassy\ThinkphpPlus\service;

use think\facade\Cache;

class AuthCore
{
    /**
     * 实例
     * @var AuthCore|null
     */
    private static ?AuthCore $instance = null;

    /**
     * 模块
     * @var string
     */
    private string $module;

    /**
     * 权限标识
     * @var string
     */
    private string $id;

    /**
     * 是否为系统管理员
     * @var int
     */
    private int $isSystem;

    /**
     * 管理员模型
     * @var mixed
     */
    private mixed $Administrator;

    /**
     * 管理员菜单模型
     * @var mixed
     */
    private mixed $AdministratorMenu;

    /**
     * 权限标识
     * @var string
     */
    private string $authFlag;

    /**
     * 实例化入口
     * @param string|null $id
     * @return AuthCore
     */
    public static function Instance(?string $id = null): AuthCore
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($id);
        }
        return self::$instance;
    }

    private function __construct(?string $id = null)
    {
        $this->module = app()->http->getName();
        $this->Administrator = config('permission.adminModel');
        $this->AdministratorMenu = config('permission.menuModel');
        $this->authFlag = config('permission.authFlag', '');
        $token = request()->payload;
        if (!$id && empty((array)$token)) {
            abort(-99, '请先登录');
        }
        $this->id = $id ?: $token['aid'];
        $this->isSystem = $token['isSystem'] ?? 0;
    }

    private function __clone(): void {}

    /**
     * 获取菜单
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getMenu(): array
    {
        return $this->genAuthData();
    }

    /**
     * 验证权限
     * @param string $url
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function verify(string $url): bool
    {
        if (!$this->isSystem) {
            $authData = $this->genAuthData();
            if (!in_array($url, $authData['url'])) {
                abort(-88, '权限不足');
            }
        }
        return true;
    }

    /**
     * 生成权限数据
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function genAuthData(): mixed
    {
        $userAuth = Cache::get($this->module . $this->authFlag . '_' . $this->id);

        if (!$userAuth) {
            // 查询用户权限组所包含权限数据
            $userAuthData = self::ownAuthData();
            if ($userAuthData['roleInfo']['rules'] == -1 || $userAuthData['roleInfo']['btnRules'] == -1) {
                $userAuth = $this->handleAuthData(self::fullAuthData());
            } else {
                $ownMenu = $this->AdministratorMenu
                    ->where('id', 'in', $userAuthData['roleInfo']['rules'])
                    ->field('id,pid,name,path,component,meta,apiList')
                    ->order(['sort' => 'desc', 'id' => 'desc'])
                    ->select();
                if (!$ownMenu->isEmpty()) {
                    $permissionsFlip = array_flip(explode(',', $userAuthData['roleInfo']['btnRules']));
                    $userAuth = $this->handleAuthData($ownMenu->toArray(), $permissionsFlip);
                }
            }
            $userAuth['menu'] = buildMenuChild($userAuth['menu'], 0);
            Cache::tag($this->module . $this->authFlag)->set($this->module . $this->authFlag . '_' . $this->id, $userAuth);
        }

        return $userAuth;
    }

    /**
     * 处理权限数据
     * @param $fullAuthData
     * @param array|null $flip
     * @return array
     */
    private function handleAuthData($fullAuthData, ?array $flip = null): array
    {
        // 菜单
        $userAuth['menu'] = [];
        // 按钮标识
        $userAuth['permissions'] = [];
        // 权限链接
        $userAuth['url'] = [];
        foreach ($fullAuthData as &$fullAuthDatum) {
            if ($flip) {
                if ($fullAuthDatum['apiList']) {
                    $fullAuthDatum['apiList'] = array_filter($fullAuthDatum['apiList'], function ($value) use ($flip) {
                        return array_key_exists($value['code'], $flip);
                    });
                }
            }
            $userAuth['menu'][] = $fullAuthDatum;
            if ($fullAuthDatum['apiList']) {
                foreach ($fullAuthDatum['apiList'] as $listItem) {
                    $userAuth['permissions'][] = $listItem['code'];
                    $userAuth['url'][] = $listItem['url'];
                }
            }
        }
        return $userAuth;
    }

    /**
     * 全部权限数据
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function fullAuthData(): array
    {
        return $this->AdministratorMenu
            ->where('id', '>', 0)
            ->field('id,pid,name,path,component,meta,apiList')
            ->order(['sort' => 'asc', 'createAt' => 'asc'])
            ->select()
            ->toArray();
    }

    /**
     * 获取自己拥有的权限数据
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function ownAuthData(): mixed
    {
        return $this->Administrator
            ->where([
                ['id', '=', $this->id],
                ['status', '=', 1]
            ])
            ->field('id,username,name')
            ->withJoin(['roleInfo' => ['rules', 'btnRules', 'desc']])
            ->find();
    }
}
