<?php
/**
 * Plugin upgrade and data migration functions.
 *
 * @package MyMicroblogPlugin
 */

// Define plugin-specific constants.
// It's good practice to ensure these are defined only once,
// usually in your main plugin file and wrapped in !defined checks if necessary.
if ( ! defined( 'MICROBLOG_PLUGIN_VERSION' ) ) {
	define( 'MICROBLOG_PLUGIN_VERSION', '2.0.0' ); // This should match your plugin header Version.
}
if ( ! defined( 'MICROBLOG_V2_MIGRATION_VERSION' ) ) {
	define( 'MICROBLOG_V2_MIGRATION_VERSION', '2.0.0' ); // The first v2 version requiring these CPT/taxonomy changes.
}
if ( ! defined( 'MICROBLOG_DB_VERSION_OPTION_KEY' ) ) {
	define( 'MICROBLOG_DB_VERSION_OPTION_KEY', 'my_microblog_db_version' ); // Option key for plugin's data/database version.
}
if ( ! defined( 'MICROBLOG_ORPHAN_CHECK_DONE_FLAG' ) ) {
	define( 'MY_MICROBLOG_ORPHAN_CHECK_DONE_FLAG', 'my_microblog_v2_orphaned_check_done' ); // Flag for the orphan check.
}
if ( ! defined( 'MICROBLOG_MIGRATION_V1_V2_FLAG' ) ) {
	define( 'MY_MICROBLOG_MIGRATION_V1_V2_FLAG', 'microblog_v1_data_migrated_to_v2_specific_flag' ); // Flag for the main v1 to v2 migration.
}
if ( ! defined( 'MICROBLOG_TEXT_DOMAIN' ) ) {
	define( 'MICROBLOG_TEXT_DOMAIN', 'my-microblog-plugin' ); // Replace with your plugin's text domain.
}

/**
 * Handles plugin activation.
 * Sets the initial database version if it's not already set.
 *
 * @since 2.0.0
 */
function microblog_on_activate() {
	if ( false === get_option( MICROBLOG_DB_VERSION_OPTION_KEY ) ) {
		update_option( MICROBLOG_DB_VERSION_OPTION_KEY, MICROBLOG_PLUGIN_VERSION );
	}
}

/**
 * Checks for orphaned v1 data and triggers migration if necessary.
 * This is particularly for scenarios where v1 was deleted and v2 installed,
 * potentially leaving v1 data behind without a clear upgrade path via DB version.
 *
 * @since 2.0.0
 */
function microblog_check_for_orphaned_v1_data_and_migrate() {
	// Only run this specific check once.
	if ( get_option( MICROBLOG_ORPHAN_CHECK_DONE_FLAG ) ) {
		return;
	}

	// If the main v1->v2 migration has already run (e.g., via a standard upgrade path),
	// mark orphan check as done and exit.
	if ( get_option( MICROBLOG_MIGRATION_V1_V2_FLAG ) ) {
		update_option( MICROBLOG_ORPHAN_CHECK_DONE_FLAG, true );
		return;
	}

	// Check if there are any posts of the old 'microblog' post type.
	$args = array(
		'post_type'      => 'microblog', // v1 post type.
		'posts_per_page' => 1,           // We only need to know if at least one exists.
		'fields'         => 'ids',       // More efficient.
		'post_status'    => 'any',       // Check all statuses.
	);
	$orphaned_posts = get_posts( $args );

	if ( ! empty( $orphaned_posts ) ) {
		// Orphaned v1 posts found, and primary migration specific flag isn't set. Trigger migration.
		run_microblog_v1_to_v2_migration();
	}

	// Mark this specific orphaned check as done to prevent re-running.
	update_option( MICROBLOG_ORPHAN_CHECK_DONE_FLAG, true );
}
add_action( 'admin_init', 'microblog_check_for_orphaned_v1_data_and_migrate', 5 );

/**
 * Checks plugin version and runs the main migration if needed during standard upgrades.
 * Also updates the stored DB version.
 *
 * @since 2.0.0
 */
