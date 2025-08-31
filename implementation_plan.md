# Implementation Plan

[Overview]
The goal is to create a standalone PHP script loaded via curl from a public GitHub repository that ensures WordPress is properly configured for Composer with /wp/ subdirectory, complemented by a Composer plugin that adds the 'wp' command for integrated workflow.

The initial setup command will be: `curl -s https://raw.githubusercontent.com/user/repo/main/wp-setup.php | php -- --check` (or --fix). The script will be executed as `php wp-setup.php --check/--fix` after download. This hybrid implementation provides both the robust standalone script for initial/broken environments and a Composer plugin for seamless integration once Composer is functional. It addresses shared hosting constraints by using PHP file operations without exec, ensuring WordPress files are correctly organized and configured according to the provided sample templates.

**Enhanced Compatibility**: The script now includes graceful fallback handling for environments where the `exec()` function is disabled (common in shared hosting). When `exec()` is unavailable, the script provides clear manual instructions for running Composer commands, ensuring the script works in all PHP environments while still providing automation when possible.

[Types]
The script will use associative arrays for configuration checks and status tracking, with keys like 'composer_dependency', 'wp_files_clean', 'wp_config_valid', etc., each containing boolean status and descriptive messages.

[Files]
The implementation involves creating the standalone script and Composer plugin package.

- New files:
  - /wp-setup.php (main script, ~500 lines, standalone version for curl loading)
  - /composer-wp-plugin/composer.json (plugin package manifest)
  - /composer-wp-plugin/src/Command/WpCommand.php (Composer command class)
  - /composer-wp-plugin/src/Plugin.php (plugin registration class)
  - /wp-config.php (generated/modified from sample.php template if missing or invalid)
  - /index.php (copied and modified from /wp/index.php with updated paths)

- Existing files to be modified:
  - /wp-config.php (validate structure, ensure ABSPATH, confirm WP_HOME, leave DB/salts untouched)
  - /composer.json (validate against composer-sample.json template, add plugin dependency)

- Files to be deleted or moved:
  - Move /wp-config.php to root if in /wp/
  - Remove /wp-*.php, /xmlrpc.php, /license.txt, /readme.html from root
  - Remove /wp-admin/, /wp-includes/ directories recursively
  - Remove /.htaccess
  - Clean /wp/wp-content/themes/ and /wp/wp-content/plugins/ (remove all except .gitkeep or other git files, as our own theme/plugins will be in wp-content/*)

- Configuration file updates:
  - Update /composer.json to match composer-sample.json and include the wp plugin
  - Ensure /wp-config.php has correct ABSPATH and constants

[Functions]
The script contains modular functions for validation and fixing, with the plugin providing command-line integration.

  - check_composer_dependency() : void - verifies johnpbloch/wordpress in composer.json
  - fix_composer_dependency() : void - adds dependency to composer.json
  - check_wp_files_clean() : array - scans root for WP files to remove
  - fix_wp_files_clean() : void - performs file removals and moves
  - check_wp_config() : array - validates wp-config.php structure and constants
  - fix_wp_config() : void - restructures wp-config.php to match sample.php
  - check_index_php() : bool - verifies index.php exists and has correct paths
  - fix_index_php() : void - copies and modifies /wp/index.php
  - check_themes_plugins() : array - scans for non-repo themes/plugins
  - fix_themes_plugins() : void - removes unauthorized themes/plugins
  - check_composer_json() : array - compares with composer-sample.json
  - fix_composer_json() : void - updates composer.json to match template
  - check_repman_token() : array - verifies Repman token is set (not "xxx")
  - fix_repman_token() : void - prompts user for token and updates composer.json
  - detect_environment() : string - returns 'dev' or 'prod' based on WP_HOME
  - confirm_user() : bool - prompts user for confirmation on WP_HOME

- New methods in WpCommand.php:
  - execute() : int - main command handler for --check/--fix
  - run_checks() : array - executes all validation functions
  - apply_fixes() : void - executes all fix functions

All functions will have clear error handling and user feedback.

[Classes]
New classes for the Composer plugin to provide the 'wp' command.

- New classes:
  - WpCommand (extends Symfony\Component\Console\Command\Command) - implements the 'wp' command with --check (default)/--fix options
  - Plugin (implements Composer\Plugin\PluginInterface) - registers the command with Composer

[Dependencies]
Minimal dependencies for the hybrid approach.

- External dependencies for plugin: symfony/console, composer-plugin-api
- No dependencies for standalone script (self-contained PHP core functions)
- WordPress version: johnpbloch/wordpress: "*" (always latest stable)
- The plugin package will be added to composer.json as a local path repository during development

[Clarifications]
Key implementation details confirmed:

- Works on both fresh WordPress installations and existing sites needing conversion
- Assumes wp-config.php exists with 16 required parameters (DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, DB_CHARSET, DB_COLLATE, table prefix, 8 authentication salts/keys)
- Single site only (no multisite support)
- nginx environment (no .htaccess handling required)
- wp-content directory stays at root level, WordPress core in /wp/ subdirectory
- Theme/plugin dependencies managed via theme's composer.json
- Prompts user for <website-slug> and <website-repo-slug> to customize composer.json
- Halts at first critical error in check mode

[Testing]
Testing covers both standalone script and Composer plugin functionality.

- Create test scenarios: fresh WP install, partially configured site, broken composer.json
- Test both --check and --fix modes for both script and command
- Validate file operations don't corrupt existing data
- Test environment detection (dev/prod)
- Manual verification of wp-config.php structure preservation
- Test Composer plugin installation and command registration

[Implementation Order]
Logical sequence ensuring script works standalone before adding plugin integration.

1. Create /wp-setup.php with basic structure and argument parsing (--check/--fix)
2. Implement check/fix functions for Composer dependency
3. Add WP files cleanup functions
4. Implement wp-config.php validation and fixing
5. Add index.php installation and modification
6. Implement themes/plugins cleanup
7. Add composer.json validation and fixing
8. Integrate user confirmation for WP_HOME
9. Add comprehensive error handling and user feedback
10. Test standalone script functionality
11. Create Composer plugin package structure
12. Implement WpCommand class
13. Implement Plugin class for command registration
14. Add plugin to composer.json
15. Test integrated Composer command
