<?php

namespace Goldfinch\Imaginarium\Views;

use SilverStripe\View\ViewableData;
use NicoVerbruggen\ImageGenerator\ImageGenerator;

class ImagePlaceholder extends ViewableData
{
    private $generator;

    public function __construct($args)
    {
        $width = isset($args[0]) ? $args[0] : null;
        $height = isset($args[1]) ? $args[1] : null;

        if ($width && $height) {

            $path = BASE_PATH . '/vendor/goldfinch/image-generator/fonts/OpenSans.ttf';
            $size = $width . 'x' . $height;

            $this->generator = new ImageGenerator(
                targetSize: $size,
                textColorHex: '#999',
                backgroundColorHex: '#EEE',
                fontPath: $path,
                fontSize: 30
            );
        }
    }

    public function Dimensions()
    {
        return $this->generator->targetSize;
    }

    public function Link()
    {
        return $this->URL();
    }

    public function URL()
    {
        return $this->generator->generate($this->generator->targetSize);
    }

    public function forTemplate()
    {
        return $this->renderWith('Views/ImagePlaceholder');
    }
}
