<?php

use App\Billing\StripePackageBilling;
use App\Models\User;
use Stripe\ApiRequestor;
use Stripe\Exception\InvalidRequestException;
use Stripe\HttpClient\ClientInterface;

function fakeStripeHttp(int $status, array $body): object
{
    $client = new class($status, $body) implements ClientInterface
    {
        /** @var list<array{method: string, url: string}> */
        public array $requests = [];

        public function __construct(private int $status, private array $body) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $this->requests[] = ['method' => $method, 'url' => $absUrl];

            return [json_encode($this->body), $this->status, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

beforeEach(function () {
    config(['cashier.secret' => 'sk_test_fake']);
    $this->previousClient = ApiRequestor::httpClient();
});

afterEach(function () {
    ApiRequestor::setHttpClient($this->previousClient);
});

test('the stripe customer is deleted', function () {
    $http = fakeStripeHttp(200, ['id' => 'cus_123', 'object' => 'customer', 'deleted' => true]);

    (new StripePackageBilling)->deleteCustomer(User::factory()->make(['stripe_id' => 'cus_123']));

    expect($http->requests)->toHaveCount(1)
        ->and($http->requests[0]['method'])->toBe('delete')
        ->and($http->requests[0]['url'])->toEndWith('/v1/customers/cus_123');
});

test('a customer that no longer exists at stripe is not an error', function () {
    fakeStripeHttp(404, ['error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such customer']]);

    (new StripePackageBilling)->deleteCustomer(User::factory()->make(['stripe_id' => 'cus_gone']));

    expect(true)->toBeTrue();
});

test('other stripe errors propagate', function () {
    fakeStripeHttp(400, ['error' => ['type' => 'invalid_request_error', 'code' => 'parameter_invalid_empty', 'message' => 'Bad request']]);

    (new StripePackageBilling)->deleteCustomer(User::factory()->make(['stripe_id' => 'cus_123']));
})->throws(InvalidRequestException::class);

test('a user without a stripe customer makes no request', function () {
    $http = fakeStripeHttp(200, []);

    (new StripePackageBilling)->deleteCustomer(User::factory()->make(['stripe_id' => null]));

    expect($http->requests)->toBe([]);
});
