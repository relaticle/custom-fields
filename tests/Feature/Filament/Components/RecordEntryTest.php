<?php

declare(strict_types=1);

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\RecordEntry;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

mutates(RecordEntry::class);

final class ViewPostCustomFields extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Post $post;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->post)
            ->components([CustomFields::infolist()->forModel($this->post)->build()]);
    }

    public function render(): string
    {
        return '<div>{{ $this->infolist }}</div>';
    }
}

describe('RecordEntry', function (): void {
    beforeEach(function (): void {
        $this->field = CustomField::factory()->create([
            'custom_field_section_id' => CustomFieldSection::factory()->forEntityType(Post::class)->create(['active' => true])->getKey(),
            'name' => 'Related Post',
            'code' => 'related_post',
            'type' => 'record',
            'entity_type' => Post::class,
            'lookup_type' => Post::class,
        ]);
    });

    it('shows the entity icon for a linked record without an image and the image for one with it', function (): void {
        registerLookupEntity(Post::class, primaryAttribute: 'title', avatarAttribute: 'cover_url', icon: 'heroicon-o-newspaper');

        $pictured = Post::factory()->create(['cover_url' => 'https://example.test/cover.png']);
        $unpictured = Post::factory()->create(['cover_url' => null]);

        $withImage = Post::factory()->create();
        $withImage->saveCustomFieldValue($this->field, $pictured->getKey());

        $withoutImage = Post::factory()->create();
        $withoutImage->saveCustomFieldValue($this->field, $unpictured->getKey());

        livewire(ViewPostCustomFields::class, ['post' => $withImage])
            ->assertSeeHtml('src="https://example.test/cover.png"')
            ->assertDontSeeHtml('data-record-icon');

        livewire(ViewPostCustomFields::class, ['post' => $withoutImage])
            ->assertSee($unpictured->title)
            ->assertSeeHtml('data-record-icon');
    });

    it('shows no icon for a linked record whose entity has no image', function (): void {
        registerLookupEntity(Post::class, primaryAttribute: 'title');

        $linked = Post::factory()->create();
        $post = Post::factory()->create();
        $post->saveCustomFieldValue($this->field, $linked->getKey());

        livewire(ViewPostCustomFields::class, ['post' => $post])
            ->assertSee($linked->title)
            ->assertDontSeeHtml('data-record-icon');
    });
});
