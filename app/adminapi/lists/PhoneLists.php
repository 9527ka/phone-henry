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

namespace app\adminapi\lists;


use app\adminapi\lists\BaseAdminDataLists;
use app\common\model\Phone;
use app\common\lists\ListsSearchInterface;
use app\common\lists\ListsExcelInterface;

/**
 * Phone列表
 * Class PhoneLists
 * @package app\adminapi\lists
 */
class PhoneLists extends BaseAdminDataLists implements ListsExcelInterface
{


    /**
     * @notes 设置搜索条件
     * @return \string[][]
     * @author likeadmin
     * @date 2024/09/14 23:34
     */
    public function setSearch(): array
    {
        return [
            '=' => ['admin_id', 'phone'],
        ];
    }


    /**
     * @notes 获取列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author likeadmin
     * @date 2024/09/14 23:34
     */
    public function lists(): array
    {
        $p = $this->params;
        unset($p['page_size']);
        unset($p['page_no']);
        $arr = filtered_array($p);
        $map = [];
        foreach ($arr as $v){
            if(in_array('start_time',$v)){
                array_push($map,['create_time','between',[strtotime($p['start_time']),strtotime($p['end_time'])]]);
                continue;
            }
            if(in_array('end_time',$v)) continue;
            if(in_array('page_type',$v)) continue;
            if(in_array('page_start',$v)) continue;
            if(in_array('page_end',$v)) continue;
            if(in_array('file_name',$v)) continue;
            if(in_array('export',$v)) continue;
            if(in_array('total',$v)) continue;
            if(in_array('serverResponse',$v)) continue;
            array_push($map,$v);
        }
        // echo json_encode($map);die;
        $list = Phone::where($map)
            ->field(['id', 'admin_id', 'phone', 'create_time'])
            ->limit($this->limitOffset, $this->limitLength)
            ->order(['id' => 'desc'])
            ->select()
            ->toArray();
        // echo OceanCard::getlastsql();die;
        return $list;
    }


    /**
     * @notes 获取数量
     * @return int
     * @author likeadmin
     * @date 2024/09/14 23:34
     */
    public function count(): int
    {
        $p = $this->params;
        unset($p['page_size']);
        unset($p['page_no']);
        $arr = filtered_array($p);
        $map = [];
        foreach ($arr as $v){
            if(in_array('start_time',$v)){
                array_push($map,['create_time','between',[strtotime($p['start_time']),strtotime($p['end_time'])]]);
                continue;
            }
            if(in_array('end_time',$v)) continue;
            if(in_array('page_type',$v)) continue;
            if(in_array('page_start',$v)) continue;
            if(in_array('page_end',$v)) continue;
            if(in_array('file_name',$v)) continue;
            if(in_array('export',$v)) continue;
            if(in_array('total',$v)) continue;
            if(in_array('serverResponse',$v)) continue;
            array_push($map,$v);
        }
        return Phone::where($map)->count();
    }
    
    /**
     * @notes 导出文件名
     * @return string
     * @author 段誉
     * @date 2022/11/24 16:17
     */
    public function setFileName(): string
    {
        return '手机号';
    }
    
    /**
     * @notes 导出字段
     * @return string[]
     * @author 段誉
     * @date 2022/11/24 16:17
     */
    public function setExcelFields(): array
    {
        return [
            'phone' => '手机号',
            'create_time' => '添加时间'
        ];
    }
}