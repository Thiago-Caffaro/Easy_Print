# SQLite backup and recovery

Easy Print stores print metadata and sanitized operational errors in the `easy_print_data` volume. It never stores document bytes, CUPS spool files, credentials, or printer configuration in this database.

## Backup points

Take a backup before upgrading the image or applying a migration, and keep a second copy outside the Docker host. Stop the web service first so SQLite is quiescent:

```bash
docker compose stop web
docker run --rm \
  -v easy-print_easy_print_data:/source:ro \
  -v "$PWD/backups:/backup" \
  alpine:3.22 \
  tar -czf /backup/easy-print-$(date -u +%Y%m%dT%H%M%SZ).tar.gz -C /source .
docker compose start web
```

The actual volume name can differ when Compose uses a project name; confirm it with `docker volume ls`. Restrict the backup directory to operators and encrypt copies at rest. A backup contains metadata such as original filenames, queue names, job IDs, and safe error codes.

## Startup migrations

The web container entrypoint runs `php bin/migrate.php` before starting the HTTP process. A migration is transactional and records its version only after success. If migration fails, the container must remain stopped: inspect logs, restore the pre-migration backup if necessary, fix the deployment, and retry. Do not delete `schema_migrations` or edit migration history manually.

## Restore verification

1. Stop the web service and preserve the current volume as a separate rollback copy.
2. Extract the selected archive into the `easy_print_data` volume with ownership matching the web container (`10001:10001` in the reference image).
3. Start the web service and inspect `docker compose logs web` for migration errors.
4. Confirm `/health/ready` reports `database: ok` and that the history page renders expected metadata.
5. Run a harmless queue/status check. CUPS remains authoritative; restoring SQLite does not restore queues, drivers, or active jobs.

If the database is corrupt, keep the original volume for forensic review, restore the newest verified archive, and run migrations only after the restored file passes the readiness check. Missing history is preferable to guessing at or recreating CUPS job state.

## Retention and temporary data

History and operational errors are deleted according to `HISTORY_RETENTION_DAYS` and `ERROR_RETENTION_DAYS`. Accepted documents are not part of a backup because they are deleted after submission; abandoned private uploads are handled separately by `php bin/cleanup-uploads.php`. The CUPS configuration and spool volumes require their own backup and recovery policy.

## TrueNAS SCALE notes

Place `easy_print_data` on a dedicated dataset with a documented ACL for the container user, enable scheduled snapshots, and replicate encrypted snapshots to separate storage. Do not expose the dataset over a general SMB share. Test a restore into a temporary dataset before replacing the production volume, and preserve the same UID/GID mapping used by the web container.
