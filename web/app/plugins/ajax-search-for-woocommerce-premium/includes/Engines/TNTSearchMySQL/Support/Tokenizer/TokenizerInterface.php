<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Support\Tokenizer;

interface TokenizerInterface {
	/**
	 * @param string $text
	 * @param array $stopwords
	 *
	 * @return array
	 */
	public function tokenize( $text, $stopwords );

	/**
	 * @param string $context
	 *
	 * @return void
	 */
	public function setContext( $context );
}
