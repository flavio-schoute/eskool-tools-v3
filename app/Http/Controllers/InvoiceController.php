<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MollieService;
use Inertia\Inertia;
use Inertia\Response;

final class InvoiceController extends Controller
{
    public function index(MollieService $mollie): Response
    {
        return Inertia::render('invoices/Index', [
            'transactions' => $mollie->chargebacksAndRefunds(),
        ]);
    }
}
