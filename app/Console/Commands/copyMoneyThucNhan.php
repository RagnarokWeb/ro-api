<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PaymentLogs;
use App\Helper\Util;

class copyMoneyThucNhan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'copyMoneyThucNhan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $paymentLogs = PaymentLogs::get();
        $paymentLogs->map(function($item) {
            $amount = $item['money'];
            $paymentLogsData = [
                'money_thuc_nhan' => Util::calcutePaypal($amount),
            ];
            
            PaymentLogs::where('id', $item['id'])->update($paymentLogsData);
        });
        return 0;
    }
}
