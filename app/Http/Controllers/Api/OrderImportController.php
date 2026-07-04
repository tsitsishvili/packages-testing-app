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
    #[Summary('Import orders from a file')]
    #[Description('Bulk-imports orders from an uploaded CSV. The presence of the binary `file` field makes documentator describe the body as `multipart/form-data`.')]
    #[ApiResponse(status: 202, description: 'Import accepted for processing.', example: ['accepted' => true, 'source' => 'shopify', 'rows' => 42, 'dry_run' => false])]
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
