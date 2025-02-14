<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\importExport;

use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\App;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Zip\Unzip;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;
use form;

/**
 * @brief   The import flat module handler.
 * @ingroup importExport
 */
class ModuleImportFlat extends Module
{
    /**
     * Current import type (full|single).
     *
     * @var     string  $status
     */
    protected $status = '';

    public function setInfo(): void
    {
        $this->type        = 'import';
        $this->name        = __('Flat file import');
        $this->description = __('Imports a blog or a full Dotclear installation from flat file.');
    }

    public function process(string $do): void
    {
        if ($do === 'single' || $do === 'full') {
            $this->status = $do;

            return;
        }

        $to_unlink = false;

        # Single blog import
        $files      = $this->getPublicFiles();
        $single_upl = null;
        if (!empty($_POST['public_single_file']) && in_array($_POST['public_single_file'], $files)) {
            $single_upl = false;
        } elseif (!empty($_FILES['up_single_file'])) {
            $single_upl = true;
        }

        if ($single_upl !== null) {
            if ($single_upl) {
                Files::uploadStatus($_FILES['up_single_file']);
                $file = App::config()->cacheRoot() . '/' . md5(uniqid());
                if (!move_uploaded_file($_FILES['up_single_file']['tmp_name'], $file)) {
                    throw new Exception(__('Unable to move uploaded file.'));
                }
                $to_unlink = true;
            } else {
                $file = $_POST['public_single_file'];
            }

            $unzip_file = '';

            try {
                # Try to unzip file
                $unzip_file = $this->unzip($file);
                if (false !== $unzip_file) {
                    $bk = new FlatImportV2($unzip_file);
                }
                # Else this is a normal file
                else {
                    $bk = new FlatImportV2($file);
                }

                $bk->importSingle();
            } catch (Exception $e) {
                if (false !== $unzip_file) {
                    @unlink($unzip_file);
                }
                if ($to_unlink) {
                    @unlink($file);
                }

                throw $e;
            }
            if ($unzip_file) {
                @unlink($unzip_file);
            }
            if ($to_unlink) {
                @unlink($file);
            }
            Http::redirect($this->getURL() . '&do=single');
        }

        # Full import
        $full_upl = null;
        if (!empty($_POST['public_full_file']) && in_array($_POST['public_full_file'], $files)) {
            $full_upl = false;
        } elseif (!empty($_FILES['up_full_file'])) {
            $full_upl = true;
        }

        if ($full_upl !== null && App::auth()->isSuperAdmin()) {
            if (empty($_POST['your_pwd']) || !App::auth()->checkPassword($_POST['your_pwd'])) {
                throw new Exception(__('Password verification failed'));
            }

            if ($full_upl) {
                Files::uploadStatus($_FILES['up_full_file']);
                $file = App::config()->cacheRoot() . '/' . md5(uniqid());
                if (!move_uploaded_file($_FILES['up_full_file']['tmp_name'], $file)) {
                    throw new Exception(__('Unable to move uploaded file.'));
                }
                $to_unlink = true;
            } else {
                $file = $_POST['public_full_file'];
            }

            $unzip_file = '';

            try {
                # Try to unzip file
                $unzip_file = $this->unzip($file);
                if (false !== $unzip_file) {
                    $bk = new FlatImportV2($unzip_file);
                }
                # Else this is a normal file
                else {
                    $bk = new FlatImportV2($file);
                }

                $bk->importFull();
            } catch (Exception $e) {
                if (false !== $unzip_file) {
                    @unlink($unzip_file);
                }
                if ($to_unlink) {
                    @unlink($file);
                }

                throw $e;
            }
            if ($unzip_file) {
                @unlink($unzip_file);
            }
            if ($to_unlink) {
                @unlink($file);
            }
            Http::redirect($this->getURL() . '&do=full');
        }

        header('content-type:text/plain');
        var_dump($_POST);
        exit;
    }

