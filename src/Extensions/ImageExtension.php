<?php

namespace Goldfinch\Imaginarium\Extensions;

use SilverStripe\Core\Extension;

class ImageExtension extends Extension
{
    private static $db = [];

    public function LazyFocusFill(int $width, int $height)
    {
        return $this->Lazy($this->owner->FocusFill($width, $height));
    }

    public function LazyFocusFillMax(int $width, int $height)
    {
        return $this->Lazy($this->owner->FocusFillMax($width, $height));
    }

    public function LazyFitMax(int $width, int $height)
    {
        return $this->Lazy($this->owner->FitMax($width, $height));
    }

    public function LazyFocusCropWidth(int $width)
    {
        return $this->Lazy($this->owner->FocusCropWidth($width));
    }

    public function LazyFocusCropHeight(int $height)
    {
        return $this->Lazy($this->owner->FocusCropHeight($height));
    }

    public function LazyThumb(int $width, int $height)
    {
        return $this->Lazy($this->owner->FocusFill($width, $height));
    }

    protected function Lazy($file)
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

        return $file;
    }
}
