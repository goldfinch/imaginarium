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
use App\FlysystemAssetStore;
use SilverStripe\Assets\Storage\Sha1FileHashingService;
use function ShortPixel\fromUrls;

class ImageCompressorBuildTask extends BuildTask
{

    /**
     * _t.jpg
     * _t.webp
     * _sg
     * _sl
     * _sll
     *
    [
      origin => [
        compressions => [
          tiny => [{name}, {size}],
          tiny-webp => [{name}, {size}],
          spix => [{name}, {size}],
          spix => [{name}, {size}],
          spix-webp => [{name}, {size}],
          spix-avif => [{name}, {size}],
          spix-g-webp => [{name}, {size}],
          spix-g-avif => [{name}, {size}],
        ],
      ],
      variants => [
        [
          created_at => '',
          updated_at => '',
          mime => '',
          width => '',
          height => '',
          size => '',
          fn => '',
          compressions => [
            tiny => [{name}, {size}],
            tiny-webp => [{name}, {size}],
            spix => [{name}, {size}],
            spix => [{name}, {size}],
            spix-webp => [{name}, {size}],
            spix-avif => [{name}, {size}],
            spix-g-webp => [{name}, {size}],
            spix-ll-avif => [{name}, {size}],
          ],
        ],
      ],
    ]
     */

    private static $segment = 'ImageCompressor';

    protected $enabled = true;

    protected $title = 'Image compressor';

    protected $description = 'Compress image assets';

    public function run($request)
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

        Environment::increaseTimeLimitTo(600);
        Environment::increaseMemoryLimitTo(5000);
        // Environment::setTimeLimitMax();
        // Environment::setMemoryLimitMax();

        $quality = 'lossy';

        // ! the lossless return same as lossy, so we ignore it here

        $client = new ShortPixel();
        $client->setKey(Environment::getEnv('SHORTPIXEL_API_KEY'));
        $client->setOptions([
          'lossy' => $quality == 'lossy' ? 1 : 2, // 1 - lossy, 2 - glossy, 0 - lossless
          'convertto' => '+webp|+avif',
          'notify_me' => null,
          'wait' => 300,
          'total_wait' => 300,
        ]);

        $images = Image::get();

        $imageTotalOptimized = 0;
        $imageVariantOptimized = 0;

