<?php

namespace Goldfinch\Imaginarium\Extensions;

use SilverStripe\ORM\ArrayList;
use SilverStripe\Core\Extension;
use SilverStripe\View\ArrayData;
use Goldfinch\Imaginarium\Services\Imaginator;

class ImageExtension extends Extension
{
    private static $escapeFormatting;
    private static $escapeFormattingAvif;
    private static $escapeFormattingWebp;

    private $RIOptions = null;

    public function LazyFocusFill(int $width, int $height, $lazyloadTag = true)
    {
        return $this->Lazy($this->owner->FocusFill($width, $height), $lazyloadTag);
    }

    public function LazyFocusFillMax(int $width, int $height, $lazyloadTag = true)
    {
        return $this->Lazy($this->owner->FocusFillMax($width, $height));
    }

    public function LazyFitMax(int $width, int $height, $lazyloadTag = true)
    {
        return $this->Lazy($this->owner->FitMax($width, $height));
    }

    public function LazyFocusCropWidth(int $width, $lazyloadTag = true)
    {
        return $this->Lazy($this->owner->FocusCropWidth($width));
    }

    public function LazyFocusCropHeight(int $height, $lazyloadTag = true)
    {
        return $this->Lazy($this->owner->FocusCropHeight($height));
    }

    protected function Lazy($file, $lazyloadTag = true)
    {
        if (!$file) {
          return $file;
        }

        // Default Lazy Thumbnail width (height is calculated automaticly proportionally based on width)
        $thumbnailWidth = 80;

        $width = $file->getAttribute('width');
        $height = $file->getAttribute('height');

        $thumbnailWidthPercentage = $width / 100;
        $thumbnailHeightPercentage = $height / 100;

        $thumbWidthPercentage = $thumbnailWidth / $thumbnailWidthPercentage;
        $thumbnailHeight = $thumbnailHeightPercentage * $thumbWidthPercentage;

        $url = $file->getAttribute('src');

        $thumbnail = $file->owner->FocusFill((int) $thumbnailWidth, (int) $thumbnailHeight);

        if (!$thumbnail) {

          $thumbnail = $file->owner->Fill((int) $thumbnailWidth, (int) $thumbnailHeight);

        }

        if($thumbnail) {

          $file = $file->setAttribute('src', $thumbnail->getURL());
          $file = $file->setAttribute('data-loaded', 'false');
          $file = $file->setAttribute('data-src', $url);

        }

        if (!$lazyloadTag)
        {
            $file = $file->setAttribute('loading', false);
        }

        return $file;
    }

    private function setRIoptions($options)
    {
        if ($options)
        {
            $this->RIOptions = json_decode($options, true);
        }
    }

    private function getRIoptions()
    {
        return $this->RIOptions;
    }

    private function hasRIOption($prop)
    {
        $options = $this->getRIoptions();

        if ($options && isset($options[$prop]))
        {
            return true;
        }

        return false;
    }

    private function getRIOption($prop)
    {
        $options = $this->getRIoptions();

        if ($options && isset($options[$prop]))
        {
            return $options[$prop];
        }

        return null;
    }

    public function EscapeF()
    {
        $this->owner->escapeFormatting = true;

        return $this->owner;
    }

    public function EscapeFAvif()
    {
        $this->owner->escapeFormattingAvif = true;

        return $this->owner;
    }

    public function EscapeFWebp()
    {
        $this->owner->escapeFormattingWebp = true;

        return $this->owner;
    }

    public function Responsive($ratio, $sizes, $options = null)
    {
        $expl = explode(':', $ratio);

        $intrinsicWidth = (int) $expl[0];
        $intrinsicHeight = (int) $expl[1];

        $sizes = explode(',', $sizes);
        $formatedSizes = [];

        array_map(function($w) use (&$formatedSizes) {
          $ex = explode('>', $w);

          $bp = (int) trim($ex[0]);
          $wd = (int) trim($ex[1]);
          $formatedSizes[$bp] = $wd;

        }, $sizes);

        krsort($formatedSizes);

        $singleWidth = ['CropWidth', 'ScaleWidth', 'ScaleMaxWidth'];
        $singleHeight = ['CropHeight', 'ScaleHeight', 'ScaleMaxHeight'];

        $this->setRIoptions($options);
        // ! here to make sure that the initially passed $options is not carring options, to force using object property instead
        $options = null;

        if ($this->hasRIOption('manipulation'))
        {
            $manipulation = $this->getRIOption('manipulation');
        }
        else
        {
            $manipulation = 'FocusFill';
        }

        $intrinsicRatio = $intrinsicWidth / $intrinsicHeight;

        $sizes = ArrayList::create();

        $firstImage = null;

        foreach($formatedSizes as $bp => $width)
        {
            $height = (int) round($width / $intrinsicRatio);

            if (in_array($manipulation, $singleWidth))
            {
                $sizedImage = $this->owner->EscapeF()->$manipulation($width);
            }
            else if (in_array($manipulation, $singleHeight))
            {
                $sizedImage = $this->owner->EscapeF()->$manipulation($height);
            }
            else
            {
                $sizedImage = $this->owner->EscapeF()->$manipulation($width, $height);
            }

            if ($bp === 0)
            {
                $firstImage = $sizedImage;
                continue;
            }

            $mediaQuery = '(min-width: ' . $bp . 'px)'; // and (min-device-pixel-ratio: 2.0)';

            $sizedImageAvif = $this->Avif($sizedImage);
            $sizedImageWebp = $this->Webp($sizedImage);

            $sizes->push(ArrayData::create([
              'Image' => $sizedImage,
              'ImageAvif' => $sizedImageAvif,
              'ImageWebp' => $sizedImageWebp,
              'MediaQuery' => $mediaQuery,
            ]));
        }

        // Placeholder image

        $placeholderWidth = 80;
        $placeholderHeight = round($placeholderWidth / $intrinsicRatio);

        if (in_array($manipulation, $singleWidth))
        {
            $placeholderImage = $this->owner->EscapeF()->$manipulation($placeholderWidth);
        }
        else if (in_array($manipulation, $singleHeight))
        {
            $placeholderImage = $this->owner->EscapeF()->$manipulation($placeholderHeight);
        }
        else
        {
            $placeholderImage = $this->owner->EscapeF()->$manipulation($placeholderWidth, $placeholderHeight);
        }

        return $this->owner->customise([
          'Sizes' => $sizes,
          'FirstImage' => $firstImage,
          'PlaceholderImage' => $placeholderImage,
          'Lazy' => $this->hasRIOption('lazy') ? $this->getRIOption('lazy') : true,
          'LazyLoadingTag' => $this->hasRIOption('loadingtag') ? $this->getRIOption('loadingtag') : true,
        ])->renderWith(['Layout' => 'Goldfinch/Imaginarium/Responsive']);
    }
    public function updateURL(&$link)
    {
        if ($this->owner->getIsImage())
        {
            if (!$this->owner->escapeFormatting)
            {
                $link = $this->imaginariumURL($link);
            }
        }

        if (Environment::getEnv('APP_URL_CDN'))
        {
            $link = Environment::getEnv('APP_URL_CDN') . $link;
        }
    }

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
