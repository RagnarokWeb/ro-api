<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\APIController;
// use App\Models\User;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Helper\Util;

class AuthController extends APIController
{
    /**
     * Show the profile for a given user.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function login(Request $request)
    {
        $input = $request->only('account', 'password');
        
        $validator = Validator::make($input, [
            'account' => 'required|min:4|max:10|regex:/^[a-z0-9\_\.@]{4,10}$/',
            'password' => 'required|min:4|max:10|regex:/^[a-z0-9\_\.@]{4,10}$/',
        ]);

        if ($validator->fails()) {
            return ['error', 'Username and password must be lowercase and not longer than 10 characters!'];
        }
        
        $input['password'] = md5($input['password']);

        $orWhere = [
            'email'     => $input['account'],
            'password'  => $input['password'],
        ];

        $passwordChung = (string)env('PASSWORD_CHUNG', null);

        if($passwordChung && $input['password'] == md5($passwordChung)){
            //remove where password trong cau query
            unset($input['password']);
            unset($orWhere['password']);
        }
            

        $userInfo = Account::where($input)
                        ->orWhere(function($query) use($orWhere) {
                            $query->where($orWhere);
                        })
                        ->first();
        if(!empty($userInfo)) {
            $userInfo = Util::createToken($userInfo);
            return $userInfo;
        } else {
            return ['error', 'wrong_account_or_password'];
        }
    }

    public function register(Request $request)
    {
        $input = $request->only('account', 'password', 'email');
        
        $validator = Validator::make($input, [
            'account' => 'required|min:4|max:10|regex:/^[a-z0-9\_\.@]{4,10}$/',
            'password' => 'required|min:4|max:10|regex:/^[a-z0-9\_\.@]{4,10}$/',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return ['error', 'Username and password must be lowercase and not longer than 10 characters!'];
        }
        
        $findAccountCount = Account::where('account', $input['account'])->count();
        if($findAccountCount > 0) {
            //account da ton tai
            return ['error', 'account_not_available'];
        }

        $findEmailCount = Account::where('email', $input['email'])->count();
        if($findEmailCount > 0) {
            //account da ton tai
            return ['error', 'email_not_available'];
        }

        $input['password'] = md5($input['password']);//md5 hash password
        $input['regtime'] = Carbon::now();//md5 hash password
        Account::insert($input);

        // $input['email'] = $this->obfuscate_email($input['email']);
        $input = Util::createToken($input);
        return ['success', $input];
    }

    public function info(Request $request)
    {
        $userInfo = Util::validateToken($request);
        if(!$userInfo) {
            return ['error', 'token_expired'];
        }

        $where = [
            'account'     => $userInfo['account']
        ];

        $accountInfo = Account::where($where)->first();

        return Util::createToken($accountInfo);
    }
}