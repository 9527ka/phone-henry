<?php
// +----------------------------------------------------------------------
// | likeadmin快速开发前后端分离管理后台（PHP版）
// +----------------------------------------------------------------------
// | 欢迎阅读学习系统程序代码，建议反馈是我们前进的动力
// | 开源版本可自由商用，可去除界面版权logo
// | gitee下载：https://gitee.com/likeshop_gitee/likeadmin
// | github下载：https://github.com/likeshop-github/likeadmin
// | 访问官网：https://www.likeadmin.cn
// | likeadmin团队 版权所有 拥有最终解释权
// +----------------------------------------------------------------------
// | author: likeadminTeam
// +----------------------------------------------------------------------

namespace app\adminapi\controller;

use app\adminapi\logic\WorkbenchLogic;
use app\common\model\UserPosters;
use app\common\model\OceanCardOrder;
use app\common\model\Phone;
use think\facade\Filesystem;
/**
 * 工作台
 * Class WorkbenchCotroller
 * @package app\adminapi\controller
 */
class WorkbenchController extends BaseAdminController
{
    public array $notNeedLogin = ['uploadZip','checkOrder'];
    //上传txt zip,储存数据库
    public function upload()
    {
        // 检查文件上传
        $file = request()->file('file');
        if (!$file) {
            return $this->data(['code' => 0,'msg' => '未接收到文件']);
        }

        // 验证文件类型
        $fileExt = $file->getOriginalExtension();
        if ($fileExt !== 'zip' && $fileExt !== 'txt') {
            return $this->data(['code' => 0,'msg' => '仅支持ZIP压缩文件|TXT文件']);
        }
        $path = $fileExt == 'txt'? app()->getRootPath() . 'public/uploads/txt/' : app()->getRootPath() . 'public/uploads/zip/';
        
        // 暂存文件到指定目录
        $filename = time().'.'.$fileExt;
        $filePath = $file->move($path, $filename);
        if (!$filePath) {
            return $this->data(['code' => 0,'msg' => '文件上传失败']);
        }
        // 异步处理文件（可以用 Swoole 或 Workerman 异步任务）
        // 这里使用同步处理解压示例
        if($fileExt == 'zip'){
            $this->processZipFile($filePath->getPathname(),$path);
        }else{
            $this->txtFile($path);
        }
        //清理目录文件
        $txt = app()->getRootPath() . 'public/uploads/txt/';
        $zip = app()->getRootPath() . 'public/uploads/zip/';
        $unzip = app()->getRootPath() . 'public/uploads/unzip/';
        deleteDirectory($txt);
        deleteDirectory($zip);
        deleteDirectory($unzip);
        // 返回成功响应
        return $this->data(['code' => 1,'msg' => '文件上传成功']);
    }
    public function txtFile($path){
        $files = scandir($path);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
                $this->processTxtFile($path . $file);
            }
        }
    }

    // 处理压缩包
    public function processZipFile($filePath)
    {
        // 创建ZipArchive实例解压
        $zip = new \ZipArchive;
        $a = $zip->open($filePath);
        
        if ($zip->open($filePath) === TRUE) {
            $extractPath = app()->getRootPath() . 'public/uploads/unzip/';
            $zip->extractTo($extractPath);
            $zip->close();

            // 查找解压后的所有txt文件
            $files = scandir($extractPath);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
                    $this->processTxtFile($extractPath . $file);
                }
            }
        } else {
            return $this->data(['code' => 0,'msg' => '解压失败']);
        }
    }

    // 处理txt文件
    public function processTxtFile($txtFilePath)
    {
        $content = file_get_contents($txtFilePath);
        $phoneNumbers = explode(PHP_EOL, $content);
        $phoneNumbers = array_map('trim', $phoneNumbers); // 去除空格
        // var_dump($phoneNumbers);die;
        // 批量存储电话号码到数据库
        // Db::startTrans();
        // try {
            foreach ($phoneNumbers as $phoneNumber) {
                if (!empty($phoneNumber)) {
                    if(!Phone::where('phone', $phoneNumber)->value('id')){
                        Phone::create(['phone' => $phoneNumber]);
                    }
                }
            }
            // Db::commit();
            // return $this->success('success');
        // } catch (\Exception $e) {
        //     Db::rollback();
        //     return json('存储数据失败']);
        // }
    }
    //检查是否有新分享订单、充值订单
    public function checkOrder(){
        $orderCount = OceanCardOrder::where('is_tip', 0)->where('state',0)->count();
        $shareCount = UserPosters::where('is_tip', 0)->where('audit_status',0)->count();
        return $this->data(['orderCount' => $orderCount, 'shareCount' => $shareCount]);
    }
    //人工介入时，关闭提示音
    // public function closeOrderTip(){
    //     $act = request()->get('act',1);
    //     if($act == 1){
    //         $orderCount = OceanCardOrder::where('is_tip', 0)->save(['is_tip' => 1]);
    //     }else{
    //         $shareCount = UserPosters::where('is_tip', 0)->save(['is_tip' => 1]);
    //     }
    //     return $this->success();
    // }
    /**
     * @notes 工作台
     * @return \think\response\Json
     * @author 段誉
     * @date 2021/12/29 17:01
     */
    public function index()
    {
        $result = WorkbenchLogic::index();
        return $this->data($result);
    }
}