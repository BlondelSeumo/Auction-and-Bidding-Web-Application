<?php

namespace App\Http\Controllers;

use App\Exports\AccountStatementExport;
use App\Exports\BalanceSheetExport;
use App\Exports\LeaveReportExport;
use App\Exports\PayrollExport;
use App\Exports\ProductStockExport;
use App\Exports\ProfitLossExport;
use App\Exports\ReceivableExport;
use App\Exports\SalesReportExport;
use App\Exports\TrialBalancExport;
use App\Models\AttendanceEmployee;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\BillProduct;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\ChartOfAccountParent;
use App\Models\ChartOfAccountSubType;
use App\Models\ChartOfAccountType;
use App\Models\ClientDeal;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DebitNote;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceProduct;
use App\Models\Lead;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Payment;
use App\Models\PaySlip;
use App\Models\Pipeline;
use App\Models\Pos;
use App\Models\ProductServiceCategory;
use App\Models\Purchase;
use App\Models\Revenue;
use App\Models\Source;
use App\Models\StockReport;
use App\Models\Tax;
use App\Models\TransactionLines;
use App\Models\User;
use App\Models\UserDeal;
use App\Models\Utility;
use App\Models\Vender;
use App\Models\warehouse;
use App\Models\WarehouseProduct;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{

    public function incomeSummary(Request $request)
    {
        if (\Auth::user()->can('income report')) {
            $account = BankAccount::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('holder_name', 'id');
            $account->prepend('All', '');
            $customer = Customer::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $customer->prepend('Select Customer', '');
            $category = ProductServiceCategory::where('created_by', '=', \Auth::user()->creatorId())->where('type', '=', 'income')->get()->pluck('name', 'id');
            $category->prepend('Select Category', '');

            if ($request->period === 'quarterly') {
                $month = [
                    'January-March',
                    'April-June',
                    'July-September',
                    'Octomber-December',
                ];
            } elseif ($request->period === 'half-yearly') {
                $month = [
                    'January-June',
                    'July-December',
                ];
            } elseif ($request->period === 'yearly') {
                $month = array_values(array_reverse($this->yearList()));
            } else {
                $month = $this->yearMonth();
            }
            $periods = Budget::$period;

            $data['monthList'] = $month;
            $data['yearList'] = $this->yearList();
            $data['periods'] = $periods;
            $filter['category'] = __('All');
            $filter['customer'] = __('All');

            if ($request->period === 'yearly') {
            $year = array_reverse($this->yearList());
            $yearList = [];
            foreach ($year as $value) {
                $yearList[$value] = $value;
            }
            }
            else
            {
            $yearList[($request->year) ? $request->year : date('Y')] = ($request->year) ? $request->year : date('Y');
            }

            if (isset($request->year)) {
                $year = $request->year;
            } else {
                $year = date('Y');
            }
            if (isset($request->period)) {
                $period = $request->period;
            } else {
                $period = 'monthly';
            }
            $data['currentYear'] = $year;

            // ------------------------------REVENUE INCOME-----------------------------------

                $incomes = Revenue::selectRaw('sum(revenues.amount) as amount,MONTH(date) as month,YEAR(date) as year, product_service_categories.name as category_id')->leftjoin('product_service_categories', 'revenues.category_id', '=', 'product_service_categories.id')->where('product_service_categories.type', '=', 'income');
                $incomes->where('revenues.created_by', '=', \Auth::user()->creatorId());
            if ($request->period != 'yearly') {
                $incomes->whereRAW('YEAR(date) =?', [$year]);
            }

            if (!empty($request->category)) {
                $incomes->where('category_id', '=', $request->category);
                $cat = ProductServiceCategory::find($request->category);
                $filter['category'] = !empty($cat) ? $cat->name : '';
            }

            if (!empty($request->customer)) {
                $incomes->where('customer_id', '=', $request->customer);
                $cust = Customer::find($request->customer);
                $filter['customer'] = !empty($cust) ? $cust->name : '';
            }

            $incomes->groupBy('month', 'year', 'category_id');
            $incomes = $incomes->get();

            $tmpArray = [];
            foreach ($incomes as $income) {
                $tmpArray[$income->category_id][$income->year][$income->month] = $income->amount;
            }
            $array = [];

            foreach ($tmpArray as $key => $yearData) {
                $array[$key] = [];

                foreach ($yearList as $targetYear) {
                    $array[$key][$targetYear] = [];

                    for ($i = 1; $i <= 12; $i++) {
                        $array[$key][$targetYear][$i] = 0;
                    }

                    if (isset($yearData[$targetYear])) {
                        foreach ($yearData[$targetYear] as $month => $value) {
                            $array[$key][$targetYear][$month] = (float) $value; // Convert the value to float if needed
                        }
                    }
                }
            }

            //---------------------------INVOICE INCOME-----------------------------------------------

            $invoices = Invoice::selectRaw('MONTH(send_date) as month,YEAR(send_date) as year,product_service_categories.name as category_id,invoice_id,invoices.id')
                ->leftjoin('product_service_categories', 'invoices.category_id', '=', 'product_service_categories.id')
                ->where('invoices.created_by', \Auth::user()->creatorId())->where('status', '!=', 0);

                if ($request->period != 'yearly') {
            $invoices->whereRAW('YEAR(send_date) =?', [$year]);
                }

            if (!empty($request->customer)) {
                $invoices->where('customer_id', '=', $request->customer);
            }

            if (!empty($request->category)) {
                $invoices->where('category_id', '=', $request->category);
            }

            $invoices = $invoices->get();

            // ------------------------------------------ invoice ------------------------------------------

            $invoiceTmpArray = [];

            foreach ($invoices as $invoice) {
                $invoiceTmpArray[$invoice->category_id][$invoice->year][$invoice->month][] = $invoice->getTotal();
            }

            $invoiceArray = [];

            foreach ($invoiceTmpArray as $key => $yearData) {
                $invoiceArray[$key] = [];

                foreach ($yearList as $targetYear) {
                    $invoiceArray[$key][$targetYear] = [];

                    for ($i = 1; $i <= 12; $i++) {
                        $invoiceArray[$key][$targetYear][$i] = 0;
                    }

                    if (isset($yearData[$targetYear])) {
                        foreach ($yearData[$targetYear] as $month => $values) {
                            if (is_array($values)) {
                                $sum = array_sum($values);
                                $invoiceArray[$key][$targetYear][$month] = $sum;
                            } else {
                                $invoiceArray[$key][$targetYear][$month] = (float) $values;
                            }
                        }
                    }
                }
            }

            $invoicesum = Utility::billInvoiceData($invoiceArray, $request , $yearList);

            $invoiceTotalArray = [];

            foreach ($invoices as $invoice) {
                $invoiceTotalArray[$invoice->year][$invoice->month][] = $invoice->getTotal();
            }
            // ------------------------------------------ income ------------------------------------------

            $incomeArr = [];
            $invoiceArr = [];
            $incomesum = [];

        foreach ($yearList as $year) {
            $invoiceArr[$year] = [];

            for ($i = 1; $i <= 12; $i++) {
                $invoiceArr[$year][$i] = 0;
            }

            if (isset($invoiceTotalArray[$year])) {
                foreach ($invoiceTotalArray[$year] as $month => $values) {
                    $invoiceArr[$year][$month] = array_sum($values);
                }
            }
        }


            foreach ($array as $key => $categoryData) {

                $incomesum[] = Utility::revenuePaymentData($key , $categoryData, $request ,$yearList);

            }

            $revenueTotalArray = [];

            foreach ($incomes as $income) {
                $revenueTotalArray[$income->year][$income->month][] = $income->amount;
            }

            foreach ($yearList as $year) {
                $incomeArr[$year] = [];

                for ($i = 1; $i <= 12; $i++) {
                    $incomeArr[$year][$i] = 0;
                }

                if (isset($revenueTotalArray[$year])) {
                    foreach ($revenueTotalArray[$year] as $month => $values) {
                        $incomeArr[$year][$month] = array_sum($values);
                    }
                }
            }


            $chartIncomeArr = Utility::totalData($invoiceArr, $incomeArr, $request ,$yearList);


            $data['chartIncomeArr'] = $chartIncomeArr;
            $data['incomeArr'] = $incomesum;
            $data['invoiceArray'] = $invoicesum;
            $data['account'] = $account;
            $data['customer'] = $customer;
            $data['category'] = $category;
            $filter['startDateRange'] = 'Jan-' . $year;
            $filter['endDateRange'] = 'Dec-' . $year;

            return view('report.income_summary', compact('filter', 'category'), $data);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function expenseSummary(Request $request)
    {
        if (\Auth::user()->can('expense report')) {
            $account = BankAccount::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('holder_name', 'id');
            $account->prepend('Select Account', '');
            $vender = Vender::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $vender->prepend('Select Vendor', '');
            $category = ProductServiceCategory::where('created_by', '=', \Auth::user()->creatorId())->where('type', '=', 'expense')->get()->pluck('name', 'id');
            $category->prepend('Select Category', '');

            if ($request->period === 'quarterly') {
                $month = [
                    'January-March',
                    'April-June',
                    'July-September',
                    'Octomber-December',
                ];
            } elseif ($request->period === 'half-yearly') {
                $month = [
                    'January-June',
                    'July-December',
                ];
            } elseif ($request->period === 'yearly') {
                $month = array_values(array_reverse($this->yearList()));
            } else {
                $month = $this->yearMonth();
            }
            $periods = Budget::$period;

            $data['monthList'] = $month;
            $data['yearList'] = $this->yearList();
            $data['periods'] = $periods;
            $filter['category'] = __('All');
            $filter['vender'] = __('All');

            if ($request->period === 'yearly') {
                $year = array_reverse($this->yearList());
                $yearList = [];
                foreach ($year as $value) {
                    $yearList[$value] = $value;
                }
                }
                else
                {
                $yearList[($request->year) ? $request->year : date('Y')] = ($request->year) ? $request->year : date('Y');
                }

            if (isset($request->year)) {
                $year = $request->year;
            } else {
                $year = date('Y');
            }
            $data['currentYear'] = $year;

            //   -----------------------------------------PAYMENT EXPENSE ------------------------------------------------------------
            $expenses = Payment::selectRaw('sum(payments.amount) as amount,MONTH(date) as month,YEAR(date) as year,product_service_categories.name as category_id')->leftjoin('product_service_categories', 'payments.category_id', '=', 'product_service_categories.id');
            $expenses->where('payments.created_by', '=', \Auth::user()->creatorId());
            if ($request->period != 'yearly') {
                $expenses->whereRAW('YEAR(date) =?', [$year]);
            }

            if (!empty($request->category)) {
                $expenses->where('category_id', '=', $request->category);
                $cat = ProductServiceCategory::find($request->category);
                $filter['category'] = !empty($cat) ? $cat->name : '';
            }
            if (!empty($request->vender)) {
                $expenses->where('vender_id', '=', $request->vender);

                $vend = Vender::find($request->vender);
                $filter['vender'] = !empty($vend) ? $vend->name : '';
            }

            $expenses->groupBy('month', 'year', 'category_id');
            $expenses = $expenses->get();

            $tmpArray = [];
            foreach ($expenses as $expense) {
                $tmpArray[$expense->category_id][$expense->year][$expense->month] = $expense->amount;
            }
            $array = [];

            foreach ($tmpArray as $key => $yearData) {
                $array[$key] = [];

                foreach ($yearList as $targetYear) {
                    $array[$key][$targetYear] = [];

                    for ($i = 1; $i <= 12; $i++) {
                        $array[$key][$targetYear][$i] = 0;
                    }

                    if (isset($yearData[$targetYear])) {
                        foreach ($yearData[$targetYear] as $month => $value) {
                            $array[$key][$targetYear][$month] = (float) $value; // Convert the value to float if needed
                        }
                    }
                }
            }

            //     ------------------------------------BILL EXPENSE----------------------------------------------------

            $bills = Bill::selectRaw('MONTH(send_date) as month,YEAR(send_date) as year,product_service_categories.name as category_id,bill_id, bills.id')
                ->leftjoin('product_service_categories', 'bills.category_id', '=', 'product_service_categories.id')
                ->where('bills.created_by', \Auth::user()->creatorId())->where('status', '!=', 0);
            $bills->whereRAW('YEAR(send_date) =?', [$year]);

            if ($request->period != 'yearly') {
                $bills->whereRAW('YEAR(send_date) =?', [$year]);
                    }

            if (!empty($request->vender)) {
                $bills->where('vender_id', '=', $request->vender);
            }

            if (!empty($request->category)) {
                $bills->where('category_id', '=', $request->category);
            }
            $bills = $bills->get();

            $billTmpArray = [];
            foreach ($bills as $bill) {
                $billTmpArray[$bill->category_id][$bill->year][$bill->month][] = $bill->getTotal();
            }

            $billArray = [];

            foreach ($billTmpArray as $key => $yearData) {
                $billArray[$key] = [];

                foreach ($yearList as $targetYear) {
                    $billArray[$key][$targetYear] = [];

                    for ($i = 1; $i <= 12; $i++) {
                        $billArray[$key][$targetYear][$i] = 0;
                    }

                    if (isset($yearData[$targetYear])) {
                        foreach ($yearData[$targetYear] as $month => $values) {
                            if (is_array($values)) {
                                $sum = array_sum($values);
                                $billArray[$key][$targetYear][$month] = $sum;
                            } else {
                                $billArray[$key][$targetYear][$month] = (float) $values;
                            }
                        }
                    }
                }
            }

            $billsum = Utility::billInvoiceData($billArray, $request , $yearList);

            $billTotalArray = [];
            foreach ($bills as $bill) {
                $billTotalArray[$bill->year][$bill->month][] = $bill->getTotal();
            }

            $expenseArr = [];
            $billArr = [];
            $expensesum = [];

        foreach ($yearList as $year) {
            $billArr[$year] = [];

            for ($i = 1; $i <= 12; $i++) {
                $billArr[$year][$i] = 0;
            }

            if (isset($billTotalArray[$year])) {
                foreach ($billTotalArray[$year] as $month => $values) {
                    $billArr[$year][$month] = array_sum($values);
                }
            }
        }


        foreach ($array as $key => $categoryData) {

            $expensesum[] = Utility::revenuePaymentData($key , $categoryData, $request ,$yearList);
        }

        $paymentTotalArray = [];

        foreach ($expenses as $expense) {
            $paymentTotalArray[$expense->year][$expense->month][] = $expense->amount;
        }

        foreach ($yearList as $year) {
            $expenseArr[$year] = [];

            for ($i = 1; $i <= 12; $i++) {
                $expenseArr[$year][$i] = 0;
            }

            if (isset($paymentTotalArray[$year])) {
                foreach ($paymentTotalArray[$year] as $month => $values) {
                    $expenseArr[$year][$month] = array_sum($values);
                }
            }
        }

            $chartExpenseArr = Utility::totalData($billArr, $expenseArr, $request , $yearList);

            $data['chartExpenseArr'] = $chartExpenseArr;
            $data['expenseArr'] = $expensesum;
            $data['billArray'] = $billsum;
            $data['account'] = $account;
            $data['vender'] = $vender;
            $data['category'] = $category;

            $filter['startDateRange'] = 'Jan-' . $year;
            $filter['endDateRange'] = 'Dec-' . $year;

            return view('report.expense_summary', compact('filter'), $data);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    public function incomeVsExpenseSummary(Request $request)
    {
        if (\Auth::user()->can('income vs expense report')) {
            $account = BankAccount::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('holder_name', 'id');
            $account->prepend('Select Account', '');
            $vender = Vender::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $vender->prepend('Select Vendor', '');
            $customer = Customer::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $customer->prepend('Select Customer', '');

            $category = ProductServiceCategory::where('created_by', '=', \Auth::user()->creatorId())->whereIn(
                'type', [
                    'income',
                    'expense',
                ]
            )->get()->pluck('name', 'id');
            $category->prepend('Select Category', '');

            if ($request->period === 'quarterly') {
                $month = [
                    'January-March',
                    'April-June',
                    'July-September',
                    'Octomber-December',
                ];
            } elseif ($request->period === 'half-yearly') {
                $month = [
                    'January-June',
                    'July-December',
                ];
            } elseif ($request->period === 'yearly') {
                $month = array_values(array_reverse($this->yearList()));

            } else {
                $month = $this->yearMonth();
            }

            $periods = Budget::$period;

            $data['monthList'] = $month;
            $data['yearList'] = $this->yearList();
            $data['periods'] = $periods;
            $filter['category'] = __('All');
            $filter['customer'] = __('All');
            $filter['vender'] = __('All');


            if ($request->period === 'yearly') {
                $year = array_reverse($this->yearList());
                $yearList = [];
                foreach ($year as $value) {
                    $yearList[$value] = $value;
                }
                }
                else
                {
                $yearList[($request->year) ? $request->year : date('Y')] = ($request->year) ? $request->year : date('Y');
                }

            if (isset($request->year)) {
                $year = $request->year;
            } else {
                $year = date('Y');
            }
            $data['currentYear'] = $year;

            // ------------------------------TOTAL PAYMENT EXPENSE-----------------------------------------------------------
            $expensesData = Payment::selectRaw('sum(payments.amount) as amount,MONTH(date) as month,YEAR(date) as year');
            $expensesData->where('payments.created_by', '=', \Auth::user()->creatorId());
            if ($request->period != 'yearly') {
                $expensesData->whereRAW('YEAR(date) =?', [$year]);
            }

            if (!empty($request->category)) {
                $expensesData->where('category_id', '=', $request->category);
                $cat = ProductServiceCategory::find($request->category);
                $filter['category'] = !empty($cat) ? $cat->name : '';

            }
            if (!empty($request->vender)) {
                $expensesData->where('vender_id', '=', $request->vender);

                $vend = Vender::find($request->vender);
                $filter['vender'] = !empty($vend) ? $vend->name : '';
            }
            $expensesData->groupBy('month', 'year');
            $expensesData = $expensesData->get();

            // ------------------------------TOTAL BILL EXPENSE-----------------------------------------------------------

            $bills = Bill::selectRaw('MONTH(send_date) as month,YEAR(send_date) as year,category_id,bill_id,id')->where('created_by', \Auth::user()->creatorId())->where('status', '!=', 0);
            if ($request->period != 'yearly') {
                $bills->whereRAW('YEAR(send_date) =?', [$year]);
            }

            if (!empty($request->vender)) {
                $bills->where('vender_id', '=', $request->vender);

            }

            if (!empty($request->category)) {
                $bills->where('category_id', '=', $request->category);
            }

            $bills = $bills->get();

            $paymentTotalArray = [];
            foreach ($expensesData as $expense) {
                $paymentTotalArray[$expense->year][$expense->month][] = $expense->amount;
            }
            $expenseArr = [];

            foreach ($yearList as $year) {
                $expenseArr[$year] = [];

                for ($i = 1; $i <= 12; $i++) {
                    $expenseArr[$year][$i] = 0;
                }

                if (isset($paymentTotalArray[$year])) {
                    foreach ($paymentTotalArray[$year] as $month => $values) {
                        $expenseArr[$year][$month] = array_sum($values);
                    }
                }
            }

            $billTotalArray = [];
            foreach ($bills as $bill) {
                $billTotalArray[$bill->year][$bill->month][] = $bill->getTotal();
            }

            $billArr = [];
            $expensesum = [];

            foreach ($yearList as $year) {
                $billArr[$year] = [];

                for ($i = 1; $i <= 12; $i++) {
                    $billArr[$year][$i] = 0;
                }

                if (isset($billTotalArray[$year])) {
                    foreach ($billTotalArray[$year] as $month => $values) {
                        $billArr[$year][$month] = array_sum($values);
                    }
                }
            }


            $billsum = Utility::totalSum($billArr, $request , $yearList);

            $expensesum = Utility::totalSum($expenseArr, $request , $yearList);

            $chartExpenseArr = Utility::totalData($billArr, $expenseArr, $request , $yearList);

            // ------------------------------TOTAL REVENUE INCOME-----------------------------------------------------------

            $incomesData = Revenue::selectRaw('sum(revenues.amount) as amount,MONTH(date) as month,YEAR(date) as year');
            $incomesData->where('revenues.created_by', '=', \Auth::user()->creatorId());
            if ($request->period != 'yearly') {
                $incomesData->whereRAW('YEAR(date) =?', [$year]);
            }

            if (!empty($request->category)) {
                $incomesData->where('category_id', '=', $request->category);
            }
            if (!empty($request->customer)) {
                $incomesData->where('customer_id', '=', $request->customer);
                $cust = Customer::find($request->customer);
                $filter['customer'] = !empty($cust) ? $cust->name : '';
            }
            $incomesData->groupBy('month', 'year');
            $incomesData = $incomesData->get();

            // ------------------------------TOTAL INVOICE INCOME-----------------------------------------------------------
            $invoices = Invoice::selectRaw('MONTH(send_date) as month,YEAR(send_date) as year,category_id,invoice_id,id')
                ->where('created_by', \Auth::user()->creatorId())->where('status', '!=', 0);
                if ($request->period != 'yearly') {
                    $invoices->whereRAW('YEAR(send_date) =?', [$year]);
                }
            if (!empty($request->customer)) {
                $invoices->where('customer_id', '=', $request->customer);
            }
            if (!empty($request->category)) {
                $invoices->where('category_id', '=', $request->category);
            }
            $invoices = $invoices->get();

            $revenueTotalArray = [];
            foreach ($incomesData as $income) {
                $revenueTotalArray[$income->year][$income->month][] = $income->amount;
            }

            $incomeArr = [];

            foreach ($yearList as $year) {
                $incomeArr[$year] = [];

                for ($i = 1; $i <= 12; $i++) {
                    $incomeArr[$year][$i] = 0;
                }

                if (isset($revenueTotalArray[$year])) {
                    foreach ($revenueTotalArray[$year] as $month => $values) {
                        $incomeArr[$year][$month] = array_sum($values);
                    }
                }
            }

            $invoiceTotalArray = [];
            foreach ($invoices as $invoice) {
                $invoiceTotalArray[$invoice->year][$invoice->month][] = $invoice->getTotal();
            }

            $invoiceArr = [];
            $incomesum = [];

            foreach ($yearList as $year) {
                $invoiceArr[$year] = [];

                for ($i = 1; $i <= 12; $i++) {
                    $invoiceArr[$year][$i] = 0;
                }

                if (isset($invoiceTotalArray[$year])) {
                    foreach ($invoiceTotalArray[$year] as $month => $values) {
                        $invoiceArr[$year][$month] = array_sum($values);
                    }
                }
            }

            $invoicesum = Utility::totalSum($invoiceArr, $request , $yearList);

            $incomesum = Utility::totalSum($incomeArr, $request , $yearList);

            $chartIncomeArr = Utility::totalData($invoiceArr, $incomeArr, $request , $yearList);



            $profit = [];

                if (count($chartIncomeArr) === count($chartExpenseArr) && count($chartIncomeArr[0]) === count($chartExpenseArr[0])) {
                    foreach ($chartIncomeArr as $i => $values1) {
                        foreach ($values1 as $j => $value1) {
                            $profit[$i][$j] = $value1 - $chartExpenseArr[$i][$j];
                        }
                    }
                }


            $data['paymentExpenseTotal'] = $expensesum;
            $data['billExpenseTotal'] = $billsum;
            $data['revenueIncomeTotal'] = $incomesum;
            $data['invoiceIncomeTotal'] = $invoicesum;
            $data['profit'] = $profit;
            $data['account'] = $account;
            $data['vender'] = $vender;
            $data['customer'] = $customer;
            $data['category'] = $category;

            $filter['startDateRange'] = 'Jan-' . $year;
            $filter['endDateRange'] = 'Dec-' . $year;

            return view('report.income_vs_expense_summary', compact('filter'), $data);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function taxSummary(Request $request)
    {

        if (\Auth::user()->can('tax report')) {
            $data['monthList'] = $month = $this->yearMonth();
            $data['yearList'] = $this->yearList();
            $data['taxList'] = $taxList = Tax::where('created_by', \Auth::user()->creatorId())->get();

            if (isset($request->year)) {
                $year = $request->year;
            } else {
                $year = date('Y');
            }

            $data['currentYear'] = $year;

            $invoiceProducts = InvoiceProduct::selectRaw('invoice_products.* ,MONTH(invoice_products.created_at) as month,YEAR(invoice_products.created_at) as year')->leftjoin('product_services', 'invoice_products.product_id', '=', 'product_services.id')->whereRaw('YEAR(invoice_products.created_at) =?', [$year])->where('product_services.created_by', '=', \Auth::user()->creatorId())->get();

            $incomeTaxesData = [];

            foreach ($invoiceProducts as $invoiceProduct) {
                $incomeTax = [];
                $getTaxData = Utility::getTaxData();

                if ($invoiceProduct->tax != null) {
                    foreach (explode(',', $invoiceProduct->tax) as $tax) {
                        $taxPrice = \Utility::taxRate($getTaxData[$tax]['rate'], $invoiceProduct->price, $invoiceProduct->quantity);

                        $itemName = $getTaxData[$tax]['name'];
                        $itemTax['name'] = $itemName;
                        $itemTax['rate'] = $getTaxData[$tax]['rate'] . '%';
                        $itemTax['price'] = ($taxPrice);

                        if (!isset($incomeTax[$itemName])) {
                            $incomeTax[$itemName] = 0;
                        }

                        $incomeTax[$itemName] += $itemTax['price'];
                    }
                }
                $incomeTaxesData[$invoiceProduct->month][] = $incomeTax;
            }

            $income = [];
            foreach ($incomeTaxesData as $month => $incomeTaxx) {
                $incomeTaxRecord = [];
                foreach ($incomeTaxx as $k => $record) {
                    foreach ($record as $incomeTaxName => $incomeTaxAmount) {
                        if (array_key_exists($incomeTaxName, $incomeTaxRecord)) {
                            $incomeTaxRecord[$incomeTaxName] += $incomeTaxAmount;
                        } else {
                            $incomeTaxRecord[$incomeTaxName] = $incomeTaxAmount;
                        }
                    }
                    $income['data'][$month] = $incomeTaxRecord;
                }

            }

            foreach ($income as $incomeMonth => $incomeTaxData) {
                $incomeData = [];
                for ($i = 1; $i <= 12; $i++) {
                    $incomeData[$i] = array_key_exists($i, $incomeTaxData) ? $incomeTaxData[$i] : 0;
                }

            }

            $incomes = [];
            if (isset($incomeData) && !empty($incomeData)) {
                foreach ($taxList as $taxArr) {
                    foreach ($incomeData as $month => $tax) {
                        if ($tax != 0) {
                            if (isset($tax[$taxArr->name])) {
                                $incomes[$taxArr->name][$month] = $tax[$taxArr->name];
                            } else {
                                $incomes[$taxArr->name][$month] = 0;
                            }
                        } else {
                            $incomes[$taxArr->name][$month] = 0;
                        }
                    }
                }
            }

            $billProducts = BillProduct::selectRaw('bill_products.* ,MONTH(bill_products.created_at) as month,YEAR(bill_products.created_at) as year')->leftjoin('product_services', 'bill_products.product_id', '=', 'product_services.id')->whereRaw('YEAR(bill_products.created_at) =?', [$year])->where('product_services.created_by', '=', \Auth::user()->creatorId())->get();

            $expenseTaxesData = [];
            foreach ($billProducts as $billProduct) {
                $billTax = [];

                $getTaxData = Utility::getTaxData();
                $taxesData = [];

                if ($billProduct->tax != null) {

                    foreach (explode(',', $billProduct->tax) as $tax) {
                        $taxPrice = \Utility::taxRate($getTaxData[$tax]['rate'], $billProduct->price, $billProduct->quantity);
                        $itemName = $getTaxData[$tax]['name'];
                        $itemTax['name'] = $itemName;
                        $itemTax['rate'] = $getTaxData[$tax]['rate'] . '%';
                        $itemTax['price'] = ($taxPrice);

                        if (!isset($billTax[$itemName])) {
                            $billTax[$itemName] = 0;
                        }
                        $billTax[$itemName] += $itemTax['price'];
                    }
                }
                $expenseTaxesData[$billProduct->month][] = $billTax;
            }

            $bill = [];
            foreach ($expenseTaxesData as $month => $billTaxx) {
                $billTaxRecord = [];
                foreach ($billTaxx as $k => $record) {
                    foreach ($record as $billTaxName => $billTaxAmount) {
                        if (array_key_exists($billTaxName, $billTaxRecord)) {
                            $billTaxRecord[$billTaxName] += $billTaxAmount;
                        } else {
                            $billTaxRecord[$billTaxName] = $billTaxAmount;
                        }
                    }
                    $bill['data'][$month] = $billTaxRecord;
                }

            }

            foreach ($bill as $billMonth => $billTaxData) {
                $billData = [];
                for ($i = 1; $i <= 12; $i++) {
                    $billData[$i] = array_key_exists($i, $billTaxData) ? $billTaxData[$i] : 0;
                }

            }
            $expenses = [];
            if (isset($billData) && !empty($billData)) {

                foreach ($taxList as $taxArr) {
                    foreach ($billData as $month => $tax) {
                        if ($tax != 0) {
                            if (isset($tax[$taxArr->name])) {
                                $expenses[$taxArr->name][$month] = $tax[$taxArr->name];
                            } else {
                                $expenses[$taxArr->name][$month] = 0;
                            }
                        } else {
                            $expenses[$taxArr->name][$month] = 0;
                        }
                    }

                }
            }

            $data['expenses'] = $expenses;
            $data['incomes'] = $incomes;

            $filter['startDateRange'] = 'Jan-' . $year;
            $filter['endDateRange'] = 'Dec-' . $year;

            return view('report.tax_summary', compact('filter'), $data);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    public function yearMonth()
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

        return $month;
    }

    public function yearList()
    {
        $starting_year = date('Y', strtotime('-5 year'));
        $ending_year = date('Y');

        foreach (range($ending_year, $starting_year) as $year) {
            $years[$year] = $year;
        }

        return $years;
    }

    public function invoiceSummary(Request $request)
    {

        if (\Auth::user()->can('invoice report')) {
            $filter['customer'] = __('All');
            $filter['status'] = __('All');

            $customer = Customer::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $customer->prepend('Select Customer', '');
            $status = Invoice::$statues;

            $invoices = Invoice::selectRaw('invoices.*,MONTH(send_date) as month,YEAR(send_date) as year');

            if ($request->status != '') {
                $invoices->where('status', $request->status);

                $filter['status'] = Invoice::$statues[$request->status];
            } else {
                $invoices->where('status', '!=', 0);
            }

            $invoices->where('created_by', '=', \Auth::user()->creatorId());

            if (!empty($request->start_month) && !empty($request->end_month)) {
                $start = strtotime($request->start_month);
                $end = strtotime($request->end_month);
            } else {
                $start = strtotime(date('Y-01'));
                $end = strtotime(date('Y-12'));
            }

            $invoices->where('send_date', '>=', date('Y-m-01', $start))->where('send_date', '<=', date('Y-m-t', $end));

            $filter['startDateRange'] = date('M-Y', $start);
            $filter['endDateRange'] = date('M-Y', $end);

            if (!empty($request->customer)) {
                $invoices->where('customer_id', $request->customer);
                $cust = Customer::find($request->customer);

                $filter['customer'] = !empty($cust) ? $cust->name : '';
            }

            $invoices = $invoices->with(['customer', 'category'])->get();

            $totalInvoice = 0;
            $totalDueInvoice = 0;
            $invoiceTotalArray = [];
            foreach ($invoices as $invoice) {
                $totalInvoice += $invoice->getTotal();
                $totalDueInvoice += $invoice->getDue();

                $invoiceTotalArray[$invoice->month][] = $invoice->getTotal();
            }
            $totalPaidInvoice = $totalInvoice - $totalDueInvoice;

            for ($i = 1; $i <= 12; $i++) {
                $invoiceTotal[] = array_key_exists($i, $invoiceTotalArray) ? array_sum($invoiceTotalArray[$i]) : 0;
            }

            $monthList = $month = $this->yearMonth();

            return view('report.invoice_report', compact('invoices', 'customer', 'status', 'totalInvoice', 'totalDueInvoice', 'totalPaidInvoice', 'invoiceTotal', 'monthList', 'filter'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function billSummary(Request $request)
    {
//        dd($request->all());
        if (\Auth::user()->can('bill report')) {

            $filter['vender'] = __('All');
            $filter['status'] = __('All');

            $vender = Vender::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $vender->prepend('Select Vendor', '');
            $status = Bill::$statues;

            $bills = Bill::selectRaw('bills.*,MONTH(send_date) as month,YEAR(send_date) as year');

            if ($request->status != '') {
                $bills->where('status', '=', $request->status);

                $filter['status'] = Bill::$statues[$request->status];
            } else {
                $bills->where('status', '!=', 0);
            }

            $bills->where('created_by', '=', \Auth::user()->creatorId());

            if (!empty($request->start_month) && !empty($request->end_month)) {
                $start = strtotime($request->start_month);
                $end = strtotime($request->end_month);
            } else {
                $start = strtotime(date('Y-01'));
                $end = strtotime(date('Y-12'));
            }

            $bills->where('send_date', '>=', date('Y-m-01', $start))->where('send_date', '<=', date('Y-m-t', $end));

            $filter['startDateRange'] = date('M-Y', $start);
            $filter['endDateRange'] = date('M-Y', $end);

            if (!empty($request->vender)) {
                $bills->where('vender_id', $request->vender);
                $vend = Vender::find($request->vender);

                $filter['vender'] = !empty($vend) ? $vend->name : '';
            }

            $bills = $bills->with(['vender', 'category'])->get();

            $totalBill = 0;
            $totalDueBill = 0;
            $billTotalArray = [];
            foreach ($bills as $bill) {
                $totalBill += $bill->getTotal();
                $totalDueBill += $bill->getDue();

                $billTotalArray[$bill->month][] = $bill->getTotal();
            }
            $totalPaidBill = $totalBill - $totalDueBill;

            for ($i = 1; $i <= 12; $i++) {
                $billTotal[] = array_key_exists($i, $billTotalArray) ? array_sum($billTotalArray[$i]) : 0;
            }

            $monthList = $month = $this->yearMonth();

            return view('report.bill_report', compact('bills', 'vender', 'status', 'totalBill', 'totalDueBill', 'totalPaidBill', 'billTotal', 'monthList', 'filter'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function accountStatement(Request $request)
    {
        if (\Auth::user()->can('statement report')) {

            $filter['account'] = __('All');
            $filter['type'] = __('Revenue');
            $reportData['revenues'] = '';
            $reportData['payments'] = '';
            $reportData['revenueAccounts'] = '';
            $reportData['paymentAccounts'] = '';

            $account = BankAccount::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('holder_name', 'id');
            $account->prepend('Select Account', '');

            $types = [
                'revenue' => __('Revenue'),
                'payment' => __('Payment'),
            ];

            if ($request->type == 'revenue' || !isset($request->type)) {

                $revenueAccounts = Revenue::select('bank_accounts.id', 'bank_accounts.holder_name', 'bank_accounts.bank_name')->leftjoin('bank_accounts', 'revenues.account_id', '=', 'bank_accounts.id')->groupBy('revenues.account_id')->selectRaw('sum(amount) as total')->where('revenues.created_by', '=', \Auth::user()->creatorId());

                $revenues = Revenue::where('revenues.created_by', '=', \Auth::user()->creatorId())->orderBy('id', 'desc');
            }

            if ($request->type == 'payment') {
                $paymentAccounts = Payment::select('bank_accounts.id', 'bank_accounts.holder_name', 'bank_accounts.bank_name')->leftjoin('bank_accounts', 'payments.account_id', '=', 'bank_accounts.id')->groupBy('payments.account_id')->selectRaw('sum(amount) as total')->where('payments.created_by', '=', \Auth::user()->creatorId());

                $payments = Payment::where('payments.created_by', '=', \Auth::user()->creatorId())->orderBy('id', 'desc');
            }

            if (!empty($request->start_month) && !empty($request->end_month)) {
                $start = strtotime($request->start_month);
                $end = strtotime($request->end_month);
            } else {
                $start = strtotime(date('Y-m'));
                $end = strtotime(date('Y-m', strtotime("-5 month")));
            }

            $currentdate = $start;
            while ($currentdate <= $end) {
                $data['month'] = date('m', $currentdate);
                $data['year'] = date('Y', $currentdate);

                if ($request->type == 'revenue' || !isset($request->type)) {
                    $revenues->Orwhere(
                        function ($query) use ($data) {
                            $query->whereMonth('date', $data['month'])->whereYear('date', $data['year']);
                            $query->where('revenues.created_by', '=', \Auth::user()->creatorId());
                        }
                    );

                    $revenueAccounts->Orwhere(
                        function ($query) use ($data) {
                            $query->whereMonth('date', $data['month'])->whereYear('date', $data['year']);
                            $query->where('revenues.created_by', '=', \Auth::user()->creatorId());
                        }
                    );
                }

                if ($request->type == 'payment') {
                    $paymentAccounts->Orwhere(
                        function ($query) use ($data) {
                            $query->whereMonth('date', $data['month'])->whereYear('date', $data['year']);
                            $query->where('payments.created_by', '=', \Auth::user()->creatorId());
                        }
                    );
                }

                $currentdate = strtotime('+1 month', $currentdate);
            }

            if (!empty($request->account)) {
                if ($request->type == 'revenue' || !isset($request->type)) {
                    $revenues->where('account_id', $request->account);
                    $revenues->where('revenues.created_by', '=', \Auth::user()->creatorId());
                    $revenueAccounts->where('account_id', $request->account);
                    $revenueAccounts->where('revenues.created_by', '=', \Auth::user()->creatorId());
                }

                if ($request->type == 'payment') {
                    $payments->where('account_id', $request->account);
                    $payments->where('payments.created_by', '=', \Auth::user()->creatorId());

                    $paymentAccounts->where('account_id', $request->account);
                    $paymentAccounts->where('payments.created_by', '=', \Auth::user()->creatorId());
                }

                $bankAccount = BankAccount::find($request->account);
                $filter['account'] = !empty($bankAccount) ? $bankAccount->holder_name . ' - ' . $bankAccount->bank_name : '';
                if ($bankAccount->holder_name == 'Cash') {
                    $filter['account'] = 'Cash';
                }

            }

            if ($request->type == 'revenue' || !isset($request->type)) {
                $reportData['revenues'] = $revenues->get();

                $revenueAccounts->where('revenues.created_by', '=', \Auth::user()->creatorId());
                $reportData['revenueAccounts'] = $revenueAccounts->get();

            }

            if ($request->type == 'payment') {
                $reportData['payments'] = $payments->get();

                $paymentAccounts->where('payments.created_by', '=', \Auth::user()->creatorId());
                $reportData['paymentAccounts'] = $paymentAccounts->get();
                $filter['type'] = __('Payment');
            }

            $filter['startDateRange'] = date('M-Y', $start);
            $filter['endDateRange'] = date('M-Y', $end);

            return view('report.statement_report', compact('reportData', 'account', 'types', 'filter'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    // public function balanceSheet(Request $request, $view = '')
    // {
    //     if (\Auth::user()->can('bill report')) {
    //         if (!empty($request->start_date) && !empty($request->end_date)) {
    //             $start = $request->start_date;
    //             $end = $request->end_date;
    //         } else {
    //             $start = date('Y-01-01');
    //             $end = date('Y-m-d', strtotime('+1 day'));
    //         }
    //         $types = ChartOfAccountType::where('created_by', \Auth::user()->creatorId())->whereIn('name', ['Assets', 'Liabilities', 'Equity'])->get();
    //         $totalAccounts = [];
    //         foreach ($types as $type) {
    //             $subTypes = ChartOfAccountSubType::where('type', $type->id)->get();
    //             $subTypeArray = [];
    //             foreach ($subTypes as $subType) {
    //                 $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
    //                 $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
    //                 $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
    //                 $accounts->where('chart_of_accounts.type', $type->id);
    //                 $accounts->where('chart_of_accounts.sub_type', $subType->id);
    //                 $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
    //                 $accounts->where('transaction_lines.date', '>=', $start);
    //                 $accounts->where('transaction_lines.date', '<=', $end);
    //                 $accounts->groupBy('account_id');
    //                 $accounts = $accounts->get()->toArray();
    //                 $totalBalance = 0;
    //                 $creditTotal = 0;
    //                 $debitTotal = 0;
    //                 $totalAmount = 0;
    //                 $accountArray = [];
    //                 foreach ($accounts as $account) {
    //                     $Balance = $account['totalCredit'] - $account['totalDebit'];
    //                     $totalBalance += $Balance;
    //                     if ($Balance != 0) {
    //                         $data['account_id'] = $account['id'];
    //                         $data['account_code'] = $account['code'];
    //                         $data['account_name'] = $account['name'];
    //                         $data['totalCredit'] = 0;
    //                         $data['totalDebit'] = 0;
    //                         $data['netAmount'] = $Balance;
    //                         $accountArray[] = $data;
    //                         $creditTotal += $data['totalCredit'];
    //                         $debitTotal += $data['totalDebit'];
    //                         $totalAmount += $data['netAmount'];
    //                     }
    //                 }
    //                 $totalAccountArray = [];
    //                 if ($accountArray != []) {
    //                     $dataTotal['account_id'] = '';
    //                     $dataTotal['account_code'] = '';
    //                     $dataTotal['account_name'] = 'Total ' . $subType->name;
    //                     $dataTotal['totalCredit'] = $creditTotal;
    //                     $dataTotal['totalDebit'] = $debitTotal;
    //                     $dataTotal['netAmount'] = $totalAmount;
    //                     $accountArrayTotal[] = $dataTotal;
    //                     $totalAccountArray = array_merge($accountArray, $accountArrayTotal);
    //                 }
    //                 if ($totalAccountArray != []) {
    //                     $subTypeData['subType'] = ($totalAccountArray != []) ? $subType->name : '';
    //                     $subTypeData['account'] = $totalAccountArray;
    //                     $subTypeArray[] = ($subTypeData['account'] != [] && $subTypeData['subType'] != []) ? $subTypeData : [];
    //                 }
    //             }
    //             $totalAccounts[$type->name] = $subTypeArray;
    //         }
    //         $filter['startDateRange'] = $start;
    //         $filter['endDateRange'] = $end;
    //         if ($request->view == 'horizontal' || $view == 'horizontal') {
    //             return view('report.balance_sheet_horizontal', compact('filter', 'totalAccounts'));
    //         } elseif ($view == '' || $view == 'vertical') {
    //             return view('report.balance_sheet', compact('filter', 'totalAccounts'));
    //         } else {
    //             return redirect()->back();
    //         }
    //     } else {
    //         return redirect()->back()->with('error', __('Permission Denied.'));
    //     }
    // }

    public function balanceSheet(Request $request, $view = '', $collapseview = 'expand')
    {
        if (\Auth::user()->can('bill report')) {
            if (!empty($request->start_date) && !empty($request->end_date)) {
                $start = $request->start_date;
                $end = $request->end_date;
            } else {
                $start = date('Y-01-01');
                $end = date('Y-m-d', strtotime('+1 day'));
            }
            $types = ChartOfAccountType::where('created_by', \Auth::user()->creatorId())->whereIn('name', ['Assets', 'Liabilities', 'Equity'])->get();
            $totalAccounts = [];
            foreach ($types as $type) {
                $subTypes = ChartOfAccountSubType::where('type', $type->id)->get();

                $subTypeArray = [];
                foreach ($subTypes as $subType) {
                    $parentAccounts = ChartOfAccountParent::where('sub_type', $subType->id)->get();
                    // $parentAccountArray = [];
                    // $parentAccountArrayTotal = [];

                    $totalParentAccountArray = [];
                    if ($parentAccounts->isNotEmpty()) {
                        foreach ($parentAccounts as $parentAccount) {
                            $totalArray = [];
                            $parentAccountArray = [];
                            $parentAccountArrayTotal = [];

                            $parentAccs = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                            $parentAccs->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                            $parentAccs->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                            $parentAccs->where('chart_of_accounts.type', $type->id);
                            $parentAccs->where('chart_of_accounts.sub_type', $subType->id);
                            $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                            $parentAccs->where('transaction_lines.created_by', \Auth::user()->creatorId());
                            $parentAccs->where('transaction_lines.date', '>=', $start);
                            $parentAccs->where('transaction_lines.date', '<=', $end);
                            $parentAccs->groupBy('account_id');
                            $parentAccs = $parentAccs->get()->toArray();

                            $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                            $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                            $accounts->where('chart_of_accounts.type', $type->id);
                            $accounts->where('chart_of_accounts.sub_type', $subType->id);
                            $accounts->where('chart_of_accounts.parent', $parentAccount->id);
                            $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                            $accounts->where('transaction_lines.date', '>=', $start);
                            $accounts->where('transaction_lines.date', '<=', $end);
                            $accounts->groupBy('account_id');
                            $accounts = $accounts->get()->toArray();

                            if ($parentAccs == [] && $accounts != []) {

                                $parentAccs = ChartOfAccount::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('0 as totalDebit'), \DB::raw('0 as totalCredit'));
                                $parentAccs->leftjoin('chart_of_account_parents', 'chart_of_accounts.id', 'chart_of_account_parents.account');
                                $parentAccs->where('chart_of_accounts.type', $type->id);
                                $parentAccs->where('chart_of_accounts.sub_type', $subType->id);
                                $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                                $parentAccs->where('chart_of_accounts.created_by', \Auth::user()->creatorId());
                                $parentAccs = $parentAccs->get()->toArray();
                            }

                            if ($parentAccs != [] && $accounts == []) {

                                $parentAccs = [];
                            }

                            $parenttotalBalance = 0;
                            $parentcreditTotal = 0;
                            $parenntdebitTotal = 0;
                            $parenttotalAmount = 0;

                            foreach ($parentAccs as $account) {
                                $Balance = $account['totalCredit'] - $account['totalDebit'];
                                $parenttotalBalance += $Balance;

                                $data = [
                                    'account_id' => $account['id'],
                                    'account_code' => $account['code'],
                                    'account_name' => $account['name'],
                                    'account' => 'parent',
                                    'totalCredit' => 0,
                                    'totalDebit' => 0,
                                    'netAmount' => $Balance,
                                ];

                                $parentAccountArray[] = $data;
                                $parentcreditTotal += $data['totalCredit'];
                                $parenntdebitTotal += $data['totalDebit'];
                                $parenttotalAmount += $data['netAmount'];
                            }

                            foreach ($accounts as $account) {
                                $Balance = $account['totalCredit'] - $account['totalDebit'];
                                $parenttotalBalance += $Balance;

                                if ($Balance != 0) {
                                    $data = [
                                        'account_id' => $account['id'],
                                        'account_code' => $account['code'],
                                        'account_name' => $account['name'],
                                        'account' => 'subAccount',
                                        'totalCredit' => 0,
                                        'totalDebit' => 0,
                                        'netAmount' => $Balance,
                                    ];

                                    $parentAccountArray[] = $data;
                                    $parentcreditTotal += $data['totalCredit'];
                                    $parenntdebitTotal += $data['totalDebit'];
                                    $parenttotalAmount += $data['netAmount'];
                                }
                            }

                            if (!empty($parentAccountArray)) {
                                $dataTotal = [
                                    'account_id' => $parentAccount->account,
                                    'account_code' => '',
                                    'account' => 'parentTotal',
                                    'account_name' => 'Total ' . $parentAccount->name,
                                    'totalCredit' => $parentcreditTotal,
                                    'totalDebit' => $parenntdebitTotal,
                                    'netAmount' => $parenttotalAmount,
                                ];

                                $parentAccountArrayTotal[] = $dataTotal;
                                $totalArray = array_merge($parentAccountArray, $parentAccountArrayTotal);
                                $totalParentAccountArray[] = $totalArray;
                            }

                        }

                    }
                    if ($totalParentAccountArray != []) {
                        $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                        $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                        $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                        $accounts->leftjoin('chart_of_account_parents', 'chart_of_accounts.name', 'chart_of_account_parents.name');
                        $accounts->where('chart_of_accounts.type', $type->id);
                        $accounts->where('chart_of_accounts.sub_type', $subType->id);
                        $accounts->where('chart_of_account_parents.account');
                        $accounts->where('chart_of_accounts.parent', '=', 'chart_of_account_parents.id');
                        $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                        $accounts->where('transaction_lines.date', '>=', $start);
                        $accounts->where('transaction_lines.date', '<=', $end);
                        $accounts->groupBy('account_id');
                        $accounts = $accounts->get()->toArray();
                    } else {
                        $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                        $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                        $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                        $accounts->where('chart_of_accounts.type', $type->id);
                        $accounts->where('chart_of_accounts.sub_type', $subType->id);
                        $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                        $accounts->where('transaction_lines.date', '>=', $start);
                        $accounts->where('transaction_lines.date', '<=', $end);
                        $accounts->groupBy('account_id');
                        $accounts = $accounts->get()->toArray();
                    }

                    $totalBalance = 0;
                    $creditTotal = 0;
                    $debitTotal = 0;
                    $totalAmount = 0;
                    $accountArray = [];

                    foreach ($accounts as $account) {
                        $Balance = $account['totalCredit'] - $account['totalDebit'];
                        $totalBalance += $Balance;
                        if ($Balance != 0) {
                            $data['account_id'] = $account['id'];
                            $data['account_code'] = $account['code'];
                            $data['account_name'] = $account['name'];
                            $data['account'] = '';
                            $data['totalCredit'] = 0;
                            $data['totalDebit'] = 0;
                            $data['netAmount'] = $Balance;
                            $accountArray[][] = $data;
                            $creditTotal += $data['totalCredit'];
                            $debitTotal += $data['totalDebit'];
                            $totalAmount += $data['netAmount'];
                        }
                    }

                    $totalAccountArray = [];
                    if ($accountArray != []) {
                        $dataTotal['account_id'] = '';
                        $dataTotal['account_code'] = '';
                        $dataTotal['account'] = '';
                        $dataTotal['account_name'] = 'Total ' . $subType->name;
                        $dataTotal['totalCredit'] = $creditTotal;
                        $dataTotal['totalDebit'] = $debitTotal;

                        if (isset($totalParentAccountArray) && $totalParentAccountArray != []) {

                            $netAmount = 0;

                            foreach ($totalParentAccountArray as $innerArray) {
                                $lastElement = end($innerArray);

                                $netAmount += $lastElement['netAmount'];
                            }

                            $dataTotal['netAmount'] = $netAmount + $totalAmount;
                        } else {
                            $dataTotal['netAmount'] = $totalAmount;
                        }
                        $accountArrayTotal[][] = $dataTotal;
                        $totalAccountArray = array_merge($totalParentAccountArray, $accountArray, $accountArrayTotal);

                    } elseif ($totalParentAccountArray != []) {
                        $dataTotal['account_id'] = '';
                        $dataTotal['account_code'] = '';
                        $dataTotal['account'] = '';
                        $dataTotal['account_name'] = 'Total ' . $subType->name;
                        $dataTotal['totalCredit'] = $creditTotal;
                        $dataTotal['totalDebit'] = $debitTotal;
                        $netAmount = 0;

                        foreach ($totalParentAccountArray as $innerArray) {
                            $lastElement = end($innerArray);

                            $netAmount += $lastElement['netAmount'];
                        }
                        $dataTotal['netAmount'] = $netAmount;
                        $accountArrayTotal[][] = $dataTotal;
                        $totalAccountArray = array_merge($totalParentAccountArray, $accountArrayTotal);
                    }

                    if ($totalAccountArray != []) {
                        $subTypeData['subType'] = ($totalAccountArray != []) ? $subType->name : '';
                        $subTypeData['account'] = $totalAccountArray;
                        $subTypeArray[] = ($subTypeData['account'] != [] && $subTypeData['subType'] != []) ? $subTypeData : [];
                    }
                }
                $totalAccounts[$type->name] = $subTypeArray;
            }

            $filter['startDateRange'] = $start;
            $filter['endDateRange'] = $end;

            if ($request->view == 'horizontal' || $view == 'horizontal') {
                return view('report.balance_sheet_horizontal', compact('filter', 'totalAccounts', 'collapseview'));
            } elseif ($view == '' || $view == 'vertical') {
                return view('report.balance_sheet', compact('filter', 'totalAccounts', 'collapseview'));
            } else {
                return redirect()->back();
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    // public function ledgerSummary(Request $request, $account = '')
    // {
    //     if (\Auth::user()->can('ledger report')) {
    //         $accounts = ChartOfAccount::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
    //         $accounts->prepend('All', '');

    //         if (!empty($request->start_date) && !empty($request->end_date)) {
    //             $start = $request->start_date;
    //             $end = $request->end_date;
    //         } else {
    //             $start = date('Y-01-01');
    //             $end = date('Y-m-d', strtotime('+1 day'));
    //         }

    //         if (!empty($request->account)) {
    //             $accountss = ChartOfAccount::where('id', $request->account)->get();
    //         } else {
    //             $accountss = ChartOfAccount::where('created_by', \Auth::user()->creatorId())->get();
    //         }

    //         $balance = 0;
    //         $debit = 0;
    //         $credit = 0;

    //         // foreach($journalItems as $item)
    //         // {
    //         //     if($item->debit > 0)
    //         //     {
    //         //         $debit += $item->debit;
    //         //     }

    //         //     else
    //         //     {
    //         //         $credit += $item->credit;
    //         //     }

    //         //     $balance = $credit - $debit;
    //         // }

    //         $filter['balance'] = $balance;
    //         $filter['credit'] = $credit;
    //         $filter['debit'] = $debit;
    //         $filter['startDateRange'] = $start;
    //         $filter['endDateRange'] = $end;
    //         return view('report.ledger_summary', compact('filter', 'accountss', 'accounts'));
    //     } else {
    //         return redirect()->back()->with('error', __('Permission Denied.'));
    //     }
    // }

    public function ledgerSummary(Request $request, $account = '')
    {

        if (\Auth::user()->can('ledger report')) {

            if (!empty($request->start_date) && !empty($request->end_date)) {
                $start = $request->start_date;
                $end = $request->end_date;
            } else {
                $start = date('Y-m-01');
                $end = date('Y-m-t');
            }
            if (!empty($request->account)) {
                $chart_accounts = ChartOfAccount::where('id', $request->account)->where('created_by', \Auth::user()->creatorId())->get();
                // $accounts = ChartOfAccount::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
                $accounts = ChartOfAccount::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.parent')
                ->where('parent', '=', 0)
                ->where('created_by', \Auth::user()->creatorId())->get()
                ->toarray();

            } else {
                $chart_accounts = ChartOfAccount::where('created_by', \Auth::user()->creatorId())->get();
                // $accounts = $chart_accounts->pluck('name', 'id');
                $accounts = ChartOfAccount::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.parent')
                ->where('parent', '=', 0)
                ->where('created_by', \Auth::user()->creatorId())->get()
                ->toarray();
            }

            $subAccounts = ChartOfAccount::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_account_parents.account');
            $subAccounts->leftjoin('chart_of_account_parents', 'chart_of_accounts.parent', 'chart_of_account_parents.id');
            $subAccounts->where('chart_of_accounts.parent', '!=', 0);
            $subAccounts->where('chart_of_accounts.created_by', \Auth::user()->creatorId());
            $subAccounts = $subAccounts->get()->toArray();

            $balance = 0;
            $debit = 0;
            $credit = 0;
            $filter['balance'] = $balance;
            $filter['credit'] = $credit;
            $filter['debit'] = $debit;
            $filter['startDateRange'] = $start;
            $filter['endDateRange'] = $end;
            return view('report.ledger_summary', compact('filter', 'chart_accounts', 'accounts', 'subAccounts'));

        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    // public function trialBalanceSummary(Request $request)
    // {
    //     if (\Auth::user()->can('trial balance report')) {
    //         if (!empty($request->start_date) && !empty($request->end_date)) {
    //             $start = $request->start_date;
    //             $end = $request->end_date;
    //         } else {
    //             $start = date('Y-01-01');
    //             $end = date('Y-m-d', strtotime('+1 day'));
    //         }

    //         $types = ChartOfAccountType::where('created_by', \Auth::user()->creatorId())->get();
    //         $chartAccounts = [];
    //         $totalAccounts = [];
    //         $totalAccount = [];

    //         foreach ($types as $type) {
    //             $total = Utility::trialBalance($type->id, $start, $end);
    //             $name = $type->name;
    //             if (isset($totalAccount[$name])) {
    //                 $totalAccount[$name]["totalCredit"] += $total["totalCredit"];
    //                 $totalAccount[$name]["totalDebit"] += $total["totalDebit"];
    //             } else {
    //                 $totalAccount[$name] = $total;
    //             }
    //         }
    //         foreach ($totalAccount as $category => $entries) {
    //             foreach ($entries as $entry) {
    //                 $name = $entry['name'];
    //                 if (!isset($totalAccounts[$category][$name])) {
    //                     $totalAccounts[$category][$name] = [
    //                         'id' => $entry['id'],
    //                         'code' => $entry['code'],
    //                         'name' => $name,
    //                         'totalDebit' => 0,
    //                         'totalCredit' => 0,
    //                     ];
    //                 }
    //                 // dd(0 + $entry['totalDebit']);
    //                 if($entry['totalDebit'] < 0)
    //                 {
    //                     $totalAccounts[$category][$name]['totalDebit'] += 0;
    //                     $totalAccounts[$category][$name]['totalCredit'] += -$entry['totalDebit'];
    //                 }
    //                 else
    //                 {
    //                     $totalAccounts[$category][$name]['totalDebit'] += $entry['totalDebit'];
    //                     $totalAccounts[$category][$name]['totalCredit'] += $entry['totalCredit'];
    //                 }

    //             }
    //         }
    //         $filter['startDateRange'] = $start;
    //         $filter['endDateRange'] = $end;

    //         return view('report.trial_balance', compact('filter', 'totalAccounts'));
    //     } else {
    //         return redirect()->back()->with('error', __('Permission Denied.'));
    //     }
    // }

    // public function trialBalanceSummary(Request $request)
    // {
    //     if (\Auth::user()->can('trial balance report')) {

    //         if (!empty($request->start_date) && !empty($request->end_date)) {
    //             $start = $request->start_date;
    //             $end = $request->end_date;
    //         } else {
    //             $start = date('Y-01-01');
    //             $end = date('Y-m-d', strtotime('+1 day'));
    //         }
    //         $types = ChartOfAccountType::where('created_by', \Auth::user()->creatorId())->get();
    //         $totalAccounts = [];
    //         $totalAccount = [];
    //         foreach ($types as $type) {
    //             $total = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
    //             $total->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
    //             $total->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
    //             $total->where('chart_of_accounts.type', $type->id);
    //             $total->where('transaction_lines.created_by', \Auth::user()->creatorId());
    //             $total->where('transaction_lines.date', '>=', $start);
    //             $total->where('transaction_lines.date', '<=', $end);
    //             $total->groupBy('account_id');
    //             $total = $total->get()->toArray();
    //             $name = $type->name;
    //             if (isset($totalAccount[$name])) {
    //                 $totalAccount[$name]["totalCredit"] += $total["totalCredit"];
    //                 $totalAccount[$name]["totalDebit"] += $total["totalDebit"];
    //             } else {
    //                 $totalAccount[$name] = $total;
    //             }
    //         }
    //         foreach ($totalAccount as $category => $entries) {
    //             foreach ($entries as $entry) {
    //                 $name = $entry['name'];
    //                 if (!isset($totalAccounts[$category][$name])) {
    //                     $totalAccounts[$category][$name] = [
    //                         'id' => $entry['id'],
    //                         'code' => $entry['code'],
    //                         'name' => $name,
    //                         'totalDebit' => 0,
    //                         'totalCredit' => 0,
    //                     ];
    //                 }
    //                 if ($entry['totalDebit'] < 0) {
    //                     $totalAccounts[$category][$name]['totalDebit'] += 0;
    //                     $totalAccounts[$category][$name]['totalCredit'] += -$entry['totalDebit'];
    //                 } else {
    //                     $totalAccounts[$category][$name]['totalDebit'] += $entry['totalDebit'];
    //                     $totalAccounts[$category][$name]['totalCredit'] += $entry['totalCredit'];
    //                 }
    //             }
    //         }

    //         $filter['startDateRange'] = $start;
    //         $filter['endDateRange'] = $end;
    //         return view('report.trial_balance', compact('filter', 'totalAccounts'));
    //     } else {
    //         return redirect()->back()->with('error', __('Permission Denied.'));
    //     }
    // }

    public function trialBalanceSummary(Request $request, $view = "expand")
    {
        if (\Auth::user()->can('trial balance report')) {

            if (!empty($request->start_date) && !empty($request->end_date)) {
                $start = $request->start_date;
                $end = $request->end_date;
            } else {
                $start = date('Y-01-01');
                $end = date('Y-m-d', strtotime('+1 day'));
            }
            $types = ChartOfAccountType::where('created_by', \Auth::user()->creatorId())->get();
            $totalAccounts = [];
            $totalAccount = [];
            foreach ($types as $type) {

                // $type->id = 4;
                $parentAccounts = ChartOfAccountParent::where('type', $type->id)->where('created_by', \Auth::user()->creatorId())->get();

                $totalParentAccountArray = [];
                if ($parentAccounts->isNotEmpty()) {
                    foreach ($parentAccounts as $parentAccount) {
                        $totalArray = [];
                        $parentAccountArray = [];
                        $parentAccountArrayTotal = [];

                        $parentAccs = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                        $parentAccs->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                        $parentAccs->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                        $parentAccs->where('chart_of_accounts.type', $type->id);
                        $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                        $parentAccs->where('transaction_lines.created_by', \Auth::user()->creatorId());
                        $parentAccs->where('transaction_lines.date', '>=', $start);
                        $parentAccs->where('transaction_lines.date', '<=', $end);
                        $parentAccs->groupBy('account_id');
                        $parentAccs = $parentAccs->get()->toArray();

                        $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                        $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                        $accounts->where('chart_of_accounts.type', $type->id);
                        $accounts->where('chart_of_accounts.parent', $parentAccount->id);
                        $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                        $accounts->where('transaction_lines.date', '>=', $start);
                        $accounts->where('transaction_lines.date', '<=', $end);
                        $accounts->groupBy('account_id');
                        $accounts = $accounts->get()->toArray();

                        if ($parentAccs == [] && $accounts != []) {

                            $parentAccs = ChartOfAccount::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('0 as totalDebit'), \DB::raw('0 as totalCredit'));
                            $parentAccs->leftjoin('chart_of_account_parents', 'chart_of_accounts.id', 'chart_of_account_parents.account');
                            $parentAccs->where('chart_of_accounts.type', $type->id);
                            $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                            $parentAccs->where('chart_of_accounts.created_by', \Auth::user()->creatorId());
                            $parentAccs = $parentAccs->get()->toArray();
                        } elseif ($parentAccs != [] && $accounts == []) {
                            $parentAccs = [];

                        }

                        $parenttotalBalance = 0;
                        $parentcreditTotal = 0;
                        $parenntdebitTotal = 0;
                        $parenttotalAmount = 0;

                        foreach ($parentAccs as $account) {
                            $Balance = $account['totalCredit'] - $account['totalDebit'];
                            $parenttotalBalance += $Balance;

                            $data = [
                                'account_id' => $account['id'],
                                'account_code' => $account['code'],
                                'account_name' => $account['name'],
                                'account' => 'parent',
                                'totalCredit' => $account['totalCredit'],
                                'totalDebit' => $account['totalDebit'],
                            ];

                            $parentAccountArray[] = $data;
                            $parentcreditTotal += $data['totalCredit'];
                            $parenntdebitTotal += $data['totalDebit'];
                        }

                        foreach ($accounts as $account) {
                            $Balance = $account['totalCredit'] - $account['totalDebit'];

                            if ($Balance != 0) {
                                $data = [
                                    'account_id' => $account['id'],
                                    'account_code' => $account['code'],
                                    'account_name' => $account['name'],
                                    'account' => 'subAccount',
                                    'totalCredit' => $account['totalCredit'],
                                    'totalDebit' => $account['totalDebit'],
                                ];

                                $parentAccountArray[] = $data;
                                $parentcreditTotal += $data['totalCredit'];
                                $parenntdebitTotal += $data['totalDebit'];
                            }
                        }

                        if (!empty($parentAccountArray)) {

                            $dataTotal = [
                                'account_id' => $parentAccount->account,
                                'account_code' => '',
                                'account' => 'parentTotal',
                                'account_name' => 'Total ' . $parentAccount->name,
                                'totalCredit' => $parentcreditTotal,
                                'totalDebit' => $parenntdebitTotal,
                            ];

                            $parentAccountArrayTotal[] = $dataTotal;
                        }

                        if ($parentAccountArray != []) {
                            $totalArray = array_merge($parentAccountArray, $parentAccountArrayTotal);
                            $totalParentAccountArray[] = $totalArray;
                        }
                    }
                }

                if ($totalParentAccountArray != []) {
                    $accounts = TransactionLines::select('chart_of_accounts.id as account_id', 'chart_of_accounts.code as account_code', 'chart_of_accounts.name as account_name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                    $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                    $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                    $accounts->leftjoin('chart_of_account_parents', 'chart_of_accounts.name', 'chart_of_account_parents.name');
                    $accounts->where('chart_of_accounts.type', $type->id);
                    $accounts->where('chart_of_account_parents.account');
                    $accounts->where('chart_of_accounts.parent', '=', 'chart_of_account_parents.id');
                    $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                    $accounts->where('transaction_lines.date', '>=', $start);
                    $accounts->where('transaction_lines.date', '<=', $end);
                    $accounts->groupBy('account_id');
                    $accounts = $accounts->get()->toArray();
                } else {
                    $accounts = TransactionLines::select('chart_of_accounts.id as account_id', 'chart_of_accounts.code as account_code', 'chart_of_accounts.name as account_name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                    $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                    $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                    $accounts->where('chart_of_accounts.type', $type->id);
                    $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                    $accounts->where('transaction_lines.date', '>=', $start);
                    $accounts->where('transaction_lines.date', '<=', $end);
                    $accounts->groupBy('account_id');
                    $accounts = $accounts->get()->toArray();
                }

                $name = $type->name;
                if (isset($totalAccount[$name])) {
                    $totalAccount[$name]["totalCredit"] += $accounts["totalCredit"];
                    $totalAccount[$name]["totalDebit"] += $accounts["totalDebit"];
                } else {
                    $totalAccount[$name] = $accounts;
                }
                if ($totalParentAccountArray != []) {
                    $totalAccount[$name] = array_merge_recursive($totalAccount[$name], $totalParentAccountArray[0]);
                }
            }

            foreach ($totalAccount as $category => $entries) {
                foreach ($entries as $entry) {
                    $name = $entry['account_name'];
                    if (!isset($totalAccounts[$category][$name])) {
                        $totalAccounts[$category][$name] = [
                            'account_id' => $entry['account_id'],
                            'account_code' => $entry['account_code'],
                            'account_name' => $name,
                            'account' => isset($entry['account']) ? $entry['account'] : '',
                            'totalDebit' => 0,
                            'totalCredit' => 0,
                        ];
                    }
                    if ($entry['totalDebit'] < 0) {
                        $totalAccounts[$category][$name]['totalDebit'] += 0;
                        $totalAccounts[$category][$name]['totalCredit'] += -$entry['totalDebit'];
                    } else {
                        $totalAccounts[$category][$name]['totalDebit'] += $entry['totalDebit'];
                        $totalAccounts[$category][$name]['totalCredit'] += $entry['totalCredit'];
                    }
                }
            }

            $filter['startDateRange'] = $start;
            $filter['endDateRange'] = $end;
            return view('report.trial_balance', compact('filter', 'totalAccounts', 'view'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }
    public function leave(Request $request)
    {

        if (\Auth::user()->can('manage report')) {

            $branch = Branch::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $branch->prepend('Select Branch', '');

            $department = Department::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $department->prepend('Select Department', '');

            $filterYear['branch'] = __('All');
            $filterYear['department'] = __('All');
            $filterYear['type'] = __('Monthly');
            $filterYear['dateYearRange'] = date('M-Y');
            $employees = Employee::where('created_by', \Auth::user()->creatorId());
            if (!empty($request->branch)) {
                $employees->where('branch_id', $request->branch);
                $filterYear['branch'] = !empty(Branch::find($request->branch)) ? Branch::find($request->branch)->name : '';
            }
            if (!empty($request->department)) {
                $employees->where('department_id', $request->department);
                $filterYear['department'] = !empty(Department::find($request->department)) ? Department::find($request->department)->name : '';
            }

            $employees = $employees->get();

            $leaves = [];
            $totalApproved = $totalReject = $totalPending = 0;
            foreach ($employees as $employee) {

                $employeeLeave['id'] = $employee->id;
                $employeeLeave['employee_id'] = $employee->employee_id;
                $employeeLeave['employee'] = $employee->name;

                $approved = Leave::where('employee_id', $employee->id)->where('status', 'Approved');
                $reject = Leave::where('employee_id', $employee->id)->where('status', 'Reject');
                $pending = Leave::where('employee_id', $employee->id)->where('status', 'Pending');

                if ($request->type == 'monthly' && !empty($request->month)) {
                    $month = date('m', strtotime($request->month));
                    $year = date('Y', strtotime($request->month));

                    $approved->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $reject->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $pending->whereMonth('applied_on', $month)->whereYear('applied_on', $year);

                    $filterYear['dateYearRange'] = date('M-Y', strtotime($request->month));
                    $filterYear['type'] = __('Monthly');

                } elseif (!isset($request->type)) {
                    $month = date('m');
                    $year = date('Y');
                    $monthYear = date('Y-m');

                    $approved->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $reject->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $pending->whereMonth('applied_on', $month)->whereYear('applied_on', $year);

                    $filterYear['dateYearRange'] = date('M-Y', strtotime($monthYear));
                    $filterYear['type'] = __('Monthly');
                }

                if ($request->type == 'yearly' && !empty($request->year)) {
                    $approved->whereYear('applied_on', $request->year);
                    $reject->whereYear('applied_on', $request->year);
                    $pending->whereYear('applied_on', $request->year);

                    $filterYear['dateYearRange'] = $request->year;
                    $filterYear['type'] = __('Yearly');
                }

                $approved = $approved->count();
                $reject = $reject->count();
                $pending = $pending->count();

                $totalApproved += $approved;
                $totalReject += $reject;
                $totalPending += $pending;

                $employeeLeave['approved'] = $approved;
                $employeeLeave['reject'] = $reject;
                $employeeLeave['pending'] = $pending;

                $leaves[] = $employeeLeave;
            }

            $starting_year = date('Y', strtotime('-5 year'));
            $ending_year = date('Y', strtotime('+5 year'));

            $filterYear['starting_year'] = $starting_year;
            $filterYear['ending_year'] = $ending_year;

            $filter['totalApproved'] = $totalApproved;
            $filter['totalReject'] = $totalReject;
            $filter['totalPending'] = $totalPending;

            return view('report.leave', compact('department', 'branch', 'leaves', 'filterYear', 'filter'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function employeeLeave(Request $request, $employee_id, $status, $type, $month, $year)
    {
        if (\Auth::user()->can('manage report')) {
            $leaveTypes = LeaveType::where('created_by', \Auth::user()->creatorId())->get();
            $leaves = [];
            foreach ($leaveTypes as $leaveType) {
                $leave = new Leave();
                $leave->title = $leaveType->title;
                $totalLeave = Leave::where('employee_id', $employee_id)->where('status', $status)->where('leave_type_id', $leaveType->id);
                if ($type == 'yearly') {
                    $totalLeave->whereYear('applied_on', $year);
                } else {
                    $m = date('m', strtotime($month));
                    $y = date('Y', strtotime($month));

                    $totalLeave->whereMonth('applied_on', $m)->whereYear('applied_on', $y);
                }
                $totalLeave = $totalLeave->count();

                $leave->total = $totalLeave;
                $leaves[] = $leave;
            }

            $leaveData = Leave::where('employee_id', $employee_id)->where('status', $status);
            if ($type == 'yearly') {
                $leaveData->whereYear('applied_on', $year);
            } else {
                $m = date('m', strtotime($month));
                $y = date('Y', strtotime($month));

                $leaveData->whereMonth('applied_on', $m)->whereYear('applied_on', $y);
            }

            $leaveData = $leaveData->get();

            return view('report.leaveShow', compact('leaves', 'leaveData'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    public function monthlyAttendance(Request $request)
    {
        if (\Auth::user()->can('manage report')) {
            $branch = Branch::where('created_by', '=', \Auth::user()->creatorId())->get();
            $department = Department::where('created_by', '=', \Auth::user()->creatorId())->get();

            $data['branch'] = __('All');
            $data['department'] = __('All');

            $employees = Employee::select('id', 'name');
            if (!empty($request->employee_id) && $request->employee_id[0] != 0) {
                $employees->whereIn('id', $request->employee_id);
            }
            $employees = $employees->where('created_by', \Auth::user()->creatorId());

            if (!empty($request->branch)) {
                $employees->where('branch_id', $request->branch);
                $data['branch'] = !empty(Branch::find($request->branch)) ? Branch::find($request->branch)->name : '';
            }

            if (!empty($request->department)) {
                $employees->where('department_id', $request->department);
                $data['department'] = !empty(Department::find($request->department)) ? Department::find($request->department)->name : '';
            }

            $employees = $employees->get()->pluck('name', 'id');

            if (!empty($request->month)) {
                $currentdate = strtotime($request->month);
                $month = date('m', $currentdate);
                $year = date('Y', $currentdate);
                $curMonth = date('M-Y', strtotime($request->month));

            } else {
                $month = date('m');
                $year = date('Y');
                $curMonth = date('M-Y', strtotime($year . '-' . $month));
            }

            $num_of_days = date('t', mktime(0, 0, 0, $month, 1, $year));
            for ($i = 1; $i <= $num_of_days; $i++) {
                $dates[] = str_pad($i, 2, '0', STR_PAD_LEFT);
            }

            $employeesAttendance = [];
            $totalPresent = $totalLeave = $totalEarlyLeave = 0;
            $ovetimeHours = $overtimeMins = $earlyleaveHours = $earlyleaveMins = $lateHours = $lateMins = 0;
            foreach ($employees as $id => $employee) {
                $attendances['name'] = $employee;

                foreach ($dates as $date) {
                    $dateFormat = $year . '-' . $month . '-' . $date;

                    if ($dateFormat <= date('Y-m-d')) {
                        $employeeAttendance = AttendanceEmployee::where('employee_id', $id)->where('date', $dateFormat)->first();

                        if (!empty($employeeAttendance) && $employeeAttendance->status == 'Present') {
                            $attendanceStatus[$date] = 'P';
                            $totalPresent += 1;

                            if ($employeeAttendance->overtime > 0) {
                                $ovetimeHours += date('h', strtotime($employeeAttendance->overtime));
                                $overtimeMins += date('i', strtotime($employeeAttendance->overtime));
                            }

                            if ($employeeAttendance->early_leaving > 0) {
                                $earlyleaveHours += date('h', strtotime($employeeAttendance->early_leaving));
                                $earlyleaveMins += date('i', strtotime($employeeAttendance->early_leaving));
                            }

                            if ($employeeAttendance->late > 0) {
                                $lateHours += date('h', strtotime($employeeAttendance->late));
                                $lateMins += date('i', strtotime($employeeAttendance->late));
                            }

                        } elseif (!empty($employeeAttendance) && $employeeAttendance->status == 'Leave') {
                            $attendanceStatus[$date] = 'A';
                            $totalLeave += 1;
                        } else {
                            $attendanceStatus[$date] = '';
                        }
                    } else {
                        $attendanceStatus[$date] = '';
                    }

                }
                $attendances['status'] = $attendanceStatus;
                $employeesAttendance[] = $attendances;
            }

            $totalOverTime = $ovetimeHours + ($overtimeMins / 60);
            $totalEarlyleave = $earlyleaveHours + ($earlyleaveMins / 60);
            $totalLate = $lateHours + ($lateMins / 60);

            $data['totalOvertime'] = $totalOverTime;
            $data['totalEarlyLeave'] = $totalEarlyleave;
            $data['totalLate'] = $totalLate;
            $data['totalPresent'] = $totalPresent;
            $data['totalLeave'] = $totalLeave;
            $data['curMonth'] = $curMonth;

            return view('report.monthlyAttendance', compact('employeesAttendance', 'branch', 'department', 'dates', 'data'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    public function payroll(Request $request)
    {

        if (\Auth::user()->can('manage report')) {
            $branch = Branch::where('created_by', '=', \Auth::user()->creatorId())->get();
            $department = Department::where('created_by', '=', \Auth::user()->creatorId())->get();
            $employees = Employee::select('id', 'name');
            if (!empty($request->employee_id) && $request->employee_id[0] != 0) {
                $employees->where('id', $request->employee_id);
            }
            $employees = $employees->where('created_by', \Auth::user()->creatorId());

            $data['branch'] = __('All');
            $data['department'] = __('All');
            $filterYear['branch'] = __('All');
            $filterYear['department'] = __('All');
            $filterYear['type'] = __('Monthly');
            $filterYear['dateYearRange'] = '';

            $payslips = PaySlip::select('pay_slips.*', 'employees.name')->leftjoin('employees', 'pay_slips.employee_id', '=', 'employees.id')->where('pay_slips.created_by', \Auth::user()->creatorId());

            if ($request->type == 'monthly' && !empty($request->month)) {

                $payslips->where('salary_month', $request->month);

                $filterYear['dateYearRange'] = date('M-Y', strtotime($request->month));
                $filterYear['type'] = __('Monthly');
            } elseif (!isset($request->type)) {
                $month = date('Y-m');

                $payslips->where('salary_month', $month);

                $filterYear['dateYearRange'] = date('M-Y', strtotime($month));
                $filterYear['type'] = __('Monthly');
            }

            if ($request->type == 'yearly' && !empty($request->year)) {
                $startMonth = $request->year . '-01';
                $endMonth = $request->year . '-12';
                $payslips->where('salary_month', '>=', $startMonth)->where('salary_month', '<=', $endMonth);

                $filterYear['dateYearRange'] = $request->year;
                $filterYear['type'] = __('Yearly');
            }

            if (!empty($request->branch)) {
                $payslips->where('employees.branch_id', $request->branch);

                $filterYear['branch'] = !empty(Branch::find($request->branch)) ? Branch::find($request->branch)->name : '';
            }

            if (!empty($request->department)) {

                $payslips->where('employees.department_id', $request->department);

                $filterYear['department'] = !empty(Department::find($request->department)) ? Department::find($request->department)->name : '';
            }

            $employees = $employees->get()->pluck('name', 'id')->toArray();

            $payslips = $payslips->whereIn('name', $employees)->with(['employees'])->get();

            $totalBasicSalary = $totalNetSalary = $totalAllowance = $totalCommision = $totalLoan = $totalSaturationDeduction = $totalOtherPayment = $totalOverTime = 0;

            foreach ($payslips as $payslip) {
                $totalBasicSalary += $payslip->basic_salary;
                $totalNetSalary += $payslip->net_payble;

                $allowances = json_decode($payslip->allowance);
                foreach ($allowances as $allowance) {
                    $totalAllowance += $allowance->amount;

                }

                $commisions = json_decode($payslip->commission);
                foreach ($commisions as $commision) {
                    $totalCommision += $commision->amount;

                }

                $loans = json_decode($payslip->loan);
                foreach ($loans as $loan) {
                    $totalLoan += $loan->amount;
                }

                $saturationDeductions = json_decode($payslip->saturation_deduction);
                foreach ($saturationDeductions as $saturationDeduction) {
                    $totalSaturationDeduction += $saturationDeduction->amount;
                }

                $otherPayments = json_decode($payslip->other_payment);
                foreach ($otherPayments as $otherPayment) {
                    $totalOtherPayment += $otherPayment->amount;
                }

                $overtimes = json_decode($payslip->overtime);
                foreach ($overtimes as $overtime) {
                    $days = $overtime->number_of_days;
                    $hours = $overtime->hours;
                    $rate = $overtime->rate;

                    $totalOverTime += ($rate * $hours) * $days;
                }

            }

            $filterData['totalBasicSalary'] = $totalBasicSalary;
            $filterData['totalNetSalary'] = $totalNetSalary;
            $filterData['totalAllowance'] = $totalAllowance;
            $filterData['totalCommision'] = $totalCommision;
            $filterData['totalLoan'] = $totalLoan;
            $filterData['totalSaturationDeduction'] = $totalSaturationDeduction;
            $filterData['totalOtherPayment'] = $totalOtherPayment;
            $filterData['totalOverTime'] = $totalOverTime;

            $starting_year = date('Y', strtotime('-5 year'));
            $ending_year = date('Y', strtotime('+5 year'));

            $filterYear['starting_year'] = $starting_year;
            $filterYear['ending_year'] = $ending_year;

            return view('report.payroll', compact('payslips', 'filterData', 'branch', 'department', 'filterYear'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    //branch wise department get in Payroll report
    public function getPayrollDepartment(Request $request)
    {
        if ($request->branch_id == 0) {
            $departments = Department::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id')->toArray();
        } else {
            $departments = Department::where('created_by', '=', \Auth::user()->creatorId())->where('branch_id', $request->branch_id)->get()->pluck('name', 'id')->toArray();
        }

        return response()->json($departments);
    }

    public function getPayrollEmployee(Request $request)
    {
        if (!$request->department_id) {
            $employees = Employee::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id')->toArray();
        } else {
            $employees = Employee::where('created_by', '=', \Auth::user()->creatorId())->where('department_id', $request->department_id)->get()->pluck('name', 'id')->toArray();
        }
        return response()->json($employees);
    }

    public function exportCsv($filter_month, $branch, $department)
    {

        $data['branch'] = __('All');
        $data['department'] = __('All');
        $employees = Employee::select('id', 'name')->where('created_by', \Auth::user()->creatorId());
        if ($branch != 0) {
            $employees->where('branch_id', $branch);
            $data['branch'] = !empty(Branch::find($branch)) ? Branch::find($branch)->name : '';
        }

        if ($department != 0) {
            $employees->where('department_id', $department);
            $data['department'] = !empty(Department::find($department)) ? Department::find($department)->name : '';
        }

        $employees = $employees->get()->pluck('name', 'id');

        $currentdate = strtotime($filter_month);
        $month = date('m', $currentdate);
        $year = date('Y', $currentdate);
        $data['curMonth'] = date('M-Y', strtotime($filter_month));

        $fileName = $data['branch'] . ' ' . __('Branch') . ' ' . $data['curMonth'] . ' ' . __('Attendance Report of') . ' ' . $data['department'] . ' ' . __('Department') . ' ' . '.csv';

        $num_of_days = date('t', mktime(0, 0, 0, $month, 1, $year));
        for ($i = 1; $i <= $num_of_days; $i++) {
            $dates[] = str_pad($i, 2, '0', STR_PAD_LEFT);
        }

        foreach ($employees as $id => $employee) {
            $attendances['name'] = $employee;

            foreach ($dates as $date) {

                $dateFormat = $year . '-' . $month . '-' . $date;

                if ($dateFormat <= date('Y-m-d')) {
                    $employeeAttendance = AttendanceEmployee::where('employee_id', $id)->where('date', $dateFormat)->first();

                    if (!empty($employeeAttendance) && $employeeAttendance->status == 'Present') {
                        $attendanceStatus[$date] = 'P';
                    } elseif (!empty($employeeAttendance) && $employeeAttendance->status == 'Leave') {
                        $attendanceStatus[$date] = 'A';
                    } else {
                        $attendanceStatus[$date] = '-';
                    }

                } else {
                    $attendanceStatus[$date] = '-';
                }
                $attendances[$date] = $attendanceStatus[$date];
            }

            $employeesAttendance[] = $attendances;
        }

        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0",
        );

        $emp = array(
            'employee',
        );

        $columns = array_merge($emp, $dates);

        $callback = function () use ($employeesAttendance, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($employeesAttendance as $attendance) {
                fputcsv($file, str_replace('"', '', array_values($attendance)));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function productStock(Request $request)
    {
        if (\Auth::user()->can('stock report')) {
            $stocks = StockReport::with(['product'])->where('created_by', '=', \Auth::user()->creatorId())->get();
            return view('report.product_stock_report', compact('stocks'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

    }

    //for export in account statement report
    public function export()
    {
        $name = 'account_statement' . date('Y-m-d i:h:s');
        $data = Excel::download(new AccountStatementExport(), $name . '.xlsx');

        return $data;
    }
    // for export in product stock report
    public function stock_export()
    {
        $name = 'Product_Stock' . date('Y-m-d i:h:s');
        $data = Excel::download(new ProductStockExport(), $name . '.xlsx');

        return $data;
    }

    // for export in payroll report
    public function PayrollReportExport(Request $request)
    {
        $name = 'Payroll_' . date('Y-m-d i:h:s');
        $data = \Excel::download(new PayrollExport(), $name . '.xlsx');

        return $data;
    }

    // for export in leave report
    public function LeaveReportExport()
    {
        $name = 'leave_' . date('Y-m-d i:h:s');
        $data = \Excel::download(new LeaveReportExport(), $name . '.xlsx');

        return $data;
    }

    //branch wise department get in monthly-attendance report
    public function getdepartment(Request $request)
    {
        if ($request->branch_id == 0) {
            $departments = Department::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id')->toArray();
        } else {
            $departments = Department::where('created_by', '=', \Auth::user()->creatorId())->where('branch_id', $request->branch_id)->get()->pluck('name', 'id')->toArray();
        }

        return response()->json($departments);
    }

    public function getemployee(Request $request)
    {
        if (!$request->department_id) {
            $employees = Employee::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id')->toArray();
        } else {
            $employees = Employee::where('created_by', '=', \Auth::user()->creatorId())->where('department_id', $request->department_id)->get()->pluck('name', 'id')->toArray();
        }
        return response()->json($employees);
    }

    public function leadreport(Request $request)
    {
        $user = \Auth::user();
        $leads = Lead::orderBy('id');
        $leads->where('created_by', \Auth::user()->creatorId());

        $user_week_lead = Lead::orderBy('created_at')->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get()->groupBy(function ($item) {
            return $item->created_at->format('Y-m-d');
        });
        $carbaoDay = Carbon::now()->startOfWeek();

        $weeks = [];
        for ($i = 0; $i < 7; $i++) {
            $weeks[$carbaoDay->startOfWeek()->addDay($i)->format('Y-m-d')] = 0;
        }
        foreach ($user_week_lead as $name => $leads) {
            $weeks[$name] = $leads->count();
        }

        $devicearray = [];
        $devicearray['label'] = [];
        $devicearray['data'] = [];

        foreach ($weeks as $name => $leads) {
            $devicearray['label'][] = Carbon::parse($name)->format('l');
            $devicearray['data'][] = $leads;
        }
        $leads = Lead::where('created_by', '=', \Auth::user()->creatorId())->get();

        $lead_source = Source::where('created_by', \Auth::user()->id)->get();

        $leadsourceName = [];
        $leadsourceeData = [];
        foreach ($lead_source as $lead_source_data) {
            $lead_source = lead::where('created_by', \Auth::user()->id)->where('sources', $lead_source_data->id)->count();
            $leadsourceName[] = $lead_source_data->name;
            $leadsourceeData[] = $lead_source;
        }

        // monthly report

        $labels = [];
        $data = [];

        if (!empty($request->start_month) && !empty($request->end_month)) {
            $start = strtotime($request->start_month);
            $end = strtotime($request->end_month);
        } else {
            $start = strtotime(date('Y-01'));
            $end = strtotime(date('Y-12'));
        }

        $leads = Lead::orderBy('id');
        $leads->where('date', '>=', date('Y-m-01', $start))->where('date', '<=', date('Y-m-t', $end));
        $leads->where('created_by', \Auth::user()->creatorId());
        $leads = $leads->get();

        $currentdate = $start;
        while ($currentdate <= $end) {
            $month = date('m', $currentdate);
            $year = date('Y');

            if (!empty($request->start_month)) {
                $leadFilter = Lead::where('created_by', \Auth::user()->creatorId())->whereMonth('date', $request->start_month)->whereYear('date', $year)->get();

            } else {
                $leadFilter = Lead::where('created_by', \Auth::user()->creatorId())->whereMonth('date', $month)->whereYear('date', $year)->get();
                // dd($request->leadFilter);
            }

            $data[] = count($leadFilter);
            $labels[] = date('M Y', $currentdate);
            $currentdate = strtotime('+1 month', $currentdate);

            if (!empty($request->start_month)) {
                $cdate = '01-' . $request->start_month . '-' . $year;
                $mstart = strtotime($cdate);
                $labelss[] = date('M Y', $mstart);

                return response()->json(['data' => $data, 'name' => $labelss]);
            }
        }

        if (empty($request->start_month) && !empty($request->all())) {
            return response()->json(['data' => $data, 'name' => $labels]);
        }
        $filter['startDateRange'] = date('M-Y', $start);
        $filter['endDateRange'] = date('M-Y', $end);

        $monthList = $month = $this->yearMonth();

        //staff report

        if ($request->type == "staff_repport") {
            $form_date = date('Y-m-d H:i:s', strtotime($request->From_Date));
            $to_date = date('Y-m-d H:i:s', strtotime($request->To_Date));

            if (!empty($request->From_Date) && !empty($request->To_Date)) {

                $lead_user = User::where('created_by', \Auth::user()->id)->get();
                $leaduserName = [];
                $leadusereData = [];
                foreach ($lead_user as $lead_user_data) {
                    $lead_user = Lead::where('created_by', \Auth::user()->id)->where('user_id', $lead_user_data->id)->whereBetween('created_at', [$form_date, $to_date])->count();
                    $leaduserName[] = $lead_user_data->name;
                    $leadusereData[] = $lead_user;
                }
                return response()->json(['data' => $leadusereData, 'name' => $leaduserName]);
            }
        } else {
            $lead_user = User::where('created_by', \Auth::user()->id)->get();
            $leaduserName = [];
            $leadusereData = [];
            foreach ($lead_user as $lead_user_data) {
                $lead_user = Lead::where('created_by', \Auth::user()->id)->where('user_id', $lead_user_data->id)->count();
                $leaduserName[] = $lead_user_data->name;
                $leadusereData[] = $lead_user;
            }
        }

        $lead_pipeline = Pipeline::where('created_by', \Auth::user()->id)->get();

        $leadpipelineName = [];
        $leadpipelineeData = [];
        foreach ($lead_pipeline as $lead_pipeline_data) {
            $lead_pipeline = lead::where('created_by', \Auth::user()->id)->where('pipeline_id', $lead_pipeline_data->id)->count();
            $leadpipelineName[] = $lead_pipeline_data->name;
            $leadpipelineeData[] = $lead_pipeline;
        }

        return view('report.lead', compact('devicearray', 'leadsourceName', 'leadsourceeData', 'labels', 'data', 'filter', 'monthList', 'leads', 'leaduserName', 'leadusereData', 'user', 'leadpipelineName', 'leadpipelineeData'));
    }

    public function dealreport(Request $request)
    {
        $user = \Auth::user();
        $deals = Deal::orderBy('id');
        $deals->where('created_by', \Auth::user()->creatorId());

        $user_week_deal = Deal::orderBy('created_at')->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get()->groupBy(function ($item) {
            return $item->created_at->format('Y-m-d');
        });

        $carbaoDay = Carbon::now()->startOfWeek();
        $weeks = [];
        for ($i = 0; $i < 7; $i++) {
            $weeks[$carbaoDay->startOfWeek()->addDay($i)->format('Y-m-d')] = 0;
        }
        foreach ($user_week_deal as $name => $deals) {
            $weeks[$name] = $deals->count();
        }

        $devicearray = [];
        $devicearray['label'] = [];
        $devicearray['data'] = [];
        foreach ($weeks as $name => $deals) {
            $devicearray['label'][] = Carbon::parse($name)->format('l');
            $devicearray['data'][] = $deals;
        }
        $deals = Deal::where('created_by', '=', \Auth::user()->creatorId())->get();

        $deals_source = Source::where('created_by', \Auth::user()->id)->get();

        $dealsourceName = [];
        $dealsourceeData = [];
        foreach ($deals_source as $deals_source_data) {
            $deals_source = Deal::where('created_by', \Auth::user()->id)->where('sources', $deals_source_data->id)->count();
            $dealsourceName[] = $deals_source_data->name;
            $dealsourceeData[] = $deals_source;
        }
        if ($request->type == "deal_staff_repport") {
            $from_date = date('Y-m-d H:i:s', strtotime($request->From_Date));
            $to_date = date('Y-m-d H:i:s', strtotime($request->To_Date));

            if (!empty($request->From_Date) && !empty($request->To_Date)) {
                $user_deal = $this->deals();
                $dealUserData = [];
                $dealUserName = [];
                foreach ($user_deal as $user_deal_data) {

                    $user_deals = UserDeal::where('user_id', $user_deal_data->id)->whereBetween('created_at', [$from_date, $to_date])->count();
                    $dealUserName[] = $user_deal_data->name;
                    $dealUserData[] = $user_deals;
                }
                return response()->json(['data' => $dealUserData, 'name' => $dealUserName]);
            }
        } else {
            $user_deal = $this->deals();
            $dealUserData = [];
            $dealUserName = [];
            foreach ($user_deal as $user_deal_data) {
                $user_deals = UserDeal::where('user_id', $user_deal_data->id)->count();

                $dealUserName[] = $user_deal_data->name;
                $dealUserData[] = $user_deals;
            }
        }

        $deal_pipeline = Pipeline::where('created_by', \Auth::user()->id)->get();

        $dealpipelineName = [];
        $dealpipelineeData = [];
        foreach ($deal_pipeline as $deal_pipeline_data) {
            $deal_pipeline = Deal::where('created_by', \Auth::user()->id)->where('pipeline_id', $deal_pipeline_data->id)->count();
            $dealpipelineName[] = $deal_pipeline_data->name;
            $dealpipelineeData[] = $deal_pipeline;
        }

        if ($request->type == "client_repport") {

            $from_date1 = date('Y-m-d H:i:s', strtotime($request->from_date));
            $to_date1 = date('Y-m-d H:i:s', strtotime($request->to_date));
            if (!empty($request->from_date) && !empty($request->to_date)) {
                $client_deal = $this->deals();
                $dealClientData = [];
                $dealClientName = [];
                foreach ($client_deal as $client_deal_data) {

                    $deals_client = ClientDeal::where('client_id', $client_deal_data->id)->whereBetween('created_at', [$from_date1, $to_date1])->count();
                    $dealClientName[] = $client_deal_data->name;
                    $dealClientData[] = $deals_client;
                }
                return response()->json(['data' => $dealClientData, 'name' => $dealClientName]);
            }
        } else {
            $client_deal = $this->deals();
            $dealClientName = [];
            $dealClientData = [];
            foreach ($client_deal as $client_deal_data) {
                $deals_client = ClientDeal::where('client_id', $client_deal_data->id)->count();
                $dealClientName[] = $client_deal_data->name;
                $dealClientData[] = $deals_client;
            }
        }
        $labels = [];
        $data = [];

        if (!empty($request->start_month) && !empty($request->end_month)) {
            $start = strtotime($request->start_month);
            $end = strtotime($request->end_month);
        } else {
            $start = strtotime(date('Y-01'));
            $end = strtotime(date('Y-12'));
        }

        $deals = Deal::orderBy('id');
        $deals->where('created_at', '>=', date('Y-m-01', $start))->where('created_at', '<=', date('Y-m-t', $end));
        $deals->where('created_by', \Auth::user()->creatorId());
        $deals = $deals->get();

        $currentdate = $start;
        while ($currentdate <= $end) {
            $month = date('m', $currentdate);

            $year = date('Y');

            if (!empty($request->start_month)) {
                $dealFilter = Deal::where('created_by', \Auth::user()->creatorId())->whereMonth('created_at', $request->start_month)->whereYear('created_at', $year)->get();
            } else {
                $dealFilter = Deal::where('created_by', \Auth::user()->creatorId())->whereMonth('created_at', $month)->whereYear('created_at', $year)->get();
            }

            $data[] = count($dealFilter);
            $labels[] = date('M Y', $currentdate);
            $currentdate = strtotime('+1 month', $currentdate);

            if (!empty($request->start_month)) {
                $cdate = '01-' . $request->start_month . '-' . $year;
                $mstart = strtotime($cdate);
                $labelss[] = date('M Y', $mstart);

                return response()->json(['data' => $data, 'name' => $labelss]);
            }
        }
        if (empty($request->start_month) && !empty($request->all())) {
            return response()->json(['data' => $data, 'name' => $labels]);
        }
        $filter['startDateRange'] = date('M-Y', $start);
        $filter['endDateRange'] = date('M-Y', $end);

        $monthList = $month = $this->yearMonth();
        return view('report.deal', compact('devicearray', 'dealsourceName', 'dealsourceeData', 'dealUserData', 'dealUserName', 'dealpipelineName', 'dealpipelineeData', 'data', 'labels', 'dealClientName', 'dealClientData', 'monthList'));
    }

    private static $dealData = null;

    public function deals()
    {
        if (self::$dealData == null) {
            $deal = User::where('created_by', \Auth::user()->creatorId())->get();
            self::$dealData = $deal;
        }

        return self::$dealData;
    }

    public function warehouseReport()
    {
        if (\Auth::user()->can('manage pos')) {
            $warehouse = warehouse::where('created_by', \Auth::user()->id)->get();
            $totalWarehouse = warehouse::where('created_by', \Auth::user()->id)->count();
            $totalProduct = WarehouseProduct::where('created_by', '=', \Auth::user()->creatorId())->count();
            $warehousename = [];
            $warehouseProductData = [];
            foreach ($warehouse as $warehouse_data) {
                $warehouseGet = WarehouseProduct::where('created_by', \Auth::user()->id)->where('warehouse_id', $warehouse_data->id)->count();
                $warehousename[] = $warehouse_data->name;
                $warehouseProductData[] = $warehouseGet;
            }

            return view('report.warehouse', compact('warehouse', 'totalWarehouse', 'totalProduct', 'warehouseProductData', 'warehousename'));

        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    public function purchaseDailyReport(Request $request)
    {
        if (\Auth::user()->can('manage pos')) {
            $warehouse = warehouse::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $warehouse->prepend('All Warehouse', 0);
            $vendor = Vender::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $vendor->prepend('All Vendor', 0);
            $query = Purchase::where('created_by', '=', \Auth::user()->creatorId());
            if (!empty($request->warehouse)) {
                $query->where('warehouse_id', '=', $request->warehouse);
            }
            if (!empty($request->vendor)) {
                $query->where('vender_id', '=', $request->vendor);
            }

            $arrDuration = [];
            $data = [];
            if (!empty($request->start_date) && !empty($request->end_date)) {
                $first_date = $request->start_date;
                $end_date = $request->end_date;
            } else {
                $first_date = date('Y-m-d', strtotime('today - 30 days'));
                $end_date = date('Y-m-d', strtotime('today - 1 days'));
            }
            $query->whereBetween('purchase_date', [$first_date, $end_date]);
            $purchases = $query->get()->groupBy(
                function ($val) {
                    return Carbon::parse($val->purchase_date)->format('Y-m-d');
                });
            $total = [];
            if (!empty($purchases) && count($purchases) > 0) {
                foreach ($purchases as $day => $onepurchase) {
                    $totals = 0;
                    foreach ($onepurchase as $purchase) {
                        $totals += $purchase->getTotal();
                    }
                    $total[$day] = $totals;
                }
            }
            // dd($request->all(), $purchases);

            $previous_days = strtotime("-1 month +1 days");
            for ($i = 0; $i < 30; $i++) {
                $previous_days = strtotime(date('Y-m-d', $previous_days) . " +1 day");
                $arrDuration[] = date('d-M', $previous_days);
                $date = date('Y-m-d', $previous_days);
                $data[] = isset($total[$date]) ? $total[$date] : 0;
            }

            $filter['startDate'] = $first_date;
            $filter['endDate'] = $end_date;
            $warehouses = warehouse::where('id', '=', $request->warehouse)->where('created_by', \Auth::user()->creatorId())->first();
            $filter['warehouse'] = !empty($warehouses) ? $warehouses->name : '';
            $vendors = Vender::where('id', '=', $request->vendor)->where('created_by', \Auth::user()->creatorId())->first();
            $filter['vendor'] = !empty($vendors) ? $vendors->name : '';

            return view('report.daily_purchase', compact('warehouse', 'vendor', 'arrDuration', 'data', 'filter'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    public function purchaseMonthlyReport(Request $request)
    {
        if (\Auth::user()->can('manage pos')) {
            $monthList = $this->yearMonth();
            $yearList = $this->yearList();
            $warehouse = warehouse::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $warehouse->prepend('All Warehouse', 0);
            $vendor = Vender::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $vendor->prepend('All Vendor', 0);
            $query = Purchase::where('created_by', '=', \Auth::user()->creatorId());
            if (!empty($request->warehouse)) {
                $query->where('warehouse_id', '=', $request->warehouse);
            }
            if (!empty($request->vendor)) {
                $query->where('vender_id', '=', $request->vendor);
            }
            $arrDuration = [];
            $data = [];
            if (!empty($request->year)) {
                $year = $request->year;
            } else {
                $year = date('Y');
            }
            $query->whereYear('purchase_date', $year);
            $purchases = $query->get()->groupBy(
                function ($val) {
                    return Carbon::parse($val->purchase_date)->format('m');
                });
            $total = [];
            if (!empty($purchases) && count($purchases) > 0) {
                foreach ($purchases as $month => $onepurchase) {
                    $totals = 0;
                    foreach ($onepurchase as $purchase) {
                        $totals += $purchase->getTotal();
                    }
                    $total[$month] = $totals;
                }
            }
            for ($i = 0; $i < 12; $i++) {
                $arrDuration[] = date("my", strtotime(date('Y-m-01') . " -$i months"));
                $month = date("m", strtotime(date('Y-m-01') . " -$i months"));
                $data[] = isset($total[$month]) ? $total[$month] : 0;
            }

            $filter['startMonth'] = 'Jan-' . $year;
            $filter['endMonth'] = 'Dec-' . $year;
            $warehouses = warehouse::where('id', '=', $request->warehouse)->where('created_by', \Auth::user()->creatorId())->first();
            $filter['warehouse'] = !empty($warehouses) ? $warehouses->name : '';
            $vendors = Vender::where('id', '=', $request->vendor)->where('created_by', \Auth::user()->creatorId())->first();
            $filter['vendor'] = !empty($vendors) ? $vendors->name : '';

            return view('report.monthly_purchase', compact('monthList', 'yearList', 'warehouse', 'vendor', 'arrDuration', 'data', 'filter'));

        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function posDailyReport(Request $request)
    {
        if (\Auth::user()->can('manage pos')) {
            $warehouse = warehouse::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $warehouse->prepend('All Warehouse', 0);

            $customer = Customer::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $customer->prepend('All Customer', 0);
            $query = Pos::where('created_by', '=', \Auth::user()->creatorId());
            if (!empty($request->warehouse)) {
                $query->where('warehouse_id', '=', $request->warehouse);
            }
            if (!empty($request->customer)) {
                $query->where('customer_id', '=', $request->customer);
            }

            $arrDuration = [];
            $data = [];
            if (!empty($request->start_date) && !empty($request->end_date)) {
                $first_date = $request->start_date;
                $end_date = $request->end_date;
            } else {
                $first_date = date('Y-m-d', strtotime('today - 30 days'));
                $end_date = date('Y-m-d', strtotime('today - 1 days'));

            }
            $query->whereBetween('pos_date', [$first_date, $end_date]);
            $poses = $query->get()->groupBy(
                function ($val) {
                    return Carbon::parse($val->pos_date)->format('Y-m-d');
                });
            $total = [];
            if (!empty($poses) && count($poses) > 0) {
                foreach ($poses as $day => $onepos) {
                    $totals = 0;
                    foreach ($onepos as $pos) {

                        $totals += $pos->getTotal();
                    }
                    $total[$day] = $totals;
                }
            }
            $previous_days = strtotime("-1 month +1 days");
            for ($i = 0; $i < 30; $i++) {
                $previous_days = strtotime(date('Y-m-d', $previous_days) . " +1 day");
                $arrDuration[] = date('d-M', $previous_days);
                $date = date('Y-m-d', $previous_days);
                $data[] = isset($total[$date]) ? $total[$date] : 0;
            }

            $filter['startDate'] = $first_date;
            $filter['endDate'] = $end_date;
            $warehouses = warehouse::where('id', '=', $request->warehouse)->where('created_by', \Auth::user()->creatorId())->first();
            $filter['warehouse'] = !empty($warehouses) ? $warehouses->name : '';
            $customers = Customer::where('id', '=', $request->customer)->where('created_by', \Auth::user()->creatorId())->first();
            $filter['customer'] = !empty($customers) ? $customers->name : '';

            return view('report.daily_pos', compact('warehouse', 'customer', 'arrDuration', 'data', 'filter'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    public function posMonthlyReport(Request $request)
    {
        if (\Auth::user()->can('manage pos')) {
            $monthList = $this->yearMonth();
            $yearList = $this->yearList();

            $warehouse = warehouse::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $warehouse->prepend('All Warehouse', 0);
            $customer = Customer::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $customer->prepend('All Customer', 0);
            $query = Pos::where('created_by', '=', \Auth::user()->creatorId());
            if (!empty($request->warehouse)) {
                $query->where('warehouse_id', '=', $request->warehouse);
            }
            if (!empty($request->customer)) {
                $query->where('customer_id', '=', $request->customer);
            }
            $arrDuration = [];
            $data = [];
            if (!empty($request->year)) {
                $year = $request->year;
            } else {
                $year = date('Y');
            }
            $query->whereYear('pos_date', $year);
            $poses = $query->get()->groupBy(
                function ($val) {
                    return Carbon::parse($val->pos_date)->format('m');
                });
            $total = [];
            if (!empty($poses) && count($poses) > 0) {
                foreach ($poses as $month => $onepos) {
                    $totals = 0;
                    foreach ($onepos as $pos) {
                        $totals += $pos->getTotal();
                    }
                    $total[$month] = $totals;
                }
            }
            for ($i = 0; $i < 12; $i++) {
                $arrDuration[] = date("my", strtotime(date('Y-m-01') . " -$i months"));
                $month = date("m", strtotime(date('Y-m-01') . " -$i months"));
                $data[] = isset($total[$month]) ? $total[$month] : 0;
            }

            $filter['startMonth'] = 'Jan-' . $year;
            $filter['endMonth'] = 'Dec-' . $year;
            $warehouses = warehouse::where('id', '=', $request->warehouse)->where('created_by', \Auth::user()->creatorId())->first();
            $filter['warehouse'] = !empty($warehouses) ? $warehouses->name : '';
            $customers = Customer::where('id', '=', $request->customer)->where('created_by', \Auth::user()->creatorId())->first();
            $filter['customer'] = !empty($customers) ? $customers->name : '';

            return view('report.monthly_pos', compact('monthList', 'yearList', 'warehouse', 'customer', 'arrDuration', 'data', 'filter'));

        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function posVsPurchaseReport(Request $request)
    {
        if (\Auth::user()->can('manage pos')) {
            $data['monthList'] = $month = $this->yearMonth();
            $data['yearList'] = $this->yearList();

            if (isset($request->year)) {
                $year = $request->year;
            } else {
                $year = date('Y');
            }
            $data['currentYear'] = $year;

            // ------------------------------TOTAL POS-----------------------------------------------------------

            $posData = Pos::selectRaw('MONTH(pos_date) as month,YEAR(pos_date) as year,pos_id,id')
                ->where('created_by', \Auth::user()->creatorId());
            $posData->whereRAW('YEAR(pos_date) =?', [$year]);
            $posData = $posData->get();
            $posTotalArray = [];
            foreach ($posData as $pos) {
                $posTotalArray[$pos->month] = $pos->getTotal();
            }

            // ------------------------------ TOTAL PAYMENT-----------------------------------------------------------
            $purchaseData = Purchase::selectRaw('MONTH(send_date) as month,YEAR(send_date) as year,purchase_id,id')
                ->where('created_by', \Auth::user()->creatorId())
                ->where('status', '!=', 0);
            $purchaseData->whereRAW('YEAR(send_date) =?', [$year]);
            $purchaseData = $purchaseData->get();
            $purchaseTotalArray = [];
            foreach ($purchaseData as $purchase) {
                $purchaseTotalArray[$purchase->month] = $purchase->getTotal();
            }

//            -----------------------------

            for ($i = 1; $i <= 12; $i++) {
                $PosTotal[] = array_key_exists($i, $posTotalArray) ? $posTotalArray[$i] : 0;
                $PurchaseTotal[] = array_key_exists($i, $purchaseTotalArray) ? $purchaseTotalArray[$i] : 0;

            }
            $totalPos = array_map(
                function () {
                    return array_sum(func_get_args());
                }, $PosTotal
            );

            $totalPurchase = array_map(
                function () {
                    return array_sum(func_get_args());
                }, $PurchaseTotal
            );

            $profits = [];
            $keys = array_keys($totalPos + $totalPurchase);
            foreach ($keys as $v) {
                $profits[$v] = number_format((empty($totalPos[$v]) ? 0 : $totalPos[$v]) - (empty($totalPurchase[$v]) ? 0 : $totalPurchase[$v]), 2);
            }

            $data['posTotal'] = $PosTotal;
            $data['purchaseTotal'] = $PurchaseTotal;
            $data['profits'] = $profits;
            $filter['startDateRange'] = 'Jan-' . $year;
            $filter['endDateRange'] = 'Dec-' . $year;

            return view('report.pos_vs_purchase', compact('filter'), $data);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    // public function profitLoss(Request $request, $view = '')
    // {

    //     if (\Auth::user()->can('income vs expense report')) {

    //         if (!empty($request->start_date) && !empty($request->end_date)) {
    //             $start = $request->start_date;
    //             $end = $request->end_date;
    //         } else {
    //             $start = date('Y-01-01');
    //             $end = date('Y-m-d', strtotime('+1 day'));
    //         }
    //         $types = ChartOfAccountType::where('created_by', \Auth::user()->creatorId())->whereIn('name', ['Income', 'Expenses', 'Costs of Goods Sold'])->get();
    //         $chartAccounts = [];
    //         $subTypeArray = [];
    //         foreach ($types as $type) {
    //             $accounts = ChartOfAccount::where('created_by', \Auth::user()->creatorId())->where('type', $type->id)->get();
    //             $totalBalance = 0;
    //             $creditTotal = 0;
    //             $debitTotal = 0;
    //             $totalAmount = 0;
    //             $accountArray = [];

    //             foreach ($accounts as $account) {

    //                 $Balance = Utility::getAccountBalance($account->id, $start, $end);
    //                 $totalBalance += $Balance;

    //                 if ($Balance != 0) {
    //                     $data['account_id'] = $account->id;
    //                     $data['account_code'] = $account->code;
    //                     $data['account_name'] = $account->name;
    //                     $data['totalCredit'] = 0;
    //                     $data['totalDebit'] = 0;
    //                     $data['netAmount'] = $Balance;
    //                     $accountArray[] = $data;

    //                     $creditTotal += $data['totalCredit'];
    //                     $debitTotal += $data['totalDebit'];
    //                     $totalAmount += $data['netAmount'];
    //                 }
    //             }

    //             $totalAccountArray = [];

    //             if ($accountArray != []) {
    //                 $dataTotal['account_id'] = '';
    //                 $dataTotal['account_code'] = '';
    //                 $dataTotal['account_name'] = 'Total ' . $type->name;
    //                 $dataTotal['totalCredit'] = $creditTotal;
    //                 $dataTotal['totalDebit'] = $debitTotal;
    //                 $dataTotal['netAmount'] = $totalAmount;
    //                 $accountArray[] = $dataTotal;

    //             }

    //             if ($accountArray != []) {
    //                 $subTypeData['Type'] = ($accountArray != []) ? $type->name : '';
    //                 $subTypeData['account'] = $accountArray;
    //                 $subTypeArray[] = ($subTypeData['account'] != []) ? $subTypeData : [];
    //             }
    //             $chartAccounts = $subTypeArray;
    //         }

    //         $filter['startDateRange'] = $start;
    //         $filter['endDateRange'] = $end;

    //         if ($request->view == 'horizontal' || $view == 'horizontal') {
    //             return view('report.profit_loss_horizontal', compact('filter', 'chartAccounts'));
    //         } elseif ($view == '' || $view == 'vertical') {
    //             return view('report.profit_loss', compact('filter', 'chartAccounts'));
    //         } else {
    //             return redirect()->back();
    //         }

    //     } else {
    //         return redirect()->back()->with('error', __('Permission denied.'));
    //     }
    // }

    public function profitLoss(Request $request, $view = '', $collapseView = 'expand')
    {
        if (\Auth::user()->can('income vs expense report')) {
            if (!empty($request->start_date) && !empty($request->end_date)) {
                $start = $request->start_date;
                $end = $request->end_date;
            } else {
                $start = date('Y-01-01');
                $end = date('Y-m-d', strtotime('+1 day'));
            }
            $types = ChartOfAccountType::where('created_by', \Auth::user()->creatorId())->whereIn('name', ['Income', 'Expenses', 'Costs of Goods Sold'])->get();
            $subTypeArray = [];
            $totalAccounts = [];
            foreach ($types as $type) {

                $parentAccounts = ChartOfAccountParent::where('type', $type->id)->where('created_by', \Auth::user()->creatorId())->get();

                $totalParentAccountArray = [];
                if ($parentAccounts->isNotEmpty()) {
                    foreach ($parentAccounts as $parentAccount) {
                        $totalArray = [];
                        $parentAccountArray = [];
                        $parentAccountArrayTotal = [];

                        $parentAccs = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                        $parentAccs->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                        $parentAccs->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                        $parentAccs->where('chart_of_accounts.type', $type->id);
                        $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                        $parentAccs->where('transaction_lines.created_by', \Auth::user()->creatorId());
                        $parentAccs->where('transaction_lines.date', '>=', $start);
                        $parentAccs->where('transaction_lines.date', '<=', $end);
                        $parentAccs->groupBy('account_id');
                        $parentAccs = $parentAccs->get()->toArray();

                        $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                        $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                        $accounts->where('chart_of_accounts.type', $type->id);
                        $accounts->where('chart_of_accounts.parent', $parentAccount->id);
                        $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                        $accounts->where('transaction_lines.date', '>=', $start);
                        $accounts->where('transaction_lines.date', '<=', $end);
                        $accounts->groupBy('account_id');
                        $accounts = $accounts->get()->toArray();

                        if ($parentAccs == [] && $accounts != []) {

                            $parentAccs = ChartOfAccount::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('0 as totalDebit'), \DB::raw('0 as totalCredit'));
                            $parentAccs->leftjoin('chart_of_account_parents', 'chart_of_accounts.id', 'chart_of_account_parents.account');
                            $parentAccs->where('chart_of_accounts.type', $type->id);
                            $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                            $parentAccs->where('chart_of_accounts.created_by', \Auth::user()->creatorId());
                            $parentAccs = $parentAccs->get()->toArray();
                        }
                        if ($parentAccs != [] && $accounts == []) {

                            $parentAccs = [];
                        }

                        $parenttotalBalance = 0;
                        $parentcreditTotal = 0;
                        $parenntdebitTotal = 0;
                        $parenttotalAmount = 0;

                        foreach ($parentAccs as $account) {
                            $Balance = $account['totalCredit'] - $account['totalDebit'];
                            $parenttotalBalance += $Balance;

                            $data = [
                                'account_id' => $account['id'],
                                'account_code' => $account['code'],
                                'account_name' => $account['name'],
                                'account' => 'parent',
                                'totalCredit' => 0,
                                'totalDebit' => 0,
                                'netAmount' => $Balance,
                            ];

                            $parentAccountArray[] = $data;
                            $parentcreditTotal += $data['totalCredit'];
                            $parenntdebitTotal += $data['totalDebit'];
                            $parenttotalAmount += $data['netAmount'];
                        }

                        foreach ($accounts as $account) {
                            $Balance = $account['totalCredit'] - $account['totalDebit'];
                            $parenttotalBalance += $Balance;

                            if ($Balance != 0) {
                                $data = [
                                    'account_id' => $account['id'],
                                    'account_code' => $account['code'],
                                    'account_name' => $account['name'],
                                    'account' => 'subAccount',
                                    'totalCredit' => 0,
                                    'totalDebit' => 0,
                                    'netAmount' => $Balance,
                                ];

                                $parentAccountArray[] = $data;
                                $parentcreditTotal += $data['totalCredit'];
                                $parenntdebitTotal += $data['totalDebit'];
                                $parenttotalAmount += $data['netAmount'];
                            }
                        }

                        if (!empty($parentAccountArray)) {
                            $dataTotal = [
                                'account_id' => $parentAccount->account,
                                'account_code' => '',
                                'account' => 'parentTotal',
                                'account_name' => 'Total ' . $parentAccount->name,
                                'totalCredit' => $parentcreditTotal,
                                'totalDebit' => $parenntdebitTotal,
                                'netAmount' => $parenttotalAmount,
                            ];

                            $parentAccountArrayTotal[] = $dataTotal;
                            $totalArray = array_merge($parentAccountArray, $parentAccountArrayTotal);
                            $totalParentAccountArray[] = $totalArray;
                        }

                    }

                }

                if ($totalParentAccountArray != []) {
                    $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                    $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                    $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                    $accounts->leftjoin('chart_of_account_parents', 'chart_of_accounts.name', 'chart_of_account_parents.name');
                    $accounts->where('chart_of_accounts.type', $type->id);
                    $accounts->where('chart_of_account_parents.account');
                    $accounts->where('chart_of_accounts.parent', '=', 'chart_of_account_parents.id');
                    $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                    $accounts->where('transaction_lines.date', '>=', $start);
                    $accounts->where('transaction_lines.date', '<=', $end);
                    $accounts->groupBy('account_id');
                    $accounts = $accounts->get()->toArray();
                } else {
                    $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                    $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                    $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                    $accounts->where('chart_of_accounts.type', $type->id);
                    $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                    $accounts->where('transaction_lines.date', '>=', $start);
                    $accounts->where('transaction_lines.date', '<=', $end);
                    $accounts->groupBy('account_id');
                    $accounts = $accounts->get()->toArray();
                }

                $totalBalance = 0;
                $creditTotal = 0;
                $debitTotal = 0;
                $totalAmount = 0;
                $accountArray = [];
                foreach ($accounts as $account) {
                    $Balance = $account['totalCredit'] - $account['totalDebit'];
                    $totalBalance += $Balance;
                    if ($Balance != 0) {
                        $data['account_id'] = $account['id'];
                        $data['account_code'] = $account['code'];
                        $data['account_name'] = $account['name'];
                        $data['account'] = '';
                        $data['totalCredit'] = 0;
                        $data['totalDebit'] = 0;
                        $data['netAmount'] = $Balance;
                        $accountArray[][] = $data;
                        $creditTotal += $data['totalCredit'];
                        $debitTotal += $data['totalDebit'];
                        $totalAmount += $data['netAmount'];
                    }
                }

                $totalAccountArray = [];

                if ($accountArray != []) {
                    $dataTotal['account_id'] = '';
                    $dataTotal['account_code'] = '';
                    $dataTotal['account'] = '';
                    $dataTotal['account_name'] = 'Total ' . $type->name;
                    $dataTotal['totalCredit'] = $creditTotal;
                    $dataTotal['totalDebit'] = $debitTotal;
                    $dataTotal['netAmount'] = $totalAmount;
                    $accountArray[][] = $dataTotal;
                    $totalAccountArray = array_merge($totalParentAccountArray, $accountArray);

                } elseif ($totalParentAccountArray != []) {

                    $dataTotal['account_id'] = '';
                    $dataTotal['account_code'] = '';
                    $dataTotal['account'] = '';
                    $dataTotal['account_name'] = 'Total ' . $type->name;
                    $dataTotal['totalCredit'] = $creditTotal;
                    $dataTotal['totalDebit'] = $debitTotal;
                    $netAmount = 0;
                    foreach ($totalParentAccountArray as $innerArray) {
                        $lastElement = end($innerArray);

                        $netAmount += $lastElement['netAmount'];
                    }
                    $dataTotal['netAmount'] = $netAmount;
                    $accountArrayTotal[][] = $dataTotal;
                    $totalAccountArray = array_merge($totalParentAccountArray, $accountArrayTotal);
                }
                if ($totalAccountArray != []) {
                    $subTypeData['Type'] = ($totalAccountArray != []) ? $type->name : '';
                    $subTypeData['account'] = $totalAccountArray;
                    $subTypeArray[] = ($subTypeData['account'] != []) ? $subTypeData : [];
                }
                $totalAccounts = $subTypeArray;
            }

            $filter['startDateRange'] = $start;
            $filter['endDateRange'] = $end;
            if ($request->view == 'horizontal' || $view == 'horizontal') {
                return view('report.profit_loss_horizontal', compact('filter', 'totalAccounts', 'collapseView'));
            } elseif ($view == '' || $view == 'vertical') {
                return view('report.profit_loss', compact('filter', 'totalAccounts', 'collapseView'));
            } else {
                return redirect()->back();
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    public function monthlyCashflow(Request $request)
    {
        if (\Auth::user()->can('loss & profit report')) {

            $data['monthList'] = $month = $this->yearMonth();
            $data['yearList'] = $this->yearList();

            if (isset($request->year)) {
                $year = $request->year;
            } else {
                $year = date('Y');
            }
            $data['currentYear'] = $year;

            // -------------------------------REVENUE INCOME-------------------------------------------------

            // ------------------------------REVENUE INCOME-----------------------------------
            $incomes = Revenue::selectRaw('sum(revenues.amount) as amount,MONTH(date) as month,YEAR(date) as year,category_id')
                ->leftjoin('product_service_categories', 'revenues.category_id', '=', 'product_service_categories.id')->where('product_service_categories.type', '=', 1);
            $incomes->where('revenues.created_by', '=', \Auth::user()->creatorId());
            $incomes->whereRAW('YEAR(date) =?', [$year]);

            if (!empty($request->category)) {
                $incomes->where('category_id', '=', $request->category);
                $cat = ProductServiceCategory::find($request->category);
                $filter['category'] = !empty($cat) ? $cat->name : '';
            }

            if (!empty($request->customer)) {
                $incomes->where('customer_id', '=', $request->customer);
                $cust = Customer::find($request->customer);
                $filter['customer'] = !empty($cust) ? $cust->name : '';
            }
            $incomes->groupBy('month', 'year', 'category_id');
            $incomes = $incomes->get();

            $tmpArray = [];
            foreach ($incomes as $income) {
                $tmpArray[$income->category_id][$income->month] = $income->amount;
            }
            $array = [];
            foreach ($tmpArray as $cat_id => $record) {
                $tmp = [];
                $tmp['category'] = !empty(ProductServiceCategory::where('id', '=', $cat_id)->first()) ? ProductServiceCategory::where('id', '=', $cat_id)->first()->name : '';
                $tmp['data'] = [];
                for ($i = 1; $i <= 12; $i++) {
                    $tmp['data'][$i] = array_key_exists($i, $record) ? $record[$i] : 0;
                }
                $array[] = $tmp;
            }

            $incomesData = Revenue::selectRaw('sum(revenues.amount) as amount,MONTH(date) as month,YEAR(date) as year');
            $incomesData->where('revenues.created_by', '=', \Auth::user()->creatorId());
            $incomesData->whereRAW('YEAR(date) =?', [$year]);

            if (!empty($request->category)) {
                $incomesData->where('category_id', '=', $request->category);
            }
            if (!empty($request->customer)) {
                $incomesData->where('customer_id', '=', $request->customer);
            }
            $incomesData->groupBy('month', 'year');
            $incomesData = $incomesData->get();
            $incomeArr = [];
            foreach ($incomesData as $k => $incomeData) {
                $incomeArr[$incomeData->month] = $incomeData->amount;
            }
            for ($i = 1; $i <= 12; $i++) {
                $incomeTotal[] = array_key_exists($i, $incomeArr) ? $incomeArr[$i] : 0;
            }

            //---------------------------INVOICE INCOME-----------------------------------------------

            $invoices = Invoice::selectRaw('MONTH(send_date) as month,YEAR(send_date) as year,category_id,invoice_id,id')
                ->where('created_by', \Auth::user()->creatorId())
                ->where('status', '!=', 0);

            $invoices->whereRAW('YEAR(send_date) =?', [$year]);

            if (!empty($request->customer)) {
                $invoices->where('customer_id', '=', $request->customer);
            }

            if (!empty($request->category)) {
                $invoices->where('category_id', '=', $request->category);
            }

            $invoices = $invoices->get();
            $invoiceTmpArray = [];
            foreach ($invoices as $invoice) {
                $invoiceTmpArray[$invoice->category_id][$invoice->month][] = $invoice->getTotal();
            }

            $invoiceArray = [];
            foreach ($invoiceTmpArray as $cat_id => $record) {

                $invoice = [];
                $productCtegory = ProductServiceCategory::where('id', '=', $cat_id)->first();
                $invoice['category'] = !empty($productCtegory) ? $productCtegory->name : '';
                $invoice['data'] = [];
                for ($i = 1; $i <= 12; $i++) {

                    $invoice['data'][$i] = array_key_exists($i, $record) ? array_sum($record[$i]) : 0;
                }
                $invoiceArray[] = $invoice;
            }

            $invoiceTotalArray = [];
            foreach ($invoices as $invoice) {
                $invoiceTotalArray[$invoice->month][] = $invoice->getTotal();
            }
            for ($i = 1; $i <= 12; $i++) {
                $invoiceTotal[] = array_key_exists($i, $invoiceTotalArray) ? array_sum($invoiceTotalArray[$i]) : 0;
            }

            $chartIncomeArr = array_map(
                function () {
                    return array_sum(func_get_args());
                }, $incomeTotal, $invoiceTotal
            );

            $data['chartIncomeArr'] = $chartIncomeArr;
            $data['incomeArr'] = $array;
            $data['invoiceArray'] = $invoiceArray;

            //   -----------------------------------------PAYMENT EXPENSE ------------------------------------------------------------
            $expenses = Payment::selectRaw('sum(payments.amount) as amount,MONTH(date) as month,YEAR(date) as year,category_id')->leftjoin('product_service_categories', 'payments.category_id', '=', 'product_service_categories.id')->where('product_service_categories.type', '=', 2);
            $expenses->where('payments.created_by', '=', \Auth::user()->creatorId());
            $expenses->whereRAW('YEAR(date) =?', [$year]);

            if (!empty($request->category)) {
                $expenses->where('category_id', '=', $request->category);
                $cat = ProductServiceCategory::find($request->category);
                $filter['category'] = !empty($cat) ? $cat->name : '';
            }
            if (!empty($request->vender)) {
                $expenses->where('vender_id', '=', $request->vender);

                $vend = Vender::find($request->vender);
                $filter['vender'] = !empty($vend) ? $vend->name : '';
            }

            $expenses->groupBy('month', 'year', 'category_id');
            $expenses = $expenses->get();
            $tmpArray = [];
            foreach ($expenses as $expense) {
                $tmpArray[$expense->category_id][$expense->month] = $expense->amount;
            }
            $array = [];
            foreach ($tmpArray as $cat_id => $record) {
                $tmp = [];
                $tmp['category'] = !empty(ProductServiceCategory::where('id', '=', $cat_id)->first()) ? ProductServiceCategory::where('id', '=', $cat_id)->first()->name : '';
                $tmp['data'] = [];
                for ($i = 1; $i <= 12; $i++) {
                    $tmp['data'][$i] = array_key_exists($i, $record) ? $record[$i] : 0;
                }
                $array[] = $tmp;
            }
            $expensesData = Payment::selectRaw('sum(payments.amount) as amount,MONTH(date) as month,YEAR(date) as year');
            $expensesData->where('payments.created_by', '=', \Auth::user()->creatorId());
            $expensesData->whereRAW('YEAR(date) =?', [$year]);

            if (!empty($request->category)) {
                $expensesData->where('category_id', '=', $request->category);
            }
            if (!empty($request->vender)) {
                $expensesData->where('vender_id', '=', $request->vender);
            }
            $expensesData->groupBy('month', 'year');
            $expensesData = $expensesData->get();

            $expenseArr = [];
            foreach ($expensesData as $k => $expenseData) {
                $expenseArr[$expenseData->month] = $expenseData->amount;
            }
            for ($i = 1; $i <= 12; $i++) {
                $expenseTotal[] = array_key_exists($i, $expenseArr) ? $expenseArr[$i] : 0;
            }

            //     ------------------------------------BILL EXPENSE----------------------------------------------------

            $bills = Bill::selectRaw('MONTH(send_date) as month,YEAR(send_date) as year,category_id,bill_id,id')->where('created_by', \Auth::user()->creatorId())->where('status', '!=', 0);
            $bills->whereRAW('YEAR(send_date) =?', [$year]);

            if (!empty($request->vender)) {
                $bills->where('vender_id', '=', $request->vender);
            }

            if (!empty($request->category)) {
                $bills->where('category_id', '=', $request->category);
            }
            $bills = $bills->get();
            $billTmpArray = [];
            foreach ($bills as $bill) {
                $billTmpArray[$bill->category_id][$bill->month][] = $bill->getTotal();
            }

            $billArray = [];
            foreach ($billTmpArray as $cat_id => $record) {

                $bill = [];
                $productCategory = ProductServiceCategory::where('id', '=', $cat_id)->first();
                $bill['category'] = !empty($productCategory) ? $productCategory->name : '';
                $bill['data'] = [];
                for ($i = 1; $i <= 12; $i++) {

                    $bill['data'][$i] = array_key_exists($i, $record) ? array_sum($record[$i]) : 0;
                }
                $billArray[] = $bill;
            }

            $billTotalArray = [];
            foreach ($bills as $bill) {
                $billTotalArray[$bill->month][] = $bill->getTotal();
            }
            for ($i = 1; $i <= 12; $i++) {
                $billTotal[] = array_key_exists($i, $billTotalArray) ? array_sum($billTotalArray[$i]) : 0;
            }

            $chartExpenseArr = array_map(
                function () {
                    return array_sum(func_get_args());
                }, $expenseTotal, $billTotal
            );

            $netProfit = [];
            $keys = array_keys($chartIncomeArr + $chartExpenseArr);
            foreach ($keys as $v) {
                $netProfit[$v] = (empty($chartIncomeArr[$v]) ? 0 : $chartIncomeArr[$v]) - (empty($chartExpenseArr[$v]) ? 0 : $chartExpenseArr[$v]);
            }

            $data['chartExpenseArr'] = $chartExpenseArr;
            $data['expenseArr'] = $array;
            $data['billArray'] = $billArray;

            $data['netProfitArray'] = $netProfit;
            $filter['startDateRange'] = 'Jan-' . $year;
            $filter['endDateRange'] = 'Dec-' . $year;

            return view('report.monthly_cashflow', compact('filter'), $data);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    public function quarterlyCashflow(Request $request)
    {

        if (\Auth::user()->can('loss & profit report')) {
            $data['month'] = [
                'Jan-Mar',
                'Apr-Jun',
                'Jul-Sep',
                'Oct-Dec',
                'Total',
            ];
            $data['monthList'] = $month = $this->yearMonth();
            $data['yearList'] = $this->yearList();

            if (isset($request->year)) {
                $year = $request->year;
            } else {
                $year = date('Y');
            }
            $data['currentYear'] = $year;

            // -------------------------------REVENUE INCOME-------------------------------------------------

            $incomes = Revenue::selectRaw('sum(revenues.amount) as amount,MONTH(date) as month,YEAR(date) as year,category_id');
            $incomes->where('created_by', '=', \Auth::user()->creatorId());
            $incomes->whereRAW('YEAR(date) =?', [$year]);
            $incomes->groupBy('month', 'year', 'category_id');
            $incomes = $incomes->get();
            $tmpIncomeArray = [];
            foreach ($incomes as $income) {
                $tmpIncomeArray[$income->category_id][$income->month] = $income->amount;
            }

            $incomeCatAmount_1 = $incomeCatAmount_2 = $incomeCatAmount_3 = $incomeCatAmount_4 = 0;
            $revenueIncomeArray = array();
            foreach ($tmpIncomeArray as $cat_id => $record) {

                $tmp = [];
                $tmp['category'] = !empty(ProductServiceCategory::where('id', '=', $cat_id)->first()) ? ProductServiceCategory::where('id', '=', $cat_id)->first()->name : '';
                $sumData = [];
                for ($i = 1; $i <= 12; $i++) {
                    $sumData[] = array_key_exists($i, $record) ? $record[$i] : 0;
                }

                $month_1 = array_slice($sumData, 0, 3);
                $month_2 = array_slice($sumData, 3, 3);
                $month_3 = array_slice($sumData, 6, 3);
                $month_4 = array_slice($sumData, 9, 3);

                $incomeData[__('Jan-Mar')] = $sum_1 = array_sum($month_1);
                $incomeData[__('Apr-Jun')] = $sum_2 = array_sum($month_2);
                $incomeData[__('Jul-Sep')] = $sum_3 = array_sum($month_3);
                $incomeData[__('Oct-Dec')] = $sum_4 = array_sum($month_4);
                $incomeData[__('Total')] = array_sum(
                    array(
                        $sum_1,
                        $sum_2,
                        $sum_3,
                        $sum_4,
                    )
                );

                $incomeCatAmount_1 += $sum_1;
                $incomeCatAmount_2 += $sum_2;
                $incomeCatAmount_3 += $sum_3;
                $incomeCatAmount_4 += $sum_4;

                $data['month'] = array_keys($incomeData);
                $tmp['amount'] = array_values($incomeData);

                $revenueIncomeArray[] = $tmp;

            }

            $data['incomeCatAmount'] = $incomeCatAmount = [
                $incomeCatAmount_1,
                $incomeCatAmount_2,
                $incomeCatAmount_3,
                $incomeCatAmount_4,
                array_sum(
                    array(
                        $incomeCatAmount_1,
                        $incomeCatAmount_2,
                        $incomeCatAmount_3,
                        $incomeCatAmount_4,
                    )
                ),
            ];

            $data['revenueIncomeArray'] = $revenueIncomeArray;

            //-----------------------INVOICE INCOME---------------------------------------------

            $invoices = Invoice::selectRaw('MONTH(send_date) as month,YEAR(send_date) as year,category_id,invoice_id,id')->where('created_by', \Auth::user()->creatorId())->where('status', '!=', 0);
            $invoices->whereRAW('YEAR(send_date) =?', [$year]);
            if (!empty($request->customer)) {
                $invoices->where('customer_id', '=', $request->customer);
            }
            $invoices = $invoices->get();

            $invoiceTmpArray = [];
            foreach ($invoices as $invoice) {
                $invoiceTmpArray[$invoice->category_id][$invoice->month][] = $invoice->getDue();
            }

            $invoiceCatAmount_1 = $invoiceCatAmount_2 = $invoiceCatAmount_3 = $invoiceCatAmount_4 = 0;

            $invoiceIncomeArray = array();
            foreach ($invoiceTmpArray as $cat_id => $record) {

                $invoiceTmp = [];
                $invoiceTmp['category'] = !empty(ProductServiceCategory::where('id', '=', $cat_id)->first()) ? ProductServiceCategory::where('id', '=', $cat_id)->first()->name : '';
                $invoiceSumData = [];
                for ($i = 1; $i <= 12; $i++) {
                    $invoiceSumData[] = array_key_exists($i, $record) ? array_sum($record[$i]) : 0;

                }

                $month_1 = array_slice($invoiceSumData, 0, 3);
                $month_2 = array_slice($invoiceSumData, 3, 3);
                $month_3 = array_slice($invoiceSumData, 6, 3);
                $month_4 = array_slice($invoiceSumData, 9, 3);
                $invoiceIncomeData[__('Jan-Mar')] = $sum_1 = array_sum($month_1);
                $invoiceIncomeData[__('Apr-Jun')] = $sum_2 = array_sum($month_2);
                $invoiceIncomeData[__('Jul-Sep')] = $sum_3 = array_sum($month_3);
                $invoiceIncomeData[__('Oct-Dec')] = $sum_4 = array_sum($month_4);
                $invoiceIncomeData[__('Total')] = array_sum(
                    array(
                        $sum_1,
                        $sum_2,
                        $sum_3,
                        $sum_4,
                    )
                );
                $invoiceCatAmount_1 += $sum_1;
                $invoiceCatAmount_2 += $sum_2;
                $invoiceCatAmount_3 += $sum_3;
                $invoiceCatAmount_4 += $sum_4;

                $invoiceTmp['amount'] = array_values($invoiceIncomeData);

                $invoiceIncomeArray[] = $invoiceTmp;

            }

            $data['invoiceIncomeCatAmount'] = $invoiceIncomeCatAmount = [
                $invoiceCatAmount_1,
                $invoiceCatAmount_2,
                $invoiceCatAmount_3,
                $invoiceCatAmount_4,
                array_sum(
                    array(
                        $invoiceCatAmount_1,
                        $invoiceCatAmount_2,
                        $invoiceCatAmount_3,
                        $invoiceCatAmount_4,
                    )
                ),
            ];

            $data['invoiceIncomeArray'] = $invoiceIncomeArray;

            $data['totalIncome'] = $totalIncome = array_map(
                function () {
                    return array_sum(func_get_args());
                }, $invoiceIncomeCatAmount, $incomeCatAmount
            );

            //---------------------------------PAYMENT EXPENSE-----------------------------------

            $expenses = Payment::selectRaw('sum(payments.amount) as amount,MONTH(date) as month,YEAR(date) as year,category_id');
            $expenses->where('created_by', '=', \Auth::user()->creatorId());
            $expenses->whereRAW('YEAR(date) =?', [$year]);
            $expenses->groupBy('month', 'year', 'category_id');
            $expenses = $expenses->get();

            $tmpExpenseArray = [];
            foreach ($expenses as $expense) {
                $tmpExpenseArray[$expense->category_id][$expense->month] = $expense->amount;
            }

            $expenseArray = [];
            $expenseCatAmount_1 = $expenseCatAmount_2 = $expenseCatAmount_3 = $expenseCatAmount_4 = 0;
            foreach ($tmpExpenseArray as $cat_id => $record) {
                $tmp = [];
                $tmp['category'] = !empty(ProductServiceCategory::where('id', '=', $cat_id)->first()) ? ProductServiceCategory::where('id', '=', $cat_id)->first()->name : '';
                $expenseSumData = [];
                for ($i = 1; $i <= 12; $i++) {
                    $expenseSumData[] = array_key_exists($i, $record) ? $record[$i] : 0;

                }

                $month_1 = array_slice($expenseSumData, 0, 3);
                $month_2 = array_slice($expenseSumData, 3, 3);
                $month_3 = array_slice($expenseSumData, 6, 3);
                $month_4 = array_slice($expenseSumData, 9, 3);

                $expenseData[__('Jan-Mar')] = $sum_1 = array_sum($month_1);
                $expenseData[__('Apr-Jun')] = $sum_2 = array_sum($month_2);
                $expenseData[__('Jul-Sep')] = $sum_3 = array_sum($month_3);
                $expenseData[__('Oct-Dec')] = $sum_4 = array_sum($month_4);
                $expenseData[__('Total')] = array_sum(
                    array(
                        $sum_1,
                        $sum_2,
                        $sum_3,
                        $sum_4,
                    )
                );

                $expenseCatAmount_1 += $sum_1;
                $expenseCatAmount_2 += $sum_2;
                $expenseCatAmount_3 += $sum_3;
                $expenseCatAmount_4 += $sum_4;

                $data['month'] = array_keys($expenseData);
                $tmp['amount'] = array_values($expenseData);

                $expenseArray[] = $tmp;

            }

            $data['expenseCatAmount'] = $expenseCatAmount = [
                $expenseCatAmount_1,
                $expenseCatAmount_2,
                $expenseCatAmount_3,
                $expenseCatAmount_4,
                array_sum(
                    array(
                        $expenseCatAmount_1,
                        $expenseCatAmount_2,
                        $expenseCatAmount_3,
                        $expenseCatAmount_4,
                    )
                ),
            ];
            $data['expenseArray'] = $expenseArray;

            //    ----------------------------EXPENSE BILL-----------------------------------------------------------------------

            $bills = Bill::selectRaw('MONTH(send_date) as month,YEAR(send_date) as year,category_id,bill_id,id')->where('created_by', \Auth::user()->creatorId())->where('status', '!=', 0);
            $bills->whereRAW('YEAR(send_date) =?', [$year]);
            if (!empty($request->customer)) {
                $bills->where('vender_id', '=', $request->vender);
            }
            $bills = $bills->get();
            $billTmpArray = [];
            foreach ($bills as $bill) {
                $billTmpArray[$bill->category_id][$bill->month][] = $bill->getTotal();
            }

            $billExpenseArray = [];
            $billExpenseCatAmount_1 = $billExpenseCatAmount_2 = $billExpenseCatAmount_3 = $billExpenseCatAmount_4 = 0;
            foreach ($billTmpArray as $cat_id => $record) {
                $billTmp = [];
                $billTmp['category'] = !empty(ProductServiceCategory::where('id', '=', $cat_id)->first()) ? ProductServiceCategory::where('id', '=', $cat_id)->first()->name : '';
                $billExpensSumData = [];
                for ($i = 1; $i <= 12; $i++) {
                    $billExpensSumData[] = array_key_exists($i, $record) ? array_sum($record[$i]) : 0;
                }

                $month_1 = array_slice($billExpensSumData, 0, 3);
                $month_2 = array_slice($billExpensSumData, 3, 3);
                $month_3 = array_slice($billExpensSumData, 6, 3);
                $month_4 = array_slice($billExpensSumData, 9, 3);

                $billExpenseData[__('Jan-Mar')] = $sum_1 = array_sum($month_1);
                $billExpenseData[__('Apr-Jun')] = $sum_2 = array_sum($month_2);
                $billExpenseData[__('Jul-Sep')] = $sum_3 = array_sum($month_3);
                $billExpenseData[__('Oct-Dec')] = $sum_4 = array_sum($month_4);
                $billExpenseData[__('Total')] = array_sum(
                    array(
                        $sum_1,
                        $sum_2,
                        $sum_3,
                        $sum_4,
                    )
                );

                $billExpenseCatAmount_1 += $sum_1;
                $billExpenseCatAmount_2 += $sum_2;
                $billExpenseCatAmount_3 += $sum_3;
                $billExpenseCatAmount_4 += $sum_4;

                $data['month'] = array_keys($billExpenseData);
                $billTmp['amount'] = array_values($billExpenseData);

                $billExpenseArray[] = $billTmp;

            }

            $data['billExpenseCatAmount'] = $billExpenseCatAmount = [
                $billExpenseCatAmount_1,
                $billExpenseCatAmount_2,
                $billExpenseCatAmount_3,
                $billExpenseCatAmount_4,
                array_sum(
                    array(
                        $billExpenseCatAmount_1,
                        $billExpenseCatAmount_2,
                        $billExpenseCatAmount_3,
                        $billExpenseCatAmount_4,
                    )
                ),
            ];

            $data['billExpenseArray'] = $billExpenseArray;

            $data['totalExpense'] = $totalExpense = array_map(
                function () {
                    return array_sum(func_get_args());
                }, $billExpenseCatAmount, $expenseCatAmount
            );

            foreach ($totalIncome as $k => $income) {
                $netProfit[] = $income - $totalExpense[$k];
            }
            $data['netProfitArray'] = $netProfit;

            $filter['startDateRange'] = 'Jan-' . $year;
            $filter['endDateRange'] = 'Dec-' . $year;

            return view('report.quarterly_cashflow', compact('filter'), $data);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function trialBalanceExport(Request $request)
    {
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $start = $request->start_date;
            $end = $request->end_date;
        } else {
            $start = date('Y-m-01');
            $end = date('Y-m-t', strtotime('+1 day'));
        }

        $types = ChartOfAccountType::where('created_by', \Auth::user()->creatorId())->get();
        $chartAccounts = [];

        $totalAccounts = [];
        $totalAccounts = [];

        foreach ($types as $type) {

            // $type->id = 4;
            $parentAccounts = ChartOfAccountParent::where('type', $type->id)->where('created_by', \Auth::user()->creatorId())->get();

            $totalParentAccountArray = [];
            if ($parentAccounts->isNotEmpty()) {
                foreach ($parentAccounts as $parentAccount) {
                    $totalArray = [];
                    $parentAccountArray = [];
                    $parentAccountArrayTotal = [];

                    $parentAccs = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                    $parentAccs->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                    $parentAccs->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                    $parentAccs->where('chart_of_accounts.type', $type->id);
                    $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                    $parentAccs->where('transaction_lines.created_by', \Auth::user()->creatorId());
                    $parentAccs->where('transaction_lines.date', '>=', $start);
                    $parentAccs->where('transaction_lines.date', '<=', $end);
                    $parentAccs->groupBy('account_id');
                    $parentAccs = $parentAccs->get()->toArray();

                    $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                    $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                    $accounts->where('chart_of_accounts.type', $type->id);
                    $accounts->where('chart_of_accounts.parent', $parentAccount->id);
                    $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                    $accounts->where('transaction_lines.date', '>=', $start);
                    $accounts->where('transaction_lines.date', '<=', $end);
                    $accounts->groupBy('account_id');
                    $accounts = $accounts->get()->toArray();

                    if ($parentAccs == [] && $accounts != []) {

                        $parentAccs = ChartOfAccount::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('0 as totalDebit'), \DB::raw('0 as totalCredit'));
                        $parentAccs->leftjoin('chart_of_account_parents', 'chart_of_accounts.id', 'chart_of_account_parents.account');
                        $parentAccs->where('chart_of_accounts.type', $type->id);
                        $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                        $parentAccs->where('chart_of_accounts.created_by', \Auth::user()->creatorId());
                        $parentAccs = $parentAccs->get()->toArray();
                    } elseif ($parentAccs != [] && $accounts == []) {
                        $parentAccs = [];

                    }

                    $parenttotalBalance = 0;
                    $parentcreditTotal = 0;
                    $parenntdebitTotal = 0;
                    $parenttotalAmount = 0;

                    foreach ($parentAccs as $account) {
                        $Balance = $account['totalCredit'] - $account['totalDebit'];
                        $parenttotalBalance += $Balance;

                        $data = [
                            'account_id' => $account['id'],
                            'account_code' => $account['code'],
                            'account_name' => $account['name'],
                            'account' => 'parent',
                            'totalCredit' => $account['totalCredit'],
                            'totalDebit' => $account['totalDebit'],
                        ];

                        $parentAccountArray[] = $data;
                        $parentcreditTotal += $data['totalCredit'];
                        $parenntdebitTotal += $data['totalDebit'];
                    }

                    foreach ($accounts as $account) {
                        $Balance = $account['totalCredit'] - $account['totalDebit'];

                        if ($Balance != 0) {
                            $data = [
                                'account_id' => $account['id'],
                                'account_code' => $account['code'],
                                'account_name' => $account['name'],
                                'account' => 'subAccount',
                                'totalCredit' => $account['totalCredit'],
                                'totalDebit' => $account['totalDebit'],
                            ];

                            $parentAccountArray[] = $data;
                            $parentcreditTotal += $data['totalCredit'];
                            $parenntdebitTotal += $data['totalDebit'];
                        }
                    }

                    if (!empty($parentAccountArray)) {

                        $dataTotal = [
                            'account_id' => $parentAccount->account,
                            'account_code' => '',
                            'account' => 'parentTotal',
                            'account_name' => 'Total ' . $parentAccount->name,
                            'totalCredit' => $parentcreditTotal,
                            'totalDebit' => $parenntdebitTotal,
                        ];

                        $parentAccountArrayTotal[] = $dataTotal;
                    }

                    if ($parentAccountArray != []) {
                        $totalArray = array_merge($parentAccountArray, $parentAccountArrayTotal);
                        $totalParentAccountArray[] = $totalArray;
                    }
                }
            }

            if ($totalParentAccountArray != []) {
                $accounts = TransactionLines::select('chart_of_accounts.id as account_id', 'chart_of_accounts.code as account_code', 'chart_of_accounts.name as account_name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                $accounts->leftjoin('chart_of_account_parents', 'chart_of_accounts.name', 'chart_of_account_parents.name');
                $accounts->where('chart_of_accounts.type', $type->id);
                $accounts->where('chart_of_account_parents.account');
                $accounts->where('chart_of_accounts.parent', '=', 'chart_of_account_parents.id');
                $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                $accounts->where('transaction_lines.date', '>=', $start);
                $accounts->where('transaction_lines.date', '<=', $end);
                $accounts->groupBy('account_id');
                $accounts = $accounts->get()->toArray();
            } else {
                $accounts = TransactionLines::select('chart_of_accounts.id as account_id', 'chart_of_accounts.code as account_code', 'chart_of_accounts.name as account_name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                $accounts->where('chart_of_accounts.type', $type->id);
                $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                $accounts->where('transaction_lines.date', '>=', $start);
                $accounts->where('transaction_lines.date', '<=', $end);
                $accounts->groupBy('account_id');
                $accounts = $accounts->get()->toArray();
            }

            $name = $type->name;
            if (isset($totalAccount[$name])) {
                $totalAccount[$name]["totalCredit"] += $accounts["totalCredit"];
                $totalAccount[$name]["totalDebit"] += $accounts["totalDebit"];
            } else {
                $totalAccount[$name] = $accounts;
            }
            if ($totalParentAccountArray != []) {
                $totalAccount[$name] = array_merge_recursive($totalAccount[$name], $totalParentAccountArray[0]);
            }
        }

        foreach ($totalAccount as $category => $entries) {
            foreach ($entries as $entry) {
                $name = $entry['account_name'];
                if (!isset($totalAccounts[$category][$name])) {
                    $totalAccounts[$category][$name] = [
                        'account_id' => $entry['account_id'],
                        'account_code' => $entry['account_code'],
                        'account_name' => $name,
                        'account' => isset($entry['account']) ? $entry['account'] : '',
                        'totalDebit' => 0,
                        'totalCredit' => 0,
                    ];
                }
                if ($entry['totalDebit'] < 0) {
                    $totalAccounts[$category][$name]['totalDebit'] += 0;
                    $totalAccounts[$category][$name]['totalCredit'] += -$entry['totalDebit'];
                } else {
                    $totalAccounts[$category][$name]['totalDebit'] += $entry['totalDebit'];
                    $totalAccounts[$category][$name]['totalCredit'] += $entry['totalCredit'];
                }
            }
        }
        $companyName = User::where('id', \Auth::user()->creatorId())->first();
        $companyName = $companyName->name;

        $name = 'trial_balance_' . date('Y-m-d i:h:s');
        $data = Excel::download(new TrialBalancExport($totalAccounts, $start, $end, $companyName), $name . '.xlsx');
        ob_end_clean();

        return $data;
    }

    public function balanceSheetExport(Request $request)
    {
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $start = $request->start_date;
            $end = $request->end_date;
        } else {
            $start = date('Y-m-01');
            $end = date('Y-m-t', strtotime('+1 day'));
        }

        $types = ChartOfAccountType::where('created_by', \Auth::user()->creatorId())->whereIn('name', ['Assets', 'Liabilities', 'Equity'])->get();
        $chartAccounts = [];
        foreach ($types as $type) {
            $subTypes = ChartOfAccountSubType::where('type', $type->id)->get();

            $subTypeArray = [];
            foreach ($subTypes as $subType) {
                $parentAccounts = ChartOfAccountParent::where('sub_type', $subType->id)->get();
                // $parentAccountArray = [];
                // $parentAccountArrayTotal = [];

                $totalParentAccountArray = [];
                if ($parentAccounts->isNotEmpty()) {
                    foreach ($parentAccounts as $parentAccount) {
                        $totalArray = [];
                        $parentAccountArray = [];
                        $parentAccountArrayTotal = [];

                        $parentAccs = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                        $parentAccs->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                        $parentAccs->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                        $parentAccs->where('chart_of_accounts.type', $type->id);
                        $parentAccs->where('chart_of_accounts.sub_type', $subType->id);
                        $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                        $parentAccs->where('transaction_lines.created_by', \Auth::user()->creatorId());
                        $parentAccs->where('transaction_lines.date', '>=', $start);
                        $parentAccs->where('transaction_lines.date', '<=', $end);
                        $parentAccs->groupBy('account_id');
                        $parentAccs = $parentAccs->get()->toArray();

                        $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                        $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                        $accounts->where('chart_of_accounts.type', $type->id);
                        $accounts->where('chart_of_accounts.sub_type', $subType->id);
                        $accounts->where('chart_of_accounts.parent', $parentAccount->id);
                        $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                        $accounts->where('transaction_lines.date', '>=', $start);
                        $accounts->where('transaction_lines.date', '<=', $end);
                        $accounts->groupBy('account_id');
                        $accounts = $accounts->get()->toArray();

                        if ($parentAccs == [] && $accounts != []) {

                            $parentAccs = ChartOfAccount::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('0 as totalDebit'), \DB::raw('0 as totalCredit'));
                            $parentAccs->leftjoin('chart_of_account_parents', 'chart_of_accounts.id', 'chart_of_account_parents.account');
                            $parentAccs->where('chart_of_accounts.type', $type->id);
                            $parentAccs->where('chart_of_accounts.sub_type', $subType->id);
                            $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                            $parentAccs->where('chart_of_accounts.created_by', \Auth::user()->creatorId());
                            $parentAccs = $parentAccs->get()->toArray();
                        }

                        if ($parentAccs != [] && $accounts == []) {

                            $parentAccs = [];
                        }

                        $parenttotalBalance = 0;
                        $parentcreditTotal = 0;
                        $parenntdebitTotal = 0;
                        $parenttotalAmount = 0;

                        foreach ($parentAccs as $account) {
                            $Balance = $account['totalCredit'] - $account['totalDebit'];
                            $parenttotalBalance += $Balance;

                            $data = [
                                'account_id' => $account['id'],
                                'account_code' => $account['code'],
                                'account_name' => $account['name'],
                                'account' => 'parent',
                                'totalCredit' => 0,
                                'totalDebit' => 0,
                                'netAmount' => $Balance,
                            ];

                            $parentAccountArray[] = $data;
                            $parentcreditTotal += $data['totalCredit'];
                            $parenntdebitTotal += $data['totalDebit'];
                            $parenttotalAmount += $data['netAmount'];
                        }

                        foreach ($accounts as $account) {
                            $Balance = $account['totalCredit'] - $account['totalDebit'];
                            $parenttotalBalance += $Balance;

                            if ($Balance != 0) {
                                $data = [
                                    'account_id' => $account['id'],
                                    'account_code' => $account['code'],
                                    'account_name' => $account['name'],
                                    'account' => 'subAccount',
                                    'totalCredit' => 0,
                                    'totalDebit' => 0,
                                    'netAmount' => $Balance,
                                ];

                                $parentAccountArray[] = $data;
                                $parentcreditTotal += $data['totalCredit'];
                                $parenntdebitTotal += $data['totalDebit'];
                                $parenttotalAmount += $data['netAmount'];
                            }
                        }

                        if (!empty($parentAccountArray)) {
                            $dataTotal = [
                                'account_id' => $parentAccount->account,
                                'account_code' => '',
                                'account' => 'parentTotal',
                                'account_name' => 'Total ' . $parentAccount->name,
                                'totalCredit' => $parentcreditTotal,
                                'totalDebit' => $parenntdebitTotal,
                                'netAmount' => $parenttotalAmount,
                            ];

                            $parentAccountArrayTotal[] = $dataTotal;
                            $totalArray = array_merge($parentAccountArray, $parentAccountArrayTotal);
                            $totalParentAccountArray[] = $totalArray;
                        }

                    }

                }
                if ($totalParentAccountArray != []) {
                    $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                    $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                    $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                    $accounts->leftjoin('chart_of_account_parents', 'chart_of_accounts.name', 'chart_of_account_parents.name');
                    $accounts->where('chart_of_accounts.type', $type->id);
                    $accounts->where('chart_of_accounts.sub_type', $subType->id);
                    $accounts->where('chart_of_account_parents.account');
                    $accounts->where('chart_of_accounts.parent', '=', 'chart_of_account_parents.id');
                    $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                    $accounts->where('transaction_lines.date', '>=', $start);
                    $accounts->where('transaction_lines.date', '<=', $end);
                    $accounts->groupBy('account_id');
                    $accounts = $accounts->get()->toArray();
                } else {
                    $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                    $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                    $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                    $accounts->where('chart_of_accounts.type', $type->id);
                    $accounts->where('chart_of_accounts.sub_type', $subType->id);
                    $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                    $accounts->where('transaction_lines.date', '>=', $start);
                    $accounts->where('transaction_lines.date', '<=', $end);
                    $accounts->groupBy('account_id');
                    $accounts = $accounts->get()->toArray();
                }

                $totalBalance = 0;
                $creditTotal = 0;
                $debitTotal = 0;
                $totalAmount = 0;
                $accountArray = [];

                foreach ($accounts as $account) {
                    $Balance = $account['totalCredit'] - $account['totalDebit'];
                    $totalBalance += $Balance;
                    if ($Balance != 0) {
                        $data['account_id'] = $account['id'];
                        $data['account_code'] = $account['code'];
                        $data['account_name'] = $account['name'];
                        $data['account'] = '';
                        $data['totalCredit'] = 0;
                        $data['totalDebit'] = 0;
                        $data['netAmount'] = $Balance;
                        $accountArray[][] = $data;
                        $creditTotal += $data['totalCredit'];
                        $debitTotal += $data['totalDebit'];
                        $totalAmount += $data['netAmount'];
                    }
                }

                $totalAccountArray = [];
                if ($accountArray != []) {
                    $dataTotal['account_id'] = '';
                    $dataTotal['account_code'] = '';
                    $dataTotal['account'] = '';
                    $dataTotal['account_name'] = 'Total ' . $subType->name;
                    $dataTotal['totalCredit'] = $creditTotal;
                    $dataTotal['totalDebit'] = $debitTotal;

                    if (isset($totalParentAccountArray) && $totalParentAccountArray != []) {

                        $netAmount = 0;

                        foreach ($totalParentAccountArray as $innerArray) {
                            $lastElement = end($innerArray);

                            $netAmount += $lastElement['netAmount'];
                        }

                        $dataTotal['netAmount'] = $netAmount + $totalAmount;
                    } else {
                        $dataTotal['netAmount'] = $totalAmount;
                    }
                    $accountArrayTotal[][] = $dataTotal;
                    $totalAccountArray = array_merge($totalParentAccountArray, $accountArray, $accountArrayTotal);

                } elseif ($totalParentAccountArray != []) {
                    $dataTotal['account_id'] = '';
                    $dataTotal['account_code'] = '';
                    $dataTotal['account'] = '';
                    $dataTotal['account_name'] = 'Total ' . $subType->name;
                    $dataTotal['totalCredit'] = $creditTotal;
                    $dataTotal['totalDebit'] = $debitTotal;
                    $netAmount = 0;

                    foreach ($totalParentAccountArray as $innerArray) {
                        $lastElement = end($innerArray);

                        $netAmount += $lastElement['netAmount'];
                    }
                    $dataTotal['netAmount'] = $netAmount;
                    $accountArrayTotal[][] = $dataTotal;
                    $totalAccountArray = array_merge($totalParentAccountArray, $accountArrayTotal);
                }

                if ($totalAccountArray != []) {
                    $subTypeData['subType'] = ($totalAccountArray != []) ? $subType->name : '';
                    $subTypeData['account'] = $totalAccountArray;
                    $subTypeArray[] = ($subTypeData['account'] != [] && $subTypeData['subType'] != []) ? $subTypeData : [];
                }
            }
            $totalAccounts[$type->name] = $subTypeArray;
        }

        $companyName = User::where('id', \Auth::user()->creatorId())->first();
        $companyName = $companyName->name;

        $name = 'balance_sheet_' . date('Y-m-d i:h:s');
        $data = Excel::download(new BalanceSheetExport($totalAccounts, $start, $end, $companyName), $name . '.xlsx');
        ob_end_clean();

        return $data;

    }

    public function profitLossExport(Request $request)
    {

        if (\Auth::user()->can('income vs expense report')) {

            if (!empty($request->start_date) && !empty($request->end_date)) {
                $start = $request->start_date;
                $end = $request->end_date;
            } else {
                $start = date('Y-01-01');
                $end = date('Y-m-d', strtotime('+1 day'));
            }

            $types = ChartOfAccountType::where('created_by', \Auth::user()->creatorId())->whereIn('name', ['Income', 'Expenses', 'Costs of Goods Sold'])->get();
            $subTypeArray = [];
            $totalAccounts = [];
            foreach ($types as $type) {

                $parentAccounts = ChartOfAccountParent::where('type', $type->id)->where('created_by', \Auth::user()->creatorId())->get();

                $totalParentAccountArray = [];
                if ($parentAccounts->isNotEmpty()) {
                    foreach ($parentAccounts as $parentAccount) {
                        $totalArray = [];
                        $parentAccountArray = [];
                        $parentAccountArrayTotal = [];

                        $parentAccs = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                        $parentAccs->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                        $parentAccs->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                        $parentAccs->where('chart_of_accounts.type', $type->id);
                        $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                        $parentAccs->where('transaction_lines.created_by', \Auth::user()->creatorId());
                        $parentAccs->where('transaction_lines.date', '>=', $start);
                        $parentAccs->where('transaction_lines.date', '<=', $end);
                        $parentAccs->groupBy('account_id');
                        $parentAccs = $parentAccs->get()->toArray();

                        $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                        $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                        $accounts->where('chart_of_accounts.type', $type->id);
                        $accounts->where('chart_of_accounts.parent', $parentAccount->id);
                        $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                        $accounts->where('transaction_lines.date', '>=', $start);
                        $accounts->where('transaction_lines.date', '<=', $end);
                        $accounts->groupBy('account_id');
                        $accounts = $accounts->get()->toArray();

                        if ($parentAccs == [] && $accounts != []) {

                            $parentAccs = ChartOfAccount::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('0 as totalDebit'), \DB::raw('0 as totalCredit'));
                            $parentAccs->leftjoin('chart_of_account_parents', 'chart_of_accounts.id', 'chart_of_account_parents.account');
                            $parentAccs->where('chart_of_accounts.type', $type->id);
                            $parentAccs->where('chart_of_accounts.name', $parentAccount->name);
                            $parentAccs->where('chart_of_accounts.created_by', \Auth::user()->creatorId());
                            $parentAccs = $parentAccs->get()->toArray();
                        }
                        if ($parentAccs != [] && $accounts == []) {

                            $parentAccs = [];
                        }

                        $parenttotalBalance = 0;
                        $parentcreditTotal = 0;
                        $parenntdebitTotal = 0;
                        $parenttotalAmount = 0;

                        foreach ($parentAccs as $account) {
                            $Balance = $account['totalCredit'] - $account['totalDebit'];
                            $parenttotalBalance += $Balance;

                            $data = [
                                'account_id' => $account['id'],
                                'account_code' => $account['code'],
                                'account_name' => $account['name'],
                                'account' => 'parent',
                                'totalCredit' => 0,
                                'totalDebit' => 0,
                                'netAmount' => $Balance,
                            ];

                            $parentAccountArray[] = $data;
                            $parentcreditTotal += $data['totalCredit'];
                            $parenntdebitTotal += $data['totalDebit'];
                            $parenttotalAmount += $data['netAmount'];
                        }

                        foreach ($accounts as $account) {
                            $Balance = $account['totalCredit'] - $account['totalDebit'];
                            $parenttotalBalance += $Balance;

                            if ($Balance != 0) {
                                $data = [
                                    'account_id' => $account['id'],
                                    'account_code' => $account['code'],
                                    'account_name' => $account['name'],
                                    'account' => 'subAccount',
                                    'totalCredit' => 0,
                                    'totalDebit' => 0,
                                    'netAmount' => $Balance,
                                ];

                                $parentAccountArray[] = $data;
                                $parentcreditTotal += $data['totalCredit'];
                                $parenntdebitTotal += $data['totalDebit'];
                                $parenttotalAmount += $data['netAmount'];
                            }
                        }

                        if (!empty($parentAccountArray)) {
                            $dataTotal = [
                                'account_id' => $parentAccount->account,
                                'account_code' => '',
                                'account' => 'parentTotal',
                                'account_name' => 'Total ' . $parentAccount->name,
                                'totalCredit' => $parentcreditTotal,
                                'totalDebit' => $parenntdebitTotal,
                                'netAmount' => $parenttotalAmount,
                            ];

                            $parentAccountArrayTotal[] = $dataTotal;
                            $totalArray = array_merge($parentAccountArray, $parentAccountArrayTotal);
                            $totalParentAccountArray[] = $totalArray;
                        }

                    }

                }

                if ($totalParentAccountArray != []) {
                    $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                    $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                    $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                    $accounts->leftjoin('chart_of_account_parents', 'chart_of_accounts.name', 'chart_of_account_parents.name');
                    $accounts->where('chart_of_accounts.type', $type->id);
                    $accounts->where('chart_of_account_parents.account');
                    $accounts->where('chart_of_accounts.parent', '=', 'chart_of_account_parents.id');
                    $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                    $accounts->where('transaction_lines.date', '>=', $start);
                    $accounts->where('transaction_lines.date', '<=', $end);
                    $accounts->groupBy('account_id');
                    $accounts = $accounts->get()->toArray();
                } else {
                    $accounts = TransactionLines::select('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', \DB::raw('sum(debit) as totalDebit'), \DB::raw('sum(credit) as totalCredit'));
                    $accounts->leftjoin('chart_of_accounts', 'transaction_lines.account_id', 'chart_of_accounts.id');
                    $accounts->leftjoin('chart_of_account_types', 'chart_of_accounts.type', 'chart_of_account_types.id');
                    $accounts->where('chart_of_accounts.type', $type->id);
                    $accounts->where('transaction_lines.created_by', \Auth::user()->creatorId());
                    $accounts->where('transaction_lines.date', '>=', $start);
                    $accounts->where('transaction_lines.date', '<=', $end);
                    $accounts->groupBy('account_id');
                    $accounts = $accounts->get()->toArray();
                }

                $totalBalance = 0;
                $creditTotal = 0;
                $debitTotal = 0;
                $totalAmount = 0;
                $accountArray = [];
                foreach ($accounts as $account) {
                    $Balance = $account['totalCredit'] - $account['totalDebit'];
                    $totalBalance += $Balance;
                    if ($Balance != 0) {
                        $data['account_id'] = $account['id'];
                        $data['account_code'] = $account['code'];
                        $data['account_name'] = $account['name'];
                        $data['account'] = '';
                        $data['totalCredit'] = 0;
                        $data['totalDebit'] = 0;
                        $data['netAmount'] = $Balance;
                        $accountArray[][] = $data;
                        $creditTotal += $data['totalCredit'];
                        $debitTotal += $data['totalDebit'];
                        $totalAmount += $data['netAmount'];
                    }
                }

                $totalAccountArray = [];

                if ($accountArray != []) {
                    $dataTotal['account_id'] = '';
                    $dataTotal['account_code'] = '';
                    $dataTotal['account'] = '';
                    $dataTotal['account_name'] = 'Total ' . $type->name;
                    $dataTotal['totalCredit'] = $creditTotal;
                    $dataTotal['totalDebit'] = $debitTotal;
                    $dataTotal['netAmount'] = $totalAmount;
                    $accountArray[][] = $dataTotal;
                    $totalAccountArray = array_merge($totalParentAccountArray, $accountArray);

                } elseif ($totalParentAccountArray != []) {

                    $dataTotal['account_id'] = '';
                    $dataTotal['account_code'] = '';
                    $dataTotal['account'] = '';
                    $dataTotal['account_name'] = 'Total ' . $type->name;
                    $dataTotal['totalCredit'] = $creditTotal;
                    $dataTotal['totalDebit'] = $debitTotal;
                    $netAmount = 0;
                    foreach ($totalParentAccountArray as $innerArray) {
                        $lastElement = end($innerArray);

                        $netAmount += $lastElement['netAmount'];
                    }
                    $dataTotal['netAmount'] = $netAmount;
                    $accountArrayTotal[][] = $dataTotal;
                    $totalAccountArray = array_merge($totalParentAccountArray, $accountArrayTotal);
                }
                if ($totalAccountArray != []) {
                    $subTypeData['Type'] = ($totalAccountArray != []) ? $type->name : '';
                    $subTypeData['account'] = $totalAccountArray;
                    $subTypeArray[] = ($subTypeData['account'] != []) ? $subTypeData : [];
                }
                $totalAccounts = $subTypeArray;
            }
            $companyName = User::where('id', \Auth::user()->creatorId())->first();
            $companyName = $companyName->name;

            $name = 'profit & loss_' . date('Y-m-d i:h:s');
            $data = Excel::download(new ProfitLossExport($totalAccounts, $start, $end, $companyName), $name . '.xlsx');
            ob_end_clean();

            return $data;
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function salesReport(Request $request)
    {
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $start = $request->start_date;
            $end = $request->end_date;
        } else {
            $start = date('Y-01-01');
            $end = date('Y-m-d', strtotime('+1 day'));
        }
        $invoiceItems = InvoiceProduct::select('product_services.name', \DB::raw('sum(invoice_products.quantity) as quantity'), \DB::raw('sum(invoice_products.price * invoice_products.quantity) as price'), \DB::raw('sum(invoice_products.price)/sum(invoice_products.quantity) as avg_price'));
        $invoiceItems->leftjoin('product_services', 'product_services.id', 'invoice_products.product_id');
        $invoiceItems->leftjoin('invoices', 'invoices.id', 'invoice_products.invoice_id');
        $invoiceItems->where('product_services.created_by', \Auth::user()->creatorId());
        $invoiceItems->where('invoices.issue_date', '>=', $start);
        $invoiceItems->where('invoices.issue_date', '<=', $end);
        $invoiceItems->groupBy('invoice_products.product_id');
        $invoiceItems = $invoiceItems->get()->toArray();

        $invoiceCustomeres = Invoice::select('customers.name', \DB::raw('count(DISTINCT invoices.customer_id, invoice_products.invoice_id) as invoice_count'))
            ->selectRaw('sum((invoice_products.price * invoice_products.quantity) - invoice_products.discount) as price')
            ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM invoice_products
             LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
             WHERE invoice_products.invoice_id = invoices.id) as total_tax')
            ->leftJoin('customers', 'customers.id', 'invoices.customer_id')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', 'invoices.id')
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->where('invoices.issue_date', '>=', $start)
            ->where('invoices.issue_date', '<=', $end)
            ->groupBy('invoices.invoice_id')
            ->get()
            ->toArray();
        $mergedArray = [];
        foreach ($invoiceCustomeres as $item) {
            $name = $item["name"];

            if (!isset($mergedArray[$name])) {
                $mergedArray[$name] = [
                    "name" => $name,
                    "invoice_count" => 0,
                    "price" => 0.0,
                    "total_tax" => 0.0,
                ];
            }

            $mergedArray[$name]["invoice_count"] += $item["invoice_count"];
            $mergedArray[$name]["price"] += $item["price"];
            $mergedArray[$name]["total_tax"] += $item["total_tax"];
        }
        $invoiceCustomers = array_values($mergedArray);

        $filter['startDateRange'] = $start;
        $filter['endDateRange'] = $end;

        return view('report.sales_report', compact('filter', 'invoiceItems', 'invoiceCustomers'));
    }

    public function salesReportExport(Request $request)
    {
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $start = $request->start_date;
            $end = $request->end_date;
        } else {
            $start = date('Y-01-01');
            $end = date('Y-m-d', strtotime('+1 day'));
        }
        if ($request->report == '#item') {
            $invoiceItems = InvoiceProduct::select('product_services.name', \DB::raw('sum(invoice_products.quantity) as quantity'), \DB::raw('sum(invoice_products.price * invoice_products.quantity) as price'), \DB::raw('sum(invoice_products.price)/sum(invoice_products.quantity) as avg_price'));
            $invoiceItems->leftjoin('product_services', 'product_services.id', 'invoice_products.product_id');
            $invoiceItems->leftjoin('invoices', 'invoices.id', 'invoice_products.invoice_id');
            $invoiceItems->where('product_services.created_by', \Auth::user()->creatorId());
            $invoiceItems->where('invoices.issue_date', '>=', $start);
            $invoiceItems->where('invoices.issue_date', '<=', $end);
            $invoiceItems->groupBy('invoice_products.product_id');
            $invoiceItems = $invoiceItems->get()->toArray();

            $reportName = 'Item';
        } else {
            $invoiceCustomeres = Invoice::select('customers.name', \DB::raw('count(DISTINCT invoices.customer_id, invoice_products.invoice_id) as invoice_count'))
                ->selectRaw('sum((invoice_products.price * invoice_products.quantity) - invoice_products.discount) as price')
                ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM invoice_products
             LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
             WHERE invoice_products.invoice_id = invoices.id) as total_tax')
                ->leftJoin('customers', 'customers.id', 'invoices.customer_id')
                ->leftJoin('invoice_products', 'invoice_products.invoice_id', 'invoices.id')
                ->where('invoices.created_by', \Auth::user()->creatorId())
                ->where('invoices.issue_date', '>=', $start)
                ->where('invoices.issue_date', '<=', $end)
                ->groupBy('invoices.invoice_id')
                ->get()
                ->toArray();
            $mergedArray = [];
            foreach ($invoiceCustomeres as $item) {
                $name = $item["name"];

                if (!isset($mergedArray[$name])) {
                    $mergedArray[$name] = [
                        "name" => $name,
                        "invoice_count" => 0,
                        "price" => 0.0,
                        "total_tax" => 0.0,
                    ];
                }

                $mergedArray[$name]["invoice_count"] += $item["invoice_count"];
                $mergedArray[$name]["price"] += $item["price"];
                $mergedArray[$name]["total_tax"] += $item["total_tax"];
            }
            $invoiceItems = array_values($mergedArray);

            $reportName = 'Customer';
        }
        $companyName = User::where('id', \Auth::user()->creatorId())->first();
        $companyName = $companyName->name;

        $name = 'Sales By ' . $reportName . '_ ' . date('Y-m-d i:h:s');
        $data = Excel::download(new SalesReportExport($invoiceItems, $start, $end, $companyName, $reportName), $name . '.xlsx');
        ob_end_clean();

        return $data;

    }

    public function ReceivablesReport(Request $request)
    {
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $start = $request->start_date;
            $end = $request->end_date;
        } else {
            $start = date('Y-01-01');
            $end = date('Y-m-d', strtotime('+1 day'));
        }

        $receivableCustomers = Invoice::select('customers.name')
            ->selectRaw('sum((invoice_products.price * invoice_products.quantity) - invoice_products.discount) as price')
            ->selectRaw('sum((invoice_payments.amount)) as pay_price')
            ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM invoice_products
             LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
             WHERE invoice_products.invoice_id = invoices.id) as total_tax')
            ->selectRaw('(SELECT SUM(credit_notes.amount) FROM credit_notes
             WHERE credit_notes.invoice = invoices.id) as credit_price')
            ->leftJoin('customers', 'customers.id', 'invoices.customer_id')
            ->leftJoin('invoice_payments', 'invoice_payments.invoice_id', 'invoices.id')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', 'invoices.id')
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->where('invoices.issue_date', '>=', $start)
            ->where('invoices.issue_date', '<=', $end)
            ->groupBy('invoices.invoice_id')
            ->get()
            ->toArray();

        $receivableSummariesInvoice = Invoice::select('customers.name')
            ->selectRaw('(invoices.invoice_id) as invoice')
            ->selectRaw('sum((invoice_products.price * invoice_products.quantity) - invoice_products.discount) as price')
            ->selectRaw('sum((invoice_payments.amount)) as pay_price')
            ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM invoice_products
             LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
             WHERE invoice_products.invoice_id = invoices.id) as total_tax')
            ->selectRaw('invoices.issue_date as issue_date')
            ->selectRaw('invoices.status as status')
            ->leftJoin('customers', 'customers.id', 'invoices.customer_id')
            ->leftJoin('invoice_payments', 'invoice_payments.invoice_id', 'invoices.id')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', 'invoices.id')
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->where('invoices.issue_date', '>=', $start)
            ->where('invoices.issue_date', '<=', $end)
            ->groupBy('invoices.invoice_id')
            ->get()
            ->toArray();

        $receivableSummariesCredit = CreditNote::select('customers.name')
            ->selectRaw('null as invoice')
            ->selectRaw('(credit_notes.amount) as price')
            ->selectRaw('0 as pay_price')
            ->selectRaw('0 as total_tax')
            ->selectRaw('credit_notes.date as issue_date')
            ->selectRaw('5 as status')
            ->leftJoin('customers', 'customers.id', 'credit_notes.customer')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', 'credit_notes.invoice')
            ->leftJoin('invoices', 'invoices.id', 'credit_notes.invoice')
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->where('credit_notes.date', '>=', $start)
            ->where('credit_notes.date', '<=', $end)
            ->groupBy('credit_notes.id')
            ->get()
            ->toArray();

        $receivableSummaries = (array_merge($receivableSummariesCredit, $receivableSummariesInvoice));

        $receivableDetailsInvoice = Invoice::select('customers.name')
            ->selectRaw('(invoices.invoice_id) as invoice')
            ->selectRaw('sum(invoice_products.price) as price')
            ->selectRaw('(invoice_products.quantity) as quantity')
            ->selectRaw('(product_services.name) as product_name')
            ->selectRaw('invoices.issue_date as issue_date')
            ->selectRaw('invoices.status as status')
            ->leftJoin('customers', 'customers.id', 'invoices.customer_id')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', 'invoices.id')
            ->leftJoin('product_services', 'product_services.id', 'invoice_products.product_id')
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->where('invoices.issue_date', '>=', $start)
            ->where('invoices.issue_date', '<=', $end)
            ->groupBy('invoices.invoice_id', 'product_services.name')
            ->get()
            ->toArray();

        $receivableDetailsCredit = CreditNote::select('customers.name')
            ->selectRaw('null as invoice')
            ->selectRaw('(credit_notes.id) as invoices')
            ->selectRaw('(credit_notes.amount) as price')
            ->selectRaw('(product_services.name) as product_name')
            ->selectRaw('credit_notes.date as issue_date')
            ->selectRaw('5 as status')
            ->leftJoin('customers', 'customers.id', 'credit_notes.customer')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', 'credit_notes.invoice')
            ->leftJoin('product_services', 'product_services.id', 'invoice_products.product_id')
            ->leftJoin('invoices', 'invoices.id', 'credit_notes.invoice')
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->where('credit_notes.date', '>=', $start)
            ->where('credit_notes.date', '<=', $end)
            ->groupBy('credit_notes.id', 'product_services.name')
            ->get()
            ->toArray();

        $mergedArray = [];
        foreach ($receivableDetailsCredit as $item) {
            $invoices = $item["invoices"];

            if (!isset($mergedArray[$invoices])) {
                $mergedArray[$invoices] = [
                    "name" => $item["name"],
                    "invoice" => $item["invoice"],
                    "invoices" => $invoices,
                    "price" => $item["price"],
                    "quantity" => 0,
                    "product_name" => "",
                    "issue_date" => "",
                    "status" => 0,
                ];
            }

            if (!strstr($mergedArray[$invoices]["product_name"], $item["product_name"])) {
                if ($mergedArray[$invoices]["product_name"] !== "") {
                    $mergedArray[$invoices]["product_name"] .= ", ";
                }
                $mergedArray[$invoices]["product_name"] .= $item["product_name"];
            }

            $mergedArray[$invoices]["issue_date"] = $item["issue_date"];
            $mergedArray[$invoices]["status"] = $item["status"];
        }

        $receivableDetailsCredits = array_values($mergedArray);

        $receivableDetails = (array_merge($receivableDetailsInvoice, $receivableDetailsCredits));

        $agingSummary = Invoice::select('customers.name', 'invoices.due_date as due_date', 'invoices.status as status', 'invoices.invoice_id as invoice_id')
            ->selectRaw('sum((invoice_products.price * invoice_products.quantity) - invoice_products.discount) as price')
            ->selectRaw('sum((invoice_payments.amount)) as pay_price')
            ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM invoice_products
             LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
             WHERE invoice_products.invoice_id = invoices.id) as total_tax')
            ->selectRaw('(SELECT SUM(credit_notes.amount) FROM credit_notes
             WHERE credit_notes.invoice = invoices.id) as credit_price')
            ->leftJoin('customers', 'customers.id', 'invoices.customer_id')
            ->leftJoin('invoice_payments', 'invoice_payments.invoice_id', 'invoices.id')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', 'invoices.id')
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->where('invoices.issue_date', '>=', $start)
            ->where('invoices.issue_date', '<=', $end)
            ->groupBy('invoices.invoice_id')
            ->get()
            ->toArray();

        $agingSummaries = [];

        $today = date("Y-m-d");
        foreach ($agingSummary as $item) {
            $name = $item["name"];
            $price = floatval(($item["price"] + $item['total_tax']) - ($item['pay_price'] + $item['credit_price']));
            $dueDate = $item["due_date"];

            if (!isset($agingSummaries[$name])) {
                $agingSummaries[$name] = [
                    'current' => 0.0,
                    "1_15_days" => 0.0,
                    "16_30_days" => 0.0,
                    "31_45_days" => 0.0,
                    "greater_than_45_days" => 0.0,
                    "total_due" => 0.0,
                ];
            }

            $daysDifference = date_diff(date_create($dueDate), date_create($today));
            $daysDifference = $daysDifference->format("%R%a");

            if ($daysDifference <= 0) {
                $agingSummaries[$name]["current"] += $price;
            } elseif ($daysDifference >= 1 && $daysDifference <= 15) {
                $agingSummaries[$name]["1_15_days"] += $price;
            } elseif ($daysDifference >= 16 && $daysDifference <= 30) {
                $agingSummaries[$name]["16_30_days"] += $price;
            } elseif ($daysDifference >= 31 && $daysDifference <= 45) {
                $agingSummaries[$name]["31_45_days"] += $price;
            } elseif ($daysDifference > 45) {
                $agingSummaries[$name]["greater_than_45_days"] += $price;
            }

            $agingSummaries[$name]["total_due"] += $price;
        }

        $currents = [];
        $days1to15 = [];
        $days16to30 = [];
        $days31to45 = [];
        $moreThan45 = [];

        foreach ($agingSummary as $item) {
            $dueDate = $item["due_date"];
            $price = floatval($item["price"]);
            $total_tax = floatval($item["total_tax"]);
            $credit_price = floatval($item["credit_price"]);
            $payPrice = $item["pay_price"] ? floatval($item["pay_price"]) : 0;

            $daysDifference = date_diff(date_create($dueDate), date_create($today));
            $daysDifference = $daysDifference->format("%R%a");
            $balanceDue = ($price + $total_tax) - ($payPrice + $credit_price);
            $totalPrice = $price + $total_tax;
            if ($daysDifference <= 0) {
                $item["total_price"] = $totalPrice;
                $item["balance_due"] = $balanceDue;
                $currents[] = $item;
            } elseif ($daysDifference >= 1 && $daysDifference <= 15) {
                $item["total_price"] = $totalPrice;
                $item["balance_due"] = $balanceDue;
                $item['age'] = intval(str_replace(array('+', '-'), '', $daysDifference));
                $days1to15[] = $item;
            } elseif ($daysDifference >= 16 && $daysDifference <= 30) {
                $item["total_price"] = $totalPrice;
                $item["balance_due"] = $balanceDue;
                $item['age'] = intval(str_replace(array('+', '-'), '', $daysDifference));
                $days16to30[] = $item;
            } elseif ($daysDifference >= 31 && $daysDifference <= 45) {
                $item["total_price"] = $totalPrice;
                $item["balance_due"] = $balanceDue;
                $item['age'] = intval(str_replace(array('+', '-'), '', $daysDifference));
                $days31to45[] = $item;
            } else {
                $item["total_price"] = $totalPrice;
                $item["balance_due"] = $balanceDue;
                $item['age'] = intval(str_replace(array('+', '-'), '', $daysDifference));
                $moreThan45[] = $item;
            }
        }

        $filter['startDateRange'] = $start;
        $filter['endDateRange'] = $end;

        return view('report.receivable_report', compact('filter', 'receivableCustomers', 'receivableSummaries', 'receivableDetails', 'agingSummaries', 'currents', 'days1to15', 'days16to30', 'days31to45', 'moreThan45'));
    }

    public function ReceivablesExport(Request $request)
    {
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $start = $request->start_date;
            $end = $request->end_date;
        } else {
            $start = date('Y-01-01');
            $end = date('Y-m-d', strtotime('+1 day'));
        }

        $receivableCustomers = Invoice::select('customers.name')
            ->selectRaw('sum((invoice_products.price * invoice_products.quantity) - invoice_products.discount) as price')
            ->selectRaw('sum((invoice_payments.amount)) as pay_price')
            ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM invoice_products
             LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
             WHERE invoice_products.invoice_id = invoices.id) as total_tax')
            ->selectRaw('(SELECT SUM(credit_notes.amount) FROM credit_notes
             WHERE credit_notes.invoice = invoices.id) as credit_price')
            ->leftJoin('customers', 'customers.id', 'invoices.customer_id')
            ->leftJoin('invoice_payments', 'invoice_payments.invoice_id', 'invoices.id')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', 'invoices.id')
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->where('invoices.issue_date', '>=', $start)
            ->where('invoices.issue_date', '<=', $end)
            ->groupBy('invoices.invoice_id')
            ->get()
            ->toArray();

        $companyName = User::where('id', \Auth::user()->creatorId())->first();
        $companyName = $companyName->name;

        $name = 'Receivable Report_ ' . date('Y-m-d i:h:s');
        $data = Excel::download(new ReceivableExport($receivableCustomers, $start, $end, $companyName), $name . '.xlsx');
        ob_end_clean();

        return $data;
    }

    public function PayablesReport(Request $request)
    {
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $start = $request->start_date;
            $end = $request->end_date;
        } else {
            $start = date('Y-01-01');
            $end = date('Y-m-d', strtotime('+1 day'));
        }

        $payableVendors = Bill::select('venders.name')
            ->selectRaw('sum((bill_products.price * bill_products.quantity) - bill_products.discount) as price')
            ->selectRaw('sum((bill_payments.amount)) as pay_price')
            ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM bill_products
         LEFT JOIN taxes ON FIND_IN_SET(taxes.id, bill_products.tax) > 0
         WHERE bill_products.bill_id = bills.id) as total_tax')
            ->selectRaw('(SELECT SUM(debit_notes.amount) FROM debit_notes
         WHERE debit_notes.bill = bills.id) as debit_price')
            ->leftJoin('venders', 'venders.id', 'bills.vender_id')
            ->leftJoin('bill_payments', 'bill_payments.bill_id', 'bills.id')
            ->leftJoin('bill_products', 'bill_products.bill_id', 'bills.id')
            ->where('bills.created_by', \Auth::user()->creatorId())
            ->whereNotIn('bills.user_type', ['employee', 'customer'])
            ->where('bills.bill_date', '>=', $start)
            ->where('bills.bill_date', '<=', $end)
            ->groupBy('bills.bill_id')
            ->get()
            ->toArray();

        $payableSummariesBill = Bill::select('venders.name')
            ->selectRaw('(bills.bill_id) as bill')
            ->selectRaw('(bills.type) as type')
            ->selectRaw('sum((bill_products.price * bill_products.quantity) - bill_products.discount) as price')
            ->selectRaw('sum((bill_payments.amount)) as pay_price')
            ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM bill_products
         LEFT JOIN taxes ON FIND_IN_SET(taxes.id, bill_products.tax) > 0
         WHERE bill_products.bill_id = bills.id) as total_tax')
            ->selectRaw('bills.bill_date as bill_date')
            ->selectRaw('bills.status as status')
            ->leftJoin('venders', 'venders.id', 'bills.vender_id')
            ->leftJoin('bill_payments', 'bill_payments.bill_id', 'bills.id')
            ->leftJoin('bill_products', 'bill_products.bill_id', 'bills.id')
            ->where('bills.created_by', \Auth::user()->creatorId())
            ->whereNotIn('bills.user_type', ['employee', 'customer'])
            ->where('bills.bill_date', '>=', $start)
            ->where('bills.bill_date', '<=', $end)
            ->groupBy('bills.id')
            ->get()
            ->toArray();

        $payableSummariesDebit = DebitNote::select('venders.name')
            ->selectRaw('null as bill')
            ->selectRaw('debit_notes.amount as price')
            ->selectRaw('0 as pay_price')
            ->selectRaw('0 as total_tax')
            ->selectRaw('debit_notes.date as bill_date')
            ->selectRaw('5 as status')
            ->leftJoin('venders', 'venders.id', 'debit_notes.vendor')
            ->leftJoin('bill_products', 'bill_products.bill_id', 'debit_notes.bill')
            ->leftJoin('bills', 'bills.id', 'debit_notes.bill')
            ->where('bills.created_by', \Auth::user()->creatorId())
            ->where('debit_notes.date', '>=', $start)
            ->where('debit_notes.date', '<=', $end)
            ->groupBy('debit_notes.id')
            ->get()
            ->toArray();

        $payableSummaries = (array_merge($payableSummariesDebit, $payableSummariesBill));

        $payableDetailsBill = Bill::select('venders.name')
            ->selectRaw('(bills.bill_id) as bill')
            ->selectRaw('(bills.type) as type')
            ->selectRaw('sum(bill_products.price) as price')
            ->selectRaw('(bill_products.quantity) as quantity')
            ->selectRaw('(product_services.name) as product_name')
            ->selectRaw('bills.bill_date as bill_date')
            ->selectRaw('bills.status as status')
            ->leftJoin('venders', 'venders.id', 'bills.vender_id')
            ->leftJoin('bill_products', 'bill_products.bill_id', 'bills.id')
            ->leftJoin('product_services', 'product_services.id', 'bill_products.product_id')
            ->where('bills.created_by', \Auth::user()->creatorId())
            ->whereNotIn('bills.user_type', ['employee', 'customer'])
            ->where('bills.bill_date', '>=', $start)
            ->where('bills.bill_date', '<=', $end)
            ->groupBy('bills.bill_id', 'product_services.name')
            ->get()
            ->toArray();

        $payableDetailsDebit = DebitNote::select('venders.name')
            ->selectRaw('null as bill')
            ->selectRaw('(debit_notes.id) as bills')
            ->selectRaw('(debit_notes.amount) as price')
            ->selectRaw('(product_services.name) as product_name')
            ->selectRaw('debit_notes.date as bill_date')
            ->selectRaw('5 as status')
            ->leftJoin('venders', 'venders.id', 'debit_notes.vendor')
            ->leftJoin('bill_products', 'bill_products.bill_id', 'debit_notes.bill')
            ->leftJoin('product_services', 'product_services.id', 'bill_products.product_id')
            ->leftJoin('bills', 'bills.id', 'debit_notes.bill')
            ->where('bills.created_by', \Auth::user()->creatorId())
            ->where('debit_notes.date', '>=', $start)
            ->where('debit_notes.date', '<=', $end)
            ->groupBy('debit_notes.id', 'product_services.name')
            ->get()
            ->toArray();

        $mergedArray = [];
        foreach ($payableDetailsDebit as $item) {
            $invoices = $item["bills"];

            if (!isset($mergedArray[$invoices])) {
                $mergedArray[$invoices] = [
                    "name" => $item["name"],
                    "bill" => $item["bill"],
                    "bills" => $invoices,
                    "price" => $item["price"],
                    "quantity" => 0,
                    "product_name" => "",
                    "bill_date" => "",
                    "status" => 0,
                ];
            }

            if (!strstr($mergedArray[$invoices]["product_name"], $item["product_name"])) {
                if ($mergedArray[$invoices]["product_name"] !== "") {
                    $mergedArray[$invoices]["product_name"] .= ", ";
                }
                $mergedArray[$invoices]["product_name"] .= $item["product_name"];
            }

            $mergedArray[$invoices]["bill_date"] = $item["bill_date"];
            $mergedArray[$invoices]["status"] = $item["status"];
        }

        $payableDetailsDebits = array_values($mergedArray);

        $payableDetails = (array_merge($payableDetailsBill, $payableDetailsDebits));

        $filter['startDateRange'] = $start;
        $filter['endDateRange'] = $end;

        return view('report.payable_report', compact('filter', 'payableVendors', 'payableSummaries', 'payableDetails'));
    }

}
