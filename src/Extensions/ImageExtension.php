<?php

namespace Goldfinch\Imaginarium\Extensions;

use SilverStripe\ORM\ArrayList;
use SilverStripe\Core\Extension;
use SilverStripe\View\ArrayData;
use DorsetDigital\CDNRewrite\CDNMiddleware;
use Goldfinch\Imaginarium\Views\ImagePlaceholder;

class ImageExtension extends Extension
{
    private $RIOptions = null;

    public function LazyFocusFill(int $width, int $height, $lazyloadTag = true, $object = false)
    {
        return $this->Lazy(
            $this->owner->FocusFill($width, $height),
            $lazyloadTag,
            $object
        );
    }

    public function Placeholder($fn, ...$args)
    {
        $return = $this->owner->$fn(...$args);

        if (!$return) {
            return ImagePlaceholder::create($args);
        }

        return $return;
    }

    public function LazyFocusFillMax(
        int $width,
        int $height,
        $lazyloadTag = true,
        $object = false
    ) {
        return $this->Lazy(
            $this->owner->FocusFillMax($width, $height),
            $lazyloadTag,
            $object
        );
    }

    public function LazyFitMax(int $width, int $height, $lazyloadTag = true, $object = false)
    {
        return $this->Lazy(
            $this->owner->FitMax($width, $height),
            $lazyloadTag,
            $object
        );
    }

    public function LazyFocusCropWidth(int $width, $lazyloadTag = true, $object = false)
    {
        return $this->Lazy(
            $this->owner->FocusCropWidth($width),
            $lazyloadTag,
            $object
        );
    }

    public function LazyFocusCropHeight(int $height, $lazyloadTag = true, $object = false)
    {
        return $this->Lazy(
            $this->owner->FocusCropHeight($height),
            $lazyloadTag,
            $object
        );
    }

    protected function Lazy($file, $lazyloadTag = true, $object = false)
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

        $thumbnail = $file->owner->FocusFill(
            (int) $thumbnailWidth,
            (int) $thumbnailHeight,
        );

        if (!$thumbnail) {
            $thumbnail = $file->owner->Fill(
                (int) $thumbnailWidth,
                (int) $thumbnailHeight,
            );
        }

        if ($thumbnail) {
            $file = $file->setAttribute('src', $thumbnail->getURL());
            $file = $file->setAttribute('data-loaded', 'false');
            $file = $file->setAttribute('data-src', $url);
        }

        if (!$lazyloadTag) {
            $file = $file->setAttribute('loading', false);
        }

        if ($object) {
            $file = $file->setAttribute('style', 'object-position: '.$file->FocusPoint->PercentageX().'% '.$file->FocusPoint->PercentageY().'%');
        }

        return $file;
    }

    private function setRIoptions($options)
    {
        if ($options) {
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

        if ($options && isset($options[$prop])) {
            return true;
        }

        return false;
    }

    private function getRIOption($prop)
    {
        $options = $this->getRIoptions();

        if ($options && isset($options[$prop])) {
            return $options[$prop];
        }

        return null;
    }

    public function Responsive($ratio, $sizes, $options = null)
    {
        $expl = explode(':', $ratio);

        $intrinsicWidth = (int) $expl[0];
        $intrinsicHeight = (int) $expl[1];

        $sizes = explode(',', $sizes);
        $formatedSizes = [];

        array_map(function ($w) use (&$formatedSizes) {
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

        if ($this->hasRIOption('manipulation')) {
            $manipulation = $this->getRIOption('manipulation');
        } else {
            $manipulation = 'FocusFill';
        }

        $intrinsicRatio = $intrinsicWidth / $intrinsicHeight;

        $sizes = ArrayList::create();

        $firstImage = null;

        foreach ($formatedSizes as $bp => $width) {
            $height = (int) round($width / $intrinsicRatio);

            if (in_array($manipulation, $singleWidth)) {
                if ($bp === 0) {
                    $sizedImage = $this->owner->$manipulation($width);
                } else {
                    $sizedImage = $this->owner
                        // ->EscapeF()
                        ->$manipulation($width);
                }
            } elseif (in_array($manipulation, $singleHeight)) {
                if ($bp === 0) {
                    $sizedImage = $this->owner->$manipulation($height);
                } else {
                    $sizedImage = $this->owner
                        // ->EscapeF()
                        ->$manipulation($height);
                }
            } else {
                if ($bp === 0) {
                    $sizedImage = $this->owner->$manipulation($width, $height);
                } else {
                    $sizedImage = $this->owner
                        // ->EscapeF()
                        ->$manipulation($width, $height);
                }
            }

            if ($bp === 0) {
                $firstImage = $sizedImage;
                continue;
            }

            $mediaQuery = '(min-width: ' . $bp . 'px)'; // and (min-device-pixel-ratio: 2.0)';

            // $sizedImageAvif = $this->Avif($sizedImage);
            // $sizedImageWebp = $this->Webp($sizedImage);

            $sizes->push(
                ArrayData::create([
                    'Image' => $sizedImage,
                    // 'ImageAvif' => $sizedImageAvif,
                    // 'ImageWebp' => $sizedImageWebp,
                    'MediaQuery' => $mediaQuery,
                ]),
            );
        }

        // Placeholder image

        $placeholderWidth = 80;
        $placeholderHeight = round($placeholderWidth / $intrinsicRatio);

        if (in_array($manipulation, $singleWidth)) {
            $placeholderImage = $this->owner
                // ->EscapeF()
                ->$manipulation($placeholderWidth);
        } elseif (in_array($manipulation, $singleHeight)) {
            $placeholderImage = $this->owner
                // ->EscapeF()
                ->$manipulation($placeholderHeight);
        } else {
            $placeholderImage = $this->owner
                // ->EscapeF()
                ->$manipulation($placeholderWidth, $placeholderHeight);
        }

        /**
         *
         * -- decoding=(sync/async/auto)
         * There are 3 accepted values for the decoding attribute:
         *
         *  sync: the rendering will continue only after the image is ready; preferred for a "complete experience"
         *  async: continue the rendering and as soon as image decoding is complete, the browser will update the presentation; preferred for performance
         *  auto: will let the browser do what it determines is best approach (not sure who it decides that)
         *
         *
         * -- fetchpriority=(high/low)
         * https://web.dev/articles/fetch-priority
         *
         */

        $cdnState = $this->hasRIOption('cdn') ?? true;
        $cdnSuffix = '';

        if ($cdnState && $cdnState != 'false') {
            $cdnSuffix = class_exists(CDNMiddleware::class) ? CDNMiddleware::config()->get('cdn_domain') : '';
        }

        return $this->owner
            ->customise([
                'Sizes' => $sizes,
                'CDNSuffix' => $cdnSuffix,
                'FirstImage' => $firstImage,
                'PlaceholderImage' => $placeholderImage,
                'Lazy' => $this->hasRIOption('lazy')
                    ? $this->getRIOption('lazy')
                    : true,
                'LazyLoadingTag' => $this->hasRIOption('loadingtag')
                    ? $this->getRIOption('loadingtag')
                    : true,
                'FetchPriorityTag' => $this->hasRIOption('fetchpriority')
                    ? $this->getRIOption('fetchpriority')
                    : false,
                'DecodingTag' => $this->hasRIOption('decoding')
                    ? $this->getRIOption('decoding')
                    : 'async',
            ])
            ->renderWith(['Layout' => 'Goldfinch/Imaginarium/Responsive']);
    }
}
