#!/usr/bin/env php
<?php
/**
 * WordPress Composer Setup Script v2.0
 *
 * PHASED SETUP: Phase 1 (Composer + Subdirectory) → Phase 2 (WP-Config)
 *
 * Usage: php wp-setup.php [--check|--fix]
* Quick Start (run this command):
 * curl -s https://raw.githubusercontent.com/jengo-agency/jblank-compowp/main/setup.php | php -- --check
 */

const SCRIPT_VERSION = '2.0.0';
const REQUIRED_PHP_VERSION = '8.0';

const WP_FILES_TO_CLEAN = [
    'wp-admin', 'wp-includes', 'wp-activate.php', 'wp-blog-header.php',
    'wp-comments-post.php', 'wp-config-sample.php', 'wp-cron.php',
    'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php',
    'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php',
    'license.txt', 'readme.html', '.htaccess'
];

// Color output functions for better UX
function output_success($message) {
    echo "\033[32m✓ $message\033[0m\n";
}

function output_error($message) {
    echo "\033[31m✗ $message\033[0m\n";
}

function output_warning($message) {
    echo "\033[33m⚠ $message\033[0m\n";
}

function output_info($message) {
    echo "\033[34mℹ $message\033[0m\n";
}

// Check PHP version
if (version_compare(PHP_VERSION, REQUIRED_PHP_VERSION, '<')) {
    output_error("PHP " . REQUIRED_PHP_VERSION . " or higher is required. Current version: " . PHP_VERSION);
    exit(1);
}

// Download required sample files from the same repo
download_sample_files();

output_info("WordPress Composer Setup Tool v" . SCRIPT_VERSION);
output_info("Working directory: " . getcwd());

// Parse command line arguments
$mode = 'check'; // default mode
$args = $argv;
array_shift($args); // remove script name

foreach ($args as $arg) {
    if ($arg === '--fix') {
        $mode = 'fix';
    } elseif ($arg === '--check') {
        $mode = 'check';
    } else {
        output_error("Unknown argument: $arg");
        output_info("Usage: php wp-setup.php [--check|--fix]");
        exit(1);
    }
}

output_info("Running in " . strtoupper($mode) . " mode");

// PHASED EXECUTION FLOW

// PHASE 1: WordPress subdirectory + Composer setup
output_info("🚀 PHASE 1: Composer + WordPress subdirectory setup");
$user_input_phase1 = [];
if ($mode === 'fix') {
    $user_input_phase1 = get_phase1_user_input();
}
$phase1_result = run_phase_1($user_input_phase1);

if (!$phase1_result['success']) {
    output_error("Phase 1 failed. Cannot proceed.");
    exit(1);
}
$user_input_phase1 = array_merge($user_input_phase1, $phase1_result['composer_data']);

output_success("✅ Phase 1 completed successfully!");

// PHASE 2: wp-config analysis and fixes
output_info("🔧 PHASE 2: wp-config analysis and configuration");
$user_input_phase2 = [];
if ($mode === 'fix') {
    $user_input_phase2 = get_phase2_user_input();
}
$phase2_success = run_phase_2($user_input_phase1, $user_input_phase2);

if (!$phase2_success) {
    output_error("Phase 2 failed.");
    exit(1);
}
output_success("✅ Phase 2 completed successfully!");


// PHASE 3: Installation Verification
output_info("🔍 PHASE 3: Verifying WordPress Installation");
$phase3_success = run_phase_3($mode, $user_input_phase1);

if($phase3_success) {
    output_success("✅ Phase 3 completed successfully!");
    output_success("🎉 WordPress setup completed successfully!");
} else {
    output_error("Phase 3 verification failed.");
    exit(1);
}

/**
 * PHASE 1: WordPress subdirectory + Composer setup
 */
