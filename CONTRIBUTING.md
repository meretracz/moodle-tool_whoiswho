# Contributing

Thank you for your interest in improving this Moodle admin tool. We welcome community contributions via GitHub Pull Requests.

## Before You Start

- Open an issue for larger changes to discuss scope and approach.
- Target branch: `main`.
- Environment: Moodle 4.5+, PHP 8.1.

## Development Workflow

1. Fork the repo and create a feature branch (e.g., `feat/...`, `fix/...`).
2. Implement changes with small, focused commits and clear messages.
3. Follow Moodle coding standards and keep PHP 8.1 compatibility.
   - Lint/style: `phpcs --standard=moodle .`
   - Tests: add/update PHPUnit tests under `tests/` for logic changes.
4. Do not bump `version.php` or modify release workflows; maintainers handle releases.
5. Update docs and language strings when behavior or UI changes.

## Submitting a Pull Request

- Ensure local checks pass (style, tests) before opening the PR.
- Provide a clear summary, context/links to issues, and screenshots for UI changes.
- Keep PRs small and single-purpose; split large changes into smaller PRs.

## Security

Please report security issues via the GitHub issue tracker. Avoid sharing sensitive details publicly—share minimal reproduction steps and impact.

## Commercial / Priority Support

If you need commercial help or priority support, contact: sales@ltnc.nl

