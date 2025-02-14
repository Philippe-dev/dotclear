<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\pings;

use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Html\Html;
use Exception;
use form;

if (!App::task()->checkContext('BACKEND')) {
    return false;
}

/**
 * @brief   The module backend behaviors.
 * @ingroup pings
 */
class BackendBehaviors
{
    /**
     * Add attachment fieldset in entry sidebar.
     *
     * @param   ArrayObject<string, mixed>     $main       The main part of the entry form
     * @param   ArrayObject<string, mixed>     $sidebar    The sidebar part of the entry form
     */
    public static function pingsFormItems(ArrayObject $main, ArrayObject $sidebar): void
    {
        if (!My::settings()->pings_active) {
            return;
        }

        $pings_uris = My::settings()->pings_uris;
        if (empty($pings_uris) || !is_array($pings_uris)) {
            return;
        }

        if (!empty($_POST['pings_do']) && is_array($_POST['pings_do'])) {
            $pings_do = $_POST['pings_do'];
        } else {
            $pings_do = [];
        }

        $item = '<h5 class="ping-services">' . __('Pings') . '</h5>';
        $i    = 0;
        foreach ($pings_uris as $name => $uri) {
            $item .= '<p class="ping-services"><label for="pings_do-' . $i . '" class="classic">' .
            form::checkbox(['pings_do[]', 'pings_do-' . $i], Html::escapeHTML($uri), in_array($uri, $pings_do), 'check-ping-services') . ' ' .
            Html::escapeHTML((string) $name) . '</label></p>';
            $i++;
        }
        $sidebar['options-box']['items']['pings'] = $item;
    }

    /**
     * Do pings.
     */
    public static function doPings(): void
    {
        if (empty($_POST['pings_do']) || !is_array($_POST['pings_do'])) {
            return;
        }

        if (!My::settings()->pings_active) {
            return;
        }

        $pings_uris = My::settings()->pings_uris;
        if (empty($pings_uris) || !is_array($pings_uris)) {
            return;
        }

        foreach ($_POST['pings_do'] as $uri) {
            if (in_array($uri, $pings_uris)) {
                try {
                    PingsAPI::doPings($uri, App::blog()->name(), App::blog()->url());
                } catch (Exception) {
                }
            }
        }
    }
}
