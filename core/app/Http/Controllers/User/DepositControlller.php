<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\DepositService;
use Illuminate\Http\Request;

class DepositControlller extends Controller
{
    public function index()
    {
        if (!gs()->wallet) {
            abort(403, 'Access denied.');
        }

        $deposits = Deposit::with(['transaction'])
            ->where('user_id', user_id())
            ->latest()
            ->paginate(gs()->paginate_per_page);

        return view('user.add-funds', compact('deposits'));
    }

    public function addFund(Request $request, DepositService $depositService)
    {
        $minAmount = gs()->tzsmmpay_min_amount;
        $maxAmount = gs()->tzsmmpay_max_amount;

        $validator = validator()->make($request->all(), [
            'amount' => ['required', 'numeric', 'min:' . $minAmount, 'max:' . $maxAmount],
        ]);

        if ($validator->fails()) {
            return back()->with('error', __('The amount field must be between ' . $minAmount . ' and ' . $maxAmount . '.'));
        }

        return $depositService->addFund($request);
    }

    public function payNow(Request $request, DepositService $depositService)
    {
        $validator = validator()->make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return back()->with('error', __('Deposit ID field is required.'));
        }

        return $depositService->payNow($request->id);
    }
}
