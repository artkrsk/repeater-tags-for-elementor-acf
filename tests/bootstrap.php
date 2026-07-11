<?php
/**
 * PHPUnit bootstrap — runs INSIDE the wp-env tests-cli container.
 *
 * `wp-env start` generates the tests config this file requires via
 * WP_PHPUNIT__TESTS_CONFIG (set in phpunit.xml). The suite loads the BUILT
 * plugin from wp-content/plugins (the dist/ mount), never the repo source
 * tree — the SmokeTest pins that autoloader precedence.
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';

$rt_wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );
$rt_tests_config   = getenv( 'WP_PHPUNIT__TESTS_CONFIG' );

if ( false === $rt_wp_phpunit_dir || ! is_dir( $rt_wp_phpunit_dir ) ) {
	fwrite( STDERR, "WP_PHPUNIT__DIR is not available — run `composer install` first.\n" );
	exit( 1 );
}

if ( false === $rt_tests_config || ! file_exists( $rt_tests_config ) ) {
	fwrite( STDERR, "Tests config not found — run through wp-env: `pnpm test` (or `pnpm exec wp-env start`, then `pnpm test:php`).\n" );
	exit( 1 );
}

require $rt_wp_phpunit_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		// Two independent gates, so the three suites can each stand up the dependency
		// combo they characterize: everything, Elementor-without-ACF (the one combo
		// `Requires Plugins` can't express — what Schema's soft-check exists for), and
		// nothing at all.
		$rt_without_provider = '1' === getenv( 'RT_TESTS_WITHOUT_PROVIDER' );
		$rt_without_acf      = '1' === getenv( 'RT_TESTS_WITHOUT_ACF' );

		if ( ! $rt_without_provider ) {
			// Order matters: Elementor fires `elementor/loaded` synchronously on
			// require, and PRO Elements refuses to boot until it has.
			require WP_PLUGIN_DIR . '/elementor/elementor.php';
			require WP_PLUGIN_DIR . '/pro-elements/pro-elements.php';
		}

		if ( ! $rt_without_provider && ! $rt_without_acf ) {
			require WP_PLUGIN_DIR . '/secure-custom-fields/secure-custom-fields.php';
		}

		require WP_PLUGIN_DIR . '/repeater-tags-for-elementor-acf/repeater-tags-for-elementor-acf.php';
	}
);

tests_add_filter(
	'init',
	static function (): void {
		// PRO Elements defers its Notes table migrations to admin_init, which never
		// fires here — without the tables, every post deletion logs a DB error. The
		// installer wipes options but keeps custom tables, so drop them first and
		// let the migrations replay from zero each run.
		if ( class_exists( \ElementorPro\Modules\Notes\Database\Notes_Database_Updater::class ) ) {
			/** @var \wpdb $wpdb */
			global $wpdb;

			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}e_notes_users_relations, {$wpdb->prefix}e_notes" );

			( new \ElementorPro\Modules\Notes\Database\Notes_Database_Updater() )->up();
		}
	}
);

require $rt_wp_phpunit_dir . '/includes/bootstrap.php';
