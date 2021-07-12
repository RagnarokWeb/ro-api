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
use App\Models\GiftCode;
use App\Models\GiftCodeLogs;

class GiftCodeController extends APIController
{
    /**
     * Show the profile for a given user.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    const CODE_CHUNG = 1;
    const CODE_RIENG = 2;
    const MUA_KHONG_GIOI_HAN = 0;

    public function checkCode(Request $request)
    {        
        $input = collect($request->validate([
            'charid' => 'required|min:3|max:60|regex:/^[\d]{3,60}$/i',
            'serverid' => 'required|min:1|max:60|regex:/^[\d]{1,60}$/i',
            'code' => 'required|min:1|max:60|regex:/^[A-Za-z0-9\-\_\.@]{1,60}$/'
        ]))->only('charid', 'serverid', 'code')->toArray();

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
            
            $zoneInfo = Zone::where('regionid', $input['serverid'])->first();
            if(!empty($zoneInfo)) {
                $itemInfo = GiftCode::where('code', $input['code'])->first();
                $checkCodeUsed = GiftCodeLogs::where('code', $input['code'])->count();
                
                if(!empty($itemInfo)) {
                    try {
                        // $domain = "http://103.92.25.253:5559/charge";
                        $domain = (string)env('DOMAIN_BUY_PACKAGE', null);
                        $paykey = (string)env('PAY_KEY', null);

                        $itemID = $itemInfo['ItemID'];
                        $buyType = $itemInfo['BuyType'];
                        $giftID = $itemInfo['GiftID'];
                        $time = time();
                        $str = $paykey.$time.$input['serverid'];
                        $sign =	sha1($str,false);
                        $order 		= strtoupper(md5(uniqid(rand(),true)));

                        switch($buyType) {
                            case self::CODE_RIENG:
                                if($checkCodeUsed > 0) {
                                    return ['error', 'You had inputed used code!'];
                                }
                                $whereBuyPkg = [
                                    'ItemID'    => $itemID,
                                    'BuyType'   => $buyType,
                                    'charid'    => $input['charid'],
                                    'zoneid'    => $input['serverid'],
                                    // 'code'   => $input['code']
                                    'GiftID'    => $giftID
                                ];

                                $countBuyPKG = GiftCodeLogs::where($whereBuyPkg)->count();
                                if($countBuyPKG > 0) {
                                    return ['error', 'You had received this code!'];
                                    die();
                                }
                                break;
                            case self::CODE_CHUNG:

                                $whereBuyPkg = [
                                    'ItemID'    => $itemID,
                                    'BuyType'   => $buyType,
                                    'charid'    => $input['charid'],
                                    'zoneid'    => $input['serverid'],
                                    'account'   => $userInfo['account'],
                                    'account_id'=> $accountInfo['id'],
                                    // 'code'      => $input['code'],
                                    'GiftID'    => $giftID
                                ];

                                $countBuyPKG = GiftCodeLogs::where($whereBuyPkg)
                                                        ->count();
                                if($countBuyPKG > 0) {
                                    return ['error', 'You had received this giftcode!'];
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
                                'log_title'   => 'Receive GiftCode',
                                'log_content' => "Receive GiftCode[" . $itemInfo['Title'] . "]",
                                'log_time'    => Carbon::now(),
                                'charid'      =>$input['charid'],
                                'zoneid'      => $input['serverid'],
                                'type'        =>'receive_giftcode'
                            ];

                            $buyPackageLogs = [
                                'account'       => $accountInfo['account'],
                                'account_email' => $accountInfo['email'],
                                'account_id'    => $accountInfo['id'],
                                'time'          => Carbon::now(),
                                'charid'        => $input['charid'],
                                'zoneid'        => $input['serverid'],
                                'ItemID'        => $itemInfo['ItemID'],
                                'BuyType'       => $itemInfo['BuyType'],
                                'code'          => $input['code'],
                            ];

                            GiftCodeLogs::insert($buyPackageLogs);
                            AccountLogs::insert($accountLogsData);
                            // Account::where($where)->decrement('money', $itemInfo['Price']);
                            return ['success'];
                        }else {
                            return ['error', $res->message];
                        }
                    } catch(Exception $e) {
                        return ['error', 'Connection to game-server failed!'];
                    }
                } else {
                    return ['error', 'You had inputed wrong code!'];
                }
            }
            return [];
        }
        else {
            return ['error', 'token_expired'];
        }
    }
}