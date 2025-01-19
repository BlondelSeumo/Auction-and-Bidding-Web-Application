<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\Utility;
// use CoinGate\CoinGate;
use App\Coingate\Coingate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class CoingatePaymentController extends Controller
{
    //


    public $mode;
    public $coingate_auth_token;
    public $is_enabled;
    protected $invoiceData;
    protected $currency;

    public function paymentConfig()
    {

            $payment_setting = Utility::getAdminPaymentSetting();

        $this->coingate_auth_token = isset($payment_setting['coingate_auth_token']) ? $payment_setting['coingate_auth_token'] : '';
        $this->mode                = isset($payment_setting['coingate_mode']) ? $payment_setting['coingate_mode'] : 'off';
        $this->is_enabled          = isset($payment_setting['is_coingate_enabled']) ? $payment_setting['is_coingate_enabled'] : 'off';
        $this->currency            = isset($payment_setting['currency']) ? $payment_setting['currency'] : 'USD';

        return $this;
    }

    public function companyPaymentConfig()
    {
        $payment_setting = Utility::getCompanyPaymentSetting($this->invoiceData->created_by);

        $setting = Utility::settingsById($this->invoiceData->created_by);

        $this->coingate_auth_token = isset($payment_setting['coingate_auth_token']) ? $payment_setting['coingate_auth_token'] : '';
        $this->mode                = isset($payment_setting['coingate_mode']) ? $payment_setting['coingate_mode'] : 'off';
        $this->is_enabled          = isset($payment_setting['is_coingate_enabled']) ? $payment_setting['is_coingate_enabled'] : 'off';
        $this->currency            = isset($setting['site_currency']) ? $setting['site_currency'] : 'USD';

        return $this;
    }

    public function planPayWithCoingate(Request $request)
    {
//        dd($request->all());
        $payment    = $this->paymentConfig();
        $planID     = Crypt::decrypt($request->plan_id);
        $plan       = Plan::find($planID);
        $authuser   = Auth::user();
        $coupons_id = '';

        if($plan)
        {
            $price = $plan->price;
            if(isset($request->coupon) && !empty($request->coupon))
            {
                $request->coupon = trim($request->coupon);
                $coupons         = Coupon::where('code', strtoupper($request->coupon))->where('is_active', '1')->first();

                if(!empty($coupons))
                {
                    $usedCoupun             = $coupons->used_coupon();
                    $discount_value         = ($price / 100) * $coupons->discount;
                    $plan->discounted_price = $price - $discount_value;
                    $coupons_id             = $coupons->id;
                    if($usedCoupun >= $coupons->limit)
                    {
                        return redirect()->back()->with('error', __('This coupon code has expired.'));
                    }
                    $price = $price - $discount_value;
                }
                else
                {
                    return redirect()->back()->with('error', __('This coupon code is invalid or has expired.'));
                }
            }

            if($price <= 0)
            {
                $authuser->plan = $plan->id;
                $authuser->save();

                $assignPlan = $authuser->assignPlan($plan->id);

                if($assignPlan['is_success'] == true && !empty($plan))
                {

                    $orderID = time();
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
                            'price' => $price == null ? 0 : $price,
                            'price_currency' => $this->currency,
                            'txn_id' => '',
                            'payment_type' => 'coingate',
                            'payment_status' => 'success',
                            'receipt' => null,
                            'user_id' => $authuser->id,
                        ]
                    );
                    $assignPlan = $authuser->assignPlan($plan->id);

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

                    return redirect()->route('plans.index')->with('success', __('Plan activated Successfully!'));
                }
                else
                {
                    return redirect()->back()->with('error', __('Plan fail to upgrade.'));
                }
            }
            CoinGate::config(
                array(
                    'environment' => $this->mode,
                    'auth_token' => $this->coingate_auth_token,
                    'curlopt_ssl_verifypeer' => FALSE,
                )
            );
            $post_params = array(
                'order_id' => time(),
                'price_amount' => $price,
                'price_currency' => $this->currency,
                'receive_currency' => $this->currency,
                'callback_url' => route(
                    'plan.coingate', [
                                       $request->plan_id,
                                       'coupon_id=' . $coupons_id,
                                   ]
                ),
                'cancel_url' => route('stripe', [$request->plan_id]),
                'success_url' => route(
                    'plan.coingate', [
                                       $request->plan_id,
                                       'coupon_id=' . $coupons_id,
                                   ]
                ),
                'title' => 'Plan #' . time(),
            );



            $order = CoinGate::coingatePayment($post_params, 'POST');


            if($order['status_code'] === 200)
            {
                $response = $order['response'];
                return redirect($response['payment_url']);
            }
            else
            {
                return redirect()->back()->with('error', __('opps something wren wrong.'));
            }
        }
        else
        {
            return redirect()->back()->with('error', 'Plan is deleted.');
        }

    }

    public function getPaymentStatus(Request $request,$plan)
    {

        $user                  = Auth::user();
        $planID  = Crypt::decrypt($plan);
        $plan_id               = $planID;
        $store_id              = \Auth::user()->current_store;
        $admin_payment_setting = Utility::getAdminPaymentSetting();
        $plan                  = Plan::find($plan_id);
        $price                 = $plan->price;
        if($plan)
        {
            // try
            // {
            $orderID = time();
            if($request->has('coupon_id') && $request->coupon_id != '')
            {
                $coupons = Coupon::find($request->coupon_id);
                if(!empty($coupons))
                {
                    $usedCoupun             = $coupons->used_coupon();
                    $discount_value         = ($price / 100) * $coupons->discount;
                    $plan->discounted_price = $price - $discount_value;
                    $coupons_id             = $coupons->id;
                    if($usedCoupun >= $coupons->limit)
                    {
                        return redirect()->back()->with('error', __('This coupon code has expired.'));
                    }
                    $price = $price - $discount_value;
                }
            }
            Utility::referralTransaction($plan);

            $order                 = new Order();
            $order->order_id       = $orderID;
            $order->name           = $user->name;
            $order->card_number    = '';
            $order->card_exp_month = '';
            $order->card_exp_year  = '';
            $order->plan_name      = $plan->name;
            $order->plan_id        = $plan->id;
            $order->price          = $price;
            $order->price_currency = !empty($admin_payment_setting['currency']) ? $admin_payment_setting['currency'] :'USD';
            $order->txn_id         = isset($request->transaction_id) ? $request->transaction_id : '';
            $order->payment_type   = __('Coingate');
            $order->payment_status = 'success';
            $order->receipt        = '';
            $order->user_id        = $user->id;
            $order->save();
            $assignPlan = $user->assignPlan($plan->id);
            if($assignPlan['is_success'])
            {
                return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
            }
            else
            {
                return redirect()->route('plans.index')->with('error', $assignPlan['error']);
            }
            // }
            // catch(\Exception $e)
            // {
            //     return redirect()->route('plans.index')->with('error', __('Transaction has been failed.'));
            // }
        }
        else
        {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
        }
    }


