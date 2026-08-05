# API and Documentator

This application is a code-first integration fixture for
`tsitsishvili/documentator`. Documentator scans the real Laravel application and
generates OpenAPI 3.2; a separately maintained endpoint specification is not the
source of truth.

## Documentation surfaces

Set the following values for live local generation:

```dotenv
DOCUMENTATOR_ENABLED=true
DOCUMENTATOR_CACHE=false
```

The registered surfaces are:

| Surface | URL |
| --- | --- |
| Section landing page | `http://localhost:8000/docs` |
| API v2 section | `http://localhost:8000/docs/api-v2` |
| Unversioned API section | `http://localhost:8000/docs/api` |
| Complete OpenAPI document | `http://localhost:8000/docs/openapi.json` |
| API v2 OpenAPI document | `http://localhost:8000/docs/api-v2/openapi.json` |
| Unversioned API OpenAPI document | `http://localhost:8000/docs/api/openapi.json` |

The bare `/docs` route redirects to the first configured section, API v2.

Documentator routes are disabled by default. When enabled, this application
currently registers `Documentator::auth(fn ($request) => true)` in
`AppServiceProvider`, so the documentation is open to every caller that can
reach it. Replace that callback or add suitable route middleware before enabling
the UI in an untrusted environment.

## API surfaces exercised by the fixture

- Authentication and personal access tokens through Laravel Sanctum.
- Public product reads and authenticated product writes under `/api`.
- A distinct product representation under `/api/v2`.
- Authenticated order creation, filtering, lifecycle operations, file upload,
  and shipment responses.
- Newsletter-payload validation and a simulated payment-provider webhook.
- A reconciliation fixture that is intentionally hidden from generated
  documentation with `#[Hidden]`. Hiding documentation does not add route
  authorization.

Use the generated OpenAPI document for the complete operation and schema list.

## Where contract facts come from

| Contract fact | Application source |
| --- | --- |
| Method and path | `routes/api.php` and `routes/api_v2.php` |
| Summary and description | Controller/action method metadata |
| JSON and multipart inputs | Form Requests and inline validation |
| Structured inputs and outputs | Spatie Data objects |
| Filter/sort parameters | Spatie Query Builder allow-lists |
| Success schemas | API Resources, Data objects, models, and return expressions |
| Authentication | `auth:sanctum` middleware |
| Grouping and versions | Documentator configuration and group attributes |
| Intentional overrides | `Tsitsishvili\Documentator\Attributes` |

Attributes should describe only facts that the executable code cannot expose.
Because attributes override inference, a redundant parameter or response
attribute can discard validation constraints or an inferred response schema.

## HTTP `QUERY`

`QUERY /api/orders` demonstrates the HTTP `QUERY` method and OpenAPI 3.2
`query` Path Item operation. Its `SearchOrdersData` criteria are request content,
not URI query parameters. The equivalent `GET /api/orders` operation uses the
same typed criteria as ordinary URI query parameters.

Keep `QUERY` safe and idempotent. It is used here for structured searches, not
for mutations.

## Verify and export the contract

Trace one operation before running the broader audit:

```bash
php artisan documentator:explain POST '/api/orders'
php artisan documentator:explain QUERY '/api/orders'
```

Audit action introspectability, success schemas, health warnings, and OpenAPI
validity:

```bash
php artisan documentator:check
php artisan documentator:check --json
```

The current package reports quality notes for the legitimate empty `204`
responses on logout and delete operations because those responses have no body
schema. OpenAPI validation still passes; do not invent a response body merely to
silence those notes.

Generate or export artifacts with:

```bash
php artisan documentator:generate
php artisan documentator:export openapi.json
php artisan documentator:postman postman-collection.json
php artisan documentator:typescript documentator-client.ts
```

`documentator:generate` writes the cached document to
`storage/app/documentator/openapi.json` unless `--path` is provided. Production
deployments with `DOCUMENTATOR_CACHE=true` should generate the cache during the
release process.

## Maintenance workflow

When an endpoint changes:

1. Inspect the route, middleware, action, input type, response type, and existing
   feature test.
2. Put request and response facts in executable Laravel code wherever possible.
3. Use consumer-facing method prose for the summary and description.
4. Run `documentator:explain` for the operation.
5. Add `assertMatchesDocumentation()` to the endpoint's successful feature-test
   response when the generated schema supports that response shape.
6. Run the focused feature test and `php artisan documentator:check`.

This application tracks a development branch of Documentator and intentionally
exercises edge cases. Treat generated/runtime contract mismatches as package or
fixture defects to fix, not as facts to hide in prose.
