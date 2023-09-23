<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\blogroll;

use Dotclear\App;
use Dotclear\Plugin\widgets\WidgetsStack;
use Dotclear\Plugin\widgets\Widgets as dcWidgets;

/**
 * @brief   The module widgets.
 * @ingroup blogroll
 */
class Widgets
{
    /**
     * Initializes the blogroll widget.
     *
     * @param   WidgetsStack    $widgets    The widgets
     */
    public static function initWidgets(WidgetsStack $widgets): void
    {
        $blogroll  = new Blogroll(App::blog());
        $hierarchy = $blogroll->getLinksHierarchy($blogroll->getLinks());

        $hierarchy_cat    = array_keys($hierarchy);
        $categories_combo = [__('All categories') => ''];
        foreach ($hierarchy_cat as $category) {
            if ($category) {
                $categories_combo[$category] = $category;
            }
        }

        $widgets
            ->create('links', My::name(), FrontendTemplate::linksWidget(...), null, 'Blogroll list')
            ->addTitle(__('Links'))
            ->setting('category', __('Category'), '', 'combo', $categories_combo)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }

    /**
     * Add blogroll widget to default set.
     *
     * @param   WidgetsStack                   $widgets            The widgets
     * @param   array<string, WidgetsStack>    $default_widgets    The default widgets
     */
    public static function initDefaultWidgets(WidgetsStack $widgets, array $default_widgets): void
    {
        $default_widgets[dcWidgets::WIDGETS_EXTRA]->append($widgets->links);
    }
}
