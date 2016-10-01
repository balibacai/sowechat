<?php

namespace App\Extensions\Wechat;

class SyncKey
{
    protected $data; // {Count: 3, List: [{Key: 1, Val: 651014297}, {Key: 2, Val: 651014885},…]}

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function refresh($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * 转换为字符串形式, eg. 1_651014297|2_651014885|...
     * @return string
     */
    public function toString()
    {
        return implode('|', array_map(function($item) {
            return $item['Key'] . '_' . $item['Val'];
        }, array_get($this->data, 'List', [])));
    }
}