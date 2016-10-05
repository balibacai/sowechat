<?php

namespace App\Extensions\Wechat;

class MessageType
{
    const Text = 1;
    const Voice = 34;
    const Video = 62;
    const Image = 3;
    const Emotion = 47;
    const Verify = 37;
    const Card = 42;
    const LiveVideo = 43;
    const Init = 51;
    const Location = 48;
    const LinkShare = 49;
    const VOIP = 50;
    const VOIPNotify = 52;
    const VOIPInvite = 53;
    const SYSNotice = 9999;
    const System = 10000;
    const Revoke = 10002;
    const Unknown = -1;

    // my custom constant
    const PublicLinkShare = -2;
    const Attachment = -3;

    /**
     * convert enum value to string
     * @param int $value
     * @return string
     */
    public static function getType($value)
    {
        static $names = null;
        if (! $names) {
            $names = array_flip((new \ReflectionClass(static::class))->getConstants());
        }

        return strtolower(array_get($names, $value, 'unknown'));
    }

}