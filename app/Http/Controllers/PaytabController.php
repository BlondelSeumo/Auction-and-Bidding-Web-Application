<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Utility;
use Illuminate\Support\Facades\DB;
// use Paytabscom\Laravel_paytabs\Facades\paypage;
use App\PayTab\paypage;
use App\Models\Plan;
use App\Models\Order;
use App\Models\UserCoupon;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Exception;

class PaytabController extends Controller
{
    public $paytab_profile_id, $paytab_server_key, $paytab_region, $is_enabled;

    public function paymentConfig()
    {
        if (\Auth::check()) {
            $payment_setting = Utility::getAdminPaymentSetting();
            $settings= Utility::settings();
            config([
                'paytabs.profile_id' => isset($payment_setting['paytab_profile_id']) ? $payment_setting['paytab_profile_id'] : '',
                'paytabs.server_key' => isset($payment_setting['paytab_server_key']) ? $payment_setting['paytab_server_key'] : '',
                'paytabs.region' => isset($payment_setting['paytab_region']) ? $payment_setting['paytab_region'] : '',
                'paytabs.currency' => isset($payment_setting['currency']) ? $payment_setting['currency'] : 'INR',
            ]);
        }
    }
    public function planPayWithpaytab(Request $request)
    {
        try {
            $planID = Crypt::decrypt($request->plan_id);
            $plan = Plan::find($planID);
            $this->paymentconfig();
            $user = Auth::user();
            if ($plan) {
                $get_amount = $plan->price;

                if (!empty($request->coupon)) {
                    $coupons = Coupon::where('code', strtoupper($request->coupon))->where('is_active', '1')->first();
                    if (!empty($coupons)) {
                        $usedCoupun = $coupons->used_coupon();
                        $discount_value = ($plan->price / 100) * $coupons->discount;
                        $get_amount = $plan->price - $discount_value;

                        if ($coupons->limit == $usedCoupun) {
                            return redirect()->back()->with('error', __('This coupon code has expired.'));
                        }
                        if ($get_amount <= 0) {
                            $authuser = Auth::user();
                            $authuser->plan = $plan->id;
                            $authuser->save();
                            $assignPlan = $authuser->assignPlan($plan->id);
                            if ($assignPlan['is_success'] == true && !empty($plan)) {
                                if (!empty($authuser->payment_subscription_id) && $authuser->payment_subscription_id != '') {
                                    try {
                                        $authuser->cancel_subscription($authuser->id);
                                    } catch (\Exception $exception) {
                                        \Log::debug($exception->getMessage());
                                    }
                                }
                                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                                $userCoupon = new UserCoupon();
                                $userCoupon->user = $authuser->id;
                                $userCoupon->coupon = $coupons->id;
                                $userCoupon->order = $orderID;
                                $userCoupon->save();
                                Order()::create(
                                    [
                                        'order_id' => $orderID,
                                        'name' => null,
                                        'email' => null,
                                        'card_number' => null,
                                        'card_exp_month' => null,
                                        'card_exp_year' => null,
                                        'plan_name' => $plan->name,
                                        'plan_id' => $plan->id,
                                        'price' => $get_amount == null ? 0 : $get_amount,
                                        'price_currency' => config('paytabs.currency'),
                                        'txn_id' => '',
                                        'payment_type' => 'Paytab',
                                        'payment_status' => 'success',
                                        'receipt' => null,
                                        'user_id' => $authuser->id,
                                    ]
                                );
                                $assignPlan = $authuser->assignPlan($plan->id);
                                return redirect()->route('plans.index')->with('success', __('Plan Successfully Activated'));
                            }
                        }
                    } else {
                        return redirect()->back()->with('error', __('This coupon code is invalid or has expired.'));
                    }
                }
                $coupon = (empty($request->coupon)) ? "0" : $request->coupon;
                $paypage = new paypage();
                $pay = $paypage->sendPaymentCode('all')
                    ->sendTransaction('sale')
                    ->sendCart(1, $get_amount, 'plan payment')
                    ->sendCustomerDetails(isset($user->name) ? $user->name : "", isset($user->email) ? $user->email : '', '', '', '', '', '', '', '')
                    ->sendURLs(
                        route('plan.paytab.success', ['success' => 1, 'data' => $request->all(), 'plan_id'=>$plan->id, 'amount'=> $get_amount, 'coupon'=> $coupon]),
                        route('plan.paytab.success', ['success' => 0, 'data' => $request->all(), 'plan_id'=>$plan->id, 'amount'=> $get_amount, 'coupon'=> $coupon])
                    )
                    ->sendLanguage('en')
                    ->sendFramed($on = false)
                    ->create_pay_page();
                return $pay;
            } else {
                return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
            }
        } catch (Exception $e) {
            return redirect()->route('plans.index')->with('error', __($e->getMessage()));
        }

    }

