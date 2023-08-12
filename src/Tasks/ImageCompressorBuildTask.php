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

                    $asetValues = $image->File->getValue();
                    $store = Injector::inst()->get(AssetStore::class);

                    $getID = new ReflectionMethod(FlysystemAssetStore::class, 'getFileID');
                    $getID->setAccessible(true);
                    $flyID = $getID->invoke($store, $asetValues['Filename'], $asetValues['Hash']);
                    $getFileSystem = new ReflectionMethod(FlysystemAssetStore::class, 'getFilesystemFor');
                    $getFileSystem->setAccessible(true);

                    $system = $getFileSystem->invoke($store, $flyID);

                    $findVariants = new ReflectionMethod(FlysystemAssetStore::class, 'findVariants');
                    $findVariants->setAccessible(true);

                    foreach ($findVariants->invoke($store, $flyID, $system) as $variant)
                    {
                        $isGenerated = strpos($variant, '__');
                        if (!$isGenerated) {
                            continue;
                        }
                        // $system->delete($variant);
                    }

                    // $source = fromUrls($image->getUrl())->toBuffers();
                    // dd($source);
                }
            }
        }

        echo $imageCount ? 'Images tinified: ' . $imageCount : 'Nothing to compress';
    }
}
