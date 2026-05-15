<?php

namespace App\Http\Controllers;

use App\Exceptions\ZohoApiException;
use App\Http\Requests\TransactionsRequest;
use App\Http\Resources\TransactionListResource;
use App\Services\TransactionsAssembler;
use Illuminate\Http\JsonResponse;

final class TransactionsController extends Controller
{
    public function __construct(protected TransactionsAssembler $assembler) {}

    public function __invoke(TransactionsRequest $request): TransactionListResource|JsonResponse
    {
        try {
            $payload = $this->assembler->assemble($request->accountId(), $request->month());
        } catch (ZohoApiException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        return new TransactionListResource($payload);
    }
}
