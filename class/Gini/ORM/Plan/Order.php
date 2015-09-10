<?php

namespace Gini\ORM\Plan;

class Order extends \Gini\ORM\Object
{
    const STATUS_NEED_VENDOR_APPROVE = 0;
    const STATUS_PENDING_APPROVAL = 1;
    const STATUS_APPROVED = 2;
    const STATUS_RETURNING = 3;
    const STATUS_PENDING_TRANSFER = 4;
    const STATUS_TRANSFERRED = 5;
    const STATUS_PENDING_PAYMENT = 6;
    const STATUS_PAID = 7;
    const STATUS_CANCELED = 8;
    const STATUS_REQUESTING = 9;
    const STATUS_RETURNING_APPROVAL = 10;
    const STATUS_NEED_CUSTOMER_APPROVE = 11;

    // 计划信息
    public $plan = 'object:plan';

    public $order_voucher = 'string:40';
}

