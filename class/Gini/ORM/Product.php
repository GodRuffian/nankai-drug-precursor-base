<?php

namespace Gini\ORM;

class Product extends Object
{
    // 计划信息
    public $plan = 'object:plan';

    public $product_id = 'bigint';
    public $product_name = 'string:255';

    public $vendor_id = 'bigint';
    public $vendor_name = 'string:150';
}
