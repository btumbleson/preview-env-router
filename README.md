# Preview Env Router

Gives Laravel Cloud preview environments custom subdomains, such as `admin`, `booking` and `portal`, without any changes to the app being previewed.

Vanity domains generated on preview environments don't support wildcards due to limitations in TLS certificates are issued, so a preview only gets one hostname. This app fills that gap. When a preview is created, it adds a custom domain for each configured section through the [Laravel Cloud API](https://laravel.com/cloud/docs/api/introduction):

```
{section}.{slug}.{ROUTER_ROOT_DOMAIN}

admin.feat-purpl.laravel-demo.cloud
booking.feat-purpl.laravel-demo.cloud
```

## How it works

1. You open a pull request, and Cloud's preview automation creates a preview environment.
2. On the preview's first deploy, its **Initial deploy commands** send `POST /api/sync` to the router.
3. The router lists every application its Cloud API token can see, and every environment with `created_from_automation: true`.
4. For each preview, it adds one custom domain per section configured for that application. Domains that already exist are skipped, so syncing again is safe. Non-preview environments are never touched.
5. Each domain is added as a single hostname with real-time verification and the Cloudflare `dns` (DNS only) strategy. Cloud issues each hostname its own certificate, usually within a few minutes.
6. The landing page refreshes every 10 seconds and asks Cloud to recheck domains that are still pending. It marks a domain **live** once hostname, SSL and origin are all verified.

The sync covers every preview, not only the one that triggered it, so previews created at the same time are all handled.

### The slug

`{slug}` is the preview environment's slug, which Cloud derives from the branch name (e.g. `feat-purpl`). It's lowercased, reduced to letters, numbers and dashes, and cut to 40 characters.

The hostnames are deliberately **not** built from the preview's vanity URL (e.g. `subdomain-test-feat-purpl-kkatdq.laravel-demo.cloud`). Cloud creates a DNS record for every vanity URL, and a DNS wildcard doesn't apply below a name that has its own record. So `admin.{vanity}` never resolves without a new DNS record per preview. If the slug ever matches the first label of the vanity URL, `-preview` is appended for the same reason.

## Requirements

### A one-time DNS record

The root domain's zone needs one DNS-only (grey cloud) wildcard CNAME pointing at Cloud's origin:

| Name | Type | Target | Proxy |
|---|---|---|---|
| `*.laravel-demo.cloud` | CNAME | `to.laravel-demo.cloud` | DNS only |

Cloud shows the exact origin target as `origin_cname` when you add any custom domain. The record must be **DNS only**. If it's proxied, Cloudflare serves the zone's own certificate, which covers just one subdomain level, and two-level hostnames fail.

### Laravel Cloud

- An API token (organization settings → API tokens) that can read applications and environments, and list, create and verify domains, for every application whose previews the router should handle.
- Enough custom domains on your plan: each preview uses one per section.

## Environment variables

| Variable | Required | Default | Purpose |
|---|---|---|---|
| `CLOUD_API_TOKEN` | Yes | – | Laravel Cloud API token used for every API call. |
| `ROUTER_ROOT_DOMAIN` | No | `laravel-demo.cloud` | The domain preview hostnames are created under. Its zone needs the wildcard record above. |
| `CLOUD_API_URL` | No | `https://cloud.laravel.com/api` | Laravel Cloud API base URL. |

Standard Laravel variables (`APP_KEY`, `APP_URL`, and `DB_*` from the attached database) are set by Cloud.

Sections aren't configured through environment variables. They're edited on the landing page and stored in the database.

The session, cache and queue drivers are fixed in config to `cookie`, `file` and `sync`, and ignore `SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION`. That's because Cloud injects `SESSION_DRIVER=database` when a database is attached, and the router has no sessions table.

## Deploying on Laravel Cloud

**The router**

1. Create an application from this repository and attach a database (MySQL or Postgres).
2. Build commands:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan optimize
   ```
3. Deploy commands:
   ```bash
   php artisan migrate --force
   ```
4. Set `CLOUD_API_TOKEN`, and `ROUTER_ROOT_DOMAIN` if it differs from the default.
5. Open the router, enter each application's sections (comma separated), and click **Save**.

No scheduler is needed. The router only does work when it's asked to sync, so it can scale to zero.

**Each application that has previews**

Add this to the preview automation's **Initial deploy commands**, with the router's URL (the landing page shows it filled in):

```bash
curl -fsS -X POST https://<router-domain>/api/sync || true
```

`|| true` keeps a router problem from failing the preview's first deploy. For previews that already exist, or after changing sections, click **Sync now** on the landing page.

## Routes

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/` | Landing page: each application's sections and its previews' domains. |
| `GET` | `/previews` | The preview lists only. Polled by the landing page every 10 seconds; also nudges pending domains, at most once every 30 seconds each. |
| `POST` | `/sync` | **Sync now** button. |
| `PUT` | `/applications/{application}/sections` | Saves an application's sections. |
| `POST` | `/api/sync` | Sync trigger for curl. No CSRF token; limited to 10 requests a minute. Returns JSON with the previews checked, domains added and any errors. |

None of these routes require authentication.

## Commands

```bash
php artisan previews:sync
```

Runs the same sync from the command line and prints each domain it adds.

## Limitations

- **Hostnames use the slug, not the vanity URL.** A previewed app that builds subdomain URLs from its own `APP_URL` (the vanity URL) links to `{section}.{vanity}` names, which don't resolve. Fixing that needs a DNS record per preview, e.g. Cloud also creating `*.{vanity}` next to each vanity record.
- **Slugs can collide across applications.** Two applications previewing the same branch name would ask for the same hostnames.
- **Removing a section doesn't delete existing domains.** It only stops new ones being added.
- **The router doesn't remove domains, and doesn't need to.** Closing a pull request makes Cloud delete the preview environment along with its custom domains. In testing, the preview hostnames stopped serving their certificates within a few minutes of the PRs being closed.

## Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```

Locally the database is SQLite. Without a `CLOUD_API_TOKEN`, the landing page shows the Cloud API's 401 error.