function run_phase_1(array $user_input): array {
    global $mode;

    // Composer setup pipeline
    $composer_data = check_composer_json($mode);
    $composer_data = check_wp_dependency($composer_data, $mode);
    $composer_data = check_repman($composer_data, $mode);
    $composer_data = check_repository($composer_data, $user_input, $mode);

    if ($mode === 'fix') {
        file_put_contents('composer.json', json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        output_success("composer.json updated successfully.");
    }

    // Run composer install (only in fix mode)
    if ($mode === 'fix') {
        if (!run_composer_install()) {
            return ['success' => false, 'composer_data' => []];
        }
        // After composer install, we can clean up themes
        fix_themes_plugins();
    } elseif (!is_dir('wp') || !file_exists('wp/wp-settings.php')) {
        output_error("WordPress not found in 'wp' directory. Please run in --fix mode to set up.");
        return ['success' => false, 'composer_data' => []]; // WordPress not installed
    }

    // 5. Clean root files (only in fix mode)
    $files_result = check_wp_files_clean($mode);
    $files_ok = $files_result['status'];

    if (!$files_ok && $mode === 'check') {
        output_error($files_result['message']);
    }

    // 6. Check index.php
    $index_ok = check_index_php($mode);

    // 7. Verify WordPress structure
    $success = $files_ok && $index_ok && is_dir('wp') && file_exists('wp/wp-settings.php');
    
    return ['success' => $success, 'composer_data' => $composer_data];
}

/**
 * PHASE 3: Installation verification
 */
function run_phase_3($mode, $composer_data): bool {
    // 1. Load wp-config.php to make DB constants available
    if (file_exists('wp-config.php')) {
        // Use a function to include the file to scope it
        include_wp_config();
    } else {
        output_error("Cannot run DB check because wp-config.php is missing.");
        return false;
    }

    // 2. Test DB connection
    $db_check = check_database_connection();
    if (!$db_check['status']) {
        output_error("Database connection check failed. " . $db_check['message']);
        return false; // Blocking error
    }
    output_success("Database connection successful.");

    // 3. Cleanup Kinsta MU plugin
    if (!cleanup_mu_plugins($mode)) {
        return false;
    }
    
    // 4. Setup logging directory
    if (!setup_logging_directory($mode)) {
        return false;
    }
    
    // 5. Bootstrap WordPress to check environment and themes
    if (!file_exists('wp/wp-load.php')) {
        output_error("wp/wp-load.php not found. Cannot bootstrap WordPress.");
        return false;
    }
    require_once 'wp/wp-load.php';
    output_success("WordPress environment bootstrapped successfully.");

    // 6. Validate themes
    if (!validate_themes($composer_data)) {
        return false;
    }

    return true;
}


/**
 * Includes the wp-config.php file in a sandboxed function.
 */
function include_wp_config() {
    require_once 'wp-config.php';
}

/**
 * Cleanup Kinsta specific must-use plugins.
 */
function cleanup_mu_plugins($mode): bool {
    $kinsta_mu_plugin = 'wp-content/mu-plugins/kinsta-mu-plugins.php';
    if (file_exists($kinsta_mu_plugin)) {
        output_warning("Found Kinsta MU plugin at: $kinsta_mu_plugin");
        if ($mode === 'fix') {
            if (unlink($kinsta_mu_plugin)) {
                output_success("Removed Kinsta MU plugin.");
            } else {
                output_error("Failed to remove Kinsta MU plugin.");
                return false;
            }
        }
    } else {
        output_info("Kinsta MU plugin not found (which is good).");
    }
    return true;
}

/**
 * Setup logging directory and file.
 */
function setup_logging_directory($mode): bool {
    // Get home directory in a cross-platform way
    $home_dir = getenv('HOME');
    if (!$home_dir) {
        // Fallback for non-unix systems
        $home_dir = getenv('HOMEDRIVE') . getenv('HOMEPATH');
    }

    if (!$home_dir) {
        output_error("Could not determine home directory.");
        return false;
    }

    $web_dir = $home_dir . '/web';
    $log_file = $web_dir . '/jlogger.log';

    if (!is_dir($web_dir)) {
        output_warning("Logging directory not found: $web_dir");
        if ($mode === 'fix') {
            if (mkdir($web_dir, 0755, true)) {
                output_success("Created logging directory.");
            } else {
                output_error("Failed to create logging directory.");
                return false;
            }
        }
    }

    if (!file_exists($log_file)) {
        output_warning("Log file not found: $log_file");
        if ($mode === 'fix') {
            if (touch($log_file)) {
                output_success("Created log file.");
            } else {
                output_error("Failed to create log file.");
                return false;
            }
        }
    } else {
         output_success("Log file already exists.");
    }

    return true;
}

/**
 * Validates the installed and active themes.
 */
function validate_themes(array $composer_data): bool {
    global $mode; // Add this to access the mode
    $jengo_themes = [];
    // Extract all jengo-agency theme slugs from composer data
    if (isset($composer_data['require'])) {
        foreach ($composer_data['require'] as $pkg => $version) {
            if (strpos($pkg, 'jengo-agency/') === 0) {
                $parts = explode('/', $pkg);
                $jengo_themes[] = end($parts);
            }
        }
    }

    if (empty($jengo_themes)) {
        output_error("Could not find any themes from 'jengo-agency' in composer.json.");
        return false;
    }

    // Clear theme cache before checking
    wp_clean_themes_cache();
    $all_themes = wp_get_themes();
    $expected_theme_repo = '';

    // If there is more than one jengo theme, we need to find the child theme
    if(count($jengo_themes) > 1) {
        foreach ($jengo_themes as $theme_slug) {
            $theme = $all_themes[$theme_slug] ?? null;
            if ($theme && $theme->parent()) {
                // Check if the parent is also a jengo theme
                $parent_slug = $theme->parent()->get_stylesheet();
                if (in_array($parent_slug, $jengo_themes)) {
                    $expected_theme_repo = $theme_slug; // This is the child theme
                    break;
                }
            }
        }
    } else {
        $expected_theme_repo = $jengo_themes[0];
    }


    if (empty($expected_theme_repo)) {
        // Fallback to the first theme if child detection fails
        $expected_theme_repo = $jengo_themes[0];
        output_warning("Could not definitively determine child theme. Falling back to first detected jengo theme: $expected_theme_repo");
    }

    output_info("Expected theme from composer.json: $expected_theme_repo");

    // Clear theme cache before checking
    wp_clean_themes_cache();
    $all_themes = wp_get_themes();
    $jblank_found = false;
    $project_theme_found = false;

    foreach ($all_themes as $slug => $theme) {
        output_info("Found theme: " . $theme->get('Name') . " (slug: $slug)");
        if ($slug === 'jblank') {
            $jblank_found = true;
        }
        if ($slug === $expected_theme_repo) {
            $project_theme_found = true;
        }
    }

    if (!$jblank_found) output_error("Parent theme 'jblank' is not installed.");
    if (!$project_theme_found) output_error("Project theme '$expected_theme_repo' is not installed.");
    
    if (!$jblank_found || !$project_theme_found) {
        return false; // Stop if themes aren't installed
    }

    $active_theme_slug = wp_get_theme()->get_stylesheet();
    output_info("Active theme is: $active_theme_slug");

    if ($active_theme_slug !== $expected_theme_repo) {
        output_warning("Project theme '$expected_theme_repo' is not the active theme.");
        if ($mode === 'fix') {
            output_info("Activating theme '$expected_theme_repo'...");
            switch_theme($expected_theme_repo);
            // Verify it was switched
            $new_active_theme_slug = wp_get_theme()->get_stylesheet();
            if ($new_active_theme_slug === $expected_theme_repo) {
                output_success("Theme '$expected_theme_repo' activated successfully.");
                $active_theme_slug = $new_active_theme_slug; // Update for final check
            } else {
                output_error("Failed to activate theme '$expected_theme_repo'.");
                return false;
            }
        }
    }

    $validation_passed = $jblank_found && $project_theme_found && ($active_theme_slug === $expected_theme_repo);

    if ($validation_passed) {
        output_success("Theme validation passed.");
    } else {
        output_error("Theme validation failed.");
    }

    return $validation_passed;
}


/**
 * PHASE 2: wp-config analysis and fixes using robust parsing
 */
function run_phase_2(array $user_input_phase1, array $user_input_phase2): bool {
    global $mode;
    $config_file = 'wp-config.php';
    $sample_config_file = 'wp-config.sample.php';

    if (!file_exists($config_file)) {
        if ($mode === 'fix') {
            if (!file_exists($sample_config_file)) {
                output_error("wp-config.sample.php not found. Cannot create wp-config.php.");
                return false;
            }
            copy($sample_config_file, $config_file);
            output_success("Created wp-config.php from sample.");
        } else {
            output_error("wp-config.php not found. Cannot proceed with Phase 2.");
            return false;
        }
    }

    // Combine user inputs for a complete picture
    $user_input = array_merge($user_input_phase1, $user_input_phase2);

    // Get the ideal state of constants based on user input
    $required_definitions = get_required_wp_config_definitions($user_input);

    update_wp_config_constants($config_file, $required_definitions, $mode);

    // Final validation
    return validate_wp_config_final_parsing($config_file, $required_definitions);
}

/**
 * Parses wp-config.php to extract all defined constants and their values.
 */
function parse_wp_config_constants(string $file_path): array {
    if (!file_exists($file_path)) {
        return [];
    }

    $content = file_get_contents($file_path);
    $constants = [];

    // Robust regex to capture define() statements, ignoring whitespace and comments
    $pattern = '/^\s*define\s*\(\s*[\'"]([A-Z0-9_]+)[\'"]\s*,\s*(.*?)\s*\)\s*;/m';

    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            // $match[1] is the constant name (e.g., 'WP_HOME')
            // $match[2] is the raw value (e.g., "'https://example.com'")
            $constants[$match[1]] = trim($match[2]);
        }
    }

    return $constants;
}

