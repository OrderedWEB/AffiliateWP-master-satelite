# AFFCD Contract Quickstart

## Files
- `contracts/affcd.schema.json` — versioned schema for Master ↔ Satellite
- `includes/class-api-router.php` — unified REST namespace (`affcd/v1`)
- `docs/adr/0001-dataflow-and-namespaces.md` — rationale and plan

## Example Requests

### POST /wp-json/affcd/v1/track
```json
{
  "schema_version": "1.0.0",
  "site_id": "sat-123",
  "event_id": "b9d1d7b0-6a8b-4d3e-9c6e-2a42e8d2d111",
  "event_type": "lead",
  "occurred_at": "2025-09-29T10:45:00Z",
  "affiliate_ref": {"affiliate_code": "RICHKING10"},
  "utm": {"source":"newsletter","campaign":"sept"},
  "idempotency_key": "sat-123|b9d1d7b0-6a8b-4d3e-9c6e-2a42e8d2d111"
}
```
Headers:
```
X-AFFCD-Signature: <hmac-hex>
X-AFFCD-Timestamp: 1695980700
```

### POST /wp-json/affcd/v1/convert
Same as above, with `event_type: "purchase"` and `order` object.

### POST /wp-json/affcd/v1/batch
```json
{
  "schema_version":"1.0.0",
  "site_id":"sat-123",
  "events":[ ...TrackEvent items... ]
}
```

### GET /wp-json/affcd/v1/config
Returns `DomainConfig` with `ingest_url`, `webhook_url`, and feature flags.
