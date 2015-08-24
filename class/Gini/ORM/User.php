<?php

namespace Gini\ORM;

class User extends Gapper\User
{
    public function isAllowedTo(
        $action, $object = null, $when = null, $where = null)
    {
        if ($object === null) {
            return \Gini\Event::trigger(
                "user.is_allowed_to[$action]",
                $this, $action, null, $when, $where);
        }

        $oname = is_string($object) ? $object : $object->name();

        return \Gini\Event::trigger(
            [
                "user.is_allowed_to[$action].$oname",
                "user.is_allowed_to[$action].*",
            ],
            $this, $action, $object, $when, $where);
    }

}
