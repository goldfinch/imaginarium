<?php

namespace Goldfinch\Imaginarium\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Control\Director;
use Goldfinch\Imaginarium\Imaginator;
use SilverStripe\Admin\AdminRootController;

class DBFileExtension extends Extension
{
    private static $escapeFormatting;
    private static $escapeFormattingAvif;
    private static $escapeFormattingWebp;

    public function Avif($image)
    {
        $link = $image->getURL();

        if (isset($link))
        {
            $fullpath = BASE_PATH . '/' . PUBLIC_DIR . $link;
            $ex = explode('/', $fullpath);
            $ex2 = explode('.', last($ex));
            $ex3 = explode($ex2[0], $fullpath);
            $ex4 = explode($ex2[0], $link);

            $avif = $ex3[0] . $ex2[0] . '.' . 'avif';

            if (file_exists($avif))
            {
                return $ex4[0] . $ex2[0] . '.' . 'avif';
            }
        }

        return null;
    }

    public function Webp($image)
    {
        $link = $image->getURL();

        if (isset($link))
        {
            $fullpath = BASE_PATH . '/' . PUBLIC_DIR . $link;
            $ex = explode('/', $fullpath);
            $ex2 = explode('.', last($ex));
            $ex3 = explode($ex2[0], $fullpath);
            $ex4 = explode($ex2[0], $link);

            $webp = $ex3[0] . $ex2[0] . '.' . 'webp';

            if (file_exists($webp))
            {
                return $ex4[0] . $ex2[0] . '.' . 'webp';
            }
        }

        return null;
    }

    public function updateURL(&$link)
    {
        if ($this->owner->getIsImage())
        {
            if (!$this->owner->escapeFormatting)
            {
                // ! use Director::get_current_page causes Appolo js error in /admin/assets 'An unknown error has occurred'
                // $currentPageLink = Director::get_current_page()->Link();
                $currentPageLink = $_SERVER['REQUEST_URI'];

                if (strpos($currentPageLink, AdminRootController::admin_url()) === 0)
                {
                    // admin
                    // $link = $this->imaginariumURL($link);
                }
                else
                {
                    // site
                    $link = $this->imaginariumURL($link);
                }
            }
        }

        // if (Environment::getEnv('APP_URL_CDN'))
        // {
        //     $link = Environment::getEnv('APP_URL_CDN') . $link;
        // }
    }

    private function imaginariumURL($link)
    {
        $imageSupport = Imaginator::imageSupport();

        if ($imageSupport && count($imageSupport))
        {
            if (isset($link))
            {
                $fullpath = BASE_PATH . '/' . PUBLIC_DIR . $link;
                $ex = explode('/', $fullpath);
                $ex2 = explode('.', last($ex));
                $ex3 = explode($ex2[0], $fullpath);
                $ex4 = explode($ex2[0], $link);

                if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/avif') >= 0)
                {
                    if (!$this->owner->escapeFormattingAvif && in_array('avif', $imageSupport))
                    {
                        $avif = $ex3[0] . $ex2[0] . '.' . 'avif';
                        if (file_exists($avif))
                        {
                            $newSrc = $ex4[0] . $ex2[0] . '.' . 'avif';
                        }
                    }
                }

                if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') >= 0)
                {
                    if (!$this->owner->escapeFormattingWebp && !isset($newSrc) && in_array('webp', $imageSupport))
                    {
                        $webp = $ex3[0] . $ex2[0] . '.' . 'webp';
                        if (file_exists($webp))
                        {
                            $newSrc = $ex4[0] . $ex2[0] . '.' . 'webp';
                        }
                    }
                }

                if (isset($newSrc))
                {
                    return $newSrc;
                }
            }
        }

        return $link;
    }
}
