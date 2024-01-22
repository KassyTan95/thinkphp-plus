<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2024/1/18
 * @Time: 10:44
 * @describe:
 */

namespace Kassy\ThinkphpPlus\service;

use think\facade\Filesystem;
use think\file\UploadedFile;

class FileUpload
{
    /**
     * 文件流
     * @var array|UploadedFile|null
     */
    private array|UploadedFile|null $file = null;

    /**
     * 文件夹
     * @var string
     */
    private string $dir;

    /**
     * 上传类型
     * @var string
     */
    private string $uploadType;

    /**
     * 域名
     * @var string
     */
    public string $domain;

    /**
     * 模型
     * @var mixed
     */
    public mixed $model;

    public function __construct()
    {
        $this->file = request()->file();
        $this->dir = request()->post('dir', '');
        $this->uploadType = env('filesystem.driver', 'local');
        $this->domain = env('app.domain', request()->domain());
        $this->model = config('filesystem.model');
    }

    /**
     * 获取上传文件信息
     * @return array
     */
    public function getFileInfo(): array
    {
        $this->file || abort(-1, '上传文件不能为空');

        $this->file = $this->file['file'];

        return [
            'md5'            => $this->file->md5(),
            'ext'            => $this->file->extension(),
            'mime'           => $this->file->getOriginalMime(),
            'realPath'       => $this->file->getRealPath(),
            'size'           => $this->file->getSize(),
            'fileName'       => $this->file->hashName(),
            'dir'            => $this->dir ?: 'file',
            'fileOriginName' => $this->file->getFilename()
        ];
    }

    /**
     * 检查上传文件是否存在
     * @param $md5
     * @return array|Resource|mixed|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function checkExist($md5): mixed
    {
        return $this->model
            ::where('md5', $md5)
            ->field('id,url')
            ->find();
    }

    /**
     * 上传文件
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function up(): array
    {
        $view = $halfPath = $domainPrefix = $fileNameAs = '';
        // 获取上传文件信息
        $fileInfo = $this->getFileInfo();
        // 检查文件是否上传过
        $exist = $this->checkExist($fileInfo['md5']);

        // 禁止上传PHP和HTML文件
        if (in_array($fileInfo['mime'], ['text/x-php', 'text/html']) || in_array($fileInfo['ext'], ['php', 'html'])) {
            abort(-1, '不能上传该类型文件');
        }
        // 图片类型获取宽高
        if (in_array($fileInfo['mime'], ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png',
                'image/webp']) || in_array($fileInfo['ext'], ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
            [$image_width, $image_height] = getimagesize($fileInfo['realPath']);
        }

        switch ($this->uploadType) {
            case 'aliyun':
            case 'qcloud':
                // 域名前缀
                $domainPrefix = config('filesystem.disks.qcloud.scheme', '') . '://' .
                    config('filesystem.disks.qcloud.cdn', '');
                // 文件名字
                $fileNameAs = "/{$fileInfo['dir']}/" . $fileInfo['fileName'];
                break;
            case 'qiniu':
                break;
            default:
                // 域名前缀
                $domainPrefix = $this->domain;
                // 文件名字
                $fileNameAs = "/media/{$fileInfo['dir']}/" . $fileInfo['fileName'];
        }

        // 全路径
        $view = $domainPrefix . ($exist['url'] ?? '');
        // 半路径
        $halfPath = $exist['url'] ?? '';

        if (!$exist) {
            Filesystem::putFileAs($fileInfo['dir'], $this->file, $fileInfo['fileName']);
            // 半路径
            $halfPath = $fileNameAs;
            // 全路径
            $view = $domainPrefix . $halfPath;
            // 存入资源表
            $this->model::create([
                'url'        => $halfPath,
                'width'      => $image_width ?? null,
                'height'     => $image_height ?? null,
                'type'       => $fileInfo['ext'],
                'size'       => round($fileInfo['size'] / 1024, 1),
                'mime'       => $fileInfo['mime'],
                'md5'        => $fileInfo['md5'],
                'uploadType' => $this->uploadType
            ]);
        }

        $res = [
            'url'     => $halfPath,
            'viewUrl' => $view
        ];

        config('filesystem.showFileInfo', false) && $res['fileInfo'] = $fileInfo;

        return $res;
    }
}
