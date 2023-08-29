<?php
/**
 * @package Dotclear
 * @subpackage Backend
 *
 * Utility class for admin context.
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Backend;

use dcCore;
use dcTraitDynamicProperties;
use Dotclear\Core\Core;
use Dotclear\Core\PostType;
use Dotclear\Core\Process;
use Dotclear\Fault;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Network\Http;
use Exception;

class Utility extends Process
{
    /** Allow dynamic properties */
    use dcTraitDynamicProperties;

    /** @var    string  Current admin page URL */
    private string $p_url = '';

    /** @var    Url     Backend (admin) Url handler instance */
    public Url $url;

    /** @var    Favorites   Backend (admin) Favorites handler instance */
    public Favorites $favs;

    /** @var    Menus   Backend (admin) Menus handler instance */
    public Menus $menus;

    /** @var    Resources   Backend help resources instance */
    public Resources $resources;

    /** @deprecated since 2.27, use Menus::MENU_FAVORITES */
    public const MENU_FAVORITES = Menus::MENU_FAVORITES;

    /** @deprecated since 2.27, use Menus::MENU_BLOG */
    public const MENU_BLOG = Menus::MENU_BLOG;

    /** @deprecated since 2.27, use Menus::MENU_SYSTEM */
    public const MENU_SYSTEM = Menus::MENU_SYSTEM;

    /** @deprecated since 2.27, use Menus::MENU_PLUGINS */
    public const MENU_PLUGINS = Menus::MENU_PLUGINS;

    /**
     * Constructs a new instance.
     *
     * @throws     Exception  (if not admin context)
     */
    public function __construct()
    {
        if (!defined('DC_CONTEXT_ADMIN')) {
            throw new Exception('Application is not in administrative context.', 500);
        }

        // HTTP/1.1
        header('Expires: Mon, 13 Aug 2003 07:48:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    }

    /**
     * Initialize application utility.
     */
    public static function init(): bool
    {
        define('DC_CONTEXT_ADMIN', true);

        return true;
    }

    /**
     * Process application utility and set up a singleton instance.
     */
    public static function process(): bool
    {
        // Instanciate Backend instance
        Core::backend();

        // New admin url instance
        Core::backend()->url = new Url();

        // deprecated since 2.27, use Core::backend()->url instead
        dcCore::app()->adminurl = Core::backend()->url;

        if (Core::auth()->sessionExists()) {
            // If we have a session we launch it now
            try {
                if (!Core::auth()->checkSession()) {
                    // Avoid loop caused by old cookie
                    $p    = Core::session()->getCookieParameters(false, -600);
                    $p[3] = '/';
                    setcookie(...$p);

                    // Preserve safe_mode if necessary
                    $params = !empty($_REQUEST['safe_mode']) ? ['safe_mode' => 1] : [];
                    Core::backend()->url->redirect('admin.auth', $params);
                }
            } catch (Exception $e) {
                new Fault(__('Database error'), __('There seems to be no Session table in your database. Is Dotclear completly installed?'), Fault::DATABASE_ISSUE);
            }

            // Fake process to logout (kill session) and return to auth page.
            if (!empty($_REQUEST['process']) && $_REQUEST['process'] == 'Logout') {
                // Enable REST service if disabled, for next requests
                if (!Core::rest()->serveRestRequests()) {
                    Core::rest()->enableRestServer(true);
                }
                // Kill admin session
                Core::backend()->killAdminSession();
                // Logout
                Core::backend()->url->redirect('admin.auth');
                exit;
            }

            // Check nonce from POST requests
            if (!empty($_POST) && (empty($_POST['xd_check']) || !Core::nonce()->checkNonce($_POST['xd_check']))) {
                new Fault('Precondition Failed', __('Precondition Failed'), 412);
            }

            // Switch blog
            if (!empty($_REQUEST['switchblog']) && Core::auth()->getPermissions($_REQUEST['switchblog']) !== false) {
                $_SESSION['sess_blog_id'] = $_REQUEST['switchblog'];

                if (!empty($_REQUEST['redir'])) {
                    // Keep context as far as possible
                    $redir = (string) $_REQUEST['redir'];
                } else {
                    // Removing switchblog from URL
                    $redir = (string) $_SERVER['REQUEST_URI'];
                    $redir = (string) preg_replace('/switchblog=(.*?)(&|$)/', '', $redir);
                    $redir = (string) preg_replace('/\?$/', '', $redir);
                }

                Core::auth()->user_prefs->interface->drop('media_manager_dir');

                if (!empty($_REQUEST['process']) && $_REQUEST['process'] == 'Media' || strstr($redir, 'media.php') !== false) {
                    // Remove current media dir from media manager URL
                    $redir = (string) preg_replace('/d=(.*?)(&|$)/', '', $redir);
                }

                Http::redirect($redir);
                exit;
            }

            // Check blog to use and log out if no result
            if (isset($_SESSION['sess_blog_id'])) {
                if (Core::auth()->getPermissions($_SESSION['sess_blog_id']) === false) {
                    unset($_SESSION['sess_blog_id']);
                }
            } else {
                if (($b = Core::auth()->findUserBlog(Core::auth()->getInfo('user_default_blog'), false)) !== false) {
                    $_SESSION['sess_blog_id'] = $b;
                    unset($b);
                }
            }

            // Load locales
            Helper::loadLocales();

            // deprecated since 2.27, use Core::lang() instead
            $GLOBALS['_lang'] = Core::lang();

            // Load blog
            if (isset($_SESSION['sess_blog_id'])) {
                Core::setBlog($_SESSION['sess_blog_id']);
            } else {
                Core::session()->destroy();
                Core::backend()->url->redirect('admin.auth');
            }
        }

        // Set default backend URLs
        Core::backend()->url->setDefaultURLs();

        // (re)set post type with real backend URL (as admin URL handler is known yet)
        Core::postTypes()->set(new PostType('post', urldecode(Core::backend()->url->get('admin.post', ['id' => '%d'], '&')), Core::url()->getURLFor('post', '%s'), 'Posts'));

        // No user nor blog, do not load more stuff
        if (!(Core::auth()->userID() && Core::blog() !== null)) {
            return true;
        }

        // Load resources and help files
        Core::backend()->resources = new Resources();

        require implode(DIRECTORY_SEPARATOR, [DC_L10N_ROOT, 'en', 'resources.php']);
        if ($f = L10n::getFilePath(DC_L10N_ROOT, '/resources.php', Core::lang())) {
            require $f;
        }
        unset($f);

        if (($hfiles = @scandir(implode(DIRECTORY_SEPARATOR, [DC_L10N_ROOT, Core::lang(), 'help']))) !== false) {
            foreach ($hfiles as $hfile) {
                if (preg_match('/^(.*)\.html$/', $hfile, $m)) {
                    Core::backend()->resources->set('help', $m[1], implode(DIRECTORY_SEPARATOR, [DC_L10N_ROOT, Core::lang(), 'help', $hfile]));
                }
            }
        }
        unset($hfiles);
        // Contextual help flag
        Core::backend()->resources->context(false);

        $user_ui_nofavmenu = Core::auth()->user_prefs->interface->nofavmenu;

        Core::backend()->favs  = new Favorites();
        Core::backend()->menus = new Menus();

        // deprecated since 2.27, use Core::backend()->favs instead
        dcCore::app()->favs = Core::backend()->favs;

        // deprecated since 2.27, use Core::backend()->menus instead
        dcCore::app()->menu = Core::backend()->menus;

        // deprecated Since 2.23, use Core::backend()->menus instead
        $GLOBALS['_menu'] = Core::backend()->menus;

        // Set default menu
        Core::backend()->menus->setDefaultItems();

        if (!$user_ui_nofavmenu) {
            Core::backend()->favs->appendMenuSection(Core::backend()->menus);
        }

        // Load plugins
        Core::plugins()->loadModules(DC_PLUGINS_ROOT, 'admin', Core::lang());
        Core::backend()->favs->setup();

        if (!$user_ui_nofavmenu) {
            Core::backend()->favs->appendMenu(Core::backend()->menus);
        }

        if (empty(Core::blog()->settings->system->jquery_migrate_mute)) {
            Core::blog()->settings->system->put('jquery_migrate_mute', true, 'boolean', 'Mute warnings for jquery migrate plugin ?', false);
        }
        if (empty(Core::blog()->settings->system->jquery_allow_old_version)) {
            Core::blog()->settings->system->put('jquery_allow_old_version', false, 'boolean', 'Allow older version of jQuery', false, true);
        }

        // Admin behaviors
        Core::behavior()->addBehavior('adminPopupPosts', [BlogPref::class, 'adminPopupPosts']);

        return true;
    }

    /**
     * Set the admin page URL.
     *
     * @param   string  $url  The URL
     */
    public function setPageURL(string $url): void
    {
        $this->p_url = $url;

        // deprecated since 2.24, use Core::backend()->setPageURL() and Core::backend()->getPageURL() instaed
        $GLOBALS['p_url'] = $url;
    }

    /**
     * Get the admin page URL.
     *
     * @return  string  The URL
     */
    public function getPageURL(): string
    {
        return $this->p_url;
    }

    /**
     * Kill admin session helper
     */
    public function killAdminSession(): void
    {
        // Kill session
        Core::session()->destroy();

        // Unset cookie if necessary
        if (isset($_COOKIE['dc_admin'])) {
            unset($_COOKIE['dc_admin']);
            setcookie('dc_admin', '', -600, '', '', DC_ADMIN_SSL);
        }
    }
}
