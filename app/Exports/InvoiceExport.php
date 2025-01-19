<?php

namespace App\Exports;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductServiceCategory;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InvoiceExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $data = Invoice::where('created_by', \Auth::user()->creatorId())->get();

        foreach($data as $k => $invoice)
        {
            unset($invoice->id,$invoice->customer_id, $invoice->created_by, $invoice->shipping_display,$invoice->discount_apply,$invoice->created_at,$invoice->updated_at);
            $data[$k]["invoice_id"] = \Auth::user()->invoiceNumberFormat($invoice->invoice_id);
            $data[$k]['category_id'] = ProductServiceCategory::where('type', 'income')->where('id',$invoice->category_id)->first()->name;
            $data[$k]["status"]       = Invoice::$statues[$invoice->status];

        }

        return $data;
    }

    public function headings(): array
    {
        return [
            "Invoice Id",
            "Issue Date",
            "Due Date",
            "Send Date",
            "Category",
            "Ref Number",
            "Status",

        ];
    }
}
