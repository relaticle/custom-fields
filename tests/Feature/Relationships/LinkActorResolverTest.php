<?php

declare(strict_types=1);

use Relaticle\CustomFields\Contracts\LinkActorResolverInterface;
use Relaticle\CustomFields\Services\Relationships\AuthenticatedActorResolver;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

it('resolves the authenticated user as the actor', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(app(LinkActorResolverInterface::class))->toBeInstanceOf(AuthenticatedActorResolver::class)
        ->and(app(LinkActorResolverInterface::class)->resolve())->toBeSameModel($user);
});

it('resolves null when nobody is authenticated', function (): void {
    auth()->logout();

    expect(app(LinkActorResolverInterface::class)->resolve())->toBeNull();
});

it('lets a host swap the resolver for its own actor', function (): void {
    $agent = Post::factory()->create();

    app()->singleton(LinkActorResolverInterface::class, fn (): LinkActorResolverInterface => new class($agent) implements LinkActorResolverInterface
    {
        public function __construct(private readonly Post $agent) {}

        public function resolve(): Post
        {
            return $this->agent;
        }
    });

    expect(app(LinkActorResolverInterface::class)->resolve())->toBeSameModel($agent);
});
