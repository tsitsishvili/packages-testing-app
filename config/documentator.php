<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API metadata
    |--------------------------------------------------------------------------
    |
    | These values populate the `info` block of the generated OpenAPI document
    | and the title of the interactive docs page.
    |
    */

    'title' => env('DOCUMENTATOR_TITLE', config('app.name').' API'),
    'version' => env('DOCUMENTATOR_VERSION', '1.2.0'),
    'description' => env('DOCUMENTATOR_DESCRIPTION', 'A description of your API.'),

    /*
    |--------------------------------------------------------------------------
    | Access
    |--------------------------------------------------------------------------
    |
    | Whether the docs routes are reachable. Disabled by default (a security
    | hardening in documentator 1.6.3): set DOCUMENTATOR_ENABLED=true to expose
    | the UI/OpenAPI routes. Combine with route middleware below to put the docs
    | behind auth. To restrict *who* may view them, register a gate with
    | Documentator::auth() from a service provider:
    |
    |     Documentator::auth(fn ($request) => $request->user()?->is_admin);
    |
    */

    'enabled' => env('DOCUMENTATOR_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    |
    | The base URLs third parties can send "try it out" requests to. The first
    | entry is selected by default in the UI.
    |
    */

    'servers' => [
        ['url' => env('APP_URL', 'http://localhost'), 'description' => 'Default'],
        ['url' => env('APP_URL', 'https://tester-app.com'), 'description' => 'Production'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Docs UI route
    |--------------------------------------------------------------------------
    |
    | Where the interactive Scalar UI and the raw OpenAPI document are served.
    | Lock these down with middleware in non-public APIs.
    |
    */

    'route' => [
        'prefix' => env('DOCUMENTATOR_PREFIX', 'docs'),
        'domain' => env('DOCUMENTATOR_DOMAIN', null),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI
    |--------------------------------------------------------------------------
    |
    | "documentator" renders the built-in Aurora explorer, served from
    | this package with no external assets. "scalar" embeds the Scalar bundle
    | instead (`assets` is the pinned, overridable Scalar URL — self-host it to
    | apply Subresource Integrity / a Content-Security-Policy).
    |
    */

    'ui' => [
        'driver' => env('DOCUMENTATOR_UI', 'documentator'),
        'assets' => env('DOCUMENTATOR_UI_ASSETS', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference@1.25.0/dist/browser/standalone.min.js'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Which routes to document
    |--------------------------------------------------------------------------
    |
    | `match` includes routes whose URI matches any of these patterns (Str::is
    | wildcards). `exclude` removes routes whose URI or name matches. Routes
    | marked #[Hidden] are always excluded.
    |
    */

    'routes' => [
        'match' => ['api/*'],
        'exclude' => [
            'telescope*',
            'horizon*',
            '_debugbar*',
            'sanctum/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Grouping & sections
    |--------------------------------------------------------------------------
    |
    | `source` controls how endpoints without an explicit #[Group] are tagged
    | ("auto" = by controller / URI). `sections` split the built-in UI by route
    | surface, each served at /docs/{slug} and /docs/{slug}/openapi.json. Keys
    | are URI patterns (Str::is wildcards) matched top-to-bottom, first match
    | wins — so the more specific `api/v2/*` MUST precede the `api/*` catch-all.
    |
    | Because this file overrides the package's whole `grouping` key (config is
    | merged shallowly), the non-section keys below mirror the package defaults.
    |
    */

    'grouping' => [
        'source' => env('DOCUMENTATOR_GROUPING', 'auto'), // auto, controller, path
        'path_depth' => 1,
        'ignore_path_prefixes' => ['api'],
        'ignore_path_parameters' => true,
        'sections' => [
            'api/v2/*' => 'API v2', // the v2 product surface -> /docs/api-v2
            'api/*' => 'API',       // everything else (v1 products, auth, orders, …) -> /docs/api
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model resolution
    |--------------------------------------------------------------------------
    |
    | When inferring response types, an API Resource's wrapped model is resolved
    | by convention (e.g. UserResource -> {namespace}\User) so its $casts can
    | type the fields. Override per resource with #[UsesModel(Model::class)].
    |
    */

    'models_namespace' => 'App\\Models',

    /*
    |--------------------------------------------------------------------------
    | Authentication schemes
    |--------------------------------------------------------------------------
    |
    | Declared as OpenAPI `securitySchemes`. The key is referenced by the
    | #[Authenticated] attribute (defaults to "default"). The UI uses these to
    | render the authorize / token input for "try it out".
    |
    */

    'security' => [
        'default' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'description' => 'Pass an API token as a Bearer header.',
        ],
        'admin' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'description' => 'An admin-scoped Bearer token. Referenced by #[Authenticated(\'admin\')] on privileged endpoints such as deleting an order.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global authentication
    |--------------------------------------------------------------------------
    |
    | Require a security scheme across the whole API instead of marking each
    | endpoint. Emitted as the document's top-level `security`, which applies to
    | every operation. Set true to require the "default" scheme, or name another
    | scheme from `security` above. Endpoints that aren't authenticated (no
    | `auth` middleware / #[Authenticated]) opt out automatically and stay
    | public. Leave false to declare auth per-endpoint.
    |
    */

    'authenticate' => env('DOCUMENTATOR_AUTHENTICATE', false),

    /*
    |--------------------------------------------------------------------------
    | Spec caching
    |--------------------------------------------------------------------------
    |
    | When enabled the generated OpenAPI document is read from a cached file
    | (written by `php artisan documentator:generate`) instead of being built
    | on every request. Recommended in production.
    |
    */

    'cache' => [
        'enabled' => env('DOCUMENTATOR_CACHE', true),
        'path' => storage_path('app/documentator/openapi.json'),
    ],

];