/**
 * Defines the required constants and their correct values based on user input.
 */
function get_required_wp_config_definitions(array $user_input): array {
    $definitions = [];

    if (!empty($user_input['website_domain'])) {
        $domain = $user_input['website_domain'];
        // Define WP_HOME first, as a simple string
        $definitions['WP_HOME'] = "'$domain'";
        // Define the rest relative to WP_HOME or __DIR__
        $definitions['WP_SITEURL'] = "WP_HOME . '/wp'";
        $definitions['WP_CONTENT_URL'] = "WP_HOME . '/wp-content'";
    }

    $definitions['WP_CONTENT_DIR'] = "__DIR__ . '/wp-content'";

    return $definitions;
}

/**
 * Intelligently updates wp-config.php by adding missing or correcting existing constants.
 */
function update_wp_config_constants(string $config_file, array $required_definitions, string $mode): void {
    $content = file_get_contents($config_file);
    $existing_constants = parse_wp_config_constants($config_file);
    $lines_to_prepend = [];
    $made_changes = false;

    // First, handle corrections for existing, incorrect constants
    foreach ($required_definitions as $name => $value) {
        if (isset($existing_constants[$name]) && $existing_constants[$name] !== $value) {
            $new_definition_line = "define('$name', $value);";
            $pattern = '/^\s*define\s*\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*,\s*.*?\s*\)\s*;/m';
            $content = preg_replace($pattern, $new_definition_line, $content, 1, $count);

            if ($count > 0) {
                output_success("Corrected constant: $name");
                $made_changes = true;
            }
        }
    }

    // Second, build the block of code to prepend if anything is missing.
    // Check for missing constants to add to the top block
    foreach ($required_definitions as $name => $value) {
        if (!isset($existing_constants[$name])) {
            $lines_to_prepend[] = "define('$name', $value);";
        }
    }

    // Check if the autoload script is missing
    if (strpos($content, "/vendor/autoload.php") === false) {
        $lines_to_prepend[] = "\n// Include Composer's autoloader";
        $lines_to_prepend[] = "if (file_exists(__DIR__ . '/vendor/autoload.php')) {";
        $lines_to_prepend[] = "    require_once __DIR__ . '/vendor/autoload.php';";
        $lines_to_prepend[] = "} else {";
        $lines_to_prepend[] = "    error_log('Composer autoloader not found. Please run \"composer install\".');";
        $lines_to_prepend[] = "}";
    }

    // If there's anything to prepend, create the block and add it.
    if (!empty($lines_to_prepend)) {
        $new_block = "<?php\n" . implode("\n", $lines_to_prepend) . "\n?>\n\n";
        $content = $new_block . $content;
        output_info("Prepending new configuration block to the top of wp-config.php.");
        $made_changes = true;
    }

    // Finally, check and fix ABSPATH using the simple and robust parsing method.
    $correct_abspath_value = "__DIR__ . '/wp/'";
    if (!isset($existing_constants['ABSPATH']) || strpos($existing_constants['ABSPATH'], '/wp/') === false) {
        output_warning("ABSPATH is missing or incorrect. " . ($mode=='fix'?"Fixing...":""));
        // This pattern will find the define() line for ABSPATH, regardless of its current value.
        $pattern = '/^\s*define\s*\(\s*[\'"]ABSPATH[\'"]\s*,\s*.*?\s*\)\s*;/m';
        $new_definition_line = "define('ABSPATH', " . $correct_abspath_value . ");";
        
        // Check if the line exists to be replaced.
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $new_definition_line, $content, 1);
            if($mode=='fix') output_success("Corrected existing ABSPATH definition.");
        } else {
            // If it doesn't exist, inject it before wp-settings.php
            $wp_settings_include = "require_once ABSPATH . 'wp-settings.php';";
            $abspath_block = "/** Absolute path to the WordPress directory. */\n" . $new_definition_line . "\n\n";
            $content = str_replace($wp_settings_include, $abspath_block . $wp_settings_include, $content);
            output_info("Added missing ABSPATH definition.");
        }
        $made_changes = true;
    }


    // Finally, save the file only if changes were made and we are in fix mode.
    if ($made_changes && $mode === 'fix') {
        // Create a backup before writing changes
        $backup_file = $config_file . '.bak';
        if (!file_exists($backup_file)) {
            if (copy($config_file, $backup_file)) {
                output_success("Created backup: $backup_file");
            } else {
                output_warning("Failed to create backup of $config_file");
            }
        }
        file_put_contents($config_file, $content);
        output_success("wp-config.php has been updated.");
    } elseif ($made_changes && $mode === 'check') {
         output_warning("wp-config.php requires changes. Run with --fix to apply them.");
    }
    else {
        output_info("No configuration changes were needed in wp-config.php.");
    }
}