//    public function coingatePlanGetPayment(Request $request)
//    {
//        $user                  = Auth::user();
//        $plan_id               = $request->plan_id;
//        $store_id              = \Auth::user()->current_store;
//        $admin_payment_setting = Utility::getAdminPaymentSetting();
//        $plan                  = Plan::find($plan_id);
//        $price                 = $plan->price;
//        if($plan)
//        {
//            try
//            {
//                $orderID = time();
//                if($request->has('coupon_id') && $request->coupon_id != '')
//                {
//                    $coupons = Coupon::find($request->coupon_id);
//                    if(!empty($coupons))
//                    {
//                        $usedCoupun             = $coupons->used_coupon();
//                        $discount_value         = ($price / 100) * $coupons->discount;
//                        $plan->discounted_price = $price - $discount_value;
//                        $coupons_id             = $coupons->id;
//                        if($usedCoupun >= $coupons->limit)
//                        {
//                            return redirect()->back()->with('error', __('This coupon code has expired.'));
//                        }
//                        $price = $price - $discount_value;
//                    }
//                }
//                $order                 = new Order();
//                $order->order_id       = $orderID;
//                $order->name           = $user->name;
//                $order->card_number    = '';
//                $order->card_exp_month = '';
//                $order->card_exp_year  = '';
//                $order->plan_name      = $plan->name;
//                $order->plan_id        = $plan->id;
//                $order->price          = $price;
//                $order->price_currency = env('CURRENCY_CODE');
//                $order->txn_id         = isset($request->transaction_id) ? $request->transaction_id : '';
//                $order->payment_type   = __('Coingate');
//                $order->payment_status = 'success';
//                $order->receipt        = '';
//                $order->user_id        = $user->id;
//                $order->save();
//
//                $assignPlan = $user->assignPlan($plan->id);
//                if($assignPlan['is_success'])
//                {
//                    return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
//                }
//                else
//                {
//                    return redirect()->route('plans.index')->with('error', $assignPlan['error']);
//                }
//            }
//            catch(\Exception $e)
//            {
//                return redirect()->route('plans.index')->with('error', __('Transaction has been failed.'));
//            }
//        }
//        else
//        {
//            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
//        }
//    }

    public function customerPayWithCoingate(Request $request)
    {

        $invoiceID = \Illuminate\Support\Facades\Crypt::decrypt($request->invoice_id);
        $invoice   = Invoice::find($invoiceID);
        $this->invoiceData = $invoice;
        $user      = User::find($invoice->created_by);

        $payment   = $this->companyPaymentConfig();

        $settings  = DB::table('settings')->where('created_by', '=',$invoice->created_by)->get()->pluck('value', 'name');


        if($invoice)
        {
            $price = $request->amount;
            if($price > 0)
            {
                CoinGate::config(
                    array(
                        'environment' => $this->mode,
                        'auth_token' => $this->coingate_auth_token,
                        'curlopt_ssl_verifypeer' => FALSE,
                    )
                );
                $post_params = array(
                    'order_id' => time(),
                    'price_amount' => $price,
                    'price_currency' => Utility::getValByName('site_currency'),
                    'receive_currency' => Utility::getValByName('site_currency'),
                    'callback_url' => route(
                        'customer.coingate', [
                                               Crypt::encrypt($invoice->id),
                                           $price,
                                       ]
                    ),
                    'cancel_url' => route('invoice.link.copy', [Crypt::encrypt($invoice->id)]),
                    'success_url' => route(
                        'customer.coingate', [
                                               Crypt::encrypt($invoice->id),
                                           $price,
                                       ]
                    ),
                    'title' => __('Invoice') . ' ' . Utility::invoiceNumberFormat($settings, $invoice->invoice_id),
                );

                $order = CoinGate::coingatePayment($post_params, 'POST');
                if($order['status_code'] === 200) {
                    $response = $order['response'];
                    return redirect($response['payment_url']);
                }
                else
                {
                    return redirect()->back()->with('error', __('opps something wren wrong.'));
                }

            }
            else
            {
                $res['msg']  = __("Enter valid amount.");
                $res['flag'] = 2;

                return $res;
            }

        }
        else
        {
            return redirect()->route('invoice.index')->with('error', __('Invoice is deleted.'));

        }


    }

    public function getInvoicePaymentStatus(Request $request, $invoice_id, $amount)
    {
        $invoiceID = Crypt::decrypt($invoice_id);
        $invoice   = Invoice::find($invoiceID);
        $this->invoiceData = $invoice;

        $orderID   = strtoupper(str_replace('.', '', uniqid('', true)));
//        $settings  = DB::table('settings')->where('created_by', '=', $invoice->created_by)->get()->pluck('value', 'name');
        $settings  = Utility::settingsById($invoice->created_by);
        $payment   = $this->companyPaymentConfig();

        $result    = array();
        if($invoice)
        {
            $payments = InvoicePayment::create(
                [
                    'invoice_id' => $invoice->id,
                    'date' => date('Y-m-d'),
                    'amount' => $request->amount,
                    'payment_method' => 1,
                    'order_id' => $orderID,
                    'payment_type' => __('Coingate'),
                    'receipt' => '',
                    'description' => __('Invoice') . ' ' . Utility::invoiceNumberFormat($settings, $invoice->invoice_id),

                ]
            );

            $invoice = Invoice::find($invoice->id);


            if($invoice->getDue() <= 0)
            {
                Invoice::change_status($invoice->id, 4);
            }
            else
            {
                Invoice::change_status($invoice->id, 3);
            }

            //for customer balance update
            Utility::updateUserBalance('customer', $invoice->customer_id, $request->amount, 'debit');

            //For Notification
            $setting  = Utility::settingsById($invoice->created_by);
            $customer = Customer::find($invoice->customer_id);
            $notificationArr = [
                'payment_price' => $request->amount,
                'invoice_payment_type' => 'Coingate',
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
                $parameter = json_encode($invoice);
                $status = Utility::WebhookCall($webhook['url'],$parameter,$webhook['method']);
                if($status == true)
                {
                    return redirect()->route('invoice.link.copy', Crypt::encrypt($invoice->id))->with('success', __(' Payment successfully added.'));
                }
                else
                {
                    return redirect()->back()->with('error', __('Webhook call failed.'));
                }
            }


            return redirect()->route('invoice.link.copy', Crypt::encrypt($invoice->id))->with('success', __(' Payment successfully added.'));


        }
        else
        {
            return redirect()->back()->with('error', __('Invoice is deleted.'));
        }
    }


}
