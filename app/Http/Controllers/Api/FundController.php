<?php

namespace App\Http\Controllers\Api;

use App\Filters\FundFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListFundsRequest;
use App\Http\Resources\FundResource;
use App\Models\Fund;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FundController extends Controller
{
    /**
     * Paginated list of active funds. Filter / sort / pagination parameters
     * live on the ListFundsRequest; the FundFilter class maps the validated
     * array onto the Eloquent query.
     */
    public function index(ListFundsRequest $request): AnonymousResourceCollection
    {
        $query = Fund::query()->with(['documents', 'holdings', 'assetAllocations']);

        (new FundFilter($request->validated()))->apply($query);

        $perPage = (int) ($request->validated('per_page') ?? 15);

        return FundResource::collection($query->paginate($perPage));
    }

    /**
     * Single fund by SEDOL (route model binding — see Fund::getRouteKeyName()).
     */
    public function show(Fund $fund): FundResource
    {
        $fund->load(['documents', 'holdings', 'assetAllocations']);

        return new FundResource($fund);
    }
}
