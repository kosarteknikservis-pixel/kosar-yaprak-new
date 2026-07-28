<?php

declare(strict_types=1);

/**
 * Varyant türü adını gösterim için normalize eder (örn. "Renk Seçiniz" → "Renk").
 */
function variant_type_display_name(string $typeName): string
{
    $t = trim($typeName);
    if ($t === '') {
        return $typeName;
    }
    $cleaned = preg_replace('/\s*Seçiniz\s*$/ui', '', $t);

    return ($cleaned !== null && $cleaned !== '') ? $cleaned : $t;
}
