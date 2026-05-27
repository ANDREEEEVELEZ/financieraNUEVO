<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

uses(Tests\TestCase::class);

describe('ApiResponse', function () {
    describe('success()', function () {
        it('returns a JsonResponse', function () {
            $response = ApiResponse::success(['id' => 1]);
            expect($response)->toBeInstanceOf(JsonResponse::class);
        });

        it('has success=true in the envelope', function () {
            $response = ApiResponse::success(['id' => 1]);
            $body = $response->getData(true);
            expect($body['success'])->toBeTrue();
        });

        it('wraps the data under the data key', function () {
            $response = ApiResponse::success(['id' => 1, 'name' => 'test']);
            $body = $response->getData(true);
            expect($body['data'])->toBe(['id' => 1, 'name' => 'test']);
        });

        it('returns 200 status code by default', function () {
            $response = ApiResponse::success();
            expect($response->getStatusCode())->toBe(200);
        });

        it('returns the custom status code', function () {
            $response = ApiResponse::success(['id' => 1], 'Created', 201);
            expect($response->getStatusCode())->toBe(201);
        });

        it('includes the message in the envelope', function () {
            $response = ApiResponse::success(null, 'All good');
            $body = $response->getData(true);
            expect($body['message'])->toBe('All good');
        });

        it('sets message to null when not provided', function () {
            $response = ApiResponse::success();
            $body = $response->getData(true);
            expect($body['message'])->toBeNull();
        });

        it('accepts null data', function () {
            $response = ApiResponse::success(null);
            $body = $response->getData(true);
            expect($body['data'])->toBeNull();
        });

        it('includes errors key with null value', function () {
            $response = ApiResponse::success(['id' => 1]);
            $body = $response->getData(true);
            expect($body)->toHaveKey('errors');
            expect($body['errors'])->toBeNull();
        });
    });

    describe('error()', function () {
        it('returns a JsonResponse', function () {
            $response = ApiResponse::error('Something went wrong');
            expect($response)->toBeInstanceOf(JsonResponse::class);
        });

        it('has success=false in the envelope', function () {
            $response = ApiResponse::error('Something went wrong');
            $body = $response->getData(true);
            expect($body['success'])->toBeFalse();
        });

        it('sets data to null', function () {
            $response = ApiResponse::error('Error');
            $body = $response->getData(true);
            expect($body['data'])->toBeNull();
        });

        it('includes the message', function () {
            $response = ApiResponse::error('Validation failed');
            $body = $response->getData(true);
            expect($body['message'])->toBe('Validation failed');
        });

        it('returns 400 status code by default', function () {
            $response = ApiResponse::error('Bad request');
            expect($response->getStatusCode())->toBe(400);
        });

        it('returns the custom status code', function () {
            $response = ApiResponse::error('Not found', 404);
            expect($response->getStatusCode())->toBe(404);
        });

        it('includes errors when provided', function () {
            $errors = ['email' => ['The email is required.']];
            $response = ApiResponse::error('Validation failed', 422, $errors);
            $body = $response->getData(true);
            expect($body['errors'])->toBe($errors);
        });

        it('contains an errors key even when not provided', function () {
            $response = ApiResponse::error('Error');
            $body = $response->getData(true);
            expect($body)->toHaveKey('errors');
        });
    });

    describe('paginated()', function () {
        it('includes errors key with null value', function () {
            $paginator = new LengthAwarePaginator([], 0, 15);
            $response = ApiResponse::paginated($paginator);
            $body = $response->getData(true);
            expect($body)->toHaveKey('errors');
            expect($body['errors'])->toBeNull();
        });

        it('returns a JsonResponse', function () {
            $paginator = new LengthAwarePaginator([], 0, 15);
            $response = ApiResponse::paginated($paginator);
            expect($response)->toBeInstanceOf(JsonResponse::class);
        });

        it('has success=true', function () {
            $paginator = new LengthAwarePaginator([], 0, 15);
            $response = ApiResponse::paginated($paginator);
            $body = $response->getData(true);
            expect($body['success'])->toBeTrue();
        });

        it('returns 200 status code', function () {
            $paginator = new LengthAwarePaginator([], 0, 15);
            $response = ApiResponse::paginated($paginator);
            expect($response->getStatusCode())->toBe(200);
        });

        it('includes all required meta keys', function () {
            $items = [['id' => 1], ['id' => 2]];
            $paginator = new LengthAwarePaginator($items, 22, 15, 1);
            $response = ApiResponse::paginated($paginator);
            $body = $response->getData(true);

            expect($body)->toHaveKey('meta');
            expect($body['meta'])->toHaveKeys(['current_page', 'last_page', 'per_page', 'total']);
        });

        it('reports correct meta values', function () {
            $items = array_fill(0, 15, ['id' => 1]);
            $paginator = new LengthAwarePaginator($items, 22, 15, 1);
            $response = ApiResponse::paginated($paginator);
            $body = $response->getData(true);

            expect($body['meta']['current_page'])->toBe(1);
            expect($body['meta']['last_page'])->toBe(2);
            expect($body['meta']['per_page'])->toBe(15);
            expect($body['meta']['total'])->toBe(22);
        });

        it('wraps items under the data key', function () {
            $items = [['id' => 1], ['id' => 2]];
            $paginator = new LengthAwarePaginator($items, 2, 15, 1);
            $response = ApiResponse::paginated($paginator);
            $body = $response->getData(true);

            expect($body['data'])->toBe($items);
        });
    });
});
