<?php

namespace App\Extensions\Wechat;

class SyncCheckStatus
{
    const Normal = 0;
    const Fail = 1;
    const NewMessage = 2;
    const NewJoin = 3;
    const Unknown = 4;
}