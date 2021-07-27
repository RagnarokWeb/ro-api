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
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use App\Models\AccountLogs;
use App\Helper\Util;
use App\Models\EmailVerificationCode;

class AccountController extends APIController
{
    /**
     * Show the profile for a given user.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function forgotPassword(Request $request) {
        $input = collect($request->validate([
            'account' => 'required|min:4|max:10|regex:/^[a-zA-Z0-9\_\.@]{4,10}$/i',
            'email' => 'required|email',
        ]))->only('account', 'email')->toArray();

        $inputStep2 = $request->only('code', 'password');
        //step 1: check email and account match
        
        $whereClause = [
            'account'     => $input['account'],
            'email'  => $input['email'],
        ];
        //1.1 check user and email must match
        $userInfo = Account::where($whereClause)->first();
        if(empty($userInfo)) {
            //1.1.1 user and email not match
            return ['error', 'Account or Email does not match'];
        } else {
            //1.1.2 user and email matched
            $whereCode = [
                'account'     => $input['account'],
                'email'  => $input['email']
            ];

            //1.2 find lastest verification code from account wasnt used last 5 minutes
            $emailForgotInfo = EmailVerificationCode::where($whereClause)
                            ->where('type', 'forgot_password')
                            ->where(function($query) {
                                    $query->where('isExist', true)
                                    ->whereBetween('send_date', [Carbon::now()->subMinutes(5), Carbon::now()]);
                            })
                            ->orderBy('send_date', 'desc')
                            ->first();
            //1.2.1 check if user entered verification code and jump to step 2.1
            if(isset($inputStep2['code']) && isset($inputStep2['password'])) {
                //step 2.1: use code to reset password
                //2.1 validate input
                $validator = Validator::make($inputStep2, [
                    'code' => 'required|min:1|max:60',
                    'password' => 'required|min:1|max:60'
                ]);

                if ($validator->fails()) {
                    return ['error', 'Code format or password not accepted!'];
                }
                //if cant get lastest verification code
                if(empty($emailForgotInfo)) {
                    return ['error', 'Your code has been expired, please try again!'];
                }
                //verification code match -> change user password and disable current verification code
                if($inputStep2['code'] == $emailForgotInfo['code']) {
                    $inputStep2['password'] = md5($inputStep2['password']);//md5 hash password

                    $updatePassword = [
                        'password' => $inputStep2['password']
                    ];

                    EmailVerificationCode::where($whereClause)
                                    ->where('isExist', true)
                                    ->where('type', 'forgot_password')
                                    ->update(['isExist' => false]);
                    //update account password
                    //? có cần thêm send email về là user đã đổi pass?
                    Account::where($whereClause)->update($updatePassword);
                    return ['success', 'reset_password'];
                } else {
                    //verification code not match
                    return ['error', 'Your code was wrong, please try again!'];
                }
            }
            //1.2.1 user didnt enter verification code so jump to step 2.2
            //step 2.2 -> send verification code to user email
            //2.2.1 if system wasnt sendmail to user -> send email with randomCode
            if(empty($emailForgotInfo)){
                $randomCode = $this->sendMailToEmail($input['email']);
                if($randomCode) {

                    $emailForgotInfo = [
                        'account'     => $input['account'],
                        'email'  => $input['email'],
                        'code'  => $randomCode,
                        'send_date'  => Carbon::now(),
                        'isExist'  => true,
                        'type'  => 'forgot_password',
                    ];
                    //insert to EmailVerificationCode and waiting for step 2.1
                    EmailVerificationCode::insert($emailForgotInfo);
                    return ['success', 'send_mail'];
                } else {
                    return ['error', 'Cant send mail to ' . $input['email'] . ', please contact administrator!'];
                }
            } else {
                //2.2.2 if system was sent mail to user -> warning user and show "enter verification code" form
                $totalDuration = Carbon::parse($emailForgotInfo['send_date'])->addMinutes(5)->diffInSeconds(Carbon::now());
                return ['error', 'send_mail', 'Please wait ' . $totalDuration . ' seconds to resend your email!'];
            }
            
        }
    }

    public function changeEmail(Request $request) {
        $input = collect($request->validate([
            'email' => 'required|email',
        ]))->only('email')->toArray();
        
        $accountInfo = Util::validateToken($request);
        if(!$accountInfo) {
            return ['error', 'token_expired'];
        } 

        $input['account'] = $accountInfo['account'];

        $inputStep2 = $request->only('code', 'new_email');
        //step 1: check email and account match
        
        $whereClause = [
            'account'     => $input['account'],
            'email'  => $input['email'],
        ];

        $userInfo = Account::where($whereClause)->first();
        if(empty($userInfo)) {
            return ['error', 'account_or_email_not_match'];
        } else {
            $whereCode = [
                'account'     => $input['account'],
                'email'  => $input['email']
            ];

            $emailForgotInfo = EmailVerificationCode::where($whereClause)
                            ->where('type', 'change_email')
                            ->where(function($query) {
                                    $query->where('isExist', true)
                                    ->whereBetween('send_date', [Carbon::now()->subMinutes(5), Carbon::now()]);
                            })
                            ->orderBy('send_date', 'desc')
                            ->first();

            if(isset($inputStep2['code']) && isset($inputStep2['new_email'])) {
                //step 2.1: use code to reset password
                $validator = Validator::make($inputStep2, [
                    'code' => 'required|min:1|max:60',
                    'new_email' => 'required|email'
                ]);

                if ($validator->fails()) {
                    return ['error', 'Code format or email not accepted!'];
                }

                if(empty($emailForgotInfo)) {
                    return ['error', 'Your code has been expired, please try again!'];
                }

                if($inputStep2['code'] == $emailForgotInfo['code']) {

                    $updateAccount = [
                        'old_email' => $input['email'],
                        'email' => $inputStep2['new_email']
                    ];
                   
                    //disable verification code
                    EmailVerificationCode::where($whereClause)
                                    ->where('isExist', true)
                                    ->where('type', 'change_email')
                                    ->update(['isExist' => false]);
                    Account::where($whereClause)->update($updateAccount);
                    //có cần send thêm 1 email về email cũ thông báo user là đã thay email?
                    $tokenInfo = Util::createToken(Account::where('account', $input['account'])->first());
                    return ['success', 'reset_email', $tokenInfo];
                } else {
                    return ['error', 'Your code was wrong, please try again!'];
                }
            }
            //else step 2.2 -> send code to user email
            
            if(empty($emailForgotInfo)){
                $randomCode = $this->sendMailToEmail($input['email'], 'changeemail', 'You are tried to change your email');
                if($randomCode) {

                    $emailForgotInfo = [
                        'account'     => $input['account'],
                        'email'  => $input['email'],
                        'code'  => $randomCode,
                        'send_date'  => Carbon::now(),
                        'isExist'  => true,
                        'type'  => 'change_email',
                    ];
                    EmailVerificationCode::insert($emailForgotInfo);
                    return ['success', 'send_mail'];
                }else {
                    return ['error', 'Cant send mail to ' . $input['email'] . ', please contact administrator!'];
                }
            } else {
                $totalDuration = Carbon::parse($emailForgotInfo['send_date'])->addMinutes(5)->diffInSeconds(Carbon::now());
                return ['error', 'send_mail', 'Please wait ' . $totalDuration . ' seconds to resend your email!'];
            }
            
        }
    }

    public function changePassword(Request $request) {
        $input = collect($request->validate([
            'oldpassword' => 'required|min:4|max:10|regex:/^[a-zA-Z0-9\_\.@]{4,10}$/i',
            'newpassword' => 'required|min:4|max:10|regex:/^[a-zA-Z0-9\_\.@]{4,10}$/i'
        ]))->only('oldpassword', 'newpassword')->toArray();
        $oldPassword = md5($input['oldpassword']);
        $newPassword = md5($input['newpassword']);
        
        $accountInfo = Util::validateToken($request);

        if(!$accountInfo) {
            return ['error', 'token_expired'];
        } else {
            if($oldPassword == $newPassword) {
                return ['error', 'new_password_must_be_different'];
            }
            $whereClause = [
                'account' => $accountInfo['account'],
                'password' => $oldPassword,
            ];
            $whereAccount = Account::where($whereClause)->first();
            if(!empty($whereAccount)) {
                $updatePassword = [
                    'password'=> $newPassword
                ];
                Account::where($whereClause)->update($updatePassword);
                $whereAccount['password'] = $newPassword;
                $tokenInfo = Util::createToken($whereAccount);
                return ['success', $tokenInfo];
            }
            return ['error', 'password_not_match'];
        }
    }

    public function getLogs(Request $request) {
        $accountInfo = Util::validateToken($request);
        if(!$accountInfo) {
            return ['error', 'token_expired'];
        } else {
            $accountLogsColumns = [
                'account',
                'type',
                'charid',
                'zoneid',
                'log_title',
                'log_content',
                'log_time'
            ];
            return AccountLogs::where('account', $accountInfo['account'])->orderBy('log_time', 'desc')->get($accountLogsColumns);
        }
    }

    public function sendMailToEmail($sendTo, $fileName = 'forgotpass', $title = 'Password Reset - Ragnaok M')
    {
        //Create a new PHPMailer instance
        $mail = new PHPMailer();

        //Tell PHPMailer to use SMTP
        $mail->isSMTP();

        //Enable SMTP debugging
        //SMTP::DEBUG_OFF = off (for production use)
        //SMTP::DEBUG_CLIENT = client messages
        //SMTP::DEBUG_SERVER = client and server messages
        $mail->SMTPDebug = SMTP::DEBUG_OFF;

        //Set the hostname of the mail server
        $mail->Host = 'smtp.gmail.com';

        //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
        $mail->Port = 587;

        //Set the encryption mechanism to use - STARTTLS or SMTPS
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        //Whether to use SMTP authentication
        $mail->SMTPAuth = true;

        //Username to use for SMTP authentication - use full email address for gmail
        $mail->Username = env('SMTP_USERNAME', '');

        //Password to use for SMTP authentication
        $mail->Password = env('SMTP_PASSWORD', '');

        //Set who the message is to be sent from
        $mail->setFrom(env('SMTP_USERNAME', ''), 'Ragnaok M - Internal Love');

        //Set an alternative reply-to address
        $mail->addReplyTo(env('SMTP_USERNAME', ''), 'Ragnaok M - Internal Love');

        //Set who the message is to be sent to
        $mail->addAddress($sendTo);

        //Set the subject line
        $mail->Subject = $title;

        //Read an HTML message body from an external file, convert referenced images to embedded,
        //convert HTML into a basic plain-text alternative body
        $randomCode = rand(100000, 999999);
        $mail->msgHTML(str_replace("[code_reset]", $randomCode, file_get_contents(public_path().'/' . $fileName . '.html')), __DIR__);
        // $mail->isHTML(true);       
        // $mail->Body    = 'This is the HTML message body <b>in bold!</b>';

        //Replace the plain text body with one created manually
        $mail->AltBody = 'Your code is : '.$randomCode;

        //Attach an image file
        // $mail->addAttachment('images/phpmailer_mini.png');

        //send the message, check for errors
        if (!$mail->send()) {
            return false;
        } else {
            return $randomCode;
            //Section 2: IMAP
            //Uncomment these to save your message in the 'Sent Mail' folder.
            // if ($this->save_mail($mail)) {
            //     echo "Message saved!";
            // }
        }
    }
}