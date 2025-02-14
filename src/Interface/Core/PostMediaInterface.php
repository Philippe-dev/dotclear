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
 * @brief   Post media database handler interface.
 *
 * @since   2.28
 */
interface PostMediaInterface
{
    /**
     * Load working blog on post media instance
     *
     * @param   BlogInterface       $blog       The blog instance
     */
    public function loadFromBlog(BlogInterface $blog): PostMediaInterface;

    /**
     * The post media database table name.
     *
     * @var    string  POST_MEDIA_TABLE_NAME
     */
    public const POST_MEDIA_TABLE_NAME = 'post_media';

    /**
     * The Media database table name.
     *
     * @var     string  MEDIA_TABLE_NAME
     */
    public const MEDIA_TABLE_NAME = 'media';

    /**
     * Open a database table cursor.
     *
     * @return  Cursor  The post media database table cursor
     */
    public function openPostMediaCursor(): Cursor;

    /**
     * Returns media items attached to a blog post.
     *
     * @param   array<string, mixed>   $params     The parameters
     *
     * @return  MetaRecord  The post media.
     */
    public function getPostMedia(array $params = []): MetaRecord;

    /**
     * Attaches a media to a post.
     *
     * @param   int     $post_id    The post identifier
     * @param   int     $media_id   The media identifier
     * @param   string  $link_type  The link type (default: attachment)
     */
    public function addPostMedia(int $post_id, int $media_id, string $link_type = 'attachment'): void;

    /**
     * Detaches a media from a post.
     *
     * @param   int     $post_id    The post identifier
     * @param   int     $media_id   The media identifier
     * @param   string  $link_type  The link type
     */
    public function removePostMedia(int $post_id, int $media_id, ?string $link_type = null): void;
}
