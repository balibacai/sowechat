<?php

namespace App\Extensions\Wechat;

class SyncCheckStatus
{
    const Normal = 0;
    const Fail = 1;
    const NewMessage = 2;
    const NewJoin = 3;
    const Unknown = 4;

    /**
     * convert enum value to string
     * @param int $value
     * @return string
     */
    public static function getStatus($value)
    {
        static $names = null;
        if (! $names) {
            $names = array_flip((new \ReflectionClass(static::class))->getConstants());
        }

        return strtolower(array_get($names, $value, 'unknown'));
    }
}