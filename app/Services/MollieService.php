<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Chargeback;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Refund;
use stdClass;

final class MollieService
{
    private const PAGE_SIZE = 250;

    private const MAX_RESULTS = 500;

    public function __construct(private MollieApiClient $mollie) {}

    /**
     * Haalt alle chargebacks en refunds op uit Mollie, nieuwste bovenaan.
     *
     * @return array<int, array{id: string, type: 'chargeback'|'refund', paymentId: string, description: ?string, customerName: ?string, email: ?string, amount: float, currency: string, reason: ?string, status: ?string, paymentMethod: ?string, date: string, dashboardUrl: ?string}>
     */
    public function chargebacksAndRefunds(): array
    {
        $chargebacks = iterator_to_array($this->mollie->chargebacks
            ->iterator(limit: self::PAGE_SIZE, filters: ['include' => 'payment'])
            ->take(self::MAX_RESULTS)
            ->map(fn (Chargeback $chargeback): array => $this->toRow(
                id: $chargeback->id,
                type: 'chargeback',
                paymentId: $chargeback->paymentId,
                amount: $chargeback->amount,
                createdAt: $chargeback->createdAt,
                payment: $chargeback->_embedded->payment ?? null,
                reason: $chargeback->reason->description ?? null,
                status: $chargeback->reversedAt ? 'reversed' : null,
            )), preserve_keys: false);

        $refunds = iterator_to_array($this->mollie->refunds
            ->iterator(limit: self::PAGE_SIZE, filters: ['embed' => 'payment'])
            ->take(self::MAX_RESULTS)
            ->map(fn (Refund $refund): array => $this->toRow(
                id: $refund->id,
                type: 'refund',
                paymentId: $refund->paymentId,
                amount: $refund->amount,
                createdAt: $refund->createdAt,
                payment: $refund->_embedded->payment ?? null,
                reason: $refund->description ?: null,
                status: $refund->status,
            )), preserve_keys: false);

        $rows = array_merge($chargebacks, $refunds);

        usort($rows, fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $rows;
    }

    /**
     * Haalt de meest recente betaalde betalingen op met bedrag (incl. btw) en betaaldatum.
     *
     * @return array<int, array{amount: float, paidAt: string}>
     */
    public function paidPayments(): array
    {
        $payments = $this->mollie->payments
            ->iterator(limit: self::PAGE_SIZE)
            ->take(self::MAX_RESULTS)
            ->filter(fn (Payment $payment): bool => $payment->isPaid())
            ->map(fn (Payment $payment): array => [
                'amount' => (float) $payment->amount->value,
                'paidAt' => $this->toDate($payment->paidAt ?? $payment->createdAt),
            ]);

        return iterator_to_array($payments, preserve_keys: false);
    }

    /**
     * @param  'chargeback'|'refund'  $type
     * @return array{id: string, type: 'chargeback'|'refund', paymentId: string, description: ?string, customerName: ?string, email: ?string, amount: float, currency: string, reason: ?string, status: ?string, paymentMethod: ?string, date: string, dashboardUrl: ?string}
     */
    private function toRow(
        string $id,
        string $type,
        string $paymentId,
        stdClass $amount,
        string $createdAt,
        Payment|stdClass|null $payment,
        ?string $reason,
        ?string $status,
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'paymentId' => $paymentId,
            'description' => $payment->description ?? null,
            'customerName' => $payment->details->consumerName
                ?? $payment->details->cardHolder
                ?? $this->fullName($payment->billingAddress ?? null),
            'email' => $payment->billingEmail ?? $payment->billingAddress->email ?? null,
            'amount' => (float) $amount->value,
            'currency' => $amount->currency,
            'reason' => $reason,
            'status' => $status,
            'paymentMethod' => $payment->method ?? null,
            'date' => $this->toDate($createdAt),
            'dashboardUrl' => $payment->_links->dashboard->href ?? null,
        ];
    }

    private function fullName(?stdClass $address): ?string
    {
        $name = mb_trim(($address->givenName ?? '').' '.($address->familyName ?? ''));

        return $name !== '' ? $name : null;
    }

    private function toDate(string $timestamp): string
    {
        return CarbonImmutable::parse($timestamp)->timezone(config('app.timezone'))->format('Y-m-d');
    }
}
