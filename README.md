# Formidable Midigator Extension

A WordPress plugin that connects a [Midigator](https://www.midigator.com/) merchant account to a Formidable Forms based order system.

It receives Midigator webhook events, stores prevention alerts and RDR records in custom database tables, and renders front-end lists where an operator can resolve alerts and refund the matching orders — matched to orders by card BIN + last 4.

---

## Features

- **Webhook receiver** — one REST route per Midigator event type (10 in total) under `frm-midigator/v1`.
- **Persistence** — `prevention.new` and `rdr.new` payloads are written to custom tables (RDR upserts by `rdr_guid`).
- **Midigator API client** — auth with automatic JWT bearer-token caching/refresh, prevention lookup and resolution, order lookup, event subscription management, ping.
- **Front-end shortcodes** — paginated prevention and RDR lists with BIN / last-4 filters, per-row and bulk resolve, refunds of related orders, and bulk delete.
- **Per-event file logging** — every webhook body is appended to its own log file.
- **Migrations** — `dbDelta` schema install/upgrade plus JSON import/export of the plugin tables.

## Requirements

- WordPress with Formidable Forms
- PHP 8.0+
- `DotFrmOrderHelper` — an external class from the companion order plugin. Used for `getItemsByCardValues()`, `getOrderById()` and `runFullRefund()`. Related-order listing and refunds do not work without it.

## Installation

1. Copy the plugin folder into `wp-content/plugins/` and activate it.
2. Create a writable `logs/` directory inside the plugin folder (the logger does not create it).
3. Fill in the constants in [references.php](references.php) — see below.
4. Tables are created/upgraded automatically on `plugins_loaded` when `frm_midigator_db_version` does not match `FrmMidigatorMigrations::DB_VERSION`.
5. Register the webhook subscriptions with Midigator by visiting `/?midigator_create_subs=1` once.

## Configuration

All settings live in [references.php](references.php):

| Constant | Purpose |
| --- | --- |
| `MIDIGATOR_API_SECRET` | Midigator API secret (empty by default — must be set) |
| `MIDIGATOR_SANDBOX_MODE` | `true` switches every endpoint to `api-sandbox.midigator.com` |
| `MIDIGATOR_WEBHOOK_EMAIL` | Contact email sent when creating subscriptions |
| `MIDIGATOR_SETTINGS` | Endpoint base URLs, token option names, token refresh buffer/TTL |
| `MIDIGATOR_EVENT_TYPES` | Event types to register routes and subscriptions for |
| `MIDIGATOR_WEBHOOK_URLS` | Event type → public webhook URL |
| `MIDIGATOR_LOG_MAP` / `MIDIGATOR_LOG_FOLDER` | Event type → log filename, and log directory |
| `MIDIGATOR_RESOLVE_PREVENTION_REASONS` | Allowed resolution types and their labels |
| `MIDIGATOR_RESOLVED_LIST_PAGE`, `MIDIGATOR_RDR_LIST_PAGE` | Paths the list header links to |

## Shortcodes

```
[midigator-preventions-list]                        <!-- open alerts -->
[midigator-preventions-list show-resolved="true"]   <!-- resolved alerts -->
[midigator-preventions-list default-bulk-reason="issued_full_refund"]
[midigator-rdr-list]                                <!-- RDR records -->
```

The prevention list ships resolve / bulk-resolve / bulk-refund / bulk-delete actions over `admin-ajax.php`, nonce-protected with `midigator_preventions_nonce`. The RDR list is read-only.

## Webhook endpoints

Event type dots and underscores become hyphens:

```
POST|GET /wp-json/frm-midigator/v1/chargeback-new
                                  /chargeback-match
                                  /chargeback-result
                                  /chargeback-dnf
                                  /prevention-new           → stored in preventions table
                                  /prevention-match
                                  /rdr-new                  → upserted into rdr table
                                  /rdr-match
                                  /order-validation-new
                                  /order-validation-match
```

Every event is logged with request metadata (type, method, route, client IP, received time) and answered with `200 {"ok":true}`.

## Database tables

| Table | Contents |
| --- | --- |
| `{prefix}frm_midigator_preventions` | Prevention alerts, unique on `prevention_guid` |
| `{prefix}frm_midigator_rdr` | RDR events, unique on `rdr_guid` |
| `{prefix}frm_midigator_resolves` | Current resolution per prevention |
| `{prefix}frm_midigator_resolve_history` | Resolution history per prevention |

Deleting a prevention cascades to its resolve and history rows.

## Debug / maintenance query params

Appended to any front-end URL:

| Param | Action |
| --- | --- |
| `?midigator` | Dump current Midigator subscriptions |
| `?midigator_create_subs` | Create subscriptions for all configured webhook URLs |
| `?ping_event` | Ping the `chargeback.result` event |
| `?prevention` / `?resolve_prevention` | Fetch / resolve a hard-coded test prevention GUID |
| `?get_order=<id>` / `?refund_order=<entry_id>` | Dump an order / run a full refund |
| `?midigator_export` / `?midigator_import` | Export/import table data as JSON in `migrations/imports/` |
| `?log` | Print the raw list query result above a list shortcode |

## Layout

```
formidable-midigator.php      Plugin bootstrap + debug endpoints
references.php                All configuration constants
classes/
  FrmMidigatorInit.php        Include wiring
  api/                        Midigator API methods
  libs/MidigatorLib.php       Auth, token cache, HTTP transport, URL building
  models/                     Table models over $wpdb (abstract base + 4 tables)
  helpers/                    Prevention, event subscription and shortcode helpers
  loggers/                    Per-event-type file logger
  migrations/                 Schema install/upgrade + JSON import/export
webhooks/                     REST route registration and event dispatch
shortcodes/                   Prevention list and RDR list shortcodes
assets/                       Shared list CSS/JS
```

## Notes and caveats

- The debug query params above run **unauthenticated** for any visitor. Gate them behind a capability check before using this on a public site.
- Webhook routes use `permission_callback => __return_true` and do not verify a signature or shared secret.
- `MIDIGATOR_API_SECRET` is stored in a tracked source file; move it to an environment variable or `wp-config.php` for real deployments.
- The `FRM_MDG_BASE_URL` / `FRM_MDG_BASE_PATH` constants are named the wrong way round: `BASE_URL` holds the directory path, `BASE_PATH` holds the URL.
- List assets are enqueued with `?time=` cache busting, so they are never cached by browsers.

Plugin version 1.0.0 · DB version 1.2.0 · Author: Stanislav Matrosov
