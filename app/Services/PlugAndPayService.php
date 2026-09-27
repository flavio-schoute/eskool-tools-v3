<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PlugAndPay\Sdk\Director\BodyTo\BodyToOrder;
use PlugAndPay\Sdk\Entity\Order;
use PlugAndPay\Sdk\Enum\InvoiceStatus;
use PlugAndPay\Sdk\Enum\OrderIncludes;
use PlugAndPay\Sdk\Enum\PaymentStatus;
use PlugAndPay\Sdk\Filters\OrderFilter;
use PlugAndPay\Sdk\Service\Client;
use PlugAndPay\Sdk\Support\Parameters;

final class PlugAndPayService
{
    public const PER_PAGE = 25;

    private const MAX_RESULTS = 500;

    private Client $client;

    public function __construct()
    {
        $this->client = new Client(accessToken: config('services.plug_and_pay.token'));
    }

    /**
     * Haalt twee typen orders op:
     * 1. Betaald geweest maar gestorneerd (REVERSED)
     * 2. Factuur aangemaakt (FINAL) maar nog niet betaald (OPEN)
     *
     * Sortering en paginering worden door de controller gedaan na optioneel filteren.
     *
     * @return array{orders: array<int, array{order: Order, invoiceDate: string}>, perPage: int}
     */
    public function unpaidAndReversedOrders(): array
    {
        $reversed = $this->fetchAll(
            (new OrderFilter)->paymentStatus(PaymentStatus::REVERSED)
        );

        $unpaid = $this->fetchAll(
            (new OrderFilter)
                ->paymentStatus(PaymentStatus::OPEN)
                ->invoiceStatus(InvoiceStatus::FINAL)
        );

        $all = array_merge($reversed, $unpaid);

        // Standaard sortering: nieuwste factuur bovenaan.
        usort($all, fn (array $a, array $b) => $b['order']->createdAt() <=> $a['order']->createdAt());

        return [
            'orders' => $all,
            'perPage' => self::PER_PAGE,
        ];
    }

    /**
     * Haalt betaalde orders op en geeft per order het bedrag (ex btw) en betaaldatum terug.
     *
     * @return array<int, array{amount: float, paidAt: string}>
     */
    public function paidOrders(): array
    {
        $rows = $this->fetchAll(
            (new OrderFilter)->paymentStatus(PaymentStatus::PAID)
        );

        return array_map(function (array $row) {
            /** @var Order $order */
            $order = $row['order'];
            $paidAt = $order->payment()->paidAt()?->format('Y-m-d') ?? $row['invoiceDate'];

            return [
                'amount' => $order->amount(),
                'paidAt' => $paidAt,
            ];
        }, $rows);
    }

    /**
     * @return array<int, array{order: Order, invoiceDate: string}>
     */
    private function fetchAll(OrderFilter $filter): array
    {
        $parameters = $filter->limit(self::MAX_RESULTS)->parameters();
        $parameters['include'] = [OrderIncludes::BILLING, OrderIncludes::PAYMENT];

        $response = $this->client->get('/v2/orders'.Parameters::toString($parameters));
        $rows = $response->body()['data'];

        return array_map(function (array $row) {
            $order = BodyToOrder::build($row);
            $invoiceDate = isset($row['invoice_date']) && $row['invoice_date']
                ? (new DateTimeImmutable($row['invoice_date']))->format('Y-m-d')
                : $order->createdAt()->format('Y-m-d');

            return [
                'order' => $order,
                'invoiceDate' => $invoiceDate,
            ];
        }, $rows);
    }
}
