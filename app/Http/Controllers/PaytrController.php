<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Plan;
use App\Models\Order;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Twilio\TwiML\Voice\Stop;

class PaytrController extends Controller
{
    public function PlanpayWithPaytr(Request $request)
    {

        $payment_setting = Utility::getAdminPaymentSetting();
        $paytr_merchant_id = $payment_setting['paytr_merchant_id'];
        $paytr_merchant_key = $payment_setting['paytr_merchant_key'];
        $paytr_merchant_salt = $payment_setting['paytr_merchant_salt'];
        $currency =  !empty($payment_setting['currency']) ? $payment_setting['currency'] : 'TL';
        $planID = \Illuminate\Support\Facades\Crypt::decrypt($request->plan_id);
        $authuser = \Auth::user();
        $plan = Plan::find($planID);
        if ($plan)
        {
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
                                    'payment_type' => 'PayTR',
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
            try {
                $coupon = (empty($request->coupon)) ? "0" : $request->coupon;

                $merchant_id    = $paytr_merchant_id;
                $merchant_key   = $paytr_merchant_key;
                $merchant_salt  = $paytr_merchant_salt;

                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                $email = $authuser->email;
                $payment_amount = (float)$plan->price;
                $merchant_oid = $orderID;
                $user_name = $authuser->name;
                $user_address =  'no address';
                $user_phone =  '0000000000';
                $user_basket = base64_encode(json_encode(array(
                    array("Plan", $payment_amount, 1),
                )));

                if (isset($_SERVER["HTTP_CLIENT_IP"])) {
                    $ip = $_SERVER["HTTP_CLIENT_IP"];
                } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                    $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
                } else {
                    $ip = $_SERVER["REMOTE_ADDR"];
                }

                $user_ip = $ip;
                $timeout_limit = "30";
                $debug_on = 1;
                $test_mode = 0;
                $no_installment = 0;
                $max_installment = 0;
                $currency = $currency;
                $lang = ($authuser->lang == 'tr') ? 'tr' : 'en';
                $hash_str = $merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $user_basket . $no_installment . $max_installment . $currency . $test_mode;
                $paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));

                $request['orderID'] = $orderID;
                $request['plan_id'] = $plan->id;
                $request['price'] = $get_amount;
                $request['payment_status'] = 'failed';
                $payment_failed = $request->all();
                $request['payment_status'] = 'success';
                $payment_success = $request->all();

                $post_vals = array(
                    'merchant_id' => $merchant_id,
                    'user_ip' => $user_ip,
                    'merchant_oid' => $merchant_oid,
                    'email' => $email,
                    'payment_amount' => $payment_amount,
                    'paytr_token' => $paytr_token,
                    'user_basket' => $user_basket,
                    'debug_on' => $debug_on,
                    'lang' => $lang,
                    'no_installment' => $no_installment,
                    'max_installment' => $max_installment,
                    'user_name' => $user_name,
                    'user_address' => $user_address,
                    'user_phone' => $user_phone,
                    'merchant_ok_url' => route('pay.paytr.success', $payment_success),
                    'merchant_fail_url' => route('pay.paytr.success', $payment_failed),
                    'timeout_limit' => $timeout_limit,
                    'currency' => $currency,
                    'test_mode' => $test_mode
                );
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/api/get-token");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
                curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                
                
                $result = @curl_exec($ch);
                if (curl_errno($ch)) {
                    die("PAYTR IFRAME connection error. err:" . curl_error($ch));
                }

                curl_close($ch);
                
                $result = json_decode($result, 1);

                if ($result['status'] == 'success') {
                    $token = $result['token'];
                } else {
                    return redirect()->back()->with('error', 'Currency Not Supported.Contact To Your Site Admin');
                }

