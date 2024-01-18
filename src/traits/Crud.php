<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2024/1/18
 * @Time: 10:25
 * @describe:
 */

namespace Kassy\ThinkphpPlus\traits;

use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Patch;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\response\Json;

trait Crud
{
    /**
     * 列表where
     * @var mixed|null
     */
    protected mixed $listWhere = null;

    /**
     * 列表中间操作
     * @var mixed|null
     */
    protected mixed $listMiddle = null;

    /**
     * 列表后置操作
     * @var mixed|null
     */
    protected mixed $listAfter = null;

    /**
     * 详情where
     * @var mixed|null
     */
    protected mixed $infoWhere = null;

    /**
     * 详情数据操作
     * @var mixed|null
     */
    protected mixed $infoDataOperate = null;

    /**
     * 删除前置操作
     * @var mixed|null
     */
    protected mixed $delBefore = null;

    /**
     * 添加前置操作
     * @var mixed|null
     */
    protected mixed $addBefore = null;

    /**
     * 添加后置操作
     * @var mixed|null
     */
    protected mixed $addAfter = null;

    /**
     * 编辑前置操作
     * @var mixed|null
     */
    protected mixed $editBefore = null;

    /**
     * 编辑后置操作
     * @var mixed|null
     */
    protected mixed $editAfter = null;

    /**
     * 是否关联
     * @var bool
     */
    protected bool $isRelation = false;

    /**
     * 列表
     * @return Json
     * @throws \think\db\exception\DbException
     */
    #[Get('/list')]
    public function list(): Json
    {
        $param = $this->request->get();

        $where = [];

        if (!$this->isRelation) {
            $where[] = [$this->model->getPk(), '>', 0];
        }

        if ($this->listWhere) {
            ($this->listWhere)($param, $where);
        }

        $obj = $this->model
            ->where($where)
            ->withoutField('updateAt,deleteAt');
        if ($this->listMiddle) {
            ($this->listMiddle)($obj);
        }
        $data = $obj->order('createAt desc')->paginate($param['limit']);
        if ($this->listAfter) {
            ($this->listAfter)($data);
        }

        return apiResp($data);
    }

    /**
     * 详情
     * @return Json
     * @throws \think\db\exception\DbException
     */
    #[Get('/info')]
    public function info(): Json
    {
        $param = $this->request->get();

        $where = [
            [$this->model->getPk(), '=', $param[$this->model->getPk()]]
        ];
        if ($this->infoWhere) {
            ($this->infoWhere)($param, $where);
        }

        $data = $this->model
            ->where($this->model->getPk(), $param[$this->model->getPk()])
            ->withoutField('createAt,updateAt,deleteAt')
            ->find();
        if ($this->infoDataOperate) {
            ($this->infoDataOperate)($data);
        }

        return apiResp($data);
    }

    /**
     * 添加
     * @return Json
     */
    #[Post('/add')]
    public function add(): Json
    {
        $param = $this->request->post();

        app()->db->startTrans();

        if ($this->addBefore) {
            ($this->addBefore)($param);
        }

        $this->model->valid($param, 'add');

        $obj = $this->model::create($param);

        if ($this->addAfter) {
            ($this->addAfter)($param, $obj);
        }

        app()->db->commit();

        return apiResp(msg: '添加成功', code: 1);
    }

    /**
     * 编辑
     * @return Json
     */
    #[Put('/edit')]
    public function edit(): Json
    {
        $param = $this->request->put();

        app()->db->startTrans();

        if ($this->editBefore) {
            ($this->editBefore)($param);
        }

        $this->model->valid($param, 'edit');

        $obj = $this->model::update($param);

        if ($this->editAfter) {
            ($this->editAfter)($param, $obj);
        }

        app()->db->commit();

        return apiResp(msg: '编辑成功', code: 1);
    }

    /**
     * 删除
     * @return Json
     */
    #[Delete('/del')]
    public function del(): Json
    {
        $id = $this->request->delete($this->model->getPk());

        if ($this->delBefore) {
            ($this->delBefore)($id);
        }

        $this->model::destroy($id);

        return apiResp(msg: '删除成功', code: 1);
    }

    /**
     * 属性修改
     * @return Json
     */
    #[Patch('/modify')]
    public function modify(): Json
    {
        $param = $this->request->patch();

        $this->model::update($param);

        return apiResp(msg: '操作成功', code: 1);
    }
}
