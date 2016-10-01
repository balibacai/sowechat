<?php

namespace App\Extensions\Wechat;

class MessageType extends \SplEnum
{
    const Text = 0;
    const Voice = 1;
    const Video = 2;
    const Image = 3;
    const Emotion = 4;
    const Unknown = 5;
}