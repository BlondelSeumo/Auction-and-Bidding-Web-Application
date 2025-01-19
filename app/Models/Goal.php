<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    protected $fillable = [
        'name',
        'type',
        'from',
        'to',
        'amount',
        'is_display',
        'created_by',
    ];

    public static $goalType = [
        'Invoice',
        'Bill',
        'Revenue',
        'Payment',
    ];

    // public function target($type, $from, $to, $amount)
    // {
    //     $total    = 0;
    //     //$fromDate = $from . '-01';
    //     //$toDate   = $to . '-01';
    //     $fromDate = $from . '-00'; // Changed by Wagner Aquino at 03/03/2023 - 13:46 hrs (GMT -3)
    //     $toDate   = $to . '-00'; // Changed by Wagner Aquino at 03/03/2023 - 13:46 hrs (GMT -3)

    //     if(\App\Models\Goal::$goalType[$type] == 'Invoice')
    //     {
    //         $invoices = Invoice:: select('*')->where('created_by', \Auth::user()->creatorId())->where('issue_date', '>=', $fromDate)->where('issue_date', '<=', $toDate)->get();
    //         $total    = 0;
    //         foreach($invoices as $invoice)
    //         {
    //             $total += $invoice->getTotal();
    //         }
    //     }
    //     elseif(\App\Models\Goal::$goalType[$type] == 'Bill')
    //     {
    //         $bills = Bill:: select('*')->where('created_by', \Auth::user()->creatorId())->where('bill_date', '>=', $fromDate)->where('bill_date', '<=', $toDate)->get();
    //         $total = 0;
    //         foreach($bills as $bill)
    //         {
    //             $total += $bill->getTotal();
    //         }
    //     }
    //     elseif(\App\Models\Goal::$goalType[$type] == 'Revenue')
    //     {

    //         $revenues = Revenue:: select('*')->where('created_by', \Auth::user()->creatorId())->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->get();

    //         $total    = 0;

    //         foreach($revenues as $revenue)
    //         {
    //             $total += $revenue->amount;
    //         }
    //     }
    //     elseif(\App\Models\Goal::$goalType[$type] == 'Payment')
    //     {
    //         $payments = Payment:: select('*')->where('created_by', \Auth::user()->creatorId())->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->get();
    //         $total    = 0;

    //         foreach($payments as $payment)
    //         {
    //             $total += $payment->amount;
    //         }

    //     }

    //     $data['percentage'] = ($total * 100) / $amount;
    //     $data['total']      = $total;

    //     return $data;
    // }

    public function target($type, $from, $to, $amount)
    {
        $total    = 0;
        $fromDate = $from . '-00'; // Changed by Wagner Aquino at 03/03/2023 - 13:46 hrs (GMT -3)
        $toDate   = $to . '-00'; // Changed by Wagner Aquino at 03/03/2023 - 13:46 hrs (GMT -3)
        
        if(\App\Models\Goal::$goalType[$type] == 'Invoice')
        {
            $total        = Utility::getInvoiceProductsData($fromDate , $toDate);
        }
        elseif(\App\Models\Goal::$goalType[$type] == 'Bill')
        {
            $total           = Utility::getBillProductsData($fromDate , $toDate);
        }

        elseif(\App\Models\Goal::$goalType[$type] == 'Revenue')
        {
            $total           = self::getRevenueData($fromDate , $toDate);
        }
        elseif(\App\Models\Goal::$goalType[$type] == 'Payment')
        {
            $total           = self::getPaymentData($fromDate , $toDate);
        }

        $data['percentage'] = ($total * 100) / $amount;
        $data['total']      = $total;

        return $data;
    }

    public static $revenuesData = null;
    public static $paymentsData = null;

    public static function getRevenueData($fromDate, $toDate)
    {
        if (self::$revenuesData === null) {
            $total = Revenue::where('created_by', \Auth::user()->creatorId())->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->sum('amount');

            self::$revenuesData = $total;
        }

        return self::$revenuesData;
    }

    public static function getPaymentData($fromDate, $toDate)
    {
        if (self::$paymentsData === null) {
            $total = Payment::where('created_by', \Auth::user()->creatorId())->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->sum('amount');

            self::$paymentsData = $total;
        }

        return self::$paymentsData;
    }

}
