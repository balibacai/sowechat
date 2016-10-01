<?php

namespace App\Extensions\Wechat;

class MessageType extends \SplEnum
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
}