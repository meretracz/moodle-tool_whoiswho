## Moodle admin tool for viewing users, roles, and capabilities

Permission Dashboard (Who is who) provides an admin-focused dashboard to help diagnose role/capability assignments and prepare for conflict checks. The initial release scaffolds the UI and settings as a foundation for further features.

* Development: [Ldesignmedia.nl](https://ldesignmedia.nl/) / [wafaahamdy](https://github.com/wafaahamdy)
* Min. required: Moodle 4.5
* Supports PHP: 8.1
* Status: Alpha (work in progress)

![Moodle45](https://img.shields.io/badge/moodle-4.5-F98012.svg?logo=moodle)

![php8.1](https://img.shields.io/badge/php-8.1-777BB4.svg?logo=php)

![GDPR](https://img.shields.io/badge/GDPR-null_provider-brightgreen.svg)

## Features
- Admin category and dashboard page under Site administration > Tools > Who is who.
- Capability-gated access (`tool/whoiswho:dashboardaccess`).
- Mustache-based template for the dashboard UI.
- Plugin settings page to select profile fields used by the tool.
- Language pack structure ready for translations.

## Installation
1.  Copy this plugin to the `admin/tool/whoiswho` folder on the server
2.  Login as administrator
3.  Go to Site Administrator > Notification
4.  Install the plugin

### Usage
- Navigate to: Site administration > Tools > Who is who > Dashboard (`/admin/tool/whoiswho/view/dashboard.php`).
- Configure: Site administration > Tools > Who is who > Settings (select profile fields).

### Development & Testing
- Moodle coding style: `phpcs --standard=moodle moodle-tool_whoiswho`
- PHPUnit (from Moodle root): `vendor/bin/phpunit --testsuite tool_whoiswho`

## Security

If you discover any security related issues, please use the GitHub issue tracker.

## License

The GNU GENERAL PUBLIC LICENSE. Please see [License File](LICENSE.md) for more information.

## Contributing

We welcome community contributions via GitHub Pull Requests.

- Propose first: For larger changes, open an issue to discuss scope and approach before you start.
- Fork and branch: Fork the repo, create a feature branch from `main` (e.g., `feat/...`, `fix/...`).
- Coding standards: Follow Moodle’s coding guidelines and PHP 8.1 compatibility.
  - Lint/style: `phpcs --standard=moodle .`
  - Tests: add/update PHPUnit tests in `tests/` when changing logic.
  - Do not bump `version.php`; maintainers handle release versioning.
- PR quality: Keep PRs focused and small, include a clear description, link related issues, and add screenshots for UI changes.
- Docs and strings: Update `README.md`, `docs/`, and language strings when behavior or UI changes.
- CI: Pull requests run CI. Please ensure local checks pass before submitting.
- License: By contributing, you agree your code is GPL v3 compatible, consistent with this project’s license.
- Community: Be respectful and constructive in discussions and reviews.

Need commercial help or priority support? Contact [sales@ltnc.nl](mailto:sales@ltnc.nl).

## Changelog

#### 4.5.0
- Initial alpha release (dashboard scaffold, settings, capability)
