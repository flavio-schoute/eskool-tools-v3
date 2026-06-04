<?php

use App\Models\User;
use App\Services\PlugAndPayService;
use PlugAndPay\Sdk\Director\BodyTo\BodyToOrder;
use PlugAndPay\Sdk\Entity\Order;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function makeOrder(array $overrides = []): Order
{
    return BodyToOrder::build(array_merge([
        'id' => 1,
        'invoice_number' => '2024-001',
        'invoice_status' => 'final',
        'mode' => 'live',
        'source' => 'api',
        'reference' => 'ref-1',
        'amount' => '89.25',
        'amount_with_tax' => '99.99',
        'is_first' => false,
        'is_hidden' => false,
        'created_at' => '2024-01-15 12:00:00',
        'updated_at' => '2024-01-15 12:00:00',
        'deleted_at' => null,
        'billing' => [
            'address' => [
                'city' => 'Amsterdam',
                'country' => 'NL',
                'street' => 'Teststraat',
                'housenumber' => '1',
                'zipcode' => '1000AA',
            ],
            'contact' => [
                'email' => 'jan@example.com',
                'firstname' => 'Jan',
                'lastname' => 'Jansen',
                'tax_exempt' => 'none',
            ],
        ],
        'payment' => [
            'status' => 'open',
        ],
    ], $overrides));
}

function makeRow(array $orderOverrides = [], string $invoiceDate = '2024-01-15'): array
{
    return [
        'order' => makeOrder($orderOverrides),
        'invoiceDate' => $invoiceDate,
    ];
}

/**
 * @param  array<int, array{order: Order, invoiceDate: string}>  $rows
 */
function mockService(array $rows): void
{
    $mock = Mockery::mock(PlugAndPayService::class);
    $mock->shouldReceive('unpaidAndReversedOrders')
        ->once()
        ->andReturn(['orders' => $rows, 'perPage' => 25]);
    app()->instance(PlugAndPayService::class, $mock);
}

it('renders the invoices page with all invoices as props', function () {
    mockService([makeRow()]);

    $this->actingAs($this->user)
        ->get(route('invoices.index'))
        ->assertInertia(
            fn ($page) => $page
                ->component('invoices/Index')
                ->has('invoices', 1)
                ->where('invoices.0.id', 1)
                ->where('invoices.0.invoiceNumber', '2024-001')
                ->where('invoices.0.customerName', 'Jan Jansen')
                ->where('invoices.0.email', 'jan@example.com')
                ->where('invoices.0.amount', 89.25)
                ->where('invoices.0.paymentStatus', 'open')
                ->where('invoices.0.invoiceDate', '2024-01-15')
                ->has('invoices.0.plugAndPayUrl')
                ->has('invoices.0.paymentUrl')
                ->has('invoices.0.company')
                ->has('invoices.0.address')
        );
});

it('passes all invoices without server-side filtering or pagination', function () {
    mockService([makeRow(['id' => 1]), makeRow(['id' => 2]), makeRow(['id' => 3])]);

    $this->actingAs($this->user)
        ->get(route('invoices.index'))
        ->assertInertia(fn ($page) => $page->has('invoices', 3));
});

it('redirects guests to the login page', function () {
    $this->get(route('invoices.index'))
        ->assertRedirect(route('login'));
});
