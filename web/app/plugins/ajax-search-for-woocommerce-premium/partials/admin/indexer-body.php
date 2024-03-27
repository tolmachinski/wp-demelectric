<?php

// Exit if accessed directly
if ( ! defined( 'DGWT_WCAS_FILE' ) ) {
	exit;
}

$totalNonProducts = ! empty( $info['total_non_products_for_indexing'] ) ? absint( $info['total_non_products_for_indexing'] ) : 0;


if ( in_array( $info['status'], array( 'building', 'done' ) ) ): ?>
	<div class="progress_bar">
		<div class="pro-bar">
			<small class="progress_bar_title">
				<?php
				$text = __( 'Indexing... This process will continue in the background. You can leave this page without any worries.', 'ajax-search-for-woocommerce' );
				if ( empty( $progressPercent ) ) {
					$text = __( 'Index build progress', 'ajax-search-for-woocommerce' );
				} elseif ( $progressPercent == 100 ) {
					$txt  = \DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder::getReadableTotalIndexed();
					$text = sprintf( __( 'Finalization... Wait a moment. (%s products)', 'ajax-search-for-woocommerce' ), $txt );
				}

				if ( $info['status'] === 'cancellation' ) {
					$text = __( 'Cancellation...', 'ajax-search-for-woocommerce' );
				}

				echo $text;
				?>
				<span class="progress_number"><?php echo round( $progressPercent, 1 ); ?>%</span>
			</small>
			<span class="progress-bar-inner" style="background-color: #e6a51d; width: <?php echo $progressPercent; ?>%;">
        </span>
		</div>
	</div>
<?php endif; ?>

