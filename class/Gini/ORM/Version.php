<?php

namespace Gini\ORM;

class Version extends Object
{
    public $group = 'object:group';

    // 状态
    public $status = 'int,default:0';

    // 开始时间
    public $start_time = 'datetime';
    //结束时间
    public $end_time = 'datetime';

    public $ctime = 'datetime';
    public $mtime = 'datetime';

    // 关闭
    const STATUS_OFF = 0;
    // 开启
    const STATUS_ON = 1;
}
