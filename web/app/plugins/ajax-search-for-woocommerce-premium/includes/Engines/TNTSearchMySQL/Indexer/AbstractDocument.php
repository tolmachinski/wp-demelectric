<?php

namespace DgoraWcas\Engines\TNTSearchMySQL\Indexer;

use DgoraWcas\Multilingual;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractDocument {
	private $lang = '';
	private $postType = '';
	private $ID = 0;
	/**
	 * @var int|array
	 */
	private $data;
	private $config;
	/**
	 * @var array
	 */
	protected $dataToIndex;

	public function __construct( $data, $config = [] ) {
		if ( is_numeric( $data ) ) {
			$data = [ 'ID' => intval( $data ) ];
		}

		$this->data   = $data;
		$this->config = $config;
		$this->setID();
		$this->setPostType();
		$this->setLang();

		$this->setDataToIndex( $this->getData() );
	}

	private function setID() {
		if ( ! empty( $this->data['ID'] ) ) {
			$this->ID = (int) $this->data['ID'];
			unset( $this->data['ID'] );
		}
	}

	private function setLang() {
		if ( ! empty( $this->data['lang'] ) ) {
			$this->lang = $this->data['lang'];
			unset( $this->data['lang'] );
		} else {
			$this->lang = Multilingual::isMultilingual() ? Multilingual::getPostLang( $this->getID(), $this->getPostType() ) : '';
		}
	}

	private function setPostType() {
		if ( ! empty( $this->data['post_type'] ) ) {
			$this->postType = $this->data['post_type'];
			unset( $this->data['post_type'] );
		} else {
			$this->postType = get_post_type( $this->getID() );
		}
	}

	/**
	 * @return int
	 */
	public function getID() {
		return $this->ID;
	}

	/**
	 * @return string
	 */
	public function getLang() {
		return $this->lang;
	}

	/**
	 * @return string
	 */
	public function getPostType() {
		return $this->postType;
	}

	/**
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * @return array
	 */
	public function getDataToIndex() {
		return $this->dataToIndex;
	}

	protected function setDataToIndex( $data ) {
		$this->dataToIndex = $data;
	}

	/**
	 * @return array
	 */
	public function getConfig() {
		return $this->config;
	}

	/**
	 * Get table name
	 *
	 * @param string $type | searchable_wordlist
	 *                     | searchable_doclist
	 *                     | searchable_info
	 *                     | searchable_cache
	 *                     | vendors
	 *                     | variations
	 *                     | taxonomy
	 *                     | readable
	 *
	 * @return string
	 */
	public function getTableName( $type ) {
		return Utils::getTableName( $type, $this->getLang(), $this->getPostType() );
	}

	/**
	 * Save the document data to the index
	 *
	 * @return void
	 * @throws WPDBException
	 */
	public function save() {
		$this->prepareDataToIndex();
		$this->saveDataToIndex();
	}

	/**
	 * @throws WPDBException
	 */
	public function update() {
		$this->delete();
		$this->save();
	}

	abstract public function delete();

	/**
	 * Prepare document data for indexing
	 *
	 * @return void
	 */
	abstract public function prepareDataToIndex();

	/**
	 * Proper operation of saving a document to the index
	 *
	 * @return void
	 * @throws WPDBException
	 */
	abstract protected function saveDataToIndex();
}
