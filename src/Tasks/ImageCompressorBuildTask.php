<?php

namespace Goldfinch\Imaginarium\Tasks;

use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use Goldfinch\Imaginarium\Services\Compressor;

class ImageCompressorBuildTask extends BuildTask
{
    private static $segment = 'ImageCompressor';

    protected $enabled = true;

    protected $title = 'Image compressor';

    protected $description = 'Compress image assets';

    public function run($request)
    {
        $images = Image::get();

        // $imageTotalOptimized = 0;
        // $imageVariantOptimized = 0;

        if ($images->Count())
        {
            $compressor = Compressor::init();
            $compressor->setCompressor('shortpixel');
            $compressor->setOptions([
                'lossy' => 'lossy',
                'convertto' => '+webp|+avif',
            ]);

            foreach($images as $image)
            {
                $compressor->run($image);
            }

            // recall pendings
            // if (isset($_SESSION['ImageCompressorPendings']))
            // {
            //     $pendings = $_SESSION['ImageCompressorPendings'];

            //     foreach($pendings as $item)
            //     {
            //         $compressor->run($item[0], $item[1]);
            //     }

            //     // $calls = 0;

            //     // do
            //     // {
            //     //     $calls++;

            //     //     // escape infinit loop
            //     //     if ($calls > 50)
            //     //     {
            //     //         break;
            //     //     }
            //     // }
            //     // while(count($pendings));
            // }
        }

        // echo 'Variants: ' . $imageVariantOptimized;

        // echo $imageTotalOptimized ? 'Images optimized: ' . $imageTotalOptimized : 'Nothing to compress';
    }
}
