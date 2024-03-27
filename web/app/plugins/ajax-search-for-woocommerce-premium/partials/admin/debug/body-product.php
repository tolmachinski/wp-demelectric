<?php

use DgoraWcas\Engines\TNTSearchMySQL\SearchQuery\AjaxQuery;
use DgoraWcas\Engines\TNTSearchMySQL\Debug\Debugger;
use DgoraWcas\Engines\TNTSearchMySQL\Support\Tokenizer\Tokenizer;

// Exit if accessed directly
if ( ! defined( 'DGWT_WCAS_FILE' ) ) {
	exit;
}

$nonceValid = ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'dgwt_wcas_debug_product' );
$productID  = $nonceValid && ! empty( $_GET['product_id'] ) ? $_GET['product_id'] : '';

if ( ! empty( $productID ) ) {
	$p = new \DgoraWcas\Engines\TNTSearchMySQL\Debug\Product( $productID );

	$readableIndexData = $p->getReadableIndexData();
	$wordlist          = $p->getSearchableIndexData();
	$wordlistSQL       = $p->getDataForIndexingBySource();
}

?>


<h3>Product debug</h3>
<form action="<?php echo admin_url( 'admin.php' ); ?>" method="get">
	<input type="hidden" name="page" value="dgwt_wcas_debug">
	<?php wp_nonce_field( 'dgwt_wcas_debug_product', '_wpnonce', false ); ?>
	<input type="text" class="regular-text" id="dgwt-wcas-debug-product" name="product_id"
		   value="<?php echo esc_html( $productID ); ?>" placeholder="Product ID">
	<button class="button" type="submit">Debug</button>
</form>

<?php if ( ! empty( $productID ) && ! $p->product->isValid() ): ?>
	<p>Wrong product ID</p>
<?php endif; ?>

<?php if ( ! empty( $productID ) && $p->product->isValid() ): ?>

	<table class="wc_status_table widefat" cellspacing="0">
		<thead>
		<tr>
			<th colspan="2" data-export-label="Searchable Index"><h3>General</h3></th>
		</tr>
		</thead>
		<tbody>
		<tr>
			<td><b>Can index: </b></td>
			<td><?php echo $p->product->canIndex__premium_only() ? 'yes' : 'no'; ?></td>
		</tr>
		</tbody>
	</table>

	<table class="wc_status_table widefat" cellspacing="0">
		<thead>
		<tr>
			<th colspan="2" data-export-label="Searchable Index"><h3>Readable Index (stored in the database)</h3></th>
		</tr>
		</thead>
		<tbody>

		<?php

		foreach ( $readableIndexData as $key => $data ): ?>
			<tr>
				<td><b><?php echo $key; ?>: </b></td>
				<td><?php echo $data; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<table class="wc_status_table widefat" cellspacing="0">
		<thead>
		<tr>
			<th colspan="2" data-export-label="Searchable Index"><h3>Searchable Index (stored in the database)</h3></th>
		</tr>
		</thead>
		<tbody>

		<tr>
			<td><b>Total terms:</b></td>
			<td><?php echo count( $wordlist ); ?></td>
		</tr>

		<tr>
			<td><b>Wordlist: </b></td>
			<td class="dgwt-wcas-table-wordlist">
				<p>
					<?php foreach ( $wordlist as $term ): ?>
						<?php echo $term . '<br />'; ?>
					<?php endforeach; ?>
				</p>
			</td>
		</tr>
		</tbody>
	</table>

<?php endif; ?>
