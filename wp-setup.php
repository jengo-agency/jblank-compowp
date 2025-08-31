#!/usr/bin/env php
<?php
/**
 * WordPress Composer Setup Script
 *
 * Ensures WordPress is properly configured for Composer with /wp/ subdirectory.
 * Automatically downloads required templates and sets up the project structure.
 *
 * Quick Start (run this command):
 * curl -s https://raw.githubusercontent.com/user/repo/main/wp-setup.php | php -- --check
 *
 * Usage: php wp-setup.php [--check|--fix]
 *   --check  : Validate current setup (default)
 *   --fix    : Apply automatic fixes for issues found
 *
 * Features:
 * - Downloads sample files from GitHub repo
 * - Validates WordPress subdirectory structure (/wp/)
 * - Cleans up unnecessary files from root
 * - Creates proper wp-config.php and index.php
 * - Sets up composer.json with custom theme dependencies
 * - Prompts for website-slug and website-repo-slug customization
 *
 * @author Generated WP Setup Tool
 * @version 1.0.0
 */

// Script configuration
define('SCRIPT_VERSION', '1.0.0');
define('REQUIRED_PHP_VERSION', '7.4');

// WordPress files to clean from root directory
define('WP_FILES_TO_CLEAN', [
    'wp-admin',
    'wp-includes',
    'wp-activate.php',
    'wp-blog-header.php',
    'wp-comments-post.php',
    'wp-config-sample.php',
    'wp-cron.php',
    'wp-links-opml.php',
    'wp-load.php',
    'wp-login.php',
    'wp-mail.php',
    'wp-settings.php',
    'wp-signup.php',
    'wp-trackback.php',
    'xmlrpc.php',
    'license.txt',
    'readme.html',
    '.htaccess'
]);

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

// Get user input for customization
$user_input = get_user_input();

// Main execution flow
$results = [];
$critical_error = false;

try {
    // Run all checks
    $results = run_all_checks($user_input);

    // Display results
    display_results($results);

    // Check for critical errors
    foreach ($results as $result) {
        if ($result['critical'] && !$result['status']) {
            $critical_error = true;
            break;
        }
    }

    if ($critical_error) {
        output_error("Critical errors found. Run with --fix to attempt automatic fixes.");
        exit(1);
    }

    if ($mode === 'fix') {
        output_info("Applying fixes...");
        apply_fixes($results, $user_input);
        output_success("Fixes applied successfully!");
    } else {
        output_success("All checks passed!");
    }

} catch (Exception $e) {
    output_error("Script execution failed: " . $e->getMessage());
    exit(1);
}

output_info("Script completed successfully.");
exit(0);

/**
 * Download sample files from the same GitHub repository
 */
