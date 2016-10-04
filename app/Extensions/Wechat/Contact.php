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
            $value = array_only($item, ['UserName', 'NickName']);
            if ($item['VerifyFlag'] == 8) {
                $this->public[$item['UserName']] = $value;
            } else if (starts_with($item['UserName'], '@@')) {
                $this->groups[$item['UserName']] = $value;
            } else {
                $this->friends[$item['UserName']] = $value;
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
        $this->groupMembers[$groupName] = array_combine(array_pluck($members, 'UserName'),
            array_map(function ($item) {
                return array_only($item, ['UserName', 'NickName']);
            }, $members)
        );
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

    /**
     * get contact user info
     * @param $nickName
     * @param null|array $attributes
     * @return array|mixed
     */
    public function getUserByNick($nickName, $attributes = null)
    {
        $all = array_merge($this->friends, $this->groups, $this->public);
        $nicks = array_combine(array_pluck($all, 'NickName'), $all);
        $info = array_get($nicks, $nickName, []);

        if ($attributes === null) {
            return $info;
        } else if (is_array($attributes)) {
            return array_only($info, $attributes);
        } else {
            return array_get($info, $attributes);
        }
    }
}