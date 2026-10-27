<?php

namespace App\Services\Cart;

use App\Enums\DiscountType;
use App\Models\DiscountCode;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class DiscountEvaluator
{
    /**
     * @return array{code: DiscountCode, amount: int}
     *
     * @throws ValidationException
     */
    public function evaluate(string $code, int $subtotal, string $currency, ?string $email = null, ?int $userId = null, bool $lock = false): array
    {
        $query = DiscountCode::query()->where('code', mb_strtoupper(trim($code)));
        $discount = $lock ? $query->lockForUpdate()->first() : $query->first();

        $fail = fn (string $message) => throw ValidationException::withMessages(['discount_code' => $message]);

        if ($discount === null || ! $discount->is_active) {
            $fail(__('That discount code is not valid.'));
        }
        if ($discount->starts_at?->isFuture() || $discount->ends_at?->isPast()) {
            $fail(__('That discount code is not active right now.'));
        }
        if ($discount->currency !== $currency) {
            $fail(__('That discount code cannot be used with this currency.'));
        }
        if ($subtotal < $discount->min_subtotal_amount) {
            $fail(__('Spend at least :amount to use this code.', ['amount' => Money::format($discount->min_subtotal_amount, $currency)]));
        }
        if ($discount->max_redemptions !== null && $discount->redemptions_count >= $discount->max_redemptions) {
            $fail(__('That discount code has been fully used.'));
        }
        if ($email !== null || $userId !== null) {
            $used = $discount->redemptions()
                ->where(fn ($q) => $q->when($email, fn ($q) => $q->where('email', mb_strtolower($email)))
                    ->when($userId, fn ($q) => $q->orWhere('user_id', $userId)))
                ->count();

            if ($used >= $discount->max_per_customer) {
                $fail(__('You have already used this discount code.'));
            }
        }

        $amount = $discount->type === DiscountType::Percent
            ? Money::percentage($subtotal, $discount->percent_bps)
            : $discount->amount;

        return ['code' => $discount, 'amount' => min($amount, $subtotal)];
    }
}
