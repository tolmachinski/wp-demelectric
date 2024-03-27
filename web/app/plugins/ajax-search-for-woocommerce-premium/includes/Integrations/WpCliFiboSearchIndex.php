<?php

namespace DgoraWcas\Integrations;

use DgoraWcas\Engines\TNTSearchMySQL\Config;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Searchable\Indexer as IndexerS;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Readable\Indexer as IndexerR;
use WP_CLI;

/**
 * Commands for maintaining the index.
 */
class WpCliFiboSearchIndex {
	/**
	 * Build index.
	 *
	 * ## OPTIONS
	 * [--show-logs]
	 * : Show indexer logs.
	 *
	 * [--hide-progress]
	 * : Hide progress bar.
	 *
	 * [--disable-parallel]
	 * : Disable parallel index build.
	 *
	 */
	public function build( $args, $assocArgs ) {
		global $fibosearch_wp_cli_indexer_progress, $fibosearch_wp_cli_indexer_progress_value_before;

		$showLogs        = (bool) WP_CLI\Utils\get_flag_value( $assocArgs, 'show-logs', false );
		$hideProgress    = (bool) WP_CLI\Utils\get_flag_value( $assocArgs, 'hide-progress', false );
		$disablePallarel = (bool) WP_CLI\Utils\get_flag_value( $assocArgs, 'disable-parallel', false );

		add_filter( 'dgwt/wcas/tnt/indexer_mode', function ( $mode ) {
			return 'direct';
		} );

		if ( $disablePallarel && ! defined( 'DGWT_WCAS_PARALLEL_INDEX_BUILD' ) ) {
			define( 'DGWT_WCAS_PARALLEL_INDEX_BUILD', false );
		}

		WP_CLI::log( 'Building the index ... This may take several minutes.' );

		if ( ! $hideProgress ) {
			add_action( 'dgwt/wcas/readable_index/bg_processing/before_task', function () {
				$this->indexerProgress();
			}, PHP_INT_MAX - 10 );

			add_action( 'dgwt/wcas/searchable_index/bg_processing/before_task', function () {
				$this->indexerProgress();
			}, PHP_INT_MAX - 10 );

			add_action( 'dgwt/wcas/taxonomy_index/bg_processing/before_task', function () {
				$this->indexerProgress();
			}, PHP_INT_MAX - 10 );

			add_action( 'dgwt/wcas/variation_index/bg_processing/before_task', function () {
				$this->indexerProgress();
			}, PHP_INT_MAX - 10 );

			$fibosearch_wp_cli_indexer_progress_value_before = 0;
			$fibosearch_wp_cli_indexer_progress              = WP_CLI\Utils\make_progress_bar( 'Indexing:', 100 );

			$fibosearch_wp_cli_indexer_progress->tick( 0 );
		}

		Builder::buildIndex( false );

		if ( ! $hideProgress ) {
			$fibosearch_wp_cli_indexer_progress->finish();
		}

		if ( $showLogs ) {
			$this->showLogs( Config::getIndexRole() );
		}

		$status = Builder::getInfo( 'status', Config::getIndexRole() );

		if ( $status === 'completed' ) {
			WP_CLI::success( 'Indexing completed.' );
		} elseif ( $status === 'error' ) {
			WP_CLI::error( 'Indexing failed due to an error.' );
		} else {
			WP_CLI::error( sprintf( 'Unknown error. Indexing status: %s', $status ) );
		}
	}

	/**
	 * Update the indicated posts in the index.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : One or more IDs of posts to update in index (comma-separated).
	 *
	 * [--hide-progress]
	 * : Hide progress bar.
	 */
	public function update( $args, $assocArgs ) {
		$this->actionInIndex( 'update', $args, $assocArgs );
	}

	/**
	 * Delete the indicated posts in the index.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : One or more IDs of posts to delete in index (comma-separated).
	 *
	 * [--hide-progress]
	 * : Hide progress bar.
	 */
	public function delete( $args, $assocArgs ) {
		$this->actionInIndex( 'delete', $args, $assocArgs );
	}

	/**
	 * Show indexer logs.
	 *
	 * ## OPTIONS
	 *
	 * [--index-role=<role>]
	 * : Index role: 'main' or 'tmp' (if parallel index building enabled). Default: 'main'.
	 *
	 * @subcommand show-logs
	 */
	public function showIndexerLogs( $args, $assocArgs ) {
		$this->showLogs( $this->getIndexRole( $assocArgs ) );
	}

