<?php

namespace Goldfinch\Imaginarium\Tasks;

use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Assets\Storage\Sha1FileHashingService;

class ImageCompressorBuildTask extends BuildTask
{
    private static $segment = 'ImageCompressor';

    protected $enabled = true;

    protected $title = 'Image compressor';

    protected $description = 'Compress image assets';

    public function run($request)
    {
        $client = new Tinify();
        $client->setKey(Environment::getEnv('SHORTPIXEL_API_KEY'));

        $untinifiedImages = Image::get()->filter('Tinified', 0);

        $imageCount = 0;

        if ($untinifiedImages->Count())
        {
            // $sha1FileHash = new Sha1FileHashingService;

            foreach($untinifiedImages as $image)
            {
                // only published images
                if ($image->canViewStage())
                {
                    //
                }
            }
        }

        echo $imageCount ? 'Images tinified: ' . $imageCount : 'Nothing to compress';
    }
}
