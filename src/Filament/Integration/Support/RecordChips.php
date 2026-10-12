<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Support;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;
use Relaticle\CustomFields\Data\AvatarConfiguration;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\LinkReader;

/**
 * One linked record as the chip every record-side surface draws: a name, an avatar, a page to
 * open, and where the link came from. Built once so the table, the infolist and the picker
 * cannot describe the same link differently.
 */
final readonly class RecordChips
{
    public function __construct(private LinkReader $linkReader) {}

    /**
     * @param  array<int, int|string>  $recordIds  the order the chips are drawn in
     * @param  array<string, string>  $provenance  chip id => the sentence read on hover
     * @return array<int, array{id: string, name: string, avatarUrl: ?string, avatarShape: string, url: ?string, provenance: ?string}>
     */
    public function build(?EntityConfigurationData $entity, array $recordIds, array $provenance = []): array
    {
        if (! $entity instanceof EntityConfigurationData || $recordIds === []) {
            return [];
        }

        $records = $entity->newQuery()
            ->whereKey($recordIds)
            ->get()
            ->sortBy(fn (Model $record): int|false => array_search($record->getKey(), $recordIds, true));

        $avatarConfiguration = $entity->getAvatarConfiguration();
        $titleAttribute = $entity->getPrimaryAttribute();

        return $records
            ->map(function (Model $record) use ($entity, $avatarConfiguration, $titleAttribute, $provenance): array {
                $id = (string) $record->getKey();
                $name = $record->getAttribute($titleAttribute);

                return [
                    'id' => $id,
                    'name' => is_scalar($name) ? (string) $name : '',
                    'avatarUrl' => $this->avatarUrl($record, $avatarConfiguration),
                    'avatarShape' => $avatarConfiguration?->getCssClass() ?? 'rounded-full',
                    'url' => $this->recordUrl($record, $entity),
                    'provenance' => $provenance[$id] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Where each link came from, keyed by the record on the far end. Read from rows the caller
     * already holds: an actor name needs the morph loaded, and a table page that has not
     * loaded it says when the link was made rather than paying a query per row for who.
     *
     * @return array<string, string>
     */
    public function provenance(Model $subject, CustomField $customField): array
    {
        $definition = $customField->relationshipDefinition();

        // Provenance is read on a chip's hover, and only a paired relationship draws chips.
        if (! $customField->supportsPairing() || ! $definition instanceof CustomFieldRelationship) {
            return [];
        }

        $direction = $definition->readDirectionFor($customField);
        $provenance = [];

        foreach ($this->linkReader->orderedLinksFor($subject, $definition, $direction) as $link) {
            $id = (string) $this->linkReader->otherEndId($link, $subject, $direction);
            $sentence = $this->sentence($link);

            if ($sentence !== null) {
                $provenance[$id] = $sentence;
            }
        }

        return $provenance;
    }

    /**
     * The page a resource opens the record on, or null when the host registered no resource.
     * A named page the resource does not define is a configuration mistake, not a missing
     * link, so it is reported rather than swallowed.
     */
    public function recordUrl(Model $record, EntityConfigurationData $entity): ?string
    {
        $recordPage = $entity->getRecordPage();
        $resourceClass = $entity->getResourceClass();

        if ($recordPage === null || $resourceClass === null || ! class_exists($resourceClass)) {
            return null;
        }

        if (! method_exists($resourceClass, 'getUrl') || ! method_exists($resourceClass, 'getPages')) {
            return null;
        }

        if (! array_key_exists($recordPage, $resourceClass::getPages())) {
            throw new InvalidArgumentException(sprintf(
                "Entity '%s' has recordPage '%s' but %s does not define a '%s' page. Available pages: %s.",
                $entity->getLabelSingular(),
                $recordPage,
                class_basename($resourceClass),
                $recordPage,
                implode(', ', array_keys($resourceClass::getPages())),
            ));
        }

        return $resourceClass::getUrl($recordPage, ['record' => $record]);
    }

    /**
     * The page that creates a record of the target entity, for the picker's create-new. A host
     * whose resource has no create page gets no create-new rather than a dead link.
     */
    public function createUrl(EntityConfigurationData $entity): ?string
    {
        $resourceClass = $entity->getResourceClass();

        if ($resourceClass === null || ! class_exists($resourceClass)) {
            return null;
        }

        if (! method_exists($resourceClass, 'getUrl') || ! method_exists($resourceClass, 'getPages')) {
            return null;
        }

        return array_key_exists('create', $resourceClass::getPages())
            ? $resourceClass::getUrl('create')
            : null;
    }

    private function sentence(CustomFieldLink $link): ?string
    {
        $when = $link->active_from?->diffForHumans();

        if ($when === null) {
            return null;
        }

        $actor = $this->actorName($link);

        if ($actor !== null) {
            return __('custom-fields::custom-fields.relationships.provenance.by_actor', [
                'actor' => $actor,
                'time' => $when,
            ]);
        }

        return __('custom-fields::custom-fields.relationships.provenance.by_source', [
            'source' => $this->sourceLabel($link->source),
            'time' => $when,
        ]);
    }

    /**
     * A host writes its own source strings onto the ledger, so an unknown one reads as itself
     * rather than as the lang key that has no translation.
     */
    private function sourceLabel(string $source): string
    {
        $key = 'custom-fields::custom-fields.relationships.sources.'.$source;

        return Lang::has($key) ? __($key) : $source;
    }

    private function actorName(CustomFieldLink $link): ?string
    {
        if (! $link->relationLoaded('createdBy')) {
            return null;
        }

        $actor = $link->getRelation('createdBy');

        if (! $actor instanceof Model) {
            return null;
        }

        if ($actor instanceof HasName) {
            return $actor->getFilamentName();
        }

        $name = $actor->getAttribute('name');

        return is_scalar($name) && (string) $name !== '' ? (string) $name : null;
    }

    private function avatarUrl(Model $record, ?AvatarConfiguration $avatarConfiguration): ?string
    {
        if (! $avatarConfiguration instanceof AvatarConfiguration || ! $avatarConfiguration->hasAttribute()) {
            return null;
        }

        $url = $record->getAttribute($avatarConfiguration->attribute);

        return is_scalar($url) && (string) $url !== '' ? (string) $url : null;
    }
}
