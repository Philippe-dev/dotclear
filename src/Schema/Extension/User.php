<?php
/**
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Schema\Extension;

use Dotclear\Database\MetaRecord;

/**
 * @brief Dotclear dates Record helpers.
 *
 * This class adds new methods to database dates results.
 * You can call them on every record comming from Auth::checkUser and
 * Users::getUsers.
 *
 * @warning You should not give the first argument (usualy $rs) of every described
 * function.
 */
class User
{
    /**
     * Returns a user option.
     *
     * @param      MetaRecord   $rs         Invisible parameter
     * @param      string       $name       The name of option
     *
     * @return     mixed
     */
    public static function option(MetaRecord $rs, string $name)
    {
        $options = self::options($rs);

        if (isset($options[$name])) {
            return $options[$name];
        }
    }

    /**
     * Returns all user options.
     *
     * @param      MetaRecord   $rs       Invisible parameter
     *
     * @return     array<string, mixed>
     */
    public static function options(MetaRecord $rs): array
    {
        $options = @unserialize((string) $rs->user_options);
        if (is_array($options)) {
            return $options;
        }

        return [];
    }

    /**
     * Converts this Record to a {@link StaticRecord} instance.
     *
     * @param      MetaRecord   $rs       Invisible parameter
     *
     * @return     MetaRecord  The extent static record.
     */
    public static function toExtStatic(MetaRecord $rs): MetaRecord
    {
        $rs->toStatic();

        return $rs;
    }
}
