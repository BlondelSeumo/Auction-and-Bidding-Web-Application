<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Utility;
use App\Models\Plan;
use App\Models\Coupon;
use App\Models\UserCoupon;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\User;
use App\Models\InvoicePayment;
use App\Models\Customer;
use Auth;

class NepalstePaymnetController extends Controller
{
    public function planPayWithnepalste(Request $request)
    {

        $payment_setting = Utility::getAdminPaymentSetting();
        $api_key = isset($payment_setting['nepalste_public_key']) ? $payment_setting['nepalste_public_key'] : '';
        $currency = isset($payment_setting['currency']) ? $payment_setting['currency'] : '';
        $planID = \Illuminate\Support\Facades\Crypt::decrypt($request->plan_id);

        $plan = Plan::find($planID);
        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
        $user = Auth::user();

        if ($plan) {
            $get_amount = $plan->price;

            if (!empty($request->coupon))
            {
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

                            $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                            $userCoupon = new UserCoupon();

                            $userCoupon->user = $authuser->id;
                            $userCoupon->coupon = $coupons->id;
                            $userCoupon->order = $orderID;
                            $userCoupon->save();
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
                                    'price' => $get_amount == null ? 0 : $get_amount,
                                    'price_currency' => $currency,
                                    'txn_id' => '',
                                    'payment_type' => 'Nepalste',
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
        }
        if (!empty($request->coupon))
        {
            $response = ['get_amount' => $get_amount, 'plan' => $plan , 'coupon_id'=>$coupons->id];
        }
        else{
            $response = ['get_amount' => $get_amount, 'plan' => $plan];
        }

        $parameters = [
            'identifier' => 'DFU80XZIKS',
            'currency' => $currency,
            'amount' => $get_amount,
            'details' => $plan->name,
            'ipn_url' => route('nepalste.status',$response),
            'cancel_url' => route('nepalste.cancel'),
            'success_url' => route('nepalste.status',$response),
            'public_key' => $api_key,
            'site_logo' => 'https://nepalste.com.np/assets/images/logoIcon/logo.png',
            'checkout_theme' => 'dark',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@mail.com',
        ];

        //live end point
        if($payment_setting['nepalste_mode'] == 'live'){
            $url = "https://nepalste.com.np/payment/initiate";
        }else{
            $url = "https://nepalste.com.np/sandbox/payment/initiate";
        }


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS,  $parameters);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result, true);

