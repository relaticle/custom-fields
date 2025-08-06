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
 * Configures multi-select columns that use either lookup relationships or options.
 */
final readonly class MultiSelectColumnConfigurator implements ColumnConfiguratorInterface
{
    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        private LookupMatcherInterface $lookupMatcher
    ) {}

    /**
     * Configure a multi-select column based on a custom field.
     *
     * @param  ImportColumn  $column  The column to configure
     * @param  CustomField  $customField  The custom field to base configuration on
     */
    public function configure(ImportColumn $column, CustomField $customField): void
    {
        $column->array(',');

        if ($customField->lookup_type) {
            $this->configureLookupColumn($column, $customField);
        } else {
            $this->configureOptionsColumn($column, $customField);
        }
    }

    /**
     * Configure a column that uses lookup relationships.
     *
     * @param  ImportColumn  $column  The column to configure
     * @param  CustomField  $customField  The custom field to base configuration on
     */
    private function configureLookupColumn(ImportColumn $column, CustomField $customField): void
    {
        // Configure column to use lookup relationship
        $column->castStateUsing(function ($state) use ($customField) {
            if (blank($state)) {
                return [];
            }

            if (! is_array($state)) {
                $state = [$state];
            }

            try {
                $entityInstance = Entities::getEntity($customField->lookup_type)->createModelInstance();

                $foundIds = [];
                $missingValues = [];

                foreach ($state as $value) {
                    $record = $this->lookupMatcher->find(
                        entityInstance: $entityInstance,
                        value: (string) $value
                    );

                    if ($record instanceof Model) {
                        $foundIds[] = (int) $record->getKey();
                    } else {
                        $missingValues[] = $value;
                    }
                }

                // Check if all values were found
                if ($missingValues !== []) {
                    throw new RowImportFailedException(
                        sprintf('Could not find %s records with values: ', $customField->lookup_type).
                        implode(', ', $missingValues)
                    );
                }

                return $foundIds;
            } catch (Throwable $throwable) {
                if ($throwable instanceof RowImportFailedException) {
                    throw $throwable;
                }

                throw new RowImportFailedException(
                    sprintf('Error resolving lookup values for %s: %s', $customField->name, $throwable->getMessage())
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
                return [];
            }

            if (! is_array($state)) {
                $state = [$state];
            }

            $foundIds = [];
            $missingValues = [];
            $options = $customField->options->toArray();

            // Map of lowercase option names to their IDs for case-insensitive matching
            $optionsLowercaseMap = array_reduce($options, function (array $map, array $option) {
                $map[strtolower((string) $option['name'])] = $option['id'];

                return $map;
            }, []);

            foreach ($state as $value) {
                // Try exact match first
                $option = $customField->options
                    ->where('name', $value)
                    ->first();

                // If no match, try case-insensitive match
                if (! $option && isset($optionsLowercaseMap[strtolower($value)])) {
                    $foundIds[] = $optionsLowercaseMap[strtolower($value)];
                } elseif ($option) {
                    $foundIds[] = $option->getKey();
                } else {
                    $missingValues[] = $value;
                }
            }

            // Check if all values were found
            if ($missingValues !== []) {
                throw new RowImportFailedException(
                    sprintf('Invalid option values for %s: ', $customField->name).
                    implode(', ', $missingValues).'. Valid options are: '.
                    $customField->options->pluck('name')->implode(', ')
                );
            }

            return $foundIds;
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
                $column->example(implode(', ', $sampleValues));
                $column->helperText('Separate multiple values with commas');
            }
        } catch (Throwable) {
            // If there's an error getting example lookup values, provide generic examples
            $column->example('Value1, Value2');
            $column->helperText('Separate multiple values with commas');
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
            // Get up to 2 options for the example
            $exampleOptions = array_slice($options, 0, 2);
            $column->example(implode(', ', $exampleOptions));
            $column->helperText('Separate multiple values with commas. Valid options: '.implode(', ', $options));
        }
    }
}
