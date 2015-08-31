<?php

namespace Gini\ORM;

class Plan extends Object
{
    public $group = 'object:group';

    // 版本信息，属于哪一轮采购
    public $round = 'object:round';

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
    public $mtime = 'datetime';

    // 新建，未提交
    const STATUS_NEW = 0;
    // 申报中
    const STATUS_PENDING = 1;
    // 已申报
    const STATUS_DONE = 2;
    // 被驳回
    const STATUS_REJECTED = 3;

    public static function getStatus()
    {
        return [
            self::STATUS_NEW => T('未提交'),
            self::STATUS_PENDING => T('申报中'),
            self::STATUS_DONE => T('已申报'),
            self::STATUS_REJECTED => T('被驳回')
        ];
    }

    public function getNewInfo()
    {
        $products = (array)$this->getRemoteProducts();
        $info = (array)$this->info;
        $data = [];
        $hasErr = false;
        if (!empty($info)) {
            foreach ($info as $pid=>$value) {
                if (isset($products[$pid])) {
                    if (!$hasErr && $value['total']!=$products[$pid]['total']) {
                        $hasErr = true;
                    }
                    $data[$pid] = [
                        'name'=> $products[$pid]['name'],
                        'cas_no'=> $products[$pid]['cas_no'],
                        'values'=> $value['values'],
                        'total'=> $products[$pid]['total'],
                        'orders'=> $value['orders'],
                        // 商品信息的源数据
                        'raw'=> $value['raw'],
                    ];
                }
            }
            if (!$hasErr && !empty(array_diff(array_keys($info), array_keys($products)))) {
                $hasErr = true;
            }
        }
        foreach (array_diff(array_keys($products), array_keys($data)) as $pid) {
            $data[$pid] = $products[$pid];
        }
        return [$hasErr, $data];
    }

    public function getRemoteProducts()
    {
        static $result;
        if ($result) return $result;
        $confs = \Gini\Config::get('mall.rpc');
        $conf = $confs['default'] ?: [];
        try {
            $rpc = \Gini\IoC::construct('\Gini\RPC', $conf['url']);
            $client = \Gini\Config::get('mall.client');
            $token = $rpc->mall->authorize($client['id'], $client['secret']);
            if (!$token) return;
            $round = those('round')->orderBy('id', 'desc')->limit(1)->current();
            if (!$round->id) return;
            $data = (array)$rpc->mall->order->getDrugPrecursorProducts(_G('GROUP')->id, $round->ctime, date('Y-m-d H:i:s'));
            $tmp = [];
            foreach ($data as $id=>$value) {
                $tmp[$value['cas_no']]['ids'][] = $id;
                $tmp[$value['cas_no']]['name'] = array_unique(array_merge((array)$tmp[$value['cas_no']]['name'], (array)$value['name']));
                // TODO 单位换算
                $tmp[$value['cas_no']]['total'] += $value['quantity'];
                $tmp[$value['cas_no']]['orders'] = array_unique(array_merge((array)$tmp[$value['cas_no']]['orders'], (array)$value['orders']));
                // 商品信息的源数据
                $tmp[$value['cas_no']]['raw'][$id] = [
                    'name'=> $value['name'],
                    'quantity'=> $value['quantity'],
                    'package'=> $value['package'],
                    'vendor_id'=> $value['vendor_id'],
                    'vendor_name'=> $value['vendor_name'],
                ];
            }
            foreach ($tmp as $casNO=>$value) {
                $result[implode(',', $value['ids'])] = [
                    'name'=> implode(',', $value['name']),
                    'cas_no'=> $casNO,
                    'total'=> $value['total'],
                    'orders'=> $value['orders'],
                    // 商品信息的源数据
                    'raw'=> $value['raw'],
                ];
            }
        }
        catch(\Exception $e) {
        }
        return $result ?: [];
    }
}
