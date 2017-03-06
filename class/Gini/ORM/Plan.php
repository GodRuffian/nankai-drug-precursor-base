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

    protected static $db_index = [
        'unique:group,round'
    ];

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
            self::STATUS_REJECTED => T('已驳回')
        ];
    }

    public function getNewInfo()
    {
        $products = $this->getRemoteProducts();
        if (is_null($products)) return false;
        $info = (array)$this->info;
        $data = [];
        $orders = [];
        $hasErr = false;
        if (!empty($info)) {
            foreach ($info as $pid=>$value) {
                if (isset($products[$pid])) {
                    if (!$hasErr && (!$products[$pid]['total'] || round($value['total'], 4)!=round($products[$pid]['total'], 4))) {
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
                    $orders = array_merge($orders, (array)$value['orders']);
                }
            }
            if (!$hasErr && !empty(array_diff(array_keys($info), array_keys($products)))) {
                $hasErr = true;
            }
        }
        foreach (array_diff(array_keys($products), array_keys($data)) as $pid) {
            $data[$pid] = $products[$pid];
            $hasErr = true;
        }
        if (!$hasErr) {
            $myOrders = [];
            foreach ($products as $product) {
                $myOrders = array_merge($myOrders, (array)$product['orders']);
            }
            $orders = array_unique($orders);
            $myOrders = array_unique($myOrders);
            if (!empty(array_diff($orders, $myOrders)) || !empty(array_diff($myOrders, $orders))) {
                $hasErr = true;
            }
        }
        return [$hasErr, $data];
    }

    public static function convert2KG($package, $count, $casNO)
    {
        $confs = \Gini\Config::get('drug-precursor.list');
        $pattern = '/^(\d+(?:\.\d+)?)(ul|ml|l|mg|g|kg)$/i';
        if (!preg_match($pattern, $package, $matches)) {
            return false;
        }

        $value = 0;
        $unit = strtolower($matches[2]);
        $package = $matches[1];
        $ml2g = isset($confs[$casNO]['ml2g']) ? $confs[$casNO]['ml2g'] : 0;
        switch ($unit) {
        case 'ul':
            $value = ($package / 1000) * $ml2g / 1000;
            break;
        case 'ml':
            $value = $package * $ml2g / 1000;
            break;
        case 'l':
            $value = ($package * 1000) * $ml2g / 1000;
            break;
        case 'mg':
            $value = ($package / 1000 / 1000);
            break;
        case 'g':
            $value = ($package / 1000);
            break;
        case 'kg':
            $value = $package;
            break;
        }
        return round($count * $value, 4);
    }

    public function getRemoteProducts()
    {
        $confs = \Gini\Config::get('mall.rpc');
        $conf = $confs['default'] ?: [];
        $list = \Gini\Config::get('drug-precursor.list');
        try {
            $rpc = \Gini\IoC::construct('\Gini\RPC', $conf['url']);
            $client = \Gini\Config::get('mall.client');
            $token = $rpc->mall->authorize($client['id'], $client['secret']);
            if (!$token) return;
            $round = $this->round;
            if (!$round->id) return;
            $chronology = (array)$round->chronology;
            $data = (array)$rpc->mall->order->getDrugPrecursorProducts($this->group->id, $chronology);
            $tmp = [];
            foreach ($data as $id=>$value) {
                $tmp[$value['cas_no']]['ids'][] = $id;
                $tmp[$value['cas_no']]['name'] = array_unique(array_merge((array)$tmp[$value['cas_no']]['name'], (array)$value['name']));
                $tmpRA = $tmp[$value['cas_no']]['total'];
                $tmpRB =  self::convert2KG($value['package'], $value['quantity'], $value['cas_no']);
                if ($tmpRA===false || $tmpRB===false) {
                    $tmp[$value['cas_no']]['total'] = false;
                }
                else {
                    $tmp[$value['cas_no']]['total'] += $tmpRB;
                }
                $tmp[$value['cas_no']]['orders'] = array_unique(array_merge((array)$tmp[$value['cas_no']]['orders'], (array)$value['orders']));
                // 商品信息的源数据
                $tmp[$value['cas_no']]['raw'][$id] = [
                    'name'=> $value['name'],
                    'quantity'=> $value['quantity'],
                    'package'=> $value['package'],
                    'spec' => $value['spec'],
                    'vendor_id'=> $value['vendor_id'],
                    'vendor_name'=> $value['vendor_name'],
                ];
            }
            $result = [];
            foreach ($tmp as $casNO=>$value) {
                $result[implode(',', $value['ids'])] = [
                    'name'=> isset($list[$casNO]['name']) ? $list[$casNO]['name'] : implode(',', $value['name']),
                    'cas_no'=> $casNO,
                    'total'=> $value['total'],
                    'orders'=> $value['orders'],
                    // 商品信息的源数据
                    'raw'=> $value['raw'],
                ];
            }
        }
        catch(\Exception $e) {
            $result = null;
        }
        return $result;
    }

    public function department()
    {
        static $orgs;
        if (!$orgs) $orgs = self::getORGs();
        return $orgs['info'][$this->department];
    }

    public static function getORGs()
    {
        $data = \Gini\Config::get('nankai.drug-precursor-orgs');
        $result = [];
        $tree = [];
        $info = [];
        foreach ($data as $value) {
            $key = '';
            foreach ($value as $k=>$v) {
                if (0==$k) {
                    $key = $v['code'];
                    $info[$v['code']] = $v['name'];
                    $tree[$key] = [];
                    continue;
                }
                if (!$key) continue;
                $info[$v[0]['code']] = $v[0]['name'];
                $tree[$key][] = $v[0]['code'];
            }
        }
        return ['tree'=>$tree, 'info'=>$info];
    }

    public function canPrint()
    {
        if (!in_array($this->status, [
                \Gini\ORM\Plan::STATUS_DONE,
            ])) return false;
        $confs = \Gini\Config::get('mall.rpc');
        $conf = $confs['default'] ?: [];
        try {
            $rpc = \Gini\IoC::construct('\Gini\RPC', $conf['url']);
            $client = \Gini\Config::get('mall.client');
            $token = $rpc->mall->authorize($client['id'], $client['secret']);
            if (!$token) return;
            $pos = Those('plan/order')->Whose('plan')->is($this);
            foreach ($pos as $po) {
                $ovouchers[$po->order_voucher] = $po->order_voucher;
            }
            $count = count($ovouchers);
            $statuses = [
                \Gini\ORM\Plan\Order::STATUS_TRANSFERRED,
                \Gini\ORM\Plan\Order::STATUS_PENDING_PAYMENT,
                \Gini\ORM\Plan\Order::STATUS_PAID,
            ];
            $criteria = [
                'voucher' => implode(',', $ovouchers),
                'status' => implode(',', $statuses)
            ];
            $data = (array)$rpc->mall->order->searchOrders($criteria);
            $total = $data['total_count'];
        }
        catch(\Exception $e) {
        }

        return $count && ($total == $count);
    }
}
