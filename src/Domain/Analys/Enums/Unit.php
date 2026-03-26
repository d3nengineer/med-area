<?php

declare(strict_types=1);

namespace Domain\Analys\Enums;

use Shared\Enums\LanguageCode;

enum Unit: string
{
    case GL = 'г/л'; // Грамм на Литр
    case MMOL_L = 'mmol/L'; // Millimoles per Liter
    case PERCENT = '%'; // Проценты

    /**
     * Get translations for enum values
     * Using \Shared\Enums\LanguageCode::class
     *
     * @return array<array<int|string, string>>
     */
    public static function translations(): array
    {
        return [
            'г/л' => [
                LanguageCode::RU->value => 'г/л',
                LanguageCode::EN->value => 'g/l',
            ],
            'mmol/L' => [
                LanguageCode::RU->value => 'ммоль/л',
                LanguageCode::EN->value => 'mmol/L',
            ],
            '%' => [
                LanguageCode::RU->value => '%',
                LanguageCode::EN->value => '%',
            ],
        ];
    }

    public static function fromLanguageValue(string $value, LanguageCode $language): ?self
    {
        foreach (self::cases() as $case) {
            $translations = self::translations()[$case->value] ?? [];
            if (isset($translations[$language->value]) && $translations[$language->value] === $value) {
                return $case;
            }
        }
        return null;
    }

    public static function getValueForLanguage(self $unit, LanguageCode $language): ?string
    {
        return self::translations()[$unit->value][$language->value] ?? null;
    }

    public static function hasValueForLanguage(string $value, LanguageCode $language): bool
    {
        foreach (self::translations() as $ruValue => $translations) {
            if (isset($translations[$language->value]) && $translations[$language->value] === $value) {
                return true;
            }
        }
        return false;
    }
}
