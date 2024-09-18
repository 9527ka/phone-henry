<?php
/**
 * +----------------------------------------------------------------------
 * | 用户资金流水模型
 * +----------------------------------------------------------------------
 */
namespace app\common\model\mongo;
use think\Model;
class Phone extends Model
{
	/** 设置数据库配置 */
	protected $connection = 'mongo1';
	/** 自动完成 */
	protected $auto = [];
    protected $insert = [
        'phone' => 0,
        'update_time' => 0,
    ];
    protected $update = [];
    /** 设置json类型字段 */
    protected $json = [
    ];
    /** 类型转换 */
    protected $type = [
        'phone' => 'integer',
        'update_time' => 'integer',
    ];
    protected static function init()
    {
    }
}
