<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenanceServiceTypeResource;
use App\MaintenanceServiceType;
use App\Support\CurrentTenant;
use App\Support\MaintenanceServiceTypeDefaults;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaintenanceServiceTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MaintenanceServiceType::class);

        MaintenanceServiceTypeDefaults::seedForTenant(CurrentTenant::requireId());

        $perPage = min(max((int) $request->input('per_page', 500), 1), 500);

        $query = MaintenanceServiceType::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($perPage >= 500) {
            return MaintenanceServiceTypeResource::collection($query->get());
        }

        return MaintenanceServiceTypeResource::collection($query->paginate($perPage));
    }
}
