<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Extensions\Wechat\WebApi;

class MessageController extends Controller
{
    /**
     * @var WebApi
     */
    protected $api;

    /**
     * @var array
     */
    protected $user;

    public function __construct()
    {
        $this->api = WebApi::restoreState();
        $this->user = $this->api->getLoginUser();
    }

    /**
     * send message
     * @param Request $request
     * @return bool
     */
    public function sendText(Request $request)
    {
        $this->validate($request, [
            'to' => 'required',
            'content' => 'required',
        ]);

        $to = $request->input('to');
        $content = $request->input('content');

        $result = $this->api->sendMessage($to, $content);
        return [
            'ret' => $result ? 0 : 1,
            'message' => $result ? '' : 'send fail',
        ];
    }

    /**
     * send image
     * @param Request $request
     * @return bool
     */
    public function sendImage(Request $request)
    {
        $this->validate($request, [
            'to' => 'required',
            'path' => 'required',
        ]);

        $to = $request->input('to');
        $path = $request->input('path');

        $result = $this->api->sendImage($to, $path);
        return [
            'ret' => $result ? 0 : 1,
            'message' => $result ? '' : 'send fail',
        ];
    }

    /**
     * send emotion
     * @param Request $request
     * @return bool
     */
    public function sendEmotion(Request $request)
    {
        $this->validate($request, [
            'to' => 'required',
            'path' => 'required',
        ]);

        $to = $request->input('to');
        $path = $request->input('path');

        $result = $this->api->sendEmotion($to, $path);
        return [
            'ret' => $result ? 0 : 1,
            'message' => $result ? '' : 'send fail',
        ];
    }

    /**
     * send file
     * @param Request $request
     * @return bool
     */
    public function sendFile(Request $request)
    {
        $this->validate($request, [
            'to' => 'required',
            'path' => 'required',
        ]);

        $to = $request->input('to');
        $path = $request->input('path');

        $result = $this->api->sendFile($to, $path);
        return [
            'ret' => $result ? 0 : 1,
            'message' => $result ? '' : 'send fail',
        ];
    }
}
