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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FedapayController extends Controller
{
    public function planPayWithFedapay(Request $request)
    {
        $payment_setting = Utility::getAdminPaymentSetting();
        $fedapay_secret = isset($payment_setting['fedapay_secret']) ? $payment_setting['fedapay_secret'] : '';
        $mode = isset($payment_setting['fedapay_mode']) ? $payment_setting['fedapay_mode'] : '';
        $currency_code = !empty($payment_setting['currency_code']) ? $payment_setting['currency_code'] : 'XOF';


        $user = Auth::user();
        $planID = \Illuminate\Support\Facades\Crypt::decrypt($request->plan_id);
        $plan = Plan::find($planID);
        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
        if ($plan) {
            $price = intval($plan->price);
            $get_amount = $price;

            if (!empty($request->coupon)) {
                $coupons = Coupon::where('code', strtoupper($request->coupon))->where('is_active', '1')->first();
                if (!empty($coupons)) {
                    $usedCoupun = $coupons->used_coupon();
                    $discount_value = ($plan->price / 100) * $coupons->discount;
                    $price = intval($plan->price);
                    $get_amount = $price - $discount_value;

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
                                    'price_currency' => $currency_code,
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
            try {

                $fedapay = !empty($payment_setting['fedapay_secret']) ? $payment_setting['fedapay_secret'] : '';
                $fedapay_mode = !empty($payment_setting['fedapay_mode']) ? $payment_setting['fedapay_mode'] : 'sandbox';
                \FedaPay\FedaPay::setApiKey($fedapay);
                \FedaPay\FedaPay::setEnvironment($fedapay_mode);
                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                $transaction = \FedaPay\Transaction::create([
                    "description" => "Fedapay Payment",
                    "amount" => $get_amount,
                    "currency" => ["iso" => $currency_code],

                    "callback_url" => route('plan.get.fedapay.status', [
                        'order_id' => $orderID,
                        'plan_id' => $plan->id,
                        'coupon_code' => $request->coupon_code,
                    ]),
                    "cancel_url" => route('plan.get.fedapay.status', [
                        'order_id' => $orderID,
                        'plan_id' => $plan->id,
                        'coupon_code' => $request->coupon_code,
                    ]),

                ]);


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
                        'price_currency' => $currency_code,
                        'txn_id' => '',
                        'payment_type' => __('Fedapay'),
                        'payment_status' => 'pending',
                        'receipt' => null,
                        'user_id' => $user->id,
                    ]
                );


                $token = $transaction->generateToken();

                return redirect($token->url);
            } catch (\Exception $e) {
                return redirect()->route('plans.index')->with('error', $e->getMessage());
            }
        }
    }

    public function planGetFedapayStatus(Request $request)
    {
        if ($request->status == 'approved') {
            $payment_setting = Utility::getAdminPaymentSetting();
            $currency = isset($payment_setting['currency']) ? $payment_setting['currency'] : '';

            $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

            $getAmount = $request->get_amount;
            $authuser = \Auth::user();
            $plan = Plan::find($request->plan_id);
            Utility::referralTransaction($plan);

            $order = new Order();
            $order->order_id = $orderID;
            $order->name = $authuser->name;
            $order->card_number = '';
            $order->card_exp_month = '';
            $order->card_exp_year = '';
            $order->plan_name = $plan->name;
            $order->plan_id = $plan->id;
            $order->price = $plan->price;
            $order->price_currency = $currency;
            $order->txn_id = $orderID;
            $order->payment_type = __('FedaPay');
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

            if ($assignPlan['is_success']) {
                return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
            } else {
                return redirect()->route('plans.index')->with('error', __($assignPlan['error']));
            }
        } else {
            return redirect()->back()->with('error', ('Permission denied'));
        }
    }

    public function invoicePayWithFedapay(Request $request)
    {
        $invoice_id = \Illuminate\Support\Facades\Crypt::decrypt($request->invoice_id);
        $invoice = Invoice::find($invoice_id);

        if (Auth::check()) {
            $user = Auth::user();
        } else {
            $user = User::where('id', $invoice->created_by)->first();
        }
        if ($user->type != 'company') {
            $user = User::where('id', $user->created_by)->first();
        }

        $payment_setting = Utility::getCompanyPaymentSetting($user->id);
        $fedapay_secret = $payment_setting['fedapay_secret'];
        $fedapay_mode = $payment_setting['fedapay_mode'];
        $currency_code = !empty($payment_setting['currency_code']) ? $payment_setting['currency_code'] : 'XOF';
        $price = $request->amount;

        $get_amount = round($price);
        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

        if ($invoice) {
            if ($get_amount > $invoice->getDue()) {
                return redirect()->back()->with('error', __('Invalid amount.'));
            } else {
                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                try {

                    \FedaPay\FedaPay::setApiKey($fedapay_secret);
                    \FedaPay\FedaPay::setEnvironment($fedapay_mode);

                    $transaction = \FedaPay\Transaction::create([
                        "description" => "Fedapay Payment",
                        "amount" => $get_amount,
                        "currency" => ["iso" => $currency_code],
                        "callback_url" => route(
                            'invoice.fedapay.status',
                            [
                                'invoice_id' => $invoice_id,
                                'amount' => $get_amount,
                            ]
                        ),
                        "cancel_url" =>  route(
                            'invoice.fedapay.status',
                            [
                                'invoice_id' => $invoice_id,
                                'amount' => $get_amount,
                            ]
                        )
                    ]);
                    $token = $transaction->generateToken();

                    return redirect($token->url);
                } catch (\Exception $e) {
                    return redirect()->route('invoice.show', $invoice_id)->with('error', $e->getMessage() ?? 'Something went wrong.');
                }
                return redirect()->back()->with('error', __('Unknown error occurred'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    public function getInvociePaymentStatus(Request $request)
    {
        $orderID   = strtoupper(str_replace('.', '', uniqid('', true)));
        $invoice = Invoice::find($request->invoice_id);

        if (Auth::check()) {
            $user = Auth::user();
        } else {
            $user = User::where('id', $invoice->created_by)->first();
        }
        if ($user->type != 'owner') {
            $user = User::where('id', $user->created_by)->first();
        }
        $response = json_decode($request->json, true);
        $get_amount = $request->amount;
        $settings= Utility::settingsById($invoice->created_by);

        if ($invoice) {
            try {
                if ($request->status == 'approved') {

                    try {
                        $invoice_payment                 = new InvoicePayment();
                        $invoice_payment->invoice_id     = $invoice->id;
                        $invoice_payment->date           = Date('Y-m-d');
                        $invoice_payment->amount         = $get_amount;
                        $invoice_payment->account_id     = 0;
                        $invoice_payment->payment_method = 0;
                        $invoice_payment->order_id       = $orderID;
                        $invoice_payment->payment_type   = 'FedaPay';
                        $invoice_payment->receipt        = '';
                        $invoice_payment->reference      = '';
                        $invoice_payment->description    = 'Invoice ' . Utility::invoiceNumberFormat($settings, $invoice->invoice_id);
                        $invoice_payment->save();

                        if ($invoice->getDue() <= 0) {
                            $invoice->status = 4;
                            $invoice->save();
                        } elseif (($invoice->getDue() - $invoice_payment->amount) == 0) {
                            $invoice->status = 4;
                            $invoice->save();
                        } else {
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
                            'invoice_payment_type' => 'FedaPay',
                            'customer_name' => $customer->name,
                        ];
                        //Slack Notification
                        if (isset($settings['payment_notification']) && $settings['payment_notification'] == 1) {
                            Utility::send_slack_msg('new_invoice_payment', $notificationArr, $invoice->created_by);
                        }
                        //Telegram Notification
                        if (isset($settings['telegram_payment_notification']) && $settings['telegram_payment_notification'] == 1) {
                            Utility::send_telegram_msg('new_invoice_payment', $notificationArr, $invoice->created_by);
                        }
                        //Twilio Notification
                        if (isset($settings['twilio_payment_notification']) && $settings['twilio_payment_notification'] == 1) {
                            Utility::send_twilio_msg($customer->contact, 'new_invoice_payment', $notificationArr, $invoice->created_by);
                        }
                        //webhook
                        $module = 'New Invoice Payment';
                        $webhook =  Utility::webhookSetting($module, $invoice->created_by);
                        if ($webhook) {
                            $parameter = json_encode($invoice_payment);
                            $status = Utility::WebhookCall($webhook['url'], $parameter, $webhook['method']);
                            if ($status == true) {
                                return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('error', __('Transaction has been failed.'));
                            } else {
                                return redirect()->back()->with('error', __('Webhook call failed.'));
                            }
                        }

                        return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('success', __('Invoice paid Successfully!'));
                    } catch (\Exception $e) {
                        return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('error', __($e->getMessage()));
                    }
                } else {
                    return redirect()->back()->with('error', ('Permission denied'));
                }
            } catch (\Exception $e) {
                dd($e);
                if (Auth::check()) {
                    return redirect()->route('invoice.link.copy', $request->invoice_id)->with('error', $e->getMessage());
                } else {
                    return redirect()->route('invoice.link.copy', encrypt($request->invoice_id))->with('success', $e->getMessage());
                }
            }
        } else {
            if (Auth::check()) {
                return redirect()->route('invoice.link.copy', $request->invoice_id)->with('error', __('Invoice not found.'));
            } else {
                return redirect()->route('invoice.link.copy', encrypt($request->invoice_id))->with('success', __('Invoice not found.'));
            }
        }
    }
}
