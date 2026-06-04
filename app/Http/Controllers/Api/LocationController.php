<?php

namespace App\Http\Controllers\Api;

use App\City;
use App\Country;
use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\StateResource;
use App\State;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationController extends Controller
{
    public function countries(): AnonymousResourceCollection
    {
        return CountryResource::collection(
            Country::query()->orderBy('name')->get()
        );
    }

    public function states(Request $request): AnonymousResourceCollection
    {
        $countryId = (int) $request->input('country_id', Country::query()->where('code', 'IN')->value('id') ?? 1);

        return StateResource::collection(
            State::query()
                ->where('country_id', $countryId)
                ->orderBy('name')
                ->get()
        );
    }

    public function cities(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;

        return CityResource::collection(
            City::query()
                ->where('state_id', (int) $request->input('state_id'))
                ->when($search, fn ($query) => $query->where('name', 'like', '%'.addcslashes($search, '%_').'%'))
                ->orderBy('name')
                ->get()
        );
    }
}
