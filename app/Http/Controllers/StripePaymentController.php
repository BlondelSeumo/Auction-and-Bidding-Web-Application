<?php

namespace App\Http\Controllers;


use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Models\Transaction;
use App\Models\UserCoupon;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Session;
use Stripe;

class StripePaymentController extends Controller
{
    public $settings;

    public function index()
    {
        $objUser = \Auth::user();

        if ($objUser->type == 'super admin') {
            $orders = Order::select([
                'orders.*',
                'users.name as user_name',
            ])->join('users', 'orders.user_id', '=', 'users.id')->orderBy('orders.created_at', 'DESC')->with('total_coupon_used.coupon_detail')->with(['total_coupon_used.coupon_detail'])->get();

            $userOrders = Order::select('*')
                ->whereIn('id', function ($query) {
                    $query->selectRaw('MAX(id)')
                        ->from('orders')
                        ->groupBy('user_id');
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return view('order.index', compact('orders', 'userOrders'));

        } else {
            $orders = Order::select([
                'orders.*',
                'users.name as user_name',
            ])->join('users', 'orders.user_id', '=', 'users.id')->orderBy('orders.created_at', 'DESC')->where('users.id', '=', $objUser->id)->with('total_coupon_used.coupon_detail')->with(['total_coupon_used.coupon_detail'])->get();

            return view('order.index', compact('orders'));
        }
    }

    public function refund(Request $request, $id, $user_id)
    {
        Order::where('id', $request->id)->update(['is_refund' => 1]);

        $user = User::find($user_id);

        $assignPlan = $user->assignPlan(1);

        return redirect()->back()->with('success', __('We successfully planned a refund and assigned a free plan.'));
    }

    public function stripe($code)
    {

        $admin_payment_setting = Utility::getAdminPaymentSetting();
        if (isset($admin_payment_setting) && !empty($admin_payment_setting)) {
            if (
                $admin_payment_setting['is_manually_payment_enabled'] == 'on' ||
                    $admin_payment_setting['is_bank_transfer_enabled'] == 'on' ||
                    $admin_payment_setting['is_stripe_enabled'] == 'on' ||
                    $admin_payment_setting['is_paypal_enabled'] == 'on' ||
                    $admin_payment_setting['is_paystack_enabled'] == 'on' ||
                    $admin_payment_setting['is_flutterwave_enabled'] == 'on' ||
                    $admin_payment_setting['is_razorpay_enabled'] == 'on' ||
                    $admin_payment_setting['is_mercado_enabled'] == 'on' ||
                    $admin_payment_setting['is_paytm_enabled'] == 'on' ||
                    $admin_payment_setting['is_mollie_enabled'] == 'on' ||
                    $admin_payment_setting['is_skrill_enabled'] == 'on' ||
                    $admin_payment_setting['is_coingate_enabled'] == 'on' ||
                    $admin_payment_setting['is_paymentwall_enabled'] == 'on' ||
                    $admin_payment_setting['is_toyyibpay_enabled'] == 'on' ||
                    $admin_payment_setting['is_payfast_enabled'] == 'on' ||
                    $admin_payment_setting['is_iyzipay_enabled'] == 'on' ||
                    $admin_payment_setting['is_sspay_enabled'] == 'on' ||
                    $admin_payment_setting['is_paytab_enabled'] == 'on' ||
                    $admin_payment_setting['is_benefit_enabled'] == 'on' ||
                    $admin_payment_setting['is_cashfree_enabled'] == 'on' ||
                    $admin_payment_setting['is_aamarpay_enabled'] == 'on' ||
                    $admin_payment_setting['is_paytr_enabled'] == 'on' ||
                    $admin_payment_setting['is_yookassa_enabled'] == 'on' ||
                    $admin_payment_setting['is_midtrans_enabled'] == 'on' ||
                    $admin_payment_setting['is_xendit_enabled'] == 'on' ||
                    $admin_payment_setting['is_nepalste_enabled'] == 'on' ||
                    $admin_payment_setting['is_paiementpro_enabled'] == 'on' ||
                    $admin_payment_setting['is_cinetpay_enabled'] == 'on' ||
                    $admin_payment_setting['is_fedapay_enabled'] == 'on' ||
                    $admin_payment_setting['is_payhere_enabled'] == 'on' ||
                    $admin_payment_setting['tap_payment_is_on'] == 'on' ||
                    $admin_payment_setting['authorizenet_payment_is_on'] == 'on' ||
                    $admin_payment_setting['khalti_payment_is_on'] == 'on' ||
                    $admin_payment_setting['easebuzz_payment_is_on'] == 'on' ||
                    $admin_payment_setting['ozow_payment_is_enabled'] == 'on' ) {
                if(\Auth::user()->type == 'company'){
                    try {
                        $plan_id = Crypt::decrypt($code);
                    } catch (\Throwable $th) {
                        return redirect()->back()->with('error', __('Plan Not Found.'));
                    }
                    $plan_id = Crypt::decrypt($code);
                    $plan = Plan::find($plan_id);
                    $admin_payment_setting = Utility::getAdminPaymentSetting();
                    if ($plan) {
                        return view('stripe', compact('plan', 'admin_payment_setting'));
                    } else {
                        return redirect()->back()->with('error', __('Plan is deleted.'));
                    }
                } else {
                    return redirect()->back()->with('error', __('Permission denied.'));
                }
            } else {
                return redirect()->route('plans.index')->with('error', __('The admin has not set the payment method.'));
            }
        }
    }

    public function stripePost(Request $request)
    {


        $objUser = \Auth::user();
        $planID = Crypt::decrypt($request->plan_id);
        $plan = Plan::find($planID);

        $admin_payment_setting = Utility::getAdminPaymentSetting();

        if ($plan) {

            try {
                $price = $plan->price;

                if (!empty($request->coupon)) {
                    $coupons = Coupon::where('code', strtoupper($request->coupon))->where('is_active', '1')->first();
                    if (!empty($coupons)) {
                        $usedCoupun = $coupons->used_coupon();
                        $discount_value = ($plan->price / 100) * $coupons->discount;
                        $price = $plan->price - $discount_value;


                        if ($coupons->limit == $usedCoupun) {
                            return redirect()->back()->with('error', __('This coupon code has expired.'));
                        }
                    } else {
                        return redirect()->back()->with('error', __('This coupon code is invalid or has expired.'));
                    }
                }

                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

                if ($price > 0.0) {
                    Stripe\Stripe::setApiKey($admin_payment_setting['stripe_secret']);
                    $data = Stripe\Charge::create([
                        "amount" => 100 * $price,
                        "currency" => !empty($admin_payment_setting['currency']) ? $admin_payment_setting['currency'] : '',
                        "source" => $request->stripeToken,
                        "description" => " Plan - " . $plan->name,
                        "metadata" => ["order_id" => $orderID],
                        "shipping" => [
                            "name" => $request->name,
                            'address' => [
                                "line1" => "123 Default Street",
                                "city" => "aaaa",
                                "state" => "bbbbbb",
                                "postal_code" => "111111",
                                "country" => "IN",
                            ]
                        ],
                    ]);


                } else {

                    $data['amount_refunded'] = 0;
                    $data['failure_code'] = '';
                    $data['paid'] = 1;
                    $data['captured'] = 1;
                    $data['status'] = 'success';


                }


                if ($data['amount_refunded'] == 0 && empty($data['failure_code']) && $data['paid'] == 1 && $data['captured'] == 1) {


                    Order::create([
                        'order_id' => $orderID,
                        'name' => $request->name,
                        'card_number' => isset($data['payment_method_details']['card']['last4']) ? $data['payment_method_details']['card']['last4'] : '',
                        'card_exp_month' => isset($data['payment_method_details']['card']['exp_month']) ? $data['payment_method_details']['card']['exp_month'] : '',
                        'card_exp_year' => isset($data['payment_method_details']['card']['exp_year']) ? $data['payment_method_details']['card']['exp_year'] : '',
                        'plan_name' => $plan->name,
                        'plan_id' => $plan->id,
                        'price' => $price,
                        'price_currency' => !empty($admin_payment_setting['currency']) ? $admin_payment_setting['currency'] : '',
                        'txn_id' => isset($data['balance_transaction']) ? $data['balance_transaction'] : '',
                        'payment_type' => __('STRIPE'),
                        'payment_status' => isset($data['status']) ? $data['status'] : 'success',
                        'receipt' => isset($data['receipt_url']) ? $data['receipt_url'] : 'free coupon',
                        'user_id' => $objUser->id,
                    ]);

                    if (!empty($request->coupon)) {

                        $userCoupon = new UserCoupon();
                        $userCoupon->user = $objUser->id;
                        $userCoupon->coupon = $coupons->id;
                        $userCoupon->order = $orderID;
                        $userCoupon->save();

                        $usedCoupun = $coupons->used_coupon();
                        if ($coupons->limit <= $usedCoupun) {
                            $coupons->is_active = 0;
                            $coupons->save();
                        }
                    }

                    Utility::referralTransaction($plan);

                    if ($data['status'] == 'succeeded') {
                        $assignPlan = $objUser->assignPlan($plan->id);
                        if ($assignPlan['is_success']) {
                            return redirect()->route('plans.index')->with('success', __('Plan successfully activated.'));
                        } else {
                            return redirect()->route('plans.index')->with('error', __($assignPlan['error']));
                        }
                    } else {
                        return redirect()->route('plans.index')->with('error', __('Your payment has failed.'));
                    }
                } else {
                    return redirect()->route('plans.index')->with('error', __('Transaction has been failed.'));
                }
            } catch (\Exception $e) {
                return redirect()->route('plans.index')->with('error', __($e->getMessage()));
            }
        } else {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
        }

    }

    public function addPayment(Request $request, $id)
    {


        $invoice = Invoice::find($id);
        $company_payment_setting = Utility::getCompanyPaymentSetting($invoice->created_by);


        $settings = DB::table('settings')->where('created_by', '=', $invoice->created_by)->get()->pluck('value', 'name');




        if ($invoice) {
            if ($request->amount > $invoice->getDue()) {
                return redirect()->back()->with('error', __('Invalid amount.'));
            } else {
                try {

                    $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                    $price = $request->amount;
                    Stripe\Stripe::setApiKey($company_payment_setting['stripe_secret']);

                    $data = Stripe\Charge::create([
                        "amount" => 100 * $price,
                        "currency" => $settings['site_currency'],
                        "source" => $request->stripeToken,
                        "description" => 'Invoice ' . Utility::invoiceNumberFormat($settings, $invoice->invoice_id),
                        "metadata" => ["order_id" => $orderID],
                        "shipping" => [
                            "name" => $request->name,
                            'address' => [
                                "line1" => "123 Default Street",
                                "city" => "aaaa",
                                "state" => "bbbbbb",
                                "postal_code" => "111111",
                                "country" => "IN",
                            ]
                        ],
                    ]);

                    if ($data['amount_refunded'] == 0 && empty($data['failure_code']) && $data['paid'] == 1 && $data['captured'] == 1) {
                        $payments = InvoicePayment::create([

                            'invoice_id' => $invoice->id,
                            'date' => date('Y-m-d'),
                            'amount' => $price,
                            'account_id' => 0,
                            'payment_method' => 0,
                            'order_id' => $orderID,
                            'currency' => $data['currency'],
                            'txn_id' => $data['balance_transaction'],
                            'payment_type' => __('STRIPE'),
                            'receipt' => $data['receipt_url'],
                            'reference' => '',
                            'description' => 'Invoice ' . Utility::invoiceNumberFormat($settings, $invoice->invoice_id),
                        ]);

                        if ($invoice->getDue() <= 0) {
                            $invoice->status = 4;
                        } elseif (($invoice->getDue() - $request->amount) == 0) {
                            $invoice->status = 4;
                        } else {
                            $invoice->status = 3;
                        }
                        $invoice->save();

                        $invoicePayment = new Transaction();
                        $invoicePayment->user_id = $invoice->customer_id;
                        $invoicePayment->user_type = 'Customer';
                        $invoicePayment->type = 'STRIPE';
                        $invoicePayment->created_by = $invoice->invoice_id;
                        $invoicePayment->payment_id = $invoicePayment->id;
                        $invoicePayment->category = 'Invoice';
                        $invoicePayment->amount = $price;
                        $invoicePayment->date = date('Y-m-d');
                        $invoicePayment->payment_id = $payments->id;
                        $invoicePayment->description = 'Invoice ' . Utility::invoiceNumberFormat($settings, $invoice->invoice_id);
                        $invoicePayment->account = 0;
                        Transaction::addTransaction($invoicePayment);

                        //for customer balance update
                        Utility::updateUserBalance('customer', $invoice->customer_id, $request->amount, 'debit');

                        //For Notification
                        $setting = Utility::settingsById($invoice->created_by);
                        $customer = Customer::find($invoice->customer_id);
                        $notificationArr = [
                            'payment_price' => $price,
                            'invoice_payment_type' => $invoicePayment->type,
                            'customer_name' => $customer->name,
                        ];
                        //Slack Notification
                        if (isset($setting['payment_notification']) && $setting['payment_notification'] == 1) {
                            Utility::send_slack_msg('new_invoice_payment', $notificationArr, $invoice->created_by);
                        }
                        //Telegram Notification
                        if (isset($setting['telegram_payment_notification']) && $setting['telegram_payment_notification'] == 1) {
                            Utility::send_telegram_msg('new_invoice_payment', $notificationArr, $invoice->created_by);
                        }
                        //Twilio Notification
                        if (isset($setting['twilio_payment_notification']) && $setting['twilio_payment_notification'] == 1) {
                            Utility::send_twilio_msg($customer->contact, 'new_invoice_payment', $notificationArr, $invoice->created_by);
                        }
                        //webhook
                        $module = 'New Invoice Payment';
                        $webhook = Utility::webhookSetting($module, $invoice->created_by);
                        if ($webhook) {
                            $parameter = json_encode($invoicePayment);
                            $status = Utility::WebhookCall($webhook['url'], $parameter, $webhook['method']);
                            if ($status == true) {
                                return redirect()->back()->with('success', __('Payment successfully added!'));
                            } else {
                                return redirect()->back()->with('error', __('Webhook call failed.'));
                            }
                        }

                        return redirect()->back()->with('success', __(' Payment successfully added.'));

                    } else {
                        return redirect()->back()->with('error', __('Transaction has been failed.'));
                    }
                } catch (\Exception $e) {
                    //                    dd($e);
                    return redirect()->back()->with('error', __($e->getMessage()));
                }
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
