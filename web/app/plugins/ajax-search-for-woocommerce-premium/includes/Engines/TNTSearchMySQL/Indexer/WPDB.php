<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

class WPDB {
	/** @var \wpdb */
	public $db;

	protected static $self;
	protected static $selfSecond;

	/** @var bool */
	private $inTransaction = false;

	public static function get_instance() {
		global $wpdb;

		if ( isset( self::$self ) ) {
			return self::$self;
		}

		self::$self     = new self();
		self::$self->db = $wpdb;

		return self::$self;
	}

	/**
	 * Clear saved queries
	 *
	 * Saving indexer SQL queries consumes a lot of memory.
	 *
	 * @return void
	 */
	private function clear_saved_queries() {
		if ( defined( 'DGWT_WCAS_INDEXER_ALLOW_SAVEQUERIES' ) && DGWT_WCAS_INDEXER_ALLOW_SAVEQUERIES ) {
			return;
		}

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			$this->db->queries = [];
		}
	}

	/**
	 * @throws WPDBException
	 */
	public function delete( $table, $where, $where_format = null ) {
		$result = $this->db->delete( $table, $where, $where_format );
		if ( ! empty( $this->db->last_error ) ) {
			throw new WPDBException( sprintf( 'Database error "%1$s" for query: "%2$s"', $this->db->last_error, trim( preg_replace( '/\s+/', ' ', $this->db->last_query ) ) ) );
		}

		$this->clear_saved_queries();

		return $result;
	}

	/**
	 * @throws WPDBException
	 */
	public function get_results( $query = null, $output = OBJECT ) {
		$result = $this->db->get_results( $query, $output );
		if ( ! empty( $this->db->last_error ) ) {
			throw new WPDBException( sprintf( 'Database error "%1$s" for query: "%2$s"', $this->db->last_error, trim( preg_replace( '/\s+/', ' ', $this->db->last_query ) ) ) );
		}

		$this->clear_saved_queries();

		return $result;
	}

	/**
	 * @throws WPDBException
	 */
	public function get_row( $query = null, $output = OBJECT, $y = 0 ) {
		$result = $this->db->get_row( $query, $output, $y );
		if ( ! empty( $this->db->last_error ) ) {
			throw new WPDBException( sprintf( 'Database error "%1$s" for query: "%2$s"', $this->db->last_error, trim( preg_replace( '/\s+/', ' ', $this->db->last_query ) ) ) );
		}

		$this->clear_saved_queries();

		return $result;
	}

	/**
	 * @throws WPDBException
	 */
	public function get_var( $query = null, $x = 0, $y = 0 ) {
		$result = $this->db->get_var( $query, $x, $y );
		if ( ! empty( $this->db->last_error ) ) {
			throw new WPDBException( sprintf( 'Database error "%1$s" for query: "%2$s"', $this->db->last_error, trim( preg_replace( '/\s+/', ' ', $this->db->last_query ) ) ) );
		}

		$this->clear_saved_queries();

		return $result;
	}

	/**
	 * @throws WPDBException
	 */
	public function insert( $table, $data, $format = null ) {
		$result = $this->db->insert( $table, $data, $format );
		if ( ! empty( $this->db->last_error ) ) {
			throw new WPDBException( sprintf( 'Database error "%1$s" for query: "%2$s"', $this->db->last_error, trim( preg_replace( '/\s+/', ' ', $this->db->last_query ) ) ) );
		}

		$this->clear_saved_queries();

		return $result;
	}

	/**
	 * @throws WPDBException
	 */
	public function query( string $query ) {
		$result = $this->db->query( $query );
		if ( ! empty( $this->db->last_error ) ) {
			throw new WPDBException( sprintf( 'Database error "%1$s" for query: "%2$s"', $this->db->last_error, trim( preg_replace( '/\s+/', ' ', $this->db->last_query ) ) ) );
		}

		$this->clear_saved_queries();

		return $result;
	}

	/**
	 * @throws WPDBException
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$result = $this->db->update( $table, $data, $where, $format, $where_format );
		if ( ! empty( $this->db->last_error ) ) {
			throw new WPDBException( sprintf( 'Database error "%1$s" for query: "%2$s"', $this->db->last_error, trim( preg_replace( '/\s+/', ' ', $this->db->last_query ) ) ) );
		}

		$this->clear_saved_queries();

		return $result;
	}

	public function prepare( $query, ...$args ) {
		return $this->db->prepare( $query, $args );
	}

	/**
	 * Start transaction
	 *
	 * @return void
	 */
	public function start_transaction() {
		if ( ! $this->inTransaction ) {
			$this->db->query( 'START TRANSACTION' );
			$this->inTransaction = true;
		}
	}

	/**
	 * Commit transaction
	 *
	 * @return void
	 */
	public function commit() {
		if ( $this->inTransaction ) {
			$this->db->query( 'COMMIT' );
			$this->inTransaction = false;
		}
	}

	/**
	 * Rollback transaction
	 *
	 * @return void
	 */
	public function rollback() {
		if ( $this->inTransaction ) {
			$this->db->query( 'ROLLBACK' );
			$this->inTransaction = false;
		}
	}
}
