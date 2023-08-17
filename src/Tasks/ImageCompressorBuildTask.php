<?php

namespace Goldfinch\Imaginarium\Tasks;

use ReflectionMethod;
use ShortPixel\ShortPixel;
use App\FlysystemAssetStore;
use SilverStripe\Assets\Image;
use App\Models\CompressedImage;
use SilverStripe\Dev\BuildTask;
use ShortPixel\AccountException;
use function ShortPixel\fromUrls;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Storage\Sha1FileHashingService;

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
      source => [
        compressions => [
          tiny => [{name}, {hash}, {size}],
          tiny-webp => [{name}, {hash}, {size}],
          spix => [{name}, {hash}, {size}],
          spix => [{name}, {hash}, {size}],
          spix-w => [{name}, {hash}, {size}],
          spix-a => [{name}, {hash}, {size}],
          spix-g-webp => [{name}, {hash}, {size}],
          spix-g-avif => [{name}, {hash}, {size}],
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
          hash => '',
          compressions => [
            t => [{name}, {hash}, {size}],
            t-w => [{name}, {hash}, {size}],
            s => [{name}, {hash}, {size}],
            s => [{name}, {hash}, {size}],
            s-w => [{name}, {hash}, {size}],
            s-a => [{name}, {hash}, {size}],
            s-g-w => [{name}, {hash}, {size}],
            s-ll-a => [{name}, {hash}, {size}],
          ],
        ],
      ],
    ]
     */

    private static $segment = 'ImageCompressor';

    protected $enabled = true;

    protected $title = 'Image compressor';

    protected $description = 'Compress image assets';

    protected function generatePatterns($compressor, $branches, $compressionTypes, $quality)
    {
        $stack = [];

        foreach ($branches as $branch => $converts)
        {
            foreach ($compressionTypes as $type)
            {
                foreach ($converts as $format)
                {
                    $stack[$branch][] = $compressor . '_' . $type . '_' . $format;
                }
            }
        }
        // foreach ($branches as $key => $branch)
        // {
        //     foreach ($branch as $kc => $convert)
        //     {
        //         if ($convert == 'webp')
        //         {
        //             $c = 'w';
        //         }
        //         else if ($convert == 'avif')
        //         {
        //             $c = 'a';
        //         }

        //         $branches[$key][$kc] = $pattern . '_' . $c;
        //         // dd($pattern,$branches, $compressor, $key, $branch, $convert);
        //     }
        // }

        return $stack;
    }

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

        // config settings
        // $compressor = 'sp'; // sp - shortpixel, tp - tinypng
        // $compressionTypes = ['lossy', 'glossy', 'lossless'];
        // $compressionFormats = ['origin', 'webp', 'avif'];

        /**
         * Compression rules:
         *
         * sp[origin][lossy]
         * sp[avif][lossless]
         * sp[webp][glossy,lossy]
         *
         * tp[origin]
         * tp[webp]
         *
         * sc[webp]
         *
         * ----
         *
         * Compression:
         *
         * sp-origin-lossy
         * tp-webp
         * sc-webp
         *
         */

        // Compression rules from config (yml)
        // $compressionRules = CompressedImage::config();

        // dd($compressionRules->get('compression_rules'));
        // dd(CompressedImage::getSourceCompressionRules());

        // sub config
        // $quality = 'lossy'; // TODO: should be based on $compressionTypes
        // $_spExtraFormats = ['webp', 'avif'];
        // $convertto = '';

        // foreach ($compressionFormats as $format)
        // {
        //     if (in_array($format, $_spExtraFormats))
        //     {
        //         if ($convertto != '') $convertto .= '|';

        //         $convertto .= '+' . $format;
        //     }
        // }

        // ! the lossless return same as lossy, so we ignore it here

        $spLossy = 'lossy'; // 1 - lossy, 2 - glossy, 0 - lossless
        $spConvertto = '+webp|+avif';
        $client = new ShortPixel();
        $client->setKey(Environment::getEnv('SHORTPIXEL_API_KEY'));
        $client->setOptions([
          'lossy' => $spLossy == 'lossy' ? 1 : 2, // 1 - lossy, 2 - glossy, 0 - lossless
          'convertto' => $spConvertto,
          'notify_me' => null,
          'wait' => 300,
          'total_wait' => 300,
        ]);

        $images = Image::get();

        $imageTotalOptimized = 0;
        $imageVariantOptimized = 0;

        // $exConvertto = explode('|', str_replace('+', '', $convertto));

        // $branches = [
        //   'original' => $exConvertto,
        //   'variant' => $exConvertto,
        // ];

        // $patterns = $this->generatePatterns($compressor, $branches, $compressionTypes, $quality);

        // dd($patterns);

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
                // dd($image->getUrl());
                if ($image->canViewStage() && $image->getUrl())
                {
                    $filepath = pathinfo($image->getUrl(), PATHINFO_DIRNAME);
                    $filename = pathinfo($image->getUrl(), PATHINFO_FILENAME);
                    $extension = pathinfo($image->getUrl(), PATHINFO_EXTENSION); // $image->getExtension()

                    $manipulatedData = $image->ManipulatedData();

                    $urlsToCompress = [];

                    // https://starter-assets.s3.ap-southeast-2.amazonaws.com/public/WideBig41mb.jpg
                    // https://d2x03rcss2l1f5.cloudfront.net/public/WideBig41mb.jpg (CloudFront blocks get_contents)

                    // dd(
                    //   // $image->getUrl()
                    //   // sha1_file('file:///Users/art/Desktop/thumbnail.jpeg')
                    //   sha1(file_get_contents($OriginS3Link))
                    // );

                    // 1) Need to know what compression are about to happen, so that we can check HASH and Signature in CompressedImage and skip the url

                    // CompressedImage::checkCompression(sha1(file_get_contents($OriginS3Link)));

                    // condition : original
                    // if there are any compresison rules that haven't been applied to the source file, then we want to include it
                    $missedSourceCompressions = CompressedImage::checkSourceCompression($image, 'out');

                    if (count($missedSourceCompressions))
                    {
                        $urlsToCompress[] = [
                          'url' => $image->getUrl(),
                          'extension' => $image->getExtension(),
                          'filename' => current(explode('.', $image->getFilename())),
                          'variant' => '',
                          'hash' => $image->getHash(),
                          'compressions' => $missedSourceCompressions,
                          'type' => 'source',
                        ];
                    }

                    foreach($manipulatedData['variants'] as $variant => $attrs)
                    {
                        $variantUrl = $filepath . '/' . $filename . '__' . $variant  . '.' . $extension;
                        $missedCompressions = CompressedImage::checkVariantCompression($attrs, $variantUrl, $image, 'out');

                        if (count($missedCompressions))
                        {
                            $urlsToCompress[] = [
                              'url' => $variantUrl,
                              'extension' => $extension,
                              'filename' => $filename,
                              'variant' => $variant,
                              'hash' => isset($attrs['hash']) ? $attrs['hash'] : null,
                              'compressions' => $missedCompressions,
                              'type' => 'variant',
                            ];
                        }
                    }
                    // dd($urlsToCompress);
                    $urlsToCompress = collect($urlsToCompress);
                    $_SESSION['shortpixel-test'] = null;
                    // DEBUGGING
                    if ($urlsToCompress->count())
                    {
                        try
                        {
                            if (!isset($_SESSION['shortpixel-test']))
                            {
                                $ShortPixelResponse = fromUrls($urlsToCompress->pluck('url')->all())->toBuffers();
                                $_SESSION['shortpixel-test'] = serialize($ShortPixelResponse);
                            }
                            else
                            {
                                $ShortPixelResponse = unserialize($_SESSION['shortpixel-test']);
                            }
                        } catch (\ShortPixel\AccountException $e) {
                            dd($e->getMessage());
                        }
                    }
                    // dd($ShortPixelResponse);

                    // $currentVariant = $urlsToCompress->where('url', $variantUrl)->first();

                    // $ss = collect($currentVariant['compressions'])->reject(function ($item) {
                    //     return strpos($item, 'origin') === false;
                    // });

                    // foreach($ss as $s)
                    // {
                    //   dd($s);
                    // }

                    // dd(collect($currentVariant['compressions'])->reject(function ($item) {
                    //     return strpos($item, 'origin') === false;
                    // })->count());
                      // dd($ShortPixelResponse);
                    if (!isset($ShortPixelResponse))
                    {
                        continue;
                    }

                    if (count($ShortPixelResponse->pending))
                    {
                        // skip unfull request that is pending
                        echo 'pending';
                        continue;
                    }

                    if (
                      property_exists($ShortPixelResponse, 'succeeded') &&
                      count($ShortPixelResponse->succeeded)
                    )
                    {
                        $cfg = ['conflict' => AssetStore::CONFLICT_OVERWRITE];
                        $image_hash = $image->getHash();
                        $image_filename = $image->File->getFilename();

                        // dd($urlsToCompress , $ShortPixelResponse->succeeded);

                        foreach ($ShortPixelResponse->succeeded as $item)
                        {
                            if ($item->Status->Message == 'Success')
                            {
                                if ($image->getUrl() === $item->OriginalURL)
                                {
                                    // if true, this loop cycle is for Source
                                    $isSource = true;
                                    $isVariant = false;
                                }
                                else
                                {
                                    // if not true, this loop cycle is for Variant
                                    $isSource = false;
                                    $isVariant = true;
                                }

                                $currentVariant = $urlsToCompress->where('url', $item->OriginalURL)->first();
                                // dd(currentVariant);

                                $originCompressions = collect($currentVariant['compressions'])->reject(function ($item) {
                                    return strpos($item, 'origin') === false;
                                });

                                $webpCompressions = collect($currentVariant['compressions'])->reject(function ($item) {
                                    return strpos($item, 'webp') === false;
                                });

                                $avifCompressions = collect($currentVariant['compressions'])->reject(function ($item) {
                                    return strpos($item, 'avif') === false;
                                });

                                // 1 - Origin (of the variant)

                                if ($originCompressions->count())
                                {
                                    foreach($originCompressions as $c)
                                    {
                                        $url = null;
                                        $size = null;

                                        if (strpos($c, 'lossy') !== false && $spLossy == 'lossy')
                                        {
                                            $url = $item->LossyURL;
                                            $size = $item->LossySize;
                                            // $compressionName = $currentVariant['variant'];
                                        }
                                        else if (strpos($c, 'glossy') !== false && $spLossy == 'glossy')
                                        {
                                            // ! the glossy data here is under Lossy
                                            $url = $item->LossyURL;
                                            $size = $item->LossySize;
                                            // $compressionName = $currentVariant['variant'];
                                        }
                                        else if (strpos($c, 'lossless') !== false)
                                        {
                                            $url = $item->LosslessURL;
                                            $size = $item->LosslessSize;
                                            // $compressionName = $currentVariant['variant'];
                                        }

                                        if ($url && $size)
                                        {
                                            $imageData = file_get_contents($url);

                                            $variant = $currentVariant['variant'];
                                            $currentHash = sha1($imageData);

                                            $variant_name = $variant . '[' . $c . ']';
                                            // $variant_name = '[' . $c . ']';

                                            // pass through some data via $cfg
                                            $cfg['flydata'] = [
                                              'compression' => $c,
                                              'size' => $size,
                                              'hash' => $currentHash,
                                              'type' => 'origin',
                                              'current_variant_type' => $currentVariant['type'],
                                              'variant_name' => $variant_name,
                                            ];

                                            // ! Check when debugging - $currentVariant['variant'], could be unnecessary
                                            $image->File->setFromString($imageData, $image_filename, $image_hash, $variant, $cfg);

                                            $newCompression = new CompressedImage;
                                            $newCompression->Hash = $currentHash; // ! see if we need hash of the compressed image at all
                                            // $newCompression->Filename = $compressionName;
                                            $newCompression->Compression = $c;
                                            $newCompression->Size = $size;
                                            $newCompression->Parent = $currentVariant['hash'];
                                            $newCompression->Source = $image->getHash();
                                            $newCompression->write();

                                            // var_dump($variant_name, $currentVariant['filename'], $image_filename, 'origin', $item->OriginalURL, json_encode($currentVariant));

                                            // rename origin
                                            $rename->invoke(
                                              $store,
                                              $currentVariant['filename'] . '__' . $variant_name . '.' . $extension,
                                              $image_hash,
                                              $variant . '.' . $currentVariant['extension'],
                                              $image_filename,
                                              $store,
                                              $image,
                                              $isSource,
                                              $variant,
                                              $currentHash
                                            );
                                        }
                                    }
                                }

                                // 2 - WebP

                                if ($webpCompressions->count() && strpos($spConvertto, 'webp') !== false)
                                {
                                    foreach($webpCompressions as $c)
                                    {
                                        $url = null;
                                        $size = null;

                                        if (strpos($c, 'lossy') !== false && $spLossy == 'lossy')
                                        {
                                            $url = $item->WebPLossyURL;
                                            $size = $item->WebPLossySize;
                                            // $compressionName = $currentVariant['variant'];
                                        }
                                        else if (strpos($c, 'glossy') !== false && $spLossy == 'glossy')
                                        {
                                            // ! the glossy data here is under Lossy
                                            $url = $item->WebPLossyURL;
                                            $size = $item->WebPLossySize;
                                            // $compressionName = $currentVariant['variant'];
                                        }
                                        else if (strpos($c, 'lossless') !== false)
                                        {
                                            $url = $item->WebPLosslessURL;
                                            $size = $item->WebPLosslessSize;
                                            // $compressionName = $currentVariant['variant'];
                                        }

                                        if ($url && $size)
                                        {
                                            $imageData = file_get_contents($url);

                                            $variant = $currentVariant['variant'];
                                            $currentHash = sha1($imageData);

                                            $variant_name = $variant . '[' . $c . ']';

                                            // pass through some data via $cfg
                                            $cfg['flydata'] = [
                                              'compression' => $c,
                                              'size' => $size,
                                              'hash' => $currentHash,
                                              'type' => 'variant',
                                              'current_variant_type' => $currentVariant['type'],
                                              'variant_name' => $variant_name,
                                            ];

                                            // ! Check when debugging - $currentVariant['variant'], could be unnecessary
                                            $image->File->setFromString($imageData, $image_filename, $image_hash, $variant, $cfg);

                                            $newCompression = new CompressedImage;
                                            $newCompression->Hash = $currentHash; // ! see if we need hash of the compressed image at all
                                            // $newCompression->Filename = sha1($currentHash . $c);
                                            $newCompression->Compression = $c;
                                            $newCompression->Size = $size;
                                            $newCompression->Parent = $currentVariant['hash'];
                                            $newCompression->Source = $image->getHash();
                                            $newCompression->write();

                                            // var_dump($variant_name, $currentVariant['filename'], $image_filename, 'webp', $item->OriginalURL, json_encode($currentVariant));

                                            // rename _webp > .webp
                                            $rename->invoke(
                                              $store,
                                              $currentVariant['filename'] . '__' . $variant_name . '.' . $extension,
                                              $image_hash,
                                              $variant . '.webp',
                                              $image_filename,
                                              $store,
                                              $image,
                                              $isSource,
                                              $variant,
                                              $currentHash
                                            );
                                        }
                                    }
                                }

                                // 3 - Avif

                                if ($avifCompressions->count() && strpos($spConvertto, 'avif') !== false)
                                {
                                    foreach($avifCompressions as $c)
                                    {
                                        $url = null;
                                        $size = null;

                                        if (strpos($c, 'lossy') !== false && $spLossy == 'lossy')
                                        {
                                            $url = $item->AVIFLossyURL;
                                            $size = $item->AVIFLossySize;
                                            // $compressionName = $currentVariant['variant'];
                                        }
                                        else if (strpos($c, 'glossy') !== false && $spLossy == 'glossy')
                                        {
                                            // ! the glossy data here is under Lossy
                                            $url = $item->AVIFLossyURL;
                                            $size = $item->AVIFLossySize;
                                            // $compressionName = $currentVariant['variant'];
                                        }
                                        else if (strpos($c, 'lossless') !== false)
                                        {
                                            $url = $item->AVIFLosslessURL;
                                            $size = $item->AVIFLosslessSize;
                                            // $compressionName = $currentVariant['variant'];
                                        }

                                        if ($url && $size)
                                        {
                                            $imageData = file_get_contents($url);

                                            $variant = $currentVariant['variant'];
                                            $currentHash = sha1($imageData);

                                            $variant_name = $variant . '[' . $c . ']';

                                            // pass through some data via $cfg
                                            $cfg['flydata'] = [
                                              'compression' => $c,
                                              'size' => $size,
                                              'hash' => $currentHash,
                                              'type' => 'variant',
                                              'current_variant_type' => $currentVariant['type'],
                                              'variant_name' => $variant_name,
                                            ];

                                            // ! Check when debugging - $currentVariant['variant'], could be unnecessary
                                            $image->File->setFromString($imageData, $image_filename, $image_hash, $variant, $cfg);

                                            $newCompression = new CompressedImage;
                                            $newCompression->Hash = $currentHash; // ! see if we need hash of the compressed image at all
                                            // $newCompression->Filename = $compressionName;
                                            $newCompression->Compression = $c;
                                            $newCompression->Size = $size;
                                            $newCompression->Parent = $currentVariant['hash'];
                                            $newCompression->Source = $image->getHash();
                                            $newCompression->write();

                                            // var_dump($variant_name, $currentVariant['filename'], $image_filename, 'avif', $item->OriginalURL, json_encode($currentVariant));

                                            // rename .avif
                                            $rename->invoke(
                                              $store,
                                              $currentVariant['filename'] . '__' . $variant_name . '.' . $extension,
                                              $image_hash,
                                              $variant . '.avif',
                                              $image_filename,
                                              $store,
                                              $image,
                                              $isSource,
                                              $variant,
                                              $currentHash
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
























                    // exit;

                    // if (
                    //   $ShortPixelResponse &&
                    //   $ShortPixelResponse->succeeded[0] &&
                    //   $source->succeeded[0]->Status->Message == 'Success'
                    // )
                    // {
                    //     $cfg = ['conflict' => AssetStore::CONFLICT_OVERWRITE];
                    //     $image_hash = $image->getHash();
                    //     $image_filename = $image->File->getFilename();

                    //     foreach($ShortPixelResponse->succeeded as $compressedItem)
                    //     {
                    //         // 1) Origin - LossyURL
                    //         $url = file_get_contents($compressedItem->LossyURL);

                    //         $image->File->setFromString($url, $image_filename, $image_hash, $variant, $cfg);
                    //         $manipulatedData[$variant]['optimized_size'] = $compressedItem->LossySize;
                    //         $manipulatedData[$variant]['optimized'] = 1;

                    //         // 2) WebP - Lossy
                    //         $url = file_get_contents($compressedItem->WebPLossyURL);

                    //         $image->File->setFromString($url, $image_filename, $image_hash, $variant.'_webp', $cfg);
                    //         $manipulatedData[$variant]['webp'] = $compressedItem->WebPLossySize;

                    //         // rename _webp > .webp
                    //         $rename->invoke($store, $filename . '__' . $variant.'_webp.'.$extension, $image_hash, $variant.'.webp', $image_filename, $store);

                    //         // 3) Avif - Lossless
                    //         $url = file_get_contents($compressedItem->AVIFLosslessURL);

                    //         $image->File->setFromString($url, $image_filename, $image_hash, $variant.'_avif', $cfg);
                    //         $manipulatedData[$variant]['avif'] = $compressedItem->AVIFLosslessSize;

                    //         // $obj->LosslessURL
                    //         // $obj->LossyURL

                    //         // $obj->WebPLosslessURL
                    //         // $obj->WebPLossyURL

                    //         // $obj->AVIFLosslessURL
                    //         // $obj->AVIFLossyURL
                    //         $imageVariantOptimized++;
                    //     }
                    // }

                    // dd($ShortPixelResponse);

                    // exit;

                    // foreach($manipulatedData as $variant => $attrs)
                    // {
                    //     if ($attrs['optimized'] == 1)
                    //     {
                    //         continue;
                    //     }

                    //     $variantURL = $filepath . '/' . $filename . '__' . $variant  . '.' . $extension;

                    //     $source = fromUrls($variantURL)->toBuffers();

                    //     if ($source && $source->succeeded[0] && $source->succeeded[0]->Status->Message == 'Success')
                    //     {
                    //         $cfg = ['conflict' => AssetStore::CONFLICT_OVERWRITE];
                    //         $image_hash = $image->getHash();
                    //         $image_filename = $image->File->getFilename();

                    //         $obj = $source->succeeded[0];

                    //         // 1) Origin - LossyURL
                    //         $url = file_get_contents($obj->LossyURL);

                    //         $image->File->setFromString($url, $image_filename, $image_hash, $variant, $cfg);

                    //         // $imagesize = getimagesize($url);

                    //         // $size_in_bytes = (int) (strlen(rtrim($url, '=')) * 3 / 4);
                    //         // $headers = get_headers($url, 1);

                    //         // $manipulatedData[$variant]['optimized_size'] = $headers['Content-Length']; // $size_in_bytes;
                    //         $manipulatedData[$variant]['optimized'] = 1;

                    //         // 2) WebP - Lossy
                    //         $url = file_get_contents($obj->WebPLossyURL);
                    //         $image->File->setFromString($url, $image_filename, $image_hash, $variant.'_webp', $cfg);

                    //         $rename->invoke($store, $filename . '__' . $variant.'_webp.'.$extension, $image_hash, $variant.'.webp', $image_filename, $store);

                    //         // $size_in_bytes = (int) (strlen(rtrim($url, '=')) * 3 / 4);
                    //         // $headers = get_headers($url, 1);

                    //         // $manipulatedData[$variant]['webp'] = $headers['Content-Length']; // $size_in_bytes;

                    //         // 3) Avif - Lossless
                    //         $url = file_get_contents($obj->AVIFLosslessURL);
                    //         $image->File->setFromString($url, $image_filename, $image_hash, $variant.'_avif', $cfg);

                    //         // $size_in_bytes = (int) (strlen(rtrim($url, '=')) * 3 / 4);
                    //         // $headers = get_headers($url, 1);

                    //         // $manipulatedData[$variant]['avif'] = $headers['Content-Length']; // $size_in_bytes;

                    //         // $obj->LosslessURL
                    //         // $obj->LossyURL

                    //         // $obj->WebPLosslessURL
                    //         // $obj->WebPLossyURL

                    //         // $obj->AVIFLosslessURL
                    //         // $obj->AVIFLossyURL
                    //         $imageVariantOptimized++;
                    //     }
                    // }

                    // $image->Variants = json_encode($manipulatedData);
                    // $image->write();

                    // $imageTotalOptimized++;
                }
            }
        }

        // echo 'Variants: ' . $imageVariantOptimized;

        // echo $imageTotalOptimized ? 'Images optimized: ' . $imageTotalOptimized : 'Nothing to compress';
    }
}