        if ($images->Count())
        {
            foreach($images as $image)
            {
                $store = Injector::inst()->get(AssetStore::class);
                // $getID = new ReflectionMethod(FlysystemAssetStore::class, 'getFileID');
                // $getID->setAccessible(true);
                $rename = new ReflectionMethod(FlysystemAssetStore::class, 'renameExtr');
                $rename->setAccessible(true);
                // $flyID = $getID->invoke($store, $image->getFilename(), $image->getHash());
                // $getFileSystem = new ReflectionMethod(FlysystemAssetStore::class, 'getFilesystemFor');
                // $getFileSystem->setAccessible(true);
                // $system = $getFileSystem->invoke($store, $flyID);
                // dd($getFileSystem);

                // only published images
                if ($image->canViewStage())
                {
                    $filepath = pathinfo($image->getUrl(), PATHINFO_DIRNAME);
                    $filename = pathinfo($image->getUrl(), PATHINFO_FILENAME);
                    $extension = pathinfo($image->getUrl(), PATHINFO_EXTENSION); // $image->getExtension()

                    $manipulatedData = $image->ManipulatedData();

                    // dd($manipulatedData);

                    $urlsToCompress = [];

                    // Getting origin S3 url (skipping CDN url that won't work with file_get_contents)
                    $store = Injector::inst()->get(AssetStore::class);
                    $getID = new ReflectionMethod(FlysystemAssetStore::class, 'getFileID');
                    $getID->setAccessible(true);
                    $fileID = $getID->invoke($store, $image->Filename, $image->Hash, $image->Variant);
                    $getFileSystem = new ReflectionMethod(FlysystemAssetStore::class, 'getFilesystemFor');
                    $getFileSystem->setAccessible(true);
                    $system = $getFileSystem->invoke($store, $fileID);

                    $adapter = $store->getPublicFilesystem()->getAdapter()->getAdapter();
                    $OriginS3Link = $adapter->getClient()->getObjectUrl($adapter->getBucket(), $adapter->applyPathPrefix($fileID));

                    // https://starter-assets.s3.ap-southeast-2.amazonaws.com/public/WideBig41mb.jpg
                    // https://d2x03rcss2l1f5.cloudfront.net/public/WideBig41mb.jpg (CloudFront blocks get_contents)

                    // dd(
                    //   // $image->getUrl()
                    //   // sha1_file('file:///Users/art/Desktop/thumbnail.jpeg')
                    //   sha1(file_get_contents($OriginS3Link))
                    // );

                    // 1) Need to know what compression are about to happen, so that we can check HASH and Signature in CompressedImage and skip the url

                    // condition : original
                    $urlsToCompress[] = $image->getUrl();

                    foreach($manipulatedData as $variant => $attrs)
                    {
                        $urlsToCompress[] = $filepath . '/' . $filename . '__' . $variant  . '.' . $extension;
                    }

                    $ShortPixelResponse = fromUrls($urlsToCompress)->toBuffers();

                    dd($ShortPixelResponse);

                    if (
                      $ShortPixelResponse &&
                      $ShortPixelResponse->succeeded[0] &&
                      $source->succeeded[0]->Status->Message == 'Success'
                    )
                    {
                        $cfg = ['conflict' => AssetStore::CONFLICT_OVERWRITE];
                        $image_hash = $image->getHash();
                        $image_filename = $image->File->getFilename();

                        foreach($ShortPixelResponse->succeeded as $compressedItem)
                        {
                            // 1) Origin - LossyURL
                            $url = file_get_contents($compressedItem->LossyURL);

                            $image->File->setFromString($url, $image_filename, $image_hash, $variant, $cfg);
                            $manipulatedData[$variant]['optimized_size'] = $compressedItem->LossySize;
                            $manipulatedData[$variant]['optimized'] = 1;

                            // 2) WebP - Lossy
                            $url = file_get_contents($compressedItem->WebPLossyURL);

                            $image->File->setFromString($url, $image_filename, $image_hash, $variant.'_webp', $cfg);
                            $manipulatedData[$variant]['webp'] = $compressedItem->WebPLossySize;

                            // rename _webp > .webp
                            $rename->invoke($store, $filename . '__' . $variant.'_webp.'.$extension, $image_hash, $variant.'.webp', $image_filename, $store);

                            // 3) Avif - Lossless
                            $url = file_get_contents($compressedItem->AVIFLosslessURL);

                            $image->File->setFromString($url, $image_filename, $image_hash, $variant.'_avif', $cfg);
                            $manipulatedData[$variant]['avif'] = $compressedItem->AVIFLosslessSize;

                            // $obj->LosslessURL
                            // $obj->LossyURL

                            // $obj->WebPLosslessURL
                            // $obj->WebPLossyURL

                            // $obj->AVIFLosslessURL
                            // $obj->AVIFLossyURL
                            $imageVariantOptimized++;
                        }
                    }

                    dd($ShortPixelResponse);

                    exit;
                    foreach($manipulatedData as $variant => $attrs)
                    {
                        if ($attrs['optimized'] == 1)
                        {
                            continue;
                        }

                        $variantURL = $filepath . '/' . $filename . '__' . $variant  . '.' . $extension;

                        $source = fromUrls($variantURL)->toBuffers();

                        if ($source && $source->succeeded[0] && $source->succeeded[0]->Status->Message == 'Success')
                        {
                            $cfg = ['conflict' => AssetStore::CONFLICT_OVERWRITE];
                            $image_hash = $image->getHash();
                            $image_filename = $image->File->getFilename();

                            $obj = $source->succeeded[0];

                            // 1) Origin - LossyURL
                            $url = file_get_contents($obj->LossyURL);

                            $image->File->setFromString($url, $image_filename, $image_hash, $variant, $cfg);

                            // $imagesize = getimagesize($url);

                            // $size_in_bytes = (int) (strlen(rtrim($url, '=')) * 3 / 4);
                            // $headers = get_headers($url, 1);

                            // $manipulatedData[$variant]['optimized_size'] = $headers['Content-Length']; // $size_in_bytes;
                            $manipulatedData[$variant]['optimized'] = 1;

                            // 2) WebP - Lossy
                            $url = file_get_contents($obj->WebPLossyURL);
                            $image->File->setFromString($url, $image_filename, $image_hash, $variant.'_webp', $cfg);

                            $rename->invoke($store, $filename . '__' . $variant.'_webp.'.$extension, $image_hash, $variant.'.webp', $image_filename, $store);

                            // $size_in_bytes = (int) (strlen(rtrim($url, '=')) * 3 / 4);
                            // $headers = get_headers($url, 1);

                            // $manipulatedData[$variant]['webp'] = $headers['Content-Length']; // $size_in_bytes;

                            // 3) Avif - Lossless
                            $url = file_get_contents($obj->AVIFLosslessURL);
                            $image->File->setFromString($url, $image_filename, $image_hash, $variant.'_avif', $cfg);

                            // $size_in_bytes = (int) (strlen(rtrim($url, '=')) * 3 / 4);
                            // $headers = get_headers($url, 1);

                            // $manipulatedData[$variant]['avif'] = $headers['Content-Length']; // $size_in_bytes;

                            // $obj->LosslessURL
                            // $obj->LossyURL

                            // $obj->WebPLosslessURL
                            // $obj->WebPLossyURL

                            // $obj->AVIFLosslessURL
                            // $obj->AVIFLossyURL
                            $imageVariantOptimized++;
                        }
                    }

                    $image->Variants = json_encode($manipulatedData);
                    $image->write();

                    $imageTotalOptimized++;
                }
            }
        }

        echo 'Variants: ' . $imageVariantOptimized;

        echo $imageTotalOptimized ? 'Images optimized: ' . $imageTotalOptimized : 'Nothing to compress';
    }
}
