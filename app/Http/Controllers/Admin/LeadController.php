<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappLead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->user()->scopedBranchId();
        $status = $request->query('estado');

        $leads = WhatsappLead::with(['product.primaryImage', 'variant.attributeValues.attribute', 'post', 'branch'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)->withQueryString();

        $counts = WhatsappLead::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.leads.index', compact('leads', 'status', 'counts'));
    }

    public function update(Request $request, WhatsappLead $lead)
    {
        $branchId = $request->user()->scopedBranchId();
        abort_if($branchId && $lead->branch_id !== $branchId, 403);

        $data = $request->validate(['status' => ['required', 'in:nuevo,contactado,vendido,perdido']]);
        $lead->update($data);

        return back()->with('status', 'Lead actualizado.');
    }
}
