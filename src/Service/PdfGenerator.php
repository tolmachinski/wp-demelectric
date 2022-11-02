<?php

namespace Demelectric\Service;

use Breakmedia\Ms3Connector\Service\Logger;
use Breakmedia\Ms3Connector\Service\Wordpress\AdapterMeta;
use Breakmedia\Ms3Connector\Service\Wordpress\AdapterPost;
use Breakmedia\Ms3Connector\Service\Wordpress\AdapterWoocommerce;
use BreakmediaPdf\Common\Utils\PdfHelper;

class PdfGenerator
{
    const META_FLAG_NAME = 'pdf-datasheet-generated';

    protected Logger $logger;
    protected PdfHelper $pdfHelper;
    protected AdapterWoocommerce $adapterWoocommerce;
    protected AdapterMeta $adapterMeta;

    public function __construct(
        Logger $logger,
        AdapterWoocommerce $adapterWoocommerce,
        AdapterMeta $adapterMeta
    ) {
        $this->logger = $logger;
        $this->adapterWoocommerce = $adapterWoocommerce;
        $this->adapterMeta = $adapterMeta;
        $this->pdfHelper = new PdfHelper();
    }

    public function generate()
    {
        $this->logger->title('Start PDF generation');
        $updatedProducts = $this->getUpdatedProducts();

        $this->logger->info('Got ' . count($updatedProducts) . ' products for PDF generation');

        $progressBar = $this->logger->getProgressBar(count($updatedProducts));
        $progressBar->start();
        foreach ($updatedProducts as $updatedProduct) {
            try {
                $product = $this->adapterWoocommerce->getProduct($updatedProduct['ID']);
                $this->pdfHelper->createPdf($this->pdfHelper->getPdfPath($product), $product);
                $this->markProduct($updatedProduct);
            } catch (\Exception $e) {
                $this->logger->warn('PDF for product '. $updatedProduct['ID'] . ' not generated: ' . $e->getMessage());
            }
            $progressBar->advance();
        }
        $progressBar->finish();

        $this->logger->title('Complete PDF generation');
    }

    /**
     * @return \WC_Product[]
     * @throws \Breakmedia\Ms3Connector\Exception\WordpressException
     */
    protected function getUpdatedProducts(): array
    {
        $products = $this->adapterWoocommerce->getProductWithMetaSearch([], ['post_modified']);
        $updatedProducts = [];
        foreach ($products as $product) {
            if (empty($product['meta'][self::META_FLAG_NAME]) || $product['meta'][self::META_FLAG_NAME] !== $product['post_modified']) {
                $updatedProducts[] = $product;
            }
        }
        return $updatedProducts;
    }

    protected function markProduct($updatedProduct)
    {
        $this->adapterMeta->updatePostMeta($updatedProduct['ID'], self::META_FLAG_NAME, $updatedProduct['post_modified']);
    }
}
