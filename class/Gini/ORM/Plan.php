<?php

namespace Gini\ORM;

class Plan extends Object
{
    public $group = 'object:group';

    // 版本信息，属于哪一轮采购
    public $version = 'string:80';

    // 使用责任人
    public $owner = 'string:80';
    // 经办人
    public $agent = 'string:80';
    // 院系信息
    public $department = 'string:80';
    // 使用地点
    public $address = 'string:255';
    // 电话
    public $phone = 'string:80';
    // 填报日期
    public $date = 'datetime';
    // email
    public $email = 'string:80';
    // 计划信息
    public $info = 'array';
    // 当前状态
    public $status = 'int,default:0';

    public $ctime = 'datetime';
    public $mtime = 'timestamp';

    // 新建，未提交
    const STATUS_NEW = 0;
    // 申报中
    const STATUS_PENDING = 1;
    // 已申报
    const STATUS_DONE = 2;
    // 被驳回
    const STATUS_REJECTED = 3;
}
