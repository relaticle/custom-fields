# Custom Fields in Relation Managers

This example demonstrates how to display custom fields from a related model in a Filament relation manager table.

## Use Case

You have two models:
- `Registration` - the main model
- `Member` - a related model that has custom fields

You want to display the Member's custom fields in the Registration's relation manager table.

## Usage

### Basic Example

```php
use Filament\Tables\Table;
use Relaticle\CustomFields\Facades\CustomFields;

class RegistrationRelationManager extends RelationManager
{
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // Your regular columns...
                Tables\Columns\TextColumn::make('name'),
                
                // Add custom fields from the related Member model
                ...CustomFields::table()
                    ->forModel(Member::class)
                    ->forRelation('member')  // The relation name on Registration
                    ->columns(),
            ])
            ->filters([
                // Add filters for custom fields from the related Member model
                ...CustomFields::table()
                    ->forModel(Member::class)
                    ->forRelation('member')
                    ->filters(),
            ]);
    }
}
```

### Using Post and User Example

If you have a `Post` model with an `author` relationship to `User`:

```php
use Filament\Tables\Table;
use Relaticle\CustomFields\Facades\CustomFields;

class PostResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                
                // Display User custom fields in the Post table
                ...CustomFields::table()
                    ->forModel(User::class)
                    ->forRelation('author')
                    ->columns(),
            ])
            ->filters([
                ...CustomFields::table()
                    ->forModel(User::class)
                    ->forRelation('author')
                    ->filters(),
            ]);
    }
}
```

## How It Works

The `forRelation()` method tells the CustomFields builder to:

1. **State Resolution**: Resolve values by navigating through the relation
   - Instead of: `$record->getCustomFieldValue($field)`
   - It does: `$record->member->getCustomFieldValue($field)`

2. **Searching**: Search on related model's custom fields
   - Uses `whereHas()` to search through the relation

3. **Filtering**: Filter by related model's custom fields
   - Applies filters through the relation

**Note**: Sorting is currently disabled for related custom fields due to the complexity of joining through relations with custom field values. Columns will display as non-sortable when using `forRelation()`.

## Requirements

- The related model (`Member`, `User`, etc.) must implement `HasCustomFields`
- The relation must be defined on the main model
- Custom fields must be configured for the related model

## Method Chaining

You can combine `forRelation()` with other builder methods:

```php
CustomFields::table()
    ->forModel(Member::class)
    ->forRelation('member')
    ->only(['bio', 'phone'])  // Only show specific fields
    ->columns()
```

```php
CustomFields::table()
    ->forModel(Member::class)
    ->forRelation('member')
    ->except(['internal_notes'])  // Exclude specific fields
    ->columns()
```
