<?php

namespace Gini\Module;

class NankaiDrugPrecursorBase
{
    public static function setup()
    {
    }

    public static function diagnose()
    {
        $error = [];
        // 1. check database: SHOW TABLES?
        $db = \Gini\Database::db();
        $ret = $db->query('SHOW TABLES');
        if (!$ret) {
            $error[] = 'Please config your database in raw/config/@'
                .($_SERVER['GINI_ENV'] ?: 'production').'/database.yml!';
        }

        $confs = \Gini\Config::get('mall.rpc');
        $conf = $confs['default'] ?: [];
        if (empty($conf)) {
            $error[] = '请配置mall.rpc.default';
        }
        else {
            $rpc = \Gini\IoC::construct('\Gini\RPC', $conf['url']);
            $client = \Gini\Config::get('mall.client');
            if (!$rpc->mall->authorize($client['id'], $client['secret'])) {
                $error[] = '请配置mall.rpc配置存在异常，无法通过authorize';
            }
        }

        if (!empty($error)) {
            return $error;
        }

    }
}
