<?php

namespace App\Models\Concerns;

/**
 * Locale-keyed JSONB columns for catalogue content, e.g. {"en": "Hoodie", "rw": "Ikoti"}.
 * Interface strings live in lang files; this is only for administrator-managed content.
 *
 * Models declare the translatable columns in a `translatable()` method and cast them to arrays.
 */
trait HasTranslations
{
    public function translate(string $attribute, ?string $locale = null): string
    {
        $values = $this->getAttribute($attribute);

        if (! is_array($values)) {
            return (string) $values;
        }

        $locale ??= app()->getLocale();

        return (string) ($values[$locale] ?? $values[config('app.fallback_locale')] ?? reset($values) ?: '');
    }
}
