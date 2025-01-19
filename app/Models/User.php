<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Lab404\Impersonate\Models\Impersonate;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles ,Impersonate;

    protected $appends = ['profile'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'storage_limit',
        'avatar',
        'lang',
        'mode',
        'delete_status',
        'plan',
        'email_verified_at',
        'plan_expire_date',
        'requested_plan',
        'is_active',
        'referral_code',
        'used_referral_code',
        'commission_amount',
        'paid_amount',
        'is_enable_login',
        'last_login_at',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public $settings;

    public function getProfileAttribute()
    {

        if (!empty($this->avatar) && \Storage::exists($this->avatar)) {
            return $this->attributes['avatar'] = asset(\Storage::url($this->avatar));
        } else {
            return $this->attributes['avatar'] = asset(\Storage::url('avatar.png'));
        }
    }

    public function authId()
    {
        return $this->id;
    }

    public function creatorId()
    {
        if ($this->type == 'company' || $this->type == 'super admin') {
            return $this->id;
        } else {
            return $this->created_by;
        }
    }

    public function ownerId()
    {
        if ($this->type == 'company' || $this->type == 'super admin') {
            return $this->id;
        } else {
            return $this->created_by;
        }
    }

    public function ownerDetails()
    {

        if ($this->type == 'company' || $this->type == 'super admin') {
            return User::where('id', $this->id)->first();
        } else {
            return User::where('id', $this->created_by)->first();
        }
    }

    public function currentLanguage()
    {
        return $this->lang;
    }

    public function priceFormat($price)
    {
        $number = explode('.', $price);
        $length = strlen(trim($number[0]));
        $float_number = Utility::getValByName('float_number') == 'dot' ? '.' : ',';

        if ($length > 3) {
            $decimal_separator = Utility::getValByName('decimal_separator') == 'dot' ? ',' : ',';
            $thousand_separator = Utility::getValByName('thousand_separator') == 'dot' ? '.' : ',';
        } else {
            $decimal_separator = Utility::getValByName('decimal_separator') == 'dot' ? '.' : ',';
            $thousand_separator = Utility::getValByName('thousand_separator') == 'dot' ? '.' : ',';
        }

        $currency = Utility::getValByName('currency_symbol') == 'withcurrencysymbol' ? Utility::getValByName('site_currency_symbol') : Utility::getValByName('site_currency');
        $settings = Utility::settings();
        // dd($currency,$settings['site_currency']);
        $decimal_number = Utility::getValByName('decimal_number') ? Utility::getValByName('decimal_number') : 0;
        $currency_space = Utility::getValByName('currency_space');
        $price = number_format($price, $decimal_number, $decimal_separator, $thousand_separator);

        if ($float_number == 'dot') {
            $price = preg_replace('/' . preg_quote($thousand_separator, '/') . '([^' . preg_quote($thousand_separator, '/') . ']*)$/', $float_number . '$1', $price);
        } else {
            $price = preg_replace('/' . preg_quote($decimal_separator, '/') . '([^' . preg_quote($decimal_separator, '/') . ']*)$/', $float_number . '$1', $price);
        }

        return (($settings['site_currency_symbol_position'] == "pre") ? $currency : '') . ($currency_space == 'withspace' ? ' ' : '') . $price . ($currency_space == 'withspace' ? ' ' : '') . (($settings['site_currency_symbol_position'] == "post") ? $currency : '');
    }


    public function currencySymbol()
    {
        $settings = Utility::settings();

        return $settings['site_currency_symbol'];
    }

    public function dateFormat($date)
    {
        $settings = Utility::settings();

        return date($settings['site_date_format'], strtotime($date));
    }

    public function timeFormat($time)
    {
        $settings = Utility::settings();

        return date($settings['site_time_format'], strtotime($time));
    }
    public function DateTimeFormat($date)
    {
        $settings = Utility::settings();

        $date_formate = !empty($settings['site_date_format']) ? $settings['site_date_format'] : 'd-m-y';
        $time_formate = !empty($settings['site_time_format']) ? $settings['site_time_format'] : 'H:i';

        return date($date_formate . ' ' . $time_formate, strtotime($date));
    }
    public function purchaseNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["purchase_prefix"] . sprintf("%05d", $number);
    }
    public static function quotationNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["quotation_prefix"] . sprintf("%05d", $number);
    }
    public function posNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["pos_prefix"] . sprintf("%05d", $number);
    }

    public function invoiceNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["invoice_prefix"] . sprintf("%05d", $number);
    }

    public function proposalNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["proposal_prefix"] . sprintf("%05d", $number);
    }

    public function contractNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["contract_prefix"] . sprintf("%05d", $number);
    }

    public function billNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["bill_prefix"] . sprintf("%05d", $number);
    }
    public function expenseNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["expense_prefix"] . sprintf("%05d", $number);
    }

    public function journalNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["journal_prefix"] . sprintf("%05d", $number);
    }

    public function getPlan()
    {
        return $this->hasOne('App\Models\Plan', 'id', 'plan');
    }

    public function assignPlan($planID, $company_id = 0)
    {
        $plan = Plan::find($planID);
        if ($plan) {
            $this->plan = $plan->id;
            if($this->trial_expire_date != null);
            {
                $this->trial_expire_date = null;
            }

            if ($plan->duration == 'month') {
                $this->plan_expire_date = Carbon::now()->addMonths(1)->isoFormat('YYYY-MM-DD');
            } elseif ($plan->duration == 'year') {
                $this->plan_expire_date = Carbon::now()->addYears(1)->isoFormat('YYYY-MM-DD');
            } else {
                $this->plan_expire_date = null;
            }
            $this->save();

            if ($company_id != 0) {
                $user_id = $company_id;
            } else {
                $user_id = \Auth::user()->creatorId();
            }


            $users = User::where('created_by', '=', $user_id)->where('type', '!=', 'super admin')->where('type', '!=', 'company')->where('type', '!=', 'client')->get();
            $clients = User::where('created_by', '=', $user_id)->where('type', 'client')->get();
            $customers = Customer::where('created_by', '=', $user_id)->get();
            $venders = Vender::where('created_by', '=', $user_id)->get();

            if ($plan->max_users == -1) {
                foreach ($users as $user) {
                    $user->is_active = 1;
                    $user->save();
                }
            } else {
                $userCount = 0;
                foreach ($users as $user) {
                    $userCount++;
                    if ($userCount <= $plan->max_users) {
                        $user->is_active = 1;
                        $user->save();
                    } else {
                        $user->is_active = 0;
                        $user->save();
                    }
                }
            }

            if ($plan->max_clients == -1) {
                foreach ($clients as $client) {
                    $client->is_active = 1;
                    $client->save();
                }
            } else {
                $clientCount = 0;
                foreach ($clients as $client) {
                    $clientCount++;
                    if ($clientCount <= $plan->max_clients) {
                        $client->is_active = 1;
                        $client->save();
                    } else {
                        $client->is_active = 0;
                        $client->save();
                    }
                }
            }

            if ($plan->max_customers == -1) {
                foreach ($customers as $customer) {
                    $customer->is_active = 1;
                    $customer->save();
                }
            } else {
                $customerCount = 0;
                foreach ($customers as $customer) {
                    $customerCount++;
                    if ($customerCount <= $plan->max_customers) {
                        $customer->is_active = 1;
                        $customer->save();
                    } else {
                        $customer->is_active = 0;
                        $customer->save();
                    }
                }
            }

            if ($plan->max_venders == -1) {
                foreach ($venders as $vender) {
                    $vender->is_active = 1;
                    $vender->save();
                }
            } else {
                $venderCount = 0;
                foreach ($venders as $vender) {
                    $venderCount++;
                    if ($venderCount <= $plan->max_venders) {
                        $vender->is_active = 1;
                        $vender->save();
                    } else {
                        $vender->is_active = 0;
                        $vender->save();
                    }
                }
            }

            return ['is_success' => true];
        } else {
            return [
                'is_success' => false,
                'error' => 'Plan is deleted.',
            ];
        }
    }

    public function customerNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["customer_prefix"] . sprintf("%05d", $number);
    }

    public function venderNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["vender_prefix"] . sprintf("%05d", $number);
    }

    public function countUsers()
    {
        return User::where('type', '!=', 'super admin')->where('type', '!=', 'company')->where('type', '!=', 'client')->where('created_by', '=', $this->creatorId())->count();
    }

    public function countCompany()
    {
        return User::where('type', '=', 'company')->where('created_by', '=', $this->creatorId())->count();
    }

    public function countOrder()
    {
        return Order::count();
    }

    public function countplan()
    {
        return Plan::count();
    }

    public function countPaidCompany()
    {
        return User::where('type', '=', 'company')->whereNotIn(
            'plan', [
                0,
                1,
            ]
        )->where('created_by', '=', \Auth::user()->id)->count();
    }

    public function countCustomers()
    {
        return Customer::where('created_by', '=', $this->creatorId())->count();
    }

    public function countVenders()
    {
        return Vender::where('created_by', '=', $this->creatorId())->count();
    }

    public function countInvoices()
    {
        return Invoice::where('created_by', '=', $this->creatorId())->count();
    }

    public function countBills()
    {
        return Bill::where('created_by', '=', $this->creatorId())->count();
    }

    public function todayIncome()
    {
        $revenue = Revenue::where('created_by', '=', $this->creatorId())->whereRaw('Date(date) = CURDATE()')->where('created_by', \Auth::user()->creatorId())->sum('amount');
        $invoiceTotal = self::getInvoiceProductsData((date('y-m-d')));

        // $invoices     = Invoice:: select('*')->where('created_by', \Auth::user()->creatorId())->whereRAW('Date(send_date) = CURDATE()')->get();
        // $invoiceArray = array();
        // foreach($invoices as $invoice)
        // {
        //     $invoiceArray[] = $invoice->getTotal();
        // }
        $totalIncome = (!empty($revenue) ? $revenue : 0) + (!empty($invoiceTotal) ? ($invoiceTotal) : 0);

        return $totalIncome;
    }

    public function todayExpense()
    {
        $payment = Payment::where('created_by', '=', $this->creatorId())->where('created_by', \Auth::user()->creatorId())->whereRaw('Date(date) = CURDATE()')->sum('amount');

        $billTotal = self::getBillProductsData(date('y-m-d'));

        // $bills = Bill:: select('*')->where('created_by', \Auth::user()->creatorId())->whereRAW('Date(send_date) = CURDATE()')->get();

        // $billArray = array();
        // foreach($bills as $bill)
        // {
        //     $billArray[] = $bill->getTotal();
        // }

        $totalExpense = (!empty($payment) ? $payment : 0) + (!empty($billTotal) ? ($billTotal) : 0);

        return $totalExpense;
    }

    public function incomeCurrentMonth()
    {
        $currentMonth = date('m');
        $revenue = Revenue::where('created_by', '=', $this->creatorId())->whereRaw('MONTH(date) = ?', [$currentMonth])->sum('amount');
        $invoiceTotal = self::getInvoiceProductsData('', $currentMonth);

        // $invoices = Invoice:: select('*')->where('created_by', \Auth::user()->creatorId())->whereRAW('MONTH(send_date) = ?', [$currentMonth])->get();

        // $invoiceArray = array();
        // foreach($invoices as $invoice)
        // {
        //     $invoiceArray[] = $invoice->getTotal();
        // }
        $totalIncome = (!empty($revenue) ? $revenue : 0) + (!empty($invoiceTotal) ? ($invoiceTotal) : 0);

        return $totalIncome;

    }
    public function incomecat()
    {

        $currentMonth = date('m');
        $revenue = Revenue::where('created_by', '=', $this->creatorId())->whereRaw('MONTH(date) = ?', [$currentMonth])->sum('amount');

        $incomes = Revenue::selectRaw('sum(revenues.amount) as amount,MONTH(date) as month,YEAR(date) as year,category_id')->leftjoin('product_service_categories', 'revenues.category_id', '=', 'product_service_categories.id')->where('product_service_categories.type', '=', 1);

        $invoices = Invoice::select('*')->where('created_by', \Auth::user()->creatorId())->whereRAW('MONTH(send_date) = ?', [$currentMonth])->get();

        $invoiceArray = array();
        foreach ($invoices as $invoice) {
            $invoiceArray[] = $invoice->getTotal();
        }
        $totalIncome = (!empty($revenue) ? $revenue : 0) + (!empty($invoiceArray) ? array_sum($invoiceArray) : 0);

        return $totalIncome;
    }

    public function expenseCurrentMonth()
    {
        $currentMonth = date('m');

        $payment = Payment::where('created_by', '=', $this->creatorId())->whereRaw('MONTH(date) = ?', [$currentMonth])->sum('amount');
        $billTotal = self::getBillProductsData('', $currentMonth);

        // $bills     = Bill:: select('*')->where('created_by', \Auth::user()->creatorId())->whereRAW('MONTH(send_date) = ?', [$currentMonth])->get();
        // $billArray = array();
        // foreach($bills as $bill)
        // {
        //     $billArray[] = $bill->getTotal();
        // }

        $totalExpense = (!empty($payment) ? $payment : 0) + (!empty($billTotal) ? ($billTotal) : 0);

        return $totalExpense;
    }

    // public function getincExpBarChartData()
    // {
    //     $month[]          = __('January');
    //     $month[]          = __('February');
    //     $month[]          = __('March');
    //     $month[]          = __('April');
    //     $month[]          = __('May');
    //     $month[]          = __('June');
    //     $month[]          = __('July');
    //     $month[]          = __('August');
    //     $month[]          = __('September');
    //     $month[]          = __('October');
    //     $month[]          = __('November');
    //     $month[]          = __('December');
    //     $dataArr['month'] = $month;

    //     $user_id = \Auth::user()->creatorId();
    //     for($i = 1; $i <= 12; $i++)
    //     {
    //         $monthlyIncome = Revenue::selectRaw('sum(amount) amount')->where('created_by', '=', $user_id)->whereRaw('year(`date`) = ?', array(date('Y')))->whereRaw('month(`date`) = ?', $i)->first();
    //         $invoices      = Invoice:: select('*')->where('created_by', \Auth::user()->creatorId())
    //             ->whereRaw('year(`send_date`) = ?', array(date('Y')))
    //             ->whereRaw('month(`send_date`) = ?', $i)->get();

    //         $invoiceArray = array();
    //         foreach($invoices as $invoice)
    //         {
    //             $invoiceArray[] = $invoice->getTotal();
    //         }
    //         $totalIncome = (!empty($monthlyIncome) ? $monthlyIncome->amount : 0) + (!empty($invoiceArray) ? array_sum($invoiceArray) : 0);

    //         $incomeArr[] = !empty($totalIncome) ? str_replace(",", "", number_format($totalIncome, 2) ): 0;

    //         $monthlyExpense = Payment::selectRaw('sum(amount) amount')->where('created_by', '=', $this->creatorId())->whereRaw('year(`date`) = ?', array(date('Y')))->whereRaw('month(`date`) = ?', $i)->first();
    //         $bills          = Bill:: select('*')->where('created_by', \Auth::user()->creatorId())->whereRaw('year(`send_date`) = ?', array(date('Y')))->whereRaw('month(`send_date`) = ?', $i)->get();
    //         $billArray      = array();
    //         foreach($bills as $bill)
    //         {
    //             $billArray[] = $bill->getTotal();
    //         }

    //         $totalExpense = (!empty($monthlyExpense) ? $monthlyExpense->amount : 0) + (!empty($billArray) ? array_sum($billArray) : 0);

    //         $expenseArr[] = !empty($totalExpense) ? str_replace(",", "", number_format($totalExpense, 2) ): 0;
    //     }

    //     $dataArr['income']  = $incomeArr;
    //     $dataArr['expense'] = $expenseArr;

    //     return $dataArr;

    // }

    public static function getInvoiceProductsData($date = '', $month = '')
    {
        if ($month != '' && $date != '') {
            $InvoiceProducts = \DB::table('invoice_products')
                ->select('invoice_products.invoice_id as invoice',
                    \DB::raw('SUM(quantity) as total_quantity'),
                    \DB::raw('SUM(discount) as total_discount'),
                    \DB::raw('SUM(price * quantity)  as sub_total'))
                ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM invoice_products
                    LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
                    WHERE invoice_products.invoice_id = invoices.id) as tax_values')
                ->leftJoin('invoices', 'invoice_products.invoice_id', 'invoices.id')
                ->where(\DB::raw('YEAR(invoices.send_date)'), '=', $date)
                ->where(\DB::raw('MONTH(invoices.send_date)'), '=', $month)
                ->where('invoices.created_by', \Auth::user()->creatorId())
                ->groupBy('invoice')
                ->get()
                ->keyBy('invoice');
        } elseif ($date != '') {
            $InvoiceProducts = \DB::table('invoice_products')
                ->select('invoice_products.invoice_id as invoice',
                    \DB::raw('SUM(quantity) as total_quantity'),
                    \DB::raw('SUM(discount) as total_discount'),
                    \DB::raw('SUM(price * quantity)  as sub_total'))
                ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM invoice_products
                    LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
                    WHERE invoice_products.invoice_id = invoices.id) as tax_values')
                ->leftJoin('invoices', 'invoice_products.invoice_id', 'invoices.id')
                ->where(\DB::raw('(invoices.send_date)'), '=', $date)
                ->where('invoices.created_by', \Auth::user()->creatorId())
                ->groupBy('invoice')
                ->get()
                ->keyBy('invoice');
        } elseif ($month != '') {
            $InvoiceProducts = \DB::table('invoice_products')
                ->select('invoice_products.invoice_id as invoice',
                    \DB::raw('SUM(quantity) as total_quantity'),
                    \DB::raw('SUM(discount) as total_discount'),
                    \DB::raw('SUM(price * quantity)  as sub_total'))
                ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM invoice_products
                    LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
                    WHERE invoice_products.invoice_id = invoices.id) as tax_values')
                ->leftJoin('invoices', 'invoice_products.invoice_id', 'invoices.id')
                ->where(\DB::raw('MONTH(invoices.send_date)'), '=', $month)
                ->where('invoices.created_by', \Auth::user()->creatorId())
                ->groupBy('invoice')
                ->get()
                ->keyBy('invoice');
        }

        $InvoiceProducts->map(function ($invoice) {
            $invoice->total = $invoice->sub_total + $invoice->tax_values - $invoice->total_discount;
            return $invoice;
        });

        $total = 0;
        foreach ($InvoiceProducts as $invoice) {
            $total += ($invoice->total);
        }

        return $total;
    }

    public static function getBillProductsData($date = '', $month = '')
    {
        if ($month != '' && $date != '') {
            $BillProducts = \DB::table('bill_products')
                ->select('bill_products.bill_id as bill',
                    \DB::raw('SUM(bill_products.quantity) as total_quantity'),
                    \DB::raw('SUM(bill_products.discount) as total_discount'),
                    \DB::raw('SUM(bill_products.price * bill_products.quantity)  as sub_total'))
                ->selectRaw('(SELECT SUM(bill_accounts.price) FROM bill_accounts
                    WHERE bill_accounts.ref_id = bills.id) as acc_price')
                ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM bill_products
                                        LEFT JOIN taxes ON FIND_IN_SET(taxes.id, bill_products.tax) > 0
                                        WHERE bill_products.bill_id = bills.id) as tax_values')
                ->leftJoin('bills', 'bill_products.bill_id', 'bills.id')
                ->where(\DB::raw('YEAR(bills.send_date)'), '=', $date)
                ->where(\DB::raw('MONTH(bills.send_date)'), '=', $month)
                ->where('bills.created_by', \Auth::user()->creatorId())
                ->groupBy('bill')
                ->get()
                ->keyBy('bill');
        } elseif ($date != '') {
            $BillProducts = \DB::table('bill_products')
                ->select('bill_products.bill_id as bill',
                    \DB::raw('SUM(quantity) as total_quantity'),
                    \DB::raw('SUM(discount) as total_discount'),
                    \DB::raw('SUM(bill_products.price * bill_products.quantity)  as sub_total'))
                ->selectRaw('(SELECT SUM(bill_accounts.price) FROM bill_accounts
                    WHERE bill_accounts.ref_id = bills.id) as acc_price')
                ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM bill_products
                                LEFT JOIN taxes ON FIND_IN_SET(taxes.id, bill_products.tax) > 0
                                WHERE bill_products.bill_id = bills.id) as tax_values')
                ->leftJoin('bills', 'bill_products.bill_id', 'bills.id')
                ->where(\DB::raw('(bills.send_date)'), '=', $date)
                ->where('bills.created_by', \Auth::user()->creatorId())
                ->groupBy('bill')
                ->get()
                ->keyBy('bill');
        } elseif ($month != '') {
            $BillProducts = \DB::table('bill_products')
                ->select('bill_products.bill_id as bill',
                    \DB::raw('SUM(quantity) as total_quantity'),
                    \DB::raw('SUM(discount) as total_discount'),
                    \DB::raw('SUM(bill_products.price * bill_products.quantity)  as sub_total'))
                ->selectRaw('(SELECT SUM(bill_accounts.price) FROM bill_accounts
                    WHERE bill_accounts.ref_id = bills.id) as acc_price')
                ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM bill_products
                                LEFT JOIN taxes ON FIND_IN_SET(taxes.id, bill_products.tax) > 0
                                WHERE bill_products.bill_id = bills.id) as tax_values')
                ->leftJoin('bills', 'bill_products.bill_id', 'bills.id')
                ->where(\DB::raw('MONTH(bills.send_date)'), '=', $month)
                ->where('bills.created_by', \Auth::user()->creatorId())
                ->groupBy('bill')
                ->get()
                ->keyBy('bill');
        }
        $BillProducts->map(function ($bill) {
            $bill->total = $bill->sub_total + $bill->acc_price + $bill->tax_values - $bill->total_discount;
            return $bill;
        });
        $total = 0;
        foreach ($BillProducts as $bill) {
            $total += ($bill->total);
        }

        return $total;
    }

    public function getincExpBarChartData()
    {
        $month[] = __('January');
        $month[] = __('February');
        $month[] = __('March');
        $month[] = __('April');
        $month[] = __('May');
        $month[] = __('June');
        $month[] = __('July');
        $month[] = __('August');
        $month[] = __('September');
        $month[] = __('October');
        $month[] = __('November');
        $month[] = __('December');
        $dataArr['month'] = $month;

        $user_id = \Auth::user()->creatorId();
        for ($i = 1; $i <= 12; $i++) {
            $monthlyIncome = Revenue::selectRaw('sum(amount) amount')->where('created_by', '=', $user_id)->whereRaw('year(`date`) = ?', array(date('Y')))->whereRaw('month(`date`) = ?', $i)->first();
            $invoiceTotal = self::getInvoiceProductsData((date('Y')), $i);

            $totalIncome = (!empty($monthlyIncome) ? $monthlyIncome->amount : 0) + (!empty($invoiceTotal) ? ($invoiceTotal) : 0);

            $incomeArr[] = !empty($totalIncome) ? str_replace(",", "", number_format($totalIncome, 2)) : 0;

            $monthlyExpense = Payment::selectRaw('sum(amount) amount')->where('created_by', '=', $this->creatorId())->whereRaw('year(`date`) = ?', array(date('Y')))->whereRaw('month(`date`) = ?', $i)->first();
            $billTotal = self::getBillProductsData((date('Y')), $i);

            $totalExpense = (!empty($monthlyExpense) ? $monthlyExpense->amount : 0) + (!empty($billTotal) ? ($billTotal) : 0);

            $expenseArr[] = !empty($totalExpense) ? str_replace(",", "", number_format($totalExpense, 2)) : 0;
        }

        $dataArr['income'] = $incomeArr;
        $dataArr['expense'] = $expenseArr;

        return $dataArr;

    }

    // public function getIncExpLineChartDate()
    // {
    //     $usr           = \Auth::user();
    //     $m             = date("m");
    //     $de            = date("d");
    //     $y             = date("Y");
    //     $format        = 'Y-m-d';
    //     $arrDate       = [];
    //     $arrDateFormat = [];

    //     for($i = 0; $i <= 15 - 1; $i++)
    //     {
    //         $date = date($format, mktime(0, 0, 0, $m, ($de - $i), $y));

    //         $arrDay[]        = date('D', mktime(0, 0, 0, $m, ($de - $i), $y));
    //         $arrDate[]       = $date;
    //         $arrDateFormat[] = date("d-M", strtotime($date));;
    //     }
    //     $dataArr['day'] = $arrDateFormat;
    //     for($i = 0; $i < count($arrDate); $i++)
    //     {
    //         $dayIncome = Revenue::selectRaw('sum(amount) amount')->where('created_by', \Auth::user()->creatorId())->whereRaw('date = ?', $arrDate[$i])->first();

    //         $invoices     = Invoice:: select('*')->where('created_by', \Auth::user()->creatorId())->whereRAW('send_date = ?', $arrDate[$i])->get();
    //         $invoiceArray = array();
    //         foreach($invoices as $invoice)
    //         {
    //             $invoiceArray[] = $invoice->getTotal();
    //         }

    //         $incomeAmount = (!empty($dayIncome->amount) ? $dayIncome->amount : 0) + (!empty($invoiceArray) ? array_sum($invoiceArray) : 0);
    //         $incomeArr[]  = str_replace(",", "", number_format($incomeAmount, 2));

    //         $dayExpense = Payment::selectRaw('sum(amount) amount')->where('created_by', \Auth::user()->creatorId())->whereRaw('date = ?', $arrDate[$i])->first();

    //         $bills     = Bill:: select('*')->where('created_by', \Auth::user()->creatorId())->whereRAW('send_date = ?', $arrDate[$i])->get();
    //         $billArray = array();
    //         foreach($bills as $bill)
    //         {
    //             $billArray[] = $bill->getTotal();
    //         }
    //         $expenseAmount = (!empty($dayExpense->amount) ? $dayExpense->amount : 0) + (!empty($billArray) ? array_sum($billArray) : 0);
    //         $expenseArr[]  = str_replace(",", "", number_format($expenseAmount, 2));
    //     }

    //     $dataArr['income']  = $incomeArr;
    //     $dataArr['expense'] = $expenseArr;

    //     return $dataArr;
    // }

    public function getIncExpLineChartDate()
    {
        $usr = \Auth::user();
        $m = date("m");
        $de = date("d");
        $y = date("Y");
        $format = 'Y-m-d';
        $arrDate = [];
        $arrDateFormat = [];

        for ($i = 0; $i <= 15 - 1; $i++) {
            $date = date($format, mktime(0, 0, 0, $m, ($de - $i), $y));

            $arrDay[] = date('D', mktime(0, 0, 0, $m, ($de - $i), $y));
            $arrDate[] = $date;
            $arrDateFormat[] = date("d-M", strtotime($date));
        }
        $dataArr['day'] = $arrDateFormat;
        for ($i = 0; $i < count($arrDate); $i++) {
            $dayIncome = Revenue::selectRaw('sum(amount) amount')->where('created_by', \Auth::user()->creatorId())->whereRaw('date = ?', $arrDate[$i])->first();

            $invoiceTotal = self::getInvoiceProductsData($arrDate[$i]);

            $incomeAmount = (!empty($dayIncome->amount) ? $dayIncome->amount : 0) + (!empty($invoiceTotal) ? ($invoiceTotal) : 0);
            $incomeArr[] = str_replace(",", "", number_format($incomeAmount, 2));

            $dayExpense = Payment::selectRaw('sum(amount) amount')->where('created_by', \Auth::user()->creatorId())->whereRaw('date = ?', $arrDate[$i])->first();

            $billTotal = self::getBillProductsData($arrDate[$i]);

            $expenseAmount = (!empty($dayExpense->amount) ? $dayExpense->amount : 0) + (!empty($billTotal) ? ($billTotal) : 0);
            $expenseArr[] = str_replace(",", "", number_format($expenseAmount, 2));
        }

        $dataArr['income'] = $incomeArr;
        $dataArr['expense'] = $expenseArr;

        return $dataArr;
    }
    public function totalCompanyUser($id)
    {
        return User::where('created_by', '=', $id)->count();
    }

    public function totalCompanyCustomer($id)
    {
        return Customer::where('created_by', '=', $id)->count();
    }

    public function totalCompanyVender($id)
    {
        return Vender::where('created_by', '=', $id)->count();
    }

    public function planPrice()
    {
        $user = \Auth::user();
        if ($user->type == 'super admin') {
            $userId = $user->id;
        } else {
            $userId = $user->created_by;
        }

        return DB::table('settings')->where('created_by', '=', $userId)->get()->pluck('value', 'name');

    }

    public function currentPlan()
    {
        return $this->hasOne('App\Models\Plan', 'id', 'plan');
    }

    public function invoicesData($start, $current)
    {
        $InvoiceProducts = Invoice::select('invoices.invoice_id as invoice')
            ->selectRaw('sum((invoice_products.price * invoice_products.quantity) - invoice_products.discount) as price')
            ->selectRaw('(SELECT SUM(credit_notes.amount) FROM credit_notes WHERE credit_notes.invoice = invoices.id) as credit_price')
            ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM invoice_products
             LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
             WHERE invoice_products.invoice_id = invoices.id) as total_tax')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', 'invoices.id')
            ->where('issue_date', '>=', $start)->where('issue_date', '<=', $current)
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->groupBy('invoice')
            ->get()
            ->keyBy('invoice')
            ->toArray();


        $invoicepayment = Invoice::select('invoices.invoice_id as invoice')
            ->selectRaw('sum((invoice_payments.amount)) as pay_price')
            ->leftJoin('invoice_payments', 'invoice_payments.invoice_id', 'invoices.id')
            ->where('issue_date', '>=', $start)->where('issue_date', '<=', $current)
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->groupBy('invoice')
            ->get()
            ->keyBy('invoice')
            ->toArray();

        $mergedArray = [];

        foreach ($InvoiceProducts as $key => $value) {
            if (isset($invoicepayment[$key])) {
                $mergedArray[$key] = array_merge($value, $invoicepayment[$key]);
            }
        }

        $invoiceTotal = 0;
        $invoicePaid = 0;
        $invoiceDue = 0;
        $invoiceData = [];

        foreach ($mergedArray as $invoice) {
            $invoiceTotal += $invoice['price'] + $invoice['total_tax'];
            $invoicePaid += $invoice['pay_price'] + $invoice['credit_price'];
            $invoiceDue += ($invoice['price'] + $invoice['total_tax']) - $invoice['credit_price'] - $invoice['pay_price'];
        }

        $invoiceData = [
            "invoiceTotal" => $invoiceTotal,
            "invoicePaid" => $invoicePaid,
            'invoiceDue' => $invoiceDue,
        ];

        return $invoiceData;
    }

    public function billsData($start, $current)
    {
        $billProducts = Bill::select('bills.bill_id as bill')
            ->selectRaw('sum((bill_products.price * bill_products.quantity) - bill_products.discount) as price')
            ->selectRaw('(SELECT SUM(debit_notes.amount) FROM debit_notes
             WHERE debit_notes.bill = bills.id) as debit_price')
            ->selectRaw('(SELECT SUM(bill_accounts.price) FROM bill_accounts
             WHERE bill_accounts.ref_id = bills.id) as acc_price')
            ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM bill_products
             LEFT JOIN taxes ON FIND_IN_SET(taxes.id, bill_products.tax) > 0
             WHERE bill_products.bill_id = bills.id) as total_tax')
            ->leftJoin('bill_products', 'bill_products.bill_id', 'bills.id')
            ->where('bill_date', '>=', $start)->where('bill_date', '<=', $current)
            ->where('bills.created_by', \Auth::user()->creatorId())
            ->where('bills.type', 'Bill')
            ->groupBy('bill')
            ->get()
            ->keyBy('bill')
            ->toArray();


        $billPayment = Bill::select('bills.bill_id as bill')
            ->selectRaw('sum((bill_payments.amount)) as pay_price')
            ->leftJoin('bill_payments', 'bill_payments.bill_id', 'bills.id')
            ->where('bill_date', '>=', $start)->where('bill_date', '<=', $current)
            ->where('bills.created_by', \Auth::user()->creatorId())
            ->where('bills.type', 'Bill')
            ->groupBy('bill')
            ->get()
            ->keyBy('bill')
            ->toArray();

        $mergedArray = [];

        foreach ($billProducts as $key => $value) {
            if (isset($billPayment[$key])) {
                $mergedArray[$key] = array_merge($value, $billPayment[$key]);
            } else {
                $mergedArray[$key] = ($value);
            }
        }

        $billTotal = 0;
        $billPaid = 0;
        $billDue = 0;
        $billData = [];

        foreach ($mergedArray as $bill) {
            $billTotal += $bill['price'] + $bill['total_tax'] + $bill['acc_price'];
            $billPaid += $bill['pay_price'] + $bill['debit_price'];
            $billDue += ($bill['price'] + $bill['total_tax'] + $bill['acc_price']) - $bill['pay_price'] - $bill['debit_price'];
        }

        $billData = [
            "billTotal" => $billTotal,
            "billPaid" => $billPaid,
            'billDue' => $billDue,
        ];

        return $billData;
    }

    public function expenseData($start, $current)
    {
        $billProducts = Bill::select('bills.bill_id as bill')
            ->selectRaw('sum((bill_products.price * bill_products.quantity) - bill_products.discount) as price')
            ->selectRaw('(SELECT SUM(debit_notes.amount) FROM debit_notes
             WHERE debit_notes.bill = bills.id) as debit_price')
            ->selectRaw('(SELECT SUM(bill_accounts.price) FROM bill_accounts
             WHERE bill_accounts.ref_id = bills.id) as acc_price')
            ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM bill_products
             LEFT JOIN taxes ON FIND_IN_SET(taxes.id, bill_products.tax) > 0
             WHERE bill_products.bill_id = bills.id) as total_tax')
            ->leftJoin('bill_products', 'bill_products.bill_id', 'bills.id')
            ->where('bill_date', '>=', $start)->where('bill_date', '<=', $current)
            ->where('bills.created_by', \Auth::user()->creatorId())
            ->where('bills.type', 'Expense')
            ->groupBy('bill')
            ->get()
            ->keyBy('bill')
            ->toArray();


        $billPayment = Bill::select('bills.bill_id as bill')
            ->selectRaw('sum((bill_payments.amount)) as pay_price')
            ->leftJoin('bill_payments', 'bill_payments.bill_id', 'bills.id')
            ->where('bill_date', '>=', $start)->where('bill_date', '<=', $current)
            ->where('bills.created_by', \Auth::user()->creatorId())
            ->where('bills.type', 'Expense')
            ->groupBy('bill')
            ->get()
            ->keyBy('bill')
            ->toArray();

        $mergedArray = [];

        foreach ($billProducts as $key => $value) {
            if (isset($billPayment[$key])) {
                $mergedArray[$key] = array_merge($value, $billPayment[$key]);
            } else {
                $mergedArray[$key] = ($value);
            }
        }

        $billTotal = 0;
        $billPaid = 0;
        $billDue = 0;
        $billData = [];

        foreach ($mergedArray as $bill) {
            $billTotal += $bill['price'] + $bill['total_tax'] + $bill['acc_price'];
            $billPaid += $bill['pay_price'] + $bill['debit_price'];
            $billDue += ($bill['price'] + $bill['total_tax'] + $bill['acc_price']) - $bill['pay_price'] - $bill['debit_price'];
        }

        $billData = [
            "billTotal" => $billTotal,
            "billPaid" => $billPaid,
            'billDue' => $billDue,
        ];

        return $billData;
    }
    public function weeklyInvoice()
    {
        $staticstart = date('Y-m-d', strtotime('last Week'));
        $currentDate = date('Y-m-d');
        $invoices = self::invoicesData($staticstart, $currentDate);

        $invoiceDetail['invoiceTotal'] = $invoices['invoiceTotal'];
        $invoiceDetail['invoicePaid'] = $invoices['invoicePaid'];
        $invoiceDetail['invoiceDue'] = $invoices['invoiceDue'];

        return $invoiceDetail;
    }

    public function monthlyInvoice()
    {
        $staticstart = date('Y-m-d', strtotime('last Month'));
        $currentDate = date('Y-m-d');
        $invoices = self::invoicesData($staticstart, $currentDate);

        $invoiceDetail['invoiceTotal'] = $invoices['invoiceTotal'];
        $invoiceDetail['invoicePaid'] = $invoices['invoicePaid'];
        $invoiceDetail['invoiceDue'] = $invoices['invoiceDue'];

        return $invoiceDetail;
    }

    // public function weeklyBill()
    // {
    //     $staticstart = date('Y-m-d', strtotime('last Week'));
    //     $currentDate = date('Y-m-d');
    //     $bills = $this->bills()->where('created_by', \Auth::user()->creatorId())->where('bill_date', '>=', $staticstart)->where('bill_date', '<=', $currentDate)->get();
    //     $billTotal = 0;
    //     $billPaid = 0;
    //     $billDue = 0;
    //     foreach ($bills as $bill) {
    //         $billTotal += $bill->getTotal();
    //         $billPaid += ($bill->getTotal() - $bill->getDue());
    //         $billDue += $bill->getDue();
    //     }

    //     $billDetail['billTotal'] = $billTotal;
    //     $billDetail['billPaid'] = $billPaid;
    //     $billDetail['billDue'] = $billDue;

    //     return $billDetail;
    // }

    public function weeklyBill()
    {
        $staticstart = date('Y-m-d', strtotime('last Week'));
        $currentDate = date('Y-m-d');
        $bills = self::billsData($staticstart, $currentDate);
        $expense = self::expenseData($staticstart, $currentDate);

        $billDetail['billTotal'] = $bills['billTotal'] + $expense['billTotal'];
        $billDetail['billPaid'] = $bills['billPaid'] + $expense['billPaid'];
        $billDetail['billDue'] = $bills['billDue'] + $expense['billDue'];
        return $billDetail;
    }

    public function monthlyBill()
    {
        $staticstart = date('Y-m-d', strtotime('last Month'));
        $currentDate = date('Y-m-d');
        $bills = self::billsData($staticstart, $currentDate);
        $expense = self::expenseData($staticstart, $currentDate);

        $billDetail['billTotal'] = $bills['billTotal'] + $expense['billTotal'];
        $billDetail['billPaid'] = $bills['billPaid'] + $expense['billPaid'];
        $billDetail['billDue'] = $bills['billDue'] + $expense['billDue'];
        return $billDetail;
    }

    public function clientEstimations()
    {
        return $this->hasMany('App\Models\Estimation', 'client_id', 'id');
    }

    public function clientContracts()
    {
        return $this->hasMany('App\Models\Contract', 'client_name', 'id');
    }

    public function deals()
    {
        return $this->belongsToMany('App\Models\Deal', 'user_deals', 'user_id', 'deal_id');
    }

    public function leads()
    {
        return $this->belongsToMany('App\Models\Lead', 'user_leads', 'user_id', 'lead_id');
    }

    public function clientDeals()
    {
        return $this->belongsToMany('App\Models\Deal', 'client_deals', 'client_id', 'deal_id');
    }

    public function getBranch($branch_id)
    {
        $branch = Branch::where('id', '=', $branch_id)->first();

        return $branch;
    }

    public function getDepartment($department_id)
    {
        $department = Department::where('id', '=', $department_id)->first();

        return $department;
    }

    public function getDesignation($designation_id)
    {
        $designation = Designation::where('id', '=', $designation_id)->first();

        return $designation;
    }

    public function getEmployee($employee)
    {
        $employee = Employee::where('id', '=', $employee)->first();

        return $employee;
    }

    public function getLeaveType($leave_type)
    {
        $leavetype = LeaveType::where('id', '=', $leave_type)->first();

        return $leavetype;
    }

    public function projects()
    {
        return $this->belongsToMany('App\Models\Project', 'project_users', 'user_id', 'project_id')->withTimestamps();
    }

    // check project is shared or not
    public function checkProject($project_id)
    {
        $user_projects = $this->projects->pluck('project_id')->toArray();
        if (array_key_exists($project_id, $user_projects)) {
            $projectstatus = $user_projects[$project_id] == 'owner' ? 'Owner' : 'Shared';
        }
        return 'Owner';
    }

    // Make new attribute for directly get image
    public function getImgImageAttribute()
    {
        $userDetail = Employee::where('user_id', $this->id)->first();
        if (!empty($userDetail)) {
            if (!empty($userDetail->avatar)) {
                return asset(\Storage::url($userDetail->avatar));
            } else {
                return asset(\Storage::url('avatar.png'));
            }
        } else {
            return asset(\Storage::url('avatar.png'));
        }
    }

    // Get task users
    public function tasks()
    {
        if (\Auth::check()) {
            $user = Auth::user();
        } else {
            $user = User::find($this->id);
        }
        if ($user->type == 'company') {
            return ProjectTask::where('created_by', $user->creatorId())->get();
        } else {
            return ProjectTask::whereRaw("find_in_set('" . $this->id . "',assign_to)")->get();
        }
    }

    public function bugNumberFormat($number)
    {
        $settings = Utility::settings();

        return $settings["bug_prefix"] . sprintf("%05d", $number);
    }

    // Get User's Contact
    public function contacts()
    {
        return $this->hasMany('App\Models\UserContact', 'parent_id', 'id');
    }

    public function todo()
    {
        return $this->hasMany('App\Models\UserToDo', 'user_id', 'id');
    }

    public function employee()
    {
        return $this->hasOne('App\Models\Employee', 'user_id', 'id');
    }

    public function total_lead()
    {
        if (\Auth::user()->type == 'company') {
            return Lead::where('created_by', '=', $this->creatorId())->count();
        } elseif (\Auth::user()->type == 'client') {
            return Lead::where('client', '=', $this->authId())->count();
        } else {
            return Lead::where('owner', '=', $this->authId())->count();
        }
    }

    public function last_projectstage()
    {
        return TaskStage::where('created_by', '=', $this->creatorId())->orderBy('order', 'DESC')->first();
    }

    public function user_project()
    {
        if (\Auth::user()->type != 'client') {
            return $this->belongsToMany('App\Models\Project', 'project_users', 'user_id', 'project_id')->count();
        } else {
            return Project::where('client_id', '=', $this->authId())->count();
        }
    }

    public function created_total_project_task()
    {
        if (\Auth::user()->type == 'company') {
            return ProjectTask::join('projects', 'projects.id', '=', 'project_tasks.project_id')->where('projects.created_by', '=', $this->creatorId())->count();
        } elseif (\Auth::user()->type == 'client') {
            return ProjectTask::join('projects', 'projects.id', '=', 'project_tasks.project_id')->where('projects.client_id', '=', $this->authId())->count();
        } else {
            return ProjectTask::select('project_tasks.*', 'project_users.id as up_id')->join('project_users', 'project_users.project_id', '=', 'project_tasks.project_id')->where('project_users.user_id', '=', $this->authId())->count();
        }

    }

    public function project_complete_task($project_last_stage)
    {
        if (\Auth::user()->type == 'company') {
            return ProjectTask::join('projects', 'projects.id', '=', 'project_tasks.project_id')->where('projects.created_by', '=', $this->creatorId())->where('project_tasks.stage_id', '=', $project_last_stage)->count();
        } elseif (\Auth::user()->type == 'client') {
            $user_projects = Project::where('client_id', \Auth::user()->id)->pluck('id', 'id')->toArray();

            return ProjectTask::whereIn('project_id', $user_projects)->join('projects', 'projects.id', '=', 'project_tasks.project_id')->where('project_tasks.stage_id', '=', $project_last_stage)->count();
        } else {
            return ProjectTask::select('project_tasks.*', 'project_users.id as up_id')->join('project_users', 'project_users.project_id', '=', 'project_tasks.project_id')->where('project_users.user_id', '=', $this->authId())->where('project_tasks.stage_id', '=', $project_last_stage)->count();
        }
    }

    public function created_top_due_task()
    {
        if (\Auth::user()->type == 'company') {
            return ProjectTask::select('projects.*', 'project_tasks.id as task_id', 'project_tasks.name', 'project_tasks.end_date as task_due_date', 'project_tasks.assign_to', 'projectstages.name as stage_name')->join('projects', 'projects.id', '=', 'project_tasks.project_id')->join('projectstages', 'project_tasks.stage_id', '=', 'projectstages.id')->where('projects.created_by', '=', \Auth::user()->creatorId())->where('project_tasks.end_date', '>', date('Y-m-d'))->limit(5)->orderBy('task_due_date', 'ASC')->get();
        } elseif (\Auth::user()->type == 'client') {
            $user_projects = Project::where('client_id', \Auth::user()->id)->pluck('id', 'id')->toArray();

            return ProjectTask::whereIn('project_id', $user_projects)->join('projects', 'projects.id', '=', 'project_tasks.project_id')->where('project_tasks.end_date', '>', date('Y-m-d'))->limit(5)->get();
        } else {
            return ProjectTask::select('project_tasks.*', 'project_tasks.end_date as task_due_date', 'project_users.id as up_id', 'projects.project_name as project_name', 'projectstages.name as stage_name')->join('project_users', 'project_users.project_id', '=', 'project_tasks.project_id')->join('projects', 'project_users.project_id', '=', 'projects.id')->join('projectstages', 'project_tasks.stage_id', '=', 'projectstages.id')->where('project_users.user_id', '=', $this->authId())->where('project_tasks.end_date', '>', date('Y-m-d'))->limit(5)->orderBy(
                'project_tasks.end_date', 'ASC'
            )->get();
        }
    }

    public function show_dashboard()
    {
        $user_type = \Auth::user()->type;

        if ($user_type == 'company' || $user_type == 'super admin') {
            $user = Auth::user();
        } else {
            $user = User::where('id', \Auth::user()->created_by)->first();
        }

        return $user->plan;
        // return !empty($user->plan)?Plan::find($user->plan)->crm:'';
    }

    public static function show_crm()
    {
        $user_type = \Auth::user()->type;

        if ($user_type == 'company' || $user_type == 'super admin') {
            $user = User::where('id', \Auth::user()->id)->first();

        } else {
            $user = User::where('id', \Auth::user()->created_by)->first();
        }

        return !empty($user->plan) ? Plan::find($user->plan)->crm : '';
    }

    public static function show_hrm()
    {
        $user_type = \Auth::user()->type;
        if ($user_type == 'company' || $user_type == 'super admin') {
            $user = User::where('id', \Auth::user()->id)->first();
        } else {
            $user = User::where('id', \Auth::user()->created_by)->first();
        }

        return !empty($user->plan) ? Plan::find($user->plan)->hrm : '';

    }

    public static function show_account()
    {
        $user_type = \Auth::user()->type;
        if ($user_type == 'company' || $user_type == 'super admin') {
            $user = User::where('id', \Auth::user()->id)->first();
        } else {
            $user = User::where('id', \Auth::user()->created_by)->first();
        }

        return !empty($user->plan) ? Plan::find($user->plan)->account : '';
    }

    public static function show_project()
    {
        $user_type = \Auth::user()->type;
        if ($user_type == 'company' || $user_type == 'super admin') {
            $user = User::where('id', \Auth::user()->id)->first();
        } else {
            $user = User::where('id', \Auth::user()->created_by)->first();
        }
        return !empty($user->plan) ? Plan::find($user->plan)->project : '';

    }

    public static function show_pos()
    {
        $user_type = \Auth::user()->type;
        if ($user_type == 'company' || $user_type == 'super admin') {
            $user = User::where('id', \Auth::user()->id)->first();
        } else {
            $user = User::where('id', \Auth::user()->created_by)->first();
        }
        return !empty($user->plan) ? Plan::find($user->plan)->pos : '';

    }

    public function clientProjects()
    {
        return $this->hasMany('App\Models\Project', 'client_id', 'id');
    }

    public function isUser()
    {

        return $this->type === 'user' ? 1 : 0;
    }

    public function isClient()
    {
        return $this->type == 'client' ? 1 : 0;
    }

    // For Email template Module
    public static function defaultEmail()
    {
        // Email Template
        $emailTemplate = [
            'New User',
            'New Client',
            'New Support Ticket',
            'Lead Assigned',
            'Deal Assigned',
            'New Award',
            'Customer Invoice Sent',
            'New Invoice Payment',
            'New Payment Reminder',
            'New Bill Payment',
            'Bill Resent',
            'Proposal Sent',
            'Complaint Resent',
            'Leave Action Sent',
            'Payslip Sent',
            'Promotion Sent',
            'Resignation Sent',
            'Termination Sent',
            'Transfer Sent',
            'Trip Sent',
            'Vender Bill Sent',
            'Warning Sent',
            'New Contract',
            'New Project',
            'New Task',
            'Task Status Updated',
            'New Leave',
            'Project Assign Member',
        ];

        foreach ($emailTemplate as $eTemp) {
            $emailTemp = EmailTemplate::where('name', $eTemp)->count();
            if ($emailTemp == 0) {
                EmailTemplate::create(
                    [
                        'name' => $eTemp,
                        'from' => env('APP_NAME'),
                        'slug' => strtolower(str_replace(' ', '_', $eTemp)),
                        'created_by' => 1,
                    ]
                );
            }
        }

        $defaultTemplate = [
            'new_user' => [
                'subject' => 'New User',
                'lang' => [
                    'ar' => '<p>مرحبا،&nbsp;<br>مرحبا بك في {app_name}.</p><p><b>البريد الإلكتروني </b>: {email}<br><b>كلمه السر</b> : {password}</p><p>{app_url}</p><p>شكر،<br>{app_name}</p>',
                    'zh' => '<p>您好，<br>欢迎使用 {app_name}。</p><p><b>电子邮件 </b>：{email}<br><b>密码</b>：{password} </p><p>{app_url}</p><p>谢谢，<br>{app_name}</p>',
                    'da' => '<p>Hej,&nbsp;<br>Velkommen til {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Adgangskode</b> : {password}</p><p>{app_url}</p><p>Tak,<br>{app_name}</p>',
                    'de' => '<p>Hallo,&nbsp;<br>Willkommen zu {app_name}.</p><p><b>Email </b>: {email}<br><b>Passwort</b> : {password}</p><p>{app_url}</p><p>Vielen Dank,<br>{app_name}</p>',
                    'en' => '<p>Hello,&nbsp;<br>Welcome to {app_name}.</p><p><b>Email </b>: {email}<br><b>Password</b> : {password}</p><p>{app_url}</p><p>Thanks,<br>{app_name}</p>',
                    'es' => '<p>Hola,&nbsp;<br>Bienvenido a {app_name}.</p><p><b>Correo electrónico </b>: {email}<br><b>Contraseña</b> : {password}</p><p>{app_url}</p><p>Gracias,<br>{app_name}</p>',
                    'fr' => '<p>Bonjour,&nbsp;<br>Bienvenue à {app_name}.</p><p><b>Email </b>: {email}<br><b>Mot de passe</b> : {password}</p><p>{app_url}</p><p>Merci,<br>{app_name}</p>',
                    'he' => '<p>שלום,&nbsp;<br>ברוכים הבאים אל {app_name}.</p><p><b>דוא"ל </b>: {email}<br><b>סיסמה</b> : {password} </p><p>{app_url}</p><p>תודה,<br>{app_name}</p>',
                    'it' => '<p>Ciao,&nbsp;<br>Benvenuto a {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Parola d\'ordine</b> : {password}</p><p>{app_url}</p><p>Grazie,<br>{app_name}</p>',
                    'ja' => '<p>こんにちは、&nbsp;<br>へようこそ {app_name}.</p><p><b>Eメール </b>: {email}<br><b>パスワード</b> : {password}</p><p>{app_url}</p><p>おかげで、<br>{app_name}</p>',
                    'nl' => '<p>Hallo,&nbsp;<br>Welkom bij {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Wachtwoord</b> : {password}</p><p>{app_url}</p><p>Bedankt,<br>{app_name}</p>',
                    'pl' => '<p>Witaj,&nbsp;<br>Witamy w {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Hasło</b> : {password}</p><p>{app_url}</p><p>Dzięki,<br>{app_name}</p>',
                    'ru' => '<p>Привет,&nbsp;<br>Добро пожаловать в {app_name}.</p><p><b>Электронное письмо </b>: {email}<br><b>пароль</b> : {password}</p><p>{app_url}</p><p>Спасибо,<br>{app_name}</p>',
                    'pt' => '<p>Olá,<br>Bem-vindo ao {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Senha</b> : {password}</p><p>{app_url}</p><p>Obrigada,<br>{app_name}</p>',
                    'tr' => '<p>Merhaba,&nbsp;<br>{app_name} e hoş geldiniz.</p><p><b>E-posta </b>: {email}<br><b>Şifre</b> : {şifre} </p><p>{app_url}</p><p>Teşekkürler,<br>{app_name}</p>',
                    'pt-br' => '<p>Olá,<br>Bem-vindo ao {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Senha</b> : {password}</p><p>{app_url}</p><p>Obrigada,<br>{app_name}</p>',

                ],
            ],
            'new_client' => [
                'subject' => 'New Client',
                'lang' => [
                    'ar' => '<p>مرحبا { client_name } ، </p><p>أنت الآن Client ..</p><p>البريد الالكتروني : { client_email } </p><p>كلمة السرية : { client_password }</p><p>{ app_url }</p><p>شكرا</p><p>{ app_name }</p>',
                    'zh' => '<p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">你好 {client_name},</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">您现在是客户..</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><b data-stringify-type="bold" style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">电子邮件&nbsp;</b><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">: {client_email}</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><b data-stringify-type="bold" style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">密码</b><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">&nbsp;: {client_password}</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">{app_url}</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">谢谢,</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">{app_name}</span><br></p>',
                    'es' => '<p>Hola {nombre_cliente},</p><p> ahora es Cliente ..</p><p>Correo electrónico: {client_email}</p><p> Contraseña: {client_password}</p><p>{app_url}</p><p>Gracias,</p><p>{app_name}</p>',
                    'da' => '<p>Hej { client_name },</p><p> Du er nu klient ..</p><p>E-mail: { client_email } </p><p>Password: { client_password }</p><p>{ app_url }</p><p>Tak.</p><p>{ app_name }</p>',
                    'de' => '<p>Hallo {client_name}, </p><p>Sie sind jetzt Client ..</p><p>E-Mail: {client_email}</p><p> Kennwort: {client_password}</p><p>{app_url}</p><p>Danke,</p><p>{Anwendungsname}</p>',
                    'en' => '<p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">Hello {client_name},</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">You are now Client..</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><b data-stringify-type="bold" style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">Email&nbsp;</b><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">: {client_email}</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><b data-stringify-type="bold" style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">Password</b><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">&nbsp;: {client_password}</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">{app_url}</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">Thanks,</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">{app_name}</span><br></p>',
                    'es' => '<p>Hola {nombre_cliente},</p><p> ahora es Cliente ..</p><p>Correo electrónico: {client_email}</p><p> Contraseña: {client_password}</p><p>{app_url}</p><p>Gracias,</p><p>{app_name}</p>',
                    'fr' => '<p>Bonjour { client_name }, </p><p>Vous êtes maintenant Client ..</p><p>Adresse électronique: { client_email } </p><p>Mot de passe: { client_password }</p><p>{ app_url }</p><p>Merci,</p><p>{ app_name }</p>',
                    'he' => '<p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">הלו {client_name},</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span סגנון = " צבע: rgb (29, 28, 29); משפחת פונט: Sמחסור-Lato, ססור-שברים, appleלוגו, appleLogo, sans-serif; גודל גופן: 15px; גלגולי גופן: 15px; צבע-כללי רקע: rgb (248, 248, 248, 248); אתה עכשיו לקוח ...</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><b data-stringify-type = "מודגש" סגנון = "מודגש", צבע: צבע: rgb (29, 28, 29, 29); משפחת פונט: Slack-Lato, Slack-Fractions, AppleLogo, sans-serif; גודל גופן: 15px; גופנים-גלידות: צבע רקע: rgb: rgb (248, 248, 248, 248); #nbsp;</b><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">: {client_ייל}</span><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">: {client_ייל}</span><br לסגנון = " תיבה: צבע: צבע: צבע: rgb (29, 28, 29); משפחה: Slack-Lato, Slack-Fractions, appleLogo, appleLogo, sans-serif; גודל גופן: 15px; גופן-יוני ליגריות: rgb-צבע רקע: rgb (248, 248, 248, 248);<b data-stringify-type="bold" style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">סיסמה</b><span סגנון = " צבע: rgb (29, 28, 29); Slack-Lato, Slack-Lato, Slack-Fractions, appleLogo, appleLogo, applelogo, appleLogo, pleLogo, applelogo, applelogo, appleLogo, sans-serif; גופן = 15px; #15px; צבע רקע: rgb (248, 248, 248); &nbsp;: {client_password}</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">{app_url}</span><br לסגנון = " תיבה-גודל: צבע: צבע: rgb (29, 28, 29); משפחת פונט: Slack-Lato, Slack-Fractions, appleLogo, appleלוגו, זנות-גודל גופן: 15px; צבע רקע: 15px; צבע רקע: rgb: rgb (248, 248, 248, 248);<span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">תודה,</span><br סטייל = " תיבה: rgb: צבע: rgb (29, 28, 29); משפחת פונט: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; גופן-גודל גופן: 15px; גופן-variant-קשירה: צבע רקע משותף: rgb (248, 248, 248);<span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">{app_name}</span><br></p>',
                    'it' => '<p>Hello {client_name}, </p><p>Tu ora sei Client ..</p><p>Email: {client_email} </p><p>Password: {client_password}</p><p>{app_url}</p><p>Grazie,</p><p>{app_name}</p>',
                    'ja' => '<p>こんにちは {client_name} 、</p><p>お客様になりました。</p><p>E メール : {client_email}</p><p> パスワード : {client_password}</p><p>{app_url}</p><p>ありがとう。</p><p>{app_name}</p>',
                    'nl' => '<p>Hallo { client_name }, </p><p>U bent nu Client ..</p><p>E-mail: { client_email } </p><p>Wachtwoord: { client_password }</p><p>{ app_url }</p><p>Bedankt.</p><p>{ app_name }</p>',
                    'pl' => '<p>Witaj {client_name }, </p><p>jesteś teraz Client ..</p><p>E-mail: {client_email }</p><p> Hasło: {client_password }</p><p>{app_url }</p><p>Dziękuję,</p><p>{app_name }</p>',
                    'ru' => '<p>Hello { client_name }, </p><p>Вы теперь клиент ..</p><p>Адрес электронной почты: { client_email } </p><p>Пароль: { client_password }</p><p>{ app_url }</p><p>Спасибо.</p><p>{ app_name }</p><p>Olá {client_name}, </p><p>Você agora é Client ..</p><p>E-mail: {client_email} </p><p>Senha: {client_password}</p><p>{app_url}</p><p>Obrigado,</p><p>{app_name}</p>',
                    'pt' => '<p>Olá {client_name}, </p><p>Você agora é Client ..</p><p>E-mail: {client_email} </p><p>Senha: {client_password}</p><p>{app_url}</p><p>Obrigado,</p><p>{app_name}</p>',
                    'tr' => '<p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">Merhaba { client_name },</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span style = " color: rgb (29, 28, 29); font-family: Sack-Lato, Slack-Fragactions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb (248, 248, 248); "> Rgb (248, 248, 248); "> Artık Müşteri ..</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><b data-stringify-type = "bold" style = " box-boyutlandırma: devral; renk: rgb (29, 28, 29); font-family: Seksime-Lato, Seksiks-Frarits, appleLogo, sans-serif; font-size: 15px; font-variant-color: common-ligatures; background-color: rgb (248, 248, 248); "> E-posta &nbsp;</b><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">: { client_email }</span><br style = " box-boyutlandırma: devral; renk: rgb (29, 28, 29); font-family: Seksime-Lato, Sack-Frations, appleLogo, sans-serif; font-size: 15px; font-variant-ligatürler: common-ligatures; background-color: rgb (248, 248, 248); "><b data-stringify-type="bold" style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">Parola</b><span style = " color: rgb (29, 28, 29); font-family: Seksime-lato, Seksi-Frations, appleLogo, sans-serif; font-size: 15px; font-variant-ligatür: common-ligature; background-color: rgb (248, 248, 248); "> &nbsp;: { client_password }</span><br style="box-sizing: inherit; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);"><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">{ app_url }</span><br style = " box-boyutlandırma: devral; renk: rgb (29, 28, 29); font-family: Seksi-Lato, sack-Frations, appleLogo, sans-serif; font-size: 15px; font-variant-ligatürler: common-ligatures; background-color: rgb (248, 248, 248); "><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">Teşekkürler,</span><br style = " box-boyutlandırma: devral; color: rgb (29, 28, 29); font-family: Seksime-Lapo, Seksime-Frations, appleLogo, sans-serif; font-size: 15px; font-variant-ligatürler: common-ligatures; background-color: rgb (248, 248, 248); "><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: rgb(248, 248, 248);">{ app_name }</span><br></p>',
                    'pt-br' => '<p>Olá {client_name}, </p><p>Você agora é Client ..</p><p>E-mail: {client_email} </p><p>Senha: {client_password}</p><p>{app_url}</p><p>Obrigado,</p><p>{app_name}</p>',

                ],
            ],
            'new_support_ticket' => [
                'subject' => 'New Support Ticket',
                'lang' => [
                    'ar' => '<p><span style="background-color: rgb(248, 249, 250); color: rgb(34, 34, 34); font-family: inherit; font-size: 24px; text-align: right; white-space: pre-wrap;">مرحبا</span><span style="font-size: 12pt;">&nbsp;{support_name}</span><br><br></p><p><span style="background-color: rgb(248, 249, 250); color: rgb(34, 34, 34); font-family: inherit; font-size: 24px; text-align: right; white-space: pre-wrap;">تم فتح تذكرة دعم جديدة.</span><span style="font-size: 12pt;">.</span><br><br></p><p><span style="background-color: rgb(248, 249, 250); color: rgb(34, 34, 34); font-family: inherit; font-size: 24px; text-align: right; white-space: pre-wrap;">عنوان</span><span style="font-size: 12pt;"><strong>:</strong>&nbsp;{support_title}</span><br></p><p><span style="background-color: rgb(248, 249, 250); color: rgb(34, 34, 34); font-family: inherit; font-size: 24px; text-align: right; white-space: pre-wrap;">أفضلية</span><span style="font-size: 12pt;"><strong>:</strong>&nbsp;{support_priority}</span><span style="font-size: 12pt;"><br></span></p><p><span style="background-color: rgb(248, 249, 250); color: rgb(34, 34, 34); font-family: inherit; font-size: 24px; text-align: right; white-space: pre-wrap;">تاريخ الانتهاء</span><span style="font-size: 12pt;">: {support_end_date}</span></p><p><span style="background-color: rgb(248, 249, 250); color: rgb(34, 34, 34); font-family: inherit; font-size: 24px; text-align: right; white-space: pre-wrap;">رسالة دعم</span><span style="font-size: 12pt;"><strong>:</strong></span><br><span style="font-size: 12pt;">{support_description}</span><span style="font-size: 12pt;"><br><br></span></p><p><span style="background-color: rgb(248, 249, 250); color: rgb(34, 34, 34); font-family: inherit; font-size: 24px; text-align: right; white-space: pre-wrap;">أطيب التحيات،</span><span style="font-size: 12pt;">,</span><br>{app_name}</p>',
                    'zh' => '<p><span style="font-size: 12pt;"><b>嗨</b> {support_name}</span><br><br><span style="font-size: 12pt;">新的支持请求已打开。</span><br><br><span style="font-size: 12pt;"><strong>标题：</strong> {support_title}</span><br>< span style="font-size: 12pt;"><strong>优先级：</strong> {support_priority}</span><span style="font-size: 12pt;"><br></span><span style="font-size: 12pt;"><b>结束日期</b>：{support_end_date}</span></p><p><br><span style="font-size: 12pt;" ><strong>支持消息：</strong></span><br><span style="font-size: 12pt;">{support_description}</span><span style="font-size: 12pt;" ><br><br><b>亲切的问候</b>，</span><br>{app_name}</p>',
                    'da' => '<p><b>Hej</b>&nbsp;{support_name}<br><br></p><p>Ny supportbillet er blevet åbnet.<br><br></p><p><b>Titel</b>: {support_title}<br></p><p><b>Prioritet</b>: {support_priority}<br></p><p><b>Slutdato</b>: {support_end_date}</p><p><br></p><p><b>Supportmeddelelse</b>:<br>{support_description}<br><br></p><p><b>Med venlig hilsen</b>,<br>{app_name}</p>',
                    'de' => '<p><b>Hallo</b>&nbsp;{support_name}<br><br></p><p>Neues Support-Ticket wurde eröffnet.<br><br></p><p><b>Titel</b>: {support_title}<br></p><p><b>Priorität</b>: {support_priority}<br></p><p><b>Endtermin</b>: {support_end_date}</p><p><br></p><p><b>Support-Nachricht</b>:<br>{support_description}<br><br></p><p><b>Mit freundlichen Grüßen</b>,<br>{app_name}</p>',
                    'en' => '<p><span style="font-size: 12pt;"><b>Hi</b>&nbsp;{support_name}</span><br><br><span style="font-size: 12pt;">New support ticket has been opened.</span><br><br><span style="font-size: 12pt;"><strong>Title:</strong>&nbsp;{support_title}</span><br><span style="font-size: 12pt;"><strong>Priority:</strong>&nbsp;{support_priority}</span><span style="font-size: 12pt;"><br></span><span style="font-size: 12pt;"><b>End Date</b>: {support_end_date}</span></p><p><br><span style="font-size: 12pt;"><strong>Support message:</strong></span><br><span style="font-size: 12pt;">{support_description}</span><span style="font-size: 12pt;"><br><br><b>Kind Regards</b>,</span><br>{app_name}</p>',
                    'es' => '<p><b>Hola</b>&nbsp;{support_name}<br><br></p><p>Se ha abierto un nuevo ticket de soporte.<br><br></p><p><b>Título</b>: {support_title}<br></p><p><b>Prioridad</b>: {support_priority}<br></p><p><b>Fecha final</b>: {support_end_date}</p><p><br></p><p><b>Mensaje de apoyo</b>:<br>{support_description}<br><br></p><p><b>Saludos cordiales</b>,<br>{app_name}</p>',
                    'fr' => '<p><b>salut</b>&nbsp;{support_name}<br><br></p><p>Un nouveau ticket d\'assistance a été ouvert.<br><br></p><p><b>Titre</b>: {support_title}<br></p><p><b>Priorité</b>: {support_priority}<br></p><p><b>Date de fin</b>: {support_end_date}</p><p><b>Message d\'assistance</b>:<br>{support_description}<br><br></p><p><b>Sincères amitiés</b>,<br>{app_name}</p>',
                    'he' => '<p><span style="font-size: 12pt;"><b>היי</b> {support_name}</span><br><br><span style="font-size: 12pt;"> כרטיס תמיכה חדש נפתח.</span><br><br><span style="font-size: 12pt;"><strong>כותרת:</strong> {support_title}</span><br>< span style="font-size: 12pt;"><strong>עדיפות:</strong> {support_priority}</span><span style="font-size: 12pt;"><br></span><span style="font-size: 12pt;"><b>תאריך סיום</b>: {support_end_date}</span></p><p><br><span style="font-size: 12pt;" ><strong>הודעת תמיכה:</strong></span><br><span style="font-size: 12pt;">{support_description}</span><span style="font-size: 12pt;" ><br><br><b>בברכה</b>,</span><br>{app_name}</p>',
                    'it' => '<p><b>Ciao</b>&nbsp;{support_name},<br><br></p><p>È stato aperto un nuovo ticket di supporto.<br><br></p><p><b>Titolo</b>: {support_title}<br></p><p><b>Priorità</b>: {support_priority}<br></p><p><b>Data di fine</b>: {support_end_date}</p><p><br></p><p><b>Messaggio di supporto</b>:<br>{support_description}</p><p><b>Cordiali saluti</b>,<br>{app_name}</p>',
                    'ja' => '<p>こんにちは {support_name}<br><br></p><p>新しいサポートチケットがオープンしました。.<br><br></p><p>題名: {support_title}<br></p><p>優先: {support_priority}<br></p><p>終了日: {support_end_date}</p><p><br></p><p>サポートメッセージ:<br>{support_description}<br><br></p><div class="tw-ta-container hide-focus-ring tw-lfl focus-visible" id="tw-target-text-container" tabindex="0" data-focus-visible-added="" style="overflow: hidden; position: relative; outline: 0px;"><pre class="tw-data-text tw-text-large XcVN5d tw-ta" data-placeholder="Translation" id="tw-target-text" dir="ltr" style="unicode-bidi: isolate; line-height: 32px; border: none; padding: 2px 0.14em 2px 0px; position: relative; margin-top: -2px; margin-bottom: -2px; resize: none; overflow: hidden; width: 277px; overflow-wrap: break-word;"><span lang="ja">敬具、</span>,</pre></div><p>{app_name}</p>',
                    'nl' => '<p><b>Hoi</b>&nbsp;{support_name}<br><br></p><p>Er is een nieuw supportticket geopend.<br><br></p><p><b>Titel</b>: {support_title}<br></p><p><b>Prioriteit</b>: {support_priority}<br></p><p><b>Einddatum</b>: {support_end_date}</p><p><br></p><p><b>Ondersteuningsbericht</b>:<br>{support_description}<br><br></p><p><b>Vriendelijke groeten</b>,<br>{app_name}</p>',
                    'pl' => '<p><b>cześć</b>&nbsp;{support_name}<br><br></p><p>Nowe zgłoszenie do pomocy technicznej zostało otwarte.<br><br></p><p><b>Tytuł</b>: {support_title}<br></p><p><b>Priorytet</b>: {support_priority}<br></p><p><b>Data końcowa</b>: {support_end_date}</p><p><br></p><p><b>Wiadomość pomocy</b>:<br>{support_description}<br><br></p><p><b>Z poważaniem</b>,<br>{app_name}</p>',
                    'ru' => '<p><b>Здравствуй</b>&nbsp;{support_name}<br><br></p><p>Открыта новая заявка в службу поддержки.<br><br></p><p><b>заглавие</b>: {support_title}<br></p><p><b>Приоритет</b>: {support_priority}<br></p><p><b>Дата окончания</b>: {support_end_date}</p><p><br></p><p><b>Сообщение поддержки</b>:<br>{support_description}<br><br></p><p><b>С уважением</b>,<br>{app_name}</p>',
                    'pt' => '<p><b>Oi</b>&nbsp;{support_name}<br><br></p><p>ОNovo ticket de suporte foi aberto.<br><br></p><p><b>Título</b>: {support_title}<br></p><p><b>Prioridade</b>: {support_priority}<br></p><p><b>Data final</b>: {support_end_date}</p><p><br></p><p><b>Mensagem de suporte</b>:<br>{support_description}<br><br></p><p><b>С Atenciosamente</b>,<br>{app_name}</p>',
                    'tr' => '<p><span style="font-size: 12pt;"><b>Merhaba</b> {support_name}</span><br><br><span style="font-size: 12pt;"> Yeni destek bileti açıldı.</span><br><br><span style="font-size: 12pt;"><strong>Başlık:</strong> {support_title}</span><br>< span style="font-size: 12pt;"><strong>Öncelik:</strong> {support_priority}</span><span style="font-size: 12pt;"><br></span><span style="font-size: 12pt;"><b>Bitiş Tarihi</b>: {support_end_date}</span></p><p><br><span style="font-size: 12pt;" ><strong>Destek mesajı:</strong></span><br><span style="font-size: 12pt;">{support_description}</span><span style="font-size: 12pt;" ><br><br><b>Saygılarımızla</b>,</span><br>{app_name}</p>',
                    'pt-br' => '<p><b>Oi</b>&nbsp;{support_name}<br><br></p><p>ОNovo ticket de suporte foi aberto.<br><br></p><p><b>Título</b>: {support_title}<br></p><p><b>Prioridade</b>: {support_priority}<br></p><p><b>Data final</b>: {support_end_date}</p><p><br></p><p><b>Mensagem de suporte</b>:<br>{support_description}<br><br></p><p><b>С Atenciosamente</b>,<br>{app_name}</p>',
                ],
            ],
            'lead_assigned' => [
                'subject' => 'Lead Assigned',
                'lang' => [
                    'ar' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: " open="" sans";"="">﻿</span><span style="font-family: " open="" sans";"="">مرحبا,</span><br style="font-family: sans-serif;"></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";"="">تم تعيين عميل محتمل جديد لك.</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";"="">اسم العميل المحتمل&nbsp;: {lead_name}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span open="" sans";"="" style="">الرصاص البريد الإلكتروني<span style="font-size: 1rem;">&nbsp;: {lead_email}</span></span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";"="">خط أنابيب الرصاص&nbsp;: {lead_pipeline}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";"="">مرحلة الرصاص&nbsp;: {lead_stage}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";"="">الموضوع الرئيسي: {lead_subject}</span></p><p></p>',
                    'zh' => '<p style="line-height: 28px; font-family: Nunito," segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span 样式="font-family: " open="" sans";"="">﻿</span><span style="font-family: " open="" sans";"="">您好，</ span><br style="font-family: sans-serif;"><span style="font-family: " open="" sans";"="">新潜在客户已分配给您。</span ></p><p style="line-height: 28px; font-family: Nunito," segoe="" ui",="" arial;="" font-size:="" 14px;"=" "><span style="" open="" sans";"=""><b>潜在客户姓名</b></span><span style="" open="" sans";"="" > : {lead_name}</span></p><p style="line-height: 28px; font-family: Nunito," segoe="" ui",="" arial;="" 字体大小: ="" 14px;"=""><span open=""sans";"="" style="font-size: 1rem;"><b>潜在客户电子邮件</b></span><span open ="" sans";"="" style="font-size: 1rem;"> : {lead_email}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" 字体大小:="" 14px;"=""><span style="" open="" sans";"=""><b >引导管道</b></span><span style="" open="" sans";"=""> ：{lead_pipeline}</span></p><p style="line-height: 28 像素；字体系列：Nunito，" segoe="" ui"，="" arial;="" 字体大小：="" 14px;"=""><span style="" open="" sans";" =""><b>领先阶段</b></span><span style="" open="" sans";"=""> ：{lead_stage}</span></p><p style ="line-height: 28px;"><span style="" open="" sans";"=""><b>主要主题</b>：{lead_subject}</span></p>< p></p>',
                    'da' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: " open="" sans";"="">Hej,</span><br style="font-family: sans-serif;"></p><p><span style="font-family: " open="" sans";"="">Ny bly er blevet tildelt dig.</span></p><p><span style="font-size: 1rem; font-weight: bolder; font-family: " open="" sans";"="">Lead-e-mail</span><span style="font-size: 1rem; font-family: " open="" sans";"="">&nbsp;</span><span style="font-size: 1rem; font-family: " open="" sans";"="">: {lead_email}</span></p><p><span style="font-family: sans-serif;"><span style="font-weight: bolder; font-family: " open="" sans";"="">Blyrørledning</span><span style="font-family: " open="" sans";"="">&nbsp;</span><span style="font-family: " open="" sans";"="">: {lead_pipeline}</span></span></p><p><span style="font-size: 1rem; font-weight: bolder; font-family: " open="" sans";"="">Lead scenen</span><span style="font-size: 1rem; font-family: " open="" sans";"="">&nbsp;</span><span style="font-size: 1rem; font-family: " open="" sans";"="">: {lead_stage}</span></p><p></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: sans-serif;"><span style="font-weight: bolder; font-family: " open="" sans";"="">Blynavn</span><span style="font-family: " open="" sans";"="">&nbsp;</span><span style="font-family: " open="" sans";"="">: {lead_name}</span></span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span open="" sans";"=""><b>Lead Emne</b>: {lead_subject}</span><span style="font-family: sans-serif;"><span style="font-family: " open="" sans";"=""><br></span><br></span></p><p></p>',
                    'de' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: sans-serif;">Hallo,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">Neuer Lead wurde Ihnen zugewiesen.</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: sans-serif; font-weight: bolder;" open="" sans";"="">Lead Name</span><span style="font-family: sans-serif;" open="" sans";"="">&nbsp;</span><span style="" open="" sans";"=""><font face="sans-serif">:</font> {lead_name}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: sans-serif; font-weight: bolder;" open="" sans";"="">Lead-E-Mail</span><span style="font-family: sans-serif;" open="" sans";"="">&nbsp;</span><span style="" open="" sans";"=""><font face="sans-serif">: </font>{lead_email}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: sans-serif; font-weight: bolder;" open="" sans";"="">Lead Pipeline</span><span style="font-family: sans-serif;" open="" sans";"="">&nbsp;</span><span style="" open="" sans";"=""><font face="sans-serif">:</font> {lead_pipeline}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: sans-serif; font-weight: bolder;" open="" sans";"="">Lead Stage</span><span style="font-family: sans-serif;" open="" sans";"="">&nbsp;</span><span style="" open="" sans";"=""><font face="sans-serif">: </font>{lead_stage}</span></p><p style="line-height: 28px;"><span style="font-family: " open="" sans";"=""><b>Lead Emne</b>: {lead_subject}</span></p><p></p>',
                    'en' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: " open="" sans";"="">﻿</span><span style="font-family: " open="" sans";"="">Hello,</span><br style="font-family: sans-serif;"><span style="font-family: " open="" sans";"="">New Lead has been Assign to you.</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";"=""><b>Lead Name</b></span><span style="" open="" sans";"="">&nbsp;: {lead_name}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span open="" sans";"="" style="font-size: 1rem;"><b>Lead Email</b></span><span open="" sans";"="" style="font-size: 1rem;">&nbsp;: {lead_email}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";"=""><b>Lead Pipeline</b></span><span style="" open="" sans";"="">&nbsp;: {lead_pipeline}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";"=""><b>Lead Stage</b></span><span style="" open="" sans";"="">&nbsp;: {lead_stage}</span></p><p style="line-height: 28px;"><span style="" open="" sans";"=""><b>Lead Subject</b>: {lead_subject}</span></p><p></p>',
                    'es' => '<p style="line-height: 28px;">Hola,<br style=""></p><p>Se le ha asignado un nuevo plomo.</p><p></p><p style="line-height: 28px;"><b>Nombre principal</b>&nbsp;: {lead_name}</p><p style="line-height: 28px;"><b>Correo electrónico</b> principal&nbsp;: {lead_email}</p><p style="line-height: 28px;"><b>Tubería de plomo</b>&nbsp;: {lead_pipeline}</p><p style="line-height: 28px;"><b>Etapa de plomo</b>&nbsp;: {lead_stage}</p><p style="line-height: 28px;"><span open="" sans";"=""><b>Hauptthema</b>: {lead_subject}</span><br></p><p></p>',
                    'fr' => '<p style="line-height: 28px;">Bonjour,<br style=""></p><p style="">Un nouveau prospect vous a été attribué.</p><p></p><p style="line-height: 28px;"><b>Nom du responsable</b>&nbsp;: {lead_name}</p><p style="line-height: 28px;"><b>Courriel principal</b>&nbsp;: {lead_email}</p><p style="line-height: 28px;"><b>Pipeline de plomb</b>&nbsp;: {lead_pipeline}</p><p style="line-height: 28px;"><b>Étape principale</b>&nbsp;: {lead_stage}</p><p style="line-height: 28px;"><span style="" open="" sans";"=""><b>Sujet principal</b>: {lead_subject}</span></p><p></p>',
                    'he' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style = "font-family:" open = "" sans ";" = ""> </span> <span style = "font-family:" open = "" sans ";" = ""> שלום, </ span><br style="font-family: sans-serif;"><span style="font-family: " open="" sans";"="">הפניה חדשה הוקצה לך.</span ></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="" "><span style="" open="" sans";"=""><b>שם ליד</b></span><span style="" open="" sans";"="" > : {lead_name}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size: ="" 14px;"=""><span open="" sans";"="" style="font-size: 1rem;"><b>דוא"ל לידים</b></span><span פתוח ="" sans";"="" style="font-size: 1rem;"> : {lead_email}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";"=""><b >Lead Pipeline</b></span><span style="" open="" sans";"=""> : {lead_pipeline}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";" =""><b>שלב מוביל</b></span><span style="" open="" sans";"=""> : {lead_stage}</span></p><p style ="line-height: 28px;"><span style="" open="" sans";"=""><b>נושא מוביל</b>: {lead_subject}</span></p>< p></p>',
                    'it' => '<p style="line-height: 28px;">Ciao,<br style=""></p><p>New Lead è stato assegnato a te.</p><p><b>Lead Email</b>&nbsp;: {lead_email}</p><p><b>Conduttura di piombo&nbsp;: {lead_pipeline}</b></p><p><b>Lead Stage</b>&nbsp;: {lead_stage}</p><p></p><p style="line-height: 28px;"><b>Nome del lead</b>&nbsp;: {lead_name}<br></p><p style="line-height: 28px;"><span style="" open="" sans";"=""><b>Soggetto principale</b>: {lead_subject}</span></p><p></p>',
                    'ja' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: " open="" sans";"="">こんにちは、</span><br style="font-family: sans-serif;"></p><p><span style="font-family: " open="" sans";"="">新しいリードが割り当てられました。</span><br><span style="font-family: sans-serif;"><span style="font-weight: bolder; font-family: " open="" sans";"="">リードメール</span><span style="font-family: " open="" sans";"="">&nbsp;</span><span style="font-family: " open="" sans";"="">: {lead_email}</span></span><br><span style="font-family: sans-serif;"><span style="font-weight: bolder; font-family: " open="" sans";"="">リードパイプライン</span><span style="font-family: " open="" sans";"="">&nbsp;</span><span style="font-family: " open="" sans";"="">: {lead_pipeline}</span></span><br><span style="font-family: sans-serif;"><span style="font-weight: bolder; font-family: " open="" sans";"="">リードステージ</span><span style="font-family: " open="" sans";"="">&nbsp;: {lead_stage}</span></span></p><p></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: sans-serif;"><span style="font-weight: bolder; font-family: " open="" sans";"="">リード名</span><span style="font-family: " open="" sans";"="">&nbsp;</span><span style="font-family: " open="" sans";"="">: {lead_name}</span><br></span></p><p style="line-height: 28px;"><span open="" sans";"="" style=""><span style="font-family: " open="" sans";"="">リードサブジェクト</span><span style="font-size: 1rem; font-family: " open="" sans";"="">: {lead_subject}</span></span></p><p></p>',
                    'nl' => '<p style="line-height: 28px;">Hallo,<br style=""></p><p style="">Nieuwe lead is aan u toegewezen.<br><b>E-mail leiden</b>&nbsp;: {lead_email}<br><b>Lead Pipeline</b>&nbsp;: {lead_pipeline}<br><b>Hoofdfase</b>&nbsp;: {lead_stage}</p><p></p><p style="line-height: 28px;"><b>Lead naam</b>&nbsp;: {lead_name}<br></p><p style="line-height: 28px;"><span style="" open="" sans";"=""><b>Hoofdonderwerp</b>: {lead_subject}</span></p><p></p>',
                    'pl' => '<p style="line-height: 28px;">Witaj,<br style="">Nowy potencjalny klient został do ciebie przypisany.</p><p style="line-height: 28px;"><b>Imię i nazwisko</b>&nbsp;: {lead_name}<br><b>Główny adres e-mail</b>&nbsp;: {lead_email}<br><b>Ołów rurociągu</b>&nbsp;: {lead_pipeline}<br><b>Etap prowadzący</b>&nbsp;: {lead_stage}</p><p style="line-height: 28px;"><span style="" open="" sans";"=""><b>Główny temat</b>: {lead_subject}</span></p><p></p>',
                    'ru' => '<p style="line-height: 28px;">Привет,<br style="">Новый Лид был назначен вам.</p><p style="line-height: 28px;"><b>Имя лидера</b>&nbsp;: {lead_name}<br><b>Ведущий Email</b>&nbsp;: {lead_email}<br><b>Ведущий трубопровод</b>&nbsp;: {lead_pipeline}<br><b>Ведущий этап</b>&nbsp;: {lead_stage}</p><p style="line-height: 28px;"><span style="" open="" sans";"=""><b>Ведущая тема</b>: {lead_subject}</span></p><p></p>',
                    'pt' => '<p style="line-height: 28px;">Olá,<br style="">O novo lead foi atribuído a você.</p><p style="line-height: 28px;"><b>Nome do lead</b>&nbsp;: {lead_name}<br><b>E-mail principal</b>&nbsp;: {lead_email}<br><b>Pipeline principal</b>&nbsp;: {lead_pipeline}<br><b>Estágio principal</b>&nbsp;: {lead_stage}</p><p style="line-height: 28px;"><span style="" open="" sans";"=""><b>Assunto principal</b>: {lead_subject}</span></p><p></p>',
                    'tr' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span stili ="font-family: " open="" sans";"="">﻿</span><span style="font-family: " open="" sans";"="">Merhaba,</ span><br style="font-family: sans-serif;"><span style="font-family: " open="" sans";"="">Yeni Müşteri Atandı.</span ></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=" "><span style="" open="" sans";"=""><b>Müşteri Adı</b></span><span style="" open="" sans";"="" > : {lead_name}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size: ="" 14px;"=""><span open="" sans";"="" style="font-size: 1rem;"><b>Müşteri E-postası</b></span><spanopen open ="" sans";"="" style="font-size: 1rem;"> : {lead_email}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";"=""><b >Müşteri Hattı</b></span><span style="" open="" sans";"=""> : {lead_pipeline}</span></p><p style="line-height: 28 piksel; yazı tipi ailesi: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="" open="" sans";" =""><b>Kurşun Aşaması</b></span><span style="" open="" sans";"=""> : {lead_stage}</span></p><p style ="line-height: 28px;"><span style="" open="" sans";"=""><b>Aday Konu</b>: {lead_subject}</span></p>< p></p>',
                    'pt-br' => '<p style="line-height: 28px;">Olá,<br style="">O novo lead foi atribuído a você.</p><p style="line-height: 28px;"><b>Nome do lead</b>&nbsp;: {lead_name}<br><b>E-mail principal</b>&nbsp;: {lead_email}<br><b>Pipeline principal</b>&nbsp;: {lead_pipeline}<br><b>Estágio principal</b>&nbsp;: {lead_stage}</p><p style="line-height: 28px;"><span style="" open="" sans";"=""><b>Assunto principal</b>: {lead_subject}</span></p><p></p>',
                ],
            ],
            'deal_assigned' => [
                'subject' => 'Deal Assigned',
                'lang' => [
                    'ar' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">مرحبا،</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">تم تعيين صفقة جديدة لك.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">اسم الصفقة</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">خط أنابيب الصفقة</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">مرحلة الصفقة</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">حالة الصفقة</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">سعر الصفقة</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'zh' => '<p style="line-height: 28px; font-family: Nunito, "Segoe UI", arial; font-size: 14px;"><span style="font-family: sans-serif;">你好，< /span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">新优惠已分配给您。</span></p>< p style="line-height: 28px; font-family: Nunito, "Segoe UI", arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style= "font-weight: BOLDER;">交易名称</span>：{deal_name}<br><span style="font-weight：bolder;">交易渠道</span>：{deal_pipeline}<br><span style="font-weight: BOLDER;">交易阶段</span>：{deal_stage}<br><span style="font-weight：bolder;">交易状态</span>：{deal_status}<br> <span style="font-weight: Bolder;">交易价格</span>：{deal_price}</span></p><p></p>',
                    'da' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">Hej,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">New Deal er blevet tildelt til dig.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">Deal Navn</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">Deal Pipeline</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">Deal Fase</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">Deal status</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">Deal pris</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'de' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">Hallo,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">New Deal wurde Ihnen zugewiesen.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">Geschäftsname</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">Deal Pipeline</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">Deal Stage</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">Deal Status</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">Ausgehandelter Preis</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'en' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">Hello,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">New Deal has been Assign to you.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">Deal Name</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">Deal Pipeline</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">Deal Stage</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">Deal Status</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">Deal Price</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'es' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">Hola,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">New Deal ha sido asignado a usted.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">Nombre del trato</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">Tubería de reparto</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">Etapa de reparto</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">Estado del acuerdo</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">Precio de oferta</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'fr' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">Bonjour,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">Le New Deal vous a été attribué.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">Nom de l\'accord</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">Pipeline de transactions</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">Étape de l\'opération</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">Statut de l\'accord</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">Prix ​​de l\'offre</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'he' => '<p style="line-height: 28px; font-family: Nunito, "Segoe UI", arial; font-size: 14px;"><span style="font-family: sans-serif;">שלום,< /span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">דיל חדש הוקצה לך.</span></p>< p style="line-height: 28px; font-family: Nunito, "Segoe UI", arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style= "font-weight: bolder;">שם העסקה</span> : {deal_name}<br><span style="font-weight: bolder;">צינור העסקה</span> : {deal_pipeline}<br><span style="font-weight: bolder;">שלב העסקה</span> : {deal_stage}<br><span style="font-weight: bolder;">סטטוס העסקה</span> : {deal_status}<br> <span style="font-weight: bolder;">מחיר מבצע</span> : {deal_price}</span></p><p></p>',
                    'it' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">Ciao,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">New Deal è stato assegnato a te.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">Nome dell\'affare</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">Pipeline di offerte</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">Stage Deal</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">Stato dell\'affare</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">Prezzo dell\'offerta</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'ja' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">こんにちは、</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">新しい取引が割り当てられました。</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">取引名</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">取引パイプライン</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">取引ステージ</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">取引状況</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">取引価格</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'nl' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">Hallo,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">New Deal is aan u toegewezen.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">Dealnaam</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">Deal Pipeline</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">Deal Stage</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">Dealstatus</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">Deal prijs</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'pl' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">Witaj,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">Umowa została przeniesiona {deal_old_stage} do&nbsp; {deal_new_stage}.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">Nazwa oferty</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">Deal Pipeline</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">Etap transakcji</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">Status oferty</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">Cena oferty</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'ru' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">Привет,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">Сделка была перемещена из {deal_old_stage} в&nbsp; {deal_new_stage}.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">Название сделки</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">Трубопровод сделки</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">Этап сделки</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">Статус сделки</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">Цена сделки</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'pt' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">Olá,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">Сделка была перемещена из {deal_old_stage} в&nbsp; {deal_new_stage}.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">Nome do negócio</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">Pipeline de negócios</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">Estágio do negócio</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">Status da transação</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">Preço da oferta</span>&nbsp;: {deal_price}</span></p><p></p>',
                    'tr' => '<p style="line-height: 28px; font-family: Nunito, "Segoe UI", arial; font-size: 14px;"><span style="font-family: sans-serif;">Merhaba,< /span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">Size Yeni Anlaşma Atandı.</span></p>< p style="line-height: 28px; font-family: Nunito, "Segoe UI", arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style= "font-weight: bolder;">Anlaşma Adı</span> : {deal_name}<br><span style="font-weight: bolder;">Anlaşma Hattı</span> : {deal_pipeline}<br><span style="font-weight: bolder;">Anlaşma Aşaması</span> : {deal_stage}<br><span style="font-weight: bolder;">Anlaşma Durumu</span> : {deal_status}<br> <span style="font-weight: bolder;">Anlaşma Fiyatı</span> : {deal_price}</span></p><p></p>',
                    'pt-br' => '<p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;">Olá,</span><br style="font-family: sans-serif;"><span style="font-family: sans-serif;">Сделка была перемещена из {deal_old_stage} в&nbsp; {deal_new_stage}.</span></p><p style="line-height: 28px; font-family: Nunito, &quot;Segoe UI&quot;, arial; font-size: 14px;"><span style="font-family: sans-serif;"><span style="font-weight: bolder;">Nome do negócio</span>&nbsp;: {deal_name}<br><span style="font-weight: bolder;">Pipeline de negócios</span>&nbsp;: {deal_pipeline}<br><span style="font-weight: bolder;">Estágio do negócio</span>&nbsp;: {deal_stage}<br><span style="font-weight: bolder;">Status da transação</span>&nbsp;: {deal_status}<br><span style="font-weight: bolder;">Preço da oferta</span>&nbsp;: {deal_price}</span></p><p></p>',
                ],
            ],
            'new_award' => [
                'subject' => 'New Award',
                'lang' => [
                    'ar' => '<p>مرحبا،&nbsp;<br>مرحبا بك في {app_name}.</p><p><b>البريد الإلكتروني </b>: {email}<br><b>كلمه السر</b> : {password}</p><p>{app_url}</p><p>شكر،<br>{app_name}</p>',
                    'zh' => '<p>嗨，<span style="font-family: var(--bs-body-font-family); font-size: var(--bs-body-font-size); font-weight: var( --bs-body-font-weight); text-align: var(--bs-body-text-align);">{award_name}</span></p><p>我很高兴提名.</p><p>我很满意他/她是获得该奖项的最佳员工。 </p><p>我发现他/她是一个以目标为导向的人，高效且非常准时。</p><p>如果您有任何问题，请随时与我们联系。<br></p ><p>谢谢您，</p><p>{app_name}</p><p>{app_url}</p>',
                    'da' => '<p>Hej,&nbsp;<br>Velkommen til {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Adgangskode</b> : {password}</p><p>{app_url}</p><p>Tak,<br>{app_name}</p>',
                    'de' => '<p>Hallo,&nbsp;<br>Willkommen zu {app_name}.</p><p><b>Email </b>: {email}<br><b>Passwort</b> : {password}</p><p>{app_url}</p><p>Vielen Dank,<br>{app_name}</p>',
                    'en' => '<p>Hi , <span style="font-family: var(--bs-body-font-family); font-size: var(--bs-body-font-size); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{award_name}</span></p><p>I am much pleased to nominate .</p><p>I am satisfied that he/she is the best employee for the award. </p><p>I have realized  that he/she is a goal-oriented person, efficient and very punctual .</p><p>Feel free to reach out if you have any question.<br></p><p>Thank You, </p><p>{app_name}</p><p>{app_url}</p>',
                    'es' => '<p>Hola,&nbsp;<br>Bienvenido a {app_name}.</p><p><b>Correo electrónico </b>: {email}<br><b>Contraseña</b> : {password}</p><p>{app_url}</p><p>Gracias,<br>{app_name}</p>',
                    'fr' => '<p>Bonjour,&nbsp;<br>Bienvenue à {app_name}.</p><p><b>Email </b>: {email}<br><b>Mot de passe</b> : {password}</p><p>{app_url}</p><p>Merci,<br>{app_name}</p>',
                    'he' => '<p>שלום, <span style="font-family: var(--bs-body-font-family); font-size: var(--bs-body-font-size); font-weight: var( --bs-body-font-weight); text-align: var(--bs-body-text-align);">{award_name}</span></p><p>אני שמח מאוד להציע מועמדות .</p><p>אני מרוצה מכך שהוא/היא העובד הטוב ביותר עבור הפרס. </p><p>הבנתי  שהוא/היא אדם ממוקד מטרה, יעיל ומאוד דייקן .</p><p>אל תהסס לפנות אם יש לך שאלה כלשהי.<br></p ><p>תודה, </p><p>{app_name}</p><p>{app_url}</p>',
                    'it' => '<p>Ciao,&nbsp;<br>Benvenuto a {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Parola d\'ordine</b> : {password}</p><p>{app_url}</p><p>Grazie,<br>{app_name}</p>',
                    'ja' => '<p>こんにちは、&nbsp;<br>へようこそ {app_name}.</p><p><b>Eメール </b>: {email}<br><b>パスワード</b> : {password}</p><p>{app_url}</p><p>おかげで、<br>{app_name}</p>',
                    'nl' => '<p>Hallo,&nbsp;<br>Welkom bij {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Wachtwoord</b> : {password}</p><p>{app_url}</p><p>Bedankt,<br>{app_name}</p>',
                    'pl' => '<p>Witaj,&nbsp;<br>Witamy w {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Hasło</b> : {password}</p><p>{app_url}</p><p>Dzięki,<br>{app_name}</p>',
                    'ru' => '<p>Привет,&nbsp;<br>Добро пожаловать в {app_name}.</p><p><b>Электронное письмо </b>: {email}<br><b>пароль</b> : {password}</p><p>{app_url}</p><p>Спасибо,<br>{app_name}</p>',
                    'pt' => '<p>Olá,<br>Bem-vindo ao {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Senha</b> : {password}</p><p>{app_url}</p><p>Obrigada,<br>{app_name}</p>',
                    'tr' => '<p>Merhaba , <span style="font-family: var(--bs-body-font-family); font-size: var(--bs-body-font-size); font-weight: var( --bs-body-font-weight); text-align: var(--bs-body-text-align);">{award_name}</span></p><p>Aday göstermekten çok memnunum .</p><p>Ödül için en iyi çalışanın o olduğundan memnunum. </p><p>Hedef odaklı, verimli ve çok dakik biri olduğunu fark ettim.</p><p>Herhangi bir sorunuz olursa bize ulaşmaktan çekinmeyin.<br></p ><p>Teşekkürler, </p><p>{app_name}</p><p>{app_url}</p>',
                    'pt-br' => '<p>Olá,<br>Bem-vindo ao {app_name}.</p><p><b>E-mail </b>: {email}<br><b>Senha</b> : {password}</p><p>{app_url}</p><p>Obrigada,<br>{app_name}</p>',

                ],
            ],
            'customer_invoice_sent' => [
                'subject' => 'Customer Invoice Sent',
                'lang' => [
                    'ar' => '<p>مرحب<span style="text-align: var(--bs-body-text-align);">مرحبا ، { invoice_name }</span></p><p>مرحبا بك في { app_name }</p><p>أتمنى أن يجدك هذا البريد الإلكتروني جيدا برجاء الرجوع الى رقم الفاتورة الملحقة { invoice_number } للخدمة / الخدمة.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">ببساطة ، اضغط على الاختيار بأسفل :&nbsp;</span></p><p>{ invoice_url }</p><p>إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة.</p><p>شكرا لك</p><p>Regards,</p><p>{ company_name }</p><p>{ app_url }</p><div><br></div>',
                    'zh' => '<p style="line-height: 28px; font-family: Nunito," segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span 样式="font-family: " open="" sans";"="">﻿</span><span style="text-align: var(--bs-body-text-align);">嗨， {invoice_name}</span></p><p style="line-height: 28px; font-family: Nunito," segoe="" ui",="" arial;="" font-size:=" " 14px;"="">欢迎使用 {app_name}</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:=""14px;"="">希望这封电子邮件能让您满意！请参阅随附的发票号码 {invoice_number}<span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align : var(--bs-body-text-align);">} 用于产品/服务。</span></p><p style="line-height: 28px; font-family: Nunito, " segoe= "" ui",="" arial;="" font-size:="" 14px;"="">只需点击下面的按钮即可：</p><p style="line-height: 28px; font -family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">{invoice_url}</p><p style="line-height : 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">如果您有任何疑问，请随时与我们联系。 </p><p style="line-height: 28px; font-family: Nunito," segoe="" ui",="" arial;="" font-size:="" 14px;"="" >谢谢，</p><p style="line-height: 28px; font-family: Nunito," segoe="" ui",="" arial;="" font-size:="" 14px; "="">问候，</p><p style="line-height: 28px; font-family: Nunito," segoe="" ui",="" arial;="" font-size:=" " 14px;"="">{company_name}</p><p style="line-height: 28px;字体系列：Nunito、" segoe="" ui",="" arial;="" font-size:="" 14px;"="">{app_url}</p><p></p>',
                    'da' => '<p>Hej, { invoice_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Velkommen til { app_name }</span></p><p>Håber denne e-mail finder dig godt! Se vedlagte fakturanummer { invoice_number } for product/service.</p><p>Klik på knappen nedenfor:&nbsp;</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ invoice_url }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Du er velkommen til at række ud, hvis du har nogen spørgsmål.</span></p><p>Tak.</p><p>Med venlig hilsen</p><p>{ company_name }</p><p>{ app_url }</p>',
                    'de' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Hi, {invoice_name}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Willkommen bei {app_name}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Hoffe, diese E-Mail findet dich gut! Bitte beachten Sie die beigefügte Rechnungsnummer {invoice_number} für Produkt/Service.</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Klicken Sie einfach auf den Button unten:&nbsp;</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">{invoice_url}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Fühlen Sie sich frei, wenn Sie Fragen haben.</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Vielen Dank,</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Betrachtet,</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">{company_name}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">{app_url}</font></p><p></p>',
                    'en' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style="font-family: " open="" sans";"="">﻿</span><span style="text-align: var(--bs-body-text-align);">Hi ,{invoice_name}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">Welcome to {app_name}</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">Hope this email finds you well! Please see attached invoice number {invoice_number}<span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">} for product/service.</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">Simply click on the button below: </p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">{invoice_url}</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">Feel free to reach out if you have any questions.</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">Thank You,</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">Regards,</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">{company_name}</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">{app_url}</p><p></p>',
                    'es' => '<p>Hi, {invoice_name}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Bienvenido a {app_name}</span></p><p>¡Espero que este email le encuentre bien! Consulte el número de factura adjunto {invoice_number} para el producto/servicio.</p><p>Simplemente haga clic en el botón de abajo:&nbsp;</p><p>{invoice_url}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Siéntase libre de llegar si usted tiene alguna pregunta.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Gracias,</span></p><p>Considerando,</p><p>{nombre_empresa}</p><p>{app_url}</p>',
                    'fr' => '<p>Bonjour, { nom_appel }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Bienvenue dans { app_name }</span></p><p>J espère que ce courriel vous trouve bien ! Voir le numéro de facture { invoice_number } pour le produit/service.</p><p>Cliquez simplement sur le bouton ci-dessous:&nbsp;</p><p>{ url-invoque_utilisateur }</p><p>N hésitez pas à nous contacter si vous avez des questions.</p><p>Merci,</p><p>Regards,</p><p>{ nom_entreprise }</p><p>{ adresse_url }</p><div><br></div>',
                    'he' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span style = "Font-family:" Open = "" sans ";" = ""> </span> <span style = "text-align: var (-bs-body-text-align);"> hi, {invoice_name}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:=" " 14px;"="">ברוכים הבאים אל {app_name}</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">מקווה שהמייל הזה ימצא אותך היטב! ראה את מספר החשבונית המצורפת {invoice_number}<span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align : var(--bs-body-text-align);">} עבור מוצר/שירות.</span></p><p style="line-height: 28px; font-family: Nunito, " segoe= "" ui",="" arial;="" font-size:="" 14px;"="">פשוט לחץ על הכפתור למטה: </p><p style="line-height: 28px; font -family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">{invoice_url}</p><p style="line-height : 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">אל תהסס לפנות אם יש לך שאלות כלשהן. </p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="" >תודה,</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px; "="">בברכה,</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:=" " 14px;"="">{company_name}</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">{app_url}</p><p></p>',
                    'it' => '<p>Ciao, {nome_invoca_}</p><p>Benvenuti in {app_name}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Spero che questa email ti trovi bene! Si prega di consultare il numero di fattura collegato {invoice_number} per il prodotto/servizio.</span></p><p>Semplicemente clicca sul pulsante sottostante:&nbsp;</p><p>{invoice_url}</p><p>Sentiti libero di raggiungere se hai domande.</p><p>Grazie,</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Riguardo,</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p>',
                    'ja' => '<p>こんにちは、 {請求書名}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_name} へようこそ</span></p><p>この E メールでよくご確認ください。 製品 / サービスについては、添付された請求書番号 {invoice_number} を参照してください。</p><p>以下のボタンをクリックしてください。&nbsp;</p><p>{請求書 URL}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">質問がある場合は、自由に連絡してください。</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">ありがとうございます</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">よろしく</span></p><p>{ company_name}</p><p>{app_url}</p>',
                    'nl' => '<p>Hallo, { invoice_name }</p><p>Welkom bij { app_name }</p><p>Hoop dat deze e-mail je goed vindt! Zie bijgevoegde factuurnummer { invoice_number } voor product/service.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Klik gewoon op de knop hieronder:&nbsp;</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ invoice_url }</span></p><p>Voel je vrij om uit te reiken als je vragen hebt.</p><p>Dank U,</p><p>Betreft:</p><p>{ bedrijfsnaam }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ app_url }</span><br></p>',
                    'pl' => '<p>Witaj, {invoice_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Witamy w aplikacji {app_name }</span></p><p>Mam nadzieję, że ta wiadomość znajdzie Cię dobrze! Sprawdź załączoną fakturę numer {invoice_number } dla produktu/usługi.</p><p>Wystarczy kliknąć na przycisk poniżej:&nbsp;</p><p>{adres_URL_faktury }</p><p>Czuj się swobodnie, jeśli masz jakieś pytania.</p><p>Dziękuję,</p><p>W odniesieniu do</p><p>{company_name }</p><p>{app_url }</p>',
                    'ru' => '<p>Привет, { invoice_name }</p><p>Вас приветствует { app_name }</p><p>Надеюсь, это электронное письмо найдет вас хорошо! См. вложенный номер счета-фактуры { invoice_number } для производства/услуги.</p><p>Просто нажмите на кнопку ниже:&nbsp;</p><p>{ invoice_url }</p><p>Не стеснитесь, если у вас есть вопросы.</p><p>Спасибо.</p><p>С уважением,</p><p>{ company_name }</p><p>{ app_url }</p>',
                    'pt' => '<p><span style="font-size: 14.4px;">Oi, {invoice_name}</span></p><p><span style="font-size: 14.4px;">Bem-vindo a {app_name}</span></p><p><span style="font-size: 14.4px;">Espero que este e-mail encontre você bem! Por favor, consulte o número da fatura anexa {invoice_number} para produto/serviço.</span></p><p><span style="font-size: 14.4px;">Basta clicar no botão abaixo:&nbsp;</span></p><p><span style="font-size: 14.4px; font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{invoice_url}</span></p><p><span style="font-size: 14.4px;">Sinta-se à vontade para alcançar fora se você tiver alguma dúvida.</span></p><p><span style="font-size: 14.4px;">Obrigado,</span></p><p><span style="font-size: 14.4px;">Considera,</span></p><p><span style="font-size: 14.4px;">{company_name}</span></p><p><span style="font-size: 14.4px;">{app_url}</span></p>',
                    'tr' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><span stili ="font-family: " open="" sans";"="">﻿</span><span style="text-align: var(--bs-body-text-align);">Merhaba , {invoice_name}</span></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:=" " 14px;"="">{app_name}</p><p style="line-height: 28px; font-family: Nunito ya hoş geldiniz, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">Umarım bu e-posta sizi bulur! Lütfen ekteki fatura numarasına bakın {invoice_number}<span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align : var(--bs-body-text-align);">} ürün/hizmet için.</span></p><p style="line-height: 28px; font-family: Nunito, " segoe= "" ui",="" arial;="" font-size:="" 14px;"="">Aşağıdaki düğmeyi tıklamanız yeterlidir: </p><p style="line-height: 28px; font -family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">{invoice_url}</p><p style="line-height : 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">Herhangi bir sorunuz olursa bize ulaşmaktan çekinmeyin. </p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="" >Teşekkürler,</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px; "="">Saygılarımızla,</p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:=" " 14px;"="">{şirket_adı}</p><p style="line-height: 28px; yazı tipi ailesi: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"="">{app_url}</p><p></p>',
                    'pt-br' => '<p><span style="font-size: 14.4px;">Oi, {invoice_name}</span></p><p><span style="font-size: 14.4px;">Bem-vindo a {app_name}</span></p><p><span style="font-size: 14.4px;">Espero que este e-mail encontre você bem! Por favor, consulte o número da fatura anexa {invoice_number} para produto/serviço.</span></p><p><span style="font-size: 14.4px;">Basta clicar no botão abaixo:&nbsp;</span></p><p><span style="font-size: 14.4px; font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{invoice_url}</span></p><p><span style="font-size: 14.4px;">Sinta-se à vontade para alcançar fora se você tiver alguma dúvida.</span></p><p><span style="font-size: 14.4px;">Obrigado,</span></p><p><span style="font-size: 14.4px;">Considera,</span></p><p><span style="font-size: 14.4px;">{company_name}</span></p><p><span style="font-size: 14.4px;">{app_url}</span></p>',

                ],
            ],
            'new_invoice_payment' => [
                'subject' => 'New Invoice Payment',
                'lang' => [
                    'ar' => '<p>Hej.</p>
                    <p>Velkommen til { app_name }</p>
                    <p>K&aelig;re { invoice_payment_name }</p>
                    <p>Vi har modtaget din m&aelig;ngde { invoice_payment_amount } betaling for { invoice_number } undert.d. p&aring; dato { invoice_payment_date }</p>
                    <p>Dit { invoice_number } Forfaldsbel&oslash;b er { payment_dueAmount }</p>
                    <p>Vi s&aelig;tter pris p&aring; din hurtige betaling og ser frem til fortsatte forretninger med dig i fremtiden.</p>
                    <p>Mange tak, og ha en god dag!</p>
                    <p>&nbsp;</p>
                    <p>Med venlig hilsen</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'zh' => '<p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">嗨，</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">欢迎来到 {app_name}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">亲爱的{invoice_ payment_name}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">我们已收到您于 {invoice_ payment_date} 日期提交的 {invoice_number} 金额为 {invoice_ payment_amount} 的付款</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">您的 {invoice_number} 应付金额为 { payment_dueAmount}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">我们感谢您及时付款，并期待将来继续与您开展业务。</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">非常感谢您，祝您有美好的一天！！</span></span></p>
                    <p> </p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">问候，</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">{company_name}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;">
                    <span style="font-size: 15px; font-variant-ligatures: common-ligatures;">{app_url}</span></span></p>',
                    'da' => '<p>Hej.</p>
                    <p>Velkommen til { app_name }</p>
                    <p>K&aelig;re { invoice_payment_name }</p>
                    <p>Vi har modtaget din m&aelig;ngde { invoice_payment_amount } betaling for { invoice_number } undert.d. p&aring; dato { invoice_payment_date }</p>
                    <p>Dit { invoice_number } Forfaldsbel&oslash;b er { payment_dueAmount }</p>
                    <p>Vi s&aelig;tter pris p&aring; din hurtige betaling og ser frem til fortsatte forretninger med dig i fremtiden.</p>
                    <p>Mange tak, og ha en god dag!</p>
                    <p>&nbsp;</p>
                    <p>Med venlig hilsen</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'de' => '<p>Hi,</p>
                    <p>Willkommen bei {app_name}</p>
                    <p>Sehr geehrter {invoice_payment_name}</p>
                    <p>Wir haben Ihre Zahlung {invoice_payment_amount} f&uuml;r {invoice_number}, die am Datum {invoice_payment_date} &uuml;bergeben wurde, erhalten.</p>
                    <p>Ihr {invoice_number} -f&auml;lliger Betrag ist {payment_dueAmount}</p>
                    <p>Wir freuen uns &uuml;ber Ihre prompte Bezahlung und freuen uns auf das weitere Gesch&auml;ft mit Ihnen in der Zukunft.</p>
                    <p>Vielen Dank und habe einen guten Tag!!</p>
                    <p>&nbsp;</p>
                    <p>Betrachtet,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'en' => '<p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">Hi,</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">Welcome to {app_name}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">Dear {invoice_payment_name}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">We have recieved your amount {invoice_payment_amount} payment for {invoice_number} submited on date {invoice_payment_date}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">Your {invoice_number} Due amount is {payment_dueAmount}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">We appreciate your prompt payment and look forward to continued business with you in the future.</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">Thank you very much and have a good day!!</span></span></p>
                    <p>&nbsp;</p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">Regards,</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">{company_name}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;">
                    <span style="font-size: 15px; font-variant-ligatures: common-ligatures;">{app_url}</span></span></p>',
                    'es' => '<p>Hola,</p>
                    <p>Bienvenido a {app_name}</p>
                    <p>Estimado {invoice_payment_name}</p>
                    <p>Hemos recibido su importe {invoice_payment_amount} pago para {invoice_number} submitado en la fecha {invoice_payment_date}</p>
                    <p>El importe de {invoice_number} Due es {payment_dueAmount}</p>
                    <p>Agradecemos su pronto pago y esperamos continuar con sus negocios con usted en el futuro.</p>
                    <p>Muchas gracias y que tengan un buen d&iacute;a!!</p>
                    <p>&nbsp;</p>
                    <p>Considerando,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'fr' => '<p>Salut,</p>
                    <p>Bienvenue dans { app_name }</p>
                    <p>Cher { invoice_payment_name }</p>
                    <p>Nous avons re&ccedil;u votre montant { invoice_payment_amount } de paiement pour { invoice_number } soumis le { invoice_payment_date }</p>
                    <p>Votre {invoice_number} Montant d&ucirc; est { payment_dueAmount }</p>
                    <p>Nous appr&eacute;cions votre rapidit&eacute; de paiement et nous attendons avec impatience de poursuivre vos activit&eacute;s avec vous &agrave; lavenir.</p>
                    <p>Merci beaucoup et avez une bonne journ&eacute;e ! !</p>
                    <p>&nbsp;</p>
                    <p>Regards,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'he' => '<p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">שלום,</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">ברוך הבא אל {app_name}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">{invoice_payment_name}</span></span></p> היקר
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">קיבלנו את התשלום שלך בסכום {invoice_payment_amount} עבור {invoice_number} שנשלח בתאריך {invoice_payment_date}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">סכום התשלום שלך ב-{invoice_number} הוא {payment_dueAmount}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">אנו מעריכים את התשלום המהיר שלך ומצפים להמשך העסקים איתך בעתיד.</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">תודה רבה ויום טוב!!</span></span></p>
                    <p> </p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">בברכה,</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">{company_name}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;">
                    <span style="font-size: 15px; font-variant-ligatures: common-ligatures;">{app_url}</span></span></p>',
                    'it' => '<p>Ciao,</p>
                    <p>Benvenuti in {app_name}</p>
                    <p>Caro {invoice_payment_name}</p>
                    <p>Abbiamo ricevuto la tua quantit&agrave; {invoice_payment_amount} pagamento per {invoice_number} subita alla data {invoice_payment_date}</p>
                    <p>Il tuo {invoice_number} A somma cifra &egrave; {payment_dueAmount}</p>
                    <p>Apprezziamo il tuo tempestoso pagamento e non vedo lora di continuare a fare affari con te in futuro.</p>
                    <p>Grazie mille e buona giornata!!</p>
                    <p>&nbsp;</p>
                    <p>Riguardo,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'ja' => '<p>こんにちは。</p>
                    <p>{app_name} へようこそ</p>
                    <p>{ invoice_payment_name} に出れます</p>
                    <p>{ invoice_payment_date} 日付で提出された {請求書番号} の支払金額 } の金額を回収しました。 }</p>
                    <p>お客様の {請求書番号} 予定額は {payment_dueAmount} です</p>
                    <p>お客様の迅速な支払いを評価し、今後も継続してビジネスを継続することを期待しています。</p>
                    <p>ありがとうございます。良い日をお願いします。</p>
                    <p>&nbsp;</p>
                    <p>よろしく</p>
                    <p>{ company_name}</p>
                    <p>{app_url}</p>',
                    'nl' => '<p>Hallo,</p>
                    <p>Welkom bij { app_name }</p>
                    <p>Beste { invoice_payment_name }</p>
                    <p>We hebben uw bedrag ontvangen { invoice_payment_amount } betaling voor { invoice_number } ingediend op datum { invoice_payment_date }</p>
                    <p>Uw { invoice_number } verschuldigde bedrag is { payment_dueAmount }</p>
                    <p>Wij waarderen uw snelle betaling en kijken uit naar verdere zaken met u in de toekomst.</p>
                    <p>Hartelijk dank en hebben een goede dag!!</p>
                    <p>&nbsp;</p>
                    <p>Betreft:</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'pl' => '<p>Witam,</p>
                    <p>Witamy w aplikacji {app_name }</p>
                    <p>Droga {invoice_payment_name }</p>
                    <p>Odebrano kwotę {invoice_payment_amount } płatności za {invoice_number } w dniu {invoice_payment_date }, kt&oacute;ry został zastąpiony przez użytkownika.</p>
                    <p>{invoice_number } Kwota należna: {payment_dueAmount }</p>
                    <p>Doceniamy Twoją szybką płatność i czekamy na kontynuację działalności gospodarczej z Tobą w przyszłości.</p>
                    <p>Dziękuję bardzo i mam dobry dzień!!</p>
                    <p>&nbsp;</p>
                    <p>W odniesieniu do</p>
                    <p>{company_name }</p>
                    <p>{app_url }</p>',
                    'ru' => '<p>Привет.</p>
                    <p>Вас приветствует { app_name }</p>
                    <p>Дорогая { invoice_payment_name }</p>
                    <p>Мы получили вашу сумму оплаты {invoice_payment_amount} для { invoice_number }, подавшей на дату { invoice_payment_date }</p>
                    <p>Ваша { invoice_number } Должная сумма-{ payment_dueAmount }</p>
                    <p>Мы ценим вашу своевременную оплату и надеемся на продолжение бизнеса с вами в будущем.</p>
                    <p>Большое спасибо и хорошего дня!!</p>
                    <p>&nbsp;</p>
                    <p>С уважением,</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'pt' => '<p>Oi,</p>
                    <p>Bem-vindo a {app_name}</p>
                    <p>Querido {invoice_payment_name}</p>
                    <p>N&oacute;s recibimos sua quantia {invoice_payment_amount} pagamento para {invoice_number} requisitado na data {invoice_payment_date}</p>
                    <p>Sua quantia {invoice_number} Due &eacute; {payment_dueAmount}</p>
                    <p>Agradecemos o seu pronto pagamento e estamos ansiosos para continuarmos os neg&oacute;cios com voc&ecirc; no futuro.</p>
                    <p>Muito obrigado e tenha um bom dia!!</p>
                    <p>&nbsp;</p>
                    <p>Considera,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'tr' => '<p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-bitişik harfler: common-ligatures;">Merhaba,</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-bitişik harfler: common-ligatures;">{app_name}</span></span></p> e hoş geldiniz
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-bitişik harfler: common-ligatures;">Sayın {invoice_payment_name}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-bitişik harfler: common-ligatures;">{invoice_payment_date}</span></span></p> tarihinde gönderdiğiniz {invoice_number} için {invoice_payment_amount} tutarındaki ödemenizi aldık
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-bitişik harfler: common-ligatures;">Ödenmesi gereken {invoice_number} tutarındaki tutar {payment_dueAmount}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-bitişik harfler: common-ligatures;">Hızlı ödemeniz için teşekkür ederiz ve gelecekte sizinle iş yapmaya devam etmeyi dört gözle bekliyoruz.</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-bitişik harfler: common-ligatures;">Çok teşekkür ederiz, iyi günler dilerim!!</span></span></p>
                    <p> </p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-bitişik harfler: common-ligatures;">Saygılarımızla,</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;"><span style="font-size: 15px; font-variant-bitişik harfler: ortak bitişik harfler;">{şirket_adı}</span></span></p>
                    <p><span style="color: #1d1c1d; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif;">
                    <span style="font-size: 15px; font-variant-ligatures: common-ligatures;">{app_url}</span></span></p>',
                    'pt-br' => '<p>Oi,</p>
                    <p>Bem-vindo a {app_name}</p>
                    <p>Querido {invoice_payment_name}</p>
                    <p>N&oacute;s recibimos sua quantia {invoice_payment_amount} pagamento para {invoice_number} requisitado na data {invoice_payment_date}</p>
                    <p>Sua quantia {invoice_number} Due &eacute; {payment_dueAmount}</p>
                    <p>Agradecemos o seu pronto pagamento e estamos ansiosos para continuarmos os neg&oacute;cios com voc&ecirc; no futuro.</p>
                    <p>Muito obrigado e tenha um bom dia!!</p>
                    <p>&nbsp;</p>
                    <p>Considera,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                ],
            ],
            'new_payment_reminder' => [
                'subject' => 'New Payment Reminder',
                'lang' => [
                    'ar' => '<p>عزيزي ، { payment_reminder_name }</p>
                    <p>آمل أن تكون بخير. هذا مجرد تذكير بأن الدفع على الفاتورة { invoice_payment_number } الاجمالي { invoice_payment_dueAmount } ، والتي قمنا بارسالها على { payment_reminder_date } مستحق اليوم.</p>
                    <p>يمكنك دفع مبلغ لحساب البنك المحدد على الفاتورة.</p>
                    <p>أنا متأكد أنت مشغول ، لكني أقدر إذا أنت يمكن أن تأخذ a لحظة ونظرة على الفاتورة عندما تحصل على فرصة.</p>
                    <p>إذا كان لديك أي سؤال مهما يكن ، يرجى الرد وسأكون سعيدا لتوضيحها.</p>
                    <p>&nbsp;</p>
                    <p>شكرا&nbsp;</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>
                    <p>&nbsp;</p>',
                    'zh' => '<p>亲爱的，{ payment_reminder_name}</p>
                    <p>希望您一切顺利。这只是一个提醒，我们于 { payment_reminder_date} 发送的发票 {invoice_ payment_number} 总应付金额 {invoice_ payment_dueAmount} 的付款将于今天到期。</p>
                    <p>您可以向发票上指定的银行帐户付款。</p>
                    <p>我确信您很忙，但如果您有机会花点时间查看一下发票，我将不胜感激。</p>
                    <p>如果您有任何疑问，请回复，我很乐意为您解答。</p>
                    <p> </p>
                    <p>谢谢，</p>
                    <p>{公司名称}</p>
                    <p>{app_url}</p>
                    <p> </p>',
                    'da' => '<p>K&aelig;re, { payment_reminder_name }</p>
                    <p>Dette er blot en p&aring;mindelse om, at betaling p&aring; faktura { invoice_payment_number } i alt { invoice_payment_dueAmount}, som vi sendte til { payment_reminder_date }, er forfalden i dag.</p>
                    <p>Du kan foretage betalinger til den bankkonto, der er angivet p&aring; fakturaen.</p>
                    <p>Jeg er sikker p&aring; du har travlt, men jeg ville s&aelig;tte pris p&aring;, hvis du kunne tage et &oslash;jeblik og se p&aring; fakturaen, n&aring;r du f&aring;r en chance.</p>
                    <p>Hvis De har nogen sp&oslash;rgsm&aring;l, s&aring; svar venligst, og jeg vil med gl&aelig;de tydeligg&oslash;re dem.</p>
                    <p>&nbsp;</p>
                    <p>Tak.&nbsp;</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>
                    <p>&nbsp;</p>',
                    'de' => '<p>Sehr geehrte/r, {payment_reminder_name}</p>
                    <p>Ich hoffe, Sie sind gut. Dies ist nur eine Erinnerung, dass die Zahlung auf Rechnung {invoice_payment_number} total {invoice_payment_dueAmount}, die wir gesendet am {payment_reminder_date} ist heute f&auml;llig.</p>
                    <p>Sie k&ouml;nnen die Zahlung auf das auf der Rechnung angegebene Bankkonto vornehmen.</p>
                    <p>Ich bin sicher, Sie sind besch&auml;ftigt, aber ich w&uuml;rde es begr&uuml;&szlig;en, wenn Sie einen Moment nehmen und &uuml;ber die Rechnung schauen k&ouml;nnten, wenn Sie eine Chance bekommen.</p>
                    <p>Wenn Sie irgendwelche Fragen haben, antworten Sie bitte und ich w&uuml;rde mich freuen, sie zu kl&auml;ren.</p>
                    <p>&nbsp;</p>
                    <p>Danke,&nbsp;</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>
                    <p>&nbsp;</p>',
                    'en' => '<p>Dear, {payment_reminder_name}</p>
                    <p>I hope you&rsquo;re well.This is just a reminder that payment on invoice {invoice_payment_number} total dueAmount {invoice_payment_dueAmount} , which we sent on {payment_reminder_date} is due today.</p>
                    <p>You can make payment to the bank account specified on the invoice.</p>
                    <p>I&rsquo;m sure you&rsquo;re busy, but I&rsquo;d appreciate if you could take a moment and look over the invoice when you get a chance.</p>
                    <p>If you have any questions whatever, please reply and I&rsquo;d be happy to clarify them.</p>
                    <p>&nbsp;</p>
                    <p>Thanks,&nbsp;</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>
                    <p>&nbsp;</p>',
                    'es' => '<p>Estimado, {payment_reminder_name}</p>
                    <p>Espero que est&eacute;s bien. Esto es s&oacute;lo un recordatorio de que el pago en la factura {invoice_payment_number} total {invoice_payment_dueAmount}, que enviamos en {payment_reminder_date} se vence hoy.</p>
                    <p>Puede realizar el pago a la cuenta bancaria especificada en la factura.</p>
                    <p>Estoy seguro de que est&aacute;s ocupado, pero agradecer&iacute;a si podr&iacute;as tomar un momento y mirar sobre la factura cuando tienes una oportunidad.</p>
                    <p>Si tiene alguna pregunta, por favor responda y me gustar&iacute;a aclararlas.</p>
                    <p>&nbsp;</p>
                    <p>Gracias,&nbsp;</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>
                    <p>&nbsp;</p>',
                    'fr' => '<p>Cher, { payment_reminder_name }</p>
                    <p>Jesp&egrave;re que vous &ecirc;tes bien, ce nest quun rappel que le paiement sur facture {invoice_payment_number}total { invoice_payment_dueAmount }, que nous avons envoy&eacute; le {payment_reminder_date} est d&ucirc; aujourdhui.</p>
                    <p>Vous pouvez effectuer le paiement sur le compte bancaire indiqu&eacute; sur la facture.</p>
                    <p>Je suis s&ucirc;r que vous &ecirc;tes occup&eacute;, mais je vous serais reconnaissant de prendre un moment et de regarder la facture quand vous aurez une chance.</p>
                    <p>Si vous avez des questions, veuillez r&eacute;pondre et je serais heureux de les clarifier.</p>
                    <p>&nbsp;</p>
                    <p>Merci,&nbsp;</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>
                    <p>&nbsp;</p>',
                    'he' => '<p>שלום, {payment_reminder_name}</p>
                    <p>אני מקווה ששלומך טוב. זוהי רק תזכורת לכך שהתשלום על החשבונית {invoice_payment_number} total dueAmount {invoice_payment_dueAmount} , ששלחנו בתאריך {payment_reminder_date}, יבוא היום.</p>
                    <p>תוכל לבצע תשלום לחשבון הבנק המצוין בחשבונית.</p>
                    <p>אני בטוח שאתה עסוק, אבל אשמח אם תוכל להקדיש רגע ולעיין בחשבונית כשתהיה לך הזדמנות.</p>
                    <p>אם יש לך שאלות כלשהן, אנא השב ואשמח להבהיר אותן.</p>
                    <p> </p>
                    <p>תודה, </p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>
                    <p> </p>',
                    'it' => '<p>Caro, {payment_reminder_name}</p>
                    <p>Spero che tu stia bene, questo &egrave; solo un promemoria che il pagamento sulla fattura {invoice_payment_number} totale {invoice_payment_dueAmount}, che abbiamo inviato su {payment_reminder_date} &egrave; dovuto oggi.</p>
                    <p>&Egrave; possibile effettuare il pagamento al conto bancario specificato sulla fattura.</p>
                    <p>Sono sicuro che sei impegnato, ma apprezzerei se potessi prenderti un momento e guardare la fattura quando avrai una chance.</p>
                    <p>Se avete domande qualunque, vi prego di rispondere e sarei felice di chiarirle.</p>
                    <p>&nbsp;</p>
                    <p>Grazie,&nbsp;</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>
                    <p>&nbsp;</p>',
                    'ja' => '<p>ID、 {payment_reminder_name}</p>
                    <p>これは、 { invoice_payment_dueAmount} の合計 {invoice_payment_dueAmount } に対する支払いが今日予定されていることを思い出させていただきたいと思います。</p>
                    <p>請求書に記載されている銀行口座に対して支払いを行うことができます。</p>
                    <p>お忙しいのは確かですが、機会があれば、少し時間をかけてインボイスを見渡すことができればありがたいのですが。</p>
                    <p>何か聞きたいことがあるなら、お返事をお願いしますが、喜んでお答えします。</p>
                    <p>&nbsp;</p>
                    <p>ありがとう。&nbsp;</p>
                    <p>{ company_name}</p>
                    <p>{app_url}</p>
                    <p>&nbsp;</p>',
                    'nl' => '<p>Geachte, { payment_reminder_name }</p>
                    <p>Ik hoop dat u goed bent. Dit is gewoon een herinnering dat betaling op factuur { invoice_payment_number } totaal { invoice_payment_dueAmount }, die we verzonden op { payment_reminder_date } is vandaag verschuldigd.</p>
                    <p>U kunt betaling doen aan de bankrekening op de factuur.</p>
                    <p>Ik weet zeker dat je het druk hebt, maar ik zou het op prijs stellen als je even over de factuur kon kijken als je een kans krijgt.</p>
                    <p>Als u vragen hebt, beantwoord dan uw antwoord en ik wil ze graag verduidelijken.</p>
                    <p>&nbsp;</p>
                    <p>Bedankt.&nbsp;</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>
                    <p>&nbsp;</p>',
                    'pl' => '<p>Drogi, {payment_reminder_name }</p>
                    <p>Mam nadzieję, że jesteś dobrze. To jest tylko przypomnienie, że płatność na fakturze {invoice_payment_number } total {invoice_payment_dueAmount }, kt&oacute;re wysłaliśmy na {payment_reminder_date } jest dzisiaj.</p>
                    <p>Płatność można dokonać na rachunek bankowy podany na fakturze.</p>
                    <p>Jestem pewien, że jesteś zajęty, ale byłbym wdzięczny, gdybyś m&oacute;gł wziąć chwilę i spojrzeć na fakturę, kiedy masz szansę.</p>
                    <p>Jeśli masz jakieś pytania, proszę o odpowiedź, a ja chętnie je wyjaśniam.</p>
                    <p>&nbsp;</p>
                    <p>Dziękuję,&nbsp;</p>
                    <p>{company_name }</p>
                    <p>{app_url }</p>
                    <p>&nbsp;</p>',
                    'ru' => '<p>Уважаемый, { payment_reminder_name }</p>
                    <p>Я надеюсь, что вы хорошо. Это просто напоминание о том, что оплата по счету { invoice_payment_number } всего { invoice_payment_dueAmount }, которое мы отправили в { payment_reminder_date }, сегодня.</p>
                    <p>Вы можете произвести платеж на банковский счет, указанный в счете-фактуре.</p>
                    <p>Я уверена, что ты занята, но я была бы признательна, если бы ты смог бы поглядеться на счет, когда у тебя появится шанс.</p>
                    <p>Если у вас есть вопросы, пожалуйста, ответьте, и я буду рад их прояснить.</p>
                    <p>&nbsp;</p>
                    <p>Спасибо.&nbsp;</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>
                    <p>&nbsp;</p>',
                    'pt' => '<p>Querido, {payment_reminder_name}</p>
                    <p>Espero que voc&ecirc; esteja bem. Este &eacute; apenas um lembrete de que o pagamento na fatura {invoice_payment_number} total {invoice_payment_dueAmount}, que enviamos em {payment_reminder_date} &eacute; devido hoje.</p>
                    <p>Voc&ecirc; pode fazer o pagamento &agrave; conta banc&aacute;ria especificada na fatura.</p>
                    <p>Eu tenho certeza que voc&ecirc; est&aacute; ocupado, mas eu agradeceria se voc&ecirc; pudesse tirar um momento e olhar sobre a fatura quando tiver uma chance.</p>
                    <p>Se voc&ecirc; tiver alguma d&uacute;vida o que for, por favor, responda e eu ficaria feliz em esclarec&ecirc;-las.</p>
                    <p>&nbsp;</p>
                    <p>Obrigado,&nbsp;</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>
                    <p>&nbsp;</p>',
                    'tr' => '<p>Sayın {payment_reminder_name}</p>
                    <p>Umarım iyisinizdir. Bu yalnızca, {payment_reminder_date} tarihinde gönderdiğimiz {invoice_payment_number} toplam vade tutarı {invoice_payment_dueAmount} tutarındaki faturanın ödemesinin bugün yapılması gerektiğini hatırlatma amaçlıdır.</p>
                    <p>Faturada belirtilen banka hesabına ödeme yapabilirsiniz.</p>
                    <p>Yoğun olduğunuzdan eminim ama fırsat bulduğunuzda bir dakikanızı ayırıp faturaya göz atarsanız sevinirim.</p>
                    <p>Herhangi bir sorunuz varsa, lütfen yanıtlayın; bunları açıklığa kavuşturmaktan memnuniyet duyarım.</p>
                    <p> </p>
                    <p>Teşekkürler, </p>
                    <p>{şirket_adı</p>
                    <p>{app_url}</p>
                    <p> </p>',
                    'pt-br' => '<p>Querido, {payment_reminder_name}</p>
                    <p>Espero que voc&ecirc; esteja bem. Este &eacute; apenas um lembrete de que o pagamento na fatura {invoice_payment_number} total {invoice_payment_dueAmount}, que enviamos em {payment_reminder_date} &eacute; devido hoje.</p>
                    <p>Voc&ecirc; pode fazer o pagamento &agrave; conta banc&aacute;ria especificada na fatura.</p>
                    <p>Eu tenho certeza que voc&ecirc; est&aacute; ocupado, mas eu agradeceria se voc&ecirc; pudesse tirar um momento e olhar sobre a fatura quando tiver uma chance.</p>
                    <p>Se voc&ecirc; tiver alguma d&uacute;vida o que for, por favor, responda e eu ficaria feliz em esclarec&ecirc;-las.</p>
                    <p>&nbsp;</p>
                    <p>Obrigado,&nbsp;</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>
                    <p>&nbsp;</p>',
                ],
            ],
            'new_bill_payment' => [
                'subject' => 'New Bill Payment',
                'lang' => [
                    'ar' => '<p>مرحبا ، { payment_name }</p><p>مرحبا بك في { app_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">نحن نكتب لإبلاغكم بأننا قد أرسلنا مدفوعات (payment_الفاتورة) } الخاصة بك.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">لقد أرسلنا قيمتك { payment_cama } لأجل { payment_فاتورة } قمت بالاحالة في التاريخ { payment_date } من خلال { payment_method }.</span></p><p>شكرا جزيلا لك وطاب يومك ! !!!</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ company_name }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ app_url }</span><br></p>',
                    'zh' => '<p>您好，{ payment_name}</p><p>欢迎来到 {app_name}</p><p><span style="font-family: var(--bs-body-font-family); font -weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">我们写信通知您，我们已发送您的 { payment_bill} 付款.</span></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight) ; text-align: var(--bs-body-text-align);">我们已发送您于 { payment_date} 通过 { payment_method} 提交的金额为 { payment_amount} 的 { payment_bill} 付款。</span></span></ p><p><span style="font-family: var(--bs-body-font-family); 字体粗细: var(--bs-body-font-weight); 文本对齐: var( --bs-body-text-align);">非常感谢您，祝您有美好的一天！！！</span></p><p>{company_name}</p><p>{app_url} </p>',
                    'da' => '<p>Hej {payment_name}</p><p>Velkommen til {app_name}</p><p><span style="font-family: var(--bs-body-font-family); font -weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Vi skriver for at informere dig om, at vi har sendt din {payment_bill}-betaling .</span></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight) ; text-align: var(--bs-body-text-align);">Vi har sendt dit beløb {payment_amount} betaling for {payment_bill} indsendt  på datoen {payment_date} via {payment_method}.</span></ p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var( --bs-body-text-align);">Mange tak og god dag !!!!</span></p><p>{company_name}</p><p>{app_url} </p>',
                    'de' => '<p>Hallo, {payment_name}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Willkommen bei {app_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Wir schreiben Ihnen mitzuteilen, dass wir Ihre Zahlung von {payment_bill} gesendet haben.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Wir haben Ihre Zahlung {payment_amount} Zahlung für {payment_bill} am Datum {payment_date} über {payment_method} gesendet.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Vielen Dank und haben einen guten Tag! !!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p>',
                    'en' => '<p>Hi , {payment_name}</p><p>Welcome to {app_name}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">We are writing to inform you that we has sent your {payment_bill} payment.</span></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">We has sent your amount {payment_amount} payment for {payment_bill} submited&nbsp; on date {payment_date} via {payment_method}.</span></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Thank You very much and have a good day !!!!</span></p><p>{company_name}</p><p>{app_url}</p>',
                    'es' => '<p>Hola, {nombre_pago}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Bienvenido a {app_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Estamos escribiendo para informarle que hemos enviado su pago {payment_bill}.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Hemos enviado su importe {payment_amount} pago para {payment_bill} submitado en la fecha {payment_date} a través de {payment_method}.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Thank You very much and have a good day! !!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{nombre_empresa}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p>',
                    'fr' => '<p>Salut, { payment_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Bienvenue dans { app_name }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Nous vous écrivons pour vous informer que nous avons envoyé votre paiement { payment_bill }.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Nous avons envoyé votre paiement { payment_amount } pour { payment_bill } soumis à la date { payment_date } via { payment_method }.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Merci beaucoup et avez un bon jour ! !!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ nom_entreprise }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ adresse_url }</span><br></p>',
                    'he' => '<p>היי, {payment_name}</p><p>ברוכים הבאים אל {app_name}</p><p><span style="font-family: var(--bs-body-font-family); font -weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">אנו כותבים כדי להודיע ​​לך ששלחנו את התשלום שלך ב-{payment_bill} .</span></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight) ; text-align: var(--bs-body-text-align);">שלחנו את הסכום שלך {payment_amount} תשלום עבור {payment_bill} שנשלח  בתאריך {payment_date} באמצעות {payment_method}.</span></ p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var( --bs-body-text-align);">תודה רבה ויום טוב!!!!</span></p><p>{company_name}</p><p>{app_url} </p>',
                    'it' => '<p>Ciao, {payment_name}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Benvenuti in {app_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Scriviamo per informarti che abbiamo inviato il tuo pagamento {payment_bill}.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Abbiamo inviato la tua quantità {payment_amount} pagamento per {payment_bill} subita alla data {payment_date} tramite {payment_method}.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Grazie mille e buona giornata! !!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p>',
                    'ja' => '<p>こんにちは、 {payment_name}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_name} へようこそ</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{payment_紙幣} の支払いを送信したことをお知らせするために執筆しています。</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{payment_date } に提出された {payment_議案} に対する金額 {payment_金額} の支払いは、 {payment_method}を介して送信されました。</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">ありがとうございます。良い日をお願いします。</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ company_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p>',
                    'nl' => '<p>Hallo, { payment_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Welkom bij { app_name }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Wij schrijven u om u te informeren dat wij uw betaling van { payment_bill } hebben verzonden.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">We hebben uw bedrag { payment_amount } betaling voor { payment_bill } verzonden op datum { payment_date } via { payment_method }.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Hartelijk dank en hebben een goede dag! !!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ bedrijfsnaam }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ app_url }</span><br></p>',
                    'pl' => '<p>Witaj, {payment_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Witamy w aplikacji {app_name }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Piszemy, aby poinformować Cię, że wysłaliśmy Twoją płatność {payment_bill }.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Twoja kwota {payment_amount } została wysłana przez użytkownika {payment_bill } w dniu {payment_date } za pomocą metody {payment_method }.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Dziękuję bardzo i mam dobry dzień! !!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url }</span><br></p>',
                    'ru' => '<p>Привет, { payment_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Вас приветствует { app_name }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Мы пишем, чтобы сообщить вам, что мы отправили вашу оплату { payment_bill }.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Мы отправили вашу сумму оплаты { payment_amoon } для { payment_bill }, подав на дату { payment_date } через { payment_method }.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Большое спасибо и хорошего дня! !!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ company_name }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ app_url }</span><br></p>',
                    'pt' => '<p>Oi, {payment_name}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Bem-vindo a {app_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Estamos escrevendo para informá-lo que enviamos o seu pagamento {payment_bill}.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Nós enviamos sua quantia {payment_amount} pagamento por {payment_bill} requisitado na data {payment_date} via {payment_method}.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Muito obrigado e tenha um bom dia! !!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p>',
                    'tr' => '<p>Merhaba, {payment_name}</p><p>{app_name}</p><p><span style="font-family: var(--bs-body-font-family); font a hoş geldiniz -weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Size {payment_bill} ödemenizi gönderdiğimizi bildirmek için yazıyoruz. .</span></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight) ; text-align: var(--bs-body-text-align);">{payment_date} tarihinde {payment_bill} için gönderdiğiniz {payment_amount} tutarındaki ödemenizi {payment_method} aracılığıyla gönderdik.</span></span></span></span></span></span></span></span></br></br></br></br></br></br></br></br></br></br></br></br></br></br> p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var( --bs-body-text-align);">Çok teşekkür ederiz ve iyi günler dilerim !!!!span></p><p>{company_name}</p><p>{app_url} </p>',
                    'pt' => '<p>Oi, {payment_name}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Bem-vindo a {app_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Estamos escrevendo para informá-lo que enviamos o seu pagamento {payment_bill}.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Nós enviamos sua quantia {payment_amount} pagamento por {payment_bill} requisitado na data {payment_date} via {payment_method}.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Muito obrigado e tenha um bom dia! !!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p>',

                ],
            ],
            'bill_resent' => [
                'subject' => 'Bill Resent',
                'lang' => [
                    'ar' => '<p>مرحبا ، { bill_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">مرحبا بك في { app_name }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">أتمنى أن يجدك هذا البريد الإلكتروني جيدا برجاء الرجوع الى رقم الفاتورة الملحقة { bill_bill } لخدمة المنتج / الخدمة.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ببساطة اضغط على الاختيار بأسفل.</span></p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; { bill_url }</p><p>إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">شكرا لعملك ! !!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Regards,</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ company_name }</span></p><p>{ app_url }</p><div><br></div>',
                    'zh' => '<p><span style="font-family: var(--bs-body-font-family); 字体粗细: var(--bs-body-font-weight); 文本对齐: var(-- bs-body-text-align);">嗨，{bill_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family ); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">欢迎使用 {app_name}</span><br> </p><p><span style="font-family: var(--bs-body-font-family); 字体粗细: var(--bs-body-font-weight); 文本对齐: var(--bs-body-text-align);">希望这封电子邮件能让您满意！请参阅随附的产品/服务帐单编号 {bill_bill}。</span><br></p><p><span style="font-family: var(--bs-body-font-family); font -weight: var(--bs-body-font-weight); 文本对齐: var(--bs-body-text-align);">                                                                                                                   只需点击下面的按钮即可。</span><br>< /p><p><span style="font-family: var(--bs-body-font-family); 字体粗细: var(--bs-body-font-weight); 文本对齐: var (--bs-body-text-align);">                                                                                                                                  {bill_url}</span></p><p>如果您有任何疑问，请随时与我们联系。</p><p><span style= “字体系列：var（--bs-body-font-family）；字体粗细：var（--bs-body-font-weight）；文本对齐：var（--bs-body-text-align） );">感谢您的惠顾！！！</span><br></p><p><span style="font-family: var(--bs-body-font-family); font -权重：var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">问候，</span><br></p><p><span style="font-family: var(--bs-正文字体系列）；字体粗细：var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><br></p><p><span style="font-family: var(--bs -正文字体系列）；字体粗细：var(--bs-body-font-weight);文本对齐：var(--bs-body-text-align);">{app_url}</span><br></p><div><br></div>',
                    'da' => '<p>Hej, { bill_name }</p><p>Velkommen til { app_name }</p><p>Håber denne e-mail finder dig godt! Se vedlagte fakturanummer { bill_bill } for product/service.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Klik på knappen nedenfor.</span></p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;{ bill_url }</p><p>Du er velkommen til at række ud, hvis du har nogen spørgsmål.</p><p>Tak for din virksomhed! !!!</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Med venlig hilsen</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ company_name }</span></p><p>{ app_url }</p>',
                    'de' => '<p>Hi, {bill_name}</p><p>Willkommen bei {app_name}</p><p>Hoffe, diese E-Mail findet dich gut! Bitte sehen Sie die angehängte Rechnungsnummer {bill_bill} für Produkt/Service an.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;Klicken Sie einfach auf den Button unten.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; {bill_url}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Fühlen Sie sich frei, wenn Sie Fragen haben.</span></p><p>Vielen Dank für Ihr Geschäft! !!!</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Betrachtet,</span></p><p>{company_name}</p><p>{app_url}</p>',
                    'en' => '<p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Hi , {bill_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Welcome to {app_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Hope this email finds you well! Please see attached bill number {bill_bill} for product/service.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Simply click on the button below .</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;{bill_url}</span></p><p>Feel free to reach out if you have any questions.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Thank You for your business !!!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Regards,</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p><div><br></div>',
                    'es' => '<p>Hi, {nombre_billar}</p><p>Bienvenido a {app_name}</p><p>¡Espero que este email le encuentre bien! Consulte el número de factura adjunto {bill_bill} para el producto/servicio.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Simplemente haga clic en el botón de abajo.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;{bill_url}</p><p>Siéntase libre de llegar si usted tiene alguna pregunta.</p><p>Thank You for your business! !!!</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Considerando,</span></p><p>{nombre_empresa}</p><p>{app_url}</p><div><br></div>',
                    'fr' => '<p>Salut, { nom_facturation }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Bienvenue dans { app_name }</span></p><p>Jespère que ce courriel vous trouve bien ! Veuillez consulter le numéro de facture { factur_bill } associé au produit / service.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Cliquez simplement sur le bouton ci-dessous.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;{ url-facturation }</span></p><p>Nhésitez pas à nous contacter si vous avez des questions.</p><p>Merci pour votre entreprise ! !!!</p><p>Regards,</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ nom_entreprise }</span></p><p>{ adresse_url }</p>',
                    'he' => '<p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(-- bs-body-text-align);>היי, {bill_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family ); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">ברוכים הבאים אל {app_name}</span><br> </p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">מקווה שהמייל הזה ימצא אותך היטב! ראה את מספר החשבון המצורף {bill_bill} עבור מוצר/שירות.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font -weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">                                                                                                 פשוט לחץ על הלחצן למטה .</span><br>< /p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var (--bs-body-text-align);">                                                                                                              {bill_url}</span></p><p>אל תהסס לפנות אם יש לך שאלות.</p><p><span style= "font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align );">תודה על העסק שלך !!!!</span><br></p><p><span style="font-family: var(--bs-body-font-family); font -משקל: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">בברכה,</span><br></p><p><span style="font-family: var(--bs- גוף-גופן-משפחה); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><br></p><p><span style="font-family: var(--bs -גוף-גופן-משפחה); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p><div><br></div>',
                    'it' => '<p>Ciao, {bill_name}</p><p>Benvenuti in {app_name}</p><p>Spero che questa email ti trovi bene! Si prega di consultare il numero di fattura allegato {bill_bill} per il prodotto/servizio.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Semplicemente clicca sul pulsante sottostante.</span></p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;{bill_url}</p><p>Sentiti libero di raggiungere se hai domande.</p><p>Grazie per il tuo business! !!!</p><p>Riguardo,</p><p>{company_name}</p><p>{app_url}</p>',
                    'ja' => '<p>こんにちは、 {bill_name}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_name} へようこそ</span></p><p>この E メールでよくご確認ください。 製品 / サービスの添付された請求番号 {bill_紙幣} を参照してください。</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; 以下のボタンをクリックしてください。</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;{bill_url}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">質問がある場合は、自由に連絡してください。</span></p><p>お客様のビジネスに感謝しています。</p><p>よろしく</p><p>{ company_name}</p><p>{app_url}</p><div><br></div>',
                    'nl' => '<p>Hallo, { bill_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Welkom bij { app_name }</span></p><p>Hoop dat deze e-mail je goed vindt! Zie het bijgesloten factuurnummer { bill_bill } voor product/service.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Klik gewoon op de knop hieronder.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;{ bill_url }</p><p>Voel je vrij om uit te reiken als je vragen hebt.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Dank U voor uw bedrijf! !!!</span></p><p>Betreft:</p><p>{ bedrijfsnaam }</p><p>{ app_url }</p><div><br></div>',
                    'pl' => '<p>Witaj, {nazwa_faktury }</p><p>Witamy w aplikacji {app_name }</p><p>Mam nadzieję, że ta wiadomość znajdzie Cię dobrze! Zapoznaj się z załączonym numerem rachunku {bill_bill } dla produktu/usługi.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Wystarczy kliknąć na przycisk poniżej.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;{adres_URL_faktury }</p><p>Czuj się swobodnie, jeśli masz jakieś pytania.</p><p>Dziękujemy za swój biznes! !!!</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">W odniesieniu do</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url }</span><br></p><div><br></div>',
                    'ru' => '<p>Привет, { bill_name }</p><p>Вас приветствует { app_name }</p><p>Надеюсь, это электронное письмо найдет вас хорошо! См. прилагаемый номер счета { bill_bill } для product/service.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Просто нажмите на кнопку внизу.</span></p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; { bill_url }</p><p>Не стеснитесь, если у вас есть вопросы.</p><p>Спасибо за ваш бизнес! !!!</p><p>С уважением,</p><p>{ company_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{ app_url }</span><br></p>',
                    'pt' => '<p>Oi, {bill_name}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Bem-vindo a {app_name}</span></p><p>Espero que este e-mail encontre você bem! Por favor, consulte o número de faturamento conectado {bill_bill} para produto/serviço.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Basta clicar no botão abaixo.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;{bill_url}</p><p>Sinta-se à vontade para alcançar fora se você tiver alguma dúvida.</p><p>Obrigado pelo seu negócio! !!!</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Considera,</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p><div><br></div>',
                    'tr' => '<p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(-- bs-body-text-align);">Merhaba, {bill_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family) ); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_name}</span><br> e hoş geldiniz </p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Umarım bu e-posta sizi bulur! Lütfen ürün/hizmet için ekteki {bill_bill} fatura numarasına bakın.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font -ağırlık: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">                                                                                                                 Aşağıdaki düğmeyi tıklamanız yeterlidir .</span><br>< /p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var (--bs-body-text-align);">                                                                                                                                {bill_url}</span></p><p>Sorularınız varsa bize ulaşmaktan çekinmeyin.</p><p><span style= "font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align) );">İlginiz için teşekkürler !!!!span><br></p><p><span style="font-family: var(--bs-body-font-family); font -ağırlık: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Saygılarımızla,</span><br></p><p><span style="font-family: var(--bs- gövde-yazı tipi-ailesi); yazı tipi ağırlığı: var(--bs-body-font-ağırlığı); text-align: var(--bs-body-text-align);">{şirket_adı}</span><br></p><p><span style="font-family: var(--bs) -body-font-ailesi); yazı tipi ağırlığı: var(--bs-body-font-ağırlığı); metin hizalama: var(--bs-body-text-align);">{app_url}</span><br></p><div><br></div>',
                    'pt-br' => '<p>Oi, {bill_name}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Bem-vindo a {app_name}</span></p><p>Espero que este e-mail encontre você bem! Por favor, consulte o número de faturamento conectado {bill_bill} para produto/serviço.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Basta clicar no botão abaixo.</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;{bill_url}</p><p>Sinta-se à vontade para alcançar fora se você tiver alguma dúvida.</p><p>Obrigado pelo seu negócio! !!!</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Considera,</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p><div><br></div>',

                ],
            ],
            'proposal_sent' => [
                'subject' => 'Proposal Sent',
                'lang' => [
                    'ar' => '<p>مرحبا ، { proposal_name }</p>
                    <p>أتمنى أن يجدك هذا البريد الإلكتروني جيدا برجاء الرجوع الى رقم الاقتراح المرفق { proposal_number } للمنتج / الخدمة.</p>
                    <p>اضغط ببساطة على الاختيار بأسفل</p>
                    <p>{ proposal_url }</p>
                    <p>إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة.</p>
                    <p>شكرا لعملك ! !</p>
                    <p>&nbsp;</p>
                    <p>Regards,</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'zh' => '<p>嗨，{proposal_name}</p>
                    <p>希望这封电子邮件能让您满意！请参阅随附的产品/服务提案编号 {proposal_number}。</p>
                    <p>只需点击下面的按钮</p>
                    <p>{proposal_url}</p>
                    <p>如果您有任何疑问，请随时与我们联系。</p>
                    <p>感谢您的惠顾！！</p>
                    <p> </p>
                    <p>问候，</p>
                    <p>{公司名称}</p>
                    <p>{app_url}</p>',
                    'da' => '<p>Hej, {proposal__name }</p>
                    <p>H&aring;ber denne e-mail finder dig godt! Se det vedh&aelig;ftede forslag nummer { proposal_number } for product/service.</p>
                    <p>klik bare p&aring; knappen nedenfor</p>
                    <p>{ proposal_url }</p>
                    <p>Du er velkommen til at r&aelig;kke ud, hvis du har nogen sp&oslash;rgsm&aring;l.</p>
                    <p>Tak for din virksomhed!</p>
                    <p>&nbsp;</p>
                    <p>Med venlig hilsen</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'de' => '<p>Hi, {proposal_name}</p>
                    <p>Hoffe, diese E-Mail findet dich gut! Bitte sehen Sie die angeh&auml;ngte Vorschlagsnummer {proposal_number} f&uuml;r Produkt/Service an.</p>
                    <p>Klicken Sie einfach auf den Button unten</p>
                    <p>{proposal_url}</p>
                    <p>F&uuml;hlen Sie sich frei, wenn Sie Fragen haben.</p>
                    <p>Vielen Dank f&uuml;r Ihr Unternehmen!!</p>
                    <p>&nbsp;</p>
                    <p>Betrachtet,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'en' => '<p>Hi, {proposal_name}</p>
                    <p>Hope this email ﬁnds you well! Please see attached proposal number {proposal_number} for product/service.</p>
                    <p>simply click on the button below</p>
                    <p>{proposal_url}</p>
                    <p>Feel free to reach out if you have any questions.</p>
                    <p>Thank you for your business!!</p>
                    <p>&nbsp;</p>
                    <p>Regards,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'es' => '<p>Hi, {proposal_name}</p>
                    <p>&iexcl;Espero que este email le encuentre bien! Consulte el n&uacute;mero de propuesta adjunto {proposal_number} para el producto/servicio.</p>
                    <p>simplemente haga clic en el bot&oacute;n de abajo</p>
                    <p>{proposal_url}</p>
                    <p>Si&eacute;ntase libre de llegar si usted tiene alguna pregunta.</p>
                    <p>&iexcl;Gracias por su negocio!!</p>
                    <p>&nbsp;</p>
                    <p>Considerando,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'fr' => '<p>Salut, {proposal_name}</p>
                    <p>Jesp&egrave;re que ce courriel vous trouve bien ! Veuillez consulter le num&eacute;ro de la proposition jointe {proposal_number} pour le produit/service.</p>
                    <p>Il suffit de cliquer sur le bouton ci-dessous</p>
                    <p>{proposal_url}</p>
                    <p>Nh&eacute;sitez pas &agrave; nous contacter si vous avez des questions.</p>
                    <p>Merci pour votre entreprise ! !</p>
                    <p>&nbsp;</p>
                    <p>Regards,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'he' => '<p>שלום, {proposal_name}</p>
                    <p>מקווה שדוא"ל זה ימצא אותך היטב! ראה את מספר ההצעה המצורפת {proposal_number} עבור מוצר/שירות.</p>
                    <p>פשוט לחץ על הלחצן למטה</p>
                    <p>{proposal_url}</p>
                    <p>אל תהסס לפנות אם יש לך שאלות.</p>
                    <p>תודה על העסק שלך!!</p>
                    <p> </p>
                    <p>בברכה,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'it' => '<p>Ciao, {proposal_name}</p>
                    <p>Spero che questa email ti trovi bene! Si prega di consultare il numero di proposta allegato {proposal_number} per il prodotto/servizio.</p>
                    <p>semplicemente clicca sul pulsante sottostante</p>
                    <p>{proposal_url}</p>
                    <p>Sentiti libero di raggiungere se hai domande.</p>
                    <p>Grazie per il tuo business!!</p>
                    <p>&nbsp;</p>
                    <p>Riguardo,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'ja' => '<p>こんにちは、 {proposal_name}</p>
                    <p>この E メールでよくご確認ください。 製品 / サービスの添付されたプロポーザル番号 {proposal_number} を参照してください。</p>
                    <p>下のボタンをクリックするだけで</p>
                    <p>{proposal_url}</p>
                    <p>質問がある場合は、自由に連絡してください。</p>
                    <p>お客様のビジネスに感謝します。</p>
                    <p>&nbsp;</p>
                    <p>よろしく</p>
                    <p>{ company_name}</p>
                    <p>{app_url}</p>',
                    'nl' => '<p>Hallo, {proposal_name}</p>
                    <p>Hoop dat deze e-mail je goed vindt! Zie bijgevoegde nummer { proposal_number } voor product/service.</p>
                    <p>gewoon klikken op de knop hieronder</p>
                    <p>{ proposal_url }</p>
                    <p>Voel je vrij om uit te reiken als je vragen hebt.</p>
                    <p>Dank u voor uw bedrijf!!</p>
                    <p>&nbsp;</p>
                    <p>Betreft:</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'pl' => '<p>Witaj, {proposal_name}</p>
                    <p>Mam nadzieję, że ta wiadomość znajdzie Cię dobrze! Proszę zapoznać się z załączonym numerem wniosku {proposal_number} dla produktu/usługi.</p>
                    <p>po prostu kliknij na przycisk poniżej</p>
                    <p>{proposal_url}</p>
                    <p>Czuj się swobodnie, jeśli masz jakieś pytania.</p>
                    <p>Dziękujemy za prowadzenie działalności!!</p>
                    <p>&nbsp;</p>
                    <p>W odniesieniu do</p>
                    <p>{company_name }</p>
                    <p>{app_url }</p>',
                    'ru' => '<p>Здравствуйте, { proposal_name }</p>
                    <p>Надеюсь, это электронное письмо найдет вас хорошо! См. вложенное предложение номер { proposal_number} для product/service.</p>
                    <p>просто нажмите на кнопку внизу</p>
                    <p>{ proposal_url}</p>
                    <p>Не стеснитесь, если у вас есть вопросы.</p>
                    <p>Спасибо за ваше дело!</p>
                    <p>&nbsp;</p>
                    <p>С уважением,</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'pt' => '<p>Oi, {proposal_name}</p>
                    <p>Espero que este e-mail encontre voc&ecirc; bem! Por favor, consulte o n&uacute;mero da proposta anexada {proposal_number} para produto/servi&ccedil;o.</p>
                    <p>basta clicar no bot&atilde;o abaixo</p>
                    <p>{proposal_url}</p>
                    <p>Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</p>
                    <p>Obrigado pelo seu neg&oacute;cio!!</p>
                    <p>&nbsp;</p>
                    <p>Considera,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'tr' => '<p>Merhaba {teklif_adı</p>
                    <p>Umarım bu e-posta sizi iyi bulur! Lütfen ürün/hizmet için ekteki {proposal_number} numaralı teklife bakın.</p>
                    <p>Aşağıdaki düğmeyi tıklamanız yeterlidir</p>
                    <p>{teklif_url}</p>
                    <p>Sorularınız varsa bize ulaşmaktan çekinmeyin.</p>
                    <p>İşiniz için teşekkür ederiz!</p>
                    <p> </p>
                    <p>Saygılarımızla,</p>
                    <p>{şirket_adı</p>
                    <p>{app_url}</p>',
                    'pt' => '<p>Oi, {proposal_name}</p>
                    <p>Espero que este e-mail encontre voc&ecirc; bem! Por favor, consulte o n&uacute;mero da proposta anexada {proposal_number} para produto/servi&ccedil;o.</p>
                    <p>basta clicar no bot&atilde;o abaixo</p>
                    <p>{proposal_url}</p>
                    <p>Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</p>
                    <p>Obrigado pelo seu neg&oacute;cio!!</p>
                    <p>&nbsp;</p>
                    <p>Considera,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                ],
            ],
            'complaint_resent' => [
                'subject' => 'Complaint Resent',
                'lang' => [
                    'ar' => '<p>مرحبا</p><p>مرحبا بك في { app_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">(د) إدارة الموارد البشرية / الشركة لإرسال خطاب الشكاوى.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">عزيزي { demyt_name }</span></p><p>أود أن أبلغ عن صراع بينك وبين الشخص الآخر وقد وقعت عدة حوادث خلال الأيام القليلة الماضية ، وأشعر أن الوقت قد حان للإبلاغ عن شكوى رسمية ضده / هي.</p><p>إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة.</p><p>شكرا لك</p><p>Regards,</p><p>قسم الموارد البشرية</p><p>{ company_name }</p><p>{ app_url }</p><div><br></div>',
                    'zh' => '<p><font color="#1d1c1d"face="Slack-Lato、Slack-Fractions、appleLogo、sans-serif"><span style="font-size: 15px; font-variant-ligatures: common-ligatures; ">嗨，</span></font></p><p><span style="font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29) ; 字体系列：Slack-Lato、Slack-Fractions、appleLogo、sans-serif；字体粗细：var(--bs-body-font-weight)；文本对齐：var(--bs-body-text- align);">欢迎来到 {app_name}</span><br></p><p><font color="#1d1c1d"face="Slack-Lato, Slack-Fractions, appleLogo, sans-serif"> <span style="font-size: 15px; font-variant-ligatures: common-ligatures;">人力资源部门/公司发送投诉信。<br></span></font></p><p> <font color="#1d1c1d"face="Slack-Lato、Slack-Fractions、appleLogo、sans-serif"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">亲爱的{complaint_name}</span></font></p><p>我想举报您与他人之间的冲突。过去几天发生了几起事件，我觉得是时候对他/她提出正式投诉了。</p><p>如果您有任何疑问，请随时与我们联系。</p> <p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common -ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">谢谢，</span></p> <p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common -ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">问候，</span></p>< p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-连字; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">人力资源部门。</span></p>< p><span style="颜色: rgb(29, 28, 29);字体系列：Slack-Lato、Slack-Fractions、appleLogo、sans-serif；字体大小：15px；字体变体连字：常见连字；字体粗细：var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><span style="color: rgb(29, 28, 29);字体系列：Slack-Lato、Slack-Fractions、appleLogo、sans-serif；字体大小：15px；字体变体连字：常见连字；字体粗细：var(--bs-body-font-weight);文本对齐：var(--bs-body-text-align);"><br></span></p><p><span style="font-size: 15px;字体变体连字：常见连字；颜色：rgb(29,28,29)；字体系列：Slack-Lato、Slack-Fractions、appleLogo、sans-serif；字体粗细：var(--bs-body-font-weight);文本对齐：var(--bs-body-text-align);">{app_url}</span><br></p>',
                    'da' => '<p>Hej.</p><p>Velkommen til { app_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">HR department/company to send klager brev.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Kære { klaint_name }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Jeg vil gerne anmelde en konflikt mellem dig og den anden person. Der har været flere tilfælde i løbet af de seneste dage, og jeg mener, at tiden er inde til at anmelde en formel klage over for ham.</span></p><p>Du er velkommen til at række ud, hvis du har nogen spørgsmål.</p><p>Tak.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Med venlig hilsen</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">HR-afdelingen.</span></p><p>{ company_name }</p><p>{ app_url }</p><div><br></div>',
                    'de' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Hi,</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Willkommen bei {app_name}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Personalabteilung/Unternehmen, um Beschwerdeschreiben zu versenden.</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Sehr geehrter {beanstandname}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Ich möchte einen Konflikt zwischen Ihnen und der anderen Person melden. Es gab in den letzten Tagen mehrere Zwischenfälle, und ich bin der Meinung, dass es an der Zeit ist, eine formelle Beschwerde gegen ihn zu erstatten.</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Fühlen Sie sich frei, wenn Sie Fragen haben.</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Vielen Dank,</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Betrachtet,</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Personalabteilung.</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">{company_name}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">{app_url}</font></p><div><br></div><p></p>',
                    'en' => '<p><font color="#1d1c1d" face="Slack-Lato, Slack-Fractions, appleLogo, sans-serif"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">Hi ,</span></font></p><p><span style="font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Welcome to {app_name}</span><br></p><p><font color="#1d1c1d" face="Slack-Lato, Slack-Fractions, appleLogo, sans-serif"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">HR department/company to send complaints letter.<br></span></font></p><p><font color="#1d1c1d" face="Slack-Lato, Slack-Fractions, appleLogo, sans-serif"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">Dear {complaint_name}</span></font></p><p>I would like to report a conflict between you and the other person. There  have been several incidents over the last few days, and I feel that its is time to report a formal complaint against him/her.</p><p>Feel free to reach out if you have any questions.</p><p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Thank You,</span></p><p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Regards,</span></p><p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">HR Department.</span></p><p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);"><br></span></p><p><span style="font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p>',
                    'es' => '<p>Hola,</p><p>Bienvenido a {app_name}</p><p>Departamento de Recursos Humanos/Empresa para enviar una carta de reclamaciones.</p><p>Estimado {nombre_reclamación}</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Me gustaría informar de un conflicto entre usted y la otra persona. Ha habido varios incidentes en los últimos días, y siento que ha llegado el momento de denunciar una queja formal contra él.</span></p><p>Siéntase libre de llegar si usted tiene alguna pregunta.</p><p>Gracias,</p><p>Considerando,</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Departamento de Recursos Humanos.</span></p><p>{nombre_empresa}</p><p>{app_url}</p><div><br></div>',
                    'fr' => '<p>Salut,</p><p>Bienvenue dans { app_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Ministère / entreprise des RH pour envoyer une lettre de plainte.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Cher { nom_plainte }</span></p><p>Je voudrais signaler un conflit entre vous et lautre personne. Il y a eu plusieurs incidents au cours des derniers jours, et je pense quil est temps de signaler une plainte officielle contre lui.</p><p>N hésitez pas à nous contacter si vous avez des questions.</p><p>Merci,</p><p>Regards,</p><p>Département des RH.</p><p>{ nom_entreprise }</p><p>{ adresse_url }</p><div><br></div>',
                    'he' => '<p><font color="#1d1c1d" face="Slack-Lato, Slack-Fractions, appleLogo, sans-serif"><span style="font-size: 15px; font-variant-ligatures: common-ligatures; ">היי ,</span></font></p><p><span style="font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29) ; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text- align);">ברוכים הבאים אל {app_name}</span><br></p><p><font color="#1d1c1d" face="Slack-Lato, Slack-Fractions, appleLogo, sans-serif"> <span style="font-size: 15px; font-variant-ligatures: common-ligatures;">מחלקת משאבי אנוש/חברה לשלוח מכתב תלונות.<br></span></font></p><p> <font color="#1d1c1d" face="Slack-Lato, Slack-Fractions, appleLogo, sans-serif"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">יקירי {complaint_name}</span></font></p><p>אני רוצה לדווח על התנגשות בינך לבין האדם האחר. היו מספר תקריות במהלך הימים האחרונים, ואני מרגיש שהגיע הזמן לדווח על תלונה רשמית נגדו.</p><p>אתה מוזמן ליצור קשר אם יש לך שאלות כלשהן.</p> <p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common -ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">תודה,</span></p> <p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common -ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">בברכה,</span></p>< p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common- ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">מחלקת משאבי אנוש.</span></p>< p><span style="color: rgb(29, 28, 29); משפחת גופנים: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; גודל גופן: 15px; גופן-variant-ligatures: ליגטורות נפוצות; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span><span style="color: rgb(29, 28, 29); משפחת גופנים: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; גודל גופן: 15px; גופן-variant-ligatures: ליגטורות נפוצות; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);"><br></span></p><p><span style="font-size: 15px; גופן-variant-ligatures: ליגטורות נפוצות; color: rgb(29, 28, 29); משפחת גופנים: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p>',
                    'it' => '<p>Ciao,</p><p>Benvenuti in {app_name}</p><p>HR dipartimenta/azienda per inviare la lettera dei reclami.</p><p>Caro {nome_denuncia}</p><p>Vorrei segnalare un conflitto tra lei e l altra persona. Ci sono stati diversi incidenti negli ultimi giorni e sento che il suo è il momento di denunciare una denuncia formale contro di lui.</p><p>Sentiti libero di raggiungere se hai domande.</p><p>Grazie,</p><p>Riguardo,</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Dipartimento HR.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{company_name}</span></p><p>{app_url}</p><div><br></div>',
                    'ja' => '<p>こんにちは。</p><p>{app_name} へようこそ</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">苦情の手紙を送信するための HR 部門 / 会社。</span></p><p>{ complaint_name} に Dear があります</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">あなたと他の人との間の争いを報告したい この数日間で数件の事件があったが、私はそれが彼女に対する公式の申し立てを報告する時であると感じている。</span></p><p>質問がある場合は、自由に連絡してください。</p><p>ありがとうございます</p><p>よろしく</p><p>HR 部門</p><p>{ company_name}</p><p>{app_url}</p><div><br></div>',
                    'nl' => '<p>Hallo,</p><p>Welkom bij { app_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">HR-afdelings/bedrijf om klachten brief te sturen.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Geachte { klacht_naam }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Ik zou een conflict willen melden tussen u en de andere persoon. Er zijn de afgelopen dagen verschillende incidenten geweest en ik vind dat het tijd is om een formele klacht tegen hem/haar in te dienen.</span></p><p>Voel je vrij om uit te reiken als je vragen hebt.</p><p>Dank U,</p><p>Betreft:</p><p>HR-afdeling.</p><p>{ bedrijfsnaam }</p><p>{ app_url }</p><div><br></div>',
                    'pl' => '<p>Witam,</p><p>Witamy w aplikacji {app_name }</p><p>Dział kadr/firma, aby wysłać reklamacje.</p><p>Szanowny {skarga }</p><p>Chciałbym zgłosić konflikt między tobą a drugą osobą. W ciągu ostatnich kilku dni doszło do kilku incydentów i uważam, że nadszedł czas, aby zgłosić przeciwko nim formalną skargę.</p><p>Czuj się swobodnie, jeśli masz jakieś pytania.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Dziękuję,</span></p><p>W odniesieniu do</p><p>Dział HR.</p><p>{company_name }</p><p>{app_url }</p><div><br></div>',
                    'ru' => '<p>Привет.</p><p>Вас приветствует { app_name }</p><p>Отдел кадров/компания для направления письма с жалобами.</p><p>Уважаемый { имя-жалобы }</p><p>Я хотел бы сообщить о конфликте между вами и другим человеком. За последние несколько дней произошло несколько инцидентов, и я считаю, что настало время для того, чтобы сообщить об официальной жалобе против него.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Не стеснитесь, если у вас есть вопросы.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Спасибо.</span></p><p>С уважением,</p><p>Отдел кадров.</p><p>{ company_name }</p><p>{ app_url }</p><div><br></div>',
                    'pt' => '<p style=""><span style="font-size: 14.4px;">Oi,</span></p><p style=""><span style="font-size: 14.4px;">Bem-vindo a {app_name}</span></p><p style=""><span style="font-size: 14.4px;">HR department/empresa para enviar carta de reclamações.</span></p><p style=""><span style="font-size: 14.4px;">Querido {reclamnome_}</span></p><p style=""><span style="font-size: 14.4px;">Eu gostaria de relatar um conflito entre você e a outra pessoa. Houve vários incidentes ao longo dos últimos dias, e eu sinto que o seu é tempo de relatar uma queixa formal contra him/her.</span></p><p style=""><span style="font-size: 14.4px;">Sinta-se à vontade para alcançar fora se você tiver alguma dúvida.</span></p><p style=""><span style="font-size: 14.4px;">Obrigado,</span></p><p style=""><span style="font-size: 14.4px; font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Considera,</span></p><p style=""><span style="font-size: 14.4px;">Departamento de RH.</span></p><p style=""><span style="font-size: 14.4px;">{company_name}</span></p><p style=""><span style="font-size: 14.4px;">{app_url}</span></p><div><br></div>',
                    'tr' => '<p><font color="#1d1c1d" face="Slack-Lato, Slack-Fractions, appleLogo, sans-serif"><span style="font-size: 15px; font-variant-ligatures: common-ligatures; ">Merhaba ,</span></font></p><p><span style="font-size: 15px; font-variant-ligatures: common-ligatures; color: rgb(29, 28, 29) ; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text- align);">{app_name}</span><br></p><p><font color="#1d1c1d" face="Slack-Lato, Slack-Fractions, appleLogo, sans-serif"> hoş geldiniz <span style="font-size: 15px; font-variant-ligatures: common-ligatures;">Şikayet mektubu göndermek için İK departmanı/şirketi.<br></span></font></p><p> <font color="#1d1c1d" face="Slack-Lato, Slack-Fractions, appleLogo, sans-serif"><span style="font-size: 15px; font-variant-ligatures: common-ligatures;">Sevgili {complaint_name}</span></font></p><p>Sizinle diğer kişi arasındaki bir anlaşmazlığı bildirmek istiyorum. Son birkaç gün içinde birkaç olay yaşandı ve ona karşı resmi bir şikayette bulunmanın zamanının geldiğini düşünüyorum.</p><p>Sorularınız varsa bize ulaşmaktan çekinmeyin.</p> <p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-bitişik harfler: ortak -ligatures; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Teşekkürler,</span></p> <p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-bitişik harfler: ortak -bitişik harfler; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Saygılarımızla,</span></p>< p><span style="color: rgb(29, 28, 29); font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-bitişik harfler: ortak- bitişik harfler; font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">İK Departmanı.</span></p>< p><span style="renk: rgb(29, 28, 29); yazı tipi ailesi: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; yazı tipi boyutu: 15 piksel; font-varyant-bitişik harfler: ortak bitişik harfler; yazı tipi ağırlığı: var(--bs-body-font-ağırlığı); text-align: var(--bs-body-text-align);">{company_name}</span><span style="color: rgb(29, 28, 29); yazı tipi ailesi: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; yazı tipi boyutu: 15 piksel; font-varyant-bitişik harfler: ortak bitişik harfler; yazı tipi ağırlığı: var(--bs-body-font-ağırlığı); text-align: var(--bs-body-text-align);"><br></span></p><p><span style="font-size: 15px; font-varyant-bitişik harfler: ortak bitişik harfler; renk: rgb(29, 28, 29); yazı tipi ailesi: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; yazı tipi ağırlığı: var(--bs-body-font-ağırlığı); metin hizalama: var(--bs-body-text-align);">{app_url}</span><br></p>',
                    'pt-br' => '<p style=""><span style="font-size: 14.4px;">Oi,</span></p><p style=""><span style="font-size: 14.4px;">Bem-vindo a {app_name}</span></p><p style=""><span style="font-size: 14.4px;">HR department/empresa para enviar carta de reclamações.</span></p><p style=""><span style="font-size: 14.4px;">Querido {reclamnome_}</span></p><p style=""><span style="font-size: 14.4px;">Eu gostaria de relatar um conflito entre você e a outra pessoa. Houve vários incidentes ao longo dos últimos dias, e eu sinto que o seu é tempo de relatar uma queixa formal contra him/her.</span></p><p style=""><span style="font-size: 14.4px;">Sinta-se à vontade para alcançar fora se você tiver alguma dúvida.</span></p><p style=""><span style="font-size: 14.4px;">Obrigado,</span></p><p style=""><span style="font-size: 14.4px; font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Considera,</span></p><p style=""><span style="font-size: 14.4px;">Departamento de RH.</span></p><p style=""><span style="font-size: 14.4px;">{company_name}</span></p><p style=""><span style="font-size: 14.4px;">{app_url}</span></p><div><br></div>',

                ],
            ],
            'leave_action_sent' => [
                'subject' => 'Leave Action Sent',
                'lang' => [
                    'ar' => '<p>الموضوع : " إدارة الموارد البشرية / الشركة لإرسال رسالة موافقة إلى { leave_status } إجازة أو إجازة ".</p><p>مرحبا ، { leave_name }</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; لدي { leave_status } طلب ترك لأجل { leave_لسبب } من { leave_start_date } الى { leave_end_date }. { total_leave_yأيام } أيام لدي { leave_status } طلب الخروج الخاص بك الى { leave_لسبب }.</p><p>ونحن نطلب منكم أن تكملوا كل أعمالكم المعلقة أو أي قضية مهمة أخرى لكي لا تواجه الشركة أي خسارة أو مشكلة أثناء غيابكم ونحن نقدر لكم مدى عمق تفكيركم في إبلاغنا بذلك مسبقا.</p><p>إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة.</p><p>شكرا لك</p><p>Regards,</p><p>إدارة الموارد البشرية ،</p><p>{ app_name }</p><p>{ app_url }</p><div><br></div>',
                    'zh' => '<p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">主题 : "要发送的人力资源部门/公司{leave_status}休假或请假的批准函”。</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line -height: 28px;">﻿嗨，{leave_name}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="行高：28px;"><span style="font-family: var(--bs-body-font-family); 字体粗细：var(--bs-body-font-weight); 文本对齐: var(--bs-body-text-align);">            我有 {leave_status} 您从 {leave_start_date} 到 {leave_end_date} 提出的请假申请 {leave_reason}。 {total_leave_days} 天，我收到了您 {leave_reason} 的请假申请 {leave_status}。</span><br></p><p segoe="" ui",="" arial;="" font-size:= "" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs -body-font-weight); text-align: var(--bs-body-text-align);">我们要求您完成所有未完成的工作或任何其他重要问题，以便公司不会面临任何您缺席期间的损失或问题。感谢您提前通知我们的周到。</span></p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style ="line-height: 28px;">如有任何疑问，请随时与我们联系。</p><p segoe="" ui",="" arial;="" font-size:="" 14px ;"="" style="line-height: 28px;">谢谢您，</p><p segoe="" ui",="" arial;="" font-size:="" 14px;" ="" style="line-height: 28px;">问候，</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">人力资源部</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style= "line-height: 28px;">{app_name}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line -高度：28px;">{app_url}</p><p></p>',
                    'da' => '<p>Emne: " HR-afdeling / virksomhed, der skal sende godkendelsesbrev til { leave_status } en ferie eller orlov ".</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Hej, { leave_name }</span></p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Jeg har { leave_status } din orlov-anmodning for { leave_reason } fra { leave_start_date } til { leave_end_date }. { total_leave_days } dage Jeg har { leave_status } din anmodning om { leave_reason }.</p><p>Vi beder dig om at færdiggøre alt dit udestående arbejde eller et andet vigtigt spørgsmål, så virksomheden ikke står over for nogen tab eller problemer under dit fravær. Vi sætter pris på Deres betænksomhed, for at informere os godt på forhånd.</p><p>Du er velkommen til at række ud, hvis du har nogen spørgsmål.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Tak.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Med venlig hilsen</span></p><p>HR-afdelingen,</p><p>{ app_name }</p><p>{ app_url }</p>',
                    'de' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Betreff: " Personalabteilung/Firma, um den Zulassungsbescheid an {leave_status} einen Urlaub oder Urlaub zu schicken ".</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Hi, {leave_name}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Ich habe {leave_status} Ihre Urlaubsanforderung für {leave_reason} von {leave_start_date} bis {leave_end_date}. {total_leave_days} Tage Ich habe {leave_status} Ihre Urlaubs-Anfrage für {leave_reason}.</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Wir bitten Sie, Ihre gesamte anstehende Arbeit oder ein anderes wichtiges Thema abzuschließen, so dass das Unternehmen während Ihrer Abwesenheit keinerlei Verlust oder kein Problem zu bewältigen hat. Wir freuen uns über Ihre Nachdenklichkeit, um uns im Vorfeld gut zu informieren.</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Fühlen Sie sich frei, wenn Sie Fragen haben.</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Vielen Dank,</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Betrachtet,</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Personalabteilung,</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">{Anwendungsname}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">{app_url}</font></p><p></p>',
                    'en' => '<p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">Subject : "HR department/company to send approval letter to {leave_status} a vacation or leave" .</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">﻿Hi ,{leave_name}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; I have {leave_status} your leave request for&nbsp; {leave_reason} from {leave_start_date} to {leave_end_date}. {total_leave_days} days I have&nbsp; {leave_status} your leave request for {leave_reason}.</span><br></p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">We request you to complete all your pending work or any other important issue so that the company does not face any any loss or problem during your absence. We appreciate your thoughtfulness to inform us well in advance.</span></p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">Feel free to reach out if you have any questions.</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">Thank You,</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">Regards,</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">HR Department,</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">{app_name}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">{app_url}</p><p></p>',
                    'es' => '<p>Asunto: " Departamento de RR.HH./compañía para enviar la carta de aprobación a {leave_status} unas vacaciones o vacaciones ".</p><p>Hi, {nombre_archivo}</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Tengo {leave_status} la solicitud de licencia para {leave_reason} de {leave_start_date} a {leave_end_date}. {total_leave_days} días tengo {leave_status} la solicitud de licencia para {leave_reason}.</p><p>Le solicitamos que complete todos sus trabajos pendientes o cualquier otro asunto importante para que la empresa no se enfrente a ninguna pérdida o problema durante su ausencia. Agradecemos su consideración para informarnos con mucha antelación.</p><p>Siéntase libre de llegar si usted tiene alguna pregunta.</p><p>Gracias,</p><p>Considerando,</p><p>Departamento de Recursos Humanos,</p><p>{app_name}</p><p>{app_url}</p>',
                    'fr' => '<p>Objet: " Service des ressources humaines /entreprise pour envoyer une lettre d approbation à { leave_status } un congé annuel ou un congé ".</p><p>Salut, { nom_onde }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; J ai { leave_status } votre demande de permission pour { leave_reason } de { leave_start_date } à { leave_end_date }. { total_leave_days } jours, j ai { leave_status } votre demande de congé pour { leave_reason }.</span></p><p>Nous vous demandons de remplir tous vos travaux en cours ou toute autre question importante afin que l entreprise ne soit pas confrontée à une perte ou à un problème pendant votre absence. Nous apprécions votre attention pour nous informer longtemps à l avance.</p><p>N hésitez pas à nous contacter si vous avez des questions.</p><p>Merci,</p><p>Regards,</p><p>Département des RH,</p><p>{ nom_app }</p><p>{ adresse_url }</p>',
                    'he' => '<p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">נושא: "מחלקת משאבי אנוש/חברה לשלוח מכתב אישור ל-{leave_status} חופשה או חופשה" .</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line -הייט: 28px; "> היי, {liew_name} </p> <p segoe =" "ui", = "" arial; = "" font-size: = "" 14px; "=" "style =" line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align : var(--bs-body-text-align);">            יש לי {leave_status} את בקשת החופשה שלך עבור  {leave_reason} מ-{leave_start_date} עד {leave_end_date}. {total_leave_days} ימים יש לי  {leave_status} את בקשת החופשה שלך עבור {leave_reason}.</span><br></p><p segoe="" ui",="" arial;="" font-size:= "" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs -body-font-weight); text-align: var(--bs-body-text-align);">אנו מבקשים מכם להשלים את כל העבודה הממתינה או כל נושא חשוב אחר כך שהחברה לא תעמוד בפני כל אובדן או בעיה במהלך היעדרותך. אנו מעריכים את התחשבותך להודיע ​​לנו זמן רב מראש.</span></p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style ="line-height: 28px;">אל תהסס לפנות אם יש לך שאלות.</p><p segoe="" ui",="" arial;="" font-size:="" 14px ;"="" style="line-height: 28px;">תודה,</p><p segoe="" ui",="" arial;="" font-size:="" 14px;" ="" style="line-height: 28px;">בברכה,</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">מחלקת משאבי אנוש,</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style= "line-height: 28px;">{app_name}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line -height: 28px;">{app_url}</p><p></p>',
                    'it' => '<p>Oggetto: " HR department /company per inviare lettera di approvazione a {leave_status} una vacanza o un congedo ".</p><p>Ciao, {leave_name}</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Ho {leave_status} la tua richiesta di permesso per {leave_ragione} da {leave_start_date} a {leave_end_date}. {total_leave_days} giorni I ho {leave_status} la tua richiesta di permesso per {leave_ragione}.</p><p>Ti richiediamo di completare tutte le tue lavorazioni in sospeso o qualsiasi altra questione importante in modo che lazienda non faccia alcuna perdita o problema durante la tua assenza. Apprezziamo la vostra premura per informarci bene in anticipo.</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Sentiti libero di raggiungere se hai domande.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Grazie,</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Riguardo,</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Dipartimento HR,</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_name}</span></p><p>{app_url}</p>',
                    'ja' => '<p>件名 : " 承認レターを { leave_status} に休暇または休暇に送信するための人事部門 / 企業。</p><p>こんにちは、 {leave_name}</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; { leave_start_date} から {leave_end_date}までの { leave_reason} { leave_reason} { leave_status} { leave_status } { leave_status } { total_leave_status } { leave_reason } { leave_reason} に対するあなたの休暇リクエストをお願いします。</p><p>お客様は、お客様がお客様の不在中に損失や問題が発生しないように、保留中のすべての作業やその他の重要な問題を完了するよう要求します。 事前にお知らせするためには、あなたの思慮深さに感謝します。</p><p>質問がある場合は、自由に連絡してください。</p><p>ありがとうございます</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">よろしく</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">HR 部門</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_name}</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_url}</span><br></p>',
                    'nl' => '<p>Onderwerp: " HR-afdeling/bedrijf om een goedkeuringsbrief te sturen naar { leave_status } een vakantie of verlof ".</p><p>Hallo, { leave_name }</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Ik heb { leave_status } uw verzoek om verlof voor { leave_reason } van { leave_start_date } aan { leave_end_date }. { total_leave_days } dagen Ik heb { leave_status } uw verzoek om verlof voor { leave_reason }.</p><p>Wij vragen u om al uw lopende werk of een andere belangrijke kwestie, zodat het bedrijf geen verlies of probleem tijdens uw afwezigheid geconfronteerd. Wij waarderen uw bedachtzaamheid om ons van tevoren goed te informeren.</p><p>Voel je vrij om uit te reiken als je vragen hebt.</p><p>Dank U,</p><p>Betreft:</p><p>HR-afdeling,</p><p>{ app_name }</p><p>{ app_url }</p>',
                    'pl' => '<p>Temat: " Dział HR /firma, aby wysłać list zatwierdzający do {leave_status } urlop lub urlop ".</p><p>Cześć, {leave_name }</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Mam {leave_status } żądanie pozostania dla {leave_reason } od {leave_start_date } do {leave_end_date }. {total_leave_days } dni mam {leave_status } żądanie opuszczenia użytkownika dla {leave_reason }.</p><p>Prosimy o wypełnienie wszystkich oczekujących prac lub innych ważnych spraw, tak aby firma nie borykała się z żadną stratą lub problemem w czasie Twojej nieobecności. Doceniamy twoją przemyślność, aby poinformować nas z wyprzedzeniem.</p><p>Czuj się swobodnie, jeśli masz jakieś pytania.</p><p>Dziękuję,</p><p>W odniesieniu do</p><p>Dział HR,</p><p>{app_name }</p><p>{app_url }</p>',
                    'ru' => '<p>Тема: " Отдел кадров/компания для отправки письма с утверждением в { leave_status } отпуск или отпуск ".</p><p>Привет, { leave_name }</p><p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; У меня есть { leave_status } ваш запрос на отпуск для { leave_reason } из { leave_start_date } в { leave_end_date }. { total_leave_days } дней { leave_status } ваш запрос на отпуск для { leave_reason }.</p><p>Мы просим вас завершить все ваши ожидающие работы или любой другой важный вопрос, чтобы компания не сталкивалась с какими-либо потерям или проблемой во время вашего отсутствия. Мы ценим вашу задумчивость, чтобы сообщить нам заранее.</p><p>Не стеснитесь, если у вас есть вопросы.</p><p>Спасибо.</p><p>С уважением,</p><p>Отдел кадров,</p><p>{ имя_программы }</p><p>{ app_url }</p>',
                    'pt' => '<p><span style="font-size: 14.4px;">Assunto: " Departamento de RH /empresa para enviar carta de aprovação para {leave_status} férias ou licença ".</span></p><p><span style="font-size: 14.4px;">Oi, {leave_name}</span></p><p><span style="font-size: 14.4px;">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Eu tenho {leave_status} sua solicitação de licença para {leave_reason} de {leave_start_date} para {leave_end_date}. {total_leave_days} dias eu tenho {leave_status} sua solicitação de licença para {leave_reason}.</span></p><p><span style="font-size: 14.4px;">Solicitamos que você complete todo o seu trabalho pendente ou qualquer outra questão importante para que a empresa não enfrente qualquer perda ou problema durante a sua ausência. Agradecemos a sua atenciosidade para nos informar com bastante antecedência.</span></p><p><span style="font-size: 14.4px;">Sinta-se à vontade para alcançar fora se você tiver alguma dúvida.</span></p><p><span style="font-size: 14.4px; font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Obrigado,</span><br></p><p><span style="font-size: 14.4px; font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Considera,</span></p><p><span style="font-size: 14.4px;">Departamento de RH,</span></p><p><span style="font-size: 14.4px;">{app_name}</span></p><p><span style="font-size: 14.4px;">{app_url}</span></p>',
                    'tr' => '<p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">Subject : "Gönderilecek İK departmanı/şirket {leave_status} tatile veya ayrılmaya onay mektubu" .</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line -height: 28px;">﻿Merhaba ,{leave_name}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style=" line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align : var(--bs-body-text-align);">            {leave_status} {leave_reason} için {leave_start_date} tarihinden {leave_end_date} tarihine kadar izin talebinizi aldım. {total_leave_days} günüm  {leave_status}, {leave_reason} için izin talebiniz var.</span><br></p><p segoe="" ui",="" arial;="" font-size:= "" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs -body-font-weight); text-align: var(--bs-body-text-align);">Şirketin herhangi bir sorunla karşılaşmaması için bekleyen tüm işlerinizi veya diğer önemli sorunları tamamlamanızı rica ederiz. yokluğunuz sırasında kayıp veya sorun. Bizi önceden bilgilendirme konusundaki düşünceniz için teşekkür ederiz.</span></p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style ="line-height: 28px;">Sorularınız varsa bize ulaşmaktan çekinmeyin.</p><p segoe="" ui",="" arial;="" font-size:="" 14px ;"="" style="line-height: 28px;">Teşekkürler,</p><p segoe="" ui",="" arial;="" font-size:="" 14px;" ="" style="line-height: 28px;">Saygılarımızla,</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">İK Departmanı</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style= "line-height: 28px;">{app_name}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line -yükseklik: 28 piksel;">{app_url}</p><p></p>',
                    'pt-br' => '<p><span style="font-size: 14.4px;">Assunto: " Departamento de RH /empresa para enviar carta de aprovação para {leave_status} férias ou licença ".</span></p><p><span style="font-size: 14.4px;">Oi, {leave_name}</span></p><p><span style="font-size: 14.4px;">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; Eu tenho {leave_status} sua solicitação de licença para {leave_reason} de {leave_start_date} para {leave_end_date}. {total_leave_days} dias eu tenho {leave_status} sua solicitação de licença para {leave_reason}.</span></p><p><span style="font-size: 14.4px;">Solicitamos que você complete todo o seu trabalho pendente ou qualquer outra questão importante para que a empresa não enfrente qualquer perda ou problema durante a sua ausência. Agradecemos a sua atenciosidade para nos informar com bastante antecedência.</span></p><p><span style="font-size: 14.4px;">Sinta-se à vontade para alcançar fora se você tiver alguma dúvida.</span></p><p><span style="font-size: 14.4px; font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Obrigado,</span><br></p><p><span style="font-size: 14.4px; font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Considera,</span></p><p><span style="font-size: 14.4px;">Departamento de RH,</span></p><p><span style="font-size: 14.4px;">{app_name}</span></p><p><span style="font-size: 14.4px;">{app_url}</span></p>',

                ],
            ],
            'payslip_sent' => [
                'subject' => 'Payslip Sent',
                'lang' => [
                    'ar' => '<p>الموضوع : " إدارة الموارد البشرية / الشركة لإرسال شظية عن طريق البريد الإلكتروني في وقت تأكيد الدفع. "</p><p>عزيزي ، { paysp_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; أتمنى أن يجدك هذا البريد الإلكتروني جيدا برجاء الرجوع الى payalp المرفقة الى { payplip_salary_شهر }. اضغط ببساطة على الاختيار في أسفل : { payspp_url }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة.</span></p><p>Regards,</p><p>إدارة الموارد البشرية ،</p><p>{ app_name }</p><p>{ app_url }</p>',
                    'zh' => '<p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">主题 :  " 人力资源部门/公司发送确认工资单时通过电子邮件发送工资单。"</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-高度：28px;">﻿亲爱的，{payslip_name}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line -height: 28px;"><span style="font-family: var(--bs-body-font-family); 字体粗细: var(--bs-body-font-weight); 文本对齐: var(--bs-body-text-align);">    </span>    希望这封电子邮件能让您满意！请参阅随附的 {payslip_salary_month} 工资单。只需点击下面的按钮即可： <br>                                                            {payslip_url}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style=" line-height: 28px;">如果您有任何疑问，请随时与我们联系。</p><p segoe="" ui",="" arial;="" font-size:="" 14px;" ="" style="line-height: 28px;">问候，</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">人力资源部门，</span></p><p segoe="" ui",="" arial;="" font-size :="" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(- -bs-body-font-weight); 文本对齐: var(--bs-body-text-align);">{app_name}</span><br></p><p segoe="" ui ",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">{app_url}</p><p></p>',
                    'da' => '<p>Emne: " HR-afdeling / Kompagni til at sende lønsedler via e-mail på tidspunktet for bekræftelsen af lønsedlerne. "</p><p>Kære, { payslip_name }</p><p>&nbsp; &nbsp; &nbsp; &nbsp; Håber denne e-mail finder dig godt! Se vedhæftet payseddel for { payslip_salary_month }. Klik på knappen nedenfor: { payslip_url }</p><p>Du er velkommen til at række ud, hvis du har nogen spørgsmål.</p><p>Med venlig hilsen</p><p>HR-afdelingen,</p><p>{ app_name }</p><p>{ app_url }</p>',
                    'de' => '<p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Betreff: " Personalabteilung/Firma, um payslips per E-Mail zum Zeitpunkt der Bestätigung des Auszahlungsscheins zu senden. "</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Sehr geehrte, {payslip_name}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">&nbsp; &nbsp; &nbsp; &nbsp; Hoffe, diese E-Mail findet dich gut! Bitte sehen Sie den angehängten payslip für {payslip_salary_month}. Klicken Sie einfach auf die folgende Schaltfläche: {payslip_url}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Fühlen Sie sich frei, wenn Sie Fragen haben.</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Betrachtet,</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">Personalabteilung,</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">{Anwendungsname}</font></p><p style="line-height: 28px; font-family: Nunito, " segoe="" ui",="" arial;="" font-size:="" 14px;"=""><font face="sans-serif">{app_url}</font></p><p></p>',
                    'en' => '<p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">Subject :&nbsp; " HR&nbsp; Department / Company to send&nbsp; payslips by email at time of confirmation of payslip. "</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">﻿Dear ,{payslip_name}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp;&nbsp;</span>&nbsp; &nbsp; Hope this email finds you well! Please see attached payslip for {payslip_salary_month} . Simply click on the button below :&nbsp;<br>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; {payslip_url}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">Feel free to&nbsp; reach out if you have any questions.</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">Regards ,</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">HR Department ,</span></p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_name}</span><br></p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">{app_url}</p><p></p>',
                    'es' => '<p>Asunto: " Departamento de Recursos Humanos/Empresa para enviar nóminas por correo electrónico en el momento de la confirmación de payslip. "</p><p>Estimado, {payslip_name}</p><p>&nbsp; &nbsp; &nbsp; &nbsp; ¡Espero que este email le encuentre bien! Consulte la ficha de pago adjunta para {payslip_salary_month}. Simplemente haga clic en el botón de abajo: {payslip_url}</p><p>Siéntase libre de llegar si usted tiene alguna pregunta.</p><p>Considerando,</p><p>Departamento de Recursos Humanos,</p><p>{app_name}</p><p>{app_url}</p>',
                    'fr' => '<p>Objet: " HR Department / Company to send payborderby email at time of confirmation of payslip. "</p><p>Cher, { nom_décalage }</p><p>&nbsp; &nbsp; &nbsp; &nbsp; J espère que ce courriel vous trouve bien ! Veuillez consulter le bordereau de paiement ci-joint pour { payement_salary_month }. Cliquez simplement sur le bouton ci-dessous: { payslip_url }</p><p>N hésitez pas à nous contacter si vous avez des questions.</p><p>Regards,</p><p>Département des RH,</p><p>{ nom_app }</p><p>{ adresse_url }</p>',
                    'he' => '<p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">נושא :  " מחלקת משאבי אנוש/חברה לשלוח תלושי שכר בדוא"ל בזמן אישור תלוש השכר. "</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line- גובה: 28px; "> יקר, {paylip_name} </p> <p segoe =" "ui", = "" arial; = "" font-size: = "" 14px; "=" "style =" קו -height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">    </span>    מקווה שהמייל הזה ימצא אותך היטב! ראה תלוש שכר מצורף עבור {payslip_salary_month} . פשוט לחץ על הכפתור למטה : <br>                                                            {payslip_url}</p><p segoe="" ui",="" arial="" ui",="" arial;="" arial; line-height: 28px;">אל תהסס לפנות אם יש לך שאלות.</p><p segoe="" ui",="" arial;="" font-size:="" 14px;" ="" style="line-height: 28px;">בברכה ,</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">מחלקת משאבי אנוש ,</span></p><p segoe="" ui",="" arial;="" font-size :="" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(- -bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_name}</span><br></p><p segoe="" ui ",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">{app_url}</p><p></p>',
                    'it' => '<p>Oggetto: " HR Department / Company per inviare busta paga via email al momento della conferma della busta paga ".</p><p>Caro, {payslip_name}</p><p>&nbsp; &nbsp; &nbsp; &nbsp; Spero che questa email ti trovi bene! Si prega di consultare la busta paga per {payslip_salary_month}. Semplicemente clicca sul pulsante sottostante: {payslip_url}</p><p>Sentiti libero di raggiungere se hai domande.</p><p>Riguardo,</p><p>Dipartimento HR,</p><p>{app_name}</p><p>{app_url}</p>',
                    'ja' => '<p>件名 : " 給与明細書の確認時に、給与明細書を電子メールで送信するための HR 部門 / 企業。</p><p>{ payslip_name} を実行します。</p><p>&nbsp; &nbsp; &nbsp; &nbsp; この E メールでよくご確認ください。 {payslip_salary_month} の添付された給与明細書を参照してください。 以下のボタンをクリックするだけで、 { payslip_url} をクリックしてください。</p><p>質問がある場合は、自由に連絡してください。</p><p>よろしく</p><p>HR 部門</p><p>{app_name}</p><p>{app_url}</p>',
                    'nl' => '<p>Onderwerp: " HR Department/Company om betalingen te sturen per e-mail op het moment van de bevestiging van de payslip. "</p><p>Schat, { payslip_name }</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; Hoop dat deze e-mail je goed vindt! Zie bijgevoegde payslip voor { payslip_salary_month }. Klik gewoon op de knop hieronder: { payslip_url }</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Voel je vrij om uit te reiken als je vragen hebt.</span><br></p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Betreft:</span></p><p>HR-afdeling,</p><p>{ app_name }</p><p>{ app_url }</p>',
                    'pl' => '<p>Temat: " Dział HR/Firma, aby wysłać payslips pocztą elektroniczną w momencie potwierdzenia payslip. "</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Szanowny, {payslip_name }</span></p><p>&nbsp; &nbsp; &nbsp; &nbsp; Mam nadzieję, że ta wiadomość znajdzie Cię dobrze! Patrz załączony payslip dla {payslip_salary_month }. Wystarczy kliknąć na przycisk poniżej: {payslip_url }</p><p>Czuj się swobodnie, jeśli masz jakieś pytania.</p><p>W odniesieniu do</p><p>Dział HR,</p><p>{app_name }</p><p>{app_url }</p>',
                    'ru' => '<p>Тема: " Отдел кадров/Компания для отправки пастор по электронной почте во время подтверждения паузлиса ".</p><p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">Уважаемый, { payslip_name }</span></p><p>&nbsp; &nbsp; &nbsp; &nbsp; Надеюсь, это электронное письмо найдет вас хорошо! См. вложенный раздел для { payslip_salary_month }. Просто нажмите на кнопку ниже: { payslip_url }</p><p>Не стеснитесь, если у вас есть вопросы.</p><p>С уважением,</p><p>Отдел кадров,</p><p>{ имя_программы }</p><p>{ app_url }</p>',
                    'pt' => '<p><span style="font-size: 14.4px;">Assunto: " Departamento / Companhia de RH para enviar payslips por e-mail a hora da confirmação de payslip. "</span></p><p><span style="font-size: 14.4px;">Querido, {payslip_name}</span></p><p><span style="font-size: 14.4px; font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; Espero que este e-mail encontre você bem! Por favor, consulte o payslip anexo para {payslip_salary_month}. Basta clicar no botão abaixo: {payslip_url}</span></p><p><span style="font-size: 14.4px;">Sinta-se à vontade para alcançar fora se você tiver alguma dúvida.</span></p><p><span style="font-size: 14.4px;">Considera,</span></p><p><span style="font-size: 14.4px;">Departamento de RH,</span></p><p><span style="font-size: 14.4px;">{app_name}</span></p><p><span style="font-size: 14.4px;">{app_url}</span></p>',
                    'tr' => '<p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">Subject :  " İK  Gönderilecek Departman / Şirket "</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line- yükseklik: 28px;">﻿Sevgili ,{payslip_name}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line -height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">    </span>    Umarım bu e-posta sizi bulur! Lütfen {payslip_salary_month} için ekteki maaş bordrosuna bakın. Aşağıdaki düğmeyi tıklamanız yeterlidir: <br>                                                            {payslip_url}</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style=" line-height: 28px;">Herhangi bir sorunuz varsa  bize ulaşmaktan çekinmeyin.</p><p segoe="" ui",="" arial;="" font-size:="" 14px;" ="" style="line-height: 28px;">Saygılarımızla,</p><p segoe="" ui",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">İK Departmanı ,</span></p><p segoe="" ui",="" arial;="" font-size :="" 14px;"="" style="line-height: 28px;"><span style="font-family: var(--bs-body-font-family); font-weight: var(- -bs-body-font-weight); text-align: var(--bs-body-text-align);">{app_name}</span><br></p><p segoe="" ui ",="" arial;="" font-size:="" 14px;"="" style="line-height: 28px;">{app_url}</p><p></p>',
                    'pt' => '<p><span style="font-size: 14.4px;">Assunto: " Departamento / Companhia de RH para enviar payslips por e-mail a hora da confirmação de payslip. "</span></p><p><span style="font-size: 14.4px;">Querido, {payslip_name}</span></p><p><span style="font-size: 14.4px; font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">&nbsp; &nbsp; &nbsp; &nbsp; Espero que este e-mail encontre você bem! Por favor, consulte o payslip anexo para {payslip_salary_month}. Basta clicar no botão abaixo: {payslip_url}</span></p><p><span style="font-size: 14.4px;">Sinta-se à vontade para alcançar fora se você tiver alguma dúvida.</span></p><p><span style="font-size: 14.4px;">Considera,</span></p><p><span style="font-size: 14.4px;">Departamento de RH,</span></p><p><span style="font-size: 14.4px;">{app_name}</span></p><p><span style="font-size: 14.4px;">{app_url}</span></p>',

                ],
            ],
            'promotion_sent' => [
                'subject' => 'Promotion Sent',
                'lang' => [
                    'ar' => '<p>Subject : -HR القسم / الشركة لارسال رسالة تهنئة الى العمل للتهنئة بالعمل.</p>
                    <p>عزيزي { employee_name },</p>
                    <p>تهاني على ترقيتك الى { promotion_designation } { promotion_title } الفعال { promotion_date }.</p>
                    <p>وسنواصل توقع تحقيق الاتساق وتحقيق نتائج عظيمة منكم في دوركم الجديد. ونأمل أن تكون قدوة للموظفين الآخرين في المنظمة.</p>
                    <p>ونتمنى لكم التوفيق في أداءكم في المستقبل ، وتهانينا !</p>
                    <p>ومرة أخرى ، تهانئي على الموقف الجديد.</p>
                    <p>إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة.</p>
                    <p>شكرا لك</p>
                    <p>Regards,</p>
                    <p>إدارة الموارد البشرية ،</p>
                    <p>{ app_name }</p>',
                    'zh' => '<p> </p>
                    <p><strong>主题：-人力资源部门/公司发送职位晋升祝贺信。</strong></p>
                    <p><strong>亲爱的{employee_name}，</strong></p>
                    <p>恭喜您晋升为 {promotion_designation} {promotion_title}，生效日期：{promotion_date}。</p>
                    <p>我们将继续期待您在新职位上保持稳定并取得出色的成果。我们希望您能为组织的其他员工树立榜样。</p>
                    <p>祝您未来表现顺利，并表示祝贺！</p>
                    <p>再次祝贺您就任新职位。</p>
                    <p> </p>
                    <p>如果您有任何疑问，请随时与我们联系。</p>
                    <p>谢谢</p>
                    <p><strong>问候，</strong></p>
                    <p><strong>人力资源部</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'da' => '<p>Om: HR-afdelingen / Virksomheden om at sende en lyk&oslash;nskning til jobfremst&oslash;d.</p>
                    <p>K&aelig;re { employee_name },</p>
                    <p>Tillykke med din forfremmelse til { promotion_designation } { promotion_title } effektiv { promotion_date }.</p>
                    <p>Vi vil fortsat forvente konsekvens og store resultater fra Dem i Deres nye rolle. Vi h&aring;ber, at De vil foreg&aring; med et godt eksempel for de &oslash;vrige ansatte i organisationen.</p>
                    <p>Vi &oslash;nsker Dem held og lykke med Deres fremtidige optr&aelig;den, og tillykke!</p>
                    <p>Endnu en gang tillykke med den nye holdning.</p>
                    <p>Du er velkommen til at r&aelig;kke ud, hvis du har nogen sp&oslash;rgsm&aring;l.</p>
                    <p>Tak.</p>
                    <p>Med venlig hilsen</p>
                    <p>HR-afdelingen,</p>
                    <p>{ app_name }</p>',
                    'de' => '<p>Betrifft: -Personalabteilung/Unternehmen, um einen Gl&uuml;ckwunschschreiben zu senden.</p>
                    <p>Sehr geehrter {employee_name},</p>
                    <p>Herzlichen Gl&uuml;ckwunsch zu Ihrer Werbeaktion an {promotion_designation} {promotion_title} wirksam {promotion_date}.</p>
                    <p>Wir werden von Ihnen in Ihrer neuen Rolle weiterhin Konsistenz und gro&szlig;e Ergebnisse erwarten. Wir hoffen, dass Sie ein Beispiel f&uuml;r die anderen Mitarbeiter der Organisation setzen werden.</p>
                    <p>Wir w&uuml;nschen Ihnen viel Gl&uuml;ck f&uuml;r Ihre zuk&uuml;nftige Leistung, und gratulieren!</p>
                    <p>Nochmals herzlichen Gl&uuml;ckwunsch zu der neuen Position.</p>
                    <p>F&uuml;hlen Sie sich frei, wenn Sie Fragen haben.</p>
                    <p>Danke.</p>
                    <p>Betrachtet,</p>
                    <p>Personalabteilung,</p>
                    <p>{app_name}</p>',
                    'en' => '<p>&nbsp;</p>
                    <p><strong>Subject:-HR department/Company to send job promotion congratulation letter.</strong></p>
                    <p><strong>Dear {employee_name},</strong></p>
                    <p>Congratulations on your promotion to {promotion_designation} {promotion_title} effective {promotion_date}.</p>
                    <p>We shall continue to expect consistency and great results from you in your new role. We hope that you will set an example for the other employees of the organization.</p>
                    <p>We wish you luck for your future performance, and congratulations!.</p>
                    <p>Again, congratulations on the new position.</p>
                    <p>&nbsp;</p>
                    <p>Feel free to reach out if you have any questions.</p>
                    <p>Thank you</p>
                    <p><strong>Regards,</strong></p>
                    <p><strong>HR Department,</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'es' => '<p>Asunto: -Departamento de RRHH/Empresa para enviar carta de felicitaci&oacute;n de promoci&oacute;n de empleo.</p>
                    <p>Estimado {employee_name},</p>
                    <p>Felicidades por su promoci&oacute;n a {promotion_designation} {promotion_title} efectiva {promotion_date}.</p>
                    <p>Seguiremos esperando la coherencia y los grandes resultados de ustedes en su nuevo papel. Esperamos que usted ponga un ejemplo para los otros empleados de la organizaci&oacute;n.</p>
                    <p>Le deseamos suerte para su futuro rendimiento, y felicitaciones!.</p>
                    <p>Una vez m&aacute;s, felicidades por la nueva posici&oacute;n.</p>
                    <p>Si&eacute;ntase libre de llegar si usted tiene alguna pregunta.</p>
                    <p>&iexcl;Gracias!</p>
                    <p>Considerando,</p>
                    <p>Departamento de Recursos Humanos,</p>
                    <p>{app_name}</p>',
                    'fr' => '<p>Objet: -D&eacute;partement RH / Soci&eacute;t&eacute; denvoi dune lettre de f&eacute;licitations pour la promotion de lemploi.</p>
                    <p>Cher { employee_name },</p>
                    <p>F&eacute;licitations pour votre promotion &agrave; { promotion_d&eacute;signation } { promotion_title } effective { promotion_date }.</p>
                    <p>Nous continuerons &agrave; vous attendre &agrave; une coh&eacute;rence et &agrave; de grands r&eacute;sultats de votre part dans votre nouveau r&ocirc;le. Nous esp&eacute;rons que vous trouverez un exemple pour les autres employ&eacute;s de lorganisation.</p>
                    <p>Nous vous souhaitons bonne chance pour vos performances futures et f&eacute;licitations !</p>
                    <p>Encore une fois, f&eacute;licitations pour le nouveau poste.</p>
                    <p>Nh&eacute;sitez pas &agrave; nous contacter si vous avez des questions.</p>
                    <p>Je vous remercie</p>
                    <p>Regards,</p>
                    <p>D&eacute;partement des RH,</p>
                    <p>{ app_name }</p>',
                    'he' => '<p> </p>
                    <p><strong>נושא:-מחלקת משאבי אנוש/חברה לשלוח מכתב ברכה לקידום בעבודה.</strong></p>
                    <p><strong>{employee_name} היקר,</strong></p>
                    <p>ברכות על הקידום שלך ל-{promotion_designation} {promotion_title} החל מ-{promotion_date}.</p>
                    <p>נמשיך לצפות לעקביות ולתוצאות נהדרות ממך בתפקידך החדש. אנו מקווים שתהווה דוגמה לעובדי הארגון האחרים.</p>
                    <p>אנו מאחלים לך בהצלחה עבור הביצועים העתידיים שלך, ומזל טוב!.</p>
                    <p>שוב, ברכות על התפקיד החדש.</p>
                    <p> </p>
                    <p>אל תהסס לפנות אם יש לך שאלות.</p>
                    <p>תודה</p>
                    <p><strong>בברכה,</strong></p>
                    <p><strong>מחלקת משאבי אנוש,</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'it' => '<p>Oggetto: - Dipartimento HR / Societ&agrave; per inviare la lettera di congratulazioni alla promozione del lavoro.</p>
                    <p>Caro {employee_name},</p>
                    <p>Complimenti per la tua promozione a {promotion_designation} {promotion_title} efficace {promotion_date}.</p>
                    <p>Continueremo ad aspettarci coerenza e grandi risultati da te nel tuo nuovo ruolo. Ci auguriamo di impostare un esempio per gli altri dipendenti dellorganizzazione.</p>
                    <p>Ti auguriamo fortuna per le tue prestazioni future, e complimenti!.</p>
                    <p>Ancora, complimenti per la nuova posizione.</p>
                    <p>Sentiti libero di raggiungere se hai domande.</p>
                    <p>Grazie</p>
                    <p>Riguardo,</p>
                    <p>Dipartimento HR,</p>
                    <p>{app_name}</p>',
                    'ja' => '<p>件名:-HR 部門/企業は、求人広告の祝賀状を送信します。</p>
                    <p>{ employee_name} に出庫します。</p>
                    <p>{promotion_designation } { promotion_title} {promotion_date} 販促に対するお祝いのお祝いがあります。</p>
                    <p>今後とも、お客様の新しい役割において一貫性と大きな成果を期待します。 組織の他の従業員の例を設定したいと考えています。</p>
                    <p>あなたの未来のパフォーマンスをお祈りします。おめでとうございます。</p>
                    <p>また、新しい地位について祝意を表する。</p>
                    <p>質問がある場合は、自由に連絡してください。</p>
                    <p>ありがとう</p>
                    <p>よろしく</p>
                    <p>HR 部門</p>
                    <p>{app_name}</p>',
                    'nl' => '<p>Betreft: -HR-afdeling/Bedrijf voor het versturen van de aanbevelingsbrief voor taakpromotie.</p>
                    <p>Geachte { employee_name },</p>
                    <p>Gefeliciteerd met uw promotie voor { promotion_designation } { promotion_title } effective { promotion_date }.</p>
                    <p>Wij zullen de consistentie en de grote resultaten van u in uw nieuwe rol blijven verwachten. Wij hopen dat u een voorbeeld zult stellen voor de andere medewerkers van de organisatie.</p>
                    <p>Wij wensen u geluk voor uw toekomstige prestaties, en gefeliciteerd!.</p>
                    <p>Nogmaals, gefeliciteerd met de nieuwe positie.</p>
                    <p>Voel je vrij om uit te reiken als je vragen hebt.</p>
                    <p>Dank u wel</p>
                    <p>Betreft:</p>
                    <p>HR-afdeling,</p>
                    <p>{ app_name }</p>',
                    'pl' => '<p>Temat: -Dział kadr/Firma w celu wysłania listu gratulacyjnego dla promocji zatrudnienia.</p>
                    <p>Szanowny {employee_name },</p>
                    <p>Gratulacje dla awansowania do {promotion_designation } {promotion_title } efektywnej {promotion_date }.</p>
                    <p>W dalszym ciągu oczekujemy konsekwencji i wspaniałych wynik&oacute;w w Twojej nowej roli. Mamy nadzieję, że postawicie na przykład dla pozostałych pracownik&oacute;w organizacji.</p>
                    <p>Życzymy powodzenia dla przyszłych wynik&oacute;w, gratulujemy!.</p>
                    <p>Jeszcze raz gratulacje na nowej pozycji.</p>
                    <p>Czuj się swobodnie, jeśli masz jakieś pytania.</p>
                    <p>Dziękujemy</p>
                    <p>W odniesieniu do</p>
                    <p>Dział HR,</p>
                    <p>{app_name }</p>',
                    'ru' => '<p>Тема: -HR отдел/Компания для отправки письма с поздравлением.</p>
                    <p>Уважаемый { employee_name },</p>
                    <p>Поздравляем вас с продвижением в { promotion_designation } { promotion_title } эффективная { promotion_date }.</p>
                    <p>Мы будем и впредь ожидать от вас соответствия и больших результатов в вашей новой роли. Мы надеемся, что вы станете примером для других сотрудников организации.</p>
                    <p>Желаем вам удачи и поздравлений!</p>
                    <p>Еще раз поздравляю с новой позицией.</p>
                    <p>Не стеснитесь, если у вас есть вопросы.</p>
                    <p>Спасибо.</p>
                    <p>С уважением,</p>
                    <p>Отдел кадров,</p>
                    <p>{ app_name }</p>',
                    'pt' => '<p style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de felicita&ccedil;&atilde;o de promo&ccedil;&atilde;o de emprego.</p>
                    <p style="font-size: 14.4px;">Querido {employee_name},</p>
                    <p style="font-size: 14.4px;">Parab&eacute;ns pela sua promo&ccedil;&atilde;o para {promotion_designation} {promotion_title} efetivo {promotion_date}.</p>
                    <p style="font-size: 14.4px;">Continuaremos a esperar consist&ecirc;ncia e grandes resultados a partir de voc&ecirc; em seu novo papel. Esperamos que voc&ecirc; defina um exemplo para os demais funcion&aacute;rios da organiza&ccedil;&atilde;o.</p>
                    <p style="font-size: 14.4px;">Desejamos sorte para o seu desempenho futuro, e parab&eacute;ns!.</p>
                    <p style="font-size: 14.4px;">Novamente, parab&eacute;ns pela nova posi&ccedil;&atilde;o.</p>
                    <p style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</p>
                    <p style="font-size: 14.4px;">Obrigado</p>
                    <p style="font-size: 14.4px;">Considera,</p>
                    <p style="font-size: 14.4px;">Departamento de RH,</p>
                    <p style="font-size: 14.4px;">{app_name}</p>',
                    'tr' => '<p> </p>
                    <p><strong>Konu:-İş promosyonu tebrik mektubu gönderilecek İK departmanı/Şirket.</strong></p>
                    <p><strong>Sayın {employee_name},</strong></p>
                    <p>Geçerli {promotion_date} olan {promotion_designation} {promotion_title} promosyonunuz için tebrikler.</p>
                    <p>Yeni görevinizde sizden tutarlılık ve harika sonuçlar beklemeye devam edeceğiz. Kurumun diğer çalışanlarına da örnek olacağını umuyoruz.</p>
                    <p>Gelecekteki performansınız için size bol şans diliyor, tebrikler!.</p>
                    <p>Yeni pozisyon için tekrar tebrikler.</p>
                    <p> </p>
                    <p>Sorularınız varsa bize ulaşmaktan çekinmeyin.</p>
                    <p>Teşekkürler</p>
                    <p><strong>Saygılarımızla,</strong></p>
                    <p><strong>İK Departmanı</strong></p>
                    <p><strong>{uygulama_adı</strong></p>',
                    'pt-br' => '<p style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de felicita&ccedil;&atilde;o de promo&ccedil;&atilde;o de emprego.</p>
                    <p style="font-size: 14.4px;">Querido {employee_name},</p>
                    <p style="font-size: 14.4px;">Parab&eacute;ns pela sua promo&ccedil;&atilde;o para {promotion_designation} {promotion_title} efetivo {promotion_date}.</p>
                    <p style="font-size: 14.4px;">Continuaremos a esperar consist&ecirc;ncia e grandes resultados a partir de voc&ecirc; em seu novo papel. Esperamos que voc&ecirc; defina um exemplo para os demais funcion&aacute;rios da organiza&ccedil;&atilde;o.</p>
                    <p style="font-size: 14.4px;">Desejamos sorte para o seu desempenho futuro, e parab&eacute;ns!.</p>
                    <p style="font-size: 14.4px;">Novamente, parab&eacute;ns pela nova posi&ccedil;&atilde;o.</p>
                    <p style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</p>
                    <p style="font-size: 14.4px;">Obrigado</p>
                    <p style="font-size: 14.4px;">Considera,</p>
                    <p style="font-size: 14.4px;">Departamento de RH,</p>
                    <p style="font-size: 14.4px;">{app_name}</p>',
                ],
            ],
            'resignation_sent' => [
                'subject' => 'Resignation Sent',
                'lang' => [
                    'ar' => '<p>Subject :-قسم الموارد البشرية / الشركة لإرسال خطاب استقالته.</p>
                    <p>عزيزي { assign_user } ،</p>
                    <p>إنه لمن دواعي الأسف الشديد أن أعترف رسميا باستلام إشعار استقالتك في { notice_date } الى { resignation_date } هو اليوم الأخير لعملك.</p>
                    <p>لقد كان من دواعي سروري العمل معكم ، وبالنيابة عن الفريق ، أود أن أتمنى لكم أفضل جدا في جميع مساعيكم في المستقبل. ومن خلال هذه الرسالة ، يرجى العثور على حزمة معلومات تتضمن معلومات مفصلة عن عملية الاستقالة.</p>
                    <p>شكرا لكم مرة أخرى على موقفكم الإيجابي والعمل الجاد كل هذه السنوات.</p>
                    <p>إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة.</p>
                    <p>شكرا لك</p>
                    <p>Regards,</p>
                    <p>إدارة الموارد البشرية ،</p>
                    <p>{ app_name }</p>',
                    'zh' => '<p ><b>主题：-人力资源部门/公司发送辞职信。</b></p>
                    <p ><b>亲爱的{assign_user}，</b></p>
                    <p>我非常遗憾地正式确认在 {notice_date} 收到您的辞职通知，至 {resignation_date} 是您工作的最后一天。 </p>
                    <p>很高兴与您合作，我谨代表团队祝愿您在未来的工作中一切顺利。请在这封信中找到一个信息包，其中包含有关辞职流程的详细信息。 </p>
                    <p>再次感谢您这些年来的积极态度和辛勤工作。</p>
                    <p>如果您有任何疑问，请随时与我们联系。</p>
                    <p>谢谢</p>
                    <p><b>问候，</b></p>
                    <p><b>人力资源部</b></p>
                    <p><b>{app_name}</b></p>',
                    'da' => '<p>Om: HR-afdelingen / Kompagniet, for at sende en opsigelse.</p>
                    <p>K&aelig;re { assign_user },</p>
                    <p>Det er med stor beklagelse, at jeg formelt anerkender modtagelsen af din opsigelsesmeddelelse p&aring; { notice_date } til { resignation_date } er din sidste arbejdsdag</p>
                    <p>Det har v&aelig;ret en forn&oslash;jelse at arbejde sammen med Dem, og p&aring; vegne af teamet vil jeg &oslash;nske Dem det bedste i alle Deres fremtidige bestr&aelig;belser. Med dette brev kan du finde en informationspakke med detaljerede oplysninger om tilbagetr&aelig;delsesprocessen.</p>
                    <p>Endnu en gang tak for Deres positive holdning og h&aring;rde arbejde i alle disse &aring;r.</p>
                    <p>Du er velkommen til at r&aelig;kke ud, hvis du har nogen sp&oslash;rgsm&aring;l.</p>
                    <p>Tak.</p>
                    <p>Med venlig hilsen</p>
                    <p>HR-afdelingen,</p>
                    <p>{ app_name }</p>',
                    'de' => '<p>Betreff: -Personalabteilung/Firma, um R&uuml;ckmeldungsschreiben zu senden.</p>
                    <p>Sehr geehrter {assign_user},</p>
                    <p>Es ist mit gro&szlig;em Bedauern, dass ich den Eingang Ihrer R&uuml;cktrittshinweis auf {notice_date} an {resignation_date} offiziell best&auml;tige, ist Ihr letzter Arbeitstag.</p>
                    <p>Es war eine Freude, mit Ihnen zu arbeiten, und im Namen des Teams m&ouml;chte ich Ihnen w&uuml;nschen, dass Sie in allen Ihren zuk&uuml;nftigen Bem&uuml;hungen am besten sind. In diesem Brief finden Sie ein Informationspaket mit detaillierten Informationen zum R&uuml;cktrittsprozess.</p>
                    <p>Vielen Dank noch einmal f&uuml;r Ihre positive Einstellung und harte Arbeit all die Jahre.</p>
                    <p>F&uuml;hlen Sie sich frei, wenn Sie Fragen haben.</p>
                    <p>Danke.</p>
                    <p>Betrachtet,</p>
                    <p>Personalabteilung,</p>
                    <p>{app_name}</p>',
                    'en' => '<p ><b>Subject:-HR department/Company to send resignation letter .</b></p>
                    <p ><b>Dear {assign_user},</b></p>
                    <p >It is with great regret that I formally acknowledge receipt of your resignation notice on {notice_date} to {resignation_date} is your final day of work. </p>
                    <p >It has been a pleasure working with you, and on behalf of the team, I would like to wish you the very best in all your future endeavors. Included with this letter, please find an information packet with detailed information on the resignation process. </p>
                    <p>Thank you again for your positive attitude and hard work all these years.</p>
                    <p>Feel free to reach out if you have any questions.</p>
                    <p>Thank you</p>
                    <p><b>Regards,</b></p>EmailTemplate
                    <p><b>HR Department,</b></p>
                    <p><b>{app_name}</b></p>',
                    'es' => '<p>Asunto: -Departamento de RRHH/Empresa para enviar carta de renuncia.</p>
                    <p>Estimado {assign_user},</p>
                    <p>Es con gran pesar que recibo formalmente la recepci&oacute;n de su aviso de renuncia en {notice_date} a {resignation_date} es su &uacute;ltimo d&iacute;a de trabajo.</p>
                    <p>Ha sido un placer trabajar con usted, y en nombre del equipo, me gustar&iacute;a desearle lo mejor en todos sus esfuerzos futuros. Incluido con esta carta, por favor encuentre un paquete de informaci&oacute;n con informaci&oacute;n detallada sobre el proceso de renuncia.</p>
                    <p>Gracias de nuevo por su actitud positiva y trabajo duro todos estos a&ntilde;os.</p>
                    <p>Si&eacute;ntase libre de llegar si usted tiene alguna pregunta.</p>
                    <p>&iexcl;Gracias!</p>
                    <p>Considerando,</p>
                    <p>Departamento de Recursos Humanos,</p>
                    <p>{app_name}</p>',
                    'fr' => '<p>Objet: -D&eacute;partement RH / Soci&eacute;t&eacute; denvoi dune lettre de d&eacute;mission.</p>
                    <p>Cher { assign_user },</p>
                    <p>Cest avec grand regret que je reconnais officiellement la r&eacute;ception de votre avis de d&eacute;mission sur { notice_date } &agrave; { resignation_date } est votre dernier jour de travail.</p>
                    <p>Cest un plaisir de travailler avec vous, et au nom de l&eacute;quipe, jaimerais vous souhaiter le meilleur dans toutes vos activit&eacute;s futures. Inclus avec cette lettre, veuillez trouver un paquet dinformation contenant des informations d&eacute;taill&eacute;es sur le processus de d&eacute;mission.</p>
                    <p>Je vous remercie encore de votre attitude positive et de votre travail acharne durant toutes ces ann&eacute;es.</p>
                    <p>Nh&eacute;sitez pas &agrave; nous contacter si vous avez des questions.</p>
                    <p>Je vous remercie</p>
                    <p>Regards,</p>
                    <p>D&eacute;partement des RH,</p>
                    <p>{ app_name }</p>',
                    'he' => '<p ><b>נושא:-מחלקת משאבי אנוש/חברה לשלוח מכתב התפטרות .</b></p>
                    <p ><b>{assign_user} היקר,</b></p>
                    <p >בצער רב אני מאשר רשמית את קבלת הודעת ההתפטרות שלך בתאריך {notice_date} עד {resignation_date} הוא היום האחרון לעבודתך. </p>
                    <p > היה לי תענוג לעבוד איתך, ובשם הצוות, אני רוצה לאחל לך את הטוב ביותר בכל העשייה העתידית שלך. מצורף למכתב זה, נא למצוא חבילת מידע עם מידע מפורט על תהליך ההתפטרות. </p>
                    <p>שוב תודה לך על הגישה החיובית והעבודה הקשה שלך כל השנים הללו.</p>
                    <p>אל תהסס לפנות אם יש לך שאלות.</p>
                    <p>תודה</p>
                    <p><b>בברכה,</b></p>
                    <p><b>מחלקת משאבי אנוש,</b></p>
                    <p><b>{app_name}</b></p>',
                    'it' => '<p>Oggetto: - Dipartimento HR / Societ&agrave; per inviare lettera di dimissioni.</p>
                    <p>Caro {assign_user},</p>
                    <p>&Egrave; con grande dispiacere che riconosca formalmente la ricezione del tuo avviso di dimissioni su {notice_date} a {resignation_date} &egrave; la tua giornata di lavoro finale.</p>
                    <p>&Egrave; stato un piacere lavorare con voi, e a nome della squadra, vorrei augurarvi il massimo in tutti i vostri futuri sforzi. Incluso con questa lettera, si prega di trovare un pacchetto informativo con informazioni dettagliate sul processo di dimissioni.</p>
                    <p>Grazie ancora per il vostro atteggiamento positivo e duro lavoro in tutti questi anni.</p>
                    <p>Sentiti libero di raggiungere se hai domande.</p>
                    <p>Grazie</p>
                    <p>Riguardo,</p>
                    <p>Dipartimento HR,</p>
                    <p>{app_name}</p>',
                    'ja' => '<p>件名:-HR 部門/企業は辞表を送信します。</p>
                    <p>{assign_user} の認証を解除します。</p>
                    <p>{ notice_date} に対するあなたの辞任通知を { resignation_date} に正式に受理することを正式に確認することは、非常に残念です。</p>
                    <p>あなたと一緒に仕事をしていて、チームのために、あなたの将来の努力において、あなたのことを最高のものにしたいと思っています。 このレターには、辞任プロセスに関する詳細な情報が記載されている情報パケットをご覧ください。</p>
                    <p>これらの長年の前向きな姿勢と努力を重ねて感謝します。</p>
                    <p>質問がある場合は、自由に連絡してください。</p>
                    <p>ありがとう</p>
                    <p>よろしく</p>
                    <p>HR 部門</p>
                    <p>{app_name}</p>',
                    'nl' => '<p>Betreft: -HR-afdeling/Bedrijf om ontslagbrief te sturen.</p>
                    <p>Geachte { assign_user },</p>
                    <p>Het is met grote spijt dat ik de ontvangst van uw ontslagbrief op { notice_date } tot { resignation_date } formeel de ontvangst van uw laatste dag van het werk bevestigt.</p>
                    <p>Het was een genoegen om met u samen te werken, en namens het team zou ik u het allerbeste willen wensen in al uw toekomstige inspanningen. Vermeld bij deze brief een informatiepakket met gedetailleerde informatie over het ontslagproces.</p>
                    <p>Nogmaals bedankt voor uw positieve houding en hard werken al die jaren.</p>
                    <p>Voel je vrij om uit te reiken als je vragen hebt.</p>
                    <p>Dank u wel</p>
                    <p>Betreft:</p>
                    <p>HR-afdeling,</p>
                    <p>{ app_name }</p>',
                    'pl' => '<p>Temat: -Dział HR/Firma do wysyłania listu rezygnacyjnego.</p>
                    <p>Drogi użytkownika {assign_user },</p>
                    <p>Z wielkim żalem, że oficjalnie potwierdzam otrzymanie powiadomienia o rezygnacji w dniu {notice_date } to {resignation_date } to tw&oacute;j ostatni dzień pracy.</p>
                    <p>Z przyjemnością wsp&oacute;łpracujemy z Tobą, a w imieniu zespołu chciałbym życzyć Wam wszystkiego najlepszego we wszystkich swoich przyszłych przedsięwzięciu. Dołączone do tego listu prosimy o znalezienie pakietu informacyjnego ze szczeg&oacute;łowymi informacjami na temat procesu dymisji.</p>
                    <p>Jeszcze raz dziękuję za pozytywne nastawienie i ciężką pracę przez te wszystkie lata.</p>
                    <p>Czuj się swobodnie, jeśli masz jakieś pytania.</p>
                    <p>Dziękujemy</p>
                    <p>W odniesieniu do</p>
                    <p>Dział HR,</p>
                    <p>{app_name }</p>',
                    'ru' => '<p>Тема: -HR отдел/Компания отправить письмо об отставке.</p>
                    <p>Уважаемый пользователь { assign_user },</p>
                    <p>С большим сожалением я официально подтверждаю получение вашего уведомления об отставке { notice_date } в { resignation_date }-это ваш последний день работы.</p>
                    <p>С Вами было приятно работать, и от имени команды я хотел бы по# желать вам самого лучшего во всех ваших будущих начинаниях. В этом письме Вы можете найти информационный пакет с подробной информацией об отставке.</p>
                    <p>Еще раз спасибо за ваше позитивное отношение и трудолюбие все эти годы.</p>
                    <p>Не стеснитесь, если у вас есть вопросы.</p>
                    <p>Спасибо.</p>
                    <p>С уважением,</p>
                    <p>Отдел кадров,</p>
                    <p>{ app_name }</p>',
                    'pt' => '<p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de demiss&atilde;o.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Querido {assign_user},</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">&Eacute; com grande pesar que reconhe&ccedil;o formalmente o recebimento do seu aviso de demiss&atilde;o em {notice_date} a {resignation_date} &eacute; o seu dia final de trabalho.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Foi um prazer trabalhar com voc&ecirc;, e em nome da equipe, gostaria de desej&aacute;-lo o melhor em todos os seus futuros empreendimentos. Inclu&iacute;dos com esta carta, por favor, encontre um pacote de informa&ccedil;&otilde;es com informa&ccedil;&otilde;es detalhadas sobre o processo de demiss&atilde;o.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Obrigado novamente por sua atitude positiva e trabalho duro todos esses anos.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Obrigado</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Considera,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Departamento de RH,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{app_name}</span></p>',
                    'tr' => '<p ><b>Konu:-İstifa mektubu gönderilecek İK departmanı/Şirket .</b></p>
                    <p ><b>Sayın {assign_user},</b></p>
                    <p >İstifa bildiriminizi {notice_date} ile {resignation_date} arasında aldığımı, işinizin son günü olduğunu büyük bir üzüntüyle kabul ediyorum. </p>
                    <p >Sizinle çalışmak bir zevkti ve ekip adına, gelecekteki tüm çabalarınızda size en iyisini diliyorum. Bu mektuba ek olarak, lütfen istifa süreci hakkında ayrıntılı bilgi içeren bir bilgi paketi bulun. </p>
                    <p>Bunca yıl boyunca gösterdiğiniz olumlu tutum ve sıkı çalışmanız için tekrar teşekkür ederiz.</p>
                    <p>Sorularınız varsa bize ulaşmaktan çekinmeyin.</p>
                    <p>Teşekkürler</p>
                    <p><b>Saygılarımızla,</b></p>
                    <p><b>İK Departmanı,</b></p>
                    <p><b>{uygulama_adı</b></p>',
                    'pt' => '<p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de demiss&atilde;o.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Querido {assign_user},</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">&Eacute; com grande pesar que reconhe&ccedil;o formalmente o recebimento do seu aviso de demiss&atilde;o em {notice_date} a {resignation_date} &eacute; o seu dia final de trabalho.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Foi um prazer trabalhar com voc&ecirc;, e em nome da equipe, gostaria de desej&aacute;-lo o melhor em todos os seus futuros empreendimentos. Inclu&iacute;dos com esta carta, por favor, encontre um pacote de informa&ccedil;&otilde;es com informa&ccedil;&otilde;es detalhadas sobre o processo de demiss&atilde;o.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Obrigado novamente por sua atitude positiva e trabalho duro todos esses anos.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Obrigado</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Considera,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Departamento de RH,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{app_name}</span></p>',
                ],
            ],
            'termination_sent' => [
                'subject' => 'Termination Sent',
                'lang' => [
                    'ar' => '<p style="text-align: left;"><span style="font-size: 12pt;"><span style="color: #222222;"><span style="white-space: pre-wrap;"><span style="font-size: 12pt; white-space: pre-wrap;">Subject :-ادارة / شركة HR لارسال رسالة انهاء. عزيزي { </span><span style="white-space: pre-wrap;">employee_termination_name</span><span style="font-size: 12pt; white-space: pre-wrap;"> } ، هذه الرسالة مكتوبة لإعلامك بأن عملك مع شركتنا قد تم إنهاؤه مزيد من التفاصيل عن الانهاء : تاريخ الاشعار : { </span><span style="white-space: pre-wrap;">notice_date</span><span style="font-size: 12pt; white-space: pre-wrap;"> } تاريخ الانهاء : { </span><span style="white-space: pre-wrap;">termination_date</span><span style="font-size: 12pt; white-space: pre-wrap;"> } نوع الانهاء : { termination_type } إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة. شكرا لك Regards, إدارة الموارد البشرية ، { app_name }</span></span></span></span></p>',
                    'zh' => '<p><strong>主题：-人力资源部门/公司发送终止信。</strong></p>
                    <p><strong>亲爱的{employee_termination_name}，</strong></p>
                    <p>这封信旨在通知您，您与我们公司的雇佣关系已终止。</p>
                    <p>有关终止的更多详细信息：</p>
                    <p>通知日期：{notice_date}</p>
                    <p>终止日期：{termination_date}</p>
                    <p>终止类型：{termination_type}</p>
                    <p> </p>
                    <p>如果您有任何疑问，请随时与我们联系。</p>
                    <p>谢谢</p>
                    <p><strong>问候，</strong></p>
                    <p><strong>人力资源部</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'da' => '<p>Emne:-HR-afdelingen / Virksomheden om at sende afslutningstskrivelse.</p>
                    <p>K&aelig;re { employee_termination_name },</p>
                    <p>Dette brev er skrevet for at meddele dig, at dit arbejde med vores virksomhed er afsluttet.</p>
                    <p>Flere oplysninger om oph&aelig;velse:</p>
                    <p>Adviseringsdato: { notifice_date }</p>
                    <p>Opsigelsesdato: { termination_date }</p>
                    <p>Opsigelsestype: { termination_type }</p>
                    <p>Du er velkommen til at r&aelig;kke ud, hvis du har nogen sp&oslash;rgsm&aring;l.</p>
                    <p>Tak.</p>
                    <p>Med venlig hilsen</p>
                    <p>HR-afdelingen,</p>
                    <p>{ app_name }</p>',
                    'de' => '<p>Betreff: -Personalabteilung/Firma zum Versenden von K&uuml;ndigungsschreiben.</p>
                    <p>Sehr geehrter {employee_termination_name},</p>
                    <p>Dieser Brief wird Ihnen schriftlich mitgeteilt, dass Ihre Besch&auml;ftigung mit unserem Unternehmen beendet ist.</p>
                    <p>Weitere Details zur K&uuml;ndigung:</p>
                    <p>K&uuml;ndigungsdatum: {notice_date}</p>
                    <p>Beendigungsdatum: {termination_date}</p>
                    <p>Abbruchstyp: {termination_type}</p>
                    <p>F&uuml;hlen Sie sich frei, wenn Sie Fragen haben.</p>
                    <p>Danke.</p>
                    <p>Betrachtet,</p>
                    <p>Personalabteilung,</p>
                    <p>{app_name}</p>',
                    'en' => '<p><strong>Subject:-HR department/Company to send termination letter.</strong></p>
                    <p><strong>Dear {employee_termination_name},</strong></p>
                    <p>This letter is written to notify you that your employment with our company is terminated.</p>
                    <p>More detail about termination:</p>
                    <p>Notice Date :{notice_date}</p>
                    <p>Termination Date:{termination_date}</p>
                    <p>Termination Type:{termination_type}</p>
                    <p>&nbsp;</p>
                    <p>Feel free to reach out if you have any questions.</p>
                    <p>Thank you</p>
                    <p><strong>Regards,</strong></p>
                    <p><strong>HR Department,</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'es' => '<p>Asunto: -Departamento de RRHH/Empresa para enviar carta de rescisi&oacute;n.</p>
                    <p>Estimado {employee_termination_name},</p>
                    <p>Esta carta est&aacute; escrita para notificarle que su empleo con nuestra empresa ha terminado.</p>
                    <p>M&aacute;s detalles sobre la terminaci&oacute;n:</p>
                    <p>Fecha de aviso: {notice_date}</p>
                    <p>Fecha de terminaci&oacute;n: {termination_date}</p>
                    <p>Tipo de terminaci&oacute;n: {termination_type}</p>
                    <p>Si&eacute;ntase libre de llegar si usted tiene alguna pregunta.</p>
                    <p>&iexcl;Gracias!</p>
                    <p>Considerando,</p>
                    <p>Departamento de Recursos Humanos,</p>
                    <p>{app_name}</p>',
                    'fr' => '<p>Objet: -HR department / Company to send termination letter.</p>
                    <p>Cher { employee_termination_name },</p>
                    <p>Cette lettre est r&eacute;dig&eacute;e pour vous aviser que votre emploi aupr&egrave;s de notre entreprise prend fin.</p>
                    <p>Plus de d&eacute;tails sur larr&ecirc;t:</p>
                    <p>Date de lavis: { notice_date }</p>
                    <p>Date de fin: { termination_date}</p>
                    <p>Type de terminaison: { termination_type }</p>
                    <p>Nh&eacute;sitez pas &agrave; nous contacter si vous avez des questions.</p>
                    <p>Je vous remercie</p>
                    <p>Regards,</p>
                    <p>D&eacute;partement des RH,</p>
                    <p>{ app_name }</p>',
                    'he' => '<p><strong>נושא:-מחלקת משאבי אנוש/חברה לשלוח מכתב סיום.</strong></p>
                    <p><strong>{employee_termination_name} היקר,</strong></p>
                    <p>מכתב זה נכתב כדי להודיע ​​לך שהעסקתך בחברה שלנו הופסקה.</p>
                    <p>פרטים נוספים על סיום:</p>
                    <p>תאריך הודעה :{notice_date}</p>
                    <p>תאריך סיום:{termination_date}</p>
                    <p>סוג סיום:{termination_type}</p>
                    <p> </p>
                    <p>אל תהסס לפנות אם יש לך שאלות.</p>
                    <p>תודה</p>
                    <p><strong>בברכה,</strong></p>
                    <p><strong>מחלקת משאבי אנוש,</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'it' => '<p>Oggetto: - Dipartimento HR / Societ&agrave; per inviare lettera di terminazione.</p>
                    <p>Caro {employee_termination_name},</p>
                    <p>Questa lettera &egrave; scritta per comunicarti che la tua occupazione con la nostra azienda &egrave; terminata.</p>
                    <p>Pi&ugrave; dettagli sulla cessazione:</p>
                    <p>Data avviso: {notice_data}</p>
                    <p>Data di chiusura: {termination_date}</p>
                    <p>Tipo di terminazione: {termination_type}</p>
                    <p>Sentiti libero di raggiungere se hai domande.</p>
                    <p>Grazie</p>
                    <p>Riguardo,</p>
                    <p>Dipartimento HR,</p>
                    <p>{app_name}</p>',
                    'ja' => '<p>件名:-HR 部門/企業は終了文字を送信します。</p>
                    <p>{ employee_termination_name} を終了します。</p>
                    <p>この手紙は、当社の雇用が終了していることをあなたに通知するために書かれています。</p>
                    <p>終了についての詳細 :</p>
                    <p>通知日 :{notice_date}</p>
                    <p>終了日:{termination_date}</p>
                    <p>終了タイプ:{termination_type}</p>
                    <p>質問がある場合は、自由に連絡してください。</p>
                    <p>ありがとう</p>
                    <p>よろしく</p>
                    <p>HR 部門</p>
                    <p>{app_name}</p>',
                    'nl' => '<p>Betreft: -HR-afdeling/Bedrijf voor verzending van afgiftebrief.</p>
                    <p>Geachte { employee_termination_name },</p>
                    <p>Deze brief is geschreven om u te melden dat uw werk met ons bedrijf wordt be&euml;indigd.</p>
                    <p>Meer details over be&euml;indiging:</p>
                    <p>Datum kennisgeving: { notice_date }</p>
                    <p>Be&euml;indigingsdatum: { termination_date }</p>
                    <p>Be&euml;indigingstype: { termination_type }</p>
                    <p>Voel je vrij om uit te reiken als je vragen hebt.</p>
                    <p>Dank u wel</p>
                    <p>Betreft:</p>
                    <p>HR-afdeling,</p>
                    <p>{ app_name }</p>',
                    'pl' => '<p>Temat: -Dział kadr/Firma do wysyłania listu zakańczego.</p>
                    <p>Droga {employee_termination_name },</p>
                    <p>Ten list jest napisany, aby poinformować Cię, że Twoje zatrudnienie z naszą firmą zostaje zakończone.</p>
                    <p>Więcej szczeg&oacute;ł&oacute;w na temat zakończenia pracy:</p>
                    <p>Data ogłoszenia: {notice_date }</p>
                    <p>Data zakończenia: {termination_date }</p>
                    <p>Typ zakończenia: {termination_type }</p>
                    <p>Czuj się swobodnie, jeśli masz jakieś pytania.</p>
                    <p>Dziękujemy</p>
                    <p>W odniesieniu do</p>
                    <p>Dział HR,</p>
                    <p>{app_name }</p>',
                    'ru' => '<p>Тема: -HR отдел/Компания отправить письмо о прекращении.</p>
                    <p>Уважаемый { employee_termination_name },</p>
                    <p>Это письмо написано, чтобы уведомить вас о том, что ваше трудоустройство с нашей компанией прекратилось.</p>
                    <p>Более подробная информация о завершении:</p>
                    <p>Дата уведомления: { notice_date }</p>
                    <p>Дата завершения: { termination_date }</p>
                    <p>Тип завершения: { termination_type }</p>
                    <p>Не стеснитесь, если у вас есть вопросы.</p>
                    <p>Спасибо.</p>
                    <p>С уважением,</p>
                    <p>Отдел кадров,</p>
                    <p>{ app_name }</p>',
                    'pt' => '<p style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de rescis&atilde;o.</p>
                    <p style="font-size: 14.4px;">Querido {employee_termination_name},</p>
                    <p style="font-size: 14.4px;">Esta carta &eacute; escrita para notific&aacute;-lo de que seu emprego com a nossa empresa est&aacute; finalizado.</p>
                    <p style="font-size: 14.4px;">Mais detalhes sobre a finaliza&ccedil;&atilde;o:</p>
                    <p style="font-size: 14.4px;">Data de Aviso: {notice_date}</p>
                    <p style="font-size: 14.4px;">Data de Finaliza&ccedil;&atilde;o: {termination_date}</p>
                    <p style="font-size: 14.4px;">Tipo de Rescis&atilde;o: {termination_type}</p>
                    <p style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</p>
                    <p style="font-size: 14.4px;">Obrigado</p>
                    <p style="font-size: 14.4px;">Considera,</p>
                    <p style="font-size: 14.4px;">Departamento de RH,</p>
                    <p style="font-size: 14.4px;">{app_name}</p>',
                    'tr' => '<p><strong>Konu:-İK departmanı/Şirket fesih mektubu gönderecek.</strong></p>
                    <p><strong>Sayın {employee_termination_name},</strong></p>
                    <p>Bu mektup, şirketimizle olan işinize son verildiğini size bildirmek için yazılmıştır.</p>
                    <p>Sonlandırma hakkında daha fazla ayrıntı:</p>
                    <p>Bildirim Tarihi :{notice_date}</p>
                    <p>Sonlandırma Tarihi:{termination_date}</p>
                    <p>Sonlandırma Türü:{termination_type}</p>
                    <p> </p>
                    <p>Sorularınız varsa bize ulaşmaktan çekinmeyin.</p>
                    <p>Teşekkürler</p>
                    <p><strong>Saygılarımızla,</strong></p>
                    <p><strong>İK Departmanı</strong></p>
                    <p><strong>{uygulama_adı</strong></p>',
                    'pt-br' => '<p style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de rescis&atilde;o.</p>
                    <p style="font-size: 14.4px;">Querido {employee_termination_name},</p>
                    <p style="font-size: 14.4px;">Esta carta &eacute; escrita para notific&aacute;-lo de que seu emprego com a nossa empresa est&aacute; finalizado.</p>
                    <p style="font-size: 14.4px;">Mais detalhes sobre a finaliza&ccedil;&atilde;o:</p>
                    <p style="font-size: 14.4px;">Data de Aviso: {notice_date}</p>
                    <p style="font-size: 14.4px;">Data de Finaliza&ccedil;&atilde;o: {termination_date}</p>
                    <p style="font-size: 14.4px;">Tipo de Rescis&atilde;o: {termination_type}</p>
                    <p style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</p>
                    <p style="font-size: 14.4px;">Obrigado</p>
                    <p style="font-size: 14.4px;">Considera,</p>
                    <p style="font-size: 14.4px;">Departamento de RH,</p>
                    <p style="font-size: 14.4px;">{app_name}</p>',
                ],
            ],
            'transfer_sent' => [
                'subject' => 'Transfer Sent',
                'lang' => [
                    'ar' => '<p>Subject : -HR ادارة / شركة لارسال خطاب نقل الى موظف من مكان الى آخر.</p>
                    <p>عزيزي { transfer_name },</p>
                    <p>وفقا لتوجيهات الادارة ، يتم نقل الخدمات الخاصة بك w.e.f. { transfer_date }.</p>
                    <p>مكان الادخال الجديد الخاص بك هو { transfer_department } قسم من فرع { transfer_branch } وتاريخ التحويل { transfer_date }.</p>
                    <p>{ transfer_description }.</p>
                    <p>إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة.</p>
                    <p>شكرا لك</p>
                    <p>Regards,</p>
                    <p>إدارة الموارد البشرية ،</p>
                    <p>{ app_name }</p>',
                    'zh' => '<p ><b>主题：-人力资源部门/公司将向员工从一个地点发送到另一个地点的调动信。</b></p>
                    <p ><b>亲爱的{transfer_name}，</b></p>
                    <p >根据管理层指令，您的服务将于 {transfer_date} 转移。 </p>
                    <p >您的新邮寄地点是 {transfer_branch} 分行的 {transfer_department} 部门，转移日期为 {transfer_date}。 </p>
                    {转让说明}。
                    <p>如果您有任何疑问，请随时与我们联系。</p>
                    <p><b>谢谢</b></p>
                    <p><b>问候，</b></p>
                    <p><b>人力资源部</b></p>
                    <p><b>{app_name}</b></p>',
                    'da' => '<p>Emne:-HR-afdelingen / kompagniet om at sende overf&oslash;rsels brev til en medarbejder fra den ene lokalitet til den anden.</p>
                    <p>K&aelig;re { transfer_name },</p>
                    <p>Som Styring af direktiver overf&oslash;res dine serviceydelser w.e.f. { transfer_date }.</p>
                    <p>Dit nye sted for postering er { transfer_departement } afdeling af { transfer_branch } gren og dato for overf&oslash;rsel { transfer_date }.</p>
                    <p>{ transfer_description }.</p>
                    <p>Du er velkommen til at r&aelig;kke ud, hvis du har nogen sp&oslash;rgsm&aring;l.</p>
                    <p>Tak.</p>
                    <p>Med venlig hilsen</p>
                    <p>HR-afdelingen,</p>
                    <p>{ app_name }</p>',
                    'de' => '<p>Betreff: -Personalabteilung/Unternehmen, um einen &Uuml;berweisungsschreiben an einen Mitarbeiter von einem Standort an einen anderen zu senden.</p>
                    <p>Sehr geehrter {transfer_name},</p>
                    <p>Wie pro Management-Direktiven werden Ihre Dienste &uuml;ber w.e.f. {transfer_date} &uuml;bertragen.</p>
                    <p>Ihr neuer Ort der Entsendung ist {transfer_department} Abteilung von {transfer_branch} Niederlassung und Datum der &Uuml;bertragung {transfer_date}.</p>
                    <p>{transfer_description}.</p>
                    <p>F&uuml;hlen Sie sich frei, wenn Sie Fragen haben.</p>
                    <p>Danke.</p>
                    <p>Betrachtet,</p>
                    <p>Personalabteilung,</p>
                    <p>{app_name}</p>',
                    'en' => '<p ><b>Subject:-HR department/Company to send transfer letter to be issued to an employee from one location to another.</b></p>
                    <p ><b>Dear {transfer_name},</b></p>
                    <p >As per Management directives, your services are being transferred w.e.f.{transfer_date}. </p>
                    <p >Your new place of posting is {transfer_department} department of {transfer_branch} branch and date of transfer {transfer_date}. </p>
                    {transfer_description}.
                    <p>Feel free to reach out if you have any questions.</p>
                    <p><b>Thank you</b></p>
                    <p><b>Regards,</b></p>
                    <p><b>HR Department,</b></p>
                    <p><b>{app_name}</b></p>',
                    'es' => '<p>Asunto: -Departamento de RR.HH./Empresa para enviar carta de transferencia a un empleado de un lugar a otro.</p>
                    <p>Estimado {transfer_name},</p>
                    <p>Seg&uacute;n las directivas de gesti&oacute;n, los servicios se transfieren w.e.f. {transfer_date}.</p>
                    <p>El nuevo lugar de publicaci&oacute;n es el departamento {transfer_department} de la rama {transfer_branch} y la fecha de transferencia {transfer_date}.</p>
                    <p>{transfer_description}.</p>
                    <p>Si&eacute;ntase libre de llegar si usted tiene alguna pregunta.</p>
                    <p>&iexcl;Gracias!</p>
                    <p>Considerando,</p>
                    <p>Departamento de Recursos Humanos,</p>
                    <p>{app_name}</p>',
                    'fr' => '<p>Objet: -Minist&egrave;re des RH / Soci&eacute;t&eacute; denvoi dune lettre de transfert &agrave; un employ&eacute; dun endroit &agrave; un autre.</p>
                    <p>Cher { transfer_name },</p>
                    <p>Selon les directives de gestion, vos services sont transf&eacute;r&eacute;s dans w.e.f. { transfer_date }.</p>
                    <p>Votre nouveau lieu daffectation est le d&eacute;partement { transfer_department } de la branche { transfer_branch } et la date de transfert { transfer_date }.</p>
                    <p>{ description_transfert }.</p>
                    <p>Nh&eacute;sitez pas &agrave; nous contacter si vous avez des questions.</p>
                    <p>Je vous remercie</p>
                    <p>Regards,</p>
                    <p>D&eacute;partement des RH,</p>
                    <p>{ app_name }</p>',
                    'he' => '<p ><b>נושא:-מחלקת משאבי אנוש/חברה לשלוח מכתב העברה שיונפק לעובד ממקום אחד למשנהו.</b></p>
                    <p ><b>{transfer_name} היקר,</b></p>
                    <p >לפי הנחיות ההנהלה, השירותים שלך מועברים עם {transfer_date}. </p>
                    <p >מקום הפרסום החדש שלך הוא מחלקת {transfer_department} של סניף {transfer_branch} ותאריך ההעברה {transfer_date}. </p>
                    {transfer_description}.
                    <p>אל תהסס לפנות אם יש לך שאלות.</p>
                    <p><b>תודה</b></p>
                    <p><b>בברכה,</b></p>
                    <p><b>מחלקת משאבי אנוש,</b></p>
                    <p><b>{app_name}</b></p>',
                    'it' => '<p>Oggetto: - Dipartimento HR / Societ&agrave; per inviare lettera di trasferimento da rilasciare a un dipendente da una localit&agrave; allaltra.</p>
                    <p>Caro {transfer_name},</p>
                    <p>Come per le direttive di Management, i tuoi servizi vengono trasferiti w.e.f. {transfer_date}.</p>
                    <p>Il tuo nuovo luogo di distacco &egrave; {transfer_department} dipartimento di {transfer_branch} ramo e data di trasferimento {transfer_date}.</p>
                    <p>{transfer_description}.</p>
                    <p>Sentiti libero di raggiungere se hai domande.</p>
                    <p>Grazie</p>
                    <p>Riguardo,</p>
                    <p>Dipartimento HR,</p>
                    <p>{app_name}</p>',
                    'ja' => '<p>Oggetto: - Dipartimento HR / Societ&agrave; per inviare lettera di trasferimento da rilasciare a un dipendente da una localit&agrave; allaltra.</p>
                    <p>Caro {transfer_name},</p>
                    <p>Come per le direttive di Management, i tuoi servizi vengono trasferiti w.e.f. {transfer_date}.</p>
                    <p>Il tuo nuovo luogo di distacco &egrave; {transfer_department} dipartimento di {transfer_branch} ramo e data di trasferimento {transfer_date}.</p>
                    <p>{transfer_description}.</p>
                    <p>Sentiti libero di raggiungere se hai domande.</p>
                    <p>Grazie</p>
                    <p>Riguardo,</p>
                    <p>Dipartimento HR,</p>
                    <p>{app_name}</p>',
                    'nl' => '<p>Betreft: -HR-afdeling/Bedrijf voor verzending van overdrachtsbrief aan een werknemer van de ene plaats naar de andere.</p>
                    <p>Geachte { transfer_name },</p>
                    <p>Als per beheerinstructie worden uw services overgebracht w.e.f. { transfer_date }.</p>
                    <p>Uw nieuwe plaats van post is { transfer_department } van de afdeling { transfer_branch } en datum van overdracht { transfer_date }.</p>
                    <p>{ transfer_description }.</p>
                    <p>Voel je vrij om uit te reiken als je vragen hebt.</p>
                    <p>Dank u wel</p>
                    <p>Betreft:</p>
                    <p>HR-afdeling,</p>
                    <p>{ app_name }</p>',
                    'pl' => '<p>Temat:-Dział HR/Firma do wysyłania listu przelewowego, kt&oacute;ry ma być wydany pracownikowi z jednego miejsca do drugiego.</p>
                    <p>Droga {transfer_name },</p>
                    <p>Zgodnie z dyrektywami zarządzania, Twoje usługi są przesyłane w.e.f. {transfer_date }.</p>
                    <p>Twoje nowe miejsce delegowania to {transfer_department } dział {transfer_branch } gałąź i data transferu {transfer_date }.</p>
                    <p>{transfer_description }.</p>
                    <p>Czuj się swobodnie, jeśli masz jakieś pytania.</p>
                    <p>Dziękujemy</p>
                    <p>W odniesieniu do</p>
                    <p>Dział HR,</p>
                    <p>{app_name }</p>',
                    'ru' => '<p>Тема: -HR отдел/Компания для отправки трансферного письма сотруднику из одного места в другое.</p>
                    <p>Уважаемый { transfer_name },</p>
                    <p>В соответствии с директивами управления ваши службы передаются .ef. { transfer_date }.</p>
                    <p>Новое место разноски: { transfer_department} подразделение { transfer_branch } и дата передачи { transfer_date }.</p>
                    <p>{ transfer_description }.</p>
                    <p>Не стеснитесь, если у вас есть вопросы.</p>
                    <p>Спасибо.</p>
                    <p>С уважением,</p>
                    <p>Отдел кадров,</p>
                    <p>{ app_name }</p>',
                    'pt' => '<p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de transfer&ecirc;ncia para ser emitida para um funcion&aacute;rio de um local para outro.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Querido {transfer_name},</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Conforme diretivas de Gerenciamento, seus servi&ccedil;os est&atilde;o sendo transferidos w.e.f. {transfer_date}.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">O seu novo local de postagem &eacute; {transfer_departamento} departamento de {transfer_branch} ramo e data de transfer&ecirc;ncia {transfer_date}.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{transfer_description}.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Obrigado</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Considera,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Departamento de RH,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{app_name}</span></p>',
                    'tr' => '<p ><b>Konu:-İK departmanının/Şirketin, bir çalışana bir yerden başka bir yere gönderilecek transfer mektubunu göndermesi.</b></p>
                    <p ><b>Sevgili {transfer_name},</b></p>
                    <p >Yönetim yönergeleri uyarınca, hizmetleriniz {transfer_date} tarihinde aktarılıyor. </p>
                    <p >Yeni görev yeriniz {transfer_branch} şubesinin {transfer_department} departmanı ve transfer tarihi {transfer_date}. </p>
                    {transfer açıklaması}.
                    <p>Sorularınız varsa bize ulaşmaktan çekinmeyin.</p>
                    <p><b>Teşekkürler</b></p>
                    <p><b>Saygılarımızla,</b></p>
                    <p><b>İK Departmanı,</b></p>
                    <p><b>{uygulama_adı</b></p>',
                    'pt-br' => '<p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de transfer&ecirc;ncia para ser emitida para um funcion&aacute;rio de um local para outro.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Querido {transfer_name},</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Conforme diretivas de Gerenciamento, seus servi&ccedil;os est&atilde;o sendo transferidos w.e.f. {transfer_date}.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">O seu novo local de postagem &eacute; {transfer_departamento} departamento de {transfer_branch} ramo e data de transfer&ecirc;ncia {transfer_date}.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{transfer_description}.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Obrigado</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Considera,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Departamento de RH,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{app_name}</span></p>',

                ],
            ],
            'trip_sent' => [
                'subject' => 'Trip Sent',
                'lang' => [
                    'ar' => '<p>Subject : -HR ادارة / شركة لارسال رسالة رحلة.</p>
                    <p>عزيزي { employee_name },</p>
                    <p>قمة الصباح إليك ! أكتب إلى مكتب إدارتكم بطلب متواضع للسفر من أجل زيارة إلى الخارج عن قصد.</p>
                    <p>وسيكون هذا المنتدى هو المنتدى الرئيسي لأعمال المناخ في العام ، وقد كان محظوظا بما فيه الكفاية لكي يرشح لتمثيل شركتنا والمنطقة خلال الحلقة الدراسية.</p>
                    <p>إن عضويتي التي دامت ثلاث سنوات كجزء من المجموعة والمساهمات التي قدمتها إلى الشركة ، ونتيجة لذلك ، كانت مفيدة من الناحية التكافلية. وفي هذا الصدد ، فإنني أطلب منكم بصفتي الرئيس المباشر لي أن يسمح لي بالحضور.</p>
                    <p>مزيد من التفاصيل عن الرحلة :&nbsp;</p>
                    <p>مدة الرحلة : { start_date } الى { end_date }</p>
                    <p>الغرض من الزيارة : { purpose_of_visit }</p>
                    <p>مكان الزيارة : { place_of_visit }</p>
                    <p>الوصف : { trip_description }</p>
                    <p>إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة.</p>
                    <p>شكرا لك</p>
                    <p>Regards,</p>
                    <p>إدارة الموارد البشرية ،</p>
                    <p>{ app_name }</p>',
                    'zh' => '<p><strong>主题：-人力资源部门/公司发送出差信。</strong></p>
                    <p><strong>亲爱的{employee_name}，</strong></p>
                    <p>祝你早上好！我写信给贵部门办公室，提出一个出国访问{ Purpose_of_visit} 的请求。</p>
                    <p>这将是年度领先的气候商业论坛，我们很幸运能够被提名在研讨会上代表我们公司和该地区。</p>
                    <p>我作为团队成员的三年会员身份以及我对公司所做的贡献是共生有益的。在这方面，我请求您作为我的直接上级允许我参加。</p>
                    <p>有关行程的更多详细信息：{start_date} 至 {end_date}</p>
                    <p>行程持续时间：{start_date} 至 {end_date}</p>
                    <p>访问目的：{ Purpose_of_visit}</p>
                    <p>访问地点：{place_of_visit}</p>
                    <p>描述：{t​​rip_description}</p>
                    <p>如果您有任何疑问，请随时与我们联系。</p>
                    <p>谢谢</p>
                    <p><strong>问候，</strong></p>
                    <p><strong>人力资源部</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'da' => '<p>Om: HR-afdelingen / Kompagniet, der skal sende udflugten.</p>
                    <p>K&aelig;re { employee_name },</p>
                    <p>Godmorgen til dig! Jeg skriver til dit kontor med en ydmyg anmodning om at rejse for en { purpose_of_visit } i udlandet.</p>
                    <p>Det ville v&aelig;re &aring;rets f&oslash;rende klimaforum, og det ville v&aelig;re heldigt nok at blive nomineret til at repr&aelig;sentere vores virksomhed og regionen under seminaret.</p>
                    <p>Mit tre&aring;rige medlemskab som en del af den gruppe og de bidrag, jeg har givet til virksomheden, har som f&oslash;lge heraf v&aelig;ret symbiotisk fordelagtigt. I den henseende anmoder jeg om, at De som min n&aelig;rmeste overordnede giver mig lov til at deltage.</p>
                    <p>Flere oplysninger om turen:</p>
                    <p>Trip Duration: { start_date } til { end_date }</p>
                    <p>Form&aring;let med Bes&oslash;g: { purpose_of_visit }</p>
                    <p>Plads af bes&oslash;g: { place_of_visit }</p>
                    <p>Beskrivelse: { trip_description }</p>
                    <p>Du er velkommen til at r&aelig;kke ud, hvis du har nogen sp&oslash;rgsm&aring;l.</p>
                    <p>Tak.</p>
                    <p>Med venlig hilsen</p>
                    <p>HR-afdelingen,</p>
                    <p>{ app_name }</p>',
                    'de' => '<p>Betreff: -Personalabteilung/Firma, um Reisebrief zu schicken.</p>
                    <p>Sehr geehrter {employee_name},</p>
                    <p>Top of the morning to you! Ich schreibe an Ihre Dienststelle mit dem&uuml;tiger Bitte um eine Reise nach einem {purpose_of_visit} im Ausland.</p>
                    <p>Es w&auml;re das f&uuml;hrende Klima-Business-Forum des Jahres und hatte das Gl&uuml;ck, nominiert zu werden, um unser Unternehmen und die Region w&auml;hrend des Seminars zu vertreten.</p>
                    <p>Meine dreij&auml;hrige Mitgliedschaft als Teil der Gruppe und die Beitr&auml;ge, die ich an das Unternehmen gemacht habe, sind dadurch symbiotisch vorteilhaft gewesen. In diesem Zusammenhang ersuche ich Sie als meinen unmittelbaren Vorgesetzten, mir zu gestatten, zu besuchen.</p>
                    <p>Mehr Details zu Reise:</p>
                    <p>Dauer der Fahrt: {start_date} bis {end_date}</p>
                    <p>Zweck des Besuchs: {purpose_of_visit}</p>
                    <p>Ort des Besuchs: {place_of_visit}</p>
                    <p>Beschreibung: {trip_description}</p>
                    <p>F&uuml;hlen Sie sich frei, wenn Sie Fragen haben.</p>
                    <p>Danke.</p>
                    <p>Betrachtet,</p>
                    <p>Personalabteilung,</p>
                    <p>{app_name}</p>',
                    'en' => '<p><strong>Subject:-HR department/Company to send trip letter .</strong></p>
                    <p><strong>Dear {employee_name},</strong></p>
                    <p>Top of the morning to you! I am writing to your department office with a humble request to travel for a {purpose_of_visit} abroad.</p>
                    <p>It would be the leading climate business forum of the year and have been lucky enough to be nominated to represent our company and the region during the seminar.</p>
                    <p>My three-year membership as part of the group and contributions I have made to the company, as a result, have been symbiotically beneficial. In that regard, I am requesting you as my immediate superior to permit me to attend.</p>
                    <p>More detail about trip:{start_date} to {end_date}</p>
                    <p>Trip Duration:{start_date} to {end_date}</p>
                    <p>Purpose of Visit:{purpose_of_visit}</p>
                    <p>Place of Visit:{place_of_visit}</p>
                    <p>Description:{trip_description}</p>
                    <p>Feel free to reach out if you have any questions.</p>
                    <p>Thank you</p>
                    <p><strong>Regards,</strong></p>
                    <p><strong>HR Department,</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'es' => '<p>Asunto: -Departamento de RRHH/Empresa para enviar carta de viaje.</p>
                    <p>Estimado {employee_name},</p>
                    <p>&iexcl;Top de la ma&ntilde;ana para ti! Estoy escribiendo a su oficina del departamento con una humilde petici&oacute;n de viajar para un {purpose_of_visit} en el extranjero.</p>
                    <p>Ser&iacute;a el principal foro de negocios clim&aacute;ticos del a&ntilde;o y han tenido la suerte de ser nominados para representar a nuestra compa&ntilde;&iacute;a y a la regi&oacute;n durante el seminario.</p>
                    <p>Mi membres&iacute;a de tres a&ntilde;os como parte del grupo y las contribuciones que he hecho a la compa&ntilde;&iacute;a, como resultado, han sido simb&oacute;ticamente beneficiosos. En ese sentido, le estoy solicitando como mi superior inmediato que me permita asistir.</p>
                    <p>M&aacute;s detalles sobre el viaje:&nbsp;</p>
                    <p>Duraci&oacute;n del viaje: {start_date} a {end_date}</p>
                    <p>Finalidad de la visita: {purpose_of_visit}</p>
                    <p>Lugar de visita: {place_of_visit}</p>
                    <p>Descripci&oacute;n: {trip_description}</p>
                    <p>Si&eacute;ntase libre de llegar si usted tiene alguna pregunta.</p>
                    <p>&iexcl;Gracias!</p>
                    <p>Considerando,</p>
                    <p>Departamento de Recursos Humanos,</p>
                    <p>{app_name}</p>',
                    'fr' => '<p>Objet: -Service des RH / Compagnie pour envoyer une lettre de voyage.</p>
                    <p>Cher { employee_name },</p>
                    <p>Top of the morning to you ! J&eacute;crai au bureau de votre minist&egrave;re avec une humble demande de voyage pour une {purpose_of_visit } &agrave; l&eacute;tranger.</p>
                    <p>Il sagit du principal forum sur le climat de lann&eacute;e et a eu la chance d&ecirc;tre d&eacute;sign&eacute; pour repr&eacute;senter notre entreprise et la r&eacute;gion au cours du s&eacute;minaire.</p>
                    <p>Mon adh&eacute;sion de trois ans au groupe et les contributions que jai faites &agrave; lentreprise, en cons&eacute;quence, ont &eacute;t&eacute; b&eacute;n&eacute;fiques sur le plan symbiotique. &Agrave; cet &eacute;gard, je vous demande d&ecirc;tre mon sup&eacute;rieur imm&eacute;diat pour me permettre dy assister.</p>
                    <p>Plus de d&eacute;tails sur le voyage:</p>
                    <p>Dur&eacute;e du voyage: { start_date } &agrave; { end_date }</p>
                    <p>Objet de la visite: { purpose_of_visit}</p>
                    <p>Lieu de visite: { place_of_visit }</p>
                    <p>Description: { trip_description }</p>
                    <p>Nh&eacute;sitez pas &agrave; nous contacter si vous avez des questions.</p>
                    <p>Je vous remercie</p>
                    <p>Regards,</p>
                    <p>D&eacute;partement des RH,</p>
                    <p>{ app_name }</p>',
                    'he' => '<p><strong>נושא:-מחלקת משאבי אנוש/חברה לשלוח מכתב טיול .</strong></p>
                    <p><strong>{employee_name} היקר,</strong></p>
                    <p>ראש הבוקר לך! אני כותב למשרד המחלקה שלך עם בקשה צנועה לנסוע ל{purpose_of_visit} לחו"ל.</p>
                    <p>זה יהיה פורום עסקי האקלים המוביל של השנה והיה לנו המזל להיות מועמד לייצג את החברה שלנו ואת האזור במהלך הסמינר.</p>
                    <p>החברות שלי בת שלוש שנים כחלק מהקבוצה והתרומות שתרמתי לחברה, כתוצאה מכך, היו מועילות באופן סימביוטי. בהקשר זה, אני מבקש ממך כמפקד הישיר שלי להתיר לי להשתתף.</p>
                    <p>פרטים נוספים על נסיעה:{start_date} עד {end_date}</p>
                    <p>משך הנסיעה:{start_date} עד {end_date}</p>
                    <p>מטרת הביקור:{purpose_of_visit}</p>
                    <p>מקום ביקור:{place_of_visit}</p>
                    <p>תיאור:{trip_description}</p>
                    <p>אל תהסס לפנות אם יש לך שאלות.</p>
                    <p>תודה</p>
                    <p><strong>בברכה,</strong></p>
                    <p><strong>מחלקת משאבי אנוש,</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'it' => '<p>Oggetto: - Dipartimento HR / Societ&agrave; per inviare lettera di viaggio.</p>
                    <p>Caro {employee_name},</p>
                    <p>In cima al mattino a te! Scrivo al tuo ufficio dipartimento con umile richiesta di viaggio per un {purpose_of_visit} allestero.</p>
                    <p>Sarebbe il forum aziendale sul clima leader dellanno e sono stati abbastanza fortunati da essere nominati per rappresentare la nostra azienda e la regione durante il seminario.</p>
                    <p>La mia adesione triennale come parte del gruppo e i contributi che ho apportato allazienda, di conseguenza, sono stati simbioticamente vantaggiosi. A tal proposito, vi chiedo come mio immediato superiore per consentirmi di partecipare.</p>
                    <p>Pi&ugrave; dettagli sul viaggio:</p>
                    <p>Trip Duration: {start_date} a {end_date}</p>
                    <p>Finalit&agrave; di Visita: {purpose_of_visit}</p>
                    <p>Luogo di Visita: {place_of_visit}</p>
                    <p>Descrizione: {trip_description}</p>
                    <p>Sentiti libero di raggiungere se hai domande.</p>
                    <p>Grazie</p>
                    <p>Riguardo,</p>
                    <p>Dipartimento HR,</p>
                    <p>{app_name}</p>',
                    'ja' => '<p>件名:-HR 部門/会社は出張レターを送信します。</p>
                    <p>{ employee_name} に出庫します。</p>
                    <p>朝のトップだ ! 海外で {purpose_of_visit} をお願いしたいという謙虚な要求をもって、私はあなたの部署に手紙を書いています。</p>
                    <p>これは、今年の主要な気候ビジネス・フォーラムとなり、セミナーの開催中に当社と地域を代表する候補になるほど幸運にも恵まれています。</p>
                    <p>私が会社に対して行った 3 年間のメンバーシップは、その結果として、共生的に有益なものでした。 その点では、私は、私が出席することを許可することを、私の即座の上司として</p>
                    <p>トリップについての詳細 :</p>
                    <p>トリップ期間:{start_date} を {end_date} に設定します</p>
                    <p>アクセスの目的 :{purpose_of_visit}</p>
                    <p>訪問の場所 :{place_of_visit}</p>
                    <p>説明:{trip_description}</p>
                    <p>質問がある場合は、自由に連絡してください。</p>
                    <p>ありがとう</p>
                    <p>よろしく</p>
                    <p>HR 部門</p>
                    <p>{app_name}</p>',
                    'nl' => '<p>Betreft: -HR-afdeling/Bedrijf om reisbrief te sturen.</p>
                    <p>Geachte { employee_name },</p>
                    <p>Top van de ochtend aan u! Ik schrijf uw afdelingsbureau met een bescheiden verzoek om een { purpose_of_visit } in het buitenland te bezoeken.</p>
                    <p>Het zou het toonaangevende klimaatforum van het jaar zijn en hebben het geluk gehad om genomineerd te worden om ons bedrijf en de regio te vertegenwoordigen tijdens het seminar.</p>
                    <p>Mijn driejarige lidmaatschap als onderdeel van de groep en bijdragen die ik heb geleverd aan het bedrijf, als gevolg daarvan, zijn symbiotisch gunstig geweest. Wat dat betreft, verzoek ik u als mijn directe chef mij in staat te stellen aanwezig te zijn.</p>
                    <p>Meer details over reis:</p>
                    <p>Duur van reis: { start_date } tot { end_date }</p>
                    <p>Doel van bezoek: { purpose_of_visit }</p>
                    <p>Plaats van bezoek: { place_of_visit }</p>
                    <p>Beschrijving: { trip_description }</p>
                    <p>Voel je vrij om uit te reiken als je vragen hebt.</p>
                    <p>Dank u we</p>
                    <p>Betreft:</p>
                    <p>HR-afdeling,</p>
                    <p>{ app_name }</p>',
                    'pl' => '<p>Temat:-Dział HR/Firma do wysyłania listu podr&oacute;ży.</p>
                    <p>Szanowny {employee_name },</p>
                    <p>Od samego rana do Ciebie! Piszę do twojego biura, z pokornym prośbą o wyjazd na {purpose_of_visit&nbsp;} za granicą.</p>
                    <p>Byłoby to wiodącym forum biznesowym w tym roku i miało szczęście być nominowane do reprezentowania naszej firmy i regionu podczas seminarium.</p>
                    <p>Moje trzyletnie członkostwo w grupie i składkach, kt&oacute;re uczyniłem w firmie, w rezultacie, były symbiotycznie korzystne. W tym względzie, zwracam się do pana o m&oacute;j bezpośredni przełożony, kt&oacute;ry pozwoli mi na udział w tej sprawie.</p>
                    <p>Więcej szczeg&oacute;ł&oacute;w na temat wyjazdu:</p>
                    <p>Czas trwania rejsu: {start_date } do {end_date }</p>
                    <p>Cel wizyty: {purpose_of_visit }</p>
                    <p>Miejsce wizyty: {place_of_visit }</p>
                    <p>Opis: {trip_description }</p>
                    <p>Czuj się swobodnie, jeśli masz jakieś pytania.</p>
                    <p>Dziękujemy</p>
                    <p>W odniesieniu do</p>
                    <p>Dział HR,</p>
                    <p>{app_name }</p>',
                    'ru' => '<p>Тема: -HR отдел/Компания для отправки письма на поездку.</p>
                    <p>Уважаемый { employee_name },</p>
                    <p>С утра до тебя! Я пишу в ваш отдел с смиренным запросом на поездку за границу.</p>
                    <p>Это был бы ведущий климатический бизнес-форум года и по везло, что в ходе семинара он будет представлять нашу компанию и регион.</p>
                    <p>Мое трехлетнее членство в составе группы и взносы, которые я внес в компанию, в результате, были симбиотически выгодны. В этой связи я прошу вас как моего непосредственного начальника разрешить мне присутствовать.</p>
                    <p>Подробнее о поездке:</p>
                    <p>Длительность поездки: { start_date } в { end_date }</p>
                    <p>Цель посещения: { purpose_of_visit }</p>
                    <p>Место посещения: { place_of_visit }</p>
                    <p>Описание: { trip_description }</p>
                    <p>Не стеснитесь, если у вас есть вопросы.</p>
                    <p>Спасибо.</p>
                    <p>С уважением,</p>
                    <p>Отдел кадров,</p>
                    <p>{ app_name }</p>',
                    'pt' => '<p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de viagem.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Querido {employee_name},</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Topo da manh&atilde; para voc&ecirc;! Estou escrevendo para o seu departamento de departamento com um humilde pedido para viajar por um {purpose_of_visit} no exterior.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Seria o principal f&oacute;rum de neg&oacute;cios clim&aacute;tico do ano e teve a sorte de ser indicado para representar nossa empresa e a regi&atilde;o durante o semin&aacute;rio.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">A minha filia&ccedil;&atilde;o de tr&ecirc;s anos como parte do grupo e contribui&ccedil;&otilde;es que fiz &agrave; empresa, como resultado, foram simbioticamente ben&eacute;fico. A esse respeito, solicito que voc&ecirc; seja meu superior imediato para me permitir comparecer.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Mais detalhes sobre viagem:</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Trip Dura&ccedil;&atilde;o: {start_date} a {end_date}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Objetivo da Visita: {purpose_of_visit}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Local de Visita: {place_of_visit}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Descri&ccedil;&atilde;o: {trip_description}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Obrigado</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Considera,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Departamento de RH,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{app_name}</span></p>',
                    'tr' => '<p><strong>Konu:-İK departmanı/Şirket gezi mektubu gönderecek .</strong></p>
                    <p><strong>Sayın {employee_name},</strong></p>
                    <p>Günaydın! Yurtdışında bir ziyaret için mütevazı bir seyahat talebiyle departman ofisinize yazıyorum.</p>
                    <p>Yılın önde gelen iklim iş forumu olacaktı ve seminer sırasında şirketimizi ve bölgeyi temsil etmek üzere aday gösterilecek kadar şanslıydık.</p>
                    <p>Grubun bir parçası olarak üç yıllık üyeliğim ve bunun sonucunda şirkete yaptığım katkılar ortakyaşam açısından faydalı oldu. Bu bağlamda, acil amirim olarak katılmama izin vermenizi rica ediyorum.</p>
                    <p>Seyahat hakkında daha fazla ayrıntı:{start_date} - {end_date}</p>
                    <p>Seyahat Süresi:{start_date} - {end_date}</p>
                    <p>Ziyaretin Amacı:{contact_of_visit}</p>
                    <p>Ziyaret Yeri:{place_of_visit}</p>
                    <p>Açıklama:{trip_description}</p>
                    <p>Sorularınız varsa bize ulaşmaktan çekinmeyin.</p>
                    <p>Teşekkürler</p>
                    <p><strong>Saygılarımızla,</strong></p>
                    <p><strong>İK Departmanı</strong></p>
                    <p><strong>{uygulama_adı</strong></p>',
                    'pt' => '<p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de viagem.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Querido {employee_name},</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Topo da manh&atilde; para voc&ecirc;! Estou escrevendo para o seu departamento de departamento com um humilde pedido para viajar por um {purpose_of_visit} no exterior.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Seria o principal f&oacute;rum de neg&oacute;cios clim&aacute;tico do ano e teve a sorte de ser indicado para representar nossa empresa e a regi&atilde;o durante o semin&aacute;rio.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">A minha filia&ccedil;&atilde;o de tr&ecirc;s anos como parte do grupo e contribui&ccedil;&otilde;es que fiz &agrave; empresa, como resultado, foram simbioticamente ben&eacute;fico. A esse respeito, solicito que voc&ecirc; seja meu superior imediato para me permitir comparecer.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Mais detalhes sobre viagem:</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Trip Dura&ccedil;&atilde;o: {start_date} a {end_date}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Objetivo da Visita: {purpose_of_visit}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Local de Visita: {place_of_visit}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Descri&ccedil;&atilde;o: {trip_description}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Obrigado</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Considera,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Departamento de RH,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{app_name}</span></p>',
                ],
            ],
            'vender_bill_sent' => [
                'subject' => 'Vendor Bill Sent',
                'lang' => [
                    'ar' => '<p>مرحبا ، { bill_name }</p>
                    <p>مرحبا بك في { app_name }</p>
                    <p>أتمنى أن يجدك هذا البريد الإلكتروني جيدا ! ! برجاء الرجوع الى رقم الفاتورة الملحقة { bill_number } للحصول على المنتج / الخدمة.</p>
                    <p>ببساطة اضغط على الاختيار بأسفل.</p>
                    <p>{ bill_url }</p>
                    <p>إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة.</p>
                    <p>شكرا لك</p>
                    <p>&nbsp;</p>
                    <p>Regards,</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'zh' => '<p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">嗨，{bill_name}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">欢迎使用 {app_name}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">希望这封电子邮件能让您满意！！请参阅随附的产品/服务帐单编号 {bill_number}。</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">只需点击下面的按钮即可。</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">{bill_url}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">如果您有任何疑问，请随时与我们联系。</span> </p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">谢谢，</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">问候，</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">{company_name}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">{app_url}</span></p>',
                    'da' => '<p>Hej, { bill_name }</p>
                    <p>Velkommen til { app_name }</p>
                    <p>H&aring;ber denne e-mail finder dig godt! Se vedlagte fakturanummer } { bill_number } for product/service.</p>
                    <p>Klik p&aring; knappen nedenfor.</p>
                    <p>{ bill_url }</p>
                    <p>Du er velkommen til at r&aelig;kke ud, hvis du har nogen sp&oslash;rgsm&aring;l.</p>
                    <p>Tak.</p>
                    <p>&nbsp;</p>
                    <p>Med venlig hilsen</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'de' => '<p>Hi, {bill_name}</p>
                    <p>Willkommen bei {app_name}</p>
                    <p>Hoffe, diese E-Mail findet dich gut!! Sehen Sie sich die beigef&uuml;gte Rechnungsnummer {bill_number} f&uuml;r Produkt/Service an.</p>
                    <p>Klicken Sie einfach auf den Button unten.</p>
                    <p>{bill_url}</p>
                    <p>F&uuml;hlen Sie sich frei, wenn Sie Fragen haben.</p>
                    <p>Vielen Dank,</p>
                    <p>&nbsp;</p>
                    <p>Betrachtet,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'en' => '<p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">Hi, {bill_name}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">Welcome to {app_name}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">Hope this email finds you well!! Please see attached bill number {bill_number} for product/service.</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">Simply click on the button below.</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">{bill_url}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">Feel free to reach out if you have any questions.</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">Thank You,</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">Regards,</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">{company_name}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">{app_url}</span></p>',
                    'es' => '<p>Hi, {bill_name}</p>
                    <p>Bienvenido a {app_name}</p>
                    <p>&iexcl;Espero que este correo te encuentre bien!! Consulte el n&uacute;mero de factura adjunto {bill_number} para el producto/servicio.</p>
                    <p>Simplemente haga clic en el bot&oacute;n de abajo.</p>
                    <p>{bill_url}</p>
                    <p>Si&eacute;ntase libre de llegar si usted tiene alguna pregunta.</p>
                    <p>Gracias,</p>
                    <p>&nbsp;</p>
                    <p>Considerando,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'fr' => '<p>Salut, { bill_name }</p>
                    <p>Bienvenue dans { app_name }</p>
                    <p>Jesp&egrave;re que ce courriel vous trouve bien ! ! Veuillez consulter le num&eacute;ro de facture { bill_number } associ&eacute; au produit / service.</p>
                    <p>Cliquez simplement sur le bouton ci-dessous.</p>
                    <p>{bill_url }</p>
                    <p>Nh&eacute;sitez pas &agrave; nous contacter si vous avez des questions.</p>
                    <p>Merci,</p>
                    <p>&nbsp;</p>
                    <p>Regards,</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'he' => '<p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">היי, {bill_name}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">ברוכים הבאים אל {app_name}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">מקווה שהמייל הזה ימצא אותך טוב!! ראה את מספר החשבון המצורף {bill_number} עבור מוצר/שירות.</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">פשוט לחץ על הכפתור למטה.</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">{bill_url}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">אל תהסס לפנות אם יש לך שאלות כלשהן.</span> </p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">תודה,</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">בברכה,</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">{company_name}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,;"><span style="font-family: sans-serif;">{app_url}</span></p>',
                    'it' => '<p>Ciao, {bill_name}</p>
                    <p>Benvenuti in {app_name}</p>
                    <p>Spero che questa email ti trovi bene!! Si prega di consultare il numero di fattura allegato {bill_number} per il prodotto/servizio.</p>
                    <p>Semplicemente clicca sul pulsante sottostante.</p>
                    <p>{bill_url}</p>
                    <p>Sentiti libero di raggiungere se hai domande.</p>
                    <p>Grazie,</p>
                    <p>&nbsp;</p>
                    <p>Riguardo,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'ja' => '<p>こんにちは、 {bill_name}</p>
                    <p>{app_name} へようこそ</p>
                    <p>この E メールによりよく検出されます !! 製品 / サービスの添付された請求番号 {bill_number} を参照してください。</p>
                    <p>以下のボタンをクリックしてください。</p>
                    <p>{bill_url}</p>
                    <p>質問がある場合は、自由に連絡してください。</p>
                    <p>ありがとうございます</p>
                    <p>&nbsp;</p>
                    <p>よろしく</p>
                    <p>{ company_name}</p>
                    <p>{app_url}</p>',
                    'nl' => '<p>Hallo, { bill_name }</p>
                    <p>Welkom bij { app_name }</p>
                    <p>Hoop dat deze e-mail je goed vindt!! Zie bijgevoegde factuurnummer { bill_number } voor product/service.</p>
                    <p>Klik gewoon op de knop hieronder.</p>
                    <p>{ bill_url }</p>
                    <p>Voel je vrij om uit te reiken als je vragen hebt.</p>
                    <p>Dank U,</p>
                    <p>&nbsp;</p>
                    <p>Betreft:</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'pl' => '<p>Witaj, {bill_name }</p>
                    <p>Witamy w aplikacji {app_name }</p>
                    <p>Mam nadzieję, że ta wiadomość e-mail znajduje Cię dobrze!! Zapoznaj się z załączonym numerem rachunku {bill_number } dla produktu/usługi.</p>
                    <p>Wystarczy kliknąć na przycisk poniżej.</p>
                    <p>{bill_url}</p>
                    <p>Czuj się swobodnie, jeśli masz jakieś pytania.</p>
                    <p>Dziękuję,</p>
                    <p>&nbsp;</p>
                    <p>W odniesieniu do</p>
                    <p>{company_name }</p>
                    <p>{app_url }</p>',
                    'ru' => '<p>Привет, { bill_name }</p>
                    <p>Вас приветствует { app_name }</p>
                    <p>Надеюсь, это письмо найдет вас хорошо! См. прилагаемый номер счета { bill_number } для product/service.</p>
                    <p>Просто нажмите на кнопку внизу.</p>
                    <p>{ bill_url }</p>
                    <p>Не стеснитесь, если у вас есть вопросы.</p>
                    <p>Спасибо.</p>
                    <p>&nbsp;</p>
                    <p>С уважением,</p>
                    <p>{ company_name }</p>
                    <p>{ app_url }</p>',
                    'pt' => '<p>Oi, {bill_name}</p>
                    <p>Bem-vindo a {app_name}</p>
                    <p>Espero que este e-mail encontre voc&ecirc; bem!! Por favor, consulte o n&uacute;mero de faturamento conectado {bill_number} para produto/servi&ccedil;o.</p>
                    <p>Basta clicar no bot&atilde;o abaixo.</p>
                    <p>{bill_url}</p>
                    <p>Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</p>
                    <p>Obrigado,</p>
                    <p>&nbsp;</p>
                    <p>Considera,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                    'tr' => '<p style="line-height: 28px; font-family: Nunito,"><span style="font-family: sans-serif;">Merhaba {bill_name}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,"><span style="font-family: sans-serif;">{app_name}</span></p> e hoş geldiniz>
                    <p style="line-height: 28px; font-family: Nunito,"><span style="font-family: sans-serif;">Umarım bu e-posta sizi iyi bulur!! Lütfen ürün/hizmet için ekteki {bill_number} numaralı faturaya bakın.</span></p>
                    <p style="line-height: 28px; font-family: Nunito,"><span style="font-family: sans-serif;">Aşağıdaki düğmeyi tıklamanız yeterli.</span></p>
                    <p style="line-height: 28px; font-family: Nunito,"><span style="font-family: sans-serif;">{bill_url}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,"><span style="font-family: sans-serif;">Sorularınız varsa bize ulaşmaktan çekinmeyin.</span> </p>
                    <p style="line-height: 28px; font-family: Nunito,"><span style="font-family: sans-serif;">Teşekkürler,</span></p>
                    <p style="line-height: 28px; font-family: Nunito,"><span style="font-family: sans-serif;">Saygılarımızla,</span></p>
                    <p style="line-height: 28px; font-family: Nunito,"><span style="font-family: sans-serif;">{company_name}</span></p>
                    <p style="line-height: 28px; font-family: Nunito,"><span style="font-family: sans-serif;">{app_url}</span></p>',
                    'pt' => '<p>Oi, {bill_name}</p>
                    <p>Bem-vindo a {app_name}</p>
                    <p>Espero que este e-mail encontre voc&ecirc; bem!! Por favor, consulte o n&uacute;mero de faturamento conectado {bill_number} para produto/servi&ccedil;o.</p>
                    <p>Basta clicar no bot&atilde;o abaixo.</p>
                    <p>{bill_url}</p>
                    <p>Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</p>
                    <p>Obrigado,</p>
                    <p>&nbsp;</p>
                    <p>Considera,</p>
                    <p>{company_name}</p>
                    <p>{app_url}</p>',
                ],
            ],
            'warning_sent' => [
                'subject' => 'Warning Sent',
                'lang' => [
                    'ar' => '<p style="text-align: left;"><span style="font-size: 12pt;"><span style="color: #222222;"><span style="white-space: pre-wrap;">Subject : -HR ادارة / شركة لارسال رسالة تحذير. عزيزي { employe_warning_name }, { warning_subject } { warning_description } إشعر بالحرية للوصول إلى الخارج إذا عندك أي أسئلة. شكرا لك Regards, إدارة الموارد البشرية ، { app_name }</span></span></span></p>',
                    'zh' => '<p><strong>主题：-人力资源部门/公司发送警告信。</strong></p>
                    <p><strong>亲爱的{employee_warning_name}，</strong></p>
                    <p>{warning_subject}</p>
                    <p>{warning_description}</p>
                    <p>如果您有任何疑问，请随时与我们联系。</p>
                    <p>谢谢</p>
                    <p><strong>问候，</strong></p>
                    <p><strong>人力资源部</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'da' => '<p>Om: HR-afdelingen / kompagniet for at sende advarselsbrev.</p>
                    <p>K&aelig;re { employee_warning_name },</p>
                    <p>{ warning_subject }</p>
                    <p>{ warning_description }</p>
                    <p>Du er velkommen til at r&aelig;kke ud, hvis du har nogen sp&oslash;rgsm&aring;l.</p>
                    <p>Tak.</p>
                    <p>Med venlig hilsen</p>
                    <p>HR-afdelingen,</p>
                    <p>{ app_name }</p>',
                    'de' => '<p>Betreff: -Personalabteilung/Unternehmen zum Senden von Warnschreiben.</p>
                    <p>Sehr geehrter {employee_warning_name},</p>
                    <p>{warning_subject}</p>
                    <p>{warning_description}</p>
                    <p>F&uuml;hlen Sie sich frei, wenn Sie Fragen haben.</p>
                    <p>Danke.</p>
                    <p>Betrachtet,</p>
                    <p>Personalabteilung,</p>
                    <p>{app_name}</p>',
                    'en' => '<p><strong>Subject:-HR department/Company to send warning letter.</strong></p>
                    <p><strong>Dear {employee_warning_name},</strong></p>
                    <p>{warning_subject}</p>
                    <p>{warning_description}</p>
                    <p>Feel free to reach out if you have any questions.</p>
                    <p>Thank you</p>
                    <p><strong>Regards,</strong></p>
                    <p><strong>HR Department,</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'es' => '<p>Asunto: -Departamento de RR.HH./Empresa para enviar carta de advertencia.</p>
                    <p>Estimado {employee_warning_name},</p>
                    <p>{warning_subject}</p>
                    <p>{warning_description}</p>
                    <p>Si&eacute;ntase libre de llegar si usted tiene alguna pregunta.</p>
                    <p>&iexcl;Gracias!</p>
                    <p>Considerando,</p>
                    <p>Departamento de Recursos Humanos,</p>
                    <p>{app_name}</p>',
                    'fr' => '<p>Objet: -HR department / Company to send warning letter.</p>
                    <p>Cher { employee_warning_name },</p>
                    <p>{ warning_subject }</p>
                    <p>{ warning_description }</p>
                    <p>Nh&eacute;sitez pas &agrave; nous contacter si vous avez des questions.</p>
                    <p>Je vous remercie</p>
                    <p>Regards,</p>
                    <p>D&eacute;partement des RH,</p>
                    <p>{ app_name }</p>',
                    'he' => '<p><strong>נושא:-מחלקת משאבי אנוש/חברה לשלוח מכתב אזהרה.</strong></p>
                    <p><strong>{employee_warning_name} היקר,</strong></p>
                    <p>{warning_subject}</p>
                    <p>{warning_description}</p>
                    <p>אל תהסס לפנות אם יש לך שאלות.</p>
                    <p>תודה</p>
                    <p><strong>בברכה,</strong></p>
                    <p><strong>מחלקת משאבי אנוש,</strong></p>
                    <p><strong>{app_name}</strong></p>',
                    'it' => '<p>Oggetto: - Dipartimento HR / Societ&agrave; per inviare lettera di avvertimento.</p>
                    <p>Caro {employee_warning_name},</p>
                    <p>{warning_subject}</p>
                    <p>{warning_description}</p>
                    <p>Sentiti libero di raggiungere se hai domande.</p>
                    <p>Grazie</p>
                    <p>Riguardo,</p>
                    <p>Dipartimento HR,</p>
                    <p>{app_name}</p>',
                    'ja' => '<p><span style="font-size: 12pt;"><span style="color: #222222;"><span style="white-space: pre-wrap;">件名:-HR 部門/企業は警告レターを送信します。 { employee_warning_name} を出庫します。 {warning_subject} {warning_description} 質問がある場合は、自由に連絡してください。 ありがとう よろしく HR 部門 {app_name}</span></span></span></p>',
                    'nl' => '<p>Betreft: -HR-afdeling/bedrijf om een waarschuwingsbrief te sturen.</p>
                    <p>Geachte { employee_warning_name },</p>
                    <p>{ warning_subject }</p>
                    <p>{ warning_description }</p>
                    <p>Voel je vrij om uit te reiken als je vragen hebt.</p>
                    <p>Dank u wel</p>
                    <p>Betreft:</p>
                    <p>HR-afdeling,</p>
                    <p>{ app_name }</p>',
                    'pl' => '<p>Temat: -Dział HR/Firma do wysyłania listu ostrzegawczego.</p>
                    <p>Szanowny {employee_warning_name },</p>
                    <p>{warning_subject }</p>
                    <p>{warning_description }</p>
                    <p>Czuj się swobodnie, jeśli masz jakieś pytania.</p>
                    <p>Dziękujemy</p>
                    <p>W odniesieniu do</p>
                    <p>Dział HR,</p>
                    <p>{app_name }</p>',
                    'ru' => '<p>Тема: -HR отдел/Компания для отправки предупреждающего письма.</p>
                    <p>Уважаемый { employee_warning_name },</p>
                    <p>{ warning_subject }</p>
                    <p>{ warning_description }</p>
                    <p>Не стеснитесь, если у вас есть вопросы.</p>
                    <p>Спасибо.</p>
                    <p>С уважением,</p>
                    <p>Отдел кадров,</p>
                    <p>{ app_name }</p>',
                    'pt' => '<p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de advert&ecirc;ncia.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Querido {employee_warning_name},</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{warning_subject}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{warning_description}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Obrigado</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Considera,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Departamento de RH,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{app_name}</span></p>',
                    'tr' => '<p><strong>Konu:-İK departmanı/Şirket uyarı mektubu gönderecek.</strong></p>
                    <p><strong>Sayın {employee_warning_name},</strong></p>
                    <p>{warning_subject}</p>
                    <p>{warning_description}</p>
                    <p>Sorularınız varsa bize ulaşmaktan çekinmeyin.</p>
                    <p>Teşekkürler</p>
                    <p><strong>Saygılarımızla,</strong></p>
                    <p><strong>İK Departmanı</strong></p>
                    <p><strong>{uygulama_adı</strong></p>',
                    'pt-br' => '<p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Assunto:-Departamento de RH / Empresa para enviar carta de advert&ecirc;ncia.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Querido {employee_warning_name},</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{warning_subject}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{warning_description}</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Sinta-se &agrave; vontade para alcan&ccedil;ar fora se voc&ecirc; tiver alguma d&uacute;vida.</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Obrigado</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Considera,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">Departamento de RH,</span></p>
                    <p style="font-size: 14.4px;"><span style="font-size: 14.4px;">{app_name}</span></p>',
                ],
            ],
            'new_contract' => [
                'subject' => 'New Contract',
                'lang' => [
                    'ar' => '<p>&nbsp;</p>
                    <p><b>مرحبا</b> { contract_client }</p>
                    <p><b>موضوع العقد</b> : { contract_subject }</p>
                    <p><b>مشروع العقد </b>: { contract_project }</p>
                    <p><b>تاريخ البدء</b> : { contract_start_date }</p>
                    <p><b>تاريخ الانتهاء</b> : { contract_end_date }</p>
                    <p>. أتطلع لسماع منك</p>
                    <p><b>Regards نوع ،</b></p>
                    <p>{ company_name }</p>',
                    'zh' => '<p> </p>
                    <p><strong>嗨</strong> {contract_client}</p>
                    <p><b>合同主题</b>：{contract_subject}</p>
                    <p><b>合同项目</b>：{contract_project}</p>
                    <p><b>开始日期</b>：{contract_start_date}</p>
                    <p><b>结束日期</b>：{contract_end_date}</p>
                    <p>期待您的来信。</p>
                    <p><strong>亲切的问候，</strong></p>
                    <p>{公司名称}</p>',
                    'da' => '<p>&nbsp;</p>
                    <p><b>Hej </b>{ contract_client }</p>
                    <p><b>Kontraktemne :&nbsp;</b>{ contract_subject }</p>
                    <p><b>Kontrakt-projekt :&nbsp;</b>{ contract_project }</p>
                    <p><b>Startdato&nbsp;</b>: { contract_start_date }</p>
                    <p><b>Slutdato&nbsp;</b>: { contract_end_date }</p>
                    <p>Jeg glæder mig til at høre fra dig.</p>
                    <p><b>Kind Hilds,</b></p>
                    <p>{ company_name }</p><p></p>',
                    'de' => '<p>&nbsp;</p>
                    <p><b>Hi</b> {contract_client}</p>
                    <p>&nbsp;<b style="font-family: var(--bs-body-font-family); text-align: var(--bs-body-text-align);">Vertragsgegenstand :</b><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);"> {contract_subject}</span></p>
                    <p><b>Vertragsprojekt :&nbsp;</b>{contract_project}</p>
                    <p><b>Startdatum&nbsp;</b>: {contract_start_date}</p>
                    <p><b>Enddatum&nbsp;</b>: {contract_end_date}</p>
                    <p>Freuen Sie sich auf das Hören von Ihnen.</p>
                    <p><b>Gütige Grüße,</b></p>
                    <p>{company_name}</p>',
                    'en' => '<p>&nbsp;</p>
                    <p><strong>Hi</strong> {contract_client}</p>
                    <p><b>Contract Subject</b>&nbsp;: {contract_subject}</p>
                    <p><b>Contract Project</b>&nbsp;: {contract_project}</p>
                    <p><b>Start Date&nbsp;</b>: {contract_start_date}</p>
                    <p><b>End Date&nbsp;</b>: {contract_end_date}</p>
                    <p>Looking forward to hear from you.</p>
                    <p><strong>Kind Regards, </strong></p>
                    <p>{company_name}</p>',
                    'es' => '<p><b>Hi </b>{contract_client} </p><p><span style="text-align: var(--bs-body-text-align);"><b>asunto del contrato</b></span><b>&nbsp;:</b> {contract_subject}</p><p><b>contrato proyecto </b>: {<span style="font-family: var(--bs-body-font-family); font-size: var(--bs-body-font-size); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">contract_project</span><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">}</span></p><p> </p><p><b>Start Date :</b> {contract_start_date} </p><p><b>Fecha de finalización :</b> {contract_end_date} </p><p>Con ganas de escuchar de usted. </p><p><b>Regards de tipo, </b></p><p>{contract_name}</p>',
                    'fr' => '<p><b>Bonjour</b> { contract_client }</p>
                    <p><b>Objet du contrat :</b> { contract_subject } </p><p><span style="text-align: var(--bs-body-text-align);"><b>contrat projet :</b></span>&nbsp;{ contract_project } </p><p><b>Date de début&nbsp;</b>: { contract_start_date } </p><p><b>Date de fin&nbsp;</b>: { contract_end_date } </p><p>Regard sur lavenir.</p>
                    <p><b>Sincères amitiés,</b></p>
                    <p>{ nom_entreprise }</p>',
                    'he' => '<p> </p>
                    <p><strong>היי</strong> {contract_client}</p>
                    <p><b>נושא החוזה</b> : {contract_subject}</p>
                    <p><b>פרויקט חוזה</b> : {contract_project}</p>
                    <p><b>תאריך התחלה </b>: {contract_start_date}</p>
                    <p><b>תאריך סיום </b>: {contract_end_date}</p>
                    <p>מצפה לשמוע ממך.</p>
                    <p><strong>בברכה, </strong></p>
                    <p>{company_name}</p>',
                    'it' => '<p>&nbsp;</p>
                    <p>Ciao {contract_client}</p>
                    <p><b>Oggetto contratto :&nbsp;</b>{contract_subject} </p><p><b>Contract Project :</b> {contract_project} </p><p><b>Data di inizio</b>: {contract_start_date} </p><p><b>Data di fine</b>: {contract_end_date} </p><p>Non vedo lora di sentirti<br></p>
                    <p><b>Kind Regards,</b></p>
                    <p>{company_name}</p>',
                    'ja' => '<p><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">こんにちは {contract_client}</span><br></p>
                    <p><b>契約件名&nbsp;</b>: {contract subject}</p>
                    <p><b>契約プロジェクト :</b> {contract_project}</p>
                    <p><b>開始日</b>: {contract_start_date}</p>
                    <p>&nbsp;<b style="font-family: var(--bs-body-font-family); text-align: var(--bs-body-text-align);">終了日</b><span style="font-family: var(--bs-body-font-family); font-weight: var(--bs-body-font-weight); text-align: var(--bs-body-text-align);">: {contract_end_date}</span></p><p><span style="text-align: var(--bs-body-text-align);">あなたから聞いて楽しみにして</span></p><p><span style="text-align: var(--bs-body-text-align);"><b>敬具、</b><br></span></p>
                    <p>{ company_name}</p>',
                    'nl' => '<p>&nbsp;</p>
                    <p><b>Hallo</b> { contract_client }</p>
                    <p><b>Contractonderwerp</b> : { contract_subject } </p><p><b>Contractproject</b> : { contract_project } </p><p><b>Begindatum</b> : { contract_start_date } </p><p><b>Einddatum&nbsp;</b>: { contract_end_date } </p><p>Naar voren komen om van u te horen.</p><p><b>Met vriendelijke groeten</b>,<br></p>
                    <p>{ bedrijfsnaam }</p>',
                    'pl' => '<p>&nbsp;</p>
                    <p><b>Witaj</b> {contract_client }</p>
                    <p><b>Temat umowy :&nbsp;</b>{contract_subject } </p><p><b>Projekt kontraktu</b>&nbsp;: {contract_project } </p><p><b>Data rozpoczęcia&nbsp;</b>: {contract_start_date } </p><p><b>Data zakończenia&nbsp;</b>: {contract_end_date } </p><p>Z niecierżną datą i z niecierżką na Ciebie.</p>
                    <p><b>W Odniesieniu Do Rodzaju,</b></p>
                    <p>{company_name }</p>',
                    'ru' => '<p></p>
                    <p><b>Здравствуйте</b> { contract_client }</p>
                    <p><b>Субъект договора :</b> { contract_subject } </p><p><b>Проект договора</b>: { contract_project } </p><p><b>Начальная дата </b>: { contract_start_date } </p><p><b>Конечная дата </b>: { contract_end_date } </p><p>нетерпением ожидаю услышать от вас.</p>
                    <p><b>Привет.</b></p>
                    <p>{ company_name }</p>',
                    'pt' => '<p>&nbsp;</p>
                    <p><span style="text-align: var(--bs-body-text-align);"><b>Olá</b></span>&nbsp;{contract_client}</p>
                    <p><span style="text-align: var(--bs-body-text-align);"><b>Assunto do Contrato</b></span>&nbsp;: {contract_subject}</p>
                    <p><span style="text-align: var(--bs-body-text-align);"><b>Projeto de contrato&nbsp;</b></span>: {contract_project}</p>
                    <p><span style="text-align: var(--bs-body-text-align);"><b>Data de início</b></span><b>&nbsp;</b>: {contract_start_date}</p>
                    <p><span style="text-align: var(--bs-body-text-align);"><b>Data final</b></span><b>&nbsp;</b>: {contract_end_date}</p>
                    <p>Ansioso para ouvir de você.</p>
                    <p><b>Atenciosamente,</b><br></p>
                    <p>{company_name}</p>',
                    'tr' => '<p> </p>
                    <p><strong>Merhaba</strong> {contract_client}</p>
                    <p><b>Sözleşme Konusu</b> : {contract_subject}</p>
                    <p><b>Sözleşme Projesi</b> : {contract_project}</p>
                    <p><b>Başlangıç ​​Tarihi </b>: {contract_start_date}</p>
                    <p><b>Bitiş Tarihi </b>: {contract_end_date}</p>
                    <p>Sizden haber bekliyorum.</p>
                    <p><strong>Saygılarımızla, </strong></p>
                    <p>{şirket_adı</p>',
                    'pt' => '<p>&nbsp;</p>
                    <p><span style="text-align: var(--bs-body-text-align);"><b>Olá</b></span>&nbsp;{contract_client}</p>
                    <p><span style="text-align: var(--bs-body-text-align);"><b>Assunto do Contrato</b></span>&nbsp;: {contract_subject}</p>
                    <p><span style="text-align: var(--bs-body-text-align);"><b>Projeto de contrato&nbsp;</b></span>: {contract_project}</p>
                    <p><span style="text-align: var(--bs-body-text-align);"><b>Data de início</b></span><b>&nbsp;</b>: {contract_start_date}</p>
                    <p><span style="text-align: var(--bs-body-text-align);"><b>Data final</b></span><b>&nbsp;</b>: {contract_end_date}</p>
                    <p>Ansioso para ouvir de você.</p>
                    <p><b>Atenciosamente,</b><br></p>
                    <p>{company_name}</p>',
                ],
            ],
            'new_project' => [
                'subject' => 'New Project',
                'lang' => [
                    'ar' => '<p><strong>مرحبا-مرحبا-</strong> {project_user}</p>
                    <p><b>اسم المشروع</b>&nbsp;: {project_name}</p>
                    <p><b>تاريخ البدء&nbsp;</b>: {project_start_date}</p>
                    <p><b>تاريخ الانتهاء&nbsp;</b>: {project_end_date}</p>
                    <p><b>الساعات المقدرة&nbsp;</b>: {hours}</p>
                    <p>. أتطلع لسماع منك</p>
                    <p><strong>نوع تحيات, </strong></p>
                    <p>{company_name}</p>',
                    'zh' => '<p><strong>你好我是</strong> {project_user}</p>
                    <p><b>项目名称</b>&nbsp;: {project_name}</p>
                    <p><b>开始日期&nbsp;</b>: {project_start_date}</p>
                    <p><b>结束日期&nbsp;</b>: {project_end_date}</p>
                    <p><b>估算小时数&nbsp;</b>: {hours}</p>
                    <p>期待您的来信。</p>
                    <p><strong>敬敬, </strong></p>
                    <p>{company_name}</p>',
                    'da' =>'<p><strong>Hej</strong> {project_user}</p>
                    <p><b>Projektnavn</b>&nbsp;: {project_name}</p>
                    <p><b>Startdato&nbsp;</b>: {project_start_date}</p>
                    <p><b>Slutdato&nbsp;</b>: {project_end_date}</p>
                    <p><b>Estimerede timer&nbsp;</b>: {hours}</p>
                    <p>Jeg glæder mig til at høre fra dig.</p>
                    <p><strong>Kind Hilds, </strong></p>
                    <p>{company_name}</p>',
                    'de' =>'<p><strong>Hi.</strong> {project_user}</p>
                    <p><b>Projektname</b>&nbsp;: {project_name}</p>
                    <p><b>Startdatum&nbsp;</b>: {project_start_date}</p>
                    <p><b>Enddatum&nbsp;</b>: {project_end_date}</p>
                    <p><b>Geschätzte Stunden&nbsp;</b>: {hours}</p>
                    <p>Freuen Sie sich auf das Hören von Ihnen.</p>
                    <p><strong>KGütige Grüße, </strong></p>
                    <p>{company_name}</p>',
                    'en' => '<p><strong>Hi</strong> {project_user}</p>
                    <p><b>Project Name</b>&nbsp;: {project_name}</p>
                    <p><b>Start Date&nbsp;</b>: {project_start_date}</p>
                    <p><b>End Date&nbsp;</b>: {project_end_date}</p>
                    <p><b>Estimated Hours&nbsp;</b>: {hours}</p>
                    <p>Looking forward to hear from you.</p>
                    <p><strong>Kind Regards, </strong></p>
                    <p>{company_name}</p>',
                    'es' => '<p><strong>Hi.</strong> {project_user}</p>
                    <p><b>Nombre del proyecto</b>&nbsp;: {project_name}</p>
                    <p><b>Fecha de inicio&nbsp;</b>: {project_start_date}</p>
                    <p><b>Fecha final&nbsp;</b>: {project_end_date}</p>
                    <p><b>Horas estimadas&nbsp;</b>: {hours}</p>
                    <p>Con ganas de escuchar de usted. </p>
                    <p><strong>Bondadoso, </strong></p>
                    <p>{company_name}</p>',
                    'fr' => '<p><strong>Salut</strong> {project_user}</p>
                    <p><b>Nom du projet</b>&nbsp;: {project_name}</p>
                    <p><b>Date de début&nbsp;</b>: {project_start_date}</p>
                    <p><b>Date de fin&nbsp;</b>: {project_end_date}</p>
                    <p><b>Heures estimées&nbsp;</b>: {hours}</p>
                    <p>Regard sur lavenir.</p>
                    <p><strong>Cordialement, </strong></p>
                    <p>{company_name}</p>',
                    'he' =>'<p><strong>היי</strong> {project_user}</p>
                    <p><b>שם הפרויקט</b>&nbsp;: {project_name}</p>
                    <p><b>תאריך התחלה&nbsp;</b>: {project_start_date}</p>
                    <p><b>תאריך סיום&nbsp;</b>: {project_end_date}</p>
                    <p><b>שעות משוערות&nbsp;</b>: {hours}</p>
                    <p>מצפה לשמוע ממך.</p>
                    <p><strong>די. די. די. </strong></p>
                    <p>{company_name}</p>',
                    'it' => '<p><strong>Ciao</strong> {project_user}</p>
                    <p><b>Nome del progetto</b>&nbsp;: {project_name}</p>
                    <p><b>Data di inizio&nbsp;</b>: {project_start_date}</p>
                    <p><b>Data di fine&nbsp;</b>: {project_end_date}</p>
                    <p><b>Ore Stimate&nbsp;</b>: {hours}</p>
                    <p>Non vedo lora di sentirti<br></p>
                    <p><strong>Cordiali saluti, </strong></p>
                    <p>{company_name}</p>',
                    'ja' => '<p><strong>やあ</strong> {project_user}</p>
                    <p><b>プロジェクト名</b>&nbsp;: {project_name}</p>
                    <p><b>開始日&nbsp;</b>: {project_start_date}</p>
                    <p><b>終了日&nbsp;</b>: {project_end_date}</p>
                    <p><b>推定時間&nbsp;</b>: {hours}</p>
                    <p>あなたから聞いて楽しみにして<.</p>
                    <p><strong>カインド・レガード, </strong></p>
                    <p>{company_name}</p>',
                    'nl' => '<p><strong>Hallo</strong> {project_user}</p>
                    <p><b>Projectnaam</b>&nbsp;: {project_name}</p>
                    <p><b>Begindatum&nbsp;</b>: {project_start_date}</p>
                    <p><b>Einddatum&nbsp;</b>: {project_end_date}</p>
                    <p><b>Geschatte uren&nbsp;</b>: {hours}</p>
                    <p>Naar voren komen om van u te horen.</p>
                    <p><strong>Vriendelijke groeten, </strong></p>
                    <p>{company_name}</p>',
                    'pl' => '<p><strong>Witaj</strong> {project_user}</p>
                    <p><b>Nazwa projektu</b>&nbsp;: {project_name}</p>
                    <p><b>Data rozpoczęcia&nbsp;</b>: {project_start_date}</p>
                    <p><b>Data zakończenia&nbsp;</b>: {project_end_date}</p>
                    <p><b>Szacowane godziny&nbsp;</b>: {hours}</p>
                    <p>Z niecierżną datą i z niecierżką na Ciebie.</p>
                    <p><strong>Życzliwie, </strong></p>
                    <p>{company_name}</p>',
                    'ru' => '<p><strong>Привет</strong> {project_user}</p>
                    <p><b>Имя проекта</b>&nbsp;: {project_name}</p>
                    <p><b>Начальная дата&nbsp;</b>: {project_start_date}</p>
                    <p><b>Конечная дата&nbsp;</b>: {project_end_date}</p>
                    <p><b>Оценка часов&nbsp;</b>: {hours}</p>
                    <p>нетерпением ожидаю услышать от вас.</p>
                    <p><strong>Что касается, </strong></p>
                    <p>{company_name}</p>',
                    'pt' => '<p><strong>Oi</strong> {project_user}</p>
                    <p><b>Nome do Projeto</b>&nbsp;: {project_name}</p>
                    <p><b>Data de Início&nbsp;</b>: {project_start_date}</p>
                    <p><b>Data de encerramento&nbsp;</b>: {project_end_date}</p>
                    <p><b>Horas Estimadas&nbsp;</b>: {hours}</p>
                    <p>Ansioso para ouvir de você.</p>
                    <p><strong>Tipo Considera, </strong></p>
                    <p>{company_name}</p>',
                    'tr' => '<p><strong>Hi</strong> {project_user}</p>
                    <p><b>Proje Adı</b>&nbsp;: {project_name}</p>
                    <p><b>Başlangıç Tarihi&nbsp;</b>: {project_start_date}</p>
                    <p><b>Bitiş Tarihi&nbsp;</b>: {project_end_date}</p>
                    <p><b>Tahmini Saat&nbsp;</b>: {hours}</p>
                    <p>Sizden haber bekliyorum.</p>
                    <p><strong>Saygılarımızla, </strong></p>
                    <p>{company_name}</p>',
                    'pt' => '<p><strong>Oi</strong> {project_user}</p>
                    <p><b>Nome do Projeto</b>&nbsp;: {project_name}</p>
                    <p><b>Data de Início&nbsp;</b>: {project_start_date}</p>
                    <p><b>Data de encerramento&nbsp;</b>: {project_end_date}</p>
                    <p><b>Horas Estimadas&nbsp;</b>: {hours}</p>
                    <p>Ansioso para ouvir de você.</p>
                    <p><strong>Tipo Considera, </strong></p>
                    <p>{company_name}</p>',
                ],
            ],
            'project_assign_member' => [
                'subject' => 'Project Assign Member',
                'lang' => [
                    'ar' => '<p><strong>مرحبا-مرحبا-</strong> {project_user}</p>
                    <p><b>اسم المشروع</b>&nbsp;: {project_name}</p>
                    <p><b>تاريخ البدء&nbsp;</b>: {project_start_date}</p>
                    <p><b>تاريخ الانتهاء&nbsp;</b>: {project_end_date}</p>
                    <p><b>الساعات المقدرة&nbsp;</b>: {hours}</p>
                    <p>. أتطلع لسماع منك</p>
                    <p><strong>نوع تحيات, </strong></p>
                    <p>{company_name}</p>',
                    'zh' => '<p><strong>你好我是</strong> {project_user}</p>
                    <p><b>项目名称</b>&nbsp;: {project_name}</p>
                    <p><b>开始日期&nbsp;</b>: {project_start_date}</p>
                    <p><b>结束日期&nbsp;</b>: {project_end_date}</p>
                    <p><b>估算小时数&nbsp;</b>: {hours}</p>
                    <p>期待您的来信。</p>
                    <p><strong>敬敬, </strong></p>
                    <p>{company_name}</p>',
                    'da' =>'<p><strong>Hej</strong> {project_user}</p>
                    <p><b>Projektnavn</b>&nbsp;: {project_name}</p>
                    <p><b>Startdato&nbsp;</b>: {project_start_date}</p>
                    <p><b>Slutdato&nbsp;</b>: {project_end_date}</p>
                    <p><b>Estimerede timer&nbsp;</b>: {hours}</p>
                    <p>Jeg glæder mig til at høre fra dig.</p>
                    <p><strong>Kind Hilds, </strong></p>
                    <p>{company_name}</p>',
                    'de' =>'<p><strong>Hi.</strong> {project_user}</p>
                    <p><b>Projektname</b>&nbsp;: {project_name}</p>
                    <p><b>Startdatum&nbsp;</b>: {project_start_date}</p>
                    <p><b>Enddatum&nbsp;</b>: {project_end_date}</p>
                    <p><b>Geschätzte Stunden&nbsp;</b>: {hours}</p>
                    <p>Freuen Sie sich auf das Hören von Ihnen.</p>
                    <p><strong>KGütige Grüße, </strong></p>
                    <p>{company_name}</p>',
                    'en' => '<p><strong>Hi</strong> {project_user}</p>
                    <p><b>Project Name</b>&nbsp;: {project_name}</p>
                    <p><b>Start Date&nbsp;</b>: {project_start_date}</p>
                    <p><b>End Date&nbsp;</b>: {project_end_date}</p>
                    <p><b>Estimated Hours&nbsp;</b>: {hours}</p>
                    <p>Looking forward to hear from you.</p>
                    <p><strong>Kind Regards, </strong></p>
                    <p>{company_name}</p>',
                    'es' => '<p><strong>Hi.</strong> {project_user}</p>
                    <p><b>Nombre del proyecto</b>&nbsp;: {project_name}</p>
                    <p><b>Fecha de inicio&nbsp;</b>: {project_start_date}</p>
                    <p><b>Fecha final&nbsp;</b>: {project_end_date}</p>
                    <p><b>Horas estimadas&nbsp;</b>: {hours}</p>
                    <p>Con ganas de escuchar de usted. </p>
                    <p><strong>Bondadoso, </strong></p>
                    <p>{company_name}</p>',
                    'fr' => '<p><strong>Salut</strong> {project_user}</p>
                    <p><b>Nom du projet</b>&nbsp;: {project_name}</p>
                    <p><b>Date de début&nbsp;</b>: {project_start_date}</p>
                    <p><b>Date de fin&nbsp;</b>: {project_end_date}</p>
                    <p><b>Heures estimées&nbsp;</b>: {hours}</p>
                    <p>Regard sur lavenir.</p>
                    <p><strong>Cordialement, </strong></p>
                    <p>{company_name}</p>',
                    'he' =>'<p><strong>היי</strong> {project_user}</p>
                    <p><b>שם הפרויקט</b>&nbsp;: {project_name}</p>
                    <p><b>תאריך התחלה&nbsp;</b>: {project_start_date}</p>
                    <p><b>תאריך סיום&nbsp;</b>: {project_end_date}</p>
                    <p><b>שעות משוערות&nbsp;</b>: {hours}</p>
                    <p>מצפה לשמוע ממך.</p>
                    <p><strong>די. די. די. </strong></p>
                    <p>{company_name}</p>',
                    'it' => '<p><strong>Ciao</strong> {project_user}</p>
                    <p><b>Nome del progetto</b>&nbsp;: {project_name}</p>
                    <p><b>Data di inizio&nbsp;</b>: {project_start_date}</p>
                    <p><b>Data di fine&nbsp;</b>: {project_end_date}</p>
                    <p><b>Ore Stimate&nbsp;</b>: {hours}</p>
                    <p>Non vedo lora di sentirti<br></p>
                    <p><strong>Cordiali saluti, </strong></p>
                    <p>{company_name}</p>',
                    'ja' => '<p><strong>やあ</strong> {project_user}</p>
                    <p><b>プロジェクト名</b>&nbsp;: {project_name}</p>
                    <p><b>開始日&nbsp;</b>: {project_start_date}</p>
                    <p><b>終了日&nbsp;</b>: {project_end_date}</p>
                    <p><b>推定時間&nbsp;</b>: {hours}</p>
                    <p>あなたから聞いて楽しみにして<.</p>
                    <p><strong>カインド・レガード, </strong></p>
                    <p>{company_name}</p>',
                    'nl' => '<p><strong>Hallo</strong> {project_user}</p>
                    <p><b>Projectnaam</b>&nbsp;: {project_name}</p>
                    <p><b>Begindatum&nbsp;</b>: {project_start_date}</p>
                    <p><b>Einddatum&nbsp;</b>: {project_end_date}</p>
                    <p><b>Geschatte uren&nbsp;</b>: {hours}</p>
                    <p>Naar voren komen om van u te horen.</p>
                    <p><strong>Vriendelijke groeten, </strong></p>
                    <p>{company_name}</p>',
                    'pl' => '<p><strong>Witaj</strong> {project_user}</p>
                    <p><b>Nazwa projektu</b>&nbsp;: {project_name}</p>
                    <p><b>Data rozpoczęcia&nbsp;</b>: {project_start_date}</p>
                    <p><b>Data zakończenia&nbsp;</b>: {project_end_date}</p>
                    <p><b>Szacowane godziny&nbsp;</b>: {hours}</p>
                    <p>Z niecierżną datą i z niecierżką na Ciebie.</p>
                    <p><strong>Życzliwie, </strong></p>
                    <p>{company_name}</p>',
                    'ru' => '<p><strong>Привет</strong> {project_user}</p>
                    <p><b>Имя проекта</b>&nbsp;: {project_name}</p>
                    <p><b>Начальная дата&nbsp;</b>: {project_start_date}</p>
                    <p><b>Конечная дата&nbsp;</b>: {project_end_date}</p>
                    <p><b>Оценка часов&nbsp;</b>: {hours}</p>
                    <p>нетерпением ожидаю услышать от вас.</p>
                    <p><strong>Что касается, </strong></p>
                    <p>{company_name}</p>',
                    'pt' => '<p><strong>Oi</strong> {project_user}</p>
                    <p><b>Nome do Projeto</b>&nbsp;: {project_name}</p>
                    <p><b>Data de Início&nbsp;</b>: {project_start_date}</p>
                    <p><b>Data de encerramento&nbsp;</b>: {project_end_date}</p>
                    <p><b>Horas Estimadas&nbsp;</b>: {hours}</p>
                    <p>Ansioso para ouvir de você.</p>
                    <p><strong>Tipo Considera, </strong></p>
                    <p>{company_name}</p>',
                    'tr' => '<p><strong>Hi</strong> {project_user}</p>
                    <p><b>Proje Adı</b>&nbsp;: {project_name}</p>
                    <p><b>Başlangıç Tarihi&nbsp;</b>: {project_start_date}</p>
                    <p><b>Bitiş Tarihi&nbsp;</b>: {project_end_date}</p>
                    <p><b>Tahmini Saat&nbsp;</b>: {hours}</p>
                    <p>Sizden haber bekliyorum.</p>
                    <p><strong>Saygılarımızla, </strong></p>
                    <p>{company_name}</p>',
                    'pt' => '<p><strong>Oi</strong> {project_user}</p>
                    <p><b>Nome do Projeto</b>&nbsp;: {project_name}</p>
                    <p><b>Data de Início&nbsp;</b>: {project_start_date}</p>
                    <p><b>Data de encerramento&nbsp;</b>: {project_end_date}</p>
                    <p><b>Horas Estimadas&nbsp;</b>: {hours}</p>
                    <p>Ansioso para ouvir de você.</p>
                    <p><strong>Tipo Considera, </strong></p>
                    <p>{company_name}</p>',
                ],
            ],
            'new_task' => [
                'subject' => 'New Task',
                'lang' => [
                    'ar' => '<p><strong>مرحبا-مرحبا-</strong> {task_user}</p>
                    <p><b>اسم المهمة</b>&nbsp;: {task_name}</p>
                    <p><b>اسم المشروع</b>&nbsp;: {project_name}</p>
                    <p><b>تاريخ البدء&nbsp;</b>: {task_start_date}</p>
                    <p><b>تاريخ الانتهاء&nbsp;</b>: {task_end_date}</p>
                    <p><b>الساعات المقدرة&nbsp;</b>: {hours}</p>
                    <p>. أتطلع لسماع منك</p>
                    <p><strong>نوع تحيات, </strong></p>
                    <p>{company_name}</p>',
                    'zh' => '<p><strong>你好我是</strong> {task_user}</p>
                    <p><b>任务名称</b>&nbsp;: {task_name}</p>
                    <p><b>项目名称</b>&nbsp;: {project_name}</p>
                    <p><b>开始日期&nbsp;</b>: {task_start_date}</p>
                    <p><b>结束日期&nbsp;</b>: {task_end_date}</p>
                    <p><b>估算小时数&nbsp;</b>: {hours}</p>
                    <p>期待您的来信。</p>
                    <p><strong>敬敬, </strong></p>
                    <p>{company_name}</p>',
                    'da' =>'<p><strong>Hej</strong> {task_user}</p>
                    <p><b>Navn på opgave</b>&nbsp;: {task_name}</p>
                    <p><b>Projektnavn</b>&nbsp;: {project_name}</p>
                    <p><b>Startdato&nbsp;</b>: {task_start_date}</p>
                    <p><b>Slutdato&nbsp;</b>: {task_end_date}</p>
                    <p><b>Estimerede timer&nbsp;</b>: {hours}</p>
                    <p>Jeg glæder mig til at høre fra dig.</p>
                    <p><strong>Kind Hilds, </strong></p>
                    <p>{company_name}</p>',
                    'de' =>'<p><strong>Hi.</strong> {task_user}</p>
                    <p><b>Taskname</b>&nbsp;: {task_name}</p>
                    <p><b>Projektname</b>&nbsp;: {project_name}</p>
                    <p><b>Startdatum&nbsp;</b>: {task_start_date}</p>
                    <p><b>Enddatum&nbsp;</b>: {task_end_date}</p>
                    <p><b>Geschätzte Stunden&nbsp;</b>: {hours}</p>
                    <p>Freuen Sie sich auf das Hören von Ihnen.</p>
                    <p><strong>KGütige Grüße, </strong></p>
                    <p>{company_name}</p>',
                    'en' => '<p><strong>Hi</strong> {task_user}</p>
                    <p><b>Task Name</b>&nbsp;: {task_name}</p>
                    <p><b>Project Name</b>&nbsp;: {project_name}</p>
                    <p><b>Start Date&nbsp;</b>: {task_start_date}</p>
                    <p><b>End Date&nbsp;</b>: {task_end_date}</p>
                    <p><b>Estimated Hours&nbsp;</b>: {hours}</p>
                    <p>Looking forward to hear from you.</p>
                    <p><strong>Kind Regards, </strong></p>
                    <p>{company_name}</p>',
                    'es' => '<p><strong>Hi.</strong> {task_user}</p>
                    <p><b>Nombre de tarea</b>&nbsp;: {task_name}</p>
                    <p><b>Nombre del proyecto</b>&nbsp;: {project_name}</p>
                    <p><b>Fecha de inicio&nbsp;</b>: {task_start_date}</p>
                    <p><b>Fecha final&nbsp;</b>: {task_end_date}</p>
                    <p><b>Horas estimadas&nbsp;</b>: {hours}</p>
                    <p>Con ganas de escuchar de usted. </p>
                    <p><strong>Bondadoso, </strong></p>
                    <p>{company_name}</p>',
                    'fr' => '<p><strong>Salut</strong> {task_user}</p>
                    <p><b>Nom de la tâche</b>&nbsp;: {task_name}</p>
                    <p><b>Nom du projet</b>&nbsp;: {project_name}</p>
                    <p><b>Date de début&nbsp;</b>: {task_start_date}</p>
                    <p><b>Date de fin&nbsp;</b>: {task_end_date}</p>
                    <p><b>Heures estimées&nbsp;</b>: {hours}</p>
                    <p>Regard sur lavenir.</p>
                    <p><strong>Cordialement, </strong></p>
                    <p>{company_name}</p>',
                    'he' =>'<p><strong>היי</strong> {task_user}</p>
                    <p><b>שם משימה</b>&nbsp;: {task_name}</p>
                    <p><b>שם הפרויקט</b>&nbsp;: {project_name}</p>
                    <p><b>תאריך התחלה&nbsp;</b>: {task_start_date}</p>
                    <p><b>תאריך סיום&nbsp;</b>: {task_end_date}</p>
                    <p><b>שעות משוערות&nbsp;</b>: {hours}</p>
                    <p>מצפה לשמוע ממך.</p>
                    <p><strong>די. די. די. </strong></p>
                    <p>{company_name}</p>',
                    'it' => '<p><strong>Ciao</strong> {task_user}</p>
                    <p><b>Nome attività</b>&nbsp;: {task_name}</p>
                    <p><b>Nome del progetto</b>&nbsp;: {project_name}</p>
                    <p><b>Data di inizio&nbsp;</b>: {task_start_date}</p>
                    <p><b>Data di fine&nbsp;</b>: {task_end_date}</p>
                    <p><b>Ore Stimate&nbsp;</b>: {hours}</p>
                    <p>Non vedo lora di sentirti<br></p>
                    <p><strong>Cordiali saluti, </strong></p>
                    <p>{company_name}</p>',
                    'ja' => '<p><strong>やあ</strong> {task_user}</p>
                    <p><b>タスク名</b>&nbsp;: {task_name}</p>
                    <p><b>プロジェクト名</b>&nbsp;: {project_name}</p>
                    <p><b>開始日&nbsp;</b>: {task_start_date}</p>
                    <p><b>>End Date&nbsp;</b>: {task_end_date}</p>
                    <p><b>推定時間&nbsp;</b>: {hours}</p>
                    <p>あなたから聞いて楽しみにして<.</p>
                    <p><strong>カインド・レガード, </strong></p>
                    <p>{company_name}</p>',
                    'nl' => '<p><strong>Hallo</strong> {task_user}</p>
                    <p><b>Taaknaam</b>&nbsp;: {task_name}</p>
                    <p><b>Projectnaam</b>&nbsp;: {project_name}</p>
                    <p><b>Begindatum&nbsp;</b>: {task_start_date}</p>
                    <p><b>Einddatum&nbsp;</b>: {task_end_date}</p>
                    <p><b>Geschatte uren&nbsp;</b>: {hours}</p>
                    <p>Naar voren komen om van u te horen.</p>
                    <p><strong>Vriendelijke groeten, </strong></p>
                    <p>{company_name}</p>',
                    'pl' => '<p><strong>Witaj</strong> {task_user}</p>
                    <p><b>Nazwa zadania</b>&nbsp;: {task_name}</p>
                    <p><b>Nazwa projektu</b>&nbsp;: {project_name}</p>
                    <p><b>Data rozpoczęcia&nbsp;</b>: {task_start_date}</p>
                    <p><b>Data zakończenia&nbsp;</b>: {task_end_date}</p>
                    <p><b>Szacowane godziny&nbsp;</b>: {hours}</p>
                    <p>Z niecierżną datą i z niecierżką na Ciebie.</p>
                    <p><strong>Życzliwie, </strong></p>
                    <p>{company_name}</p>',
                    'ru' => '<p><strong>Привет</strong> {task_user}</p>
                    <p><b>Имя задачи</b>&nbsp;: {task_name}</p>
                    <p><b>Имя проекта</b>&nbsp;: {project_name}</p>
                    <p><b>Начальная дата&nbsp;</b>: {task_start_date}</p>
                    <p><b>Конечная дата&nbsp;</b>: {task_end_date}</p>
                    <p><b>Оценка часов&nbsp;</b>: {hours}</p>
                    <p>нетерпением ожидаю услышать от вас.</p>
                    <p><strong>Что касается, </strong></p>
                    <p>{company_name}</p>',
                    'pt' => '<p><strong>Oi</strong> {task_user}</p>
                    <p><b>Nome da Tarefa</b>&nbsp;: {task_name}</p>
                    <p><b>Nome do Projeto</b>&nbsp;: {project_name}</p>
                    <p><b>Data de Início&nbsp;</b>: {task_start_date}</p>
                    <p><b>Data de encerramento&nbsp;</b>: {task_end_date}</p>
                    <p><b>Horas Estimadas&nbsp;</b>: {hours}</p>
                    <p>Ansioso para ouvir de você.</p>
                    <p><strong>Tipo Considera, </strong></p>
                    <p>{company_name}</p>',
                    'tr' => '<p><strong>Hi</strong> {task_user}</p>
                    <p><b>Görev Adı</b>&nbsp;: {task_name}</p>
                    <p><b>Proje Adı</b>&nbsp;: {project_name}</p>
                    <p><b>Başlangıç Tarihi&nbsp;</b>: {task_start_date}</p>
                    <p><b>Bitiş Tarihi&nbsp;</b>: {task_end_date}</p>
                    <p><b>Tahmini Saat&nbsp;</b>: {hours}</p>
                    <p>Sizden haber bekliyorum.</p>
                    <p><strong>Saygılarımızla, </strong></p>
                    <p>{company_name}</p>',
                    'pt' => '<p><strong>Oi</strong> {task_user}</p>
                    <p><b>Nome da Tarefa</b>&nbsp;: {task_name}</p>
                    <p><b>Nome do Projeto</b>&nbsp;: {project_name}</p>
                    <p><b>Data de Início&nbsp;</b>: {task_start_date}</p>
                    <p><b>Data de encerramento&nbsp;</b>: {task_end_date}</p>
                    <p><b>Horas Estimadas&nbsp;</b>: {hours}</p>
                    <p>Ansioso para ouvir de você.</p>
                    <p><strong>Tipo Considera, </strong></p>
                    <p>{company_name}</p>',
                ],
            ],
            'task_status_updated' => [
                'subject' => 'Task Status Updated',
                'lang' => [
                    'ar' => '<p><strong>مرحبا-مرحبا-</strong> {task_user}</p>
                    <p>{task_name} تم تغيير الحالة من {old_stage_name} الى {new_stage_name}</p>
                    <p><strong>نوع تحيات, </strong></p>
                    <p>{company_name}</p>',
                    'zh' => '<p><strong>你好我是</strong> {task_user}</p>
                    <p>{task_name} 状态更改自 {old_stage_name} 到 {new_stage_name}</p>
                    <p><strong>敬敬, </strong></p>
                    <p>{company_name}</p>',
                    'da' =>'<p><strong>Hej</strong> {task_user}</p>
                    <p>{task_name} status ændret fra {old_stage_name} til {new_stage_name}</p>
                    <p><strong>Kind Hilds, </strong></p>
                    <p>{company_name}</p>',
                    'de' =>'<p>&nbsp;</p>
                    <p><strong>Hi</strong> {task_user}</p>
                    <p>{task_name} Status geändert von {old_stage_name} bis {new_stage_name}</p>
                    <p><strong>>KGütige Grüße, </strong></p>
                    <p>{company_name}</p>',
                    'en' => '<p>&nbsp;</p>
                    <p><strong>Hi</strong> {task_user}</p>
                    <p>{task_name} status changed from {old_stage_name} to {new_stage_name}</p>
                    <p><strong>Kind Regards, </strong></p>
                    <p>{company_name}</p>',
                    'es' => '<p>&nbsp;</p>
                    <p><strong>Hola</strong> {task_user}</p>
                    <p>{task_name} cambio de estado de {old_stage_name} a {new_stage_name}</p>
                    <p><strong>Bondadoso, </strong></p>
                    <p>{company_name}</p>',
                    'fr' => '<p>&nbsp;</p>
                    <p><strong>Salut</strong> {task_user}</p>
                    <p>{task_name} Statut modifié par {old_stage_name} Vers {new_stage_name}</p>
                    <p><strong>Cordialement, </strong></p>
                    <p>{company_name}</p>',
                    'he' =>'<p>&nbsp;</p>
                    <p><strong>היי</strong> {task_user}</p>
                    <p>{task_name} הסטאטוס השתנה מ - {old_stage_name} ל {new_stage_name}</p>
                    <p><strong>די. די. די. , </strong></p>
                    <p>{company_name}</p>',
                    'it' => '<p>&nbsp;</p>
                    <p><strong>Ciao</strong> {task_user}</p>
                    <p>{task_name} stato modificato da {old_stage_name} a {new_stage_name}</p>
                    <p><strong>Cordiali saluti, </strong></p>
                    <p>{company_name}</p>',
                    'ja' => '<p>&nbsp;</p>
                    <p><strong>やあ</strong> {task_user}</p>
                    <p>{task_name} 状況の変更元 {old_stage_name} に対する {new_stage_name}</p>
                    <p><strong>カインド・レガード, </strong></p>
                    <p>{company_name}</p>',
                    'nl' => '<p>&nbsp;</p>
                    <p><strong>Hallo</strong> {task_user}</p>
                    <p>{task_name} status gewijzigd van {old_stage_name} tot {new_stage_name}</p>
                    <p><strong>Vriendelijke groeten, </strong></p>
                    <p>{company_name}</p>',
                    'pl' => '<p>&nbsp;</p>
                    <p><strong>Witaj</strong> {task_user}</p>
                    <p>{task_name} status zmieniony z {old_stage_name} do {new_stage_name}</p>
                    <p><strong>Życzliwie, </strong></p>
                    <p>{company_name}</p>',
                    'ru' => '<p>&nbsp;</p>
                    <p><strong>Привет</strong> {task_user}</p>
                    <p>{task_name} состояние изменено с {old_stage_name} в {new_stage_name}</p>
                    <p><strong>Что касается, </strong></p>
                    <p>{company_name}</p>',
                    'pt' => '<p>&nbsp;</p>
                    <p><strong>Oi</strong> {task_user}</p>
                    <p>{task_name} status alterado de {old_stage_name} para {new_stage_name}</p>
                    <p><strong>Tipo Considera, </strong></p>
                    <p>{company_name}</p>',
                    'tr' =>'<p>&nbsp;</p>
                    <p><strong>Hi</strong> {task_user}</p>
                    <p>{task_name} durum değişti {old_stage_name} ile {new_stage_name}</p>
                    <p><strong>Saygılarımızla, </strong></p>
                    <p>{company_name}</p>',
                    'pt' => '<p>&nbsp;</p>
                    <p><strong>Oi</strong> {task_user}</p>
                    <p>{task_name} status alterado de {old_stage_name} para {new_stage_name}</p>
                    <p><strong>Tipo Considera, </strong></p>
                    <p>{company_name}</p>',
                ],
            ],
            'new_leave' => [
                'subject' => 'New Leave',
                'lang' => [
                    'ar' => '<p><strong>مرحبا-مرحبا-</strong> {user_name}</p>
                    <p>تكوين ترك جديد من {start_date} الى {end_date} بالنسبة الى {leave_reason}</p>
                    <p><strong>نوع تحيات, </strong></p>
                    <p>{employee_name}</p>',
                    'zh' => '<p><strong>你好我是</strong> {user_name}</p>
                    <p>新离开创建自 {start_date} 到 {end_date} 为 {leave_reason}</p>
                    <p><strong>敬敬, </strong></p>
                    <p>{employee_name}</p>',
                    'da' =>'<p><strong>-Hej.</strong> {user_name}</p>
                    <p>Ny Forlad, opret fra {start_date} til {end_date} for {leave_reason}</p>
                    <p><strong>Kind Hilds, </strong></p>
                    <p>{employee_name}</p>',
                    'de' =>'<p><strong>Hi.</strong> {user_name}</p>
                    <p>Neue Hinterlasse erstellen aus {start_date} bis {end_date} für {leave_reason}</p>
                    <p><strong>KGütige Grüße, </strong></p>
                    <p>{employee_name}</p>',
                    'en' => '<p><strong>Hi</strong> {user_name}</p>
                    <p>New Leave create from {start_date} to {end_date} for {leave_reason}</p>
                    <p><strong>Kind Regards, </strong></p>
                    <p>{employee_name}</p>',
                    'es' => '<p><strong>Hola.</strong> {user_name}</p>
                    <p>Nueva licencia para crear desde {start_date} Holaa {end_date} para {leave_reason}</p>
                    <p><strong>Bondadoso, </strong></p>
                    <p>{employee_name}</p>',
                    'fr' => '<p><strong>Salut</strong> {user_name}</p>
                    <p>Nouveau congé créé à partir de {start_date} Vers {end_date} Pour {leave_reason}</p>
                    <p><strong>Cordialement, </strong></p>
                    <p>{employee_name}</p>',
                    'he' =>'<p><strong>היי</strong> {user_name}</p>
                    <p>יצירה חדשה של יצירה מ - {start_date} ל {end_date} עבור {leave_reason}</p>
                    <p><strong>די. די. די. </strong></p>
                    <p>{employee_name}</p>',
                    'it' => '<p><strong>Ciao</strong> {user_name}</p>
                    <p>Nuovo Leave create da {start_date} a {end_date} per {leave_reason}</p>
                    <p><strong>Cordiali saluti, </strong></p>
                    <p>{employee_name}</p>',
                    'ja' => '<p><strong>やあ</strong> {user_name}</p>
                    <p>新規休暇の作成元 {start_date} に対する {end_date} 対象 {leave_reason}</p>
                    <p><strong>カインド・レガード, </strong></p>
                    <p>{employee_name}</p>',
                    'nl' => '<p><strong>Hallo</strong> {user_name}</p>
                    <p>Nieuw verlaten maken van {start_date} tot {end_date} voor {leave_reason}</p>
                    <p><strong>Vriendelijke groeten, </strong></p>
                    <p>{employee_name}</p>',
                    'pl' => '<p><strong>Witaj</strong> {user_name}</p>
                    <p>Nowe pozostaw. utworzenie z {start_date} do {end_date} dla {leave_reason}</p>
                    <p><strong>Życzliwie, </strong></p>
                    <p>{employee_name}</p>',
                    'ru' => '<p><strong>Привет.</strong> {user_name}</p>
                    <p>Создать новый отпуск {start_date} в {end_date} для {leave_reason}</p>
                    <p><strong>Что касается, </strong></p>
                    <p>{employee_name}</p>',
                    'pt' => '<p><strong>Oi</strong> {user_name}</p>
                    <p>Novo Deixe criar de {start_date} para {end_date} para {leave_reason}</p>
                    <p><strong>Tipo Considera, </strong></p>
                    <p>{employee_name}</p>',
                    'tr' =>'<p><strong>Hi</strong> {user_name}</p>
                    <p>Yeni Bırakıma yarat {start_date} ile {end_date} için {leave_reason}</p>
                    <p><strong>Saygılarımızla, </strong></p>
                    <p>{employee_name}</p>',
                    'pt' => '<p><strong>Oi</strong> {user_name}</p>
                    <p>Novo Deixe criar de {start_date} para {end_date} para {leave_reason}</p>
                    <p><strong>Tipo Considera, </strong></p>
                    <p>{employee_name}</p>',
                ],
            ],
        ];

        $email = EmailTemplate::all();

        foreach ($email as $e) {
            foreach ($defaultTemplate[$e->slug]['lang'] as $lang => $content) {
                $emailNoti = EmailTemplateLang::where('parent_id', $e->id)->where('lang', $lang)->count();
                if ($emailNoti == 0) {
                    EmailTemplateLang::create(
                        [
                            'parent_id' => $e->id,
                            'lang' => $lang,
                            'subject' => $defaultTemplate[$e->slug]['subject'],
                            'content' => $content,
                        ]
                    );
                }

            }
        }
    }

    public static function userDefaultData()
    {

        // Make Entry In User_Email_Template
        $allEmail = EmailTemplate::all();
        foreach ($allEmail as $email) {
            UserEmailTemplate::create(
                [
                    'template_id' => $email->id,
                    'user_id' => 2,
                    'is_active' => 1,
                ]
            );
        }
    }

    public function userDefaultDataRegister($user_id)
    {

        // Make Entry In User_Email_Template
        $allEmail = EmailTemplate::all();

        foreach ($allEmail as $email) {
            $emailTemplate = UserEmailTemplate::where('template_id', $email->id)->where('user_id',$user_id)->first();
            if($emailTemplate == null)
            {
                UserEmailTemplate::create(
                    [
                        'template_id' => $email->id,
                        'user_id' => $user_id,
                        'is_active' => 1,
                    ]
                );
            }

        }
    }

    public static function userDefaultWarehouse()
    {
        warehouse::create(
            [
                'name' => 'North Warehouse',
                'address' => '723 N. Tillamook Street Portland, OR Portland, United States',
                'city' => 'Portland',
                'city_zip' => 97227,
                'created_by' => 2,
            ]
        );

    }

    public function userWarehouseRegister($user_id)
    {
        warehouse::create(
            [
                'name' => 'North Warehouse',
                'address' => '723 N. Tillamook Street Portland, OR Portland, United States',
                'city' => 'Portland',
                'city_zip' => 97227,
                'created_by' => $user_id,
            ]
        );

    }

    //default bank account for new company
    public function userDefaultBankAccount($user_id)
    {
        BankAccount::create(
            [
                'holder_name' => 'cash',
                'bank_name' => '',
                'account_number' => '-',
                'opening_balance' => '0.00',
                'contact_number' => '-',
                'bank_address' => '-',
                'created_by' => $user_id,
            ]
        );

    }

    public function extraKeyword()
    {
        $keyArr = [
            __('Sun'),
            __('Mon'),
            __('Tue'),
            __('Wed'),
            __('Thu'),
            __('Fri'),
            __('Last 7 Days'),
            __('In Progress'),
            __('Complete'),
            __('Canceled'),
            __('Lead User Name'),
            __('CRM'),
            __('POS'),
            __('HRM'),
            __('Old Stage Name'),
            __('New Stage Name'),
            __('Contract Start Date'),
            __('Contract Name'),
            __('Contract Price'),
            __('Branch Name'),
            __('Support User Name'),
            __('Award Date'),
            __('Holiday Title'),
            __('Holiday Date'),
            __('Event Start Date'),
            __('Company Policy Name'),
            __('Invoice Issue Date'),
            __('Invoice Due Date'),
            __('Budget Name'),
            __('Budget Year'),
            __('Revenue Amount'),
            __('Revenue Date'),
            __('Payment Price'),
            __('New User'),
            __('Lifetime'),
            __('Coupon'),
            __('CoinGate'),
            __('Cashflow'),

        ];
    }

    public function barcodeFormat()
    {
        $settings = Utility::settings();
        return isset($settings['barcode_format']) ? $settings['barcode_format'] : 'code128';
    }

    public function barcodeType()
    {
        $settings = Utility::settings();
        return isset($settings['barcode_type']) ? $settings['barcode_type'] : 'css';
    }

    public static function employeeIdFormat($number)
    {
        $settings = Utility::settings();

        return $settings["employee_prefix"] . sprintf("%05d", $number);
    }

    //user log details
    public static function userCurrentLocation()
    {
        $company_id = Auth::User()->Company_ID();
        // dd($company_id);
        if (Auth::user()->user_type == 'company') {
            $location = location::where(['id' => Auth::User()->current_location, 'company_id' => $company_id, 'is_active' => 1])->first();

            if (!is_null($location)) {
                return $location->id;
            } else {
                return 0;
            }

        } elseif (Auth::user()->user_type != 'company' && Auth::user()->user_type != 'super admin') {

            if (Auth::user()->current_location == 0) {
                Auth::user()->current_location = Auth::user()->location_id;
            }

            $location = location::where('id', Auth::user()->current_location)->where('company_id', $company_id)->first();
            return $location->id;
        }
    }

    public function countEmployees()
    {
        return Employee::where('created_by', '=', $this->creatorId())->count();
    }

}
