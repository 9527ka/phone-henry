<?php
namespace app\adminapi\controller;

use app\adminapi\controller\BaseAdminController;
use app\adminapi\lists\PhoneLists;
use app\adminapi\logic\PhoneLogic;
use app\adminapi\validate\PhoneValidate;
use app\common\model\Phone;
// use app\common\model\mongo\Phone;
use think\facade\Request;
use ZipArchive;
use think\facade\Filesystem;
use think\facade\Db;


/**
 * Phone控制器
 * Class PhoneController
 * @package app\adminapi\controller
 */
class PhoneController extends BaseAdminController
{
    public function exportPhones()
    {
        // 文件路径，确保路径是 MySQL 有权限写入的目录
        $name = 'phone-'.date('Y-m-d');
        // 2. 生成 txt 文件
        $txtFilePath = app()->getRootPath() . 'public/download/'.$name.'.txt';
        $zipFilePath = app()->getRootPath() . 'public/download/'.$name.'.zip';
        
        // 定义查询
        $sql = "
            SELECT phone 
            INTO OUTFILE '$txtFilePath'
            FIELDS TERMINATED BY ','
            LINES TERMINATED BY '\\n'
            FROM la_phone
        ";
    
        try {
            // 执行原生 SQL 查询
            Db::execute($sql);
             // 设置 zip 文件存储路径
            $zip = new ZipArchive();
            // clearstatcache();
            // 调用 shell 脚本，并传递参数
            $command = "/www/wwwroot/phone.henry/public/download/chown-txt.sh $txtFilePath www";
            // 执行命令并获取输出
            shell_exec($command . " 2>&1");
            if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($txtFilePath, $name.'.txt'); // 将txt文件添加到zip中
                $zip->close();
            } else {
                return $this->data(['error' => '无法创建压缩文件'], 500);
            }
            unlink($txtFilePath);
            // 4. 提供下载链接
            $downloadUrl = request()->domain() . '/download/'.$name.'.zip'; // 下载链接
    
            return $this->data(['download_url' => $downloadUrl]);
            return json(['status' => 'success', 'message' => '数据导出成功', 'file_path' => $filePath]);
        } catch (\Exception $e) {
            return json(['status' => 'error', 'message' => '导出失败: ' . $e->getMessage()]);
        }
        // ini_set('memory_limit', '256M');  // 将内存限制设置为 256MB 或更大
        
        // 1. 查询数据库获取所有 phone 字段的数据o
        // $phones = Phone::column('phone');  // 获取所有 phone 字段
        // $name = 'phone-'.date('Y-m-d');
        // // 2. 生成 txt 文件
        // $txtFilePath = app()->getRootPath() . 'public/download/'.$name.'.txt'; // 设置txt文件存储路径
        // $txtFile = fopen($txtFilePath, 'w');

        // foreach ($phones as $phone) {
        //     fwrite($txtFile, $phone . PHP_EOL); // 写入每条数据并换行
        // }

        // fclose($txtFile);

        // // 3. 压缩为 zip 文件
        // $zipFilePath = app()->getRootPath() . 'public/download/'.$name.'.zip'; // 设置 zip 文件存储路径
        // $zip = new ZipArchive();

        // if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
        //     $zip->addFile($txtFilePath, $name.'.txt'); // 将txt文件添加到zip中
        //     $zip->close();
        // } else {
        //     return $this->data(['error' => '无法创建压缩文件'], 500);
        // }
        // unlink($txtFilePath);
        // // 4. 提供下载链接
        // $downloadUrl = request()->domain() . '/download/'.$name.'.zip'; // 下载链接

        // return $this->data(['download_url' => $downloadUrl]);
    }
    //统计号码总数
    public function total()
    {
        $count = Phone::count('id');
        // $count = 900000;
        return $this->data(['total' => $count]);
    }
    //检查号码是否存在
    public function check()
    {
        $phone = Request::param('phone');
        if(!$phone){
            return $this->data(['code' => 0, 'msg' => '请输入号码<br>Please enter your number']);
        }
        if(!preg_match('/^\d{12}$/', $phone)){
            return $this->data(['code' => 0, 'msg' => '请输入12位号码<br>Please enter a 12-digit number']);
        }
        $sta = Phone::where('phone', $phone)->value('id');
        if($sta){
            return $this->data(['code' => 0, 'msg' => '号码已存在<br>Number already exists']);
        }
        Phone::create(['phone' => $phone]);
        return $this->data(['code' => 1, 'msg' => '号码不存在，已录入<br>The number does not exist and has been entered']);
    }
    /**
     * @notes 获取列表
     * @return \think\response\Json
     * @author likeadmin
     * @date 2024/09/14 23:34
     */
    public function lists()
    {
        return $this->dataLists(new PhoneLists());
        $p = $_GET;
        $lastId = input('get.lastId', '66eacbe884b76b1286d8a419');
        if($p['phone']){
            $map[] = ['phone','=',$p['phone']];
        }
        $page_no = input('get.page_no', 1);
        $page_size = input('get.page_size', 1);
        $mongo_page = ($page_no - 1) * $page_size;
        $list = Phone::where('id','<',$lastId)// 只查询比上一次结果更新的记录
            ->order('id', 'desc')
            // ->limit(15)
            ->page($mongo_page,$page_size)
            ->select()
            ->toArray();
        // echo Phone::getlastsql();die;
        // $list = Phone::order('update_time','DESC')->page(1,10)->select();
        echo json_encode($list);die;
        return $this->dataLists(new PhoneLists());
    }


    /**
     * @notes 添加
     * @return \think\response\Json
     * @author likeadmin
     * @date 2024/09/14 23:34
     */
    public function add()
    {
        $params = (new PhoneValidate())->post()->goCheck('add');
        $result = PhoneLogic::add($params);
        if (true === $result) {
            return $this->success('添加成功', [], 1, 1);
        }
        return $this->fail(PhoneLogic::getError());
    }


    /**
     * @notes 编辑
     * @return \think\response\Json
     * @author likeadmin
     * @date 2024/09/14 23:34
     */
    public function edit()
    {
        $params = (new PhoneValidate())->post()->goCheck('edit');
        $result = PhoneLogic::edit($params);
        if (true === $result) {
            return $this->success('编辑成功', [], 1, 1);
        }
        return $this->fail(PhoneLogic::getError());
    }


    /**
     * @notes 删除
     * @return \think\response\Json
     * @author likeadmin
     * @date 2024/09/14 23:34
     */
    public function delete()
    {
        $params = (new PhoneValidate())->post()->goCheck('delete');
        PhoneLogic::delete($params);
        return $this->success('删除成功', [], 1, 1);
    }


    /**
     * @notes 获取详情
     * @return \think\response\Json
     * @author likeadmin
     * @date 2024/09/14 23:34
     */
    public function detail()
    {
        $params = (new PhoneValidate())->goCheck('detail');
        $result = PhoneLogic::detail($params);
        return $this->data($result);
    }


}