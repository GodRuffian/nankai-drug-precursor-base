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

    public static function convert2KG($package, $count, $casNO)
    {
        $data = [
            // 苯乙酸
            '103-82-2'=> 1.081,
            // 醋酸酐
            '108-24-7'=> 1.08,
            // 三氯甲烷
            '67-66-3'=> 1.489,
            // 乙醚
            '60-29-7'=> 0.714,
            // 哌啶
            '110-89-4'=> 0.86,
            // 甲苯
            '108-88-3'=> 0.866,
            // 丙酮
            '67-64-1'=> 0.79,
            // 甲基乙基酮
            '78-93-3'=> 0.804,
            // 高锰酸钾
            '7722-64-7'=> 0,
            // 硫酸
            '7664-93-9'=> 1.84,
            // 盐酸
            '7647-01-0'=> 1.18,
        ];
        $pattern = '/^(\d+(?:\.\d+)?)(ul|ml|l|mg|g|kg)$/i';
        if (!preg_match($pattern, $package, $matches)) {
            return false;
        }

        $value = 0;
        $unit = strtolower($matches[2]);
        $quantity = $matches[1];
        switch ($unit) {
        case 'ul':
            $value = ($quantity / 1000) * $data[$casNO] / 1000;
            break;
        case 'ml':
            $value = $quantity * $data[$casNO] / 1000;
            break;
        case 'l':
            $value = ($quantity * 1000) * $data[$casNO] / 100;
            break;
        case 'mg':
            $value = ($quantity / 1000 / 1000);
            break;
        case 'g':
            $value = ($quantity / 1000);
            break;
        case 'kg':
            $value = $quantity;
            break;
        }
        return round($count * $value, 4);
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

    public function department()
    {
        static $orgs;
        if (!$orgs) $orgs = self::getORGs();
        return $orgs['info'][$this->department];
    }

    public static function getORGs()
    {
        $data = \Gini\Config::get('nankai');
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
}
