<?php

namespace Goldfinch\Imaginarium\Services;

use ReflectionMethod;
use ShortPixel\ShortPixel;
use App\Models\ImageVariant;
use SilverStripe\Assets\Image;
use ShortPixel\AccountException;
use function ShortPixel\fromUrls;
use function ShortPixel\fromFiles;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Config\Config;
use SilverStripe\Assets\Image_Backend;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;
use Goldfinch\Imaginarium\FlysystemAssetStore;
use Goldfinch\Imaginarium\Models\CompressedImage;
use SilverStripe\EventDispatcher\Symfony\Backend;

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
            // $this->shortpixel($image);
        }
    }

    public function shortpixel($imageCompression)
    {
        $set = $imageCompression->compressionSet();
        $object = $set['object'];

        // TODO: pass 1px image?
        // $compresedImageLink = 'https://silverstripe-starter.lh/assets/pixel.jpg';
        $compresedImageLink = 'https://silverstripe-starter.lh/assets/a4ef1e29fb3a36b98d4666db49079465-lossy.jpeg';

        $image = $object->Image();
        $name = $image->variantName($object->Method, $object->Width, $object->Height, 'SP-lossy');

        // dd(ImageVariant::get()->first()->parsedFileData()['filesys']['adapter']->write());
        // dd($name);

        $backend = new \SilverStripe\Assets\InterventionBackend;
        $arrContextOptions=array(
          "ssl"=>array(
              "verify_peer"=>false,
              "verify_peer_name"=>false,
          ),
        );

        // $tinypx = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAQDAwQDAwQEBAQFBQQFBwsHBwYGBw4KCggLEA4RERAOEA8SFBoWEhMYEw8QFh8XGBsbHR0dERYgIh8cIhocHRz/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8Afz//2Q==';

        // $imagecontent = base64_decode($tinypx);
        // file_get_contents($compresedImageLink, false, stream_context_create($arrContextOptions))

        // $resource = $backend->getImageManager()->make($imagecontent);
        // dd($backend->getImageManager());
        // dd($resource);
        // $imageBackend = $image->getImageBackend();
        // $imageBackend->setImageResource($resource);
        // $store = Injector::inst()->get(AssetStore::class);
        // exit;
        // dd($imageBackend);

        // SilverStripe\Assets\InterventionBackend
        // $imageBackend->writeToStore(
        //     $store,
        //     $filename,
        //     $hash,
        //     $variant,
        //     ['conflict' => AssetStore::CONFLICT_USE_EXISTING]
        // );

        // inside writeToStroe > $resource (Intervention\Image\Image)

        // $result = $image->File->setFromString(
        //   $imagecontent,
        //   $name.'.jpg', $image->getHash(), $name, []
        // );
        // // $result = $this->File->setFromLocalFile($path, $name, null, null, $config);

        // dd($result);


        // $imagecontent = file_get_contents($compresedImageLink, false, stream_context_create($arrContextOptions));
        // $imagecontentEncode = base64_encode($imagecontent);
        // var_dump($imagecontent);exit;
        // @file_put_contents('/Users/art/Code/modules/starter/public/assets/3216x2136__FitMaxWzYwMCwzMDAsIlNQLWxvc3N5Il0.jpg', $imagecontent, 2);

        // exit;

        $result = $object->Image()->manipulateImage($name, function (Image_Backend $backend) {
          // exit;
            // dd($backend->getImageResource()->save('/Users/art/Code/modules/starter/public/assets/3216x2136__FitMaxWzYwMCwzMDAsIlNQLWxvc3N5Il0.jpg', 100));
            // $backend->loadFrom($compresedImageLink);
            // $backend->setImageResource($resource);
            // $backend->getImageResource()->setEncoded($imagecontent);
            // $backend->setImageResource($resource)->setQuality(100);




            //   $tuple = $result->writeToStore(
            //     $store,
            //     $filename,
            //     $hash,
            //     $variant,
            //     ['conflict' => AssetStore::CONFLICT_USE_EXISTING]
            // );
            // return;



              /**
               * Need to find out if we can null this step/skip but get parsedFileData below. Currently if we do not return $backend, parsedFileData will be null.
               */
            return $backend->setQuality(1);
        });


        $currentVariant = ImageVariant::get()->filter('prefix', $result->getVariant())->first();

        if ($currentVariant)
        {
          $parsed = $currentVariant->parsedFileData();

          $imagecontent = file_get_contents($compresedImageLink, false, stream_context_create($arrContextOptions));

          if ($set['filesystem'] == 's3')
          {
              // s3
              // $cfg = $parsed['filesys']['public']->prepareConfig([]);
              // dd($parsed['filesys']['adapter']);
              // $parsed['filesys']['public']->putStream($parsed['getFileID'], $imagecontent);
              $cfg = $parsed['filesys']['public']->getConfig();
              $parsed['filesys']['adapter']->write($parsed['getFileID'], $imagecontent, $cfg);
          }
          else
          {
              // local
              @file_put_contents($parsed['origin'], $imagecontent, 2);
          }
        }
        // dd($parsed['origin']);
        exit;









        // ----
        $set = $imageCompression->compressionSet();

        $spLossy = $this->options['lossy'];
        $spConvertto = $this->options['convertto'];

        $object = $set['object'];
        $parsedFileData = $object->parsedFileData();

        // dd($parsedFileData['origin']);

        // $ShortPixelResponse = fromUrls([])->toBuffers();
        if ($imageCompression->PendingURL)
        {
            $ShortPixelResponse = fromUrls([$imageCompression->PendingURL])->toBuffers();
        }
        else
        {
            $ShortPixelResponse = fromFiles([$parsedFileData['origin']])->toBuffers();
        }

        if ($ShortPixelResponse->status['code'] === 1)
        {
            if (count($ShortPixelResponse->pending))
            {
                $imageCompression->PendingURL = $ShortPixelResponse->pending[0]->OriginalURL;
                $imageCompression->write();
            }
        }
        else if ($ShortPixelResponse->status['code'] === 2)
        {
            if (count($ShortPixelResponse->succeeded))
            {
                //
            }
        }

        // $ShortPixelResponse->failed;
        // $ShortPixelResponse->same;

        // https://silverstripe-starter.lh/assets/a4ef1e29fb3a36b98d4666db49079465-lossy.jpeg
        // dd($ShortPixelResponse->succeeded[0]->LossyURL);
    }

    public function __shortpixel($image)
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
                  'path' => Director::baseFolder() . '/public' . $image->getUrl(),
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
                      'path' => Director::baseFolder() . '/public' . $variantUrl,
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
            // dd($image->PendingData());
            $urlsToCompress = collect($urlsToCompress);
            $_SESSION['shortpixel-test'] = null;
            // DEBUGGING

            $pendingDataState = $image->PendingData && $image->PendingData() && !empty($image->PendingData());

            if ($urlsToCompress->count() || $pendingDataState)
            {
                try
                {
                    // recall request
                    if ($pendingDataState)
                    {
                        $pendingData = $image->PendingData();

                        // $allCurrent = $urlsToCompress->pluck('hash')->all();

                        // $pendingsToCompress = [];

                        // foreach($allCurrent as $hash)
                        // {
                        //     if (isset($pendingData[$hash]))
                        //     {
                        //         $pendingsToCompress[] = $pendingData[$hash];
                        //     }
                        // }

                        // // dd($allCurrent, $pendingsToCompress);

                        // // ! return false if pendings returns 0 which shouldn't happen, see later if we need this condition
                        // if (empty($pendingsToCompress))
                        // {
                        //     return false;
                        // }

                        if (!isset($_SESSION['shortpixel-test']))
                        {
                            $ShortPixelResponse = fromUrls(array_values($pendingData))->toBuffers();
                            // $ShortPixelResponse = fromUrls($pendingsToCompress)->toBuffers();
                        }
                        else
                        {
                            $ShortPixelResponse = unserialize($_SESSION['shortpixel-test']);
                        }
                    }
                    else
                    {
                        if (!isset($_SESSION['shortpixel-test']))
                        {
                            // dd($urlsToCompress->pluck('path')->all());

                            // if (class_exists(\SilverStripe\S3\Adapter\PublicAdapter::class))
                            if (substr($urlsToCompress->first()['url'], 0, 4) == 'http')
                            {
                                $ShortPixelResponse = fromUrls($urlsToCompress->pluck('url')->all())->toBuffers();
                            }
                            else
                            {
                                $ShortPixelResponse = fromFiles($urlsToCompress->pluck('path')->all())->toBuffers();
                            }
                        }
                        else
                        {
                            $ShortPixelResponse = unserialize($_SESSION['shortpixel-test']);
                        }
                    }

                    $_SESSION['shortpixel-test'] = serialize($ShortPixelResponse);

                } catch (\ShortPixel\AccountException $e) {
                    dd($e->getMessage());
                }
            }

            if (!isset($ShortPixelResponse))
            {
                return;
            }

            // dd($ShortPixelResponse);

            // Check if Status=1 (Message='Image scheduled for processing')
            // If so, save the link for recall

            if ($ShortPixelResponse->status['code'] == 1)
            {
                $files = $urlsToCompress->pluck('path')->all();

                // dd($files);

                $PendingData = $image->PendingData();

                foreach ($ShortPixelResponse->pending as $item)
                {
                    if (property_exists($item, 'Key'))
                    {
                        $k = (int) str_replace('file', '', $item->Key) - 1;
                        $replacedItem = $urlsToCompress[$k];
                        // $replacedItem['pending'] = $item->OriginalURL;
                        // $urlsToCompress = $urlsToCompress->replace([$k => $replacedItem]);
                        // $image->PendingData = [
                        //   'hash' => $replacedItem['hash'],
                        //   'pending_url' => $item->OriginalURL
                        // ];

                        $PendingData[$replacedItem['hash']] = $item->OriginalURL;
                    }
                }

                $image->PendingData = json_encode($PendingData);
                $image->write();
                // $image->publishRecursive();

                // Save pending url with current image into the session
                // $_SESSION['ImageCompressorPendings'][] = [$image, $urlsToCompress];
            }

            // dd($urlsToCompress);

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

            // dd($ShortPixelResponse);

            // commented out : otherwise 'succeeded' will be escaped (that could have data to proceed)
            // if (count($ShortPixelResponse->pending))
            // {
            //     // skip unfull request that is pending
            //     return 'pending';
            // }

            if (
              property_exists($ShortPixelResponse, 'succeeded') &&
              count($ShortPixelResponse->succeeded)
            )
            {
                $cfg = ['conflict' => AssetStore::CONFLICT_OVERWRITE];
                $image_hash = $image->getHash();
                $image_filename = $image->File->getFilename();

                // dd($urlsToCompress , $ShortPixelResponse->succeeded);
                // dd($ShortPixelResponse->succeeded);
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

                        // dd(CompressedImage::getImageSha1());
                        if (substr($urlsToCompress->first()['url'], 0, 4) == 'http')
                        {
                            $currentVariant = $urlsToCompress->where('url', $item->OriginalURL)->first();
                        }
                        else
                        {
                            $currentVariant = $urlsToCompress->where('hash', sha1(file_get_contents($item->OriginalURL)))->first();
                        }

                        // dd(currentVariant);
                        // dd($image->manipulatedData(),$image->pendingData(), $urlsToCompress,$item);
                        // dd($currentVariant);
                        // dd($currentVariant, $item->OriginalURL, $item, $urlsToCompress, sha1(file_get_contents($item->OriginalURL)));

                        // ! need to check why it returend null (image should be there), or perhaps it's a dev calls messed it up, just in case for further testings - continue
                        if (!$currentVariant)
                        {
                            continue;
                        }

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
                                    // $newCompression->publishRecursive();

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
                                    // $newCompression->publishRecursive();

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
                                    // $newCompression->publishRecursive();

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

                        $image->FileVariant = null;
                        $image->write();
                        // $image->publishRecursive();

                        // remove from pendings (if exists)
                        if ($image->PendingData && $image->PendingData() && !empty($image->PendingData()))
                        {
                            $PendingData = $image->PendingData();

                            if (isset($PendingData[$currentVariant['hash']]))
                            {
                                unset($PendingData[$currentVariant['hash']]);

                                $image->PendingData = json_encode($PendingData);
                                $image->write();
                                // $image->publishRecursive();
                            }
                        }
                    }
                }

                return true;
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
