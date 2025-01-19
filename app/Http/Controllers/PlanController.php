<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\Utility;
use File;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {

        if(\Auth::user()->can('manage plan'))
        {
            if(\Auth::user()->type == 'super admin')
            {
                $plans = Plan::get();
            }
            else
            {
                $plans = Plan::where('is_disable', 1)->get();
            }
            $admin_payment_setting = Utility::getAdminPaymentSetting();

            return view('plan.index', compact('plans', 'admin_payment_setting'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function create()
    {
        if(\Auth::user()->can('create plan'))
        {
            $arrDuration = [
                'lifetime' => __('Lifetime'),
                'month' => __('Per Month'),
                'year' => __('Per Year'),
            ];

            return view('plan.create', compact('arrDuration'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function store(Request $request)
    {



        if(\Auth::user()->can('create plan'))
        {
            $admin_payment_setting = Utility::getAdminPaymentSetting();

            if(!empty($admin_payment_setting) && ($admin_payment_setting['is_manually_payment_enabled'] == 'on'
                    || $admin_payment_setting['is_bank_transfer_enabled'] == 'on' || $admin_payment_setting['is_stripe_enabled'] == 'on'
                    || $admin_payment_setting['is_paypal_enabled'] == 'on' || $admin_payment_setting['is_paystack_enabled'] == 'on'
                    || $admin_payment_setting['is_flutterwave_enabled'] == 'on' || $admin_payment_setting['is_razorpay_enabled'] == 'on'
                    || $admin_payment_setting['is_mercado_enabled'] == 'on' || $admin_payment_setting['is_paytm_enabled'] == 'on'
                    || $admin_payment_setting['is_mollie_enabled'] == 'on' || $admin_payment_setting['is_skrill_enabled'] == 'on'
                    || $admin_payment_setting['is_coingate_enabled'] == 'on'|| $admin_payment_setting['is_paymentwall_enabled'] == 'on'
                    || $admin_payment_setting['is_toyyibpay_enabled'] == 'on' || $admin_payment_setting['is_payfast_enabled'] == 'on'
                    || $admin_payment_setting['is_iyzipay_enabled'] == 'on' || $admin_payment_setting['is_sspay_enabled'] == 'on'
                    || $admin_payment_setting['is_paytab_enabled'] == 'on'  || $admin_payment_setting['is_benefit_enabled'] == 'on'
                    || $admin_payment_setting['is_cashfree_enabled'] == 'on'  || $admin_payment_setting['is_aamarpay_enabled'] == 'on'
                    || $admin_payment_setting['is_paytr_enabled'] == 'on' || $admin_payment_setting['is_yookassa_enabled'] ='on'
                    || $admin_payment_setting['is_midtrans_enabled'] == 'on' || $admin_payment_setting['is_xendit_enabled'] == 'on'
                    || $admin_payment_setting['is_nepalste_enabled'] == 'on'))
            {

                $validation                  = [];
                $validation['name']          = 'required|unique:plans';
                $validation['price']         = 'required|numeric|min:0';
                $validation['duration']      = 'required';
                $validation['max_users']     = 'required|numeric';
                $validation['max_customers'] = 'required|numeric';
                $validation['max_venders']   = 'required|numeric';
                $validation['storage_limit']   = 'required|numeric';

                if($request->image)
                {
                    $validation['image'] = 'required|max:20480';
                }
                $request->validate($validation);
                $post = $request->all();
                if(isset($request->enable_project))
                {
                    $post['project'] = 1;
                }
                if(isset($request->enable_crm))
                {
                    $post['crm'] = 1;
                }
                if(isset($request->enable_hrm))
                {
                    $post['hrm'] = 1;
                }
                if(isset($request->enable_account))
                {
                    $post['account'] = 1;
                }
                if(isset($request->enable_pos))
                {
                    $post['pos'] = 1;
                }
                if(isset($request->enable_chatgpt))
                {
                    $post['chatgpt'] = 1;
                }
                if(isset($request->trial))
                {
                    $post['trial'] = 1;
                }
                if($request->hasFile('image'))
                {
                    $filenameWithExt = $request->file('image')->getClientOriginalName();
                    $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                    $extension       = $request->file('image')->getClientOriginalExtension();
                    $fileNameToStore = 'plan_' . time() . '.' . $extension;

                    $dir = storage_path('uploads/plan/');
                    if(!file_exists($dir))
                    {
                        mkdir($dir, 0777, true);
                    }
                    $path          = $request->file('image')->storeAs('uploads/plan/', $fileNameToStore);
                    $post['image'] = $fileNameToStore;
                }



                if(Plan::create($post))
                {
                    return redirect()->back()->with('success', __('Plan Successfully created.'));
                }
                else
                {
                    return redirect()->back()->with('error', __('Something is wrong.'));
                }

            }
            else
            {
                return redirect()->back()->with('error', __('Please set stripe or paypal api key & secret key for add new plan.'));
            }
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }


    public function edit($plan_id)
    {
        if(\Auth::user()->can('edit plan'))
        {
            $arrDuration = Plan::$arrDuration;
            $plan        = Plan::find($plan_id);

            return view('plan.edit', compact('plan', 'arrDuration'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function update(Request $request, $plan_id)
    {


        if(\Auth::user()->can('edit plan'))
        {

            $admin_payment_setting = Utility::getAdminPaymentSetting();

            if(!empty($admin_payment_setting) && ($admin_payment_setting['is_manually_payment_enabled'] == 'on'
                    || $admin_payment_setting['is_bank_transfer_enabled'] == 'on' || $admin_payment_setting['is_stripe_enabled'] == 'on'
                    || $admin_payment_setting['is_paypal_enabled'] == 'on' || $admin_payment_setting['is_paystack_enabled'] == 'on'
                    || $admin_payment_setting['is_flutterwave_enabled'] == 'on' || $admin_payment_setting['is_razorpay_enabled'] == 'on'
                    || $admin_payment_setting['is_mercado_enabled'] == 'on' || $admin_payment_setting['is_paytm_enabled'] == 'on'
                    || $admin_payment_setting['is_mollie_enabled'] == 'on' || $admin_payment_setting['is_skrill_enabled'] == 'on'
                    || $admin_payment_setting['is_coingate_enabled'] == 'on'|| $admin_payment_setting['is_paymentwall_enabled'] == 'on'
                    || $admin_payment_setting['is_toyyibpay_enabled'] == 'on' || $admin_payment_setting['is_payfast_enabled'] == 'on'
                    || $admin_payment_setting['is_iyzipay_enabled'] == 'on' || $admin_payment_setting['is_sspay_enabled'] == 'on'
                    || $admin_payment_setting['is_paytab_enabled'] == 'on'  || $admin_payment_setting['is_benefit_enabled'] == 'on'
                    || $admin_payment_setting['is_cashfree_enabled'] == 'on'  || $admin_payment_setting['is_aamarpay_enabled'] == 'on'
                    || $admin_payment_setting['is_paytr_enabled'] == 'on' || $admin_payment_setting['is_yookassa_enabled'] ='on'
                    || $admin_payment_setting['is_midtrans_enabled'] == 'on' || $admin_payment_setting['is_xendit_enabled'] == 'on'
                    || $admin_payment_setting['is_nepalste_enabled'] == 'on'))
            {
                $plan = Plan::find($plan_id);
                if(!empty($plan))
                {
                    $validator = \Validator::make(
                        $request->all(),
                        [
                            'name'          => 'required|unique:plans,name,' . $plan_id,
                           'duration'      => function ($attribute, $value, $fail) use ($plan_id) {
                                if ($plan_id != 1 && empty($value)) {
                                    $fail($attribute.' is required.');
                                }
                            },
                            'max_users'     => 'required|numeric',
                            'max_customers' => 'required|numeric',
                            'max_venders'   => 'required|numeric',
                            'storage_limit' => 'required|numeric',
                        ]
                    );


                    if ($validator->fails()) {
                        $messages = $validator->getMessageBag();
                        return redirect()->back()->with('error', $messages->first());
                    }

                    $post = $request->all();

                    if(array_key_exists('enable_project', $post))
                    {
                        $post['project'] = 1;
                    }
                    else
                    {
                        $post['project'] = 0;
                    }
                    if(array_key_exists('enable_crm', $post))
                    {
                        $post['crm'] = 1;
                    }
                    else
                    {
                        $post['crm'] = 0;
                    }
                    if(array_key_exists('enable_hrm', $post))
                    {
                        $post['hrm'] = 1;
                    }
                    else
                    {
                        $post['hrm'] = 0;
                    }
                    if(array_key_exists('enable_account', $post))
                    {
                        $post['account'] = 1;
                    }
                    else
                    {
                        $post['account'] = 0;
                    }

                    if(array_key_exists('enable_pos', $post))
                    {
                        $post['pos'] = 1;
                    }
                    else
                    {
                        $post['pos'] = 0;
                    }
                    if(array_key_exists('enable_chatgpt', $post))
                    {
                        $post['chatgpt'] = 1;
                    }
                    else
                    {
                        $post['chatgpt'] = 0;
                    }
                    if(isset($request->trial))
                    {
                        $post['trial'] = 1;
                        $post['trial_days'] = $request->trial_days;
                    }
                    else
                    {
                        $post['trial'] = 0;
                        $post['trial_days'] = null;
                    }
                    if($request->hasFile('image'))
                    {
                        $filenameWithExt = $request->file('image')->getClientOriginalName();
                        $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                        $extension       = $request->file('image')->getClientOriginalExtension();
                        $fileNameToStore = 'plan_' . time() . '.' . $extension;

                        $dir = storage_path('uploads/plan/');
                        if(!file_exists($dir))
                        {
                            mkdir($dir, 0777, true);
                        }
                        $image_path = $dir . '/' . $plan->image;  // Value is not URL but directory file path
                        if(File::exists($image_path))
                        {

                            chmod($image_path, 0755);
                            File::delete($image_path);
                        }
                        $path = $request->file('image')->storeAs('uploads/plan/', $fileNameToStore);

                        $post['image'] = $fileNameToStore;
                    }

                    if($plan->update($post))
                    {
                        return redirect()->back()->with('success', __('Plan successfully updated.'));
                    }
                    else
                    {
                        return redirect()->back()->with('error', __('Something is wrong.'));
                    }
                }
                else
                {
                    return redirect()->back()->with('error', __('Plan not found.'));
                }


            }
            else
            {
                return redirect()->back()->with('error', __('Please set stripe api key & secret key for add new plan.'));
            }
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    public function destroy(Request $request, $id)
    {
        $userPlan = User::where('plan' , $id)->first();
        if($userPlan != null)
        {
            return redirect()->back()->with('error',__('The company has subscribed to this plan, so it cannot be deleted.'));
        }
        $plan = Plan::find($id);
        if($plan->id == $id)
        {
            $plan->delete();

            return redirect()->back()->with('success' , __('Plan deleted successfully'));
        }
        else
        {
            return redirect()->back()->with('error',__('Something went wrong'));
        }
    }

    public function userPlan(Request $request)
    {
        $objUser = \Auth::user();
        $planID  = \Illuminate\Support\Facades\Crypt::decrypt($request->code);
        $plan    = Plan::find($planID);
        if($plan)
        {
            if($plan->price <= 0)
            {
                $objUser->assignPlan($plan->id);

                return redirect()->route('plans.index')->with('success', __('Plan successfully activated.'));
            }
            else
            {
                return redirect()->back()->with('error', __('Something is wrong.'));
            }
        }
        else
        {
            return redirect()->back()->with('error', __('Plan not found.'));
        }
    }

    public function planTrial(Request $request , $plan)
    {

        $objUser = \Auth::user();
        $planID  = \Illuminate\Support\Facades\Crypt::decrypt($plan);
        $plan    = Plan::find($planID);

        if($plan)
        {
            if($plan->price > 0)
            {
                $user = User::find($objUser->id);
                $user->trial_plan = $planID;
                $currentDate = date('Y-m-d');
                $numberOfDaysToAdd = $plan->trial_days;

                $newDate = date('Y-m-d', strtotime($currentDate . ' + ' . $numberOfDaysToAdd . ' days'));
                $user->trial_expire_date = $newDate;
                $user->save();

                $objUser->assignPlan($planID);

                return redirect()->route('plans.index')->with('success', __('Plan successfully activated.'));
            }
            else
            {
                return redirect()->back()->with('error', __('Something is wrong.'));
            }
        }
        else
        {
            return redirect()->back()->with('error', __('Plan not found.'));
        }
    }

    public function planDisable(Request $request)
    {
        $userPlan = User::where('plan' , $request->id)->first();
        if($userPlan != null)
        {
            return response()->json(['error' =>__('The company has subscribed to this plan, so it cannot be disabled.')]);
        }

        Plan::where('id', $request->id)->update(['is_disable' => $request->is_disable]);

        if ($request->is_disable == 1) {
            return response()->json(['success' => __('Plan successfully enable.')]);

        } else {
            return response()->json(['success' => __('Plan successfully disable.')]);
        }
    }
}
