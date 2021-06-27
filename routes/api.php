<?php

use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Route;
use App\Http\Controllers;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'auth'], function () {
    Route::get('index', 'AuthController@login');
    Route::post('register', 'AuthController@register');
    Route::post('login', 'AuthController@login');
    Route::get('info', 'AuthController@info');
}); 

Route::group(['prefix' => 'zone'], function () {
    Route::get('getList', 'ZoneController@getList');
}); 

Route::group(['prefix' => 'char'], function () {
    Route::get('getList', 'CharacterController@getList');
}); 

Route::group(['prefix' => 'deposit'], function () {
    Route::get('getList', 'DepositController@getList');
    Route::get('getTypeList', 'DepositController@getTypeList');
    Route::post('buyPackage', 'DepositController@buyPackage');
}); 

Route::group(['prefix' => 'payment'], function () {
    Route::get('getPaypalConfig', 'PaymentController@getPaypalConfig');
    Route::post('processPayment', 'PaymentController@processPayment');
}); 

Route::group(['prefix' => 'account'], function () {
    Route::get('getLogs', 'AccountController@getLogs');
    Route::post('forgotPassword', 'AccountController@forgotPassword');
    Route::post('changeEmail', 'AccountController@changeEmail');
    Route::post('changePassword', 'AccountController@changePassword');
}); 

Route::group(['prefix' => 'giftcode'], function () {
    Route::post('checkCode', 'GiftCodeController@checkCode');
}); 