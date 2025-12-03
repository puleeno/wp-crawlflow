<?php

namespace CrawlFlow\Processors;

use Rake\Contracts\Processor\ProcessorInterface;

/**
 * WordPress Post Processor
 * Processes extracted data and saves as WordPress posts
 */
class WordPressPostProcessor implements ProcessorInterface
{
    /**
     * Process and save data as WordPress post
     * 
     * @param array $data Extracted data
     * @param array $options Processing options
     * @return int Post ID
     * @throws \RuntimeException If processing fails
     */
    public function process(array $data, array $options = []): int
    {
        $postType = $options['postType'] ?? 'post';
        $postStatus = $options['postStatus'] ?? 'draft';
        $authorId = $options['authorId'] ?? 1;
        $updateIfExists = $options['updateIfExists'] ?? false;

        // Prepare post data
        $postData = [
            'post_title' => $data['title'] ?? '',
            'post_content' => $data['content'] ?? '',
            'post_excerpt' => $data['excerpt'] ?? '',
            'post_status' => $postStatus,
            'post_type' => $postType,
            'post_author' => $authorId,
        ];

        // Add date if available
        if (!empty($data['date'])) {
            $postData['post_date'] = $this->parseDate($data['date']);
        }

        // Check if post already exists
        if ($updateIfExists && !empty($data['url'])) {
            $existingId = $this->findPostByUrl($data['url']);
            if ($existingId) {
                $postData['ID'] = $existingId;
            }
        }

        // Insert or update post
        if (isset($postData['ID'])) {
            $postId = wp_update_post($postData);
        } else {
            $postId = wp_insert_post($postData);
        }

        if (is_wp_error($postId)) {
            throw new \RuntimeException('Failed to save post: ' . $postId->get_error_message());
        }

        if ($postId === 0) {
            throw new \RuntimeException('Failed to save post: wp_insert_post returned 0');
        }

        // Save meta data
        if (!empty($data['url'])) {
            update_post_meta($postId, 'source_url', $data['url']);
        }

        if (!empty($data['author'])) {
            update_post_meta($postId, 'source_author', $data['author']);
        }

        // Handle featured image
        if (!empty($data['image'])) {
            $this->setFeaturedImage($postId, $data['image']);
        }

        // Set categories if provided
        if (!empty($options['categories'])) {
            wp_set_post_categories($postId, $options['categories']);
        }

        // Set tags if extracted
        if (!empty($data['tags']) && is_array($data['tags'])) {
            wp_set_post_tags($postId, $data['tags']);
        }

        return $postId;
    }

    /**
     * Process multiple items in batch
     */
    public function processBatch(array $items, array $options = []): array
    {
        $results = [];

        foreach ($items as $item) {
            try {
                $postId = $this->process($item, $options);
                $results[] = [
                    'success' => true,
                    'post_id' => $postId,
                    'title' => $item['title'] ?? '',
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'title' => $item['title'] ?? '',
                ];
            }
        }

        return $results;
    }

    /**
     * Find existing post by source URL
     */
    private function findPostByUrl(string $url): ?int
    {
        global $wpdb;

        $postId = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'source_url' AND meta_value = %s LIMIT 1",
                $url
            )
        );

        return $postId ? (int)$postId : null;
    }

    /**
     * Parse date from various formats
     */
    private function parseDate(string $date): string
    {
        $timestamp = strtotime($date);
        
        if ($timestamp === false) {
            return current_time('mysql');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Set featured image from URL
     */
    private function setFeaturedImage(int $postId, string $imageUrl): void
    {
        // Check if image URL is valid
        if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return;
        }

        try {
            // Download image
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $tmp = download_url($imageUrl);

            if (is_wp_error($tmp)) {
                return;
            }

            $fileArray = [
                'name' => basename($imageUrl),
                'tmp_name' => $tmp,
            ];

            $attachmentId = media_handle_sideload($fileArray, $postId);

            if (!is_wp_error($attachmentId)) {
                set_post_thumbnail($postId, $attachmentId);
            }

            @unlink($tmp);

        } catch (\Exception $e) {
            // Silently fail featured image setting
        }
    }
}

