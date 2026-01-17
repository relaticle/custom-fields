# Contributing

> How to contribute to Custom Fields

We love contributions from the community! Custom Fields is now open source, and we welcome pull requests, bug reports, and feature suggestions.

## How to Contribute

### 1. Getting Started

- **Fork the repository** on [GitHub](https://github.com/relaticle/custom-fields)
- **Clone your fork** locally
- **Set up your development environment** following the instructions below

### 2. Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/custom-fields.git
cd custom-fields

# Install dependencies
composer install
npm install

# Set up testing environment
cp phpunit.xml.dist phpunit.xml

# Run tests to verify setup
composer test
```

### 3. Making Changes

<steps>

### Create a Branch

```bash
git checkout -b feature/your-feature-name
```

Use descriptive branch names like `feature/add-phone-field` or `fix/validation-error`

### Write Your Code

- Follow PSR-12 coding standards
- Add tests for new features
- Update documentation as needed
- Keep commits focused and atomic

### Test Your Changes

```bash
# Run all tests
composer test

# Run specific tests
composer test:pest
composer test:types
```

### Submit Pull Request

- Push your branch to your fork
- Create a pull request with a clear description
- Link any related issues

</steps>

## Contribution Guidelines

### Code Quality

- **Follow PSR-12** coding standards
- **Write tests** for all new features (aim for 80%+ coverage)
- **Use type declarations** where possible
- **Document complex logic** with clear comments
- **Keep methods small** and focused on a single responsibility

### Testing

We use Pest PHP for testing. Please ensure:

- All tests pass before submitting PR
- New features include comprehensive tests
- Bug fixes include regression tests
- Architecture tests remain satisfied

```bash
# Run full test suite
composer test

# Run with coverage
composer test-coverage

# Run only architecture tests
composer test:arch
```

### Documentation

- Update documentation for new features
- Include docblocks for public methods
- Add examples for complex features
- Keep README up to date

### Commit Messages

Follow conventional commit format:

```text
type(scope): brief description

Longer explanation if needed

Closes #123
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

## Types of Contributions

### Bug Reports

Found a bug? Please open an issue with:

- Clear description of the problem
- Steps to reproduce
- Expected vs actual behavior
- Your environment details (PHP, Laravel, Filament versions)
- Error messages or screenshots

### Feature Requests

Have an idea? We'd love to hear it! Open an issue describing:

- The problem you're trying to solve
- Your proposed solution
- Any alternatives you've considered
- Mockups or examples (if applicable)

### Pull Requests

We actively welcome pull requests for:

- Bug fixes
- New features
- Documentation improvements
- Additional tests
- UI/UX improvements
- Code refactoring

### Field Type Contributions

Want to add a new field type? Great! Make sure to:

1. Implement the `FieldTypeDefinitionInterface`
2. Add form, table, and infolist components
3. Include validation rules
4. Add comprehensive tests
5. Update documentation

Example structure:

```php
namespace Relaticle\CustomFields\FieldTypes;

class PhoneFieldType implements FieldTypeDefinitionInterface
{
    public function getFormComponent(CustomField $field): Field
    {
        // Implementation
    }

    public function getTableColumn(CustomField $field): Column
    {
        // Implementation
    }

    // ... other required methods
}
```

## Development Workflow

### 1. Local Development

```bash
# Watch frontend assets
npm run dev

# Format code
composer lint

# Run static analysis
composer test:types
```

### 2. Before Submitting

- [ ] All tests pass
- [ ] Code follows standards
- [ ] Documentation updated
- [ ] Changelog entry added (for features)
- [ ] No debug code left

### 3. Review Process

- Maintainers will review your PR
- Address any feedback
- Once approved, it will be merged

## Community Guidelines

### Be Respectful

- Treat everyone with respect
- Constructive criticism only
- No harassment or discrimination
- Help newcomers get started

### Be Collaborative

- Discuss major changes first
- Help review other PRs
- Share knowledge and learn
- Credit others' contributions

## Licensing

By contributing, you agree that your contributions will be licensed under the same dual license as Custom Fields (AGPL-3.0 + Commercial).

## Recognition

Contributors are recognized in:

- Our README contributors section
- Release notes for significant contributions
- Special thanks in documentation

## Need Help?

- **Discord**: Join our community server
- **Email**: [customfieldsnext@gmail.com](mailto:customfieldsnext@gmail.com)
- **Issues**: [GitHub Issues](https://github.com/relaticle/custom-fields/issues)

Thank you for helping make Custom Fields better for everyone!
