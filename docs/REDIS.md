# Redis Support

This backend can optionally use Redis (if the PHP `redis` extension is installed) for:

- Rate limiting counters (preferred over filesystem counters)

## Enable

Set these in `.env` and restart PHP-FPM:

```bash
REDIS_ENABLED=1
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
REDIS_PREFIX=pweibo:
REDIS_TIMEOUT=0.2
REDIS_PERSISTENT=1
```

## Notes

- If Redis is not reachable or the PHP extension is missing, the code falls back automatically.
- Cross-process correctness is better with Redis than file-based counters.

