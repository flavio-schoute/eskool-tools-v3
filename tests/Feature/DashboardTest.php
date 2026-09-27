<?php

declare(strict_types=1);

use App\Models\User;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\GetPaginatedChargebacksRequest;
use Mollie\Api\Http\Requests\GetPaginatedPaymentsRequest;
use Mollie\Api\Http\Requests\GetPaginatedRefundsRequest;
use Mollie\Api\Resources\ChargebackCollection;
use Mollie\Api\Resources\PaymentCollection;
use Mollie\Api\Resources\RefundCollection;
use Mollie\Laravel\Facades\Mollie;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard loads transaction and cash stats from mollie', function () {
    Mollie::fake([
        GetPaginatedChargebacksRequest::class => MockResponse::list(ChargebackCollection::class)->add([
            'resource' => 'chargeback',
            'id' => 'chb_1',
            'amount' => ['value' => '50.00', 'currency' => 'EUR'],
            'createdAt' => '2024-03-01T10:00:00+00:00',
            'paymentId' => 'tr_1',
        ])->create(),
        GetPaginatedRefundsRequest::class => MockResponse::list(RefundCollection::class)->create(),
        GetPaginatedPaymentsRequest::class => MockResponse::list(PaymentCollection::class)->addMany([
            ['resource' => 'payment', 'id' => 'tr_1', 'status' => 'paid', 'amount' => ['value' => '121.00', 'currency' => 'EUR'], 'createdAt' => '2024-02-01T10:00:00+00:00', 'paidAt' => '2024-02-02T10:00:00+00:00'],
            ['resource' => 'payment', 'id' => 'tr_2', 'status' => 'expired', 'amount' => ['value' => '10.00', 'currency' => 'EUR'], 'createdAt' => '2024-02-03T10:00:00+00:00'],
        ])->create(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->missing('transactionStats')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('transactionStats.transactions', 1)
                ->where('transactionStats.transactions.0', ['type' => 'chargeback', 'amount' => 50, 'date' => '2024-03-01'])
                ->has('cashStats.payments', 1)
                ->where('cashStats.payments.0', ['amount' => 121, 'paidAt' => '2024-02-02'])
            )
        );
});
