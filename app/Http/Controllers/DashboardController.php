<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PlugAndPayService;
use App\Services\QuoteService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use PlugAndPay\Sdk\Entity\Order;

final class DashboardController extends Controller
{
    public function index(PlugAndPayService $plugAndPay): Response
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
            'invoiceStats' => Inertia::defer(function () use ($plugAndPay) {
                ['orders' => $orders] = $plugAndPay->unpaidAndReversedOrders();

                $invoices = array_map(function (array $row) {
                    /** @var Order $order */
                    $order = $row['order'];

                    return [
                        'amount' => $order->amount(),
                        'status' => $order->payment()->status()?->value,
                        'invoiceDate' => $row['invoiceDate'],
                    ];
                }, $orders);

                return ['invoices' => $invoices];
            }),
            'cashStats' => Inertia::defer(fn () => [
                'payments' => $plugAndPay->paidOrders(),
            ]),
        ]);
    }
}