    public function PaytabGetPayment(Request $request)
    {
        $this->paymentconfig();

        $planId=$request->plan_id;
        $couponCode=$request->coupon;
        $getAmount=$request->amount;


        if ($couponCode != 0) {
            $coupons = Coupon::where('code', strtoupper($couponCode))->where('is_active', '1')->first();
            $request['coupon_id'] = $coupons->id;
        } else {
            $coupons = null;
        }

        $plan = Plan::find($planId);
        $user = auth()->user();
        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

        try {
            if ($request->success == "1")
            {
                Utility::referralTransaction($plan);

                $order = new Order();
                $order->order_id = $orderID;
                $order->name = $user->name;
                $order->card_number = '';
                $order->card_exp_month = '';
                $order->card_exp_year = '';
                $order->plan_name = $plan->name;
                $order->plan_id = $plan->id;
                $order->price = $getAmount;
                $order->price_currency = config('paytabs.currency');
                $order->payment_type = __('Paytab');
                $order->payment_status = 'success';
                $order->txn_id = '';
                $order->receipt = '';
                $order->user_id = $user->id;
                $order->save();
                $assignPlan = $user->assignPlan($plan->id);
                $coupons = Coupon::find($request->coupon_id);
                if (!empty($request->coupon_id)) {
                    if (!empty($coupons)) {
                        $userCoupon = new UserCoupon();
                        $userCoupon->user = $user->id;
                        $userCoupon->coupon = $coupons->id;
                        $userCoupon->order = $orderID;
                        $userCoupon->save();
                        $usedCoupun = $coupons->used_coupon();
                        if ($coupons->limit <= $usedCoupun) {
                            $coupons->is_active = 0;
                            $coupons->save();
                        }
                    }
                }

                if ($assignPlan['is_success']) {
                    return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
                } else {
                    return redirect()->route('plans.index')->with('error', __($assignPlan['error']));
                }

            }
            else {
                return redirect()->route('plans.index')->with('error', __('Your Transaction is fail please try again'));
            }
        } catch (Exception $e) {
            return redirect()->route('plans.index')->with('error', __($e->getMessage()));
        }
    }

    public function invoicePayWithpaytab(Request $request)
    {

        $invoiceID = \Illuminate\Support\Facades\Crypt::decrypt($request->invoice_id);
        $invoice = Invoice::find($invoiceID);

        $this->invoiceData = $invoice;
        $user      = User::find($invoice->created_by);
        $settings= Utility::settingsById($invoice->created_by);
        $companyPaymentSettings = Utility::getCompanyPaymentSetting($user->id);

        config([
            'paytabs.profile_id' => isset($companyPaymentSettings['paytab_profile_id']) ? $companyPaymentSettings['paytab_profile_id'] : '',
            'paytabs.server_key' => isset($companyPaymentSettings['paytab_server_key']) ? $companyPaymentSettings['paytab_server_key'] : '',
            'paytabs.region' => isset($companyPaymentSettings['paytab_region']) ? $companyPaymentSettings['paytab_region'] : '',
            'paytabs.currency' => isset($settings['site_currency']) ? $settings['site_currency'] : '',
        ]);

        if (\Auth::check()) {
            $user = Auth::user();
        } else
        {
            $user = User::where('id', $invoice->created_by)->first();
        }
        $get_amount = (float)$request->amount;

        if ($invoice && $get_amount != 0)
        {
            if ($get_amount > $invoice->getDue())
            {
                return redirect()->back()->with('error', __('Invalid amount.'));
            }
            else{
                $paypage = new paypage();
                $pay = $paypage->sendPaymentCode('all')
                    ->sendTransaction('sale')
                    ->sendCart(1, $get_amount, 'invoice payment')
                    ->sendCustomerDetails(isset($user->name) ? $user->name : "", isset($user->email) ? $user->email : '', '', '', '', '', '', '', '')
                    ->sendURLs(
                        route('invoice.paytab.success', ['success' => 1,'data' => $request->all(), $invoice->id, 'amount' => $get_amount]),
                        route('invoice.paytab.success', ['success' => 0,'data' => $request->all(), $invoice->id, 'amount' => $get_amount])
                    )
                    ->sendLanguage('en')
                    ->sendFramed($on = false)
                    ->create_pay_page();

                return $pay;
            }
        }
    }

