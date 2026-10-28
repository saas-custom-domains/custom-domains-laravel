# Custom domains in Laravel

[![CI](https://github.com/saas-custom-domains/custom-domains-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/saas-custom-domains/custom-domains-laravel/actions/workflows/ci.yml)

An example Laravel 13 app where each customer connects their own domain. It's a small job board product, Hireboard: every company gets a job board, and can serve it on `jobs.theircompany.com` instead of a Hireboard URL. Candidates apply on that domain.

It goes with the guide [How to Add Custom Domains to a Laravel App](https://saascustomdomains.com/blog/posts/custom-domains-laravel).

What's in it:

- `ResolveBoard` middleware finds the company from the request's hostname. Unknown and unverified domains get a 404, and the dashboard and the boards never show up on each other's hosts.
- Requests from the [SaaS Custom Domains](https://saascustomdomains.com) proxy are accepted only with the upstream's auth token, so nobody can claim a customer's domain by sending `X-Served-For` themselves.
- `UseServedForHost` hands Laravel the customer's domain, so `route()`, `url()`, redirects and the CSRF-protected apply form all work on it (see [Laravel and custom domains: what breaks](#laravel-and-custom-domains-what-breaks)).
- A dashboard to add a domain, check its DNS and remove it. Webhooks update the status.
- Two ways to run HTTPS: SaaS Custom Domains (default), or FrankenPHP's built-in Caddy with on-demand TLS. FrankenPHP already runs the app, so there's no separate proxy to deploy.

## Run it in 2 minutes

You need Docker.

```bash
git clone https://github.com/saas-custom-domains/custom-domains-laravel
cd custom-domains-laravel
docker compose up --build
```

1. Open http://localhost:3000 and add `acme.localhost` to Acme Robotics.
2. Within a few seconds the status changes to **Live**: the mock API "found" the DNS records and sent a webhook.
3. Open http://acme.localhost. The request goes through the mock proxy on port 80, exactly like a real customer domain goes through SaaS Custom Domains, and you see Acme's job board. Open a job and apply.

`*.localhost` always resolves to your own machine in Chrome, Firefox, Safari and curl, so there's nothing to add to `/etc/hosts`.

The mock is [scd-mock](https://github.com/saas-custom-domains/scd-mock): a local copy of the SaaS Custom Domains API and proxy, so you can try everything without an account.

### Without Docker

With PHP 8.3 or newer and Composer:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Set `DOMAINS_PROVIDER=caddy` in `.env` so adding a domain doesn't call the API; `*.localhost` domains count as pointed at you. Add `acme.localhost` at http://localhost:8000, click Check DNS, and open http://acme.localhost:8000.

## How it works

```
Browser ── jobs.acme.com ──▶ SaaS Custom Domains ──▶ your Laravel app
                             (TLS certificate,       Host: app.hireboard.app
                              proxy)                 X-Served-For: jobs.acme.com
                                                     X-SaaS-Domains-Auth-Token: …
```

| File | What it does |
| --- | --- |
| `app/Http/Middleware/UseServedForHost.php` | Checks the proxy token, makes the customer's domain the request's host |
| `app/Http/Middleware/ResolveBoard.php` | Finds the board for the host, or 404 |
| `app/Http/Middleware/AppHostOnly.php` | Keeps the dashboard on `APP_HOSTS` |
| `routes/web.php` | Dashboard routes and board routes, each behind its middleware |
| `app/Services/DomainService.php` | Add, check and remove domains with either provider |
| `app/Services/SaasCustomDomains.php` | API client for SaaS Custom Domains (Laravel's `Http` client) |
| `app/Http/Controllers/SaasCustomDomainsWebhookController.php` | Webhook endpoint |
| `app/Http/Controllers/CaddyAskController.php` | Caddy's on-demand TLS check |
| `Caddyfile` | FrankenPHP config: plain HTTP on :3000, on-demand HTTPS on :443 |

Domains live in `custom_domains` with a unique index on the normalized host (lowercase, no port, no trailing dot). `APP_DOMAIN` and `APP_HOSTS` are reserved, so nobody can connect `admin.hireboard.app` to their board.

## Laravel and custom domains: what breaks

SaaS Custom Domains sends your app's own hostname in `Host` and `X-Forwarded-Host`, so your hosting platform and load balancer route the request like any other. The customer's domain comes separately, in `X-Served-For`. Laravel builds URLs and checks hosts from the request's host, so if your app needs the customer's domain for links, redirects, forms or routing, it has to be handed over. These are the places it shows up, each checked against Laravel 13.39.

### Links, redirects and forms point at your own hostname

`route()` and `url()` build absolute URLs from the current request's host. If you look up the tenant from `X-Served-For` but leave the host alone, every link and form action on `jobs.acme.com` points at your app's hostname instead:

```html
<form method="POST" action="https://app.hireboard.app/jobs/1/apply">
```

```
HTTP/1.1 302 Found
Location: https://app.hireboard.app/jobs/1
```

The candidate leaves the customer's domain. A form posted that way fails with **419 Page Expired** (`CSRF token mismatch.` with `APP_DEBUG=true`), because the session cookie belongs to `jobs.acme.com` and the browser doesn't send it to your hostname.

`UseServedForHost` fixes it in one place. Once the token checks out, it sets the request's `Host` to the customer's domain:

```php
if ($request->hasHeader('X-Served-For')) {
    $host = static::verifiedHost($request);
    $request->headers->set('Host', $host);
    $request->headers->remove('X-Forwarded-Host');
    $request->headers->remove('X-Forwarded-Port');
}
```

After that, `$request->getHost()`, `route()`, `url()`, `redirect()` and packages that read the host all see `jobs.acme.com`. It's prepended to the `web` middleware group in `bootstrap/app.php`, so it runs before the session starts. `URL::forceRootUrl()` also fixes the links, but only the links: `$request->getHost()` still returns your hostname, and the forced root is state on a shared URL generator, which you'd have to reset per request under Octane.

### `Route::domain()` doesn't see the customer's domain

`Route::domain('{account}.hireboard.app')` is the usual answer for subdomains, and it does work when the browser's host reaches PHP directly. Behind the proxy, routing happens before any route middleware, against `Host: app.hireboard.app`, so a route for `jobs.acme.com` returns 404. Customer domains also live in your database, not in a route file you can cache. This app uses two middleware groups instead: `app-host` for the dashboard, `board` for the job boards.

### `trustHosts()`: `Untrusted Host "jobs.acme.com".`

`$middleware->trustHosts()` makes Symfony throw `SuspiciousOperationException: Untrusted Host "jobs.acme.com".` for any host that isn't in the list, and Laravel answers **400 Bad request.** It's skipped when `APP_ENV=local`, so it bites in production first. Customer domains are dynamic, so a fixed list can't work, and health checks that call the app by IP address fail too. Leave it off: `ResolveBoard` already returns 404 for every host that isn't an active domain, which is the same check backed by the database.

### `TrustProxies`: `http://` links on an HTTPS domain

Laravel trusts no proxy by default (it trusts everyone automatically only on Laravel Cloud and on `*.on-forge.com` and `*.on-vapor.com` hosts). Behind a proxy that terminates TLS, `$request->isSecure()` is false and links come out as `http://jobs.acme.com/...`. Set `TRUSTED_PROXIES=*` (read from `config/trustedproxy.php`) when only your load balancer or SaaS Custom Domains can reach the app. `bootstrap/app.php` trusts only `X-Forwarded-For` and `X-Forwarded-Proto`. `X-Forwarded-Host` holds your own hostname here, and a trusted one wins over `Host`, which is why `UseServedForHost` also removes it.

`$request->ip()` is the proxy's address unless the proxy is trusted. The visitor's IP is also in `X-SaaS-Domains-IP`.

### `SESSION_DOMAIN` must stay null

With `SESSION_DOMAIN=.hireboard.app`, Laravel sends `Set-Cookie: laravel-session=…; domain=.hireboard.app` on `jobs.acme.com`. The browser drops it, so there's no session, no flash message and no CSRF token, and the apply form fails with **419 Page Expired**. Null means "the host that served the page", which is what custom domains need.

### CSRF: nothing to change

Laravel 13's `PreventRequestForgery` middleware first checks the browser's `Sec-Fetch-Site` header, which the browser computes from the page's own URL, so a proxy can't make it wrong. If that header is missing it falls back to the session token. There's no comparison of `Origin` against the host, so the "origin doesn't match" errors other frameworks show behind a proxy don't happen here, as long as the session cookie works (see above).

### Health checks and webhooks

Laravel registers `/up` outside the `web` group, and this app puts the webhook and Caddy's ask endpoint in `routes/hooks.php` under the `api` group (no session, no CSRF). None of them go through `ResolveBoard`, so a load balancer calling by IP, or the mock posting webhooks as `Host: app:3000`, gets through. If you moved tenant resolution into global middleware, `/up` by IP would return 404.

## Using stancl/tenancy or Spatie's laravel-multitenancy

This app doesn't use either package, but both find the tenant from `$request->getHost()`, so they need the customer's domain the same way.

**stancl/tenancy** (v3) reads the host in `InitializeTenancyByDomain`, a route middleware. Keep `UseServedForHost` prepended to the `web` group and it runs first:

```php
$middleware->web(prepend: [UseServedForHost::class]);
```

**Spatie laravel-multitenancy** (v4) finds the tenant while the app boots, before any middleware runs, so give it a tenant finder that reads `X-Served-For` itself, and set it as `tenant_finder` in `config/multitenancy.php`:

```php
class ProxiedDomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        $host = $request->hasHeader('X-Served-For')
            ? UseServedForHost::verifiedHost($request)
            : $request->getHost();

        return app(IsTenant::class)::whereDomain($host)->first();
    }
}
```

Keep `UseServedForHost` in the `web` group as well, for links and redirects.

## Run your own proxy instead (FrankenPHP)

FrankenPHP is Caddy with PHP built in, and the app already runs on it, so the same container can terminate TLS for customer domains:

```bash
DOMAINS_PROVIDER=caddy docker compose up --build
```

Add `acme.localhost` in the dashboard and click Check DNS, then:

```bash
curl -k https://acme.localhost/
```

The `Caddyfile` asks `/caddy/ask` before issuing a certificate, and only active domains get one; any other name fails the TLS handshake. Locally it uses Caddy's internal CA, hence `-k`. In production you'd remove `internal` from the `Caddyfile`, run the container on a server with a static IP and ports 80 and 443, and then own certificate storage (the `/data` volume, shared if you run more than one instance), renewals, rate limits and monitoring yourself.

## Use it with your SaaS Custom Domains account

1. [Create an API token](https://app.saascustomdomains.com/api_tokens) and an upstream pointing at your deployed app.
2. Set the environment variables from `.env.example`: `SCD_API_TOKEN`, `SCD_ACCOUNT_UUID`, `SCD_UPSTREAM_UUID`, `PROXY_AUTH_TOKEN` (the upstream's auth token), `SCD_WEBHOOK_TOKEN` (from the webhook you create in the dashboard), `APP_HOSTS`, and `TRUSTED_PROXIES` if your platform doesn't already trust its load balancer.
3. Remove `SCD_API_URL` so the client talks to the real API.

`APP_HOSTS` must list every hostname your own traffic arrives on: your app's domain, plus any internal name your platform uses to reach the app. Board routes return 404 on those hosts, and the dashboard returns 404 everywhere else. (In `compose.yaml` that's why `app` is listed: webhooks from the mock arrive as `Host: app:3000`.)

`compose.yaml` leaves `TRUSTED_PROXIES` empty on purpose: the mock sends `X-Forwarded-Proto: https` like production, but speaks plain HTTP, so trusting it would turn every redirect into a broken `https://` link.

Your customers point a CNAME at `in.saascustomdomains.com` (or A records at our two static IPs for a root domain). See the [docs](https://docs.saascustomdomains.com). If you'd rather receive the customer's domain in `X-Forwarded-Host`, support can switch that on for your account.

## Tests

```bash
php artisan test               # unit and feature tests (middleware, API client, webhooks)
docker compose up -d --build --wait
npm ci
npx playwright install chromium
npm run test:e2e               # end to end, through the mock proxy
```

The end-to-end tests use `*.test` domains and send the browser's requests for them to the mock proxy, so nothing depends on DNS or on port 80. They add a domain, wait for the webhook to make it Live, apply to a job on the custom domain, and remove it. To run a second stack side by side, set `APP_PORT`, `SCD_MOCK_API_PORT`, `SCD_MOCK_PROXY_PORT` and `CADDY_PORT` for both `docker compose` and `npm run test:e2e`.

CI runs both on every push and once a week, so a new Laravel release that breaks something shows up here first.

## Tested with

| | Version |
| --- | --- |
| Laravel | 13.39.0 |
| PHP | 8.5.12 |
| FrankenPHP | 1.13.4 (Caddy 2.11.7) |
| Playwright | 1.64.1 |

Last checked: 2026-10-28.

## Not production-ready on purpose

There's no login. In your app, check in every dashboard action that the signed-in user owns the board and the domain. The API calls run inside the request to keep the example short; in your app, queue them. SQLite keeps the example small; use your own database.

## License

MIT