/**
 * Final validation of wp-config.php using parsing.
 */
function validate_wp_config_final_parsing(string $config_file, array $required_definitions): bool {
    $final_constants = parse_wp_config_constants($config_file);
    $errors = [];

    foreach ($required_definitions as $name => $value) {
        if (!isset($final_constants[$name])) {
            $errors[] = "Constant '$name' is missing.";
        } elseif ($final_constants[$name] !== $value) {
            $errors[] = "Constant '$name' is incorrect. Found {$final_constants[$name]}, expected $value.";
        }
    }

    // Add a specific check for ABSPATH
    if (!isset($final_constants['ABSPATH']) || strpos($final_constants['ABSPATH'], '/wp/') === false) {
        $errors[] = "Constant 'ABSPATH' is missing or incorrect. It should point to the '/wp/' directory.";
    }

    if (empty($errors)) {
        output_success("wp-config.php validation passed.");
        return true;
    } else {
        foreach ($errors as $error) {
            output_error($error);
        }
        return false;
    }
}





/**
 * Download sample files from the same GitHub repository
 */
function download_sample_files() {
    $base_url = 'https://raw.githubusercontent.com/jengo-agency/jblank-compowp/main/';

    $files_to_download = [
        'wp-config.sample.php' => $base_url . 'wp-config.sample.php',
        'composer.sample.json' => $base_url . 'composer.sample.json'
    ];

    foreach ($files_to_download as $local_file => $remote_url) {
        if (!file_exists($local_file)) {
            output_info("Downloading $local_file...");
            $content = @file_get_contents($remote_url);
            if ($content === false) {
                output_error("Failed to download $local_file from $remote_url");
                exit(1);
            }
            file_put_contents($local_file, $content);
            output_success("Downloaded $local_file");
        }
    }
}

