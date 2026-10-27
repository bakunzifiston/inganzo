<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(SupportTicketStatus::class)]]);

        return view('admin.support.index', [
            'tickets' => SupportTicket::query()->with('order')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')), fn ($q) => $q->whereIn('status', ['open', 'pending']))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('reference', 'ilike', '%'.$request->string('q').'%')->orWhere('email', 'ilike', '%'.$request->string('q').'%')->orWhere('subject', 'ilike', '%'.$request->string('q').'%')))
                ->latest()->paginate(25)->withQueryString(),
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        return view('admin.support.show', ['ticket' => $ticket->load(['order', 'user'])]);
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(SupportTicketStatus::class)],
            'staff_response' => ['nullable', 'string', 'max:5000'],
            'send_email' => ['sometimes', 'boolean'],
        ]);

        $ticket->forceFill([
            'status' => $validated['status'],
            'staff_response' => $validated['staff_response'] ?? $ticket->staff_response,
            'handled_by' => $request->user()->id,
            'responded_at' => filled($validated['staff_response'] ?? null) ? now() : $ticket->responded_at,
        ])->save();

        if ($request->boolean('send_email') && filled($validated['staff_response'] ?? null)) {
            Mail::raw($validated['staff_response'], fn ($message) => $message->to($ticket->email)->subject(__('Re: :subject [:ref]', ['subject' => $ticket->subject, 'ref' => $ticket->reference])));
        }

        return back()->with('success', __('Ticket updated.'));
    }
}
