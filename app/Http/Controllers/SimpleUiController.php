<?php

namespace App\Http\Controllers;

use App\Models\GoodType;
use App\Models\LorryReceipt;
use App\Models\Transporter;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SimpleUiController extends Controller
{
    public function dashboard(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $companyId = (int) $user->company_id;
        $today = now()->toDateString();

        $stats = [
            'pendingReceipts' => LorryReceipt::query()
                ->where('company_id', $companyId)
                ->where('delivery_status', 'Pending')
                ->count(),
            'deliveredToday' => LorryReceipt::query()
                ->where('company_id', $companyId)
                ->where('delivery_status', 'Delivered')
                ->whereDate('updated_at', $today)
                ->count(),
            'pendingInvoice' => LorryReceipt::query()
                ->where('company_id', $companyId)
                ->where('invoice_generated', 0)
                ->count(),
            'recentCount' => LorryReceipt::query()
                ->where('company_id', $companyId)
                ->whereDate('created_at', '>=', now()->subDays(7)->toDateString())
                ->count(),
        ];

        $recentReceipts = LorryReceipt::query()
            ->with(['customer:id,name', 'truck:id,truck_number'])
            ->where('company_id', $companyId)
            ->latest('gr_date')
            ->latest('id')
            ->limit(8)
            ->get();

        return view('simple.dashboard', compact('stats', 'recentReceipts'));
    }

    public function receiptsIndex(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $companyId = (int) $user->company_id;
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));

        $receipts = LorryReceipt::query()
            ->with(['customer:id,name', 'consignor:id,name', 'consignee:id,name', 'truck:id,truck_number'])
            ->where('company_id', $companyId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('lr_number', 'like', '%'.$search.'%')
                        ->orWhere('invoice_number', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('delivery_status', $status);
            })
            ->latest('gr_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('simple.receipts.index', compact('receipts', 'search', 'status'));
    }

    public function receiptsCreate(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $companyId = (int) $user->company_id;
        $companyUserId = (int) $user->company->user_id;

        $consignees = \App\Models\Consignee::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'city', 'state']);

        $trucks = Truck::query()
            ->where('company_id', $companyId)
            ->orderBy('truck_number')
            ->limit(200)
            ->get(['id', 'truck_number']);

        $transporters = Transporter::query()
            ->where('company_id', $companyId)
            ->where('is_owner', 0)
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'name']);

        $goodsTypes = GoodType::query()
            ->where('user_id', $companyUserId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('simple.receipts.create', compact('consignees', 'trucks', 'transporters', 'goodsTypes'));
    }

    public function billingIndex(): View
    {
        return view('simple.billing.index');
    }

    public function reportsIndex(): View
    {
        return view('simple.reports.index');
    }
}
