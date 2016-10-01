<?php

namespace App\Extensions\Wechat;

class MessageType extends \SplEnum
{
    const Text = 0;
    const Audio = 1;
    const Video = 2;
    const Image = 3;
    const Unknown = 4;
}