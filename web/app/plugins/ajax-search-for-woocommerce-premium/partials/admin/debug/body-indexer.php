<?php

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Utils;
use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'DGWT_WCAS_FILE' ) ) {
	exit;
}
?>


<h2>Maintenance</h2>
<form action="<?php echo admin_url( 'admin.php' ); ?>" method="get">
	<input type="hidden" name="page" value="dgwt_wcas_debug">
	<?php wp_nonce_field( 'dgwt_wcas_debug_indexer', '_wpnonce', false ); ?>
	<input type="submit" name="dgwt-wcas-debug-delete-db-tables" class="button" value="Delete DB tables">
	<input type="submit" name="dgwt-wcas-debug-delete-indexer-options" class="button"
		   value="Delete Indexer options">

	<?php if ( is_multisite() ): ?>
		<br/><br/>
		<input type="submit" name="dgwt-wcas-debug-delete-db-tables-ms" class="button"
			   value="Delete DB tables (Network)">
		<input type="submit" name="dgwt-wcas-debug-delete-indexer-options-ms" class="button"
			   value="Delete Indexer options (Network)">
	<?php endif; ?>
</form>
<?php

if (
	! empty( $_GET['dgwt-wcas-debug-delete-db-tables'] ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_indexer' )
) {
	Builder::deleteDatabaseTables();
	echo 'tables deleted';
}

if (
	! empty( $_GET['dgwt-wcas-debug-delete-indexer-options'] ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_indexer' )
) {
	Builder::deleteIndexOptions();
	echo 'settings deleted';
}

if (
	! empty( $_GET['dgwt-wcas-debug-delete-db-tables-ms'] ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_indexer' )
) {
	Builder::deleteDatabaseTables( true );
	echo 'tables deleted (ms)';
}

if (
	! empty( $_GET['dgwt-wcas-debug-delete-indexer-options-ms'] ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_indexer' )
) {
	Builder::deleteIndexOptions( true );
	echo 'settings deleted (ms)';
}

?>

<h2>Extended indexer debug logs</h2>
<?php
if (
	! empty( $_GET['dgwt-wcas-debug-enable-indexer-debug'] ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_logs' )
) {
	set_transient( Builder::INDEXER_DEBUG_TRANSIENT_KEY, true, 12 * HOUR_IN_SECONDS );
	set_transient( Builder::INDEXER_DEBUG_SCOPE_TRANSIENT_KEY, array( 'all' ), 12 * HOUR_IN_SECONDS );
	?>
	<div class="dgwt-wcas-notice notice notice-success">
		<p>Indexer debug is now enabled with scope: all</p>
	</div>
	<?php
}
if (
	! empty( $_GET['dgwt-wcas-debug-disable-indexer-debug'] ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_logs' )
) {
	delete_transient( Builder::INDEXER_DEBUG_TRANSIENT_KEY );
	?>
	<div class="dgwt-wcas-notice notice notice-success">
		<p>Indexer debug is now disabled</p>
	</div>
	<?php
}
if (
	! empty( $_GET['dgwt-wcas-debug-save-indexer-debug-scope'] ) &&
	! empty( $_GET['dgwt-wcas-debug-indexer-debug-scope'] ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_logs' )
) {
	set_transient( Builder::INDEXER_DEBUG_TRANSIENT_KEY, true, 12 * HOUR_IN_SECONDS );
	set_transient( Builder::INDEXER_DEBUG_SCOPE_TRANSIENT_KEY, $_GET['dgwt-wcas-debug-indexer-debug-scope'], 12 * HOUR_IN_SECONDS );
	?>
	<div class="dgwt-wcas-notice notice notice-success">
		<p>Indexer debug scope saved</p>
	</div>
	<?php
}
?>
<form action="<?php echo admin_url( 'admin.php' ); ?>" method="get">
	<input type="hidden" name="page" value="dgwt_wcas_debug">
	<?php wp_nonce_field( 'dgwt_wcas_debug_logs', '_wpnonce', false ); ?>

	<strong>
		Debug state: <?php echo Builder::isDebug() ? 'enabled' : 'disabled'; ?>
		<?php echo defined( 'DGWT_WCAS_INDEXER_DEBUG' ) ? ( '(via DGWT_WCAS_INDEXER_DEBUG)' ) : ''; ?>
	</strong>
	<br/>
	<br/>
	<strong>
		Scope
		<?php echo defined( 'DGWT_WCAS_INDEXER_DEBUG_SCOPE' ) ? ( '(via DGWT_WCAS_INDEXER_DEBUG_SCOPE)' ) : ''; ?>
	</strong>
	<br/>
	<?php foreach ( Builder::$indexerDebugScopes as $scope ) {
		if ( $scope === 'all' ) {
			continue;
		}
		?>
		<label for="indexer-debug-scope-<?php echo $scope ?>">
			<input id="indexer-debug-scope-<?php echo $scope ?>" type="checkbox"
				   name="dgwt-wcas-debug-indexer-debug-scope[]"
				   value="<?php echo $scope ?>" <?php checked( Builder::isDebugScopeActive( $scope ) ) ?>
				<?php disabled( defined( 'DGWT_WCAS_INDEXER_DEBUG_SCOPE' ) ) ?>>
			<?php echo $scope; ?>
		</label>
		<br/>
	<?php } ?>
	<br/>
	<input type="submit" name="dgwt-wcas-debug-enable-indexer-debug" class="button"
		   value="Enable debug with scope: all" <?php disabled( defined( 'DGWT_WCAS_INDEXER_DEBUG' ) ) ?>>
	<input type="submit" name="dgwt-wcas-debug-save-indexer-debug-scope" class="button"
		   value="Enable debug with selected scope" <?php disabled( defined( 'DGWT_WCAS_INDEXER_DEBUG_SCOPE' ) ) ?>>
	<input type="submit" name="dgwt-wcas-debug-disable-indexer-debug" class="button"
		   value="Disable debug" <?php disabled( defined( 'DGWT_WCAS_INDEXER_DEBUG' ) ) ?>>
</form>

<h2>Most common words in index</h2>
<form action="<?php echo admin_url( 'admin.php' ); ?>" method="get">
	<input type="hidden" name="page" value="dgwt_wcas_debug">
	<?php wp_nonce_field( 'dgwt_wcas_debug_indexer_most_common_words', '_wpnonce', false ); ?>
	<?php
	if ( Multilingual::isMultilingual() ) { ?>
		<select name="lang">
			<?php
			foreach ( Multilingual::getLanguages() as $lang ) {
				printf( "<option %s>%s</option>", selected( $lang, ( isset( $_GET['lang'] ) && Multilingual::isLangCode( $_GET['lang'] ) ? $_GET['lang'] : '' ), false ), $lang );
			}
			?>
		</select>
		<?php
	}
	?>
	<input type="submit" name="dgwt-wcas-debug-get-most-common-words" class="button" value="Get list">
</form>
<?php
if (
	! empty( $_GET['dgwt-wcas-debug-get-most-common-words'] ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_indexer_most_common_words' )
) {
	global $wpdb;

	$lang          = isset( $_GET['lang'] ) && Multilingual::isLangCode( $_GET['lang'] ) ? $_GET['lang'] : '';
	$wordlistTable = Utils::getTableName( 'searchable_wordlist', $lang );
	$sql           = "SELECT * FROM $wordlistTable ORDER BY num_hits DESC LIMIT 0,20";
	$words         = $wpdb->get_results( $sql, ARRAY_A );
	?>
	<table class="wc_status_table widefat">
		<thead>
		<tr>
			<th><h3>Word</h3></th>
			<th><h3>Hits</h3></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( $words as $row ) { ?>
			<tr>
				<td><?php echo $row['term']; ?></td>
				<td><?php echo $row['num_hits']; ?></td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
	<?php
}
?>

<h2>Failure reports</h2>
<form action="<?php echo admin_url( 'admin.php' ); ?>" method="get">
	<input type="hidden" name="page" value="dgwt_wcas_debug">
	<?php wp_nonce_field( 'dgwt_wcas_debug_reset_failure_reports', '_wpnonce', false ); ?>
	<input type="submit" name="dgwt-wcas-reset-dismiss-failure-reports" class="button" value="Reset dismiss failure notifications">
	<input type="submit" name="dgwt-wcas-reset-auto-send-failure-reports" class="button" value="Reset auto send failure reports">
</form>
<?php
if (
	! empty( $_GET['dgwt-wcas-reset-dismiss-failure-reports'] ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_reset_failure_reports' )
) {
	DGWT_WCAS()->tntsearchMySql->failureReports->setDismissNotices( false );
	?>
	<div class="dgwt-wcas-notice notice notice-success">
		<p>Success!</p>
	</div>
	<?php
}
if (
	! empty( $_GET['dgwt-wcas-reset-auto-send-failure-reports'] ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_reset_failure_reports' )
) {
	DGWT_WCAS()->tntsearchMySql->failureReports->setAutoSend( false );
	?>
	<div class="dgwt-wcas-notice notice notice-success">
		<p>Success!</p>
	</div>
	<?php
}
?>
