<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\breadcrumb;

use Dotclear\Interface\Core\BlogSettingsInterface;
use form;

/**
 * @brief   The module backend behaviors.
 * @ingroup breadcrumb
 */
class BackendBehaviors
{
    /**
     * Display breadcrumb fieldset settings.
     *
     * @param   BlogSettingsInterface   $settings   The settings
     */
    public static function adminBlogPreferencesForm(BlogSettingsInterface $settings): void
    {
        echo
        '<div class="fieldset"><h4 id="breadcrumb_params">' . My::name() . '</h4>' .
        '<p><label class="classic">' .
        form::checkbox('breadcrumb_enabled', '1', $settings->breadcrumb->breadcrumb_enabled) .
        __('Enable breadcrumb for this blog') . '</label></p>' .
        '<p class="form-note">' . __('The {{tpl:Breadcrumb [separator=" &amp;rsaquo; "]}} tag should be present (or inserted if not) in the template.') . '</p>' .
        form::checkbox('breadcrumb_alone', '1', $settings->breadcrumb->breadcrumb_alone) .
        __('Do not encapsulate breadcrumb in a &lt;p id="breadcrumb"&gt;...&lt;/p&gt; tag.') . '</label></p>' .
            '</div>';
    }

    /**
     * Save breadcrumb settings.
     *
     * @param   BlogSettingsInterface   $settings   The settings
     */
    public static function adminBeforeBlogSettingsUpdate(BlogSettingsInterface $settings): void
    {
        $settings->breadcrumb->put('breadcrumb_enabled', !empty($_POST['breadcrumb_enabled']), 'boolean');
        $settings->breadcrumb->put('breadcrumb_alone', !empty($_POST['breadcrumb_alone']), 'boolean');
    }
}
