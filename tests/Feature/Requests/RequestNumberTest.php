<?php

use App\Exceptions\ImmutableRecordException;
use App\Models\ServiceRequest;
use App\Services\RequestNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('hands out sequential human-readable numbers', function () {
    $c = cast();

    $numbers = collect(range(1, 3))->map(fn () => makeRequest($c->requester, $c->category)->request_number);

    expect($numbers->all())->toBe(['ATH-000001', 'ATH-000002', 'ATH-000003']);
});

it('does not burn a number when the creating transaction rolls back', function () {
    $c = cast();

    try {
        DB::transaction(function () use ($c) {
            makeRequest($c->requester, $c->category);
            throw new RuntimeException('something failed after the number was taken');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(makeRequest($c->requester, $c->category)->request_number)->toBe('ATH-000001');
});

it('only ever issues a number inside a transaction', function () {
    // RefreshDatabase wraps the test in a transaction, so step out to prove the guard.
    $generator = new RequestNumberGenerator;
    $level = DB::transactionLevel();

    for ($i = 0; $i < $level; $i++) {
        DB::rollBack();
    }

    try {
        expect(fn () => $generator->next())->toThrow(LogicException::class);
    } finally {
        for ($i = 0; $i < $level; $i++) {
            DB::beginTransaction();
        }
    }
});

it('never lets the request number change', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    $request->request_number = 'ATH-999999';

    expect(fn () => $request->save())->toThrow(ImmutableRecordException::class);
    expect($request->fresh()->request_number)->toBe('ATH-000001');
});

it('never lets the original requester change', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    $request->requester_id = $c->otherRequester->id;

    expect(fn () => $request->save())->toThrow(ImmutableRecordException::class);
});

it('keeps request numbers unique at the database level', function () {
    $c = cast();
    $first = makeRequest($c->requester, $c->category);

    expect(fn () => ServiceRequest::factory()->create([
        'request_number' => $first->request_number,
        'requester_id' => $c->requester->id,
        'department_id' => $c->department->id,
        'category_id' => $c->category->id,
    ]))->toThrow(QueryException::class);
});