/**
 * Get Phase 1 user input for composer setup (repo name, slug, branch)
 */
function get_phase1_user_input(): array {
    $input = [];

    // Check if we're running in an interactive environment
    $is_interactive = defined('STDIN') && is_resource(STDIN) &&
                     function_exists('posix_isatty') && posix_isatty(STDIN);

    // Get defaults from existing composer.json if it exists
    $defaults = get_composer_defaults();

    if ($is_interactive) {
        // Interactive mode - prompt user for input

        // Get theme repo slug
        $theme_repo_default = $defaults['website_repo_slug'] ?: 'mytheme-theme';
        echo "Enter theme repo slug [{$theme_repo_default}]: ";
        $theme_repo_input = trim(fgets(STDIN));
        $input['website_repo_slug'] = sluggify($theme_repo_input ?: $theme_repo_default);


        // Get repo name (website slug)
        $repo_default = $defaults['website_slug'] ?:  $input['website_repo_slug'];
        echo "Enter github (https://github.com/orgs/jengo-agency/repositories) repo name jengo-agency/[{$repo_default}]: ";
        $repo_input = trim(fgets(STDIN));
        $input['website_slug'] = sluggify($repo_input ?: $repo_default);

        // Get Git branch name (script will add "dev-" prefix for Composer)
        $git_branch_default = $defaults['branch_name'] ?: 'main';
        echo "Enter Git branch name (e.g., main, develop) [{$git_branch_default}]: ";
        $git_branch_input = trim(fgets(STDIN));
        $git_branch_name = $git_branch_input ?: $git_branch_default;

        // Store the Git branch name - script will add "dev-" prefix when needed
        $input['branch_name'] = $git_branch_name;



    } else {
        // Non-interactive mode - use defaults or environment variables
        $input['website_slug'] = getenv('WP_SETUP_WEBSITE_SLUG') ?: $defaults['website_slug'] ?: 'mywebsite';
        $input['website_repo_slug'] = getenv('WP_SETUP_WEBSITE_REPO_SLUG') ?: $defaults['website_repo_slug'] ?: ($input['website_slug'] . '-theme');
        $input['branch_name'] = getenv('WP_SETUP_BRANCH_NAME') ?: $defaults['branch_name'] ?: 'dev-main';

        output_warning("Running in non-interactive mode. Using defaults:");
        output_warning("Website slug: {$input['website_slug']}");
        output_warning("Theme repo slug: {$input['website_repo_slug']}");
        output_warning("Branch name: {$input['branch_name']}");
        output_warning("Set WP_SETUP_WEBSITE_SLUG, WP_SETUP_WEBSITE_REPO_SLUG, and WP_SETUP_BRANCH_NAME environment variables to customize.");
    }

    return $input;
}

/**
 * Get Phase 2 user input for website URL (after wp-config exists)
 */
function get_phase2_user_input(): array {
    $input = [];

    // Check if we're running in an interactive environment
    $is_interactive = defined('STDIN') && is_resource(STDIN) &&
                     function_exists('posix_isatty') && posix_isatty(STDIN);

    // Get current values from wp-config.php if it exists
    $current_values = get_current_wp_config_values();

    if ($is_interactive) {
        // Interactive mode - prompt user for website URL

        // Get website domain with protocol handling
        $domain_default = $current_values['WP_HOME'] ?: 'https://example.com';
        echo "Enter website URL (e.g., https://example.com) [{$domain_default}]: ";
        $domain_input = trim(fgets(STDIN));
        $input['website_domain'] = normalize_domain($domain_input ?: $domain_default);

    } else {
        // Non-interactive mode - use defaults or environment variables
        $input['website_domain'] = getenv('WP_SETUP_WEBSITE_DOMAIN') ?: ($current_values['WP_HOME'] ?: 'https://example.com');

        output_warning("Running in non-interactive mode. Using defaults:");
        output_warning("Website domain: {$input['website_domain']}");
        output_warning("Set WP_SETUP_WEBSITE_DOMAIN environment variable to customize.");
    }

    return $input;
}

/**
 * Get defaults from existing composer.json
 */
