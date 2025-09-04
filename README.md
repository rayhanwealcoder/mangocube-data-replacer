# WCF Data Replacer

A professional WordPress admin tool for searching, previewing, and replacing post meta values across posts with advanced features, backups, and live testing.

## 🚀 Features

- **Advanced Search**: Search posts by post type, meta key, and value with case-sensitive and regex options
- **Live Preview**: Preview replacements before executing with diff highlighting
- **Multiple Replace Modes**: Plain text, regex, URL operations, prefix swap, and full text replacement
- **Automatic Backups**: Create backups before any replacement with versioning support
- **Live Tester**: Test replacement patterns in real-time with sample data
- **Bulk Operations**: Process multiple posts simultaneously with progress tracking
- **Modern UI**: Beautiful React-based interface with Tailwind CSS and shadcn/ui components
- **Responsive Design**: Works seamlessly on desktop and mobile devices
- **Accessibility**: Full keyboard navigation and screen reader support
- **Performance**: Optimized for large datasets with pagination and virtual scrolling

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+, WordPress 6.2+, OOP architecture
- **Frontend**: React 18, TypeScript, Tailwind CSS, shadcn/ui
- **Build Tools**: Vite, Composer, Node.js
- **Testing**: PHPUnit, Vitest, React Testing Library
- **Code Quality**: PHPStan, PHP_CodeSniffer, ESLint, Prettier

## 📋 Requirements

- **PHP**: 7.4 or higher
- **WordPress**: 6.2 or higher
- **Node.js**: 16.0 or higher
- **Composer**: Latest version
- **MySQL**: 5.7 or higher

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/your-username/wcf-data-replacer.git
cd wcf-data-replacer
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node.js Dependencies

```bash
npm install
```

### 4. Build the Frontend

```bash
# Development build
npm run dev

# Production build
npm run build
```

### 5. Activate the Plugin

1. Copy the plugin folder to your WordPress `wp-content/plugins/` directory
2. Activate the plugin from the WordPress admin panel
3. Navigate to "Data Replacer" in the admin menu

## 🏗️ Development Setup

### Prerequisites

- Local WordPress development environment (e.g., Local by Flywheel, XAMPP)
- Node.js and npm
- Composer
- Git

### Development Commands

```bash
# Install dependencies
composer install
npm install

# Start development server
npm run dev

# Build for production
npm run build

# Run tests
npm test
composer test

# Code quality checks
npm run lint
composer phpcs
composer phpstan

# Fix code style issues
npm run lint:fix
composer phpcbf
```

### Project Structure

```
wcf-data-replacer/
├── includes/                 # PHP backend classes
│   ├── Core/                # Core functionality
│   ├── Search/              # Search engine
│   ├── Replace/             # Replace engine
│   ├── Backup/              # Backup management
│   ├── Admin/               # Admin interface
│   └── REST/                # REST API endpoints
├── src/                     # React frontend
│   ├── components/          # React components
│   ├── hooks/               # Custom React hooks
│   ├── stores/              # State management
│   ├── services/            # API services
│   ├── types/               # TypeScript types
│   └── styles/              # CSS and styling
├── templates/               # PHP templates
├── dist/                    # Built assets
├── tests/                   # Test files
├── languages/               # Internationalization
├── composer.json            # PHP dependencies
├── package.json             # Node.js dependencies
├── vite.config.ts           # Vite configuration
├── tailwind.config.js       # Tailwind CSS configuration
└── README.md                # This file
```

## 🔧 Configuration

### Plugin Settings

The plugin can be configured through the WordPress admin panel:

- **Max Results Per Page**: Limit results per page (default: 200)
- **Max Bulk Rows**: Maximum rows for bulk operations (default: 5000)
- **Regex Timeout**: Maximum time for regex operations (default: 5000ms)
- **Backup Retention**: Number of backups to keep per meta key (default: 10)

### Environment Variables

```bash
# Development
WP_DEBUG=true
WP_DEBUG_LOG=true

# Production
WP_DEBUG=false
WP_DEBUG_LOG=false
```

## 📚 Usage

### Basic Search

1. Select a post type from the dropdown
2. Enter a meta key (with autosuggest support)
3. Optionally add a value filter
4. Choose search options (case-sensitive, regex)
5. Click "Search" to find matching posts

### Replacement Operations

1. **Preview Mode**: Test replacements without making changes
2. **Live Tester**: Experiment with patterns using sample data
3. **Execute Replacements**: Apply changes with confirmation
4. **Bulk Operations**: Process multiple posts simultaneously

### Backup and Restore

- Automatic backups are created before any replacement
- Restore individual posts or bulk restore
- View backup history and compare versions
- Manual backup creation for important changes

## 🧪 Testing

### PHP Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test file
./vendor/bin/phpunit tests/Search/SearchEngineTest.php
```

### Frontend Tests

```bash
# Run all tests
npm test

# Run with UI
npm run test:ui

# Run with coverage
npm run test:coverage

# Run specific test file
npm test -- src/components/SearchFilters.test.tsx
```

### Code Quality

```bash
# PHP CodeSniffer
composer phpcs

# PHPStan static analysis
composer phpstan

# ESLint
npm run lint

# TypeScript type checking
npm run type-check
```

## 🚀 Deployment

### Production Build

```bash
# Build frontend assets
npm run build

# Install production dependencies only
composer install --no-dev --optimize-autoloader

# Verify plugin activation
wp plugin activate wcf-data-replacer
```

### Deployment Checklist

- [ ] Run production build (`npm run build`)
- [ ] Remove development dependencies (`composer install --no-dev`)
- [ ] Verify all tests pass
- [ ] Check code quality standards
- [ ] Update version numbers
- [ ] Test in staging environment
- [ ] Deploy to production
- [ ] Monitor error logs

## 🔒 Security

- **Capability Checks**: All operations require appropriate WordPress capabilities
- **Nonce Verification**: CSRF protection for all AJAX requests
- **Input Sanitization**: All user inputs are properly sanitized
- **SQL Injection Protection**: Prepared statements for all database queries
- **XSS Prevention**: Output escaping for all displayed data

## 🌐 Internationalization

The plugin supports multiple languages:

- Text domain: `wcf-data-replacer`
- Language files: `languages/` directory
- Translation-ready strings throughout the codebase
- RTL language support

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines

- Follow WordPress coding standards for PHP
- Use TypeScript for all frontend code
- Write tests for new functionality
- Update documentation for API changes
- Ensure accessibility compliance
- Test on multiple devices and browsers

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [Wiki](https://github.com/your-username/wcf-data-replacer/wiki)
- **Issues**: [GitHub Issues](https://github.com/your-username/wcf-data-replacer/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-username/wcf-data-replacer/discussions)
- **Email**: support@yourwebsite.com

## 🙏 Acknowledgments

- WordPress community for the excellent platform
- React team for the amazing frontend framework
- Tailwind CSS for the utility-first CSS framework
- shadcn/ui for the beautiful component library
- All contributors and users of this plugin

## 📈 Roadmap

### Version 1.1
- [ ] Advanced filtering options
- [ ] Export/import functionality
- [ ] Scheduled replacements
- [ ] Email notifications

### Version 1.2
- [ ] Multi-site support
- [ ] API rate limiting
- [ ] Advanced backup options
- [ ] Performance monitoring

### Version 2.0
- [ ] Plugin ecosystem
- [ ] Advanced workflow automation
- [ ] Machine learning suggestions
- [ ] Enterprise features

---

**Made with ❤️ for the WordPress community**
#
