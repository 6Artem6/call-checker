<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with(['client'])
//            ->whereHas('client', function ($query) {
//                $query->where('account_id', auth()->id());
//            })
            ->orderByDesc('created_at')
            ->paginate(10);

        return Inertia::render('Invoice/Index', [
            'invoices' => $invoices,
            'start' => $invoices->perPage() * ($invoices->currentPage() - 1) + 1
        ]);
    }

    public function show(Invoice $invoice)
    {
        return Inertia::render('Invoice/Show', ['invoice' => $invoice]);
    }
}
