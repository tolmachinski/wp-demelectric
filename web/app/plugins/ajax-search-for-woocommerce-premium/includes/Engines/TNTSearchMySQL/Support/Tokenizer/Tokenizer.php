<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Support\Tokenizer;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tokenizer implements TokenizerInterface {

	/**
	 * Tokenizer context
	 * Tokenizer may be used in indexer or search process
	 *
	 * @var string
	 */
	private $context = 'search';

	/**
	 * Set context
	 *
	 * @return void
	 */
	public function setContext( $context ) {
		if ( ! in_array( $context, array( 'search', 'indexer' ) ) ) {
			return;
		}

		$this->context = $context;
	}

	public function tokenize( $text, $stopwords = array() ) {
		// Break early if there is nothing to process.
		if ( is_null( $text ) || $text === '' ) {
			return [];
		}

		$chars      = $this->getSpecialChars();
		$text       = mb_strtolower( $text );
		$charsRegex = empty( $chars ) ? '' : '\\' . implode( '\\', $chars );

		$split = preg_split( "/[^\p{L}\p{N}" . $charsRegex . "]+/u", $text, - 1, PREG_SPLIT_NO_EMPTY );

		if ( ! empty( $split ) ) {
			$split = $this->createExtraVariations( $chars, $split );
		}

		// Apply stopwords.
		if ( $this->context === 'indexer' ) {
			$tokens = array_diff( $split, $stopwords );
		} else if ( $this->context === 'search' ) {
			$tokens = $split;
			if ( count( $split ) > 1 ) {
				// Get last word.
				$last = array_pop( $tokens );
				// Remove stopwords from rest of list.
				$tokens = array_diff( $tokens, $stopwords );
				// Add the last word to the list again.
				$tokens[] = $last;
			}
		}

		if ( $this->context === 'search' ) {
			$tokensLimit = apply_filters( 'dgwt/wcas/tokenizer/tokens_limit', 10 );
			// Limit the number of tokens
			$tokens = array_splice( $tokens, 0, $tokensLimit );
		}

		if ( $this->context === 'indexer' ) {
			$tokens = array_map( function ( $token ) {
				return mb_strlen( $token ) > 127 ? mb_substr( $token, 0, 127 ) : $token;
			}, $tokens );
		}

		$tokens = apply_filters( 'dgwt/wcas/tokenizer/tokens', $tokens, $text, $this->context );

		return array_filter( array_unique( $tokens ) );
	}

	/**
	 * Get special chars that should be ignored during tokenization process
	 *
	 * @return array
	 */
	public function getSpecialChars() {
		$chars = array( '-', '_', '.', ',', '/' );
		if ( $this->context === 'search' ) {
			$chars = array();
		}

		return apply_filters( 'dgwt/wcas/tokenizer/special_chars', $chars, $this->context );
	}

	/**
	 * Creates extra variations of words
	 * e.g for phrase "PROD-1999/2000" creates variations:
	 *
	 * prod-1999/2000
	 * prod
	 * 1999/2000
	 * prod1999/2000
	 * prod-1999
	 * 2000
	 * prod-19992000
	 * 1999
	 * 19992000
	 * prod1999
	 * prod19992000
	 *
	 * @param $chars
	 * @param $tokens
	 *
	 * @return array
	 */
	private function createExtraVariations( $chars, $tokens ) {

		if ( ! empty( $chars ) && is_array( $chars ) ) {
			foreach ( $chars as $char ) {
				foreach ( $tokens as $token ) {
					$elements = explode( $char, $token );
					if ( count( $elements ) > 1 ) {

						if ( $this->context === 'indexer' ) {
							$elements[] = str_replace( $char, '', $token ); // Binds by special chars
						}

						$tokens = array_merge( $tokens, $elements );
					}

				}
			}
		}

		return $tokens;
	}
}
