<?php

use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\SearchQuery\AjaxQuery;
use DgoraWcas\Engines\TNTSearchMySQL\Debug\Debugger;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Tokenizer\Tokenizer;

// Exit if accessed directly
if ( ! defined( 'DGWT_WCAS_FILE' ) ) {
	exit;
}

Debugger::wipeLogs( 'product-search-flow' );
Debugger::wipeLogs( 'search-resutls' );

$searchPhrase = ! empty( $_GET['s'] ) ? $_GET['s'] : '';
$lang         = ! empty( $_GET['lang'] ) ? $_GET['lang'] : '';

$toTokenize   = ! empty( $_GET['dgwt-wcas-to-tokenize'] ) ? $_GET['dgwt-wcas-to-tokenize'] : '';
$tokenizerCtx = ! empty( $_GET['dgwt-wcas-debug-tokenizer-ctx'] ) ? $_GET['dgwt-wcas-debug-tokenizer-ctx'] : 'indexer';
?>

<h3>Search flow</h3>
<form action="<?php echo admin_url( 'admin.php' ); ?>" method="get">
	<input type="hidden" name="page" value="dgwt_wcas_debug">
	<?php wp_nonce_field( 'dgwt_wcas_debug_search', '_wpnonce', false ); ?>
	<label for="dgwt-wcas-debug-search"></label>
	<input type="text" class="regular-text" id="dgwt-wcas-debug-search" name="s"
		   value="<?php echo esc_html( $searchPhrase ); ?>" placeholder="search phrase">
	<input type="text" class="small-text" id="dgwt-wcas-debug-search-lang" name="lang"
		   value="<?php echo esc_html( $lang ); ?>" placeholder="lang">
	<button class="button" type="submit">Search</button>
</form>

<hr/>
<h3>Tokenizer</h3>
<form action="<?php echo admin_url( 'admin.php' ); ?>" method="get">
	<input type="hidden" name="page" value="dgwt_wcas_debug">
	<?php wp_nonce_field( 'dgwt_wcas_debug_search', '_wpnonce', false ); ?>
	<label for="dgwt-wcas-debug-tokenizer"></label>
	<input type="text" class="regular-text" id="dgwt-wcas-debug-tokenizer" name="dgwt-wcas-to-tokenize"
		   value="<?php echo esc_html( $toTokenize ); ?>" placeholder="To tokenize"">
	<select name="dgwt-wcas-debug-tokenizer-ctx">
		<option <?php echo $tokenizerCtx === 'search' ? 'selected="selected"' : ''; ?>>search</option>
		<option <?php echo $tokenizerCtx === 'indexer' ? 'selected="selected"' : ''; ?>>indexer</option>
	</select>
	<button class="button" type="submit">Tokenize</button>
</form>

<?php if (
	! empty( $searchPhrase ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_search' )
) {

	define( 'DGWT_SEARCH_START', microtime( true ) );

	$lang = empty( $_GET['lang'] ) ? $_GET['lang'] : '';
	if ( ! Builder::searchableCacheExists( $lang ) ) {
		add_filter( 'dgwt/wcas/tnt/search_cache', '__return_false', PHP_INT_MAX - 5 );
	}

	add_filter( 'dgwt/wcas/tnt/search_results/suggestion/product', function ( $p, $suggestion ) {
		if ( ! empty( $suggestion->score ) ) {
			$p['score'] = $suggestion->score;
		}

		return $p;
	}, 10, 2 );

	$query = new AjaxQuery( true );
	$query->setPhrase( $searchPhrase );

	if ( ! empty( $_GET['lang'] ) ) {
		$query->setLang( $_GET['lang'] );
	}

	$query->searchProducts();
	$query->searchPosts();
	$query->searchTaxonomy();
	Debugger::logSearchResults( $query );


	Debugger::printLogs( 'Search flow', 'product-search-flow' );
	Debugger::printLogs( 'Search resutls', 'search-resutls' );

}
?>

<?php if (
	! empty( $toTokenize ) &&
	! empty( $_REQUEST['_wpnonce'] ) &&
	wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_search' )
) {
	$tokenizer = new Tokenizer();
	$tokenizer->setContext( $tokenizerCtx );

	$stopwords = array();
	if ( $tokenizerCtx === 'search' ) {
		$stopwords = apply_filters( 'dgwt/wcas/search/stopwords', array(), ! empty( $_GET['lang'] ) ? $_GET['lang'] : '' );
	} else if ( $tokenizerCtx === 'indexer' ) {
		$stopwords = apply_filters( 'dgwt/wcas/indexer/searchable/stopwords', array(), 'product', 0, ! empty( $_GET['lang'] ) ? $_GET['lang'] : '' );
	}

	Debugger::log( '<b>Phrase:</b> <pre>' . var_export( $toTokenize, true ) . '</pre>', 'tokenizer' );
	Debugger::log( '<b>Context:</b> <pre>' . var_export( $tokenizerCtx, true ) . '</pre>', 'tokenizer' );
	Debugger::log( '<b>Split by:</b> <pre>' . var_export( $tokenizer->getSpecialChars(), true ) . '</pre>', 'tokenizer' );
	Debugger::log( '<b>Stopwords:</b> <pre>' . var_export( $stopwords, true ) . '</pre>', 'tokenizer' );
	Debugger::log( '<b>Tokens:</b> <pre>' . var_export( $tokenizer->tokenize( $toTokenize, $stopwords ), true ) . '</pre>', 'tokenizer' );

	Debugger::printLogs( 'Tokenizer', 'tokenizer' );

}

Debugger::wipeLogs( 'product-search-flow' );
Debugger::wipeLogs( 'search-resutls' );
Debugger::wipeLogs( 'tokenizer' );
