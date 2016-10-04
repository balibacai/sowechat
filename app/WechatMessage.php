<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WechatMessage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['type', 'content', 'from_user_name', 'from_user_nick', 'to_user_name', 'to_user_nick', 'info'];

}
