<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(PaymentStatus::class)], 'provider' => ['nullable', 'string', 'max:30']]);

        return view('admin.payments.index', [
            'payments' => Payment::query()->with('order')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->string('provider')))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('provider_reference', 'ilike', '%'.$request->string('q').'%')
                    ->orWhereHas('order', fn ($o) => $o->where('number', 'ilike', '%'.$request->string('q').'%'))))
                ->latest('id')->paginate(30)->withQueryString(),
            'providers' => Payment::query()->distinct()->pluck('provider'),
            'stalePending' => Payment::query()->where('status', PaymentStatus::Pending)->where('created_at', '<', now()->subMinutes(30))->count(),
            'duplicates' => Payment::query()->where('status', PaymentStatus::Duplicate)->count(),
        ]);
    }

    public function refresh(Payment $payment, PaymentService $payments, AuditLogger $audit): RedirectResponse
    {
        $before = $payment->status;
        $payment = $payments->refresh($payment);
        $audit->log('payment.status_checked', $payment, ['before' => $before->value, 'after' => $payment->status->value]);

        return back()->with('success', __('Status checked with the provider: :status.', ['status' => $payment->status->label()]));
    }
}
