[← Development](development.md) · [Back to README](../README.md) · [ELK Setup →](elk-setup.md)

# Configuration

## Environment Variables

Copy `.env.example` to `.env` and fill in the values below.

### Docker

| Variable | Description | Default |
|----------|-------------|---------|
| `COMPOSE_PROJECT_NAME` | Docker Compose project name (used as container prefix) | `medarea` |
| `APP_WEB_PORT` | Nginx port exposed on host | `80` |
| `APP_PATH_HOST` | Host path mounted into containers | `./` |
| `APP_PATH` | Path inside containers | `/var/www/app` |
| `UID` / `GID` | Host user/group IDs — prevents permission issues on Linux | `1000` |

### Application

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_ENV` | Environment (`local`, `production`) | `local` |
| `APP_DEBUG` | Enable debug mode | `true` |
| `APP_URL` | Application base URL | `http://localhost:${APP_WEB_PORT}` |
| `BCRYPT_ROUNDS` | Password hashing cost | `12` |

### Logging

| Variable | Description | Default |
|----------|-------------|---------|
| `LOG_CHANNEL` | Log channel (`stack`, `single`, etc.) | `stack` |
| `LOG_STACK` | Channels used by the `stack` driver | `json` |
| `LOG_LEVEL` | Minimum log level (`debug`, `info`, `error`, …) | `debug` |
| `LOG_DEPRECATIONS_CHANNEL` | Channel for PHP deprecation warnings | `null` |

> Set `LOG_STACK=json` to enable structured JSON logging for ELK. See [ELK Setup](elk-setup.md).

### Database (PostgreSQL)

| Variable | Description |
|----------|-------------|
| `DB_CONNECTION` | Driver (`pgsql`) |
| `DB_HOST` | PostgreSQL host (default: `${COMPOSE_PROJECT_NAME}-db`) |
| `DB_PORT` | PostgreSQL port (default `5432`) |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Database user |
| `DB_PASSWORD` | Database password |

> Tests use SQLite in-memory — set in `phpunit.xml`, no `.env` changes needed.

### Authentication

| Variable | Description |
|----------|-------------|
| `JWT_SECRET` | Secret key for JWT token signing — generate with `php artisan jwt:secret` |

### Cache & Queue (Redis)

| Variable | Description |
|----------|-------------|
| `REDIS_CLIENT` | Redis client library (`phpredis`) |
| `REDIS_HOST` | Redis host (default: `${COMPOSE_PROJECT_NAME}-redis`) |
| `REDIS_PORT` | Redis port (default `6379`) |
| `REDIS_PASSWORD` | Redis password (`null` if none) |
| `CACHE_STORE` | Cache backend | use `redis` in production |
| `QUEUE_CONNECTION` | Queue backend (`redis`) |

### S3 Storage (Yandex Cloud)

| Variable | Description |
|----------|-------------|
| `AWS_ACCESS_KEY_ID` | Yandex Cloud service account key ID |
| `AWS_SECRET_ACCESS_KEY` | Yandex Cloud secret key |
| `AWS_DEFAULT_REGION` | Region — Yandex Cloud: `ru-central1` |
| `AWS_BUCKET` | S3 bucket name (production) |
| `AWS_BUCKET_TESTING` | S3 bucket name (tests) |
| `AWS_ENDPOINT` | Yandex Cloud S3 endpoint URL |
| `AWS_URL` | Public URL for accessing stored files |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Use path-style S3 URLs (`false` for Yandex) |

### Yandex Cloud OCR (Recogniser)

| Variable | Description |
|----------|-------------|
| `RECOGNISER_YC_KEY_ID` | Yandex Cloud service account key ID |
| `RECOGNISER_YC_SECRET_ACCESS_KEY` | Yandex Cloud secret access key |
| `RECOGNISER_YC_URL` | Vision OCR endpoint URL |
| `RECOGNISER_YC_VERSION` | API version (default `v1`) |

### Elasticsearch

Used for both full-text search over user analyses and the ELK log pipeline.

| Variable | Description | Default |
|----------|-------------|---------|
| `ELASTICSEARCH_HOST` | Elasticsearch hostname | `elasticsearch` |
| `ELASTICSEARCH_PORT` | Elasticsearch port | `9200` |
| `ELASTICSEARCH_INDEX_PREFIX` | Prefix for index names (e.g. `medarea_user_analys`) | `medarea` |
| `ELASTIC_PASSWORD` | Password for the `elastic` superuser (leave empty for unauthenticated dev) | *(empty)* |

### Commands

| Variable | Description | Default |
|----------|-------------|---------|
| `FILES_FORCE_DELETE_SUB_DAYS` | Delete files older than N days in the force-delete command | `3` |

### Xdebug (local dev only)

| Variable | Description | Default |
|----------|-------------|---------|
| `XDEBUG_IDEKEY` | IDE key for Xdebug session | `MYIDEKEY` |
| `XDEBUG_CLIENT_PORT` | Port Xdebug connects to | `9003` |

## Database

- **Driver:** PostgreSQL (via Docker)
- **Migrations:** `database/migrations/`
- **Seeders:** `database/seeders/`
- **Testing:** SQLite in-memory (configured in `phpunit.xml`)

## Key Packages

### Production

| Package | Purpose |
|---------|---------|
| `laravel/framework` ^12.0 | Core framework |
| `spatie/laravel-data` ^4.17 | Data transfer objects |
| `tymon/jwt-auth` ^2.2 | JWT authentication |
| `league/flysystem-aws-s3-v3` ^3.0 | S3 filesystem driver |
| `predis/predis` ^3.2 | Redis client |
| `elasticsearch/elasticsearch` ^9.3 | Elasticsearch PHP client (full-text search) |

### Development

| Package | Purpose |
|---------|---------|
| `phpunit/phpunit` ^11.5.3 | Testing framework |
| `larastan/larastan` ^3.7 | Laravel-specific PHPStan rules |
| `friendsofphp/php-cs-fixer` ^3.88 | Code style fixer |
| `zircote/swagger-php` ^5.4 | OpenAPI doc generator |
| `barryvdh/laravel-ide-helper` ^3.6 | IDE autocompletion |

## See Also

- [Getting Started](getting-started.md) — Docker setup and first run
- [Development](development.md) — Testing and CI workflow
- [ELK Setup](elk-setup.md) — Centralized logging configuration
