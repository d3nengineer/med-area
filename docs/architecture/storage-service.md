[← Back to Architecture](../architecture.md)

# Storage Service Boundary

## Status

Accepted for phase 1 of the S3 extraction refactor.

## Decision

MedArea will extract object-storage operations into a dedicated `storage-service` using an HTTP-first boundary.

### MedArea remains responsible for

- file metadata in the `files` table
- file ownership and authorization
- lifecycle decisions visible to product code
- compatibility behavior for existing public API routes

### `storage-service` becomes responsible for

- S3 credentials and object-storage access
- presigned upload session creation
- upload finalization and object existence checks
- single or batch download URL issuance
- physical object deletion
- internal reconciliation for partial storage operations

## Phase 1 transport model

- Inter-service control plane: HTTP only
- Data plane: clients and workers exchange bytes directly with object storage via presigned URLs
- Delete semantics from MedArea: synchronous request-response contract

Kafka is explicitly deferred. Phase 1 must not require broker infrastructure, AsyncAPI contracts, or outbox/inbox mechanics.

## Non-negotiable phase 1 rules

1. `POST /api/files` stays a legacy compatibility adapter until clients migrate.
2. The internal `storage-service` HTTP contract does not accept raw multipart file bodies from MedArea by default.
3. Public file resources must expose lifecycle state and must not expose `download_url` for files outside the `available` state.
4. `FileUploaded` means the file is finalized and available, not merely that an upload session was created.
5. OCR access must move out of Presentation and through ownership-aware application services before the storage gateway swap is complete.

## Planned internal HTTP operations

- `POST /internal/storage/upload-sessions`
- `POST /internal/storage/uploads/{storageOperationId}/finalize`
- `POST /internal/storage/download-urls`
- `POST /internal/storage/download-urls/batch`
- `DELETE /internal/storage/objects/{storageOperationId}`
- `GET /health`
- `GET /ready`

These endpoints are internal service contracts, not public MedArea API routes.

## Request metadata requirements

Every inter-service request must include:

- `X-Correlation-Id`
- `X-Causation-Id`
- `X-Idempotency-Key` for retry-safe mutating calls
- authenticated caller identity via service-to-service auth headers

## Error mapping

`storage-service` returns machine-readable error codes that MedArea maps to local lifecycle transitions:

- `storage_object_missing`
- `storage_object_conflict`
- `storage_operation_not_found`
- `storage_operation_not_ready`
- `storage_auth_failed`
- `storage_unavailable`
- `storage_timeout`

## Observability

Every boundary log line must include:

- `correlation_id`
- `causation_id`
- `file_id` when known
- `storage_operation_id` when known
- caller identity
- remote status code
- latency
- retry outcome

## Consequences

### Positive

- S3 credentials leave MedArea request paths
- compatibility upload and new direct upload can coexist during rollout
- future Kafka adoption becomes an isolated follow-up decision instead of a prerequisite

### Tradeoffs

- MedArea now depends on `storage-service` availability for upload finalization, download URL issuance, and delete requests
- lifecycle transitions and reconciliation become explicit product concerns
- rollout requires granular feature flags instead of a single global switch
