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

class PaiementProController extends Controller
{
    public function planPayWithPaiementpro(Request $request)   {
        $payment_setting = Utility::getAdminPaymentSetting();
        $merchant_id = isset($payment_setting['paiementpro_merchant_id']) ? $payment_setting['paiementpro_merchant_id'] : '';
        $currency = isset($payment_setting['currency_code']) ? $payment_setting['currency_code'] : 'USD';
        $planID = \Illuminate\Support\Facades\Crypt::decrypt($request->plan_id);

        $plan = Plan::find($planID);
        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
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
                                    'payment_type' => 'Paiment Pro',
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
            $coupons = Coupon::where('code', strtoupper($request->coupon))->where('is_active', '1')->first();
            if (!empty($request->coupon)) {
                $call_back = route('plan.get.paiementpro.status', [
                    'get_amount' => $get_amount,
                    'plan' => $plan,
                    'coupon_id' => $coupons->id
                ]);
            } else {
                $call_back = route('plan.get.paiementpro.status', [
                    'get_amount' => $get_amount,
                    'plan' => $plan,
                ]);
            }
            $merchant_id = isset($payment_setting['paiementpro_merchant_id']) ? $payment_setting['paiementpro_merchant_id'] : '';
            $data = array(
                'merchantId' => $merchant_id,
                'amount' =>  $get_amount,
                'description' => "Api PHP",
                'channel' => $request->channel,
                'countryCurrencyCode' => $currency,
                'referenceNumber' => "REF-" . time(),
                'customerEmail' => $user->email,
                'customerFirstName' => $user->name,
                'customerLastname' =>  $user->name,
                'customerPhoneNumber' => $request->mobile_number,
                'notificationURL' => $call_back,
                'returnURL' => $call_back,
                'returnContext' => json_encode([
                'coupon_code' => $request->coupon_code,
                ]),
            );
            $data = json_encode($data);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.paiementpro.net/webservice/onlinepayment/init/curl-init.php");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            $response = curl_exec($ch);

            curl_close($ch);
            $response = json_decode($response);
            if (isset($response->success) && $response->success == true) {
                // redirect to approve href
                return redirect($response->url);

                return redirect()
                    ->route('plans.index', \Illuminate\Support\Facades\Crypt::encrypt($plan->id))
                    ->with('error', 'Something went wrong. OR Unknown error occurred');
            } else {
                return redirect()
                    ->route('plans.index', \Illuminate\Support\Facades\Crypt::encrypt($plan->id))
                    ->with('error', $response->message ?? 'Something went wrong.');
            }
        } else {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
        }
    }
    public function planGetPaiementProStatus(Request $request)
    {
        $payment_setting = Utility::getAdminPaymentSetting();
        $currency = isset($payment_setting['currency_code']) ? $payment_setting['currency_code'] : '';

        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
        $getAmount = $request->get_amount;
        $authuser = Auth::user();
        $plan = Plan::find($request->plan);

        if($request->responsecode == 0){

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
            $order->payment_type = __('Paiement Pro');
            $order->payment_status = 'success';
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
            return redirect()->route('plans.index')->with('error', __('Your Transaction has been failed.'));
        }

    }
    public function invoicePayWithPaiementPro(Request $request)
    {
        $invoice_id = \Illuminate\Support\Facades\Crypt::decrypt($request->invoice_id);
        $invoice = Invoice::find($invoice_id);

        $getAmount = $request->amount;
        if (Auth::check()) {
            $user = Auth::user();
        } else {
            $user = User::where('id', $invoice->created_by)->first();
        }

        $company_payment_setting = Utility::getCompanyPaymentSetting($user->id);
        $merchant_id = isset($company_payment_setting['paiementpro_merchant_id']) ? $company_payment_setting['paiementpro_merchant_id'] : '';
        $currency_code = !empty($company_payment_setting['currency_code']) ? $company_payment_setting['currency_code'] : 'USD';
        $get_amount = round($request->amount);
        $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

        try {
            if ($invoice) {
                $merchant_id = isset($company_payment_setting['paiementpro_merchant_id']) ? $company_payment_setting['paiementpro_merchant_id'] : '';
                $data = array(
                    'merchantId' => $merchant_id,
                    'amount' =>  $getAmount,
                    'description' => "Api PHP",
                    'channel' => $request->channel,
                    'countryCurrencyCode' => 'USD',
                    'referenceNumber' => "REF-" . time(),
                    'customerEmail' => $user->email,
                    'customerFirstName' => $user->name,
                    'customerLastname' =>  $user->name,
                    'customerPhoneNumber' => $request->mobile_number,
                    'notificationURL' => route('invoice.paiementpro.status',$invoice_id),
                    'returnURL' => route('invoice.paiementpro.status',$invoice_id),
                    'returnContext' => json_encode([
                        'coupon_code' => $request->coupon_code,
                    ]),
                );
                $data = json_encode($data);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://www.paiementpro.net/webservice/onlinepayment/init/curl-init.php");
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                $response = curl_exec($ch);

                curl_close($ch);
                $response = json_decode($response);
                if (isset($response->success) && $response->success == true) {
                    // redirect to approve href
                    return redirect($response->url);

                }

            } else {
                return redirect()->back()->with('error', 'Invoice not found.');
            }
        } catch (\Throwable $e) {

            return redirect()->back()->with('error', __($e));
        }
    }
    public function getInvociePaymentStatus(Request $request, $invoice_id)
    {
        $invoice = Invoice::find($invoice_id);
        $settings = Utility::settingsById($invoice->created_by);
        $response = json_decode($request->json, true);

        if ($invoice) {
            $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
            try
            {

                $invoice_payment = new InvoicePayment();
                $invoice_payment->invoice_id = $invoice->id;
                $invoice_payment->date = Date('Y-m-d');
                $invoice_payment->amount = $request->amount;
                $invoice_payment->account_id = 0;
                $invoice_payment->payment_method = 0;
                $invoice_payment->order_id = $orderID;
                $invoice_payment->payment_type = 'Payment Pro';
                $invoice_payment->receipt = '';
                $invoice_payment->reference = '';
                $invoice_payment->description = 'Invoice ' . Utility::invoiceNumberFormat($settings, $invoice->invoice_id);
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
                $setting = Utility::settingsById($invoice->created_by);
                $customer = Customer::find($invoice->customer_id);
                $notificationArr = [
                    'payment_price' => $request->amount,
                    'invoice_payment_type' => 'Aamarpay',
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
                $webhook = Utility::webhookSetting($module, $invoice->created_by);
                if ($webhook) {
                    $parameter = json_encode($invoice_payment);
                    $status = Utility::WebhookCall($webhook['url'], $parameter, $webhook['method']);
                    if ($status == true) {
                        return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('error', __('Transaction has been failed.'));
                    } else {
                        return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('error', __('Webhook call failed.'));
                    }
                }

                return redirect()->route('invoice.link.copy', \Crypt::encrypt($request->invoice_id))->with('success', __('Invoice paid Successfully!'));

            } catch (\Exception $e) {
                return redirect()->route('invoice.link.copy', \Illuminate\Support\Facades\Crypt::encrypt($request->invoice_id))->with('success', $e->getMessage());
            }
        } else {
            return redirect()->route('invoice.link.copy', \Illuminate\Support\Facades\Crypt::encrypt($request->invoice_id))->with('success', __('Invoice not found.'));
        }

    }
}
