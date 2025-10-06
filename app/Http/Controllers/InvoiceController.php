<?php

namespace App\Http\Controllers;

use App\Models\AiLead\Payment\Balance;
use App\Models\AiLead\Payment\Invoice;
use App\Models\AiLead\Payment\TokenUsage;
use App\Models\AiLead\Payment\Transaction;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with(['client'])
            ->whereHas('client', function ($query) {
                $query->where('account_id', auth()->guard('api')->user()->oauth2->account_id);
            })
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

    public function balance()
    {
        $clientId = auth()->guard('api')->user()->oauth2->account_id;

        $balance = Balance::firstOrCreate(
            ['client_id' => $clientId],
            [
                'amount_rub' => 0,
                'margin' => 1.2,
                'min_deduction' => 1,
                'low_balance_threshold' => 100,
            ]
        );

        $transactions = Transaction::where('client_id', $clientId)
            ->orderByDesc('created_at')
            ->get(); // берем без пагинации для объединения

        $merged = [];
        foreach ($transactions as $transaction) {
            if (!empty($merged)) {
                $last = &$merged[count($merged) - 1];
                // если разница между транзакциями <= 5 секунд — объединяем
                if ($transaction->created_at->diffInSeconds($last->created_at) <= 5) {
                    // объединяем суммы и описание
                    $last->amount += $transaction->amount;
                    $last->description .= ' / ' . $transaction->description;
                    continue;
                }
            }
            // иначе добавляем как отдельную запись
            $merged[] = $transaction;
        }

        // пагинация после объединения
        $perPage = 20;
        $page = request()->get('page', 1);
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($merged, ($page - 1) * $perPage, $perPage),
            count($merged),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $tokenUsage = TokenUsage::where('client_id', $clientId)
            ->selectRaw('SUM(total_tokens) as tokens, SUM(usd_cost) as usd')
            ->first();

        return inertia('Invoice/Balance', [
            'balance' => $balance,
            'transactions' => $paginated,
            'tokenUsage' => $tokenUsage,
        ]);
    }

}
