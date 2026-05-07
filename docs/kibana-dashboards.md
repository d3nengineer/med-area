[← ELK Setup](elk-setup.md) · [Back to README](../README.md)

# Kibana Dashboards

This directory contains Kibana saved objects exported as NDJSON files.
They are automatically imported by `docker/kibana/setup.sh` on container startup.

## Structure

Each dashboard lives in its own subdirectory with files split by object type:

```
docker/kibana/dashboards/
├── logs-overview/              # Application logs — level distribution & top errors
│   ├── index-pattern.ndjson   # Index pattern: medarea-logs-*
│   ├── viz-log-levels.ndjson  # Pie chart — log level distribution
│   ├── viz-top-errors.ndjson  # Table — top ERROR/CRITICAL messages
│   └── dashboard.ndjson       # Dashboard: "Logs Overview"
│
├── ocr-monitoring/             # OCR pipeline — incoming job timeline
│   ├── index-pattern.ndjson   # Index pattern: medarea-logs-* (same as above)
│   ├── viz-ocr-timeline.ndjson # Bar chart — OCR requests over time
│   └── dashboard.ndjson       # Dashboard: "OCR Monitoring"
│
└── user-activity-audit/        # Audit stream — timeline and action breakdown
    ├── index-pattern.ndjson   # Index pattern: *_user_activity_audit
    ├── viz-audit-timeline.ndjson # Bar chart — audit events over time
    ├── viz-action-breakdown.ndjson # Pie chart — action distribution
    └── dashboard.ndjson       # Dashboard: "User Activity Audit"
```

## Import order

Kibana requires objects to be imported in dependency order.
`setup.sh` enforces this automatically for every subdirectory:

1. `index-pattern.ndjson` — data source, must exist first
2. `viz-*.ndjson` — visualizations that reference the index-pattern
3. `dashboard.ndjson` — dashboard that references the visualizations

> `overwrite=true` is set, so re-running setup is safe and idempotent.

## Adding a new dashboard

1. Create a new subdirectory: `docker/kibana/dashboards/<your-dashboard-name>/`
2. Export objects from Kibana: **Stack Management → Saved Objects → Export**
3. Split the exported NDJSON into separate files following the naming convention above
4. No changes to `setup.sh` are needed — it auto-discovers subdirectories

## Audit Dashboard Notes

The `user-activity-audit/` dashboard is designed for the audit stream written to
`{index_prefix}_user_activity_audit`. It focuses on:

- activity volume over time
- action mix (`uploaded`, `soft_deleted`, `created`, `deleted`)

The dashboard intentionally avoids sensitive payload visualization because the audit
documents only index safe metadata.

## Shared objects

`index-pattern.ndjson` (id: `16e9c6a4-aeab-4baf-b7ed-83bb01b4f675`) is duplicated
across subdirectories intentionally — `overwrite=true` makes this idempotent,
and it keeps each dashboard self-contained and independently importable.

## See Also

- [ELK Setup](elk-setup.md) — Starting the stack, enabling JSON logging, troubleshooting
- [Configuration](configuration.md) — `LOG_CHANNEL` and `LOG_STACK` environment variables
