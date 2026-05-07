[← Configuration](configuration.md) · [Back to README](../README.md) · [Kibana Dashboards →](kibana-dashboards.md)

# ELK Stack Setup

Centralized logging via **Elasticsearch + Logstash + Kibana + Filebeat**.

Log flow:
```
Laravel → storage/logs/laravel.log → Filebeat → Logstash → Elasticsearch → Kibana
```

---

## Starting the Stack

```bash
docker compose up -d elasticsearch kibana logstash filebeat
```

Or start everything at once:

```bash
make dev
```

Wait ~30 seconds for Elasticsearch to become healthy before Kibana and Logstash connect.

---

## URLs

| Service | URL |
|---------|-----|
| Kibana | http://localhost:5601 |
| Elasticsearch | http://localhost:9200 |

---

## Enabling JSON Logging

In `.env`, set:

```env
LOG_CHANNEL=stack
LOG_STACK=json
```

This switches Laravel to write logs as JSON (one object per line), which Filebeat parses natively.

---

## Importing the Dashboard

The dashboard is imported automatically via `docker/kibana/setup.sh` on first start.

To re-import manually:

```bash
make kibana-import
```

This runs `docker/kibana/setup.sh` which imports all dashboards in dependency order
(index-pattern → visualizations → dashboard) for every subdirectory under `docker/kibana/dashboards/`.
See [Kibana Dashboards](kibana-dashboards.md) for the full directory structure.

---

## Bootstrapping Application Indexes

Create or refresh the application-managed Elasticsearch indexes:

```bash
docker compose exec php php artisan app:ensure-elasticsearch-indexes
```

This command is safe to re-run and ensures both indexes exist:

- `medarea_user_analys`
- `medarea_user_activity_audit`

---

## Verifying Logs Reach Elasticsearch

**Step 1** — generate a log entry:

```bash
docker compose exec php php artisan tinker --execute="logger()->info('elk test', ['source' => 'manual']);"
```

**Step 2** — check that Filebeat picked it up:

```bash
docker compose logs filebeat | grep "laravel"
```

**Step 3** — query Elasticsearch directly:

```bash
curl "http://localhost:9200/medarea-logs-*/_search?q=message:elk+test&pretty"
```

Expect a hit with `_source.message = "elk test"`.

---

## Verifying Audit Documents Reach Elasticsearch

1. Run a user-facing action that should be audited, such as uploading a file or deleting an analysis.
2. Ensure the application-managed indexes exist:

```bash
docker compose exec php php artisan app:ensure-elasticsearch-indexes
```

3. Query the audit index directly:

```bash
curl "http://localhost:9200/medarea_user_activity_audit/_search?pretty"
```

For a narrower check, search by action or entity type:

```bash
curl "http://localhost:9200/medarea_user_activity_audit/_search?q=action:uploaded&pretty"
curl "http://localhost:9200/medarea_user_activity_audit/_search?q=entity_type:file&pretty"
```

Audit documents intentionally store only minimal metadata:

- file events: `storage`
- analysis events: `analys_id`

Sensitive fields such as file keys, filenames, signed URLs, OCR payloads, and raw analysis values are not indexed.

---

## Running Elasticsearch Integration Tests

```bash
# Requires running ELK stack
make test-elastic
```

These tests are excluded from the standard CI pipeline (`@group elastic`).

---

## Troubleshooting

**Filebeat exits immediately:**
```bash
docker compose logs filebeat
```
Usually a permissions issue on `filebeat.yml` — the file must be owned by `root:filebeat` with `640` permissions (handled in `docker/filebeat/Dockerfile`).

**Logstash not receiving events:**
```bash
docker compose logs logstash
```
Check that Elasticsearch is healthy first: `curl http://localhost:9200/_cluster/health`.

**No data in Kibana:**
- Confirm index pattern is `medarea-logs-*` with time field `@timestamp`
- In Kibana → Discover, set time range to "Last 1 hour"
- Check that `LOG_STACK=json` is set in `.env` (plain text logs are not parsed by Filebeat)

## See Also

- [Kibana Dashboards](kibana-dashboards.md) — Dashboard structure, import order, and how to add new dashboards
- [Configuration](configuration.md) — Environment variables including `LOG_CHANNEL` and `LOG_STACK`
- [Getting Started](getting-started.md) — Docker setup and Makefile commands
