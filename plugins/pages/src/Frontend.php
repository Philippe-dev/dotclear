<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\pages;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Process;

/**
 * @brief   The module frontend process.
 * @ingroup pages
 */
class Frontend extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::FRONTEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        App::behavior()->addBehaviors([
            'publicPrependV2' => function (): void {
                // Localized string we find in template
                __('Published on');
                __('This page\'s comments feed');
            },
            'coreBlogBeforeGetPosts' => function (ArrayObject $params): void {
                if (App::url()->getType() === 'search') {
                    // Add page post type for searching
                    if (isset($params['post_type'])) {
                        if (!is_array($params['post_type'])) {
                            // Convert it in array
                            $params['post_type'] = [$params['post_type']];
                        }
                        if (!in_array('page', $params['post_type'])) {
                            // Add page post type
                            $params['post_type'][] = 'page';
                        }
                    } else {
                        // Dont miss default post type (aka post)
                        $params['post_type'] = ['post', 'page'];
                    }
                }
            },
            'initWidgets'        => Widgets::initWidgets(...),
            'initDefaultWidgets' => Widgets::initDefaultWidgets(...),
        ]);

        return true;
    }
}
