<?php

namespace Goldfinch\Imaginarium\Extensions;

use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\Core\Extension;
use PhpTek\JSONText\ORM\FieldType\JSONText;

class ImageExtension extends Extension
{
    private static $db = [
        'Optimized' => 'Boolean',
        'Variants' => JSONText::class,
    ];

    private $RIOptions = null;

    public function VariantsData()
    {
        return new ArrayData($this->dbObject('Variants')->getStoreAsArray());
    }

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

        // !important keep the descending order : from largest to smallest
        $breakpoints = $this->owner->config()->get('imaginarium')['breakpoints'];

        $breakpointsMax = $this->owner->config()->get('imaginarium')['breakpointsMax'];

        $gutters = $this->owner->config()->get('imaginarium')['gutters'];

        // !important keep the descending order : from largest to smallest
        $mediaQueries = $this->owner->config()->get('imaginarium')['mediaQueries'];

        // register new Breakpoints
        if ($this->hasRIOption('newbp'))
        {
            $newBps = [];

            foreach ($this->getRIOption('newbp') as $bp => $v)
            {
                $breakpoints[$bp] = $v['bp'];

                if (isset($v['bpmax']))
                {
                    $breakpointsMax[$bp] = $v['bpmax'];
                }
                else
                {
                    $breakpointsMax[$bp] = $v['bp'];
                }

                // check alias, refer to existing
                if (isset($mediaQueries[$v['mq']]))
                {
                    $mediaQueries[$bp] = $mediaQueries[$v['mq']];
                }
                else
                {
                    $mediaQueries[$bp] = $v['mq'];
                }

                // check alias, refer to existing
                if (isset($gutters[$v['g']]))
                {
                    $gutters[$bp] = $gutters[$v['g']];
                }
                else
                {
                    $gutters[$bp] = $v['g'];
                }
            }
        }

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

                    if ($this->hasRIOption('scale'))
                    {
                        $mqImageW = round($mqImageW + ($mqImageW / 100 * $this->getRIOption('scale')));
                        $mqImageH = round($mqImageH + ($mqImageH / 100 * $this->getRIOption('scale')));
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

        $baseBreakpoint = array_search(0, $breakpoints);
        $defaultWidth = min(array_filter($breakpoints));

        // Check if custom xs supplied, and use it instead if so
        if ($this->hasRIOption('bpw'))
        {
            if (isset($this->getRIOption('bpw')[$baseBreakpoint]))
            {
                $defaultWidth = $this->getRIOption('bpw')[$baseBreakpoint];
            }
        }

        $defaultHeight = round($defaultWidth / $intrinsicRatio);

        if ($this->hasRIOption('bph'))
        {
            if (isset($this->getRIOption('bph')[$baseBreakpoint]))
            {
                $defaultHeight = $this->getRIOption('bph')[$baseBreakpoint];
            }
        }

        // Placeholder image

        $placeholderWidth = 80;
        $placeholderHeight = round($placeholderWidth / $intrinsicRatio);

        return $this->owner->customise([
          'Sizes' => $sizes,
          'DefaultImage' => $this->owner->FocusFill($defaultWidth, $defaultHeight),
          'DefaultImagePlaceholder' => $this->owner->FocusFill($placeholderWidth, $placeholderHeight),
          'Lazy' => $this->hasRIOption('lazy') ? $this->getRIOption('lazy') : true,
          'LazyLoadingTag' => $this->hasRIOption('loadingtag') ? $this->getRIOption('loadingtag') : true,
        ])->renderWith(['Layout' => 'Goldfinch/Imaginarium/ResponsiveImage']);
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
