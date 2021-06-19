<?php
namespace App\Services;
use Illuminate\Http\Request;

class PaypalService{
    public function __construct()
    {
        $this->paypalEnv = (bool)env('PAYPAL_SANDBOX', false) ? 'sandbox' : 'production';
        $this->paypalURL = (bool)env('PAYPAL_SANDBOX', false) ? 'https://api.sandbox.paypal.com/v1/':'https://api.paypal.com/v1/';
        $this->paypalClientID = env('PAYPAL_API_CLIENT_ID', '');
        $this->paypalSecret = env('PAYPAL_API_SECRET', '');
    }
    
    public function validate($paymentID, $paymentToken, $payerID) { 
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $this->paypalURL.'oauth2/token'); 
        curl_setopt($ch, CURLOPT_HEADER, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_POST, true); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_USERPWD, $this->paypalClientID.":".$this->paypalSecret); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials"); 
        $response = curl_exec($ch); 
        curl_close($ch); 
         
        if(empty($response)){ 
            return false; 
        }else{
            $jsonData = json_decode($response); 
            $curl = curl_init($this->paypalURL.'payments/payment/'.$paymentID); 
            curl_setopt($curl, CURLOPT_POST, false); 
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); 
            curl_setopt($curl, CURLOPT_HEADER, false); 
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
            curl_setopt($curl, CURLOPT_HTTPHEADER, array( 
                'Authorization: Bearer ' . $jsonData->access_token, 
                'Accept: application/json', 
                'Content-Type: application/xml' 
            )); 
            $response = curl_exec($curl); 
            curl_close($curl); 
             
            // Transaction data 
            $result = json_decode($response); 
             
            return $result; 
        }
    } 

    public function getConfig() {
        return [
            'paypalEnv' => $this->paypalEnv,
            'paypalClientID' => $this->paypalClientID,
        ];
    }
}