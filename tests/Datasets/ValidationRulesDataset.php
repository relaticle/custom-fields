<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\ValidationRule;

// Comprehensive validation rules dataset with all 84 rules
dataset('validation_rules_with_parameters', fn (): array => [
    // No parameter rules
    'required' => [
        'rule' => ValidationRule::REQUIRED->value,
        'parameters' => [],
        'validValue' => 'some value',
        'invalidValue' => null,
    ],
    'accepted' => [
        'rule' => ValidationRule::ACCEPTED->value,
        'parameters' => [],
        'validValue' => true,
        'invalidValue' => false,
    ],
    'active_url' => [
        'rule' => ValidationRule::ACTIVE_URL->value,
        'parameters' => [],
        'validValue' => 'https://example.com',
        'invalidValue' => 'not-a-url',
    ],
    'alpha' => [
        'rule' => ValidationRule::ALPHA->value,
        'parameters' => [],
        'validValue' => 'abcdef',
        'invalidValue' => 'abc123',
    ],
    'alpha_dash' => [
        'rule' => ValidationRule::ALPHA_DASH->value,
        'parameters' => [],
        'validValue' => 'abc-def_123',
        'invalidValue' => 'abc def!',
    ],
    'alpha_num' => [
        'rule' => ValidationRule::ALPHA_NUM->value,
        'parameters' => [],
        'validValue' => 'abc123',
        'invalidValue' => 'abc-123',
    ],
    'array' => [
        'rule' => ValidationRule::ARRAY->value,
        'parameters' => [],
        'validValue' => ['item1', 'item2'],
        'invalidValue' => 'not-array',
    ],
    'ascii' => [
        'rule' => ValidationRule::ASCII->value,
        'parameters' => [],
        'validValue' => 'Hello World',
        'invalidValue' => 'Héllo Wörld',
    ],
    'boolean' => [
        'rule' => ValidationRule::BOOLEAN->value,
        'parameters' => [],
        'validValue' => true,
        'invalidValue' => 'not-boolean',
    ],
    'confirmed' => [
        'rule' => ValidationRule::CONFIRMED->value,
        'parameters' => [],
        'validValue' => 'password',
        'invalidValue' => 'password', // Note: needs password_confirmation field
    ],
    'current_password' => [
        'rule' => ValidationRule::CURRENT_PASSWORD->value,
        'parameters' => [],
        'validValue' => 'current-password',
        'invalidValue' => 'wrong-password',
    ],
    'date' => [
        'rule' => ValidationRule::DATE->value,
        'parameters' => [],
        'validValue' => '2023-12-25',
        'invalidValue' => 'not-a-date',
    ],
    'declined' => [
        'rule' => ValidationRule::DECLINED->value,
        'parameters' => [],
        'validValue' => false,
        'invalidValue' => true,
    ],
    'distinct' => [
        'rule' => ValidationRule::DISTINCT->value,
        'parameters' => [],
        'validValue' => ['a', 'b', 'c'],
        'invalidValue' => ['a', 'a', 'b'],
    ],
    'email' => [
        'rule' => ValidationRule::EMAIL->value,
        'parameters' => [],
        'validValue' => 'test@example.com',
        'invalidValue' => 'not-an-email',
    ],
    'file' => [
        'rule' => ValidationRule::FILE->value,
        'parameters' => [],
        'validValue' => null, // UploadedFile instance needed
        'invalidValue' => 'not-a-file',
    ],
    'filled' => [
        'rule' => ValidationRule::FILLED->value,
        'parameters' => [],
        'validValue' => 'some value',
        'invalidValue' => '',
    ],
    'image' => [
        'rule' => ValidationRule::IMAGE->value,
        'parameters' => [],
        'validValue' => null, // UploadedFile image needed
        'invalidValue' => 'not-an-image',
    ],
    'integer' => [
        'rule' => ValidationRule::INTEGER->value,
        'parameters' => [],
        'validValue' => 123,
        'invalidValue' => 12.3,
    ],
    'ip' => [
        'rule' => ValidationRule::IP->value,
        'parameters' => [],
        'validValue' => '192.168.1.1',
        'invalidValue' => 'not-an-ip',
    ],
    'ipv4' => [
        'rule' => ValidationRule::IPV4->value,
        'parameters' => [],
        'validValue' => '192.168.1.1',
        'invalidValue' => '2001:db8::1',
    ],
    'ipv6' => [
        'rule' => ValidationRule::IPV6->value,
        'parameters' => [],
        'validValue' => '2001:db8::1',
        'invalidValue' => '192.168.1.1',
    ],
    'json' => [
        'rule' => ValidationRule::JSON->value,
        'parameters' => [],
        'validValue' => '{"key": "value"}',
        'invalidValue' => 'not-json',
    ],
    'mac_address' => [
        'rule' => ValidationRule::MAC_ADDRESS->value,
        'parameters' => [],
        'validValue' => '00:14:22:01:23:45',
        'invalidValue' => 'not-a-mac',
    ],
    'numeric' => [
        'rule' => ValidationRule::NUMERIC->value,
        'parameters' => [],
        'validValue' => '123.45',
        'invalidValue' => 'not-numeric',
    ],
    'password' => [
        'rule' => ValidationRule::PASSWORD->value,
        'parameters' => [],
        'validValue' => 'StrongPassword123!',
        'invalidValue' => 'weak',
    ],
    'present' => [
        'rule' => ValidationRule::PRESENT->value,
        'parameters' => [],
        'validValue' => '',
        'invalidValue' => null, // Field must be present but can be empty
    ],
    'prohibited' => [
        'rule' => ValidationRule::PROHIBITED->value,
        'parameters' => [],
        'validValue' => null,
        'invalidValue' => 'value',
    ],
    'string' => [
        'rule' => ValidationRule::STRING->value,
        'parameters' => [],
        'validValue' => 'string value',
        'invalidValue' => 123,
    ],
    'timezone' => [
        'rule' => ValidationRule::TIMEZONE->value,
        'parameters' => [],
        'validValue' => 'America/New_York',
        'invalidValue' => 'invalid-timezone',
    ],
    'uppercase' => [
        'rule' => ValidationRule::UPPERCASE->value,
        'parameters' => [],
        'validValue' => 'UPPERCASE',
        'invalidValue' => 'lowercase',
    ],
    'url' => [
        'rule' => ValidationRule::URL->value,
        'parameters' => [],
        'validValue' => 'https://example.com',
        'invalidValue' => 'not-a-url',
    ],
    'uuid' => [
        'rule' => ValidationRule::UUID->value,
        'parameters' => [],
        'validValue' => '550e8400-e29b-41d4-a716-446655440000',
        'invalidValue' => 'not-a-uuid',
    ],

    // Single parameter rules
    'min_length' => [
        'rule' => ValidationRule::MIN->value,
        'parameters' => [3],
        'validValue' => 'abc',
        'invalidValue' => 'ab',
    ],
    'max_length' => [
        'rule' => ValidationRule::MAX->value,
        'parameters' => [10],
        'validValue' => 'short',
        'invalidValue' => 'this is too long for max validation',
    ],
    'size' => [
        'rule' => ValidationRule::SIZE->value,
        'parameters' => [5],
        'validValue' => 'exact',
        'invalidValue' => 'wrong',
    ],
    'digits' => [
        'rule' => ValidationRule::DIGITS->value,
        'parameters' => [4],
        'validValue' => '1234',
        'invalidValue' => '123',
    ],
    'max_digits' => [
        'rule' => ValidationRule::MAX_DIGITS->value,
        'parameters' => [5],
        'validValue' => '12345',
        'invalidValue' => '123456',
    ],
    'min_digits' => [
        'rule' => ValidationRule::MIN_DIGITS->value,
        'parameters' => [3],
        'validValue' => '123',
        'invalidValue' => '12',
    ],
    'multiple_of' => [
        'rule' => ValidationRule::MULTIPLE_OF->value,
        'parameters' => [5],
        'validValue' => 25,
        'invalidValue' => 23,
    ],
    'after' => [
        'rule' => ValidationRule::AFTER->value,
        'parameters' => ['2023-01-01'],
        'validValue' => '2023-06-01',
        'invalidValue' => '2022-12-31',
    ],
    'after_or_equal' => [
        'rule' => ValidationRule::AFTER_OR_EQUAL->value,
        'parameters' => ['2023-01-01'],
        'validValue' => '2023-01-01',
        'invalidValue' => '2022-12-31',
    ],
    'before' => [
        'rule' => ValidationRule::BEFORE->value,
        'parameters' => ['2023-12-31'],
        'validValue' => '2023-06-01',
        'invalidValue' => '2024-01-01',
    ],
    'before_or_equal' => [
        'rule' => ValidationRule::BEFORE_OR_EQUAL->value,
        'parameters' => ['2023-12-31'],
        'validValue' => '2023-12-31',
        'invalidValue' => '2024-01-01',
    ],
    'date_equals' => [
        'rule' => ValidationRule::DATE_EQUALS->value,
        'parameters' => ['2023-06-15'],
        'validValue' => '2023-06-15',
        'invalidValue' => '2023-06-16',
    ],
    'date_format' => [
        'rule' => ValidationRule::DATE_FORMAT->value,
        'parameters' => ['Y-m-d'],
        'validValue' => '2023-06-15',
        'invalidValue' => '15/06/2023',
    ],
    'gt' => [
        'rule' => ValidationRule::GT->value,
        'parameters' => ['10'],
        'validValue' => 15,
        'invalidValue' => 5,
    ],
    'gte' => [
        'rule' => ValidationRule::GTE->value,
        'parameters' => ['10'],
        'validValue' => 10,
        'invalidValue' => 9,
    ],
    'lt' => [
        'rule' => ValidationRule::LT->value,
        'parameters' => ['10'],
        'validValue' => 5,
        'invalidValue' => 15,
    ],
    'lte' => [
        'rule' => ValidationRule::LTE->value,
        'parameters' => ['10'],
        'validValue' => 10,
        'invalidValue' => 15,
    ],

    // Two parameter rules
    'between_numeric' => [
        'rule' => ValidationRule::BETWEEN->value,
        'parameters' => [5, 10],
        'validValue' => 7,
        'invalidValue' => 15,
    ],
    'between_string' => [
        'rule' => ValidationRule::BETWEEN->value,
        'parameters' => [3, 10],
        'validValue' => 'hello',
        'invalidValue' => 'hi',
    ],
    'digits_between' => [
        'rule' => ValidationRule::DIGITS_BETWEEN->value,
        'parameters' => [3, 5],
        'validValue' => '1234',
        'invalidValue' => '12',
    ],
    'decimal_precision' => [
        'rule' => ValidationRule::DECIMAL->value,
        'parameters' => [2, 4],
        'validValue' => '123.45',
        'invalidValue' => '123.456789',
    ],

    // Multiple parameter rules
    'in_list' => [
        'rule' => ValidationRule::IN->value,
        'parameters' => ['red', 'green', 'blue'],
        'validValue' => 'red',
        'invalidValue' => 'yellow',
    ],
    'not_in_list' => [
        'rule' => ValidationRule::NOT_IN->value,
        'parameters' => ['red', 'green', 'blue'],
        'validValue' => 'yellow',
        'invalidValue' => 'red',
    ],
    'starts_with' => [
        'rule' => ValidationRule::STARTS_WITH->value,
        'parameters' => ['hello', 'hi'],
        'validValue' => 'hello world',
        'invalidValue' => 'goodbye world',
    ],
    'ends_with' => [
        'rule' => ValidationRule::ENDS_WITH->value,
        'parameters' => ['world', 'universe'],
        'validValue' => 'hello world',
        'invalidValue' => 'hello there',
    ],
    'doesnt_start_with' => [
        'rule' => ValidationRule::DOESNT_START_WITH->value,
        'parameters' => ['bad', 'evil'],
        'validValue' => 'good morning',
        'invalidValue' => 'bad morning',
    ],
    'doesnt_end_with' => [
        'rule' => ValidationRule::DOESNT_END_WITH->value,
        'parameters' => ['bad', 'evil'],
        'validValue' => 'something good',
        'invalidValue' => 'something bad',
    ],
    'mimes' => [
        'rule' => ValidationRule::MIMES->value,
        'parameters' => ['jpg', 'png', 'gif'],
        'validValue' => null, // UploadedFile needed
        'invalidValue' => null, // Wrong mime type file needed
    ],
    'mimetypes' => [
        'rule' => ValidationRule::MIMETYPES->value,
        'parameters' => ['image/jpeg', 'image/png'],
        'validValue' => null, // UploadedFile needed
        'invalidValue' => null, // Wrong mime type file needed
    ],

    // Complex conditional rules
    'required_if' => [
        'rule' => ValidationRule::REQUIRED_IF->value,
        'parameters' => ['other_field', 'value'],
        'validValue' => 'required value',
        'invalidValue' => null, // When other_field = value
    ],
    'required_unless' => [
        'rule' => ValidationRule::REQUIRED_UNLESS->value,
        'parameters' => ['other_field', 'value'],
        'validValue' => 'required value',
        'invalidValue' => null, // When other_field != value
    ],
    'required_with' => [
        'rule' => ValidationRule::REQUIRED_WITH->value,
        'parameters' => ['other_field'],
        'validValue' => 'required value',
        'invalidValue' => null, // When other_field is present
    ],
    'required_with_all' => [
        'rule' => ValidationRule::REQUIRED_WITH_ALL->value,
        'parameters' => ['field1', 'field2'],
        'validValue' => 'required value',
        'invalidValue' => null, // When all fields are present
    ],
    'required_without' => [
        'rule' => ValidationRule::REQUIRED_WITHOUT->value,
        'parameters' => ['other_field'],
        'validValue' => 'required value',
        'invalidValue' => null, // When other_field is missing
    ],
    'required_without_all' => [
        'rule' => ValidationRule::REQUIRED_WITHOUT_ALL->value,
        'parameters' => ['field1', 'field2'],
        'validValue' => 'required value',
        'invalidValue' => null, // When all fields are missing
    ],
    'accepted_if' => [
        'rule' => ValidationRule::ACCEPTED_IF->value,
        'parameters' => ['other_field', 'value'],
        'validValue' => true,
        'invalidValue' => false, // When other_field = value
    ],
    'declined_if' => [
        'rule' => ValidationRule::DECLINED_IF->value,
        'parameters' => ['other_field', 'value'],
        'validValue' => false,
        'invalidValue' => true, // When other_field = value
    ],
    'prohibited_if' => [
        'rule' => ValidationRule::PROHIBITED_IF->value,
        'parameters' => ['other_field', 'value'],
        'validValue' => null,
        'invalidValue' => 'prohibited value', // When other_field = value
    ],
    'prohibited_unless' => [
        'rule' => ValidationRule::PROHIBITED_UNLESS->value,
        'parameters' => ['other_field', 'value'],
        'validValue' => null,
        'invalidValue' => 'prohibited value', // When other_field != value
    ],
    'exclude_if' => [
        'rule' => ValidationRule::EXCLUDE_IF->value,
        'parameters' => ['other_field', 'value'],
        'validValue' => 'any value', // Excluded from validation
        'invalidValue' => 'any value', // Excluded from validation
    ],
    'exclude_unless' => [
        'rule' => ValidationRule::EXCLUDE_UNLESS->value,
        'parameters' => ['other_field', 'value'],
        'validValue' => 'any value', // Excluded from validation
        'invalidValue' => 'any value', // Excluded from validation
    ],
    'prohibits' => [
        'rule' => ValidationRule::PROHIBITS->value,
        'parameters' => ['other_field'],
        'validValue' => 'some value',
        'invalidValue' => 'some value', // When other_field is also present
    ],

    // Advanced rules
    'different' => [
        'rule' => ValidationRule::DIFFERENT->value,
        'parameters' => ['other_field'],
        'validValue' => 'different value',
        'invalidValue' => 'same value', // When other_field has same value
    ],
    'same' => [
        'rule' => ValidationRule::SAME->value,
        'parameters' => ['other_field'],
        'validValue' => 'same value',
        'invalidValue' => 'different value', // When other_field has different value
    ],
    'regex_pattern' => [
        'rule' => ValidationRule::REGEX->value,
        'parameters' => ['/^[A-Z][a-z]+$/'],
        'validValue' => 'Hello',
        'invalidValue' => 'hello',
    ],
    'not_regex_pattern' => [
        'rule' => ValidationRule::NOT_REGEX->value,
        'parameters' => ['/^\d+$/'],
        'validValue' => 'abc123',
        'invalidValue' => '123',
    ],
    'exists_in_table' => [
        'rule' => ValidationRule::EXISTS->value,
        'parameters' => ['users.id'],
        'validValue' => 1, // Existing user ID
        'invalidValue' => 999999, // Non-existing user ID
    ],
    'unique_in_table' => [
        'rule' => ValidationRule::UNIQUE->value,
        'parameters' => ['users.email'],
        'validValue' => 'unique@example.com',
        'invalidValue' => 'existing@example.com', // Existing email
    ],
    'in_array_field' => [
        'rule' => ValidationRule::IN_ARRAY->value,
        'parameters' => ['allowed_values'],
        'validValue' => 'allowed_value',
        'invalidValue' => 'not_allowed_value',
    ],
    'dimensions_image' => [
        'rule' => ValidationRule::DIMENSIONS->value,
        'parameters' => ['min_width=100', 'min_height=100'],
        'validValue' => null, // Valid image file needed
        'invalidValue' => null, // Invalid dimensions image needed
    ],
    'exclude' => [
        'rule' => ValidationRule::EXCLUDE->value,
        'parameters' => [],
        'validValue' => 'any value', // Always excluded
        'invalidValue' => 'any value', // Always excluded
    ],
    'enum_values' => [
        'rule' => ValidationRule::ENUM->value,
        'parameters' => ['App\\Enums\\Status'],
        'validValue' => 'active', // Valid enum value
        'invalidValue' => 'invalid_status', // Invalid enum value
    ],
]);

