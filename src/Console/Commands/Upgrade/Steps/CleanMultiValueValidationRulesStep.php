<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

use Illuminate\Console\Command;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStep;
use Relaticle\CustomFields\Console\Commands\Upgrade\UpgradeStepResult;
use Relaticle\CustomFields\CustomFields;
use Throwable;

/**
 * Removes string-only validation rules from multi-value field types.
 *
 * Multi-value fields (link, email, phone) store arrays in json_value.
 * Rules like starts_with and url expect strings and cause TypeErrors.
 */
final class CleanMultiValueValidationRulesStep implements UpgradeStep
{
    /** @var list<string> */
    private const MULTI_VALUE_FIELD_TYPES = ['link', 'email', 'phone'];

    /** @var list<string> */
    private const STRING_ONLY_RULES = [
        'starts_with',
        'ends_with',
        'doesnt_start_with',
        'doesnt_end_with',
        'url',
        'email',
        'string',
        'alpha',
        'alpha_num',
        'alpha_dash',
        'regex',
        'not_regex',
        'active_url',
        'ascii',
        'ip',
        'ipv4',
        'ipv6',
        'json',
        'mac_address',
        'uuid',
        'uppercase',
    ];

    public function name(): string
    {
        return 'Clean Multi-Value Validation Rules';
    }

    public function description(): string
    {
        return 'Remove string-only validation rules (starts_with, url, etc.) from multi-value fields';
    }

    public function execute(bool $dryRun, Command $command): UpgradeStepResult
    {
        $fieldModel = CustomFields::newCustomFieldModel();

        $affectedFields = $fieldModel->newQuery()
            ->whereIn('type', self::MULTI_VALUE_FIELD_TYPES)
            ->whereNotNull('validation_rules')
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($affectedFields as $field) {
            $rules = $field->validation_rules ?? collect();

            $invalidKeys = $rules->keys()->intersect(self::STRING_ONLY_RULES);

            if ($invalidKeys->isEmpty()) {
                continue;
            }

            $ruleNames = $invalidKeys->implode(', ');
            $command->line(sprintf("  Processing field '%s' (type: %s, id: %s): removing [%s]", $field->name, $field->type, $field->id, $ruleNames));

            if (! $dryRun) {
                $cleanedRules = $rules->except(self::STRING_ONLY_RULES)->toArray();

                try {
                    $field->update([
                        'validation_rules' => $cleanedRules === [] ? null : $cleanedRules,
                    ]);
                    $processed++;
                } catch (Throwable $e) {
                    $command->line(sprintf('  <error>Failed to update field %s: %s</error>', $field->id, $e->getMessage()));
                    $failed++;
                }
            } else {
                $processed++;
            }
        }

        if ($processed === 0 && $failed === 0) {
            $command->line('  <info>No fields need cleanup</info>');

            return UpgradeStepResult::skipped('No multi-value fields with string-only validation rules found');
        }

        return UpgradeStepResult::success($processed, $failed);
    }
}
