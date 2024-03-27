<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SynonymsHandler {
	private $synonyms = array();

	public function __construct() {
	}

	/**
	 * Grouped list of synonyms
	 * @return array
	 */
	public function getSynonyms() {

		if ( ! empty( $this->synonyms ) ) {
			return $this->synonyms;
		}


		$option = DGWT_WCAS()->settings->getOption( 'search_synonyms' );

		$groups   = array();
		$synonyms = array();

		if ( ! empty( $option ) ) {
			$groups = explode( PHP_EOL, $option );
			$groups = array_map( 'trim', $groups );
		}

		if ( ! empty( $groups ) ) {
			foreach ( $groups as $group ) {
				$synonyms[] = $groups = array_map( 'trim', explode( ',', $group ) );
			}
		}

		if ( ! empty( $synonyms ) ) {
			$this->synonyms = $synonyms;
		}

		return $synonyms;

	}

	/**
	 * Apply synonymus to the text
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function applySynonyms( $text ) {

		$synonyms = $this->getSynonyms();

		if ( empty( $synonyms ) ) {
			return $text;
		}

		$subject = mb_strtolower( $text );
		$suffix = '';

		foreach ( $synonyms as $i => $synonymGroup ) {

			foreach ( $synonymGroup as $phrase ) {

				$phrase = mb_strtolower( $phrase );

				$phrase = preg_replace_callback("/([!@#$&()\-\[\]{}\\`.+,\/\"\\'])/", function ($matches) {
					return '\\' . $matches[0];
				}, $phrase);


				if ( ! empty( $phrase ) && preg_match( "/([^a-zA-Z0-9\p{Cyrillic}]|^)$phrase([^a-zA-Z0-9\p{Cyrillic}}]|$)/i", $subject ) ) {

					$suffix  .= ' ' . implode( ' ', $synonymGroup );
					break;
				}
			}
		}

		return $text . $suffix;
	}

}
