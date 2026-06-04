<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PlugAndPayService;
use Inertia\Inertia;
use Inertia\Response;
use PlugAndPay\Sdk\Entity\Order;

class InvoiceController extends Controller
{
    public function index(PlugAndPayService $plugAndPay): Response
    {
        ['orders' => $rows] = $plugAndPay->unpaidAndReversedOrders();

        $invoices = array_map(function (array $row) {
            /** @var Order $order */
            $order = $row['order'];
            $contact = $order->billing()->contact();
            $address = $order->billing()->address();

            return [
                'id' => $order->id(),
                'invoiceNumber' => $order->invoiceNumber(),
                'customerName' => $contact->firstName().' '.$contact->lastName(),
                'email' => $contact->email(),
                'company' => $contact->company(),
                'address' => implode(', ', array_filter([
                    trim(($address->street() ?? '').' '.($address->houseNumber() ?? '')),
                    $address->zipcode(),
                    $address->city(),
                    $address->country()?->value,
                ])),
                'amount' => $order->amount(),
                'paymentStatus' => $order->payment()->status()?->value,
                'paymentMethod' => $order->payment()->method()?->value,
                'paymentUrl' => $order->payment()->url(),
                'invoiceDate' => $row['invoiceDate'],
                'plugAndPayUrl' => 'https://admin.plugandpay.com/orders/'.$order->id(),
            ];
        }, $rows);

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices,
        ]);
    }
}
