<?php
/**
 * @brief Plugin blogroll My module class.
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 *
 * @since 2.27
 */
declare(strict_types=1);

namespace Dotclear\Plugin\blogroll;

use Dotclear\App;
use Dotclear\Module\MyPlugin;

class My extends MyPlugin
{
    protected static function checkCustomContext(int $context): ?bool
    {
        return in_array($context, [self::MANAGE, self::MENU]) ?
            defined('DC_CONTEXT_ADMIN')
            && !is_null(App::blog())
            && App::auth()->check(App::auth()->makePermissions([
                Blogroll::PERMISSION_BLOGROLL,
                App::auth()::PERMISSION_ADMIN,
                App::auth()::PERMISSION_CONTENT_ADMIN,
            ]), App::blog()->id)
            : null;
    }
}
