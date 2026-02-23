<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                    Select::make("{$statePath}.preset")
                        ->label('Constraint')
                        ->options(self::presetOptions($context))
                        ->default('none')
                        ->live()
                        ->afterStateHydrated(function ($component, Get $get) use ($statePath): void {
                            $anchor = $get("{$statePath}.anchor");

                            if ($anchor === null) {
                                $component->state('none');

                                return;
                            }

                            $component->state(self::anchorToPreset(
                                $anchor,
                                (int) ($get("{$statePath}.offset") ?? 0),
                            ));
                        })
                        ->afterStateUpdated(function (Set $set, ?string $state) use ($statePath): void {
                            self::applyPresetDefaults($set, $statePath, $state);
                        })
                        ->columnSpanFull(),

                    TextInput::make("{$statePath}.offset")
                        ->label('Offset value')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->visible(fn (Get $get): bool => in_array(
                            $get("{$statePath}.preset"),
                            self::PRESETS_WITH_OFFSET,
                            true,
                        )),

                    Select::make("{$statePath}.offset_unit")
                        ->label('Unit')
                        ->options(DateUnit::class)
                        ->default(DateUnit::Days->value)
                        ->visible(fn (Get $get): bool => in_array(
                            $get("{$statePath}.preset"),
                            self::PRESETS_WITH_OFFSET,
                            true,
                        )),

                    Select::make("{$statePath}.offset_direction")
                        ->label('Direction')
                        ->options(DateOffsetDirection::class)
                        ->default(DateOffsetDirection::After->value)
                        ->visible(fn (Get $get): bool => $get("{$statePath}.preset") === 'today_offset'),

                    Select::make("{$statePath}.field_reference")
                        ->label('Reference field')
                        ->options(function (Get $get, ?CustomField $record): array {
                            $entityType = $record?->entity_type ?? $get('../../../entity_type');
                            $currentCode = $record?->code ?? $get('../../../code');

                            if (! $entityType) {
                                return [];
                            }

                            return CustomField::query()
                                ->where('entity_type', $entityType)
                                ->whereIn('type', ['date', 'date-time'])
                                ->when($currentCode, fn ($q) => $q->where('code', '!=', $currentCode))
                                ->where('active', true)
                                ->pluck('name', 'code')
                                ->all();
                        })
                        ->required()
                        ->rules([
                            fn (Get $get, ?CustomField $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                                if (! $value) {
                                    return;
                                }

                                $currentCode = $record?->code ?? $get('../../../code');

                                if (! $currentCode) {
                                    return;
                                }

                                $entityType = $record?->entity_type ?? $get('../../../entity_type');

                                if (! $entityType) {
                                    return;
                                }

                                $fieldsWithReferences = self::buildReferenceMap($entityType, $currentCode, $value);

                                if (DateFieldReferenceValidator::hasCycle($currentCode, $fieldsWithReferences)) {
                                    $fail('Circular field reference detected. This would create an infinite loop.');
                                }
                            },
                        ])
                        ->visible(fn (Get $get): bool => $get("{$statePath}.preset") === 'custom_field'),

                    DatePicker::make("{$statePath}.fixed_date")
                        ->label('Date')
                        ->native(false)
                        ->format('Y-m-d')
                        ->required()
                        ->visible(fn (Get $get): bool => $get("{$statePath}.preset") === 'fixed_date'),
                ])
                ->columns(3),
        ];
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
            'none' => self::clearConstraint($set, $statePath),
            'today_preset' => self::setTodayPreset($set, $statePath),
            'today_offset' => self::setAnchor($set, $statePath, DateAnchor::Today),
            'custom_field' => self::setAnchor($set, $statePath, DateAnchor::CustomField),
            'record_created' => self::setAnchor($set, $statePath, DateAnchor::RecordCreated),
            'fixed_date' => self::setAnchor($set, $statePath, DateAnchor::FixedDate),
            default => null,
        };
    }

    private static function clearConstraint(Set $set, string $statePath): void
    {
        $set($statePath, null);
    }

    private static function setTodayPreset(Set $set, string $statePath): void
    {
        $set("{$statePath}.anchor", DateAnchor::Today->value);
        $set("{$statePath}.offset", 0);
        $set("{$statePath}.offset_unit", DateUnit::Days->value);
        $set("{$statePath}.offset_direction", DateOffsetDirection::After->value);
        $set("{$statePath}.field_reference", null);
        $set("{$statePath}.fixed_date", null);
    }

    private static function setAnchor(Set $set, string $statePath, DateAnchor $anchor): void
    {
        $set("{$statePath}.anchor", $anchor->value);
        $set("{$statePath}.offset", 0);
        $set("{$statePath}.offset_unit", DateUnit::Days->value);
        $set("{$statePath}.offset_direction", DateOffsetDirection::After->value);

        if ($anchor !== DateAnchor::CustomField) {
            $set("{$statePath}.field_reference", null);
        }

        if ($anchor !== DateAnchor::FixedDate) {
            $set("{$statePath}.fixed_date", null);
        }
    }

    /**
     * Build a map of field_code => referenced_field_code for cycle detection.
     *
     * @return array<string, string>
     */
    private static function buildReferenceMap(string $entityType, string $currentCode, string $currentReference): array
    {
        $references = [];

        $fields = CustomField::query()
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