                return view('plan.paytr_payment', compact('token'));
            } catch (\Throwable $th) {

                return redirect()->route('plans.index')->with('error', $th->getMessage());
            }
        }
    }

    public function paytrsuccess(Request $request)
    {
        if ($request->payment_status == "success")
        {
            $payment_setting = Utility::getAdminPaymentSetting();
            $currency =  !empty($payment_setting['currency']) ? $payment_setting['currency'] : 'TL';

            try {
                $user = \Auth::user();
                $planID = $request->plan_id;
                $plan = Plan::find($planID);
                $couponCode = $request->coupon;
                $getAmount = $request->price;

                if ($couponCode != 0) {
                    $coupons = Coupon::where('code', strtoupper($couponCode))->where('is_active', '1')->first();
                    $request['coupon_id'] = $coupons->id;
                } else {
                    $coupons = null;
                }
                Utility::referralTransaction($plan);

                $order = new Order();
                $order->order_id = $request->orderID;
                $order->name = $user->name;
                $order->card_number = '';
                $order->card_exp_month = '';
                $order->card_exp_year = '';
                $order->plan_name = $plan->name;
                $order->plan_id = $plan->id;
                $order->price = $getAmount;
                $order->price_currency = $currency;
                $order->txn_id = $request->orderID;
                $order->payment_type = __('PayTR');
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
                        $userCoupon->order = $request->orderID;
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
            } catch (Exception $e)
            {
                return redirect()->route('plans.index')->with('error', __($e));
            }
        }
        else
        {
            return redirect()->route('plans.index')->with('success', __('Your Transaction is fail please try again.'));
        }
    }


    public function invoicepaywithpaytr(Request $request)
    {
        $invoice_id = \Illuminate\Support\Facades\Crypt::decrypt($request->invoice_id);
        $invoice = Invoice::find($invoice_id);
        $this->invoiceData = $invoice;
        $user      = User::find($invoice->created_by);
        $company_payment_setting = Utility::getCompanyPaymentSetting($user->id);
        $settings= Utility::settingsById($invoice->created_by);
        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
        $get_amount = $request->amount;

        if ($invoice)
        {
            if ($get_amount > $invoice->getDue()) {
                return redirect()->back()->with('error', __('Invalid amount.'));
            }

            try {

                $merchant_id    = $company_payment_setting['paytr_merchant_id'];
                $merchant_key   = $company_payment_setting['paytr_merchant_key'];
                $merchant_salt  = $company_payment_setting['paytr_merchant_salt'];

                $email = $user->email;

                $user_name = $user->name;

                $user_address = 'no address';
                $user_phone =  '0000000000';

                $user_basket = base64_encode(json_encode(array(
                    array("Invoice", $get_amount, 1),
                )));

                if (isset($_SERVER["HTTP_CLIENT_IP"])) {
                    $ip = $_SERVER["HTTP_CLIENT_IP"];
                } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                    $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
                } else {
                    $ip = $_SERVER["REMOTE_ADDR"];
                }

                $timeout_limit = "30";
                $debug_on = 1;
                $test_mode = 0;
                $no_installment = 0;
                $max_installment = 0;
                $currency = $settings['site_currency'];
                $lang = ($user->lang == 'tr') ? 'tr' : 'en';

                # For 14.45 TL, 14.45 * 100 = 1445 (must be multiplied by 100 and sent as an integer.)
                $paytr_price = $get_amount * 100;
                $hash_str = $merchant_id . $ip . $orderID . $email . $paytr_price . $user_basket . $no_installment . $max_installment . $currency . $test_mode;
                $paytr_token = base64_encode(hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true));

                $request['orderID'] = $orderID;
                $request['invoice_id'] = $invoice->id;
                $request['price'] = $get_amount;
                $request['payment_status'] = 'failed';
                $payment_failed = $request->all();
                $request['payment_status'] = 'success';
                $payment_success = $request->all();

                $post_vals = array(
                    'merchant_id' => $merchant_id,
                    'user_ip' => $ip,
                    'merchant_oid' => $orderID,
                    'email' => $email,
                    'payment_amount' => $paytr_price,
                    'paytr_token' => $paytr_token,
                    'user_basket' => $user_basket,
                    'debug_on' => $debug_on,
                    'lang' => $lang,
                    'no_installment' => $no_installment,
                    'max_installment' => $max_installment,
                    'user_name' => $user_name,
                    'user_address' => $user_address,
                    'user_phone' => $user_phone,
                    'merchant_ok_url' => route('invoice.paytr', $payment_success),
                    'merchant_fail_url' => route('invoice.paytr', $payment_failed),
                    'timeout_limit' => $timeout_limit,
                    'currency' => $currency,
                    'test_mode' => $test_mode
                );


                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/api/get-token");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vals);
                curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 20);

                $result = @curl_exec($ch);

                if (curl_errno($ch))
                {
                    return redirect()->route('invoice.link.copy', \Illuminate\Support\Facades\Crypt::encrypt($invoice->id))->with('error', curl_error($ch));
                }
                curl_close($ch);

                $result = json_decode($result, 1);

                if ($result['status'] == 'success') {
                    $token = $result['token'];
                } else {
                    return redirect()->back()->with('error', 'Currency Not Supported.Contact To Your Site Admin');

                }

                return view('invoice.paytr_payment', compact('token'));
            } catch (\Throwable $th) {

                return redirect()->back()->with('error', __($th->getMessage()));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function getInvoicePaymentStatus(Request $request)
    {

        $invoice = Invoice::find($request->invoice_id);
        $settings= Utility::settingsById($invoice->created_by);
        if ($invoice)
        {
            if (empty($request->PayerID || empty($request->token)))
            {
                return redirect()->route('invoice.show', $request->invoice_id)->with('error', __('Payment failed'));
            }
            $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
            try
            {
                if ($request->payment_status == "success")
                {
                    $invoice_payment                 = new InvoicePayment();
                    $invoice_payment->invoice_id     = $request->invoice_id;
                    $invoice_payment->date           = Date('Y-m-d');
                    $invoice_payment->amount         = $request->amount;
                    $invoice_payment->account_id         = 0;
                    $invoice_payment->payment_method         = 0;
                    $invoice_payment->order_id      =$orderID;
                    $invoice_payment->payment_type   = 'PayTR';
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
                    return redirect()->route('invoice.link.copy', \Crypt::encrypt($request->invoice_id))->with('success', __('Invoice paid Successfully!'));
                }

                elseif ($request->payment_status == "cancel")
                {
                    return redirect()->back()->with('error', __('Your payment is cancel'));
                }
                else
                {
                    return redirect()->route('invoice.link.copy', \Crypt::encrypt($request->invoice_id))->with('error', __('Transaction fail'));
                }

            }
            catch (\Exception $e)
            {
                return redirect()->route('invoice.link.copy', \Illuminate\Support\Facades\Crypt::encrypt($request->invoice_id))->with('success',$e->getMessage());
            }
        } else {
            return redirect()->route('invoice.link.copy', \Illuminate\Support\Facades\Crypt::encrypt($request->invoice_id))->with('success', __('Invoice not found.'));
        }

    }

}

