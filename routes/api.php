<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

// wechat message route
Route::post('/wechat/messages/text', 'MessageController@sendText');
Route::post('/wechat/messages/image', 'MessageController@sendImage');
Route::post('/wechat/messages/emotion', 'MessageController@sendEmotion');
Route::post('/wechat/messages/file', 'MessageController@sendFile');
