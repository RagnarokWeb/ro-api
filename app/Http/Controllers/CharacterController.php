<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\APIController;
// use App\Models\User;
use App\Models\CharBase;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Helper\Util;

class CharacterController extends APIController
{
    /**
     * Show the profile for a given user.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function getList(Request $request)
    {
		$tokenInfo = Util::validateToken($request);
        if(!$tokenInfo) {
            return ['error', 'token_expired'];
        }

        $where = [
            'account'     => $tokenInfo['account'],
        ];

        $accountInfo = Account::where($where)->first();
        if(!empty($accountInfo)) {
            $whereCharBase = [
                'accid' => $accountInfo['id']
            ];
            
            $column = [
                'accid',
                'charid',
                'zoneid',
                'name'
            ];
            return CharBase::where($whereCharBase)->get($column);
        }
        else {
            return ['error', 'token_expired'];
        }
    }
}