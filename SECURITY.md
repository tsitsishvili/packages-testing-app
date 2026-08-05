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

This policy covers this application's code, configuration, and integrations
with the following first-party packages:

- [`tsitsishvili/documentator`](https://github.com/tsitsishvili/documentator)
- [`tsitsishvili/elastic-audit`](https://github.com/tsitsishvili/elastic-audit)

If a vulnerability is isolated to one of those packages rather than this
application's integration, report it privately to that package's maintainer or
GitHub security-advisory channel. Vulnerabilities in Laravel or other third-party
dependencies should also be reported to their respective maintainers. For
Laravel itself, see the [Laravel security
policy](https://github.com/laravel/laravel/security/policy).

## Security Considerations for Operators

A few deployment notes specific to this project:

- **API documentation access** (`/docs`) is disabled by default. If
  `DOCUMENTATOR_ENABLED=true`, access is gated by `Documentator::auth()`, whose
  callback is currently wired open (`fn () => true`) in
  `AppServiceProvider::boot()`. Replace it before exposing the app publicly.
- **Audit log dashboards** (`/logger/*`) provided by `tsitsishvili/elastic-audit`
  can surface request/response data. Their default authorization is intended for
  local use; add application authorization before exposing them and review the
  redaction rules in `config/http_logs.php` for your data.
- **Audit retention** in `.env.example` is permanent for both audit subsystems,
  with the Elasticsearch ILM delete phase disabled. Adopt a finite retention
  policy unless permanent storage is an explicit requirement.
- Keep `APP_DEBUG=false` and a strong, unique `APP_KEY` in production.
