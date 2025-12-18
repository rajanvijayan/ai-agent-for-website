# Contributing to AI Agent for Website

Thank you for your interest in contributing to AI Agent for Website! This document provides guidelines and instructions for contributing.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Making Changes](#making-changes)
- [Pull Request Process](#pull-request-process)
- [Testing](#testing)
- [Reporting Issues](#reporting-issues)

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for everyone. Please be considerate and constructive in your interactions.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/ai-agent-for-website.git
   ```
3. Add the upstream repository:
   ```bash
   git remote add upstream https://github.com/rajanvijayan/ai-agent-for-website.git
   ```

## Development Setup

### Prerequisites

- PHP 8.0 or higher
- WordPress 5.8 or higher
- Composer
- Node.js and npm (for frontend assets and linting)

### Installation

1. Navigate to the plugin directory:
   ```bash
   cd ai-agent-for-website
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install Node.js dependencies:
   ```bash
   npm install
   ```

4. Install WordPress Coding Standards:
   ```bash
   composer require --dev wp-coding-standards/wpcs
   composer require --dev dealerdirect/phpcodesniffer-composer-installer
   ```

## Coding Standards

This project follows:

- **PHP**: [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- **JavaScript**: Prettier for formatting
- **CSS**: Prettier for formatting

### Running Code Checks

```bash
# Check PHP coding standards
composer run phpcs

# Fix PHP coding standards automatically
composer run phpcbf

# Check JavaScript/CSS with Prettier
npm run prettier:check

# Fix JavaScript/CSS with Prettier
npm run prettier:fix
```

## Making Changes

1. Create a new branch from `main`:
   ```bash
   git checkout main
   git pull upstream main
   git checkout -b feature/your-feature-name
   ```

2. Make your changes following the coding standards

3. **Update CHANGELOG.md**: Add your changes under the `[Unreleased]` section:
   - `Added` for new features
   - `Changed` for changes in existing functionality
   - `Deprecated` for soon-to-be removed features
   - `Removed` for now removed features
   - `Fixed` for any bug fixes
   - `Security` for vulnerability fixes

4. Commit your changes with a descriptive message:
   ```bash
   git add .
   git commit -m "feat: add new feature description"
   ```

   Follow conventional commit messages:
   - `feat:` for new features
   - `fix:` for bug fixes
   - `docs:` for documentation changes
   - `style:` for formatting changes
   - `refactor:` for code refactoring
   - `test:` for adding tests
   - `chore:` for maintenance tasks

5. Push to your fork:
   ```bash
   git push origin feature/your-feature-name
   ```

## Pull Request Process

1. Ensure all CI checks pass:
   - PHP unit tests
   - WordPress coding standards
   - Prettier formatting
   - PHP compatibility tests
   - Changelog verification

2. Create a Pull Request against the `main` branch

3. Fill out the PR template with:
   - Description of changes
   - Related issue numbers
   - Testing instructions
   - Screenshots (if applicable)

4. Request review from maintainers

5. Address any feedback and make requested changes

6. Once approved, your PR will be merged

### PR Requirements

- [ ] All tests pass
- [ ] Code follows WordPress coding standards
- [ ] JavaScript/CSS is formatted with Prettier
- [ ] CHANGELOG.md is updated
- [ ] Documentation is updated (if applicable)
- [ ] No merge conflicts with `main`

## Testing

### Running Tests

```bash
# Run PHP unit tests
composer run test

# Run specific test file
./vendor/bin/phpunit tests/AIEngineTest.php

# Run tests with coverage
composer run test:coverage
```

### Writing Tests

- Place test files in the `tests/` directory
- Name test files with `Test.php` suffix
- Name test methods with `test` prefix
- Follow the existing test structure

Example:
```php
class MyFeatureTest extends PHPUnit\Framework\TestCase
{
    public function testFeatureWorks()
    {
        // Arrange
        $input = 'test';
        
        // Act
        $result = myFunction($input);
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

## Reporting Issues

### Bug Reports

When reporting bugs, please include:

1. **Description**: Clear description of the bug
2. **Steps to Reproduce**: Detailed steps to reproduce the issue
3. **Expected Behavior**: What you expected to happen
4. **Actual Behavior**: What actually happened
5. **Environment**:
   - WordPress version
   - PHP version
   - Plugin version
   - Browser (if applicable)
6. **Screenshots/Logs**: Any relevant screenshots or error logs

### Feature Requests

When requesting features, please include:

1. **Description**: Clear description of the feature
2. **Use Case**: Why this feature would be useful
3. **Proposed Solution**: How you envision this working
4. **Alternatives**: Any alternative solutions you've considered

## Questions?

If you have questions, feel free to:

- Open an issue with the `question` label
- Reach out to the maintainers

Thank you for contributing! 🎉

