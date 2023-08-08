<?php

namespace Goldfinch\Imaginarium\Extensions;

use SilverStripe\ORM\ArrayList;
use SilverStripe\Core\Extension;
use SilverStripe\View\ArrayData;

class ImageExtension extends Extension
{
    private static $db = [];

    private $RIOptions = null;

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

    public function getSourceMediaQuery($mq, $w)
    {
        $queryString = '';

        $mq = trim($mq);

        return str_replace([
          '%w'
        ], [
          $w.'px'
        ], $mq);
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

    /**
     * intrinsicWidth & intrinsicHeight should be the highest possible size (the top breakpoint) of the current image
     */
    public function ResponsiveImage($intrinsicWidth, $intrinsicHeight, $options = null)
    {
        $this->setRIoptions($options);
        // ! here to make sure that the initially passed $options is not carring options, to force using object property instead
        $options = null;

        // Bootstrap var --bs-gutter-x
        if ($this->hasRIOption('gutter') && $this->getRIOption('gutter'))
        {
            $customGutter = $this->getRIOption('gutter');
        }

        $intrinsicRatio = $intrinsicWidth / $intrinsicHeight;

        // ? default
        // !important keep the descending order : from largest to smallest
        $breakpoints = [
          'xl' => 1210,
          'lg' => 992,
          'md' => 768,
          'sm' => 576,
          // 'xs' => 0, // ! need here as a started point
        ];

        $breakpointsMax = [
          'xl' => 1174,
          'lg' => 960,
          'md' => 720,
          'sm' => 540,
          // 'xs' => 0, // ! need here as a started point
        ];

        $gutters = [
          'xl' => 12,
          'lg' => 12,
          'md' => 12,
          'sm' => 12,
          // 'xs' => 0, // ! need here as a started point
        ];

        // ? default
        // !important keep the descending order : from largest to smallest
        $mediaQueries = [
          'xl' => ['(min-width: %w) and (min-device-pixel-ratio: 2.0)', '(min-width: %w)'],
          'lg' => ['(min-width: %w) and (min-device-pixel-ratio: 2.0)', '(min-width: %w)'],
          'md' => ['(min-width: %w) and (min-device-pixel-ratio: 2.0)', '(min-width: %w)'],
          'sm' => ['(min-width: %w) and (min-device-pixel-ratio: 2.0)', '(min-width: %w)'],
          // 'xs' => [], // ! keep empty
        ];

        if (isset($customGutter) && $customGutter)
        {
            // with custom global gutters (for all)
            $scaling = current($breakpointsMax) - ($customGutter * 2);
        }
        else if (isset($customGutter) && $customGutter === false)
        {
            // without gutters
            $scaling = false;
        }
        else
        {
            // base gutters (default)
            $scaling = current($breakpointsMax) - (current($gutters) * 2);
        }

        if ($scaling && $scaling != $intrinsicWidth)
        {
            $scalingRatio = $scaling / $intrinsicWidth;
        }

        $sizes = ArrayList::create();

        foreach($breakpoints as $bp => $w)
        {
            $mqImageW = $breakpointsMax[$bp]; // $w

            $mqImageW -= ($gutters[$bp] * 2); // $w

            // << Modifications

            if (isset($scalingRatio) && (!isset($customGutter) || $customGutter !== false))
            {
                // with custom global gutters (for all) && base gutters (default)
                $mqImageW = round($mqImageW / $scalingRatio);
            }

            // Modifications >>

            if (isset($mediaQueries[$bp]))
            {
                foreach($mediaQueries[$bp] as $mq)
                {
                    if ($this->hasRIOption('bpw'))
                    {
                        if (isset($this->getRIOption('bpw')[$bp]))
                        {
                            $mqImageW = $this->getRIOption('bpw')[$bp];
                        }
                    }

                    $mqImageH = round($mqImageW / $intrinsicRatio);

                    if ($this->hasRIOption('bph'))
                    {
                        if (isset($this->getRIOption('bph')[$bp]))
                        {
                            $mqImageH = $this->getRIOption('bph')[$bp];
                        }
                    }

                    $sizes->push(ArrayData::create([
                      'Breakpoint' => $bp,
                      'Image' => $this->owner->FocusFill($mqImageW, $mqImageH),
                      'MediaQuery' => $this->getSourceMediaQuery($mq, $w),
                      // Scale
                    ]));
                }
            }
        }

        // Default image (the max width before the first specified breakpoint), it is assumed that below the first breakpoint, the image is treated as width: 100% within its container
        $defaultWidth = (end($breakpoints) - 1);

        // Check if custom xs supplied, and use it instead if so
        if ($this->hasRIOption('bpw'))
        {
            if (isset($this->getRIOption('bpw')['xs']))
            {
                $defaultWidth = $this->getRIOption('bpw')['xs'];
            }
        }

        $defaultHeight = round($defaultWidth / $intrinsicRatio);

        if ($this->hasRIOption('bph'))
        {
            if (isset($this->getRIOption('bph')['xs']))
            {
                $defaultHeight = $this->getRIOption('bph')['xs'];
            }
        }

        // Placeholder image

        $placeholderWidth = 80;
        $placeholderHeight = round($placeholderWidth / $intrinsicRatio);

        return $this->owner->customise([
          'Sizes' => $sizes,
          'DefaultImage' => $this->owner->FocusFill($defaultWidth, $defaultHeight),
          'DefaultImagePlaceholder' => $this->owner->FocusFill($placeholderWidth, $placeholderHeight),
        ])->renderWith(['type' => 'Includes', 'ResponsiveImage']);
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
