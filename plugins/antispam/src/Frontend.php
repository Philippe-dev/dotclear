<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\antispam;

use Dotclear\App;
use Dotclear\Core\Process;

/**
 * @brief   The module frontend process.
 * @ingroup antispam
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
            'publicBeforeCommentCreate'   => Antispam::isSpam(...),
            'publicBeforeTrackbackCreate' => Antispam::isSpam(...),
            'publicBeforeDocumentV2'      => Antispam::purgeOldSpam(...),
        ]);

        return true;
    }
}
