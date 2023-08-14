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
          'lossy' => 2,
          'convertto' => '+webp|+avif',
        ]);

        $images = Image::get();

        $imageTotalOptimized = 0;
        $imageVariantOptimized = 0;

        if ($images->Count())
        {
            // $sha1FileHash = new Sha1FileHashingService;

            foreach($images as $image)
            {
                dd($image->File->setFromString());
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

                    $filepath = pathinfo($image->getUrl(), PATHINFO_DIRNAME);
                    $filename = pathinfo($image->getUrl(), PATHINFO_FILENAME);
                    $extension = pathinfo($image->getUrl(), PATHINFO_EXTENSION);

                    // dd($image->getUrl(), $path);

                    $variants = $image->VariantsData();

                    foreach($variants as $variant => $attrs)
                    {
                        if ($attrs['optimized'] == 1)
                        {
                            continue;
                        }

                        $variantURL = $filepath . '/' . $filename . '__' . $variant  . '.' . $extension;

                        $source = fromUrls($variantURL)->toBuffers();

                        if ($source && $source->succeeded[0] && $source->succeeded[0]->Status->Message == 'Success')
                        {
                            $obj = $source->succeeded[0];

                            dd($obj);

                            // $obj->LosslessURL
                            // $obj->LossyURL

                            // $obj->WebPLosslessURL
                            // $obj->WebPLossyURL

                            // $obj->AVIFLosslessURL
                            // $obj->AVIFLossyURL

                            $variants[$variant]['optimized'] = 1;
                            $imageVariantOptimized++;
                        }
                    }

                    $image->Variants = json_encode($variants);
                    $image->write();

                    $imageTotalOptimized++;
                }
            }
        }

        echo 'Variants: ' . $imageVariantOptimized;

        echo $imageTotalOptimized ? 'Images optimized: ' . $imageTotalOptimized : 'Nothing to compress';
    }
}
