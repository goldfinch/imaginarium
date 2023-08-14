<?php

namespace Goldfinch\Imaginarium\Tasks;

use ReflectionMethod;
use ShortPixel\ShortPixel;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;
use SilverStripe\Assets\Storage\Sha1FileHashingService;
use function ShortPixel\fromUrls;

class ImageCompressorBuildTask extends BuildTask
{
    private static $segment = 'ImageCompressor';

    protected $enabled = true;

    protected $title = 'Image compressor';

    protected $description = 'Compress image assets';

    public function run($request)
    {
        $client = new ShortPixel();
        $client->setKey(Environment::getEnv('SHORTPIXEL_API_KEY'));
        $client->setOptions([
          'convertto' => '+webp|+avif',
        ]);

        $images = Image::get();

        $imageOptimized = 0;

        if ($images->Count())
        {
            // $sha1FileHash = new Sha1FileHashingService;

            foreach($images as $image)
            {
                // only published images
                if ($image->canViewStage())
                {
                    /**
                     * - WebPLosslessURL
                     * - AVIFLosslessURL
                     *
                     * (Lossless way too big, not saving that much)
                     *
                     * ! Glossy for webp
                     * ! Lossless for avif
                     *
                     * Glossy good for origin files
                     */


                    $imageOptimized++;
                    // $source = fromUrls($image->getUrl())->toBuffers();
                    // dd($source);
                }
            }
        }

        echo $imageOptimized ? 'Images optimized: ' . $imageOptimized : 'Nothing to compress';
    }
}
