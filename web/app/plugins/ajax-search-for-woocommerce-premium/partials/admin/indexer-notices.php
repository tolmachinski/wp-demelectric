<?php

// Exit if accessed directly
if ( ! defined( 'DGWT_WCAS_FILE' ) ) {
	exit;
}

if ( !empty( $notices ) ): ?>
	<div class="dgwt-wcas-indexing-notices">
		<?php foreach ( $notices as $type => $message ):
			$dashicon = '';
			switch ( $type ) {
				case 'info':
					$dashicon = '<span class="dashicons dashicons-info"></span>';
					break;
			}
			?>
			<div class="dgwt-wcas-indexing-notice dgwt-wcas-indexing-notice--<?php echo $type; ?>">
				<div><?php echo $dashicon; ?></div>
				<div><?php echo $message; ?></div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif;
