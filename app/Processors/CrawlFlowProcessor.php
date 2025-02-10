<?php

namespace CrawlFlow\Processors;

use WP_Error;
use Ramphor\Rake\ProcessResult;
use Ramphor\Rake\Facades\Logger;
use Ramphor\Rake\Abstracts\Processor;
use Puleeno\Rake\WordPress\Traits\WordPressProcessor;
use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;

class CrawlFlowProcessor extends Processor
{
    const NAME = 'general';

    protected $wordPressType = null;

    use WooCommerceProcessor;
    use WordPressProcessor;

    /**
     * Cleanup the HTML before import to your system
     *
     * @todo Convert styles to strong and em tags.
     * @todo Remove all attribute of the tags
     *
     * @param string $content The post content
     * @return string The output HTML after cleanup
     */
    public function cleanupContentBeforeImport($content)
    {
        if (!$this->cleanContentAttributes) {
            return $content;
        }

        return (string) $content;
    }

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
        $dataType = $this->feedItem->getMeta('dataType');
        if (
            apply_filters_ref_array(
                "pre_the_migration_plugin_{$this->tooth->getId()}_data_type",
                [$dataType, &$this->feedItem]
            ) !== null
        ) {
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

    public function process(): ProcessResult
    {
        if (!($dataType = $this->checkDataType())) {
            return ProcessResult::createErrorResult("The post type [{$dataType}] is invalid");
        }

        Logger::info("Start importing [{$dataType}]: {$this->feedItem->guid}...");

        switch ($dataType) {
            case 'post':
                $this->importPost($this->feedItem->getMeta('postContent'));
                $this->wordPressType = 'post';
                break;
            case 'category':
                $this->importPostCategory(
                    $this->feedItem->getMeta('postCategoryName'),
                    $this->feedItem->getMeta('productCategoryContent'),
                    $this->feedItem->getMeta('productCategorySlug'),
                    $this->feedItem->getMeta('productCategoryShortDescription'),
                    $this->feedItem->getMeta('taxonomy', 'category')
                );
                $this->wordPressType = 'taxonomy';
                break;
            case 'product':
                $this->importProduct($this->feedItem->getMeta('productContent'));
                $this->wordPressType = 'post';
                break;
            case 'page':
                $this->importPage($this->feedItem->pageTitle, $this->feedItem->pageContent);
                $this->wordPressType = 'post';
                break;
            case 'product_category':
                $this->importProductCategory();
                $this->wordPressType = 'taxnomy';
                break;
            default:
                do_action_ref_array(
                    'crawlflow_process_' . $dataType . '_item',
                    [
                        &$this->feedItem,
                        &$this->wordPressType,
                        &$this,
                        &$this->tooth,
                        &$dataType
                    ]
                );
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

            if (!empty($this->feedItem->getMeta('sku'))) {
                $this->importProductSku($this->feedItem->getMeta('sku'), $this->importedId);
            }

            $this->importProductTags(
                empty($this->feedItem->productTags) ? $this->feedItem->tags : $this->feedItem->productTags,
                $this->importedId
            );
        } elseif ($dataType == 'product_category') {
        }

        if ($dataType !== 'product') {
            $this->useFirstImageAsCoverImageWhenNotExists();
        }

        do_action(
            'crawlflow_after_imported',
            $this->importedId,
            $this->feedItem,
            $dataType,
            $this
        );

        $extraActionHook = 'crawlflow_after_import_' . $dataType;
        do_action(
            $extraActionHook,
            $this->importedId,
            $this->feedItem,
            $this
        );

        return ProcessResult::createSuccessResult($this->feedItem->guid, $this->importedId, $dataType);
    }

    protected function useFirstImageAsCoverImageWhenNotExists()
    {
        // Logger::debug( 'Set first image as feature image' );
    }

    public function setWordPressDataType($dataType) {
        $this->wordPressType = $dataType;
    }

    public function getWordPressDataType()
    {
        return $this->wordPressType;
    }
}
