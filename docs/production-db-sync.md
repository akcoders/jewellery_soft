# Production database to local over SSH

Run this script on your local machine. It downloads production's schema and data
over SSH, backs up your existing local database, then replaces the local database.
Production receives only dump/read operations. This is a full replacement, not a
merge: local-only tables and records are removed.

## Setup

1. Both machines need PHP 8.2+, a Composer-installed project with its `.env`, and
   `mysqldump` (or `mariadb-dump`). Local also needs `mysql` (or `mariadb`) and SSH.
2. Create the local configuration:

   ```sh
   cp scripts/db-sync.example.json scripts/db-sync.json
   ```

3. Edit `scripts/db-sync.json`:
   - `ssh.destination`: SSH alias from `~/.ssh/config`, or `user@server`.
   - `ssh.port`: your SSH port (default 22).
   - `ssh.identity_file`: absolute private key path, or empty to use your SSH agent/default keys.
   - `remote.project_path`: absolute production project root containing `.env`,
     `vendor/`, and `app/`. This is usually the directory above `public/`.
   - `remote.php_binary`, `remote.dump_binary`: executable names or full paths on production.
   - `local.mysql_binary`, `local.dump_binary`: executable names or full paths on local.
     For example, if installed there, `/opt/homebrew/opt/mysql-client/bin/mysql`
     and `/opt/homebrew/opt/mysql-client/bin/mysqldump`.
   - Optional `local.dump_events` / `remote.dump_events` (default `true`): set to
     `false` only after verifying that database has no stored SQL events. This
     supports servers where `SHOW EVENTS` fails because the scheduler is disabled,
     without changing server settings. Local applies to the recovery backup;
     remote applies to the production dump.
   - Optional `local.dump_routines` / `remote.dump_routines` (default `true`):
     set to `false` only after verifying there are no stored functions/procedures.
     This permits a table backup when an older server's system routine metadata
     is incompatible, without upgrading or changing its system tables.

Database credentials come from each project's `app/Config/Database.php` defaults
and `.env`/environment overrides, using CodeIgniter's parser for the `default`
MySQLi connection. No database passwords belong in `db-sync.json`. That file is
gitignored. DSN connections are not supported. The local hostname must be a
loopback address (`localhost`, `127.0.0.1`, `::1`) or a Unix socket path, and the
local environment must not be `production`. Use `127.0.0.1` for a TCP connection
on a custom local port. Do not point local settings at an SSH tunnel to production.

The local database must already exist. Its user needs permission to dump it and
to drop/recreate/import it. Production's user needs the dump privileges for its
tables, views, triggers, routines, and events. Test SSH once interactively to
verify/accept the server's host key:

```sh
ssh -p 22 deploy@your-production-server true
```

## Run

```sh
# Show source, target, and plan; no SSH/DB connections or file writes.
php scripts/sync-production-db.php --dry-run

# Download, back up, and replace local (type the local DB name when prompted).
php scripts/sync-production-db.php

# Skip the local replacement prompt, for an already configured workflow.
php scripts/sync-production-db.php --yes

# Use a different JSON configuration.
php scripts/sync-production-db.php --config /absolute/path/db-sync.json --dry-run
```

Backups and downloaded SQL stay under `writable/backups/db-sync/` with private
permissions. Credentials use temporary files with mode `0600` and are removed on
normal exit/error. A file lock prevents overlapping syncs in this checkout.
An SSH failure, incomplete dump, or failed local backup stops before replacement.
Dump headers are checked to reject SSH banners or PHP output mixed into the SQL.
No migrations, seeders, or application jobs are run.

Stop local application writers/background jobs before syncing. The script does
not manage application services. Recreating/importing is not atomic; if import
fails, local can contain a partial database. Dumps include routines, events, and
triggers, so use matching MySQL/MariaDB versions and adequate local privileges
for production `DEFINER` accounts. Schema-qualified references inside views,
routines, and event bodies retain the production database name; they are not
rewritten if the local database name differs. SQL events may run if your local event scheduler
is enabled. Recent MariaDB dumps include a sandbox client command that older
clients cannot parse. When the local client's help does not list the `sandbox (\-)`
command, the script skips only that exact first-line command during import and
keeps the downloaded SQL unchanged. Other SQL is preserved. See the
[MariaDB dump documentation](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump).

`--single-transaction` gives a consistent snapshot for InnoDB tables. Avoid schema
changes during the dump; nontransactional tables need a quiet source for consistency.
See the [MySQL dump documentation](https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html).
Uploads/attachments and application files are separate and are not copied.

## Recover the previous local database

The `local-before-sync-*.sql` backup includes `DROP DATABASE`, `CREATE DATABASE`,
and `USE` for the original local database. Restore it with your local client and
credentials (the example prompts for your local root password):

```sh
mysql --user=root --password < writable/backups/db-sync/local-before-sync-TIMESTAMP.sql
```

Use the full client path if it is not in `PATH`. The downloaded
`production-*.sql` has no database creation/selection statements; the script
imports it into the local configured database. Keep backup SQL private and delete
old copies when no longer needed.

## Verify the script

```sh
php -l scripts/sync-production-db.php
php tests/scripts/production-db-sync.php
```

The integration checks use disposable fixture projects and fake SSH/database
clients, including the actual streamed remote PHP payload. They never connect to
production or modify the local application database.
