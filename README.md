Deploy on prod with composer #thuglife

# Features
## Initial setup
`scripts/server/shell-setup.php`

One-time setup script that validates and repairs a WordPress + Composer subdirectory installation.
Run it via curl directly on the server — no git clone needed.

### Flags

| Flag | What it does |
|------|-------------|
| `--check` | Read-only audit: reports what is wrong without changing anything |
| `--fix` | Applies all fixes: updates composer.json, runs `composer install`, fixes wp-config, activates theme |
| `--website=<url>` | Website URL, required to run `--fix` non-interactively (see below) |

If no flag is given, a help message is displayed.

`--fix` asks questions (theme repo, website URL, Repman token, ...). Running it
non-interactively (e.g. piped from `curl`) without `--website` would silently
apply guessed defaults instead of your real setup, so the script refuses and
explains how to proceed instead.

### Quick Start (interactive, recommended)

Download the script first so it can prompt you on the terminal, then run it:

```bash
curl -o shell-setup.php https://raw.githubusercontent.com/jengo-agency/jblank-compowp/main/scripts/server/shell-setup.php

# Audit only (safe, no changes)
php shell-setup.php --check

# Full setup / repair — prompts for theme repo, website URL, Repman token, etc.
php shell-setup.php --fix
```

After a successful `--fix` run, configure git/SSH access for the theme repo:
```bash
composer setup
```

### Automation / CI (non-interactive)

For scripted use, pass `--website` explicitly; other values can be supplied
via the `WP_SETUP_*` environment variables (see the script for the full list):

```bash
curl -s https://raw.githubusercontent.com/jengo-agency/jblank-compowp/main/scripts/server/shell-setup.php | php -- --fix --website=https://example.com
```

---

## Deploy Common
`.github/workflows/deploy-common.yml`

Reusable GitHub Actions workflow (`workflow_call`) — cannot be triggered standalone.
Called from per-project workflows; receives a JSON config block and `secrets: inherit`.

- Resolves the target environment from the current git ref via `jq`
- SSHes into the server (`appleboy/ssh-action`) and runs `composer update`
- Logs output to `update.log`, fails the CI job on non-zero exit

Sample caller config: `doc/dada_deploy_example.yml`

---

## Scripts

### `scripts/remote/deploy-key.sh`
Run from your local machine. Deploys a GitHub → server SSH deploy key to all repos
that have the `composer-deploy` label.

### `scripts/server/install-log-purge.sh`
Installed on the server during `--fix` phase 4. Sets up a cron to purge old log files.
