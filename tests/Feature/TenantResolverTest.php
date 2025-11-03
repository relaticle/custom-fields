<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    // Clean up any previous resolver
    TenantContextService::clearTenantResolver();
});

afterEach(function (): void {
    // Always clean up after tests
    TenantContextService::clearTenantResolver();
    Context::flush();
});

describe('Custom Tenant Resolver', function (): void {
    it('allows registering a custom tenant resolver', function (): void {
        $expectedTenantId = 42;

        CustomFields::resolveTenantUsing(fn (): int => $expectedTenantId);

        expect(TenantContextService::getCurrentTenantId())->toBe($expectedTenantId);
    });

    it('custom resolver takes priority over Laravel Context', function (): void {
        $contextTenantId = 100;
        $customTenantId = 200;

        // Set Laravel Context
        TenantContextService::setTenantId($contextTenantId);

        // Register custom resolver
        CustomFields::resolveTenantUsing(fn (): int => $customTenantId);

        // Custom resolver should win
        expect(TenantContextService::getCurrentTenantId())->toBe($customTenantId);
    });

    it('falls back to Laravel Context when no custom resolver is set', function (): void {
        $tenantId = 150;

        TenantContextService::setTenantId($tenantId);

        expect(TenantContextService::getCurrentTenantId())->toBe($tenantId);
    });

    it('returns null when no tenant context is available', function (): void {
        expect(TenantContextService::getCurrentTenantId())->toBeNull();
    });

    it('can clear custom resolver', function (): void {
        CustomFields::resolveTenantUsing(fn (): int => 999);

        expect(TenantContextService::getCurrentTenantId())->toBe(999);

        TenantContextService::clearTenantResolver();

        expect(TenantContextService::getCurrentTenantId())->toBeNull();
    });

    it('works with dynamic tenant resolution based on auth', function (): void {
        auth()->logout();

        $user = User::factory()->create();

        CustomFields::resolveTenantUsing(fn () => auth()->user()?->id);

        // Before login
        expect(TenantContextService::getCurrentTenantId())->toBeNull();

        // After login
        $this->actingAs($user);
        expect(TenantContextService::getCurrentTenantId())->toBe($user->id);
    });
});

describe('Tenant Resolver with Context Service', function (): void {
    it('resolver can access closure variables', function (): void {
        $companyId = 999;

        CustomFields::resolveTenantUsing(fn (): int => $companyId);

        expect(TenantContextService::getCurrentTenantId())->toBe($companyId);
    });

    it('resolver can be changed dynamically', function (): void {
        $tenant1 = 111;
        $tenant2 = 222;

        CustomFields::resolveTenantUsing(fn (): int => $tenant1);
        expect(TenantContextService::getCurrentTenantId())->toBe($tenant1);

        CustomFields::resolveTenantUsing(fn (): int => $tenant2);
        expect(TenantContextService::getCurrentTenantId())->toBe($tenant2);
    });

    it('resolver can return string tenant IDs', function (): void {
        $tenantUuid = 'org_12345';

        CustomFields::resolveTenantUsing(fn (): string => $tenantUuid);

        expect(TenantContextService::getCurrentTenantId())->toBe($tenantUuid);
    });

    it('resolver can return null for no tenant', function (): void {
        CustomFields::resolveTenantUsing(fn (): null => null);

        expect(TenantContextService::getCurrentTenantId())->toBeNull();
    });

    it('handles resolver exceptions gracefully', function (): void {
        CustomFields::resolveTenantUsing(function (): void {
            throw new RuntimeException('Tenant resolution failed');
        });

        expect(fn (): int|string|null => TenantContextService::getCurrentTenantId())
            ->toThrow(RuntimeException::class, 'Tenant resolution failed');
    });
});
