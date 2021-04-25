<?php
namespace App;

use App\Core\Task;

class Tasks
{
    protected $tasks = array();

    public function __construct()
    {
        $this->load_tasks();
    }

    public function load_tasks()
    {
        $raw_tasks = array(
            array(
                'id' => 'your_task_id',
                'format' => 'html',
                'type' => 'url',
                'source_cms' => 'opencart',
                'data_rules' => array(
                    'title' => array(
                        'type'    => 'xpath',
                        'pattern' => '.product-view h1.title-product, #blog-info h1.blog-title',
                    ),
                    'seo_title' => array(
                        'type'    => 'xpath',
                        'pattern' => 'title',
                        'get'     => 'text',
                    ),
                    'seo_description' => array(
                        'type'      => 'xpath',
                        'pattern'   => 'meta[name="description"]',
                        'get'       => 'attribute',
                        'attribute' => 'content',
                    ),
                    'product_content' => array(
                        'type'    => 'xpath',
                        'pattern' => '#tab-description',
                        'get'     => 'innerHtml',
                    ),
                    'product_attributes' => array(
                        'type'    => 'xpath',
                        'pattern' => '#tab-specification',
                        'get'     => 'innerHtml',
                    ),
                    'product_price' => array(
                        'type'      => 'regex',
                        'pattern'   => '/\"price\":\s?\"([^\"]+)/',
                        'group'     => 1,
                        'callbacks' => array(
                            'str_replace' => array( array( '.' ), '', '%%argument%%' ),
                        ),
                    ),
                    'product_metas' => array(
                        'type'    => 'xpath',
                        'pattern' => '.product-view > ul.list-unstyled li',
                        'get'     => 'innerHtml',
                        'return'  => 'array',
                    ),
                    'categories' => array(
                        'type'    => 'xpath',
                        'pattern' => '.breadcrumbs .breadcrumb-links li a, #page .breadcrumb li a, .breadcrumbs .breadcrumb-links li a',
                        'get'     => 'text',
                        'return'  => 'array',
                    ),
                    'coverImage' => array(
                        'type'      => 'xpath',
                        'pattern'   => 'meta[property="og:image"]',
                        'get'       => 'attribute',
                        'attribute' => 'content',
                    ),
                    'galleryImages' => array(
                        'type'      => 'xpath',
                        'pattern'   => '#image-additional-carousel a',
                        'get'       => 'attribute',
                        'attribute' => 'href',
                        'return'    => 'array',
                    )
                ),
                'sources' => array(
                    array(
                        'type' => 'sitemap',
                        'url' => 'https://the-opencart-site/sitemap.xml',
                    )
                )
            )
        );
        $raw_tasks = apply_filters('migration_prepare_tasks', $raw_tasks);

        foreach ($raw_tasks as $index => $raw_task) {
            $raw_task = wp_parse_args($raw_task, array(
                'id' => '',
                'format' => '',
                'type' => '',
                'data_rules' => array(),
                'sources' => array(),
            ));

            if (empty($raw_task['id']) || empty($raw_task['format'])) {
                error_log(sprintf(
                    __('Task #%d is invalid [%s]', 'rake-wordpress-migration-example'),
                    $index,
                    print_r($raw_tasks[$index], true)
                ));
                continue;
            }

            $task = new Task($raw_task['id'], $raw_task['format']);
            if (trim($raw_task['type']) != false) {
                $task->set_type($raw_task['type']);
            }
            if (trim($raw_task['source_cms']) != false) {
                $task->set_cms_name($raw_task['source_cms']);
            }

            $task->set_data_rules($raw_task['data_rules']);
            $task->set_sources($raw_task['sources']);

            if ($task->validate()) {
                array_push($this->tasks, $task);
            } else {
                error_log(sprintf(
                    __('Task "%s" is invalid configurations', 'rake-wordpress-migration-example'),
                    $raw_task['id']
                ));
            }
        }
    }

    public function get_available_tasks()
    {
        return $this->tasks;
    }
}
