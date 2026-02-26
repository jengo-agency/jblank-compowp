Deploy on prod with composer #thuglife

# Features
## Initial setup
setup.php
* Usage: php wp-setup.php [--check|--fix]
* Quick Start (run this command):
* curl -s https://raw.githubusercontent.com/jengo-agency/jblank-compowp/main/setup.php | php -- --check

## Deploy Common
deploy-commmon.yml
* used by all repos to mutualize the common deployment config
* sample : doc/dada_deploy_example.yml


## Scripts
- deploy-key.sh : deploy a github -> server ssh key (from local)
for all repos having a "composer-deploy" label