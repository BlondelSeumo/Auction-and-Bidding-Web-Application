<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Utility;
use App\Models\UserCoupon;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use PhpParser\Node\Stmt\TryCatch;

class AamarpayController extends Controller
{

    public function pay(Request $request)
    {
        $url = 'https://sandbox.aamarpay.com/request.php';
        $payment_setting = Utility::getAdminPaymentSetting();
        $aamarpay_store_id = $payment_setting['aamarpay_store_id'];
        $aamarpay_signature_key = $payment_setting['aamarpay_signature_key'];
        $aamarpay_description = $payment_setting['aamarpay_description'];
        $currency = $payment_setting['currency'];
        $planID = \Illuminate\Support\Facades\Crypt::decrypt($request->plan_id);
        $authuser = Auth::user();
        $plan = Plan::find($planID);

        if ($plan) {
            $get_amount = $plan->price;

            // if (Auth::user()->phone == null) {
            //     return redirect()->back()->with('failed', __('Please add phone number to your profile.'));
            // }
            try {
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
                            $authuser = \Auth::user();
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
                                        'price_currency' => !empty($payment_setting['currency']) ? $payment_setting['currency'] : 'BDT',
                                        'txn_id' => '',
                                        'payment_type' => 'Aamarpay',
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
                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                $fields = array(
                    'store_id' => $aamarpay_store_id,
                    //store id will be aamarpay,  contact integration@aamarpay.com for test/live id
                    'amount' => $get_amount,
                    //transaction amount
                    'payment_type' => '',
                    //no need to change
                    'currency' => $currency,
                    //currenct will be USD/BDT
                    'tran_id' => $orderID,
                    //transaction id must be unique from your end
                    'cus_name' => $authuser->name,
                    //customer name
                    'cus_email' => $authuser->email,
                    //customer email address
                    'cus_add1' => '',
                    //customer address
                    'cus_add2' => '',
                    //customer address
                    'cus_city' => '',
                    //customer city
                    'cus_state' => '',
                    //state
                    'cus_postcode' => '',
                    //postcode or zipcode
                    'cus_country' => '',
                    //country
                    'cus_phone' => '1234567890',
                    //customer phone number
                    'success_url' => route('pay.aamarpay.success', Crypt::encrypt(['response'=>'success','coupon' => $coupon, 'plan_id' => $plan->id, 'price' => $get_amount, 'order_id' => $orderID])),
                    //your success route
                    'fail_url' => route('pay.aamarpay.success', Crypt::encrypt(['response'=>'failure','coupon' => $coupon, 'plan_id' => $plan->id, 'price' => $get_amount, 'order_id' => $orderID])),
                    //your fail route
                    'cancel_url' => route('pay.aamarpay.success', Crypt::encrypt(['response'=>'cancel'])),
                    //your cancel url
                    'signature_key' => $aamarpay_signature_key,
                    'desc' => $aamarpay_description,
                ); //signature key will provided aamarpay, contact integration@aamarpay.com for test/live signature key


                $fields_string = http_build_query($fields);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_URL, $url);

                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $url_forward = str_replace('"', '', stripslashes(curl_exec($ch)));
                curl_close($ch);

                $this->redirect_to_merchant($url_forward);
            } catch (\Exception $e) {

                return redirect()->back()->with('error', $e);
            }
        } else {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
        }

    }

    function redirect_to_merchant($url)
    {

        $token = csrf_token();
        ?>
        <html xmlns="http://www.w3.org/1999/xhtml">

        <head>
            <script type="text/javascript">
                function closethisasap() { document.forms["redirectpost"].submit(); }
            </script>
        </head>

        <body onLoad="closethisasap();">

        <form name="redirectpost" method="post" action="<?php echo 'https://sandbox.aamarpay.com/' . $url; ?>">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
        </form>
        </body>

        </html>
        <?php
        exit;
    }

    public function aamarpaysuccess($data, Request $request)
    {
        $data = Crypt::decrypt($data);
        $user = \Auth::user();

        if ($data['response'] == "success")
        {
            $plan = Plan::find($data['plan_id']);
            $couponCode = $data['coupon'];
            $getAmount = $data['price'];
            $orderID = $data['order_id'];
            if ($couponCode != 0) {
                $coupons = Coupon::where('code', strtoupper($couponCode))->where('is_active', '1')->first();
                $request['coupon_id'] = $coupons->id;
            } else {
                $coupons = null;
            }
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
            $order->price_currency = !empty($payment_setting['currency']) ? $payment_setting['currency'] : 'BDT';
            $order->payment_type = __('Aamarpay');
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
        elseif ($data['response'] == "cancel")
        {
            return redirect()->route('plans.index')->with('error', __('Your payment is cancel'));
        }
        else {
            return redirect()->route('plans.index')->with('error', __('Your Transaction is fail please try again'));
        }

    }

    public function invoicepaywithaamarpay(Request $request)
    {

        $invoice_id = \Illuminate\Support\Facades\Crypt::decrypt($request->invoice_id);
        $invoice = Invoice::find($invoice_id);

        $this->invoiceData = $invoice;
        $user      = User::find($invoice->created_by);

        $company_payment_setting = Utility::getCompanyPaymentSetting($user->id);
        $settings= Utility::settingsById($invoice->created_by);

        $url = 'https://sandbox.aamarpay.com/request.php';


        try {

            $get_amount = $request->amount;

            if ($invoice && $get_amount != 0) {

                if ($get_amount > $invoice->getDue())
                {
                    return redirect()->back()->with('error', __('Invalid amount.'));
                }
            }
            try {

                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

                $fields = array(
                    'store_id' => $company_payment_setting['aamarpay_store_id'],
                    //store id will be aamarpay,  contact integration@aamarpay.com for test/live id
                    'amount' => $get_amount,
                    //transaction amount
                    'payment_type' => '',
                    //no need to change
                    'currency' => $settings['site_currency'],
                    //currenct will be USD/BDT
                    'tran_id' => $orderID,
                    //transaction id must be unique from your end
                    'cus_name' => $user['name'],
                    //customer name
                    'cus_email' => $user['email'],
                    //customer email address
                    'cus_add1' => '',
                    //customer address
                    'cus_add2' => '',
                    //customer address
                    'cus_city' => '',
                    //customer city
                    'cus_state' => '',
                    //state
                    'cus_postcode' => '',
                    //postcode or zipcode
                    'cus_country' => '',
                    //country
                    'cus_phone' => '1234567890',
                    //customer phone number
                    'success_url' => route('invoice.pay.aamarpay.success',Crypt::encrypt(['response'=>'success','invoice_id' => $invoice_id, 'amount' => $get_amount, 'order_id' => $orderID]) ),
                    //your success route
                    'fail_url' => route('invoice.pay.aamarpay.success',Crypt::encrypt(['response'=>'success','invoice_id' => $invoice_id, 'amount' => $get_amount, 'order_id' => $orderID]) ),
                    //your fail route
                    'cancel_url' => route('invoice.pay.aamarpay.success', Crypt::encrypt(['response'=>'success','invoice_id' => $invoice_id, 'amount' => $get_amount, 'order_id' => $orderID]) ),
                    //your cancel url
                    'signature_key' => $company_payment_setting['aamarpay_signature_key'],
                    'desc' => $company_payment_setting['aamarpay_description'],
                ); //signature key will provided aamarpay, contact integration@aamarpay.com for test/live signature key



                $fields_string = http_build_query($fields);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_URL, $url);

                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $url_forward = str_replace('"', '', stripslashes(curl_exec($ch)));
                curl_close($ch);
                $this->redirect_to_merchant($url_forward);
            } catch (\Exception $e) {

                return redirect()->back()->with('error', $e);
            }

        } catch (\Throwable $e) {
            return redirect()->back()->with('error', __($e->getMessage()));
        }
    }

    public function getInvoicePaymentStatus($data)
    {
        $data = Crypt::decrypt($data);

        $getAmount = $data['amount'];

        $invoice    = Invoice::find($data['invoice_id']);

        $user      = User::find($invoice->created_by);
        $settings= Utility::settingsById($invoice->created_by);
//        $companyPaymentSettings = Utility::getCompanyPaymentSetting($user->id);

        try {

            $orderID  = strtoupper(str_replace('.', '', uniqid('', true)));

            if ($data['response'] == "success")
            {
                $invoice_payment                 = new InvoicePayment();
                $invoice_payment->invoice_id     = $invoice->id;
                $invoice_payment->date           = Date('Y-m-d');
                $invoice_payment->amount         = !empty($getAmount) ?$getAmount:0;
                $invoice_payment->account_id         = 0;
                $invoice_payment->payment_method         = 0;
                $invoice_payment->order_id      =$orderID;
                $invoice_payment->payment_type   = 'Aamarpay';
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
                Utility::updateUserBalance('customer', $invoice->customer_id, $getAmount, 'debit');

                //For Notification
                $setting  = Utility::settingsById($invoice->created_by);
                $customer = Customer::find($invoice->customer_id);
                $notificationArr = [
                    'payment_price' => $getAmount,
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
            elseif ($data['response'] == "cancel")
            {

                return redirect()->back()->with('error', __('Your payment is cancel'));
            }
            else
            {


                return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('error', __('Transaction fail'));
            }
        }
        catch (\Throwable $th)
        {

            return redirect()->route('invoice.link.copy', \Crypt::encrypt($invoice->id))->with('error', $th->getMessage());

        }
    }

}
