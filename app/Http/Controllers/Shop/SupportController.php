<?php

namespace App\Http\Controllers\Shop;

use App\Enums\SupportCategory;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Support\Reference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function create(Request $request): View
    {
        return view('shop.support', ['orderNumber' => $request->string('order')->toString()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'category' => ['required', Rule::enum(SupportCategory::class)],
            'order_number' => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        // Only link an order the requester can prove is theirs (matching e-mail).
        $order = filled($validated['order_number'] ?? null)
            ? Order::query()->where('number', mb_strtoupper(trim($validated['order_number'])))->where('email', mb_strtolower($validated['email']))->first()
            : null;

        $ticket = SupportTicket::query()->create([
            'reference' => Reference::generate('SUP', SupportTicket::class),
            'user_id' => $request->user()?->id,
            'order_id' => $order?->id,
            'name' => $validated['name'],
            'email' => mb_strtolower($validated['email']),
            'category' => $validated['category'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
        ]);

        return redirect()->route('support.create')->with('success', __('Thanks! Your request :ref has been received. We usually reply within one business day.', ['ref' => $ticket->reference]));
    }
}
