<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\importExport;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Combos;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Crypt;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Text;
use Dotclear\Interface\Core\ConnectionInterface;
use Exception;
use Dotclear\Plugin\blogroll\Blogroll;
use form;

/**
 * @brief   The WP import module handler.
 * @ingroup importExport
 */
class ModuleImportWp extends Module
{
    protected ConnectionInterface $con;
    protected string $prefix;
    protected string $blog_id;

    protected ?string $action = null;
    protected int $step       = 1;

    protected int $post_offset = 0;
    protected int $post_limit  = 20;
    protected int $post_count  = 0;

    /**
     * @var array<string, bool>
     */
    protected array $has_table = [];

    /**
     * @var array<string, mixed>
     */
    protected array $vars;

    /**
     * @var array<string, mixed>
     */
    protected array $base_vars = [
        'db_host'            => '',
        'db_name'            => '',
        'db_user'            => '',
        'db_pwd'             => '',
        'db_prefix'          => 'wp_',
        'ignore_first_cat'   => 1,
        'cat_import'         => 1,
        'cat_as_tags'        => '',
        'cat_tags_prefix'    => 'cat: ',
        'post_limit'         => 20,
        'post_formater'      => 'xhtml',
        'comment_formater'   => 'xhtml',
        'user_ids'           => [],
        'cat_ids'            => [],
        'permalink_template' => 'p=%post_id%',
        'permalink_tags'     => [
            '%year%',
            '%monthnum%',
            '%day%',
            '%hour%',
            '%minute%',
            '%second%',
            '%postname%',
            '%post_id%',
            '%category%',
            '%author%',
        ],
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $formaters;

    /**
     * Sets the module information.
     */
    protected function setInfo(): void
    {
        $this->type        = 'import';
        $this->name        = __('WordPress import');
        $this->description = __('Import a WordPress installation into your current blog.');
    }

    public function init(): void
    {
        $this->con     = App::con();
        $this->prefix  = App::con()->prefix();
        $this->blog_id = App::blog()->id();

        if (!isset($_SESSION['wp_import_vars'])) {
            $_SESSION['wp_import_vars'] = $this->base_vars;
        }
        $this->vars = &$_SESSION['wp_import_vars'];

        if ($this->vars['post_limit'] > 0) {
            $this->post_limit = $this->vars['post_limit'];
        }

        $this->formaters = Combos::getFormatersCombo();
    }

    public function resetVars(): void
    {
        $this->vars = $this->base_vars;
        unset($_SESSION['wp_import_vars']);
    }

    public function process(string $do): void
    {
        $this->action = $do;
    }

    /**
     * Processes the import/export with GUI feedback.
     *
     * We handle process in another way to always display something to user
     *
     * @param   string  $do     action
     */
    protected function guiprocess(string $do): void
    {
        switch ($do) {
            case 'step1':
                $this->vars['db_host']          = $_POST['db_host'];
                $this->vars['db_name']          = $_POST['db_name'];
                $this->vars['db_user']          = $_POST['db_user'];
                $this->vars['db_pwd']           = $_POST['db_pwd'];
                $this->vars['db_prefix']        = $_POST['db_prefix'];
                $this->vars['ignore_first_cat'] = isset($_POST['ignore_first_cat']);
                $this->vars['cat_import']       = isset($_POST['cat_import']);
                $this->vars['cat_as_tags']      = isset($_POST['cat_as_tags']);
                $this->vars['cat_tags_prefix']  = $_POST['cat_tags_prefix'];
                $this->vars['post_limit']       = abs((int) $_POST['post_limit']) > 0 ? $_POST['post_limit'] : 0;
                $this->vars['post_formater']    = isset($this->formaters[$_POST['post_formater']]) ? $_POST['post_formater'] : 'xhtml';
                $this->vars['comment_formater'] = isset($this->formaters[$_POST['comment_formater']]) ? $_POST['comment_formater'] : 'xhtml';
                $db                             = $this->db();
                $db->close();
                $this->step = 2;
                echo $this->progressBar(1);

                break;
            case 'step2':
                $this->step = 2;
                $this->importUsers();
                $this->step = 3;
                echo $this->progressBar(3);

                break;
            case 'step3':
                $this->step = 3;
                $this->importCategories();
                if (App::plugins()->moduleExists('blogroll')) {
                    $this->step = 4;
                    echo $this->progressBar(5);
                } else {
                    $this->step = 5;
                    echo $this->progressBar(7);
                }

                break;
            case 'step4':
                $this->step = 4;
                $this->importLinks();
                $this->step = 5;
                echo $this->progressBar(7);

                break;
            case 'step5':
                $this->step        = 5;
                $this->post_offset = !empty($_REQUEST['offset']) ? abs((int) $_REQUEST['offset']) : 0;
                $percent           = 0;
                if ($this->importPosts($percent) === -1) {
                    Http::redirect($this->getURL() . '&do=ok');
                } else {
                    echo $this->progressBar(ceil($percent * 0.93) + 7);
                }

                break;
            case 'ok':
                $this->resetVars();
                App::blog()->triggerBlog();
                $this->step = 6;
                echo $this->progressBar(100);

                break;
        }
    }

    public function gui(): void
    {
        try {
            $this->guiprocess((string) $this->action);
        } catch (Exception $e) {
            $this->error($e);
        }

        switch ($this->step) {
            case 1:
                echo
                '<p>' . sprintf(
                    __('This will import your WordPress content as new content in the current blog: %s.'),
                    '<strong>' . Html::escapeHTML(App::blog()->name()) . '</strong>'
                ) . '</p>' .
                '<p class="warning">' . __('Please note that this process ' .
                    'will empty your categories, blogroll, entries and comments on the current blog.') . '</p>';

                printf(
                    $this->imForm(1, __('General information'), __('Import my blog now')),
                    '<p>' . __('We first need some information about your old WordPress installation.') . '</p>' .
                    '<p><label for="db_host">' . __('Database Host Name:') . '</label> ' .
                    form::field('db_host', 30, 255, Html::escapeHTML($this->vars['db_host'])) . '</p>' .
                    '<p><label for="db_name">' . __('Database Name:', Html::escapeHTML($this->vars['db_name'])) . '</label> ' .
                    form::field('db_name', 30, 255, Html::escapeHTML($this->vars['db_name'])) . '</p>' .
                    '<p><label for="db_user">' . __('Database User Name:') . '</label> ' .
                    form::field('db_user', 30, 255, Html::escapeHTML($this->vars['db_user'])) . '</p>' .
                    '<p><label for="db_pwd">' . __('Database Password:') . '</label> ' .
                    form::password('db_pwd', 30, 255) . '</p>' .
                    '<p><label for="db_prefix">' . __('Database Tables Prefix:') . '</label> ' .
                    form::field('db_prefix', 30, 255, Html::escapeHTML($this->vars['db_prefix'])) . '</p>' .

                    '<h3 class="vertical-separator">' . __('Entries import options') . '</h3>' .
                    '<div class="two-cols">' .

                    '<div class="col">' .
                    '<p>' . __('WordPress and Dotclear\'s handling of categories are quite different. ' .
                        'You can assign several categories to a single post in WordPress. In the Dotclear world, ' .
                        'we see it more like "One category, several tags." Therefore Dotclear can only import one ' .
                        'category per post and will chose the lowest numbered one. If you want to keep a trace of ' .
                        'every category, you can import them as tags, with an optional prefix.') . '</p>' .
                    '<p>' . __('On the other hand, in WordPress, a post can not be uncategorized, and a ' .
                        'default installation has a first category labelised <i>"Uncategorized"</i>.' .
                        'If you did not change that category, you can just ignore it while ' .
                        'importing your blog, as Dotclear allows you to actually keep your posts ' .
                        'uncategorized.') . '</p>' .
                    '</div>' .

                    '<div class="col">' .
                    '<p><label for="ignore_first_cat" class="classic">' . form::checkbox('ignore_first_cat', 1, $this->vars['ignore_first_cat']) . ' ' .
                    __('Ignore the first category:') . '</label></p>' .
                    '<p><label for="cat_import" class="classic">' . form::checkbox('cat_import', 1, $this->vars['cat_import']) . ' ' .
                    __('Import lowest numbered category on posts:') . '</label></p>' .
                    '<p><label for="cat_as_tags" class="classic">' . form::checkbox('cat_as_tags', 1, $this->vars['cat_as_tags']) . ' ' .
                    __('Import all categories as tags:') . '</label></p>' .
                    '<p><label for="cat_tags_prefix">' . __('Prefix such tags with:') . '</label> ' .
                    form::field('cat_tags_prefix', 10, 20, Html::escapeHTML($this->vars['cat_tags_prefix'])) . '</p>' .
                    '<p><label for="post_limit">' . __('Number of entries to import at once:') . '</label> ' .
                    form::number('post_limit', 0, 999, Html::escapeHTML((string) $this->vars['post_limit'])) . '</p>' .
                    '</div>' .

                    '</div>' .

                    '<h3 class="clear vertical-separator">' . __('Content filters') . '</h3>' .
                    '<p>' . __('You may want to process your post and/or comment content with the following filters.') . '</p>' .
                    '<p><label for="post_formater">' . __('Post content formatter:') . '</label> ' .
                    form::combo('post_formater', $this->formaters, $this->vars['post_formater']) . '</p>' .
                    '<p><label for="comment_formater">' . __('Comment content formatter:') . '</label> '
                    . form::combo('comment_formater', $this->formaters, $this->vars['comment_formater']) . '</p>'
                );

                break;
            case 2:
                printf(
                    $this->imForm(2, __('Importing users')),
                    $this->autoSubmit()
                );

                break;
            case 3:
                printf(
                    $this->imForm(3, __('Importing categories')),
                    $this->autoSubmit()
                );

                break;
            case 4:
                printf(
                    $this->imForm(4, __('Importing blogroll')),
                    $this->autoSubmit()
                );

                break;
            case 5:
                $t = sprintf(
                    __('Importing entries from %d to %d / %d'),
                    $this->post_offset,
                    min([$this->post_offset + $this->post_limit, $this->post_count]),
                    $this->post_count
                );
                printf(
                    $this->imForm(5, $t),
                    form::hidden(['offset'], $this->post_offset) .
                    $this->autoSubmit()
                );

                break;
            case 6:
                echo
                '<p class="message">' . __('Every newly imported user has received a random password ' .
                    'and will need to ask for a new one by following the "I forgot my password" link on the login page ' .
                    '(Their registered email address has to be valid.)') . '</p>' .
                $this->congratMessage();

                break;
        }
    }

    /**
     * Return a simple form for step by step process.
     *
     * @param   int     $step           The step
     * @param   string  $legend         The legend
     * @param   string  $submit_value   The submit value
     *
     * @return  string
     */
    protected function imForm(int $step, string $legend, ?string $submit_value = null)
    {
        if (!$submit_value) {
            $submit_value = __('next step') . ' >';
        }

        return
        '<form action="' . $this->getURL(true) . '" method="post">' .
        '<h3 class="vertical-separator">' . $legend . '</h3>' .
        '<div>' . App::nonce()->getFormNonce() .
        form::hidden(['do'], 'step' . $step) .
        '%s' . '</div>' .
        '<p><input type="submit" value="' . $submit_value . '" /></p>' .
        '<p class="form-note info">' . __('Depending on the size of your blog, it could take a few minutes.') . '</p>' .
        '</form>';
    }

    /**
     * Error display.
     *
     * @param   Exception   $e  The error
     */
    protected function error(Exception $e): void
    {
        echo
        '<div class="error"><strong>' . __('Errors:') . '</strong>' .
        '<p>' . $e->getMessage() . '</p></div>';
    }

    /**
     * DB init.
     *
     * @throws  Exception
     *
     * @return  mixed
     */
    protected function db()
    {
        $db = App::newConnectionFromValues('mysqli', $this->vars['db_host'], $this->vars['db_name'], $this->vars['db_user'], $this->vars['db_pwd']);

        $rs = $db->select("SHOW TABLES LIKE '" . $this->vars['db_prefix'] . "%'");
        if ($rs->isEmpty()) {
            throw new Exception(__('WordPress tables not found'));
        }

        while ($rs->fetch()) {
            $this->has_table[(string) $rs->f(0)] = true;
        }

        # Set this to read data as they were written
        try {
            $db->execute('SET NAMES DEFAULT');
        } catch (Exception) {
        }

        $db->execute('SET CHARACTER SET DEFAULT');
        $db->execute('SET COLLATION_CONNECTION = DEFAULT');
        $db->execute('SET COLLATION_SERVER = DEFAULT');
        $db->execute('SET CHARACTER_SET_SERVER = DEFAULT');
        $db->execute('SET CHARACTER_SET_DATABASE = DEFAULT');

        $this->post_count = $db->select(
            'SELECT COUNT(ID) FROM ' . $this->vars['db_prefix'] . 'posts ' .
            'WHERE post_type = \'post\' OR post_type = \'page\''
        )->f(0);

        return $db;
    }

    /**
     * Users import.
     */
    protected function importUsers(): void
    {
        $db        = $this->db();
        $wp_prefix = $this->vars['db_prefix'];
        $rs        = $db->select('SELECT * FROM ' . $wp_prefix . 'users');

        if ($rs) {
            try {
                $this->con->begin();

                while ($rs->fetch()) {
                    $user_login                      = (string) preg_replace('/[^A-Za-z0-9@._-]/', '-', (string) $rs->user_login);
                    $this->vars['user_ids'][$rs->ID] = $user_login;
                    if (!App::users()->userExists($user_login)) {
                        $cur                   = App::auth()->openUserCursor();
                        $cur->user_id          = $user_login;
                        $cur->user_pwd         = Crypt::createPassword();
                        $cur->user_displayname = $rs->user_nicename;
                        $cur->user_email       = $rs->user_email;
                        $cur->user_url         = $rs->user_url;
                        $cur->user_creadt      = $rs->user_registered;
                        $cur->user_lang        = App::blog()->settings()->system->lang;
                        $cur->user_tz          = App::blog()->settings()->system->blog_timezone;
                        $permissions           = [];

                        $rs_meta = $db->select('SELECT * FROM ' . $wp_prefix . 'usermeta WHERE user_id = ' . $rs->ID);
                        while ($rs_meta->fetch()) {
                            switch ($rs_meta->meta_key) {
                                case 'first_name':
                                    $cur->user_firstname = Text::cleanStr($rs_meta->meta_value);

                                    break;
                                case 'last_name':
                                    $cur->user_name = Text::cleanStr($rs_meta->meta_value);

                                    break;
                                case 'description':
                                    $cur->user_desc = Text::cleanStr($rs_meta->meta_value);

                                    break;
                                case 'rich_editing':
                                    $cur->user_options = new ArrayObject([
                                        'enable_wysiwyg' => $rs_meta->meta_value == 'true' ? true : false,
                                    ]);

                                    break;
                                case 'wp_user_level':
                                    switch ($rs_meta->meta_value) {
                                        case '0': # Subscriber
                                            $cur->user_status = 0;

                                            break;
                                        case '1': # Contributor
                                            $permissions['usage']   = true;
                                            $permissions['publish'] = true;
                                            $permissions['delete']  = true;

                                            break;
                                        case '2': # Author
                                        case '3':
                                        case '4':
                                            $permissions['contentadmin'] = true;
                                            $permissions['media']        = true;

                                            break;
                                        case '5': # Editor
                                        case '6':
                                        case '7':
                                            $permissions['contentadmin'] = true;
                                            $permissions['categories']   = true;
                                            $permissions['media_admin']  = true;
                                            $permissions['pages']        = true;
                                            $permissions['blogroll']     = true;

                                            break;
                                        case '8': # Administrator
                                        case '9':
                                        case '10':
                                            $permissions['admin'] = true;

                                            break;
                                    }

                                    break;
                            }
                        }
                        App::users()->addUser($cur);
                        App::users()->setUserBlogPermissions(
                            $cur->user_id,
                            $this->blog_id,
                            $permissions
                        );
                    }
                }
                $this->con->commit();
                $db->close();
            } catch (Exception $e) {
                $this->con->rollback();
                $db->close();

                throw $e;
            }
        }
    }

    /**
     * Categories import.
     */
    protected function importCategories(): void
    {
        $db        = $this->db();
        $wp_prefix = $this->vars['db_prefix'];
        $rs        = $db->select(
            'SELECT * FROM ' . $wp_prefix . 'terms AS t, ' . $wp_prefix . 'term_taxonomy AS x ' .
            'WHERE x.taxonomy = \'category\' ' .
            'AND t.term_id = x.term_id ' .
            ($this->vars['ignore_first_cat'] ? 'AND t.term_id <> 1 ' : '') .
            'ORDER BY t.term_id ASC'
        );

        try {
            $this->con->execute(
                'DELETE FROM ' . $this->prefix . App::blog()->categories()::CATEGORY_TABLE_NAME . ' ' . // @phpstan-ignore-line
                "WHERE blog_id = '" . $this->con->escape($this->blog_id) . "' "
            );

            $ord = 2;
            while ($rs->fetch()) {
                $cur            = App::blog()->categories()->openCategoryCursor();
                $cur->blog_id   = $this->blog_id;
                $cur->cat_title = Text::cleanStr($rs->name);
                $cur->cat_desc  = Text::cleanStr($rs->description);
                $cur->cat_url   = Text::cleanStr($rs->slug);
                $cur->cat_lft   = $ord++;
                $cur->cat_rgt   = $ord++;

                $cur->cat_id = (new MetaRecord($this->con->select(
                    'SELECT MAX(cat_id) FROM ' . $this->prefix . App::blog()->categories()::CATEGORY_TABLE_NAME
                )))->f(0) + 1;
                $this->vars['cat_ids'][$rs->term_id] = $cur->cat_id;
                $cur->insert();
            }

            $db->close();
        } catch (Exception $e) {
            $db->close();

            throw $e;
        }
    }

    /**
     * Blogroll import.
     */
    protected function importLinks(): void
    {
        $db        = $this->db();
        $wp_prefix = $this->vars['db_prefix'];
        $rs        = $db->select('SELECT * FROM ' . $wp_prefix . 'links ORDER BY link_id ASC');

        try {
            $this->con->execute(
                'DELETE FROM ' . $this->prefix . Blogroll::LINK_TABLE_NAME . ' ' .  // @phpstan-ignore-line
                "WHERE blog_id = '" . $this->con->escape($this->blog_id) . "' "
            );

            while ($rs->fetch()) {
                $cur             = $this->con->openCursor($this->prefix . Blogroll::LINK_TABLE_NAME);
                $cur->blog_id    = $this->blog_id;
                $cur->link_href  = Text::cleanStr($rs->link_url);
                $cur->link_title = Text::cleanStr($rs->link_name);
                $cur->link_desc  = Text::cleanStr($rs->link_description);
                $cur->link_xfn   = Text::cleanStr($rs->link_rel);

                $cur->link_id = (new MetaRecord($this->con->select(
                    'SELECT MAX(link_id) FROM ' . $this->prefix . Blogroll::LINK_TABLE_NAME
                )))->f(0) + 1;
                $cur->insert();
            }

            $db->close();
        } catch (Exception $e) {
            $db->close();

            throw $e;
        }
    }

    /**
     * Entries import.
     *
     * @param   int     $percent    The percent
     *
     * @return  mixed
     */
    protected function importPosts(&$percent)
    {
        $db        = $this->db();
        $wp_prefix = $this->vars['db_prefix'];

        $plink = $db->select(
            'SELECT option_value FROM ' . $wp_prefix . 'options ' .
            "WHERE option_name = 'permalink_structure'"
        )->option_value;
        if ($plink) {
            $this->vars['permalink_template'] = substr($plink, 1);
        }

        $rs = $db->select(
            'SELECT * FROM ' . $wp_prefix . 'posts ' .
            'WHERE post_type = \'post\' OR post_type = \'page\' ' .
            'ORDER BY ID ASC ' .
            $db->limit($this->post_offset, $this->post_limit)
        );

        try {
            if ($this->post_offset == 0) {
                $this->con->execute(
                    'DELETE FROM ' . $this->prefix . App::blog()::POST_TABLE_NAME . ' ' .   // @phpstan-ignore-line
                    "WHERE blog_id = '" . $this->con->escape($this->blog_id) . "' "
                );
            }

            while ($rs->fetch()) {
                $this->importPost($rs, $db);
            }

            $db->close();
        } catch (Exception $e) {
            $db->close();

            throw $e;
        }

        if ($rs->count() < $this->post_limit) {
            return -1;
        }
        $this->post_offset += $this->post_limit;

        if ($this->post_offset > $this->post_count) {
            $percent = 100;
        } else {
            $percent = $this->post_offset * 100 / $this->post_count;
        }
    }

    /**
     * Entry import.
     *
     * @param   mixed   $rs     The record
     * @param   mixed   $db     The database
     */
    protected function importPost($rs, $db): void
    {
        $post_date = !@strtotime($rs->post_date) ? '1970-01-01 00:00' : $rs->post_date;
        if (!isset($this->vars['user_ids'][$rs->post_author])) {
            $user_id = App::auth()->userID();
        } else {
            $user_id = $this->vars['user_ids'][$rs->post_author];
        }

        $cur              = App::blog()->openPostCursor();
        $cur->blog_id     = $this->blog_id;
        $cur->user_id     = $user_id;
        $cur->post_dt     = $post_date;
        $cur->post_creadt = $post_date;
        $cur->post_upddt  = $rs->post_modified;
        $cur->post_title  = Text::cleanStr($rs->post_title);

        if (!$cur->post_title) {
            $cur->post_title = 'No title';
        }

        if ($this->vars['cat_import'] || $this->vars['cat_as_tags']) {
            $old_cat_ids = $db->select(
                'SELECT * FROM ' . $this->vars['db_prefix'] . 'terms AS t, ' .
                $this->vars['db_prefix'] . 'term_taxonomy AS x, ' .
                $this->vars['db_prefix'] . 'term_relationships AS r ' .
                'WHERE t.term_id = x.term_id ' .
                ($this->vars['ignore_first_cat'] ? 'AND t.term_id <> 1 ' : '') .
                'AND x.taxonomy = \'category\' ' .
                'AND t.term_id = r.term_taxonomy_id ' .
                'AND r.object_id =' . $rs->ID .
                ' ORDER BY t.term_id ASC '
            );
            if (!$old_cat_ids->isEmpty() && $this->vars['cat_import']) {
                $cur->cat_id = $this->vars['cat_ids'][(int) $old_cat_ids->term_id];
            }
        }

        $permalink_infos = [
            date('Y', (int) strtotime($cur->post_dt)),
            date('m', (int) strtotime($cur->post_dt)),
            date('d', (int) strtotime($cur->post_dt)),
            date('H', (int) strtotime($cur->post_dt)),
            date('i', (int) strtotime($cur->post_dt)),
            date('s', (int) strtotime($cur->post_dt)),
            $rs->post_name,
            $rs->ID,
            $cur->cat_id,
            $cur->user_id,
        ];
        $cur->post_url = (string) str_replace(
            $this->vars['permalink_tags'],
            $permalink_infos,
            $rs->post_type == 'post' ? $this->vars['permalink_template'] : '%postname%'
        );
        $cur->post_url = substr($cur->post_url, 0, 255);

        if (!$cur->post_url) {
            $cur->post_url = $rs->ID;
        }

        $cur->post_format = $this->vars['post_formater'];
        $_post_content    = explode('<!--more-->', $rs->post_content, 2);
        if (count($_post_content) == 1) {
            $cur->post_excerpt       = null;
            $cur->post_excerpt_xhtml = null;
            $cur->post_content       = Text::cleanStr((string) array_shift($_post_content));
        } else {
            $cur->post_excerpt = Text::cleanStr((string) array_shift($_post_content));
            $cur->post_content = Text::cleanStr((string) array_shift($_post_content));
        }

        $cur->post_content_xhtml = App::formater()->callEditorFormater('dcLegacyEditor', $this->vars['post_formater'], $cur->post_content);
        if (!is_null($cur->post_excerpt)) {
            $cur->post_excerpt_xhtml = App::formater()->callEditorFormater('dcLegacyEditor', $this->vars['post_formater'], $cur->post_excerpt);
        }

        $cur->post_status = match ($rs->post_status) {
            'publish' => App::blog()::POST_PUBLISHED,
            'draft'   => App::blog()::POST_UNPUBLISHED,
            'pending' => App::blog()::POST_PENDING,
            default   => App::blog()::POST_PENDING,
        };
        $cur->post_type         = $rs->post_type;
        $cur->post_password     = $rs->post_password ?: null;
        $cur->post_open_comment = $rs->comment_status == 'open' ? 1 : 0;
        $cur->post_open_tb      = $rs->ping_status    == 'open' ? 1 : 0;

        $cur->post_words = implode(' ', Text::splitWords(
            $cur->post_title . ' ' .
            $cur->post_excerpt_xhtml . ' ' .
            $cur->post_content_xhtml
        ));

        $cur->post_id = (int) (new MetaRecord($this->con->select(
            'SELECT MAX(post_id) FROM ' . $this->prefix . App::blog()::POST_TABLE_NAME
        )))->f(0) + 1;

        $cur->post_url = App::blog()->getPostURL($cur->post_url, $cur->post_dt, $cur->post_title, $cur->post_id);

        $cur->insert();
        $this->importComments($rs->ID, $cur->post_id, $db);
        $this->importPings($rs->ID, $cur->post_id, $db);

        # Create tags
        $this->importTags($rs->ID, $cur->post_id, $db);

        if (isset($old_cat_ids) && !$old_cat_ids->isEmpty() && $this->vars['cat_as_tags']) {
            $old_cat_ids->moveStart();
            while ($old_cat_ids->fetch()) {
                App::meta()->setPostMeta($cur->post_id, 'tag', Text::cleanStr($this->vars['cat_tags_prefix'] . $old_cat_ids->name));
            }
        }
    }

    /**
     * Comments import.
     *
     * @param   string  $post_id        The post identifier
     * @param   int     $new_post_id    The new post identifier
     * @param   mixed   $db             The database
     */
    protected function importComments(string $post_id, int $new_post_id, $db): void
    {
        $count_c = $count_t = 0;

        $rs = $db->select(
            'SELECT * FROM ' . $this->vars['db_prefix'] . 'comments ' .
            'WHERE comment_post_ID = ' . (int) $post_id . ' '
        );

        while ($rs->fetch()) {
            $cur                    = App::blog()->openCommentCursor();
            $cur->post_id           = (int) $new_post_id;
            $cur->comment_author    = Text::cleanStr($rs->comment_author);
            $cur->comment_status    = (int) $rs->comment_approved;
            $cur->comment_dt        = $rs->comment_date;
            $cur->comment_email     = Text::cleanStr($rs->comment_author_email);
            $cur->comment_content   = App::formater()->callEditorFormater('dcLegacyEditor', $this->vars['comment_formater'], Text::cleanStr($rs->comment_content));
            $cur->comment_ip        = $rs->comment_author_IP;
            $cur->comment_trackback = $rs->comment_type == 'trackback' ? 1 : 0;
            $cur->comment_site      = substr(Text::cleanStr($rs->comment_author_url), 0, 255);
            if ($cur->comment_site == '') {
                $cur->comment_site = null;
            }

            if ($rs->comment_approved == 'spam') {
                $cur->comment_status = App::blog()::COMMENT_JUNK;
            }

            $cur->comment_words = implode(' ', Text::splitWords($cur->comment_content));

            $cur->comment_id = (new MetaRecord($this->con->select(
                'SELECT MAX(comment_id) FROM ' . $this->prefix . App::blog()::COMMENT_TABLE_NAME
            )))->f(0) + 1;

            $cur->insert();

            if ($cur->comment_status == App::blog()::COMMENT_PUBLISHED) {
                if ($cur->comment_trackback) {
                    $count_t++;
                } else {
                    $count_c++;
                }
            }
        }

        if ($count_t > 0 || $count_c > 0) {
            $this->con->execute(
                'UPDATE ' . $this->prefix . App::blog()::POST_TABLE_NAME . ' SET ' .
                'nb_comment = ' . $count_c . ', ' .
                'nb_trackback = ' . $count_t . ' ' .
                'WHERE post_id = ' . $new_post_id . ' '
            );
        }
    }

    /**
     * Pings import.
     *
     * @param   string  $post_id        The post identifier
     * @param   int     $new_post_id    The new post identifier
     * @param   mixed   $db             The database
     */
    protected function importPings(string $post_id, int $new_post_id, $db): void
    {
        $urls  = [];
        $pings = [];

        $rs = $db->select(
            'SELECT pinged FROM ' . $this->vars['db_prefix'] . 'posts ' .
            'WHERE ID = ' . (int) $post_id
        );
        $pings = explode("\n", $rs->pinged);
        unset($pings[0]);

        foreach ($pings as $ping_url) {
            $url = Text::cleanStr($ping_url);
            if (isset($urls[$url])) {
                continue;
            }

            $cur           = $this->con->openCursor($this->prefix . App::trackback()::PING_TABLE_NAME);
            $cur->post_id  = $new_post_id;
            $cur->ping_url = $url;
            $cur->insert();

            $urls[$url] = true;
        }
    }

    /**
     * Meta import.
     *
     * @param   string  $post_id        The post identifier
     * @param   int     $new_post_id    The new post identifier
     * @param   mixed   $db             The database
     */
    protected function importTags(string $post_id, int $new_post_id, $db): void
    {
        $rs = $db->select(
            'SELECT * FROM ' . $this->vars['db_prefix'] . 'terms AS t, ' .
            $this->vars['db_prefix'] . 'term_taxonomy AS x, ' .
            $this->vars['db_prefix'] . 'term_relationships AS r ' .
            'WHERE t.term_id = x.term_id ' .
            'AND x.taxonomy = \'post_tag\' ' .
            'AND t.term_id = r.term_taxonomy_id ' .
            'AND r.object_id =' . $post_id .
            ' ORDER BY t.term_id ASC'
        );

        if ($rs->isEmpty()) {
            return;
        }

        while ($rs->fetch()) {
            App::meta()->setPostMeta($new_post_id, 'tag', Text::cleanStr($rs->name));
        }
    }
}
