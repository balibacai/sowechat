<?php

namespace App\Extensions\Wechat;

class Contact
{
    protected $data = [];
    protected $groupMembers = [];

    public function __construct($data = [])
    {
        $this->addContact($data);
    }

    public function addContact($data)
    {
        foreach ($data as $item) {
            if (($item['VerifyFlag'] & 8) != 0) {
                $type = 'public';
            } else if ($this->isGroup($item['UserName'])) {
                $type = 'group';
            } else if (! starts_with($item['UserName'], '@')) {
                $type = 'special';
            } else {
                $type = 'friend';
            }
            $this->data[$item['UserName']] = array_only($item, ['UserName', 'NickName', 'RemarkName']) + ['Type' => $type];
        }
    }

    public function isGroup($groupName)
    {
        return starts_with($groupName, '@@');
    }

    public function getGroups()
    {
        return array_filter($this->data, function ($item) {
            return $item['Type'] == 'group';
        });
    }

    public function getGroupMembers($groupName = null)
    {
        if ($groupName) {
            return array_get($this->groupMembers, $groupName, []);
        }

        return $this->groupMembers;
    }

    public function setGroupMembers($groupName, $members, $groupInfo)
    {
        if (! isset($this->data[$groupName])) {
            $this->addContact([$groupInfo]);
        }

        $user_names = array_pluck($members, 'UserName');
        $this->groupMembers[$groupName] = $user_names; // only refer

        $this->data += array_combine($user_names,
            array_map(function ($item) use ($groupName) {
                return array_only($item, ['UserName', 'NickName']) + [
                    'Type' => 'group_member',
                    'GroupName' => $groupName,
                ];
            }, $members));
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

        $info = array_get($this->data, $userName, []);

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
        $nicks = array_pluck($this->data, 'UserName', 'NickName');
        $user_name = array_get($nicks, $nickName);

        if ($user_name == null) {
            return [];
        }
        $info = array_get($this->data, $user_name, []);

        if ($attributes === null) {
            return $info;
        } else if (is_array($attributes)) {
            return array_only($info, $attributes);
        } else {
            return array_get($info, $attributes);
        }
    }

    public function toArray()
    {
        return [
            'data' => $this->data,
            'group_members' => $this->groupMembers,
        ];
    }
}