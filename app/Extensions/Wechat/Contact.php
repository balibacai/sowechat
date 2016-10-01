<?php

namespace App\Extensions\Wechat;

class Contact
{
    protected $friends = []; // 好友
    protected $groups = []; // 群
    protected $groupMembers = []; // 群成员
    protected $public = []; // 公众号

    public function __construct($data)
    {
        foreach($data as $item) {
            if ($item['VerifyFlag'] == 8) {
                $this->public[] = $item;
            } else if (starts_with($item, '@@')) {
                $this->groups[] = $item;
            } else {
                $this->friends[] = $item;
            }
        }
    }

    public function getFriends()
    {
        return $this->friends;
    }

    public function getGroups()
    {
        return $this->getGroups();
    }

    public function getPublic()
    {
        return $this->public;
    }

    public function getGroupMembers($groupName = null)
    {
        if ($groupName) {
            return array_get($this->groupMembers, $groupName, []);
        }
        return $this->groupMembers;
    }

    public function setGroupMembers($groupName, $members)
    {
        $this->groupMembers[$groupName] = $members;
    }
}