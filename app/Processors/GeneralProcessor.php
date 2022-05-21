<?php
namespace App\Processors;

use Ramphor\Rake\ProcessResult;
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

    protected function checkPostType()
    {
        $postType = null;
        if (apply_filters('pre_the_migration_plugin_post_type', $postType) !== null) {
            return $postType;
        }

        if ($this->feedItem->title !== '') {
            return 'post';
        }

        if ($this->feedItem->productName !== '') {
            return 'product';
        }

        if ($this->feedItem->pro) {
            return apply_filters('the_migration_plugin_check_post_type', $postType, $this->feedItem);
        }
    }

    public function execute()
    {
        if (!($postType = $this->checkPostType()) || !post_type_exists($postType)) {
            return ProcessResult::createErrorResult("The post type [{$postType}] is invalid");
        }

        switch ($postType) {
            case 'post':
                $this->importPost();
                break;
            case 'product':
                $this->importProduct();
                break;
            case 'page':
                $this->importPage();
                break;
        }

        if (is_wp_error($this->importedId)) {
            return ProcessResult::createErrorResult($this->importedId->get_error_message(), ProcessResult::ERROR_RESULT_TYPE);
        }

        if ($postType === 'post') {
            $this->importPostCategories(
                $this->feedItem->categories,
                true,
                $this->importedId
            );

            $this->importPostTags($this->feedItem->tags, $this->importedId);
        } elseif ($postType === 'product') {
            $this->importProductCategories(
                empty($this->feedItem->productCategories) ? $this->feedItem->categories : $this->feedItem->productCategories,
                true,
                $this->importedId
            );

            $this->importProductTags(
                empty($this->feedItem->productTags) ? $this->feedItem->tags : $this->feedItem->productTags,
                $this->importedId
            );
        }


        if ($postType !== 'product') {
            $this->useFirstImageAsCoverImageWhenNotExists();
        }

        return ProcessResult::createSuccessResult($this->feedItem->guid, $this->importedId, $postType);
    }

    protected function useFirstImageAsCoverImageWhenNotExists()
    {
        // Logger::debug( 'Set first image as feature image' );
    }
}
