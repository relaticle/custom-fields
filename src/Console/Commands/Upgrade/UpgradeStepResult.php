<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade;

/**
 * Result of an upgrade step execution.
 */
final readonly class UpgradeStepResult
{
    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public bool $success,
        public int $itemsProcessed,
        public int $itemsFailed = 0,
        public array $errors = [],
        public array $warnings = [],
    ) {}

    public static function success(int $processed, int $failed = 0): self
    {
        return new self(
            success: $failed === 0,
            itemsProcessed: $processed,
            itemsFailed: $failed,
        );
    }

    public static function skipped(string $reason): self
    {
        return new self(
            success: true,
            itemsProcessed: 0,
            warnings: [$reason],
        );
    }

    public static function failed(string $error): self
    {
        return new self(
            success: false,
            itemsProcessed: 0,
            itemsFailed: 0,
            errors: [$error],
        );
    }
}
