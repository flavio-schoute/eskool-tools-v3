<?php

declare(strict_types=1);

use App\Models\User;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\PendingRequest;
use Mollie\Api\Http\Requests\GetPaginatedChargebacksRequest;
use Mollie\Api\Http\Requests\GetPaginatedRefundsRequest;
use Mollie\Api\Resources\ChargebackCollection;
use Mollie\Api\Resources\RefundCollection;
use Mollie\Laravel\Facades\Mollie;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function molliePayment(array $overrides = []): array
{
    return array_merge([
        'resource' => 'payment',
        'id' => 'tr_123',
        'mode' => 'live',
        'createdAt' => '2024-01-10T12:00:00+00:00',
        'amount' => ['value' => '99.99', 'currency' => 'EUR'],
        'description' => 'Factuur 2024-001',
        'method' => 'ideal',
        'status' => 'paid',
        'billingEmail' => 'jan@example.com',
        'details' => ['consumerName' => 'Jan Jansen'],
        '_links' => [
            'dashboard' => ['href' => 'https://my.mollie.com/dashboard/org_1/payments/tr_123', 'type' => 'text/html'],
        ],
    ], $overrides);
}

function mollieChargeback(array $overrides = []): array
{
    return array_merge([
        'resource' => 'chargeback',
        'id' => 'chb_123',
        'amount' => ['value' => '99.99', 'currency' => 'EUR'],
        'createdAt' => '2024-01-15T12:00:00+00:00',
        'paymentId' => 'tr_123',
        'reason' => ['code' => 'AC04', 'description' => 'Account closed'],
        'reversedAt' => null,
        '_embedded' => ['payment' => molliePayment()],
    ], $overrides);
}

function mollieRefund(array $overrides = []): array
{
    return array_merge([
        'resource' => 'refund',
        'id' => 're_123',
        'mode' => 'live',
        'amount' => ['value' => '25.00', 'currency' => 'EUR'],
        'createdAt' => '2024-01-20T12:00:00+00:00',
        'description' => 'Gedeeltelijke terugbetaling',
        'paymentId' => 'tr_456',
        'status' => 'refunded',
        '_embedded' => ['payment' => molliePayment(['id' => 'tr_456', 'details' => null, 'billingEmail' => null])],
    ], $overrides);
}

function fakeMollie(array $chargebacks = [], array $refunds = []): void
{
    Mollie::fake([
        GetPaginatedChargebacksRequest::class => MockResponse::list(ChargebackCollection::class)->addMany($chargebacks)->create(),
        GetPaginatedRefundsRequest::class => MockResponse::list(RefundCollection::class)->addMany($refunds)->create(),
    ]);
}

it('renders chargebacks and refunds from mollie', function () {
    fakeMollie([mollieChargeback()], [mollieRefund()]);

    $this->actingAs($this->user)
        ->get(route('invoices.index'))
        ->assertInertia(
            fn ($page) => $page
                ->component('invoices/Index')
                ->has('transactions', 2)
                ->where('transactions.0.id', 're_123')
                ->where('transactions.0.type', 'refund')
                ->where('transactions.0.amount', 25)
                ->where('transactions.0.reason', 'Gedeeltelijke terugbetaling')
                ->where('transactions.0.status', 'refunded')
                ->where('transactions.0.customerName', null)
                ->where('transactions.0.date', '2024-01-20')
                ->where('transactions.1.id', 'chb_123')
                ->where('transactions.1.type', 'chargeback')
                ->where('transactions.1.paymentId', 'tr_123')
                ->where('transactions.1.description', 'Factuur 2024-001')
                ->where('transactions.1.customerName', 'Jan Jansen')
                ->where('transactions.1.email', 'jan@example.com')
                ->where('transactions.1.amount', 99.99)
                ->where('transactions.1.currency', 'EUR')
                ->where('transactions.1.reason', 'Account closed')
                ->where('transactions.1.paymentMethod', 'ideal')
                ->where('transactions.1.date', '2024-01-15')
                ->where('transactions.1.dashboardUrl', 'https://my.mollie.com/dashboard/org_1/payments/tr_123')
        );
});

it('embeds the payment when fetching chargebacks and refunds', function () {
    fakeMollie();

    $this->actingAs($this->user)->get(route('invoices.index'))->assertOk();

    Mollie::assertSent(fn (PendingRequest $request) => $request->getRequest() instanceof GetPaginatedChargebacksRequest
        && $request->query()->get('embed') === 'payment');
    Mollie::assertSent(fn (PendingRequest $request) => $request->getRequest() instanceof GetPaginatedRefundsRequest
        && $request->query()->get('embed') === 'payment');
});

it('marks reversed chargebacks', function () {
    fakeMollie([mollieChargeback(['reversedAt' => '2024-02-01T12:00:00+00:00'])]);

    $this->actingAs($this->user)
        ->get(route('invoices.index'))
        ->assertInertia(fn ($page) => $page->where('transactions.0.status', 'reversed'));
});

it('redirects guests to the login page', function () {
    $this->get(route('invoices.index'))
        ->assertRedirect(route('login'));
});
