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

        if (!empty($error)) {
            return $error;
        }

    }
}
