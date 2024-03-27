<?php

// Exit if accessed directly
if ( ! defined( 'DGWT_WCAS_FILE' ) ) {
	exit;
}

?>
<div class="dgwt-wcas-indexing-header">
	<span class="dgwt-wcas-indexing-header__title" style="color:<?php echo $statusColor; ?>"><?php echo $text; ?></span>

	<?php if ( ! empty( $lastDate ) ): ?>
		<span class="dgwt-wcas-indexing-header__subtitle">

        <?php if ( ! empty( $totalProducts ) ): ?>
			<?php echo sprintf( __( 'Indexed <strong>100&#37;</strong>, <strong>%d products</strong>.', 'ajax-search-for-woocommerce' ), $totalProducts ); ?>
		<?php endif; ?>

			<?php
			echo ' ' . sprintf( __( 'Last build %s', 'ajax-search-for-woocommerce' ), $lastDate );
			echo '. ' . __( 'All product changes will be <strong>re-indexed automatically</strong>', 'ajax-search-for-woocommerce' );
			?>.

        </span>
	<?php endif; ?>

	<?php if ( in_array( $status, array( 'error' ) ) ):
		/*
		 * $lastErrorCode:
		 * 001 - Indexer stuck; Reason: WP-Cron missed events
		 * 002 - Indexer stuck; Reason: unknown
		 * 003 - Indexer stopped; Reason: low 'max_user_connections'
		 * 004 - Indexer stopped; Reason: low 'max_connections'
		 * 005 - Indexer stopped; Reason: low 'wait_timeout'
		 * 006 - Indexer stopped; Reason: low 'max_allowed_packet'
		 */
		$linkToDocs = 'https://fibosearch.com/documentation/troubleshooting/the-search-index-could-not-be-built/';
		?>
		<div class="dgwt-wcas-indexing-header__troubleshooting">
			<h3><?php _e( 'Troubleshooting', 'ajax-search-for-woocommerce' ); ?></h3>

			<?php if ( ! empty( $lastErrorMessage ) ): ?>
				<p><?php _e( 'The following error caused the index to be canceled:', 'ajax-search-for-woocommerce' ); ?></p>
				<?php if ( ! empty( $lastErrorCode ) ): ?>
					<span class="dgwt-wcas-indexing-header__error-code"><?php printf( __( 'Error code %s', 'ajax-search-for-woocommerce' ),
							$lastErrorCode ); ?></span>
				<?php endif; ?>
				<p class="dgwt-wcas-indexing-header__log"><?php echo $lastErrorMessage; ?></p>
			<?php endif; ?>
			<ol>

				<?php if ( in_array( $lastErrorCode, array( 006 ) ) ): ?>
					<li><?php _e( 'Contact your hosting provider and ask for increasing the value of the <code>max_allowed_packet</code> variable in your MySQL server. It will definitely solve the problem.', 'ajax-search-for-woocommerce' ); ?></li>
				<?php elseif ( in_array( $lastErrorCode, array( 005 ) ) ): ?>
					<li><?php printf( __( "Contact your hosting provider and ask for increasing the value of the <code>wait_timeout</code> variable in your MySQL server. It should solve the source of the problem. If your hosting provider isn't able to change MySQL variables, try to solve it in an alternative way. Follow steps below:", 'ajax-search-for-woocommerce' ), 'js-ajax-build-index' ); ?></li>
					<li>
						<?php _e( 'Add below code to your child theme’s <em>functions.php</em> file or via a plugin that allows custom functions to be added, such as the <a href="https://wordpress.org/plugins/code-snippets/" target="_blank">Code snippets</a> plugin.', 'ajax-search-for-woocommerce' ); ?>
						<br/>
						<pre>add_filter( 'dgwt/wcas/indexer/searchable_set_items_count', function ( $count ) {
    return 10;
} );</pre>
					</li>
				<?php elseif ( in_array( $lastErrorCode, array( 003, 004 ) ) ): ?>
					<li><?php printf( __( 'Contact your hosting provider and ask for increasing the value of the <code>max_connections</code> variable in your MySQL server. It will definitely solve the problem.', 'ajax-search-for-woocommerce' ), 'js-ajax-build-index' ); ?></li>
				<?php else: ?>
					<li><?php printf( __( 'If you see the <b>Troubleshooting tab</b> above, click it and try to solve the issues mentioned there', 'ajax-search-for-woocommerce' ), 'js-ajax-build-index' ); ?></li>
				<?php endif; ?>

				<?php if ( in_array( $lastErrorCode, array( 001, 002 ) ) ): ?>
					<li>
						<?php _e( 'Add below code to your child theme’s <em>functions.php</em> file or via a plugin that allows custom functions to be added, such as the <a href="https://wordpress.org/plugins/code-snippets/" target="_blank">Code snippets</a> plugin. Please don’t add custom code directly to your parent theme’s functions.php file as this will be wiped entirely when you update the theme.', 'ajax-search-for-woocommerce' ); ?>
						<br/>
<pre>add_filter( 'dgwt/wcas/indexer/searchable_set_items_count', function ( $count ) {
    return 10;
} );
add_filter( 'dgwt/wcas/indexer/readable_set_items_count', function ( $count ) {
    return 5;
} );
add_filter( 'dgwt/wcas/indexer/taxonomy_set_items_count', function ( $count ) {
    return 10;
} );
add_filter( 'dgwt/wcas/indexer/variations_set_items_count', function ( $count ) {
    return 5;
} );</pre>
					</li>
					<li><?php printf( __( '<b>Maybe your server can’t send an HTTP requests to itself</b>. Visit the documentation and read the section <a target="_blank" href="%s">“Your server can’t send an HTTP request to itself”</a>.', 'ajax-search-for-woocommerce' ),
							$linkToDocs . '#loopback' ); ?></li>
				<?php else: ?>
					<li><?php printf( __( "If the indexer still doesn't work, add a constant <code>define('DGWT_WCAS_INDEXER_MODE', 'sync');</code> to your <code>wp-config.php</code> file and try to rebuild the search index again", 'ajax-search-for-woocommerce' ), 'js-ajax-build-index' ); ?></li>
				<?php endif; ?>

				<li> <?php printf( __( 'Is it still not working? Visit the <a target="_blank" href="%s">documentation</a> or write a <a target="_blank" href="%s">support request</a>', 'ajax-search-for-woocommerce' ), $linkToDocs, dgoraAsfwFs()->contact_url() ); ?></li>

			</ol>
		</div>
	<?php endif; ?>

	<div class="dgwt-wcas-indexing-header__actions">
		<?php echo $actionButton; ?>

		<?php if ( ! in_array( $status, array( 'building', 'cancellation' ) ) ): ?>
			<a href="#" class="<?php echo $isDetails ? 'hide' : 'show'; ?> dgwt-wcas-indexing-details-trigger js-dgwt-wcas-indexing-details-trigger js-dgwt-wcas-indexing__showd"><?php _e( 'Show details', 'ajax-search-for-woocommerce' ); ?></a>
			<a href="#" class="<?php echo $isDetails ? 'show' : 'hide'; ?> dgwt-wcas-indexing-details-trigger js-dgwt-wcas-indexing-details-trigger js-dgwt-wcas-indexing__hided"><?php _e( 'Hide details', 'ajax-search-for-woocommerce' ); ?></a>
		<?php endif; ?>
	</div>

