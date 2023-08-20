<?php

namespace Goldfinch\Imaginarium\Services;

use ReflectionMethod;
use ShortPixel\ShortPixel;
use App\FlysystemAssetStore;
use App\Models\CompressedImage;
use ShortPixel\AccountException;
use function ShortPixel\fromUrls;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;

class Compressor
{
    protected $client;
    protected $compressor;
    protected $options;

    public function __construct()
    {
        $this->setLimits();
    }

    public static function init()
    {
        return new static();
    }

    public function setCompressor($compressor)
    {
        $this->compressor = $compressor;

        return $this;
    }

    public function setOptions($options)
    {
        $this->options = $options;

        $this->shortpixelInit();

        return $this;
    }

    public function run($image)
    {
        if ($this->compressor == 'shortpixel')
        {
            $this->shortpixel($image);
        }
    }

    public function shortpixel($image)
    {
        $spLossy = $this->options['lossy'];
        $spConvertto = $this->options['convertto'];

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
                return;
            }

            if (count($ShortPixelResponse->pending))
            {
                // skip unfull request that is pending
                return 'pending';
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
                                      $currentHash,
                                      $newCompression
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
                                      $currentHash,
                                      $newCompression
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
                                      $currentHash,
                                      $newCompression
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function tinify()
    {
        //
    }

    public function webPConvert()
    {
        //
    }

    protected function setLimits()
    {
        Environment::increaseTimeLimitTo(600);
        Environment::increaseMemoryLimitTo(5000);
        // Environment::setTimeLimitMax();
        // Environment::setMemoryLimitMax();
    }

    protected function shortpixelInit()
    {
        // ! the lossless return same as lossy, so we ignore it here

        $this->client = new ShortPixel();
        $this->client->setKey(Environment::getEnv('SHORTPIXEL_API_KEY'));
        $this->client->setOptions([
          'lossy' => $this->options['lossy'] == 'lossy' ? 1 : 2, // 1 - lossy, 2 - glossy, 0 - lossless
          'convertto' => $this->options['convertto'],
          'notify_me' => null,
          'wait' => 300,
          'total_wait' => 300,
        ]);
    }
}