<div class="js-dgwt-wcas-indexer-details<?php echo $isDetails ? ' show' : ' hide'; ?>">
	<table class="dgwt-wcas-indexer-table js-dgwt-wcas-indexer-table js-dgwt-wcas-indexer-table">
		<tbody>
		<tr>
			<td><?php _e( 'Build ID', 'ajax-search-for-woocommerce' ); ?></td>
			<td><?php echo ! empty( $info['build_id'] ) ? $info['build_id'] : '-'; ?></td>
		</tr>
		<tr>
			<td><?php _e( 'DB', 'ajax-search-for-woocommerce' ); ?></td>
			<td><?php echo ! empty( $info['db'] ) ? $info['db'] : '-'; ?></td>
		</tr>
		<tr>
			<td><?php _e( 'Index build start', 'ajax-search-for-woocommerce' ); ?></td>
			<td><?php echo ! empty( $info['start_ts'] ) ? date( 'Y-m-d H:i:s', $info['start_ts'] ) : '-'; ?></td>
		</tr>
		<tr>
			<td><?php _e( 'Status', 'ajax-search-for-woocommerce' ); ?></td>
			<td><?php echo ! empty( $info['status'] ) ? $info['status'] : '-'; ?></td>
		</tr>
		<tr>
			<td><?php _e( 'Products', 'ajax-search-for-woocommerce' ); ?></td>
			<td><?php echo ! empty( $info['total_products_for_indexing'] ) ? absint( $info['total_products_for_indexing'] ) : '-'; ?></td>
		</tr>
		<?php if ( ! empty( $totalNonProducts ) ): ?>
			<tr>
				<td><?php _e( 'Posts & pages', 'ajax-search-for-woocommerce' ); ?></td>
				<td><?php echo $totalNonProducts; ?></td>
			</tr>
		<?php endif; ?>
		<tr>
			<td><?php _e( 'Searchable', 'ajax-search-for-woocommerce' ); ?></td>
			<td>
				<table class="dgwt-wcas-indexer-table__in">
					<tr>
						<td><?php _e( 'Start', 'ajax-search-for-woocommerce' ); ?></td>
						<td><?php echo ! empty( $info['start_searchable_ts'] ) ? date( 'H:i:s', $info['start_searchable_ts'] ) : '-'; ?></td>
					</tr>
					<tr>
						<td><?php _e( 'End', 'ajax-search-for-woocommerce' ); ?></td>
						<td><?php echo ! empty( $info['end_searchable_ts'] ) ? date( 'H:i:s', $info['end_searchable_ts'] ) : '-'; ?></td>
					</tr>
					<?php if ( ! empty( $info['start_searchable_ts'] ) && ! empty( $info['end_searchable_ts'] ) ): ?>
						<tr>
							<td><?php _e( 'Time', 'ajax-search-for-woocommerce' ); ?></td>
							<td><?php echo $info['end_searchable_ts'] - $info['start_searchable_ts'] ?> sec</td>
						</tr>
					<?php endif; ?>
				</table>

			</td>
		</tr>
		<tr>
			<td><?php _e( 'Readable', 'ajax-search-for-woocommerce' ); ?></td>
			<td>
				<table class="dgwt-wcas-indexer-table__in">
					<tbody>
					<tr>
						<td><?php _e( 'Start', 'ajax-search-for-woocommerce' ); ?></td>
						<td><?php echo ! empty( $info['start_readable_ts'] ) ? date( 'H:i:s', $info['start_readable_ts'] ) : '-'; ?></td>
					</tr>
					<tr>
						<td><?php _e( 'End', 'ajax-search-for-woocommerce' ); ?></td>
						<td><?php echo ! empty( $info['end_readable_ts'] ) ? date( 'H:i:s', $info['end_readable_ts'] ) : '-'; ?></td>
					</tr>
					<?php if ( ! empty( $info['start_readable_ts'] ) && ! empty( $info['end_readable_ts'] ) ): ?>
						<tr>
							<td><?php _e( 'Time', 'ajax-search-for-woocommerce' ); ?></td>
							<td><?php echo $info['end_readable_ts'] - $info['start_readable_ts'] ?> sec</td>
						</tr>
					<?php endif; ?>
					</tbody>
				</table>

			</td>
		</tr>
		<?php if ( $canBuildTaxonomyIndex ): ?>
			<tr>
				<td><?php _e( 'Taxonomies', 'ajax-search-for-woocommerce' ); ?></td>
				<td>
					<table class="dgwt-wcas-indexer-table__in">
						<tbody>
						<tr>
							<td><?php _e( 'Start', 'ajax-search-for-woocommerce' ); ?></td>
							<td><?php echo ! empty( $info['start_taxonomies_ts'] ) ? date( 'H:i:s', $info['start_taxonomies_ts'] ) : '-'; ?></td>
						</tr>
						<tr>
							<td><?php _e( 'End', 'ajax-search-for-woocommerce' ); ?></td>
							<td><?php echo ! empty( $info['end_taxonomies_ts'] ) ? date( 'H:i:s', $info['end_taxonomies_ts'] ) : '-'; ?></td>
						</tr>
						<?php if ( ! empty( $info['start_taxonomies_ts'] ) && ! empty( $info['end_taxonomies_ts'] ) ): ?>
							<tr>
								<td><?php _e( 'Time', 'ajax-search-for-woocommerce' ); ?></td>
								<td><?php echo $info['end_taxonomies_ts'] - $info['start_taxonomies_ts'] ?> sec</td>
							</tr>
						<?php endif; ?>
						</tbody>
					</table>

				</td>
			</tr>
		<?php endif; ?>
		</tbody>
	</table>

	<div class="dgwt-wcas-indexer-logs">
		<h4><?php _e( 'Logs', 'ajax-search-for-woocommerce' ); ?></h4>
		<?php foreach ( $logs as $log ) {
			$class = $log['error'] ? 'dgwt-wcas-indexer-log dgwt-wcas-indexer-logs__error' : 'dgwt-wcas-indexer-log';
			echo '<span class="' . $class . '">[' . date( 'Y-m-d H:i:s', $log['time'] ) . '] ' . $log['message'] . '</span>';
		} ?>
	</div>
</div>