    public function getInvoicePaymentStatus(Request $request, $invoice_id)
    {
        if (!empty($invoice_id))
        {
            $invoice    = Invoice::find($invoice_id);
            $orderID  = strtoupper(str_replace('.', '', uniqid('', true)));
            $settings  = Utility::settingsById($invoice->created_by);
            if ($invoice)
            {
                try
                {

                    if($request->success == "1")
                    {
                        $invoice_payment                 = new InvoicePayment();
                        $invoice_payment->invoice_id     = $invoice_id;
                        $invoice_payment->date           = Date('Y-m-d');
                        $invoice_payment->amount         = $request->has('amount') ? $request->amount : 0;
                        $invoice_payment->account_id         = 0;
                        $invoice_payment->payment_method         = 0;
                        $invoice_payment->order_id      =$orderID;
                        $invoice_payment->payment_type   = 'Paytab';
                        $invoice_payment->receipt     = '';
                        $invoice_payment->reference     = '';
                        $invoice_payment->description     = 'Invoice ' . Utility::invoiceNumberFormat($settings, $invoice->invoice_id);
                        $invoice_payment->save();

                        if($invoice->getDue() <= 0)
                        {
                            $invoice->status = 4;
                            $invoice->save();
                        }
                        elseif(($invoice->getDue() - $invoice_payment->amount) == 0)
                        {
                            $invoice->status = 4;
                            $invoice->save();
                        }
                        else
                        {
                            $invoice->status = 3;
                            $invoice->save();
                        }

                        //for customer balance update
                        Utility::updateUserBalance('customer', $invoice->customer_id, $request->amount, 'debit');

                        //For Notification
                        $setting  = Utility::settingsById($invoice->created_by);

                        $customer = Customer::find($invoice->customer_id);
                        $notificationArr = [
                            'payment_price' => $request->amount,
                            'invoice_payment_type' => 'Paytab',
                            'customer_name' => $customer->name,
                        ];
                        //Slack Notification
                        if(isset($setting['payment_notification']) && $setting['payment_notification'] ==1)
                        {
                            Utility::send_slack_msg('new_invoice_payment', $notificationArr,$invoice->created_by);
                        }
                        //Telegram Notification
                        if(isset($setting['telegram_payment_notification']) && $setting['telegram_payment_notification'] == 1)
                        {
                            Utility::send_telegram_msg('new_invoice_payment', $notificationArr,$invoice->created_by);
                        }
                        //Twilio Notification
                        if(isset($setting['twilio_payment_notification']) && $setting['twilio_payment_notification'] ==1)
                        {
                            Utility::send_twilio_msg($customer->contact,'new_invoice_payment', $notificationArr,$invoice->created_by);
                        }

                        //webhook
                        $module ='New Invoice Payment';
                        $webhook=  Utility::webhookSetting($module,$invoice->created_by);
                        if($webhook)
                        {
                            $parameter = json_encode($invoice_payment);
                            $status = Utility::WebhookCall($webhook['url'],$parameter,$webhook['method']);
                            if($status == true)
                            {
                                return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('error', __('Transaction has been failed.'));
                            }
                            else
                            {
                                return redirect()->back()->with('error', __('Webhook call failed.'));
                            }
                        }
                        if (Auth::user())
                        {


                            return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('success', __('Invoice paid Successfully!') . ((isset($msg) ? '<br> <span class="text-danger">' . $msg . '</span>' : '')));
                        } else {


                            $id = \Crypt::encrypt($invoice_id);
                            return redirect()->route('invoice.link.copy',\Crypt::encrypt($invoice->id))->with('success', __('Invoice paid Successfully!') . ((isset($msg) ? '<br> <span class="text-danger">' . $msg . '</span>' : '')));
                        }
                    }else
                    {
                        if (Auth::user())
                        {
                            return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('error', __('Transaction fail!'));
                        } else {
                            $id = \Crypt::encrypt($invoice_id);
                            return redirect()->route('invoice.link.copy',\Crypt::encrypt($invoice->id))->with('error', __('Transaction fail!'));
                        }
                    }
                } catch (\Exception $e)
                {
                    return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('error', __($e->getMessage()));
                }
            }
            else
            {
                if (Auth::user())
                {
                    return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('error', __('Invoice not found'));
                }
                else{
                    $id = \Crypt::encrypt($invoice_id);
                    return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('error', __('Transaction fail!'));
                }
            }
        } else
        {
            return redirect()->route('invoices.index')->with('error', __('Invoice not found.'));
        }
    }




}
