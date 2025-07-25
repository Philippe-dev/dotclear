<?php

/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Plugin\antispam;

use Dotclear\App;
use Dotclear\Core\Process;
use Dotclear\Database\Structure;

/**
 * @brief   The module install process.
 * @ingroup antispam
 */
class Install extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        /* Database schema
        -------------------------------------------------------- */
        $schema = new Structure(App::con(), App::con()->prefix());

        $schema->{Antispam::SPAMRULE_TABLE_NAME}    // @phpstan-ignore-line (weird usage of __call to set field in Table)
            ->rule_id('bigint', 0, false)
            ->blog_id('varchar', 32, true)
            ->rule_type('varchar', 16, false, "'word'")
            ->rule_content('varchar', 128, false)

            ->primary('pk_spamrule', 'rule_id')

            ->index('idx_spamrule_blog_id', 'btree', 'blog_id')
            ->reference('fk_spamrule_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade')
        ;

        if ($schema->driver() === 'pgsql') {
            $schema->{Antispam::SPAMRULE_TABLE_NAME}->index('idx_spamrule_blog_id_null', 'btree', '(blog_id IS NULL)');
        }

        // Schema installation
        (new Structure(App::con(), App::con()->prefix()))->synchronize($schema);

        // Creating default wordslist
        if (App::version()->getVersion(My::id()) === '') {
            (new Filters\Words())->defaultWordsList();
        }

        My::settings()->put('moderate_only_spam', false, App::blogWorkspace()::NS_BOOL, 'Moderate only spams', false, true);
        My::settings()->put('antispam_moderation_ttl', 7, App::blogWorkspace()::NS_INT, 'Antispam Moderation TTL (days)', false, true);

        return true;
    }
}
