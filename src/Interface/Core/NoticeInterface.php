<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Interface\Core;

use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;

/**
 * @brief   Core notice handler interface.
 *
 * @since   2.28
 */
interface NoticeInterface
{
    /**
     * The Notice database table name.
     *
     * @var     string  NOTICE_TABLE_NAME
     */
    public const NOTICE_TABLE_NAME = 'notice';

    /**
     * Open a database table cursor.
     *
     * @return  Cursor  The notice database table cursor
     */
    public function openNoticeCursor(): Cursor;

    /**
     * Gets the notices.
     *
     * @param      array<string, mixed>     $params      The parameters
     * @param      bool                     $count_only  The count only
     *
     * @return     MetaRecord  The notices.
     */
    public function getNotices(array $params = [], bool $count_only = false): MetaRecord;

    /**
     * Adds a notice.
     *
     * @param      Cursor  $cur    The Cursor
     *
     * @return     int     The notice id
     */
    public function addNotice(Cursor $cur): int;

    /**
     * Delete a notice.
     *
     * @param   int     $id     The notice ID
     */
    public function delNotice(int $id): void;

    /**
     * Delete session notices.
     */
    public function delSessionNotices(): void;

    /**
     * Delete notice(s)
     *
     * @deprecated since 2.28, use self::delNotice() or self::delSesionNotices() instead
     *
     * @param      int|null  $id     The identifier
     * @param      bool      $all    All
     */
    public function delNotices(?int $id, bool $all = false): void;
}
