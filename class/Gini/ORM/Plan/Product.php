<?php

namespace Gini\ORM\Plan;

class Product extends \Gini\ORM\Object
{
    // 计划信息
    public $plan = 'object:plan';

    public $product_id = 'bigint';
    public $product_name = 'string:255';
    public $product_cas_no = 'string:15';
    public $product_version = 'int';
    public $product_quantity = 'int';
    public $product_package = 'string:50';

    public $vendor_id = 'bigint';
    public $vendor_name = 'string:150';
}