        if(isset($result['success'])){
            return redirect($result['url']);
        }else{
            return redirect()->back()->with('error',__($result['message']));
        }
    }

    public function planGetNepalsteStatus(Request $request)
    {
        $payment_setting = Utility::getAdminPaymentSetting();
        $currency = isset($payment_setting['currency']) ? $payment_setting['currency'] : '';

        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

        $getAmount = $request->get_amount;
        $authuser = \Auth::user();
        $plan = Plan::find($request->plan);
            Utility::referralTransaction($plan);

            $order = new Order();
            $order->order_id = $orderID;
            $order->name = $authuser->name;
            $order->card_number = '';
            $order->card_exp_month = '';
            $order->card_exp_year = '';
            $order->plan_name = $plan->name;
            $order->plan_id = $plan->id;
            $order->price = $getAmount;
            $order->price_currency = $currency;
            $order->txn_id = $orderID;
            $order->payment_type = __('Neplaste');
            $order->payment_status = 'success';
            $order->txn_id = '';
            $order->receipt = '';
            $order->user_id = $authuser->id;
            $order->save();
            $assignPlan = $authuser->assignPlan($plan->id);

            $coupons = Coupon::find($request->coupon_id);
            if (!empty($request->coupon_id)) {
                if (!empty($coupons)) {
                    $userCoupon = new UserCoupon();
                    $userCoupon->user = $authuser->id;
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

            if ($assignPlan['is_success'])
            {
                return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
            } else
            {
                return redirect()->route('plans.index')->with('error', __($assignPlan['error']));
            }
    }

    public function planGetNepalsteCancel(Request $request)
    {
        return redirect()->back()->with('error',__('Transaction has failed'));
    }


    public function invoicePayWithnepalste(Request $request)
    {

        $invoice_id = \Illuminate\Support\Facades\Crypt::decrypt($request->invoice_id);
        $invoice = Invoice::find($invoice_id);
        $user = User::where('id', $invoice->created_by)->first();
        $get_amount = $request->amount;
        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

            if ($invoice) {
                $payment_setting = Utility::getCompanyPaymentSetting($user->id);
                $api_key = $payment_setting['nepalste_public_key'];
                $settings = Utility::settingsById($user->id);
                $currency = isset($settings['site_currency']) ? $settings['site_currency'] : '';

                    $response = ['user' => $user, 'get_amount' => $get_amount, 'invoice' => $invoice];

                $parameters = [
                    'identifier' => 'DFU80XZIKS',
                    'currency' => $currency,
                    'amount' => $get_amount,
                    'details' => 'Invoice',
                    'ipn_url' => route('invoice.nepalste.status',$response),
                    'cancel_url' => route('invoice.nepalste.cancel'),
                    'success_url' => route('invoice.nepalste.status',$response),
                    'public_key' => $api_key,
                    'site_logo' => 'https://nepalste.com.np/assets/images/logoIcon/logo.png',
                    'checkout_theme' => 'dark',
                    'customer_name' => 'John Doe',
                    'customer_email' => 'john@mail.com',
                ];

                //live end point
                if($payment_setting['nepalste_mode'] == 'live'){
                    $url = "https://nepalste.com.np/payment/initiate";
                }else{
                    $url = "https://nepalste.com.np/sandbox/payment/initiate";
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POSTFIELDS,  $parameters);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                curl_close($ch);

                $result = json_decode($result, true);

                if(isset($result['success'])){
                    return redirect($result['url']);
                }else{
                    return redirect()->back()->with('error',__($result['message']));
                }
    }
}

    public function invoiceGetNepalsteStatus(Request $request)
    {

        $invoice = Invoice::find($request->invoice);
        $user = User::where('id', $invoice->created_by)->first();
        $settings= Utility::settingsById($invoice->created_by);
        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

        $payment_setting = Utility::getCompanyPaymentSetting($user->id);
        $get_amount = $request->get_amount;

            $invoice_payment                 = new InvoicePayment();
            $invoice_payment->invoice_id     = $invoice->id;
            $invoice_payment->date           = Date('Y-m-d');
            $invoice_payment->amount         = $get_amount;
            $invoice_payment->account_id     = 0;
            $invoice_payment->payment_method = 0;
            $invoice_payment->order_id       = $orderID;
            $invoice_payment->payment_type   = 'Nepalste';
            $invoice_payment->receipt        = '';
            $invoice_payment->reference      = '';
            $invoice_payment->description    = 'Invoice ' . Utility::invoiceNumberFormat($settings, $invoice->invoice_id);
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
                    'invoice_payment_type' => 'Aamarpay',
                    'customer_name' => $customer->name,
                ];
            //Slack Notification
            if(isset($settings['payment_notification']) && $settings['payment_notification'] ==1)
            {
                Utility::send_slack_msg('new_invoice_payment', $notificationArr,$invoice->created_by);
            }
            //Telegram Notification
            if(isset($settings['telegram_payment_notification']) && $settings['telegram_payment_notification'] == 1)
            {
                Utility::send_telegram_msg('new_invoice_payment', $notificationArr,$invoice->created_by);
            }
            //Twilio Notification
            if(isset($settings['twilio_payment_notification']) && $settings['twilio_payment_notification'] ==1)
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

            return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('success', __('Invoice paid Successfully!'));

    }

    public function invoiceGetNepalsteCancel(Request $request)
    {
        return redirect()->back()->with('error',__('Transaction has failed'));
    }
}
