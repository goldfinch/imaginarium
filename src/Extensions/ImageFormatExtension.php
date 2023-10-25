<?php

namespace Goldfinch\Imaginarium\Extensions;

use SilverStripe\ORM\DataExtension;
use Goldfinch\Imaginarium\Services\Compressor;

class ImageFormatExtension extends DataExtension
{
    public function updateURL(&$link)
    {
        $link = $this->imaginariumURL($link);
    }

    private function imaginariumURL($link)
    {
        $imageSupport = Compressor::imageSupport();

        if ($imageSupport && count($imageSupport))
        {
            if (isset($link))
            {
                $fullpath = BASE_PATH . '/' . PUBLIC_DIR . $link;
                $ex = explode('/', $fullpath);
                $ex2 = explode('.', last($ex));
                $ex3 = explode($ex2[0], $fullpath);
                $ex4 = explode($ex2[0], $link);

                if (in_array('avif', $imageSupport))
                {
                    $avif = $ex3[0] . $ex2[0] . '.' . 'avif';
                    if (file_exists($avif))
                    {
                        $newSrc = $ex4[0] . $ex2[0] . '.' . 'avif';
                    }
                }

                if (!isset($newSrc) && in_array('webp', $imageSupport))
                {
                    $webp = $ex3[0] . $ex2[0] . '.' . 'webp';
                    if (file_exists($webp))
                    {
                        $newSrc = $ex4[0] . $ex2[0] . '.' . 'webp';
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
