# Backend Architecture (P-weibo-backend)

## Request Flow

1. `public/index.php` loads `config/config.php` (`.env`), sets up autoload, applies CORS early, then initializes core services.
2. `App\Core\Router` matches the route and runs middleware pipeline.
3. Controllers (`app/Controllers/*`) validate input and call Services.
4. Services (`app/Services/*`) encapsulate business logic and talk to Models.
5. Models (`app/Models/*`) use `App\Core\QueryBuilder` which executes SQL via `App\Core\Database`.
6. Errors bubble to `App\Core\ExceptionHandler` which returns a standardized JSON response.

## Key Modules

- `app/Core`
  - `Request` / `Response`: HTTP parsing + JSON output + cookies
  - `Router`: minimal route matching + middleware pipeline
  - `CorsMiddleware`: CORS headers + OPTIONS preflight
  - `Database` / `QueryBuilder`: PDO + simple query builder
  - `Auth`: JWT access token verification + user attachment
  - `ExceptionHandler` / `ApiResponse`: consistent JSON responses

- `app/Middleware`
  - `CorsMiddleware`: CORS + preflight
  - `AuthMiddleware`: attaches `Request::$user` from bearer token
  - `AdminMiddleware`: admin-only endpoints
  - `OptionalAuthMiddleware`: best-effort auth (guest allowed)
  - `RateLimitMiddleware`: request limiting (for selected routes)

- `public/`
  - `index.php`: the only file that should be internet-facing in production
  - Many `debug_*`, `test-*`, `diagnostic.php`, `install.php` scripts exist for maintenance; treat them as **internal tools**.

## Operational Notes

- **CORS** is driven by `.env` `FRONTEND_ORIGIN` (comma-separated) plus always-allowed production frontends in `config/config.php`.
- **DB failure behavior**: if DB cannot connect, API returns `503` JSON (not HTML/PHP fatal).

## Recommended Next Steps (High Value)

1. Lock down `public/` internal tools in production (nginx denylist or token-gated).
2. Move internal tools to `scripts/` (CLI-only) to reduce accidental exposure.
3. Gradually replace ad-hoc `RuntimeException(code)` with typed `AppException` subclasses for clearer error handling.

