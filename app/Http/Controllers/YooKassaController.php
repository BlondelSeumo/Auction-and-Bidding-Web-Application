<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use YooKassa\Client;
use App\Models\Customer;

class YooKassaController extends Controller
{
    public function planPayWithYooKassa(Request $request)
    {
        $payment_setting = Utility::getAdminPaymentSetting();

        $yookassa_shop_id = $payment_setting['yookassa_shop_id'];

        $yookassa_secret_key = $payment_setting['yookassa_secret'];
        $currency = isset($payment_setting['currency']) ? $payment_setting['currency'] : 'RUB';

        $planID = \Illuminate\Support\Facades\Crypt::decrypt($request->plan_id);
        $authuser = Auth::user();
        $plan = Plan::find($planID);
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
                if (is_int((int) $yookassa_shop_id)) {
                    $client = new Client();
                    $client->setAuth((int) $yookassa_shop_id, $yookassa_secret_key);
                    $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                    $product = !empty($plan->name) ? $plan->name : 'Life time';
                    $payment = $client->createPayment(
                        array(
                            'amount' => array(
                                'value' => $get_amount,
                                'currency' => $currency,
                            ),
                            'confirmation' => array(
                                'type' => 'redirect',
                                'return_url' => route('plan.yookassa.status', [$plan->id, 'order_id' => $orderID, 'price' => $get_amount, 'coupon' => $request->coupon]),
                            ),
                            'capture' => true,
                            'description' => 'Заказ №1',
                        ),
                        uniqid('', true)
                    );
                    $authuser = Auth::user();
                    $authuser->plan = $plan->id;
                    $authuser->save();

                    if (!empty($authuser->payment_subscription_id) && $authuser->payment_subscription_id != '') {
                        try {
                            $authuser->cancel_subscription($authuser->id);
                        } catch (\Exception $exception) {
                            Log::debug($exception->getMessage());
                        }
                    }

                    Session::put('payment_id', $payment['id']);
                    if ($payment['confirmation']['confirmation_url'] != null) {
                        return redirect($payment['confirmation']['confirmation_url']);
                    } else {
                        return redirect()->route('plan.index')->with('error', 'Something went wrong, Please try again');
                    }

                    // return redirect()->route('plans.index')->with('success', __('Plan Successfully Activated'));

                } else {
                    return redirect()->back()->with('error', 'Please Enter  Valid Shop Id Key');
                }
            } catch (\Throwable $th) {
                return redirect()->back()->with('error', $th);
            }
        }
    }
    public function planGetYooKassaStatus(Request $request, $planId)
    {

        $payment_setting = Utility::getAdminPaymentSetting();
        $yookassa_shop_id = $payment_setting['yookassa_shop_id'];
        $yookassa_secret_key = $payment_setting['yookassa_secret'];
        $currency = isset($payment_setting['currency']) ? $payment_setting['currency'] : 'USD';

        if (is_int((int) $yookassa_shop_id)) {
            $client = new Client();
            $client->setAuth((int) $yookassa_shop_id, $yookassa_secret_key);
            $paymentId = Session::get('payment_id');

            Session::forget('payment_id');

            if ($paymentId == null) {
                return redirect()->back()->with('error', __('Transaction Unsuccesfull'));
            }

            $payment = $client->getPaymentInfo($paymentId);

            if (isset($payment) && $payment->status == "succeeded") {

                $plan = Plan::find($planId);
                try {
                    $user = \Auth::user();
                    $planID = $request->plan_id;
                    $couponCode = $request->coupon;
                    $getAmount = $request->price;

                    if ($couponCode != 0) {
                        $coupons = Coupon::where('code', strtoupper($couponCode))->where('is_active', '1')->first();
                        $request['coupon_id'] = $coupons->id;
                    } else {
                        $coupons = null;
                    }
                    $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

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
                    $order->price_currency = $currency;
                    $order->txn_id = $request->orderID;
                    $order->payment_type = __('Yookassa');
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

                    if ($assignPlan['is_success']) {
                        return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
                    } else {
                        return redirect()->route('plans.index')->with('error', __($assignPlan['error']));
                    }
                } catch (Exception $e) {
                    return redirect()->route('plans.index')->with('error', __($e));
                }
            } else {
                return redirect()->back()->with('error', 'Please Enter  Valid Shop Id Key');
            }
        }
    }

    public function invoicePayWithYookassa(Request $request)
    {
        $invoice_id = \Illuminate\Support\Facades\Crypt::decrypt($request->invoice_id);
        $invoice = Invoice::find($invoice_id);
        $this->invoiceData = $invoice;
        $user = User::find($invoice->created_by);
        $company_payment_setting = Utility::getCompanyPaymentSetting($user->id);
        $settings = Utility::settingsById($invoice->created_by);
        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
        $get_amount = $request->amount;

        $yookassa_shop_id = $company_payment_setting['yookassa_shop_id'];
        $yookassa_secret_key = $company_payment_setting['yookassa_secret'];
        $currency = isset($company_payment_setting['site_currency']) ? $company_payment_setting['site_currency'] : 'RUB';

        try {
            if ($invoice) {
                if (is_int((int) $yookassa_shop_id)) {
                    $client = new Client();
                    $client->setAuth((int) $yookassa_shop_id, $yookassa_secret_key);
                    $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                    $payment = $client->createPayment(
                        array(
                            'amount' => array(
                                'value' => $get_amount,
                                'currency' => $currency,
                            ),
                            'confirmation' => array(
                                'type' => 'redirect',
                                'return_url' => route('invoice.yookassa.status', [
                                    'invoice_id' => $invoice->id,
                                    'amount' => $get_amount,
                                ]),
                            ),
                            'capture' => true,
                            'description' => 'Заказ №1',
                        ),
                        uniqid('', true)
                    );

                    Session::put('yookassa_payment_id', $payment['id']);

                    if ($payment['confirmation']['confirmation_url'] != null) {
                        return redirect($payment['confirmation']['confirmation_url']);
                    } else {
                        return redirect()->route('invoice.index')->with('error', 'Something went wrong, Please try again');
                    }
                } else {
                    return redirect()->back()->with('error', 'Please Enter  Valid Shop Id Key');
                }
            } else {
                return redirect()->back()->with('error', 'Invoice not found.');
            }
        } catch (\Throwable $e) {

            return redirect()->back()->with('error', __($e));
        }
    }
    public function getInvociePaymentStatus(Request $request)
    {

        $invoice = Invoice::find($request->invoice_id);
        $user = User::find($invoice->created_by);

        $settings= Utility::settingsById($invoice->created_by);
        $company_payment_setting = Utility::getCompanyPaymentSetting($user->id);

        $yookassa_shop_id = $company_payment_setting['yookassa_shop_id'];
        $yookassa_secret_key = $company_payment_setting['yookassa_secret'];
        if ($invoice)
        {
            if (empty($request->PayerID || empty($request->token)))
            {
                return redirect()->route('invoice.show', $request->invoice_id)->with('error', __('Payment failed'));
            }
            $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
            try
            {
                if (is_int((int)$yookassa_shop_id)) {
                    $client = new Client();
                    $client->setAuth((int)$yookassa_shop_id, $yookassa_secret_key);
                    $paymentId = Session::get('yookassa_payment_id');

                    if ($paymentId == null) {
                        return redirect()->back()->with('error', __('Transaction Unsuccesfull'));
                    }
                    $payment = $client->getPaymentInfo($paymentId);
                    Session::forget('yookassa_payment_id');
                    if (isset($payment) && $payment->status == "succeeded") {

                    $invoice_payment                 = new InvoicePayment();
                    $invoice_payment->invoice_id     = $request->invoice_id;
                    $invoice_payment->date           = Date('Y-m-d');
                    $invoice_payment->amount         = $request->amount;
                    $invoice_payment->account_id         = 0;
                    $invoice_payment->payment_method         = 0;
                    $invoice_payment->order_id      =$orderID;
                    $invoice_payment->payment_type   = 'Yookasa';
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

                else
                {
                    return redirect()->route('invoice.link.copy', \Crypt::encrypt($request->invoice_id))->with('error', __('Transaction fail'));
                }
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