	/**
	 * Get indexer info value or values.
	 *
	 * ## OPTIONS
	 *
	 * [<key>]
	 * : Indexer info key.
	 * ---
	 * options:
	 *   - build_id
	 *   - status
	 *   - plugin_version
	 *   - start_ts
	 *   - end_ts
	 *   - total_products_for_indexing
	 *   - total_non_products_for_indexing
	 * ---
	 *
	 * [--index-role=<role>]
	 * : Index role: 'main' or 'tmp' (if parallel index building enabled). Default: 'main'.
	 *
	 * @subcommand get-info
	 */
	public function getInfo( $args, $assocArgs ) {
		list( $key ) = $args;

		if ( ! empty( $key ) ) {
			$value = Builder::getInfo( $key, $this->getIndexRole( $assocArgs ) );
			WP_CLI::print_value( $value );
		} else {
			$infoKeys = array(
				'build_id',
				'status',
				'plugin_version',
				'start_ts',
				'end_ts',
				'total_products_for_indexing',
				'total_non_products_for_indexing',
			);
			$items    = array();
			foreach ( $infoKeys as $infoKey ) {
				$items[] = array(
					'key'   => $infoKey,
					'value' => Builder::getInfo( $infoKey, $this->getIndexRole( $assocArgs ) )
				);
			}
			WP_CLI\Utils\format_items( 'table', $items, array( 'key', 'value' ) );
		}
	}

	/**
	 * Update or delete the indicated posts in the index.
	 */
	private function actionInIndex( $action, $args, $assocArgs ) {
		$hideProgress = (bool) WP_CLI\Utils\get_flag_value( $assocArgs, 'hide-progress', false );

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'The ID list is empty.' );
		}

		$ids     = preg_replace( '/\s+/', '', $args[0] );
		$ids     = explode( ',', $ids );
		$postIDs = array_filter( $ids, 'ctype_digit' );

		if ( Builder::getInfo( 'status' ) !== 'completed' ) {
			WP_CLI::error( 'The index must be properly built to be able to update it.' );
		}

		$indexerR = new IndexerR;
		// We always update the product in the "main" index.
		$indexerS = new IndexerS( array( 'index_role' => 'main' ) );

		if ( ! $hideProgress ) {
			$label    = $action === 'update' ? 'Indexing' : 'Deleting';
			$progress = WP_CLI\Utils\make_progress_bar( $label . ':', count( $postIDs ) );
		}

		foreach ( $postIDs as $postID ) {
			if ( $action === 'update' ) {
				$indexerR->update( $postID, true );
				$indexerS->update( $postID );
			} else if ( $action === 'delete' ) {
				$indexerR->delete( $postID );
				$indexerS->delete( $postID );
			}
			if ( ! $hideProgress ) {
				$progress->tick();
			} else {
				$label = $action === 'update' ? 'Indexed' : 'Deleted';
				WP_CLI::log( sprintf( $label . ' post %d.', $postID ) );
			}
		}

		if ( ! $hideProgress ) {
			$progress->finish();
		}
	}

	private function showLogs( $indexRole = 'main' ) {
		$logs = Builder::getLogs( $indexRole );
		foreach ( $logs as $log ) {
			if ( $log['error'] ) {
				WP_CLI::error( '[' . date( 'Y-m-d H:i:s', $log['time'] ) . '] ' . $log['message'], false );
			} else {
				WP_CLI::log( '[' . date( 'Y-m-d H:i:s', $log['time'] ) . '] ' . $log['message'] );
			}
		}
	}

	private function indexerProgress() {
		global $fibosearch_wp_cli_indexer_progress, $fibosearch_wp_cli_indexer_progress_value_before;

		$progress = ceil( Builder::getProgressBarValue() );
		if ( $progress - $fibosearch_wp_cli_indexer_progress_value_before > 0 ) {
			$fibosearch_wp_cli_indexer_progress->tick( $progress - $fibosearch_wp_cli_indexer_progress_value_before );
			$fibosearch_wp_cli_indexer_progress_value_before = $progress;
		}
	}

	private function getIndexRole( $assocArgs ) {
		$indexRole = 'main';

		if ( Config::isParallelBuildingEnabled() ) {
			$assocArgs = wp_parse_args(
				$assocArgs,
				array(
					'index-role' => 'main',
				)
			);
			if ( in_array( $assocArgs['index-role'], array( 'main', 'tmp' ) ) ) {
				$indexRole = $assocArgs['index-role'];
			}
		}

		return $indexRole;
	}
}