function microblog_check_for_upgrade() {
	$current_db_version = get_option( MICROBLOG_DB_VERSION_OPTION_KEY );

	// If MICROBLOG_DB_VERSION_OPTION_KEY is not set, it's likely a fresh install,
	// or an upgrade from a version so old it didn't have this option.
	// The activation hook should handle truly fresh installs. This is a fallback.
	if ( false === $current_db_version ) {
		// If orphan check already ran and possibly migrated, respect that.
		// Otherwise, assume it's a fresh install of current version or needs to be treated as v1.
		// The orphan check should handle data if it exists. Here, we just set the DB version.
		// If the orphan check *did* migrate, the MICROBLOG_MIGRATION_V1_V2_FLAG would be set.
		// This path assumes if the DB option is missing, it's effectively a "fresh" state for THIS version tracking.
		// The orphan check (priority 5) should have already run if there was old data.
		update_option( MICROBLOG_DB_VERSION_OPTION_KEY, MICROBLOG_PLUGIN_VERSION );
		$current_db_version = MICROBLOG_PLUGIN_VERSION; // Set for further logic in this same execution.
	}

	// Check if migration from v1 to v2 is needed.
	if ( version_compare( $current_db_version, MICROBLOG_V2_MIGRATION_VERSION, '<' ) &&
		 version_compare( MICROBLOG_PLUGIN_VERSION, MICROBLOG_V2_MIGRATION_VERSION, '>=' ) ) {

		// Run the migration (it has its own internal one-time flag).
		run_microblog_v1_to_v2_migration();

		// Update the DB version to the current plugin version after successful migration attempt.
		update_option( MICROBLOG_DB_VERSION_OPTION_KEY, MICROBLOG_PLUGIN_VERSION );

	} elseif ( version_compare( $current_db_version, MICROBLOG_PLUGIN_VERSION, '<' ) ) {
		// If no v1->v2 migration was needed (or already done), but the DB version is still older
		// than the current plugin version (e.g., upgrading from 2.0.0 to 2.1.0),
		// just update the DB version.
		update_option( MICROBLOG_DB_VERSION_OPTION_KEY, MICROBLOG_PLUGIN_VERSION );
	}
}
add_action( 'admin_init', 'microblog_check_for_upgrade', 10 );

/**
 * Performs the data migration from v1 to v2 structure.
 * - Updates post_type from 'microblog' to 'cp_microblog'.
 * - Migrates terms from 'microblog_tag' to 'microblog_category'.
 *
 * @since 2.0.0
 */
function run_microblog_v1_to_v2_migration() {
	// Failsafe: check if this specific migration has already run.
	if ( get_option( MICROBLOG_MIGRATION_V1_V2_FLAG ) ) {
		return;
	}

	global $wpdb;

	$v1_post_type = 'microblog';
	$v2_post_type = 'cp_microblog';
	$v1_taxonomy  = 'microblog_tag';
	$v2_taxonomy  = 'microblog_category';

	$args_old_posts = array(
		'post_type'      => $v1_post_type,
		'posts_per_page' => -1, // Process all posts in batches if necessary for very large sites.
		'post_status'    => 'any',
		'fields'         => 'ids', // Only fetch IDs for initial loop.
	);
	$old_post_ids = get_posts( $args_old_posts );

	if ( empty( $old_post_ids ) ) {
		// No v1 posts found; mark migration as done for this step.
		update_option( MICROBLOG_MIGRATION_V1_V2_FLAG, true );
		return;
	}

	foreach ( $old_post_ids as $post_id ) {
		// Migrate Taxonomy.
		$term_slugs = wp_get_object_terms( $post_id, $v1_taxonomy, array( 'fields' => 'slugs' ) );
		if ( ! is_wp_error( $term_slugs ) && ! empty( $term_slugs ) ) {
			wp_set_object_terms( $post_id, $term_slugs, $v2_taxonomy, false ); // false = replace terms in $v2_taxonomy.
		}

		// Update post type.
		// Ensure the post still exists and is of the old type before updating.
		// Though get_posts should only return relevant posts.
		$post_to_update = get_post( $post_id );
		if ( $post_to_update && $post_to_update->post_type === $v1_post_type ) {
			$wpdb->update(
				$wpdb->posts,
				array( 'post_type' => $v2_post_type ),
				array( 'ID' => $post_id ),
				array( '%s' ), // Format for new value.
				array( '%d' )  // Format for WHERE condition.
			);
			clean_post_cache( $post_id );
		}
	}

	// Set the flag indicating this specific migration has been completed.
	update_option( MICROBLOG_MIGRATION_V1_V2_FLAG, true );

	// Optional: Add an admin notice.
	add_action(
		'admin_notices',
		function() {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: microblog */
						esc_html__( 'Microblog plugin data has been successfully migrated to the new format. If you encounter any issues, please check our support channels.', MICROBLOG_TEXT_DOMAIN )
					);
					?>
				</p>
			</div>
			<?php
		}
	);
}

?>
