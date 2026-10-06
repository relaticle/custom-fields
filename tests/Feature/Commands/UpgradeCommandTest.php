<?php

declare(strict_types=1);

use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('runs every default step in dry-run mode without errors', function (): void {
    $this->artisan('custom-fields:upgrade', ['--dry-run' => true])
        ->expectsOutputToContain('Step 1/3: Validate Schema')
        ->expectsOutputToContain('Step 2/3: Migrate Record Links')
        ->expectsOutputToContain('Step 3/3: Clear Caches')
        ->expectsOutput('DRY RUN COMPLETE - No changes were made')
        ->assertSuccessful();
});

it('runs every default step when forced', function (): void {
    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->expectsOutputToContain('Step 1/3: Validate Schema')
        ->expectsOutputToContain('Step 2/3: Migrate Record Links')
        ->expectsOutputToContain('Step 3/3: Clear Caches')
        ->expectsOutput('UPGRADE COMPLETE')
        ->assertSuccessful();
});

it('runs the purge only when it is asked for', function (): void {
    $this->artisan('custom-fields:upgrade', ['--force' => true])
        ->expectsOutputToContain('Skipping: purge-record-values')
        ->assertSuccessful();

    $this->artisan('custom-fields:upgrade', ['--force' => true, '--purge' => true])
        ->expectsOutputToContain('Step 3/4: Purge Migrated Record Values')
        ->assertSuccessful();
});

it('skips clear-caches when requested', function (): void {
    $this->artisan('custom-fields:upgrade', [
        '--force' => true,
        '--skip' => 'clear-caches',
    ])
        ->expectsOutputToContain('Skipping: clear-caches')
        ->assertSuccessful();
});

it('fails with a clear error when --skip names an unknown step', function (): void {
    $this->artisan('custom-fields:upgrade', [
        '--force' => true,
        '--skip' => 'not-a-real-step',
    ])
        ->expectsOutputToContain('Unknown --skip value(s): not-a-real-step.')
        ->expectsOutput('Valid steps: validate-schema, migrate-record-links, purge-record-values, clear-caches.')
        ->assertFailed();
});

it('rejects a falsy step name in --skip instead of ignoring it', function (): void {
    $this->artisan('custom-fields:upgrade', [
        '--force' => true,
        '--skip' => '0',
    ])
        ->expectsOutputToContain('Unknown --skip value(s): 0.')
        ->assertFailed();
});

it('ignores an empty element from a trailing comma in --skip', function (): void {
    $this->artisan('custom-fields:upgrade', [
        '--force' => true,
        '--skip' => 'clear-caches,',
    ])
        ->expectsOutputToContain('Skipping: clear-caches')
        ->assertSuccessful();
});

it('does not undercount total steps when --skip repeats the same value', function (): void {
    $this->artisan('custom-fields:upgrade', [
        '--force' => true,
        '--skip' => 'clear-caches,clear-caches',
    ])
        ->expectsOutputToContain('Step 1/2: Validate Schema')
        ->assertSuccessful();
});
