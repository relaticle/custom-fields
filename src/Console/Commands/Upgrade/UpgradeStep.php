<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade;

use Illuminate\Console\Command;

/**
 * Interface for modular upgrade steps in the 2.x → 3.x migration.
 */
interface UpgradeStep
{
    /**
     * Get the step name for display.
     */
    public function name(): string;

    /**
     * Get a brief description of what this step does.
     */
    public function description(): string;

    /**
     * Execute the upgrade step.
     *
     * @param  bool  $dryRun  If true, only simulate the migration without making changes
     * @param  Command  $command  The parent command for output
     */
    public function execute(bool $dryRun, Command $command): UpgradeStepResult;
}
