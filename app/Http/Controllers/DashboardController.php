<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MollieService;
use App\Services\QuoteService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function index(MollieService $mollie): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $firstName = explode(' ', $user->name)[0];
        $isNewUser = $user->created_at?->isToday() ?? false;

        return Inertia::render('Dashboard', [
            'greeting' => [
                'firstName' => $firstName,
                'isNewUser' => $isNewUser,
                'quote' => QuoteService::dailyQuote(),
            ],
            'transactionStats' => Inertia::defer(fn (): array => [
                'transactions' => array_map(fn (array $transaction): array => [
                    'type' => $transaction['type'],
                    'amount' => $transaction['amount'],
                    'date' => $transaction['date'],
                ], $mollie->chargebacksAndRefunds()),
            ]),
            'cashStats' => Inertia::defer(fn (): array => [
                'payments' => $mollie->paidPayments(),
            ]),
        ]);
    }
}
