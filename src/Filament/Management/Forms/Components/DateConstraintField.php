<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components;

use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\DateAnchor;
use Relaticle\CustomFields\Enums\DateOffsetDirection;
use Relaticle\CustomFields\Enums\DateUnit;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Validation\DateFieldReferenceValidator;

final class DateConstraintField
{
    private const array PRESETS_WITH_OFFSET = ['today_offset', 'custom_field', 'record_created'];

    /** @return array<int, Component> */
    public static function make(string $statePath, string $label, string $context = 'min'): array
    {
        return [
            Fieldset::make($label)
                ->schema([
                    Select::make($statePath.'.preset')
                        ->label(__('custom-fields::custom-fields.date_constraint.constraint'))
                        ->options(self::presetOptions($context))
                        ->default('none')
                        ->live()
                        ->afterStateHydrated(function ($component, Get $get) use ($statePath): void {
                            $anchor = $get($statePath.'.anchor');

                            if ($anchor === null) {
                                $component->state('none');

                                return;
                            }

                            $component->state(self::anchorToPreset(
                                $anchor,
                                (int) ($get($statePath.'.offset') ?? 0),
                            ));
                        })
                        ->afterStateUpdated(function (Set $set, ?string $state) use ($statePath): void {
                            self::applyPresetDefaults($set, $statePath, $state);
                        })
                        ->columnSpanFull(),

                    TextInput::make($statePath.'.offset')
                        ->label(__('custom-fields::custom-fields.date_constraint.offset_value'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->visible(fn (Get $get): bool => in_array(
                            $get($statePath.'.preset'),
                            self::PRESETS_WITH_OFFSET,
                            true,
                        )),

                    Select::make($statePath.'.offset_unit')
                        ->label(__('custom-fields::custom-fields.date_constraint.unit'))
                        ->options(DateUnit::class)
                        ->default(DateUnit::Days->value)
                        ->visible(fn (Get $get): bool => in_array(
                            $get($statePath.'.preset'),
                            self::PRESETS_WITH_OFFSET,
                            true,
                        )),

                    Select::make($statePath.'.offset_direction')
                        ->label(__('custom-fields::custom-fields.date_constraint.direction'))
                        ->options(DateOffsetDirection::class)
                        ->default(DateOffsetDirection::After->value)
                        ->visible(fn (Get $get): bool => $get($statePath.'.preset') === 'today_offset'),

                    Select::make($statePath.'.field_reference')
                        ->label(__('custom-fields::custom-fields.date_constraint.reference_field'))
                        ->options(function (Get $get, ?CustomField $record): array {
                            $entityType = $record?->entity_type ?? $get('entity_type'); // @phpstan-ignore nullsafe.neverNull
                            $currentCode = $record?->code ?? $get('code'); // @phpstan-ignore nullsafe.neverNull

                            if (! $entityType) {
                                return [];
                            }

                            return CustomFields::newCustomFieldModel()::query()
                                ->where('entity_type', $entityType)
                                ->whereIn('type', ['date', 'date-time'])
                                ->when($currentCode, fn ($q) => $q->where('code', '!=', $currentCode))
                                ->where('active', true)
                                ->pluck('name', 'code')
                                ->all();
                        })
                        ->required()
                        ->rules([
                            fn (Get $get, ?CustomField $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                if (! $value) {
                                    return;
                                }

                                $currentCode = $record?->code ?? $get('code'); // @phpstan-ignore nullsafe.neverNull

                                if (! $currentCode) {
                                    return;
                                }

                                $entityType = $record?->entity_type ?? $get('entity_type'); // @phpstan-ignore nullsafe.neverNull

                                if (! $entityType) {
                                    return;
                                }

                                $fieldsWithReferences = self::buildReferenceMap($entityType, $currentCode, $value);

                                if (DateFieldReferenceValidator::hasCycle($currentCode, $fieldsWithReferences)) {
                                    $fail('Circular field reference detected. This would create an infinite loop.');
                                }
                            },
                        ])
                        ->visible(fn (Get $get): bool => $get($statePath.'.preset') === 'custom_field'),

                    DatePicker::make($statePath.'.fixed_date')
                        ->label(__('custom-fields::custom-fields.date_constraint.date'))
                        ->native(false)
                        ->format('Y-m-d')
                        ->required()
                        ->visible(fn (Get $get): bool => $get($statePath.'.preset') === 'fixed_date'),

                    Hidden::make($statePath.'.anchor'),
                ])
                ->columns(3),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeValidationRules(array $data): array
    {
        if (! isset($data['validation_rules']) || ! is_array($data['validation_rules'])) {
            return $data;
        }

        foreach (['min_date', 'max_date'] as $key) {
            if (! array_key_exists($key, $data['validation_rules'])) {
                continue;
            }

            $constraint = $data['validation_rules'][$key];

            if (! is_array($constraint) || ($constraint['preset'] ?? null) === 'none' || empty($constraint['anchor'] ?? null)) {
                $data['validation_rules'][$key] = null;

                continue;
            }

            unset($data['validation_rules'][$key]['preset']);

            $constraint = $data['validation_rules'][$key];
            $data['validation_rules'][$key] = [
                'anchor' => $constraint['anchor'],
                'offset' => (int) ($constraint['offset'] ?? 0),
                'offset_unit' => $constraint['offset_unit'] ?? DateUnit::Days->value,
                'offset_direction' => $constraint['offset_direction'] ?? DateOffsetDirection::After->value,
                ...array_filter([
                    'field_reference' => $constraint['field_reference'] ?? null,
                    'fixed_date' => $constraint['fixed_date'] ?? null,
                ]),
            ];
        }

        return $data;
    }

    /** @return array<string, string> */
    private static function presetOptions(string $context): array
    {
        $isMin = $context === 'min';

        return [
            'none' => 'No restriction',
            'today_preset' => $isMin ? 'Today or later' : 'Today or earlier',
            'today_offset' => 'Offset from today...',
            'custom_field' => ($isMin ? 'After' : 'Before').' another field...',
            'record_created' => ($isMin ? 'After' : 'Before').' record creation date...',
            'fixed_date' => 'Fixed date...',
        ];
    }

    private static function anchorToPreset(string $anchor, int $offset): string
    {
        if ($anchor === DateAnchor::Today->value && $offset === 0) {
            return 'today_preset';
        }

        return match ($anchor) {
            DateAnchor::Today->value => 'today_offset',
            DateAnchor::CustomField->value => 'custom_field',
            DateAnchor::RecordCreated->value => 'record_created',
            DateAnchor::FixedDate->value => 'fixed_date',
            default => 'none',
        };
    }

    private static function applyPresetDefaults(Set $set, string $statePath, ?string $preset): void
    {
        match ($preset) {
            'none' => $set($statePath, null),
            'today_preset', 'today_offset' => self::setAnchor($set, $statePath, DateAnchor::Today),
            'custom_field' => self::setAnchor($set, $statePath, DateAnchor::CustomField),
            'record_created' => self::setAnchor($set, $statePath, DateAnchor::RecordCreated),
            'fixed_date' => self::setAnchor($set, $statePath, DateAnchor::FixedDate),
            default => null,
        };
    }

    private static function setAnchor(Set $set, string $statePath, DateAnchor $anchor): void
    {
        $set($statePath.'.anchor', $anchor->value);
        $set($statePath.'.offset', 0);
        $set($statePath.'.offset_unit', DateUnit::Days->value);
        $set($statePath.'.offset_direction', DateOffsetDirection::After->value);

        if ($anchor !== DateAnchor::CustomField) {
            $set($statePath.'.field_reference', null);
        }

        if ($anchor !== DateAnchor::FixedDate) {
            $set($statePath.'.fixed_date', null);
        }
    }

    /** @return array<string, string> */
    private static function buildReferenceMap(string $entityType, string $currentCode, string $currentReference): array
    {
        $references = [];

        $fields = CustomFields::newCustomFieldModel()::query()
            ->where('entity_type', $entityType)
            ->whereIn('type', ['date', 'date-time'])
            ->where('code', '!=', $currentCode)
            ->where('active', true)
            ->get();

        foreach ($fields as $field) {
            $rules = $field->validation_rules;

            if (! $rules) {
                continue;
            }

            foreach (['min_date', 'max_date'] as $key) {
                $constraint = $rules->get($key);

                if (is_array($constraint) && ($constraint['anchor'] ?? null) === 'custom_field' && isset($constraint['field_reference'])) {
                    $references[$field->code] = $constraint['field_reference'];

                    break;
                }
            }
        }

        $references[$currentCode] = $currentReference;

        return $references;
    }
}
