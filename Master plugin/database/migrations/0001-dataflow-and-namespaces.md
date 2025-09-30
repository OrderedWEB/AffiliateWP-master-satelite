# ADR 0001 — Master ↔ Satellite Dataflow and Namespaces

**Status**: Proposed  
**Date**: 2025-09-29

## Context
Historically, the Master plugin exposes multiple REST namespaces (`affiliate-enhancement/v1`, `affiliate/v1`, `affiliatewp-cross-domain/v1`, `affcd/v1`), while the Satellite uses `affiliate-client/v1`. This complicates discovery, versioning, and documentation.

## Decision
Unify public integration under a single namespace: **`affcd/v1`**.

- Keep legacy routes as **compat** (register aliases) where needed.
- Centralize route registration in a new router (`AFFCD_API_Router`).
- Publish a single versioned contract: `contracts/affcd.schema.json`.
- All external requests must include signature headers:
  - `X-AFFCD-Signature`: HMAC-SHA256(hex) of canonical JSON body
  - `X-AFFCD-Timestamp`: Unix epoch seconds
- Every payload includes a `schema_version` (e.g., `1.0.0`).
- Enforce **idempotency** via `idempotency_key` when creating/updating records.

## Consequences
- Clearer discovery and consistent CORS/auth/permission handling.
- Easier client implementations across satellite sites.
- Less risk of version drift between features.

## Implementation Plan
1. Add `contracts/affcd.schema.json` to the repo (Master; Satellite can vendor the file or reference it).
2. Add `includes/class-api-router.php` in Master. Router will:
   - Set `namespace = 'affcd/v1'`.
   - Delegate to existing classes for handlers (e.g., `AFFCD_API_Endpoints`, `SatelliteDataBackflowManager`, `AFFCD_Tracking_Sync`).
   - Add CORS headers and a shared `permission_callback` for integration endpoints.
3. Update Satellite to call Master at `affcd/v1` endpoints:
   - `/track`, `/convert`, `/batch`
   - `/config`, `/validate-code`, `/health`
4. Keep legacy endpoints registered for one minor version with deprecation notices.
5. Document example requests/responses in `README.md`.

## Example Endpoints (affcd/v1)
- `POST /track` — single track event (schema: `TrackEvent`)
- `POST /convert` — conversion event (schema: `ConversionEvent`)
- `POST /batch` — batch envelope (schema: `BatchEnvelope`)
- `GET  /config` — domain config for satellites (schema: `DomainConfig`)
- `POST /webhook/referral-update` — master → satellite webhook (schema: `WebhookReferralUpdate`)
- `GET  /health` — non-sensitive service check

## Security
- HMAC secret per domain/site. Reject if timestamp skew > 5 minutes.
- Apply WordPress `nonce` only for admin/browser-initiated AJAX; **REST integration uses HMAC**.
- Rate-limit `track`, `convert`, and `batch`.

