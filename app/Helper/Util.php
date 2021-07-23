<?php
namespace App\Helper;

use App\Models\Account;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Util{

    static function validateToken($request) {
        $token = null;
        $header = $request->header('Authorization', '');
        $token = Str::substr($header, 7);//get info from token

        $userInfo = json_decode(base64_decode($token), true);

        $validator = Validator::make($userInfo, [
            'account' => 'required|min:3|max:60|regex:/^[a-zA-Z0-9\_\.]{3,60}$/i',
            'signature' => 'required'
        ]);


        if ($validator->fails()) {
            return false;
        } else {
            $where = [
				'account'     => $userInfo['account']
				// 'password'  => $userInfo['password'],
			];

            $accountInfo = Account::where($where)->first();
			if(empty($accountInfo)) {
                return false;
            } else {
                if(self::validateSignature($accountInfo['password'], $userInfo['signature'])) {
                    return self::createToken($accountInfo);
                } else {
                    return false;
                }
            }
        }
    }

    static function createToken($userInfo) {
        $userInfo['email'] = self::obfuscate_email($userInfo['email']);
        $userInfo['signature'] = self::createHashPassword($userInfo['password']);
        unset($userInfo['password']);
        unset($userInfo['old_email']);
        return $userInfo;
    }

    static function obfuscate_email($email)
    {
        $em   = explode("@",$email);
        $name = implode('@', array_slice($em, 0, count($em)-1));
        $len  = floor(strlen($name)/3);
    
        return substr($name,0, $len) . str_repeat('*', ceil($len* 3 / 2)) . "@" . end($em);   
    }

    static function createHashPassword($password)
    {
        $secretKey = env('TOKEN_SECRET', '');
        $signature = hash_hmac('sha256', $password, $secretKey, true);
        $signature = base64_encode($signature); 
        return $signature;
    }

    static function validateSignature($password, $clientSignature)
    {
        $secretKey = env('TOKEN_SECRET', '');
        $signature = hash_hmac('sha256', $password, $secretKey, true);
        $signature = base64_encode($signature); 
        return $signature == $clientSignature;
    }
    
    static function calcutePaypal($money) {
        $calculated = (float)round((float)$money - (float)((float)(0.044 * (float)$money) + 0.3), 2);//phí ngoài nước 4.4% + 0.3$
        return $calculated;
    }
}