function get_composer_defaults(): array {
    $defaults = [
        'website_slug' => null,
        'website_repo_slug' => null,
        'branch_name' => null
    ];

    if (!file_exists('composer.json')) {
        return $defaults;
    }

    $composer_data = json_decode(file_get_contents('composer.json'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $defaults;
    }

    // Extract website slug from name
    if (isset($composer_data['name'])) {
        $name_parts = explode('/', $composer_data['name']);
        if (count($name_parts) > 0) {
            $defaults['website_slug'] = str_replace('<website-slug>', '', $name_parts[0]);
        }
    }

    // Extract repo slug from repositories or require
    if (isset($composer_data['repositories'])) {
        foreach ($composer_data['repositories'] as $repo) {
            if (isset($repo['url']) && strpos($repo['url'], 'jengo-agency/') !== false) {
                $url_parts = explode('/', $repo['url']);
                if (count($url_parts) > 1) {
                    $repo_name = end($url_parts);
                    $repo_name = str_replace('.git', '', $repo_name);
                    $repo_name = str_replace('<website-repo-slug>', '', $repo_name);
                    $defaults['website_repo_slug'] = $repo_name;
                    break;
                }
            }
        }
    }

    // Extract branch from require versions
    if (isset($composer_data['require'])) {
        foreach ($composer_data['require'] as $package => $version) {
            if (strpos($package, 'jengo-agency/') !== false && $version !== '*') {
                // Extract branch name from version constraint like "dev-main"
                if (preg_match('/^dev-([a-zA-Z0-9-]+)$/', $version, $matches)) {
                    $defaults['branch_name'] = $matches[1]; // Extract "main" from "dev-main"
                    break;
                }
            }
        }
    }

    return $defaults;
}

/**
 * Check database connection using credentials from wp-config.php
 */
function check_database_connection() {
    // Check if database constants are defined
    if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASSWORD') || !defined('DB_NAME')) {
        return [
            'status' => false,
            'critical' => true,
            'message' => 'Database constants not defined in wp-config.php',
            'fix_function' => null
        ];
    }

    $db_host = DB_HOST;
    $db_user = DB_USER;
    $db_password = DB_PASSWORD;
    $db_name = DB_NAME;

    // Check if password is placeholder value
    if ($db_password === 'xxx') {
        output_error("DB_PASSWORD is set to placeholder value 'xxx'");
        output_error("Please update your database password in wp-config.php before continuing");
        exit(1); // Halt the script
    }

    // Try to connect to database
    $connection = @mysqli_connect($db_host, $db_user, $db_password, $db_name);

    if (!$connection) {
        $error_message = mysqli_connect_error();
        output_error("Database connection failed: $error_message");
        output_error("Please check your database credentials in wp-config.php");
        exit(1); // Halt the script
    }

    // Connection successful
    mysqli_close($connection);

    return [
        'status' => true,
        'critical' => true,
        'message' => 'Database connection successful',
        'fix_function' => null
    ];
}

/**
 * Check and optionally fix johnpbloch/wordpress dependency in composer.json
 */