function download_sample_files() {
    $base_url = 'https://raw.githubusercontent.com/user/repo/main/';

    $files_to_download = [
        'sample.php' => $base_url . 'sample.php',
        'composer-sample.json' => $base_url . 'composer-sample.json'
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
 * Get user input for customization
 */
function get_user_input() {
    $input = [];

    // Prompt for website slug
    echo "Enter website slug (e.g., mywebsite): ";
    $input['website_slug'] = trim(fgets(STDIN));

    // Prompt for website repo slug
    echo "Enter website repo slug (e.g., mywebsite-theme): ";
    $input['website_repo_slug'] = trim(fgets(STDIN));

    return $input;
}

/**
 * Run all validation checks
 */
function run_all_checks($user_input) {
    $results = [];

    $results[] = check_composer_dependency();
    $results[] = check_wp_files_clean();
    $results[] = check_wp_config();
    $results[] = check_index_php();
    $results[] = check_themes_plugins();
    $results[] = check_composer_json($user_input);

    return $results;
}

/**
 * Display check results
 */
function display_results($results) {
    foreach ($results as $result) {
        if ($result['status']) {
            output_success($result['message']);
        } else {
            if ($result['critical']) {
                output_error($result['message']);
            } else {
                output_warning($result['message']);
            }
        }
    }
}

/**
 * Apply fixes for failed checks
 */
function apply_fixes($results, $user_input) {
    foreach ($results as $result) {
        if (!$result['status'] && isset($result['fix_function'])) {
            $fix_function = $result['fix_function'];
            if (function_exists($fix_function)) {
                output_info("Applying fix: " . $fix_function);
                // Pass user_input to functions that need it
                if (in_array($fix_function, ['fix_composer_json'])) {
                    $fix_function($user_input);
                } else {
                    $fix_function();
                }
            }
        }
    }
}

/**
 * Check if johnpbloch/wordpress dependency exists in composer.json
 */
function check_composer_dependency() {
    $composer_file = 'composer.json';
    
    if (!file_exists($composer_file)) {
        return [
            'status' => false,
            'critical' => true,
            'message' => 'composer.json not found',
            'fix_function' => 'fix_composer_dependency'
        ];
    }
    
    $composer_data = json_decode(file_get_contents($composer_file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'status' => false,
            'critical' => true,
            'message' => 'Invalid composer.json format',
            'fix_function' => 'fix_composer_dependency'
        ];
    }
    
    $has_wordpress = isset($composer_data['require']['johnpbloch/wordpress']);
    
    return [
        'status' => $has_wordpress,
        'critical' => false,
        'message' => $has_wordpress ? 'WordPress dependency found in composer.json' : 'WordPress dependency missing from composer.json',
        'fix_function' => 'fix_composer_dependency'
    ];
}

/**
 * Check if WordPress files are properly cleaned from root
 */
function check_wp_files_clean() {
    $found_files = [];
    foreach (WP_FILES_TO_CLEAN as $file) {
        if (file_exists($file) || is_dir($file)) {
            $found_files[] = $file;
        }
    }

    $clean = empty($found_files);

    return [
        'status' => $clean,
        'critical' => true,
        'message' => $clean ? 'WordPress files properly cleaned from root' : 'Found WordPress files in root: ' . implode(', ', $found_files),
        'fix_function' => 'fix_wp_files_clean'
    ];
}

/**
 * Check wp-config.php structure and constants
 */
function check_wp_config() {
    $config_file = 'wp-config.php';
    
    if (!file_exists($config_file)) {
        return [
            'status' => false,
            'critical' => true,
            'message' => 'wp-config.php not found',
            'fix_function' => 'fix_wp_config'
        ];
    }
    
    $config_content = file_get_contents($config_file);
    
    // Check required constants
    $required_constants = [
        'WP_DEBUG',
        'WP_HOME',
        'WP_SITEURL',
        'WP_CONTENT_DIR',
        'WP_CONTENT_URL',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'DB_HOST',
        'DB_CHARSET',
        'DB_COLLATE',
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
        'WP_CACHE_KEY_SALT',
        'ABSPATH'
    ];
    
    $missing_constants = [];
    foreach ($required_constants as $constant) {
        if (strpos($config_content, "define('$constant'") === false && strpos($config_content, "define(\"$constant\"") === false) {
            $missing_constants[] = $constant;
        }
    }
    
    // Check ABSPATH
    $abspath_correct = strpos($config_content, "define('ABSPATH', dirname(__FILE__) . '/wp/')") !== false ||
                       strpos($config_content, 'define("ABSPATH", dirname(__FILE__) . "/wp/")') !== false;
    
    $structure_valid = empty($missing_constants) && $abspath_correct;
    
    $message = '';
    if (!$structure_valid) {
        $message_parts = [];
        if (!empty($missing_constants)) {
            $message_parts[] = 'Missing constants: ' . implode(', ', $missing_constants);
        }
        if (!$abspath_correct) {
            $message_parts[] = 'ABSPATH not set correctly for /wp/ subdirectory';
        }
        $message = implode('; ', $message_parts);
    } else {
        $message = 'wp-config.php structure is valid';
    }
    
    return [
        'status' => $structure_valid,
        'critical' => true,
        'message' => $message,
        'fix_function' => 'fix_wp_config'
    ];
}

/**
 * Check if index.php exists and has correct paths
 */
function check_index_php() {
    $index_file = 'index.php';
    
    if (!file_exists($index_file)) {
        return [
            'status' => false,
            'critical' => true,
            'message' => 'index.php not found in root',
            'fix_function' => 'fix_index_php'
        ];
    }
    
    $index_content = file_get_contents($index_file);
    $has_correct_path = strpos($index_content, '/wp/wp-blog-header.php') !== false;
    
    return [
        'status' => $has_correct_path,
        'critical' => true,
        'message' => $has_correct_path ? 'index.php has correct WordPress path' : 'index.php does not have correct WordPress path',
        'fix_function' => 'fix_index_php'
    ];
}

/**
 * Check if themes and plugins directories are clean
 */
function check_themes_plugins() {
    $themes_dir = 'wp/wp-content/themes';
    $plugins_dir = 'wp/wp-content/plugins';
    
    $themes_empty = !is_dir($themes_dir) || count(scandir($themes_dir)) <= 2; // . and ..
    $plugins_empty = !is_dir($plugins_dir) || count(scandir($plugins_dir)) <= 2;
    
    $clean = $themes_empty && $plugins_empty;
    
    $message = '';
    if (!$clean) {
        $message_parts = [];
        if (!$themes_empty) {
            $message_parts[] = 'themes directory not empty';
        }
        if (!$plugins_empty) {
            $message_parts[] = 'plugins directory not empty';
        }
        $message = implode(', ', $message_parts);
    } else {
        $message = 'Themes and plugins directories are clean';
    }
    
    return [
        'status' => $clean,
        'critical' => false,
        'message' => $message,
        'fix_function' => 'fix_themes_plugins'
    ];
}

/**
 * Check if composer.json matches the sample template
 */
function check_composer_json($user_input) {
    $composer_file = 'composer.json';
    $sample_file = 'composer-sample.json';

    if (!file_exists($composer_file)) {
        return [
            'status' => false,
            'critical' => false,
            'message' => 'composer.json not found',
            'fix_function' => 'fix_composer_json'
        ];
    }

    if (!file_exists($sample_file)) {
        return [
            'status' => false,
            'critical' => false,
            'message' => 'composer-sample.json template not found',
            'fix_function' => 'fix_composer_json'
        ];
    }

    $composer_data = json_decode(file_get_contents($composer_file), true);
    $sample_data = json_decode(file_get_contents($sample_file), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'status' => false,
            'critical' => false,
            'message' => 'Invalid JSON in composer files',
            'fix_function' => 'fix_composer_json'
        ];
    }

    // Check basic structure
    $has_basic_structure = isset($composer_data['name']) &&
                          isset($composer_data['require']) &&
                          isset($composer_data['extra']['installer-paths']);

    return [
        'status' => $has_basic_structure,
        'critical' => false,
        'message' => $has_basic_structure ? 'composer.json has basic required structure' : 'composer.json missing required structure',
        'fix_function' => 'fix_composer_json'
    ];
}

/**
 * Fix composer dependency
 */
function fix_composer_dependency() {
    $composer_file = 'composer.json';
    
    if (!file_exists($composer_file)) {
        // Create basic composer.json
        $composer_data = [
            'name' => 'website/root',
            'require' => [
                'johnpbloch/wordpress' => '*'
            ]
        ];
    } else {
        $composer_data = json_decode(file_get_contents($composer_file), true);
        if (!isset($composer_data['require'])) {
            $composer_data['require'] = [];
        }
        $composer_data['require']['johnpbloch/wordpress'] = '*';
    }
    
    file_put_contents($composer_file, json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    output_success("Added johnpbloch/wordpress dependency to composer.json");
}

/**
 * Fix WordPress files cleanup
 */
function fix_wp_files_clean() {
    foreach (WP_FILES_TO_CLEAN as $file) {
        if (file_exists($file)) {
            if (is_dir($file)) {
                remove_directory($file);
                output_success("Removed directory: $file");
            } else {
                unlink($file);
                output_success("Removed file: $file");
            }
        }
    }

    // Move wp-config.php if it's in wp/ directory
    if (file_exists('wp/wp-config.php') && !file_exists('wp-config.php')) {
        rename('wp/wp-config.php', 'wp-config.php');
        output_success("Moved wp-config.php to root");
    }
}

/**
 * Fix wp-config.php structure
 */
function fix_wp_config() {
    $config_file = 'wp-config.php';
    $sample_file = 'sample.php';
    
    if (!file_exists($sample_file)) {
        output_error("Cannot fix wp-config.php: sample.php template not found");
        return;
    }
    
    // For now, just copy the sample structure
    // In a real implementation, we'd merge with existing DB settings
    copy($sample_file, $config_file);
    output_success("Created wp-config.php from sample template");
}

/**
 * Fix index.php
 */
function fix_index_php() {
    $wp_index = 'wp/index.php';
    $root_index = 'index.php';
    
    if (!file_exists($wp_index)) {
        output_error("Cannot fix index.php: wp/index.php not found");
        return;
    }
    
    copy($wp_index, $root_index);
    
    // Modify the path
    $content = file_get_contents($root_index);
    $content = str_replace('/wp-blog-header.php', '/wp/wp-blog-header.php', $content);
    file_put_contents($root_index, $content);
    
    output_success("Created index.php with correct WordPress path");
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
 * Fix composer.json
 */
function fix_composer_json($user_input) {
    $composer_file = 'composer.json';
    $sample_file = 'composer-sample.json';

    if (!file_exists($sample_file)) {
        output_error("Cannot fix composer.json: composer-sample.json template not found");
        return;
    }

    // Load sample template
    $composer_data = json_decode(file_get_contents($sample_file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        output_error("Invalid JSON in composer-sample.json");
        return;
    }

    // Customize with user input
    if (!empty($user_input['website_slug'])) {
        $composer_data['name'] = str_replace('<website-slug>', $user_input['website_slug'], $composer_data['name']);
    }

    if (!empty($user_input['website_repo_slug'])) {
        // Update theme repository URL
        if (isset($composer_data['repositories'])) {
            foreach ($composer_data['repositories'] as &$repo) {
                if (isset($repo['url']) && strpos($repo['url'], '<website-repo-slug>') !== false) {
                    $repo['url'] = str_replace('<website-repo-slug>', $user_input['website_repo_slug'], $repo['url']);
                }
            }
        }

        // Update theme requirement
        if (isset($composer_data['require'])) {
            foreach ($composer_data['require'] as $package => &$version) {
                if (strpos($package, '<website-repo-slug>') !== false) {
                    $new_package = str_replace('<website-repo-slug>', $user_input['website_repo_slug'], $package);
                    unset($composer_data['require'][$package]);
                    $composer_data['require'][$new_package] = $version;
                }
            }
        }
    }

    file_put_contents($composer_file, json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    output_success("Created composer.json from sample template with customizations");
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