    public function gui(): void
    {
        if ($this->status === 'single') {
            Notices::success(__('Single blog successfully imported.'));

            return;
        }
        if ($this->status === 'full') {
            Notices::success(__('Content successfully imported.'));

            return;
        }

        $public_files = ['-' => '', ...$this->getPublicFiles()];
        $has_files    = (bool) (count($public_files) - 1);

        echo
        Page::jsJson(
            'ie_import_flat_msg',
            ['confirm_full_import' => __('Are you sure you want to import a full backup file?')]
        ) .
        My::jsLoad('import_flat');
        echo
        '<form action="' . $this->getURL(true) . '" method="post" enctype="multipart/form-data" class="fieldset">' .
        '<h3>' . __('Single blog') . '</h3>' .
        '<p>' . sprintf(__('This will import a single blog backup as new content in the current blog: <strong>%s</strong>.'), Html::escapeHTML(App::blog()->name())) . '</p>' .

        '<p><label for="up_single_file">' . __('Upload a backup file') .
        ' (' . sprintf(__('maximum size %s'), Files::size(App::config()->maxUploadSize())) . ')' . ' </label>' .
            ' <input type="file" id="up_single_file" name="up_single_file" size="20" />' .
            '</p>';

        if ($has_files) {
            echo
            '<p><label for="public_single_file" class="">' . __('or pick up a local file in your public directory') . ' </label> ' .
            form::combo('public_single_file', $public_files) .
                '</p>';
        }

        echo
        '<p>' .
        App::nonce()->getFormNonce() .
        form::hidden(['do'], 1) .
        form::hidden(['MAX_FILE_SIZE'], (string) App::config()->maxUploadSize()) .
        '<input type="submit" value="' . __('Import') . '" /></p>' .

            '</form>';

        if (App::auth()->isSuperAdmin()) {
            echo
            '<form action="' . $this->getURL(true) . '" method="post" enctype="multipart/form-data" id="formfull" class="fieldset">' .
            '<h3>' . __('Multiple blogs') . '</h3>' .
            '<p class="warning">' . __('This will reset all the content of your database, except users.') . '</p>' .

            '<p><label for="up_full_file">' . __('Upload a backup file') . ' ' .
            ' (' . sprintf(__('maximum size %s'), Files::size(App::config()->maxUploadSize())) . ')' . ' </label>' .
                '<input type="file" id="up_full_file" name="up_full_file" size="20" />' .
                '</p>';

            if ($has_files) {
                echo
                '<p><label for="public_full_file">' . __('or pick up a local file in your public directory') . ' </label>' .
                form::combo('public_full_file', $public_files) .
                    '</p>';
            }

            echo
            '<p><label for="your_pwd" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label>' .
            form::password(
                'your_pwd',
                20,
                255,
                [
                    'extra_html'   => 'required placeholder="' . __('Password') . '"',
                    'autocomplete' => 'current-password',
                ]
            ) . '</p>' .

            '<p>' .
            App::nonce()->getFormNonce() .
            form::hidden(['do'], 1) .
            form::hidden(['MAX_FILE_SIZE'], (string) App::config()->maxUploadSize()) .
            '<input type="submit" value="' . __('Import') . '" /></p>' .

                '</form>';
        }
    }

    /**
     * Gets the public files.
     *
     * @return  array<string, mixed>   The public files.
     */
    protected function getPublicFiles(): array
    {
        $public_files = [];
        $dir          = @dir(App::blog()->publicPath());
        if ($dir) {
            while (($entry = $dir->read()) !== false) {
                $entry_path = $dir->path . '/' . $entry;

                if (is_file($entry_path) && is_readable($entry_path)) {
                    // Do not test each zip file content here, its too long
                    if ((str_ends_with($entry_path, '.zip')) || (self::checkFileContent($entry_path))) {
                        $public_files[$entry] = $entry_path;
                    }
                }
            }
        }

        return $public_files;
    }

    /**
     * Check if the file is in flat export format.
     *
     * @param   string  $entry_path     The entry path
     *
     * @return  bool
     */
    protected static function checkFileContent(string $entry_path): bool
    {
        $ret = false;

        $fp = fopen($entry_path, 'rb');
        if ($fp !== false) {
            $ret = str_starts_with((string) fgets($fp), '///DOTCLEAR|');
            fclose($fp);
        }

        return $ret;
    }

    /**
     * Unzip a file.
     *
     * @param   string  $file   The file
     *
     * @throws  Exception
     *
     * @return  false|string
     */
    private function unzip(string $file)
    {
        $zip = new Unzip($file);

        if ($zip->isEmpty()) {
            $zip->close();

            return false;
        }

        foreach ($zip->getFilesList() as $zip_file) {
            # Check zipped file name
            if (!str_ends_with($zip_file, '.txt')) {
                continue;
            }

            # Check zipped file contents
            $content = $zip->unzip($zip_file);
            if (!str_starts_with($content, '///DOTCLEAR|')) {
                unset($content);

                continue;
            }

            $target = Path::fullFromRoot($zip_file, dirname($file));

            # Check existing files with same name
            if (file_exists($target)) {
                $zip->close();
                unset($content);

                throw new Exception(__('Another file with same name exists.'));
            }

            # Extract backup content
            if (file_put_contents($target, $content) === false) {
                $zip->close();
                unset($content);

                throw new Exception(__('Failed to extract backup file.'));
            }

            $zip->close();
            unset($content);

            # Return extracted file name
            return $target;
        }

        $zip->close();

        throw new Exception(__('No backup in compressed file.'));
    }
}
