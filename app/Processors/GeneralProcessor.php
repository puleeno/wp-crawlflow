<?php
namespace App\Processors;

use WP_Error;

use Ramphor\Rake\ProcessResult;
use Ramphor\Rake\Facades\Logger;
use Ramphor\Rake\Abstracts\Processor;

use Puleeno\Rake\WordPress\Traits\WordPressProcessor;
use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;

class GeneralProcessor extends Processor
{
    const NAME = 'general';

    use WooCommerceProcessor;
    use WordPressProcessor;

    /**
     * @var \Ramphor\Rake\DataSource\FeedItem
     */
    protected $feedItem;

    /**
     * @var integer|\WP_Error
     */
    protected $importedId;

    protected function checkDataType()
    {
        $dataType = null;
        if (apply_filters('pre_the_migration_plugin_data_type', $dataType) !== null) {
            return $dataType;
        }

        if ($this->feedItem->title) {
            $dataType = 'post';
        } elseif ($this->feedItem->productName) {
            $dataType = 'product';
        } elseif ($this->feedItem->productCategoryName) {
            $dataType = 'product_category';
        } elseif ($this->feedItem->pageTitle) {
            $dataType = 'page';
        }

        return apply_filters('the_migration_plugin_check_data_type', $dataType, $this->feedItem);
    }

    public function execute()
    {
        if (!($dataType = $this->checkDataType())) {
            return ProcessResult::createErrorResult("The post type [{$dataType}] is invalid");
        }

        Logger::info("Start importing [{$dataType}]: {$this->feedItem->guid}...");

        switch ($dataType) {
            case 'post':
                $this->importPost();
                break;
            case 'product':
                $this->importProduct();
                break;
            case 'page':
                $this->importPage();
                break;
            case 'product_category':
                $this->importProductCategory();
                break;
            default:
                $this->importedId = new WP_Error(-1, 'The data type is not imported', $dataType);
                break;
        }

        if (is_wp_error($this->importedId)) {
            Logger::debug($this->importedId->get_error_message());
            return ProcessResult::createErrorResult(
                $this->importedId->get_error_message(),
                ProcessResult::ERROR_RESULT_TYPE
            );
        }

        if ($dataType === 'post') {
            $this->importPostCategories(
                $this->feedItem->categories,
                true,
                $this->importedId
            );

            $this->importPostTags($this->feedItem->tags, $this->importedId);
        } elseif ($dataType === 'product') {
            $this->importProductCategories(
                empty($this->feedItem->productCategories) ? $this->feedItem->categories : $this->feedItem->productCategories,
                true,
                $this->importedId
            );

            $this->importProductTags(
                empty($this->feedItem->productTags) ? $this->feedItem->tags : $this->feedItem->productTags,
                $this->importedId
            );
        } elseif ($dataType == 'product_category') {
        }

        if (post_type_exists($dataType)) {
            $this->importSeo();
        } elseif (taxonomy_exists($dataType)) {
            $this->importTermSeo();
        }

        if ($dataType !== 'product') {
            $this->useFirstImageAsCoverImageWhenNotExists();
        }

        return ProcessResult::createSuccessResult($this->feedItem->guid, $this->importedId, $dataType);
    }

    protected function useFirstImageAsCoverImageWhenNotExists()
    {
        // Logger::debug( 'Set first image as feature image' );
    }
}
