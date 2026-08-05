<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportOrdersRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Tsitsishvili\Documentator\Attributes\Authenticated;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\Response as ApiResponse;
use Tsitsishvili\Documentator\Attributes\Summary;

#[Group('Orders')]
#[Authenticated]
class OrderImportController extends Controller
{
    #[Summary('Validate an order-import file')]
    #[Description('Validates an uploaded CSV or text file and returns its data-row count with the submitted import metadata. This integration fixture does not persist or queue imported orders.')]
    #[ApiResponse(status: 202, type: 'array{accepted: bool, source: string, rows: int, dry_run: bool}', description: 'File validated.', example: ['accepted' => true, 'source' => 'shopify', 'rows' => 42, 'dry_run' => false])]
    public function store(ImportOrdersRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $rows = $file !== null
            ? max(count(file($file->getRealPath()) ?: []) - 1, 0)
            : 0;

        return response()->json([
            'accepted' => true,
            'source' => $request->validated('source'),
            'rows' => $rows,
            'dry_run' => $request->boolean('dry_run'),
        ], Response::HTTP_ACCEPTED);
    }
}
