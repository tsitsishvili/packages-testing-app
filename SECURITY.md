# Security Policy

## Supported Versions

Security updates are provided for the latest release on the `main` branch.
Older revisions are not maintained.

| Version | Supported          |
|---------|--------------------|
| `main`  | :white_check_mark: |
| older   | :x:                |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues,
pull requests, or discussions.**

Instead, report them privately using one of the following channels:

- Email **torniketsitsishvili@gmail.com** with the details, or
- Open a private advisory via GitHub's
  [Security Advisories](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing-information-about-vulnerabilities/privately-reporting-a-security-vulnerability)
  ("Report a vulnerability" on the repository's **Security** tab).

To help us triage quickly, please include:

- A description of the vulnerability and its impact.
- Steps to reproduce (proof-of-concept, affected endpoint/command, or request).
- The affected component (application code, `tsitsishvili/documentator`,
  or `tsitsishvili/elastic-audit`).
- Any suggested remediation, if known.

### What to expect

- **Acknowledgement** within 3 business days.
- A **triage assessment** and severity rating within 10 business days.
- Regular updates on remediation progress.
- Public disclosure and credit (if desired) coordinated with you **after** a
  fix is available.

Please give us a reasonable amount of time to resolve the issue before any
public disclosure.

## Scope

This policy covers this application and the two first-party packages it ships:

- `tsitsishvili/documentator`
- `tsitsishvili/elastic-audit`

Vulnerabilities in the Laravel framework or other third-party dependencies
should be reported to their respective maintainers. For Laravel itself, see the
[Laravel security policy](https://github.com/laravel/laravel/security/policy).

## Security Considerations for Operators

A few deployment notes specific to this project:

- **API documentation access** (`/docs`) is gated by `Documentator::auth()`,
  which is currently wired open (`fn () => true`) in `AppServiceProvider::boot()`.
  Restrict this before exposing the app publicly.
- **Audit log dashboards** (`/logger/*`) provided by `tsitsishvili/elastic-audit`
  can surface request/response data; ensure access is restricted and that
  redaction rules in `config/http_logs.php` are configured for your data.
- Keep `APP_DEBUG=false` and a strong, unique `APP_KEY` in production.
