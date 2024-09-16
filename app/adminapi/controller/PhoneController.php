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


use app\adminapi\controller\BaseAdminController;
use app\adminapi\lists\PhoneLists;
use app\adminapi\logic\PhoneLogic;
use app\adminapi\validate\PhoneValidate;
use app\common\model\Phone;
use think\facade\Request;
/**
 * Phone控制器
 * Class PhoneController
 * @package app\adminapi\controller
 */
class PhoneController extends BaseAdminController
{
    //统计号码总数
    public function total()
    {
        $count = Phone::where('delete_time',null)->count();
        return $this->data(['total' => $count]);
    }
    //检查号码是否存在
    public function check()
    {
        $phone = Request::param('phone');
        if(!$phone){
            return $this->success('请输入号码', [], 1, 1);
        }
        if(!preg_match('/^\d{12}$/', $phone)){
            return $this->success('请输入12位号码', [], 1, 1);
        }
        $sta = Phone::where('phone', $phone)->value('id');
        if($sta){
            return $this->success('号码已存在', [], 1, 1);
        }
        Phone::create(['phone' => $phone]);
        return $this->success('号码不存在，已录入', [], 1, 1);
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