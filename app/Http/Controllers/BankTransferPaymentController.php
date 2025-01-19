<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\InvoiceBankTransfer;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class BankTransferPaymentController extends Controller
{
    protected $invoiceData;

    public function planPayWithBank(Request $request)
    {

        $validator = \Validator::make(
            $request->all(), [
                'payment_receipt' => 'required',
            ]
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        $planID    = \Illuminate\Support\Facades\Crypt::decrypt($request->plan_id);
        $plan      = Plan::find($planID);

        $authuser  = \Auth::user();

        $coupon_id = '';
        if($plan)
        {
            $price = $plan->price;
            if(isset($request->coupon) && !empty($request->coupon))
            {
                $coupons = Coupon::where('code', strtoupper($request->coupon))->where('is_active', '1')->first();

                if(!empty($coupons))
                {
                    $usedCoupun     = $coupons->used_coupon();
                    $discount_value = ($plan->price / 100) * $coupons->discount;
                    $price          = $plan->price - $discount_value;

                    if($coupons->limit == $usedCoupun)
                    {
                        return redirect()->back()->with('error', __('This coupon code has expired.'));
                    }
                    $coupon_id = $coupons->id;
                }
                else
                {
                    return redirect()->back()->with('error', __('This coupon code is invalid or has expired.'));
                }
            }


                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                if(!empty($request->payment_receipt))
                {
                    $fileName = time() . "_" . $request->payment_receipt->getClientOriginalName();
                    $dir        = 'uploads/order';
                    $path = Utility::upload_file($request,'payment_receipt',$fileName,$dir,[]);
                }

                    Order::create(
                        [
                            'order_id' => $orderID,
                            'name' => null,
                            'email' => null,
                            'card_number' => null,
                            'card_exp_month' => null,
                            'card_exp_year' => null,
                            'plan_name' => $plan->name,
                            'plan_id' => $plan->id,
                            'price' => $price,
                            'price_currency' => !empty($payment_setting['currency']) ? $payment_setting['currency'] : 'USD',
                            'txn_id' => '',
                            'payment_type' => 'Bank Transfer',
                            'payment_status' => 'Pending',
                            'receipt' => $fileName,
                            'user_id' => $authuser->id,
                        ]
                    );

                    if(!empty($request->coupon))
                    {
                        $userCoupon         = new UserCoupon();
                        $userCoupon->user   = $authuser->id;
                        $userCoupon->coupon = $coupons->id;
                        $userCoupon->order  = $orderID;
                        $userCoupon->save();

                        $usedCoupun = $coupons->used_coupon();
                        if($coupons->limit <= $usedCoupun)
                        {
                            $coupons->is_active = 0;
                            $coupons->save();
                        }
                    }


            return redirect()->route('plans.index')->with('success', __('Plan payment request send successfully'));

        }
        else
        {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));

        }

    }

    public function orderDestroy($id)
    {
        $order = Order::where('id', $id)->delete();
        return redirect()->back()->with('success', 'Order successfully deleted.');
    }
    public function action($id)
    {
        $order     = Order::find($id);
        $admin_payment_setting = Utility::getAdminPaymentSetting();
        return view('order.action', compact('order','admin_payment_setting'));
    }

    public function changeStatus(Request $request , $order_id)
    {

        $order = Order::find($request->order_id);
        if($request->status == 'Approval')
        {
            $plan       = Plan::find($order->plan_id);
            $authuser   = User::find($order->user_id);
            $authuser->plan = $plan->id;
            $assignPlan = $authuser->assignPlan($plan->id,$authuser->id);
            $order->payment_status           = 'Approved';
            Utility::referralTransaction($plan , $authuser);
        }
        else
        {
            $order->payment_status           = 'Rejected';
        }

        $order->save();

        return redirect()->route('order.index')->with('success', __('Plan payment status updated successfully.'));
    }

    public function customerPayWithBank(Request $request)
    {


        $validator = \Validator::make(
            $request->all(), [
                'payment_receipt' => 'required',
            ]
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }


        $invoiceID = \Illuminate\Support\Facades\Crypt::decrypt($request->invoice_id);
        $invoice   = Invoice::find($invoiceID);

        $user      = User::find($invoice->created_by);
        $settings=Utility::settingsById($invoice->created_by);
        if($invoice)
        {

            if(!empty($request->payment_receipt))
            {
                $fileName = time() . "_" . $request->payment_receipt->getClientOriginalName();
                $dir        = 'uploads/order';
                $path = Utility::upload_file($request,'payment_receipt',$fileName,$dir,[]);



            }
            $orderID           = time();
            InvoiceBankTransfer::create(
                [
                    'invoice_id' =>$invoice->id,
                    'order_id' => $orderID,
                    'amount' => $request->amount,
                    'status' => 'Pending',
                    'date' => date('Y-m-d'),
                    'receipt' => $fileName,
                    'created_by' => $user->id,
                ]
            );
            return redirect()->back()->with('success', __('Invoice payment request send successfully.'));


        }
        else
        {
            return redirect()->back()->with('success', __('Invoice payment request send successfully.'));



        }


    }

    public function invoiceAction($id)
    {

        $invoiceBankTransfer     = InvoiceBankTransfer::find($id);
        $invoice = Invoice::find($invoiceBankTransfer->invoice_id);
        $user_id        = $invoiceBankTransfer->created_by;

        $company_payment_setting = Utility::getCompanyPaymentSetting($user_id);


        return view('invoice.action', compact('invoiceBankTransfer','company_payment_setting','invoice'));
    }

    public function invoiceChangeStatus(Request $request , $invoice_id)
    {

        $invoiceBankTransfer = InvoiceBankTransfer::find($request->order_id);
        $invoice = Invoice::find($invoiceBankTransfer->invoice_id);

        $settings  = DB::table('settings')->where('created_by', '=', $invoiceBankTransfer->created_by)->get()->pluck('value', 'name');

        if($request->status == 'Approval')
        {
            $invoiceBankTransfer->status           = 'Approved';
            $payments = InvoicePayment::create(
                [

                    'invoice_id' => $invoiceBankTransfer->invoice_id,
                    'date' => date('Y-m-d'),
                    'amount' => $invoiceBankTransfer->amount,
                    'payment_method' => 1,
                    'order_id' => $invoiceBankTransfer->order_id,
                    'payment_type' => __('Bank Transfer'),
                    'receipt' => $invoiceBankTransfer->receipt,
                    'description' => __('Invoice') . ' ' . Utility::invoiceNumberFormat($settings, $invoice->invoice_id),
                ]
            );
            $invoiceBankTransfer->delete();
//            dd($payments);
        }
        else
        {
            $invoiceBankTransfer->status           = 'Rejected';
        }
        $invoiceBankTransfer->save();

        return redirect()->back()->with('success', __('Invoice payment request status updated successfully.'));
    }



}
