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
use App\Models\PaymentLogs;
use App\Services\PaypalService;
use App\Helper\Util;

class PaymentController extends APIController
{
    /**
     * Show the profile for a given user.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */

    public function __construct
    (
        PaypalService $service
    )
    {
        $this->paypalService = $service;
    }

    public function getPaypalConfig(Request $request)
    {        
        return $this->paypalService->getConfig();

        $userInfo = Util::validateToken($request);
        if(!$userInfo) {
            return ['error', 'token_expired'];
        }

        $where = [
            'account'     => $userInfo['account'],
            'password'  => $userInfo['password'],
        ];

        $accountInfo = Account::where($where)->first();
        if(!empty($accountInfo)) {
            return $this->paypalService->getConfig();
        }
        else {
            return ['error', 'token_expired'];
        }
    }

    public function processPayment(Request $request)
    {        
        $input = collect($request->validate([
            'paymentID' => 'required',
            'token' => 'required',
            'payerID' => 'required',
        ]))->only('paymentID', 'token', 'payerID')->toArray();

        $userInfo = Util::validateToken($request);
        if(!$userInfo) {
            return ['error', 'token_expired'];
        }

        $where = [
            'account'     => $userInfo['account'],
        ];

        $accountInfo = Account::where($where)->first();
        if(!empty($accountInfo)) {
            $paymentID = $input['paymentID']; 
            $token = $input['token']; 
            $payerID = $input['payerID']; 
            
            $paymentCheck = $this->paypalService->validate($paymentID, $token, $payerID); 
    
            // If the payment is valid and approved 
            if($paymentCheck && $paymentCheck->state == 'approved'){ 
        
                // Get the transaction data 
                $id = $paymentCheck->id; 
                $state = $paymentCheck->state; 
                $payerFirstName = $paymentCheck->payer->payer_info->first_name; 
                $payerLastName = $paymentCheck->payer->payer_info->last_name; 
                $payerName = $payerFirstName.' '.$payerLastName; 
                $payerEmail = $paymentCheck->payer->payer_info->email; 
                $payerID = $paymentCheck->payer->payer_info->payer_id; 
                $payerCountryCode = $paymentCheck->payer->payer_info->country_code; 
                $paidAmount = $paymentCheck->transactions[0]->amount->details->subtotal; 
                $currency = $paymentCheck->transactions[0]->amount->currency; 
                
                // Get product details 
                
                // If payment price is valid 
                    
                // Insert transaction data in the database 
                $data = array( 
                    'txn_id' => $id, 
                    'payment_gross' => $paidAmount, 
                    'currency_code' => $currency, 
                    'payer_id' => $payerID, 
                    'payer_name' => $payerName, 
                    'payer_email' => $payerEmail, 
                    'payer_country' => $payerCountryCode, 
                    'payment_status' => $state 
                ); 

                Account::where($where)->increment('money', $paidAmount);

                $accountLogsData = [
                    'account'     => $userInfo['account'],
                    'log_title'   => 'Paypal recharge',
                    'log_content' => "Recharge [" . $paidAmount . "] " . $currency ,
                    'log_time'    => Carbon::now(),
                    'type'        => 'payment',
                    'signature'   => json_encode($data),
                ];
                AccountLogs::insert($accountLogsData);

                $paymentLogsData = [
                    'card_type'     => 'Paypal',
                    'status'        => $state,
                    'money'         => $paidAmount, 
                    'account_id'    => $accountInfo['id'],
                    'account'       => $accountInfo['account'],
                    'account_email' => $accountInfo['email'],
                    'signature'     => json_encode($data),
                    'note'          => "Recharge [" . $paidAmount . "] " . $currency ,
                    'time'          => Carbon::now(),
                ];

                PaymentLogs::insert($paymentLogsData);
                
                // return payment info
                return $data;
            } else {
                return ['error', 'payment_not_approved'];
            }
        }
        else {
            return ['error', 'token_expired'];
        }
    }
}