// Field type validation rules compatibility dataset
dataset('field_type_validation_compatibility', fn (): array => [
    'text_field_rules' => [
        'fieldType' => 'text',
        'allowedRules' => ['required', 'min', 'max', 'between', 'regex', 'alpha', 'alpha_num', 'alpha_dash', 'string', 'email', 'starts_with'],
        'disallowedRules' => ['numeric', 'integer', 'boolean', 'array', 'date'],
    ],
    'number_field_rules' => [
        'fieldType' => 'number',
        'allowedRules' => ['required', 'numeric', 'min', 'max', 'between', 'integer', 'starts_with'],
        'disallowedRules' => ['alpha', 'alpha_dash', 'email', 'boolean', 'array'],
    ],
    'currency_field_rules' => [
        'fieldType' => 'currency',
        'allowedRules' => ['required', 'numeric', 'min', 'max', 'between', 'decimal', 'starts_with'],
        'disallowedRules' => ['alpha', 'integer', 'boolean', 'array', 'date'],
    ],
    'date_field_rules' => [
        'fieldType' => 'date',
        'allowedRules' => ['required', 'date', 'after', 'after_or_equal', 'before', 'before_or_equal', 'date_format'],
        'disallowedRules' => ['numeric', 'alpha', 'boolean', 'array', 'email'],
    ],
    'boolean_field_rules' => [
        'fieldType' => 'toggle',
        'allowedRules' => ['required', 'boolean'],
        'disallowedRules' => ['numeric', 'alpha', 'string', 'array', 'date', 'email'],
    ],
    'select_field_rules' => [
        'fieldType' => 'select',
        'allowedRules' => ['required', 'in'],
        'disallowedRules' => ['numeric', 'alpha', 'boolean', 'array', 'date', 'email'],
    ],
    'multi_select_field_rules' => [
        'fieldType' => 'multi-select',
        'allowedRules' => ['required', 'array', 'min', 'max', 'between', 'in'],
        'disallowedRules' => ['numeric', 'alpha', 'boolean', 'string', 'date', 'email'],
    ],
    'checkbox-list_field_rules' => [
        'fieldType' => 'checkbox-list',
        'allowedRules' => ['required', 'array', 'min', 'max', 'between'],
        'disallowedRules' => ['numeric', 'alpha', 'boolean', 'string', 'date', 'email'],
    ],
    'rich-editor_field_rules' => [
        'fieldType' => 'rich-editor',
        'allowedRules' => ['required', 'string', 'min', 'max', 'between', 'starts_with'],
        'disallowedRules' => ['numeric', 'alpha', 'boolean', 'array', 'date', 'integer'],
    ],
    'url_field_rules' => [
        'fieldType' => 'link',
        'allowedRules' => ['required', 'url', 'starts_with'],
        'disallowedRules' => ['numeric', 'alpha', 'boolean', 'array', 'date', 'integer'],
    ],
]);
