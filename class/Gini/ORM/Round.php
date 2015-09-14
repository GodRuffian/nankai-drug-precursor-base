<?php

namespace Gini\ORM;

class Round extends Object
{
    // 状态
    public $status = 'int,default:0';

    // 开始时间
    public $start_time = 'datetime';
    // 结束时间
    public $end_time = 'datetime';

    // 开启和关闭的时序
    public $chronology = 'array';

    public $ctime = 'datetime';
    public $mtime = 'datetime';

    // 关闭
    const STATUS_OFF = 0;
    // 开启
    const STATUS_ON = 1;
    // 正在关闭
    const STATUS_CLOSING = 2;
}
