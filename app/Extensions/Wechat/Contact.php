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
            } else if (starts_with($item['UserName'], '@@')) {
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
        return $this->groups;
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

    /**
     * get contact user info
     * @param $userName
     * @param null|array $attributes
     * @return array|mixed
     */
    public function getUser($userName, $attributes = null)
    {
        if ($userName === null) {
            return null;
        }

        $info = array_get($this->friends, $userName,
            array_get($this->groups, $userName),
            array_get($this->public, $userName), []);

        if ($attributes === null) {
            return $info;
        } else if (is_array($attributes)) {
            return array_only($info, $attributes);
        } else {
            return array_get($info, $attributes);
        }
    }
}