function check_composer_json($mode) {
    $composer_file = 'composer.json';
    if (!file_exists($composer_file)) {
        if ($mode === 'check') {
            output_error("composer.json not found.");
            exit(1);
        }
        // In fix mode, start with the sample file content
        $sample_file = 'composer.sample.json';
        if (!file_exists($sample_file)) {
            output_error("composer.sample.json not found. Cannot create composer.json.");
            exit(1);
        }
        output_warning("composer.json not found, creating from sample.");
        return json_decode(file_get_contents($sample_file), true);
    }

    $composer_data = json_decode(file_get_contents($composer_file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        output_error("Invalid composer.json format.");
        exit(1);
    }
    output_success("composer.json loaded successfully.");
    return $composer_data;
}

function check_wp_dependency($composer_data, $mode) {
    if (!isset($composer_data['require']['johnpbloch/wordpress'])) {
        if ($mode === 'check') {
            output_error("WordPress dependency is missing from composer.json.");
        } else {
            $composer_data['require']['johnpbloch/wordpress'] = '*';
            output_success("Added johnpbloch/wordpress dependency.");
        }
    }
    return $composer_data;
}

function check_repman($composer_data, $mode) {
    $token_configured = isset($composer_data['config']['http-basic']['jengo.repo.repman.io']['password']) &&
                      !empty($composer_data['config']['http-basic']['jengo.repo.repman.io']['password']) &&
                      $composer_data['config']['http-basic']['jengo.repo.repman.io']['password'] !== 'xxx';

    if (!$token_configured) {
        if ($mode === 'check') {
            output_error("Repman token not configured.");
        } else {
            $is_interactive = defined('STDIN') && is_resource(STDIN) && function_exists('posix_isatty') && posix_isatty(STDIN);
            $token = $is_interactive ? trim(fgets(STDIN)) : getenv('WP_SETUP_REPMAN_TOKEN');

            if (empty($token)) {
                output_error("Repman token not provided. Halting. Please set WP_SETUP_REPMAN_TOKEN or run interactively.");
                exit(1);
            }
            $composer_data['config']['http-basic']['jengo.repo.repman.io'] = ['username' => 'token', 'password' => $token];
            output_success("Repman token configured successfully.");
        }
    }
    return $composer_data;
}

function check_repository($composer_data, $user_input, $mode) {
    $slug = $user_input['website_slug'] ?? null;
    $repo_slug = $user_input['website_repo_slug'] ?? null;
    $branch_name = $user_input['branch_name'] ?? null;

    if ($mode !== 'fix') {
        // In check mode, just report if placeholders are found
        $content = json_encode($composer_data);
        if (strpos($content, '<website-slug>') !== false || strpos($content, '<website-repo-slug>') !== false) {
            output_error("Repository placeholders found in composer.json.");
        } else {
            output_success("Repository configuration appears correct.");
        }
        return $composer_data;
    }

    // In fix mode, update values regardless of placeholders
    if ($slug) {
        $composer_data['name'] = $slug . '/root';
    }

    if ($repo_slug) {
        // Remove any old jengo-agency theme dependencies
        if (isset($composer_data['require'])) {
            foreach ($composer_data['require'] as $pkg => $ver) {
                if (preg_match('/^jengo-agency\//', $pkg)) {
                    unset($composer_data['require'][$pkg]);
                }
            }
        }

        // Add the new theme dependency
        $new_theme_pkg = 'jengo-agency/' . $repo_slug;
        $composer_data['require'][$new_theme_pkg] = $branch_name ? 'dev-' . $branch_name : 'dev-main';

        // Update repositories
        $repo_updated = false;
        if (isset($composer_data['repositories'])) {
            foreach ($composer_data['repositories'] as &$repo) {
                if (isset($repo['type']) && $repo['type'] === 'vcs' && isset($repo['url']) && strpos($repo['url'], 'jengo-agency') !== false) {
                    $repo['url'] = 'git@github.com:jengo-agency/' . $repo_slug;
                    $repo_updated = true;
                    break;
                }
            }
        }

        if (!$repo_updated) {
            // If no existing VCS repo for jengo-agency was found, add a new one
            $composer_data['repositories'][] = [
                'type' => 'vcs',
                'url' => 'git@github.com:jengo-agency/' . $repo_slug
            ];
        }
    }

    output_success("Repository configuration updated based on user input.");
    return $composer_data;
}

/**
 * Check and optionally clean WordPress files from the root directory.
 */
function check_wp_files_clean($mode) {
    $found_files = [];
    foreach (WP_FILES_TO_CLEAN as $file) {
        if (file_exists($file) || is_dir($file)) {
            $found_files[] = $file;
        }
    }

    if (empty($found_files)) {
        output_success('WordPress files properly cleaned from root.');
        return ['status' => true, 'message' => 'Root directory is clean.'];
    }

    if ($mode === 'fix') {
        output_warning('Found WordPress files in root. Cleaning up...');
        $deleted_count = 0;
        foreach ($found_files as $file) {
            if (is_dir($file)) {
                remove_directory($file);
                output_info("Removed directory: $file");
                $deleted_count++;
            } elseif (file_exists($file)) {
                unlink($file);
                output_info("Removed file: $file");
                $deleted_count++;
            }
        }
        output_success("Cleaned up $deleted_count WordPress files/directories from the root.");
        return ['status' => true, 'message' => 'Cleaned up WordPress files from root.'];
    }

    // In check mode, just report the issue
    return [
        'status' => false,
        'critical' => true,
        'message' => 'Found WordPress files in root: ' . implode(', ', $found_files)
    ];
}


/**
 * Get current values from wp-config.php if it exists
 * Always uses safe temporary copy to avoid database connection issues
 */
function get_current_wp_config_values() {
    $constants = parse_wp_config_constants('wp-config.php');
    $values = ['WP_HOME' => null, 'WP_SITEURL' => null];
    if(isset($constants['WP_HOME'])) {
        $values['WP_HOME'] = trim($constants['WP_HOME'], "'\"");
    }
    if(isset($constants['WP_SITEURL'])) {
        $values['WP_SITEURL'] = trim($constants['WP_SITEURL'], "'\"");
    }
    return $values;
}

/**
 * Create a safe temporary copy of wp-config.php with dangerous includes commented out
 */

/**
 * Safely read wp-config.php values by creating a temporary copy
 * with wp-settings.php requirement commented out
 */

/**
 * Normalize domain by adding https:// if protocol is missing
 */
function normalize_domain($domain) {
    if (empty($domain)) {
        return 'https://example.com';
    }

    // Check if it already has a protocol
    if (preg_match('/^https?:\/\//i', $domain)) {
        return $domain;
    }

    // Add https:// by default
    return 'https://' . $domain;
}

/**
 * Extract slug from URL
 */
function extract_slug_from_url($url) {
    if (empty($url)) {
        return 'mywebsite';
    }

    // Remove protocol and www
    $url = preg_replace('/^https?:\/\//i', '', $url);
    $url = preg_replace('/^www\./i', '', $url);

    // Remove trailing slash and path
    $url = preg_replace('/\/.*$/', '', $url);

    // Extract domain without TLD
    $parts = explode('.', $url);
    if (count($parts) > 1) {
        // Remove TLD
        array_pop($parts);
        $slug = implode('-', $parts);
    } else {
        $slug = $url;
    }

    return sluggify($slug);
}

/**
 * Convert string to slug format
 */
function sluggify($string) {
    if (empty($string)) {
        return 'mywebsite';
    }

    // Convert to lowercase
    $string = strtolower($string);

    // Replace non-alphanumeric characters with dashes
    $string = preg_replace('/[^a-z0-9]/', '-', $string);

    // Remove multiple consecutive dashes
    $string = preg_replace('/-+/', '-', $string);

    // Remove leading/trailing dashes
    $string = trim($string, '-');

    // Ensure it's not empty
    if (empty($string)) {
        return 'mywebsite';
    }

    return $string;
}

/**
 * Checks and optionally fixes the root index.php file to bootstrap WordPress.
 */
function check_index_php($mode): bool {
    $root_index = 'index.php';
    $correct_path = '/wp/wp-blog-header.php';
    $is_ok = false;

    if (file_exists($root_index)) {
        $content = file_get_contents($root_index);
        if (strpos($content, $correct_path) !== false) {
            output_success("Root index.php is correctly configured.");
            $is_ok = true;
        } else {
            output_error("Root index.php is pointing to the wrong path.");
        }
    } else {
        output_error("Root index.php is missing.");
    }

    if (!$is_ok && $mode === 'fix') {
        output_warning("Attempting to fix root index.php...");

        // We can create a canonical index.php from scratch.
        $index_content = <<<EOT
<?php
/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * @package WordPress
 */

/**
 * Tells WordPress to load the WordPress theme and output it.
 *
 * @var bool
 */
define( 'WP_USE_THEMES', true );

/** Loads the WordPress Environment and Template */
require __DIR__ . '/wp/wp-blog-header.php';

EOT;
        file_put_contents($root_index, $index_content);
        output_success("Created/fixed root index.php to point to the correct WordPress bootstrap file.");
        return true; // Return true as we've fixed it.
    }

    return $is_ok;
}

/**
 * Fix themes and plugins cleanup
 */
function fix_themes_plugins() {
    $themes_dir = 'wp/wp-content/themes';
    $plugins_dir = 'wp/wp-content/plugins';

    if (is_dir($themes_dir)) {
        remove_directory_contents($themes_dir, ['.gitkeep']);
        output_success("Cleaned themes directory");
    }

    if (is_dir($plugins_dir)) {
        remove_directory_contents($plugins_dir, ['.gitkeep']);
        output_success("Cleaned plugins directory");
    }
}


/**
 * Run composer install to create /wp/ directory
 */
function run_composer_install() {
    output_info("Running composer install to create WordPress structure...");

    // Check if exec() function is available
    if (!function_exists('exec')) {
        output_warning("exec() function is not available in this environment.");
        output_info("Please run the following command manually:");
        output_info("composer install --no-dev --optimize-autoloader");
        output_info("Or: php composer.phar install --no-dev --optimize-autoloader");
        return false;
    }

    // Check if composer is available
    $composer_command = 'composer';
    if (!command_exists($composer_command)) {
        // Try alternative composer paths
        $composer_command = '/usr/local/bin/composer';
        if (!command_exists($composer_command)) {
            $composer_command = './composer.phar';
            if (!file_exists($composer_command)) {
                output_warning("Composer not found in PATH. Please run manually:");
                output_info("composer install --no-dev --optimize-autoloader");
                output_info("Or: php composer.phar install --no-dev --optimize-autoloader");
                return false;
            }
        }
    }

    // Run composer install
    output_info("Running composer command: $composer_command install --no-dev --optimize-autoloader");
    //$command = $composer_command . ' install -vvv --no-dev --optimize-autoloader 2>&1';
    $command = $composer_command . ' install -vvv --no-dev --optimize-autoloader';
    $output = [];
    $return_code = 0;
    exec($command, $output, $return_code);

    if ($return_code === 0) {
        output_success("Composer install completed successfully");
        if (is_dir('wp') && file_exists('wp/index.php')) {
            output_success("WordPress structure created in /wp/ directory");
            return true;
        } else {
            output_warning("Composer install completed but /wp/ directory not found as expected");
            return true; // Still return true as composer install succeeded
        }
    } else {
        output_error("Composer install failed with return code: $return_code");
        if (!empty($output)) {
            output_error("Composer output: " . implode("\n", $output));
        }
        output_info("You may need to run composer install manually.");
        return false;
    }
}

/**
 * Check if a command exists in the system
 */
function command_exists($command) {
    // If exec() is not available, we can't check
    if (!function_exists('exec')) {
        return false;
    }

    $which_command = "which $command 2>/dev/null";
    exec($which_command, $output, $return_code);
    return $return_code === 0;
}

/**
 * Recursively remove a directory
 */
function remove_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            remove_directory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

/**
 * Remove directory contents except specified files
 */
function remove_directory_contents($dir, $exclude = []) {
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        if (!in_array($file, $exclude)) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                remove_directory($path);
            } else {
                unlink($path);
            }
        }
    }
}
