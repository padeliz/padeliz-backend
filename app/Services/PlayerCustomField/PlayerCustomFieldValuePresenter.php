<?php

declare(strict_types=1);

namespace App\Services\PlayerCustomField;

use App\Enums\PlayerCustomFieldType;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldValue;

final class PlayerCustomFieldValuePresenter
{
    public static function resolve(PlayerCustomFieldValue $value): mixed
    {
        $field = $value->relationLoaded('field')
            ? $value->field
            : $value->field()->first();

        if (! $field instanceof PlayerCustomField) {
            return null;
        }

        return match ($field->type) {
            PlayerCustomFieldType::Text => $value->value_text,
            PlayerCustomFieldType::Integer => $value->value_integer,
            PlayerCustomFieldType::Boolean => $value->value_boolean,
            PlayerCustomFieldType::Date => $value->value_date,
            PlayerCustomFieldType::SingleSelect => self::singleSelectLabel($value),
            PlayerCustomFieldType::MultiSelect => self::multiSelectLabels($value),
        };
    }

    private static function singleSelectLabel(PlayerCustomFieldValue $value): ?string
    {
        if ($value->relationLoaded('selectedOptions')) {
            $option = $value->selectedOptions->first();

            return $option?->option_label_snapshot;
        }

        return $value->selectedOptions()->value('option_label_snapshot');
    }

    /**
     * @return list<string>
     */
    private static function multiSelectLabels(PlayerCustomFieldValue $value): array
    {
        if ($value->relationLoaded('selectedOptions')) {
            return $value->selectedOptions
                ->pluck('option_label_snapshot')
                ->filter(static fn (?string $label): bool => $label !== null && $label !== '')
                ->values()
                ->all();
        }

        return $value->selectedOptions()
            ->orderBy('id_player_custom_field_value_option')
            ->pluck('option_label_snapshot')
            ->filter(static fn (?string $label): bool => $label !== null && $label !== '')
            ->values()
            ->all();
    }
}
