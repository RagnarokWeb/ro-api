<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\APIController;
// use App\Models\User;
use App\Models\TableDeposit;
use App\Models\DepositType;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Models\Zone;
use App\Models\Account;
use App\Models\CharBase;
use App\Models\AccountLogs;
use App\Models\BuyPackageLogs;
use App\Helper\Util;

class DepositController extends APIController
{
    /**
     * Show the profile for a given user.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    const MUA_HANG_TUAN = 1;
    const MUA_MOT_LAN = 2;
    const MUA_KHONG_GIOI_HAN = 0;

    public function getList(Request $request)
    {        
        return TableDeposit::get();
    }

    public function getTypeList(Request $request)
    {        
        return DepositType::get();
    }

    public function buyPackage(Request $request)
    {        
        $input = collect($request->validate([
            'charid' => 'required|min:3|max:60|regex:/^[\d]{3,60}$/i',
            'currency' => 'required|min:1|max:60|regex:/^[\d]{1,60}$/i',
            'serverid' => 'required|min:1|max:60|regex:/^[\d]{1,60}$/i',
            'amount' => 'required|min:1|max:60|regex:/^[\d]{1,60}$/i'
        ]))->only('charid', 'currency', 'serverid', 'amount')->toArray();

        $userInfo = Util::validateToken($request);
        if(!$userInfo) {
            return ['error', 'token_expired'];
        }

        $where = [
            'account'     => $userInfo['account']
        ];

        $accountInfo = Account::where($where)->first();
        if(!empty($accountInfo)) {
            
            $whereCharBase = [
                'accid' => $accountInfo['id'],
                'charid' => $input['charid']
            ];
            
            $column = [
                'accid',
                'charid',
                'name'
            ];
            $charInfo = CharBase::where($whereCharBase)->first($column);
            if(empty($charInfo)) {
                return ['error', 'Character ID Not available!'];
            }
            
            $depType = DepositType::where('Type', $input['currency'])->first();
            $zoneInfo = Zone::where('regionid', $input['serverid'])->first();
            if(!empty($depType) && !empty($zoneInfo)) {
                $itemInfo = TableDeposit::where('id', $input['amount'])->first();
                if($accountInfo['money'] < $itemInfo['Price']) {
                    return ['error', 'Your account was not have enough money!'];
                }
                if(!empty($itemInfo)) {
                    try {
                        $domain = "http://103.92.25.253:5559/charge";
                        $paykey = '$D#&@r!@#$%^&o#$@#!$@#DW';

                        $itemID = $itemInfo['ItemID'];
                        $buyType = $itemInfo['BuyType'];
                        $time = time();
                        $str = $paykey.$time.$input['serverid'];
                        $sign =	sha1($str,false);
                        $order 		= strtoupper(md5(uniqid(rand(),true)));

                        switch($buyType) {
                            case self::MUA_HANG_TUAN:
                                $weekStartDate = Carbon::now()->startOfWeek();
                                $weekEndDate = Carbon::now()->endOfWeek();

                                $whereBuyPkg = [
                                    'ItemID'    => $itemID,
                                    'BuyType'   => $buyType,
                                    'charid'    => $input['charid'],
                                    'zoneid'    => $input['serverid'],
                                    'account'   => $userInfo['account'],
                                    'account_id'=> $accountInfo['id'],
                                ];

                                $countBuyPKG = BuyPackageLogs::where($whereBuyPkg)
                                                        ->whereBetween('time', [$weekStartDate, $weekEndDate])
                                                        ->count();
                                if($countBuyPKG > 0) {
                                    return ['error', 'You bought this package this week, please buy it next week!'];
                                    die();
                                }
                                break;
                            case self::MUA_MOT_LAN:

                                $whereBuyPkg = [
                                    'ItemID'    => $itemID,
                                    'BuyType'   => $buyType,
                                    'charid'    => $input['charid'],
                                    'zoneid'    => $input['serverid'],
                                    'account'   => $userInfo['account'],
                                    'account_id'=> $accountInfo['id'],
                                ];

                                $countBuyPKG = BuyPackageLogs::where($whereBuyPkg)
                                                        ->count();
                                if($countBuyPKG > 0) {
                                    return ['error', 'You bought this package once, you can\'t buy it anymore!'];
                                    die();
                                }
                                break;
                            case self::MUA_KHONG_GIOI_HAN:
                                break;
                            default:
                                break;
                        }

                        $p_data = array(
                            "time"		=>  $time,
                            "server_id"	=>  $input['serverid'],
                            "sign"		=>  $sign,
                            "player_id"	=>  $input['charid'],
                            "charge"		=>0,
                            "order_id"		=>$order,
                            "data_id"		=>$itemID,
                            
                        );
                        $pay_url = $domain."?".http_build_query($p_data);
                        $str = file_get_contents($pay_url);
                        
                        $res = json_decode($str);
                        
                        if ($res->status == 1){
                            $accountLogsData = [
                                'account'     => $userInfo['account'],
                                'log_title'   => 'Buy a Package',
                                'log_content' => "Buy Package [" . $itemInfo['Title'] . "] with [" . $itemInfo['Price'] . "] USD",
                                'log_time'    => Carbon::now(),
                                'charid'      =>$input['charid'],
                                'zoneid'      => $input['serverid'],
                                'type'        =>'buy_package'
                            ];

                            $buyPackageLogs = [
                                'account'       => $accountInfo['account'],
                                'account_email' => $accountInfo['email'],
                                'account_id'    => $accountInfo['id'],
                                'time'          => Carbon::now(),
                                'charid'        => $input['charid'],
                                'zoneid'        => $input['serverid'],
                                'ItemID'        => $itemInfo['ItemID'],
                                'BuyType'       => $itemInfo['BuyType']
                            ];

                            BuyPackageLogs::insert($buyPackageLogs);
                            AccountLogs::insert($accountLogsData);
                            Account::where($where)->decrement('money', $itemInfo['Price']);
                            return ['success'];
                        }else {
                            return ['error', $res->message];
                        }
                    } catch(Exception $e) {
                        return ['error', 'Connection to game-server failed!'];
                    }
                    
                }
            }
            return [];
        }
        else {
            return ['error', 'token_expired'];
        }
    }
}