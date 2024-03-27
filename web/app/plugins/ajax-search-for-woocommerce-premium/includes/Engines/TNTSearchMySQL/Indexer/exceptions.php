<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

use Throwable;

/**
 * Frames all Indexer exceptions ("$e instanceof DgoraWcas\Engines\TNTSearchMySQL\Indexer\Exception").
 */
abstract class Exception extends \Exception {
	/** @var string[] */
	private $errors = [];

	final public function __construct( string $message = '', int $code = 0, Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}

	public static function create( Throwable $previous = null ): self {
		return new static( '', 0, $previous );
	}

	public function withMessage( string $message ): self {
		$this->message = $message;

		return $this;
	}

	public function withCode( int $code ): self {
		$this->code = $code;

		return $this;
	}

	public function withErrors( array $errors ): self {
		$this->errors = $errors;

		return $this;
	}

	public function getErrors(): array {
		return $this->errors;
	}
}

class WPDBException extends Exception {
}
