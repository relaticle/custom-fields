<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Support\Imports\ColumnConfigurators;

use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\Matchers\LookupMatcherInterface;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Support\Facades\Entities;
use Throwable;

/**
 * Configures select columns that use either lookup relationships or options.
 */
final readonly class SelectColumnConfigurator implements ColumnConfiguratorInterface
{
    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        private LookupMatcherInterface $lookupMatcher
    ) {}

    /**
     * Configure a select column based on a custom field.
     *
     * @param  ImportColumn  $column  The column to configure
     * @param  CustomField  $customField  The custom field to base configuration on
     */
    public function configure(ImportColumn $column, CustomField $customField): void
    {
        if ($customField->lookup_type) {
            $this->configureLookupColumn($column, $customField);
        } else {
            $this->configureOptionsColumn($column, $customField);
        }
    }

    /**
     * Configure a column that uses a lookup relationship.
     *
     * @param  ImportColumn  $column  The column to configure
     * @param  CustomField  $customField  The custom field to base configuration on
     */
    private function configureLookupColumn(ImportColumn $column, CustomField $customField): void
    {
        // Configure column to use lookup relationship
        $column->castStateUsing(function ($state) use ($customField): ?int {
            if (blank($state)) {
                return null;
            }

            try {
                $entityInstance = Entities::getEntity($customField->lookup_type)->createModelInstance();

                $record = $this->lookupMatcher
                    ->find(
                        entityInstance: $entityInstance,
                        value: (string) $state
                    );

                if ($record instanceof Model) {
                    return (int) $record->getKey();
                }

                throw new RowImportFailedException(
                    sprintf("No %s record found matching '%s'", $customField->lookup_type, $state)
                );
            } catch (Throwable $throwable) {
                if ($throwable instanceof RowImportFailedException) {
                    throw $throwable;
                }

                throw new RowImportFailedException(
                    sprintf('Error resolving lookup value for %s: %s', $customField->name, $throwable->getMessage())
                );
            }
        });

        // Set example values for lookup types
        $this->setLookupTypeExamples($column, $customField);
    }

    /**
     * Configure a column that uses options.
     *
     * @param  ImportColumn  $column  The column to configure
     * @param  CustomField  $customField  The custom field to base configuration on
     */
    private function configureOptionsColumn(ImportColumn $column, CustomField $customField): void
    {
        // Configure column to use options
        $column->castStateUsing(function ($state) use ($customField) {
            if (blank($state)) {
                return null;
            }

            // Try exact match first
            $option = $customField->options
                ->where('name', $state)
                ->first();

            // If no match, try case-insensitive match
            if (! $option) {
                $option = $customField->options
                    ->first(fn ($opt): bool => strtolower((string) $opt->name) === strtolower($state));
            }

            if (! $option) {
                throw new RowImportFailedException(
                    sprintf("Invalid option value '%s' for %s. Valid options are: ", $state, $customField->name).
                    $customField->options->pluck('name')->implode(', ')
                );
            }

            return $option->getKey();
        });

        // Set example options
        $this->setOptionExamples($column, $customField);
    }

    /**
     * Set example values for a lookup type column.
     *
     * @param  ImportColumn  $column  The column to set examples for
     * @param  CustomField  $customField  The custom field
     */
    private function setLookupTypeExamples(ImportColumn $column, CustomField $customField): void
    {
        try {
            /** @var Model $entityInstance */
            $entityInstance = Entities::getEntity($customField->lookup_type)->createModelInstance();
            $recordTitleAttribute = Entities::getEntity($customField->lookup_type)->getPrimaryAttribute();

            // Get sample values from the lookup model
            /** @var Builder<Model> $query */
            $query = $entityInstance->newQuery();
            /** @var Builder<Model> $limitedQuery */
            $limitedQuery = $query->limit(2);
            $sampleValues = $limitedQuery->pluck($recordTitleAttribute)->toArray();

            if ($sampleValues !== []) {
                $column->example($sampleValues[0]);
            }
        } catch (Throwable) {
            // If there's an error getting example lookup values, provide generic example
            $column->example('Example value');
        }
    }

    /**
     * Set example values for an options-based column.
     *
     * @param  ImportColumn  $column  The column to set examples for
     * @param  CustomField  $customField  The custom field
     */
    private function setOptionExamples(ImportColumn $column, CustomField $customField): void
    {
        $options = $customField->options->pluck('name')->toArray();

        if ($options !== []) {
            $column->example($options[0]);
            $column->helperText('Valid options: '.implode(', ', $options));
        }
    }
}
