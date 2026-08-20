# Release process

Easy Print releases are source releases first. Publishing a container image to a registry remains intentionally manual until the project owner selects a registry and provides credentials.

## Create a release

1. Merge reviewed changes into `main` and wait for the required CI checks.
2. Update `CHANGELOG.md` with the user-visible and operator-visible impact.
3. Create an annotated semantic-version tag, for example `v0.1.0`:

   ```bash
   git tag --sign --annotate v0.1.0 --message "Easy Print v0.1.0"
   git push origin v0.1.0
   ```

4. The `Release` workflow creates a GitHub Release with generated notes and attaches a source archive. Review the generated notes before announcing the release.

## Container identity

The reference Compose build uses the local image names `easy-print-web:<tag>` and `easy-print-cups:<tag>`. A future registry publication must preserve the OCI labels already emitted by the Docker build, include the exact Git revision in `org.opencontainers.image.revision`, and publish only the Linux AMD64 platform until ARM64 support is explicitly validated.

## Upgrade and rollback

- Back up the SQLite database before upgrading; see [database recovery](database.md).
- Review `.env.example` against the deployed environment and run migrations before starting the new web container.
- Keep the previous image tag available until `/health/ready` and the print smoke test succeed.
- Roll back by restoring the previous image tag and database backup. Do not migrate a database forward and then point an older image at it without checking migration compatibility.

## Dry-run verification

The workflow can be exercised safely by opening a pull request and validating the Compose model, PHP checks, Markdown checks, and container build. No registry push or physical-printer operation occurs as part of CI.
