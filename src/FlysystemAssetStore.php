<?php

namespace Goldfinch\Imaginarium;

use ReflectionMethod;
use InvalidArgumentException;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use Goldfinch\Imaginarium\Models\CompressedImage;
use League\Flysystem\Filesystem;
use SilverStripe\Core\Injector\Injector;
use League\Flysystem\FileExistsException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Storage\FileHashingService;
use SilverStripe\Assets\FilenameParsing\ParsedFileID;
use SilverStripe\Assets\FilenameParsing\FileResolutionStrategy;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore as SS_FlysystemAssetStore;

class FlysystemAssetStore extends SS_FlysystemAssetStore
{
    // custom method
    protected function getFilesystemForLocal($fileID)
    {
        return $this->applyToFileIDOnFilesystem(
            function (ParsedFileID $parsedFileID, Filesystem $fs) {
                return $fs;
            },
            $fileID
        );
    }

    public function renameExtr(
        $filename,
        $hash,
        $newName,
        $filen,
        $store,
        $image,
        $isSource,
        $variant,
        $currentHash,
        $compressedImage,
    ) {
        if (empty($newName)) {
            throw new InvalidArgumentException(
                'Cannot write to empty filename',
            );
        }
        if ($newName === $filename) {
            return $filename;
        }

        $filesystemType = 'local';

        if (method_exists($this, 'getFilesystemFor'))
        {
            $filesystemType = 's3';
        }

        // dd($filename, $hash, $newName, $filen, $store);

        $strategy = $this->getPublicResolutionStrategy();
        $parsedFileID = new ParsedFileID($filen, $hash, $filename);
        $getFileID = new ReflectionMethod(
            FlysystemAssetStore::class,
            'getFileID',
        );
        $getFileID->setAccessible(true);
        $flyID = $getFileID->invoke($store, $filen, $hash);
        // dd($getFileID, $flyID, Injector::inst()->get(AssetStore::class));

        // $fs = Injector::inst()->get(AssetStore::class);

        if ($filesystemType == 's3')
        {
            $fs = $this->getFilesystemFor($flyID);
        }
        else
        {
            $fs = $this->getFilesystemForLocal($flyID);
        }

        $variants = $strategy->findVariants($parsedFileID, $fs);

        // dd($fs);

        // $protected = $fs->getProtectedFilesystem();

         // $fs->getPublicFilesystem());

        foreach ($variants as $originParsedFileID) {
            $origin = $originParsedFileID->getFileID();
            // dump($origin);
            preg_match('/(?<=\[)(.+)(?=\])/is', $origin, $match);

            if ($match && $match[0]) {
                $attrs = preg_split(
                    '/[\[\]]+/',
                    $origin,
                    -1,
                    PREG_SPLIT_NO_EMPTY,
                );

                // dump($origin, $match[0], $attrs, );
                // exit;

                $ruleHash = hash('xxh32', $match[0]);
                // var_dump($origin, $match[0], $ruleHash);

                if (!$isSource) {
                    $ruleHashFormated = '_' . $ruleHash;
                } else {
                    $ruleHashFormated = $ruleHash;
                }

                try {
                    if (strpos($match[0], 'avif') !== false) {

                      // dd($filename,
                      // $hash,
                      // $newName,
                      // $filen,
                      // $store,
                      // $image,
                      // $isSource,
                      // $variant,
                      // $currentHash,
                      // $compressedImage,$originParsedFileID);

                      // dd($originParsedFileID, $originParsedFileID->getHash(), $hash);
                        $compressionFileName =
                            $attrs[0] . $ruleHashFormated . '.avif';

                        // dd($fs->getAdapter()->prefixPath($parsedFileID->getFileID());

                        if ($filesystemType == 's3')
                        {
                            $fs->rename($origin, $compressionFileName);
                        }
                        else
                        {
                            $fs->move($origin, $compressionFileName);
                        }

                        // $hasher = Injector::inst()->get(FileHashingService::class);
                        // $hasher->move($origin, $fsFilesys, $compressionFileName);
                        // dump('from app', $origin, $compressionFileName);
                    } elseif (strpos($match[0], 'webp') !== false) {
                        $compressionFileName =
                            $attrs[0] . $ruleHashFormated . '.webp';
                        // dump('webp', $origin, $hash, $compressionFileName);

                        if ($filesystemType == 's3')
                        {
                            $fs->rename($origin, $compressionFileName);
                        }
                        else
                        {
                            $fs->move($origin, $compressionFileName);
                        }

                    } elseif (strpos($match[0], 'origin')) {
                        // origin extension
                        // dump($origin, $match[0], $ruleHashFormated, $filename, $filen, '<br><br><br>');
                        $compressionFileName =
                            $attrs[0] . $ruleHashFormated . $attrs[2];
                        // dump('origin', $origin, $hash, $compressionFileName);

                        if ($filesystemType == 's3')
                        {
                            $fs->rename($origin, $compressionFileName);
                        }
                        else
                        {
                            $fs->move($origin, $compressionFileName);
                        }
                    }

                    // get fresh image data
                    $image = Image::get()
                        ->filter('ID', $image->ID)
                        ->first();

                    $manipulatedData = $image
                        ->dbObject('ManipulatedData')
                        ->getStoreAsArray();

                    if ($isSource) {
                        if (
                            !isset($manipulatedData['source']) ||
                            !isset($manipulatedData['source']['compressions'])
                        ) {
                            $manipulatedData['source']['compressions'] = [];
                        }

                        $compressions = collect(
                            $manipulatedData['source']['compressions'],
                        );
                        $ckey = $compressions->search(function ($si) use (
                            $currentHash,
                        ) {
                            return $si['hash'] == $currentHash;
                        });

                        if ($ckey !== false) {
                            $manipulatedData['source']['compressions'][$ckey][
                                'id'
                            ] = $compressedImage->ID;
                            $manipulatedData['source']['compressions'][$ckey][
                                'name'
                            ] = $compressionFileName;
                            $manipulatedData['source']['compressions'][$ckey][
                                'cphash'
                            ] = $ruleHash; // compression rule xxh32 hash
                        }
                    } else {
                        $compressions = collect(
                            $manipulatedData['variants'][$variant][
                                'compressions'
                            ],
                        );
                        $ckey = $compressions->search(function ($si) use (
                            $currentHash,
                        ) {
                            return $si['hash'] == $currentHash;
                        });

                        $manipulatedData['variants'][$variant]['compressions'][
                            $ckey
                        ]['id'] = $compressedImage->ID;
                        $manipulatedData['variants'][$variant]['compressions'][
                            $ckey
                        ]['name'] = $compressionFileName;
                        $manipulatedData['variants'][$variant]['compressions'][
                            $ckey
                        ]['cphash'] = $ruleHash; // compression rule xxh32 hash
                    }

                    $image->ManipulatedData = json_encode($manipulatedData);
                    $image->write();

                    $compressedImage->ImageID = $image->ID;
                    $compressedImage->Filename = $compressionFileName;
                    $compressedImage->FileID = $origin;
                    $compressedImage->write();
                } catch (FileExistsException $e) {
                    // File already exists at path
                }
            }
        }
        // exit;
    }

    // Local is on init upload the very first and once
    public function setFromLocalFile(
        $path,
        $filename = null,
        $hash = null,
        $variant = null,
        $config = [],
    ) {
        // $fileID = $hash ? $this->getFileID($filename, $hash) : '-';

        // $file = File::get()->filter(['FileFilename' => $filename])->first();
        // if ($file) {
        //   // $t = new \App\Models\Test; $t->Type = 'setFromLocalFile'; $t->Text = $variant; $t->Data = $file->ID; $t->write();
        //   // dd('setFromStream', $file);
        // }

        return parent::setFromLocalFile(
            $path,
            $filename,
            $hash,
            $variant,
            $config,
        );
    }

    // String goes first
    public function setFromString(
        $data,
        $filename,
        $hash = null,
        $variant = null,
        $config = [],
    ) {
        // $fileID = $hash ? $this->getFileID($filename, $hash) : '-';
        // dump($filename, $variant, $config);

        $file = File::get()
            ->filter(['FileFilename' => $filename])
            ->first();
        if ($file) {
            // dump(
            //   sha1_file($uri),
            //   md5_file($uri),
            //   hash_file('md5', $uri),
            //   hash_file('sha256', $uri),
            // );

            // $fileID = $this->getFileID($filename, $hash);

            // $filesystem = $this->getFilesystemFor($fileID);

            $manipulatedData = $file
                ->dbObject('ManipulatedData')
                ->getStoreAsArray();

            if (!isset($config['flydata'])) {
                $uri =
                    'data://application/octet-stream;base64,' .
                    base64_encode($data);
                $imagesize = getimagesize($uri);

                $size_in_bytes = (int) ((strlen(rtrim($uri, '=')) * 3) / 4);
                // $size_in_kb = $size_in_bytes / 1024;
                // $size_in_mb = $size_in_kb / 1024;

                // if not exist, it's initial original variant
                $manipulatedData['variants'][$variant]['created_at'] = time();
                $manipulatedData['variants'][$variant]['mime'] =
                    $imagesize['mime'];
                $manipulatedData['variants'][$variant]['width'] = $imagesize[0];
                $manipulatedData['variants'][$variant]['height'] =
                    $imagesize[1];
                $manipulatedData['variants'][$variant]['size'] = $size_in_bytes;
                $manipulatedData['variants'][$variant]['fn'] = substr(
                    $variant,
                    0,
                    -10,
                );
                $manipulatedData['variants'][$variant]['hash'] = sha1($data); // sha1($uri);
            } else {
                $flydata = $config['flydata'];

                if ($flydata['current_variant_type'] == 'variant') {
                    //  && isset($manipulatedData['variants'][$variant])
                    // if exists, then we deal with compression request to write file
                    $manipulatedData['variants'][$variant]['compressions'][] = [
                        // 'name' => $filename,
                        'compression' => $flydata['compression'],
                        'hash' => $flydata['hash'],
                        'size' => $flydata['size'],
                    ];

                    // we need to amend the name of the file (!after saving data to manipulated data) so that we can make changes after
                    $variant = $flydata['variant_name'];

                    // dump($variant, $flydata['type']);
                    unset($config['flydata']);
                } elseif ($flydata['current_variant_type'] == 'source') {
                    // if exists, then we deal with compression request to write file
                    $manipulatedData['source']['compressions'][] = [
                        // 'name' => $filename,
                        'compression' => $flydata['compression'],
                        'hash' => $flydata['hash'],
                        'size' => $flydata['size'],
                    ];

                    // we need to amend the name of the file (!after saving data to manipulated data) so that we can make changes after
                    $variant = $flydata['variant_name'];

                    // dump($variant, $flydata['type']);

                    unset($config['flydata']);
                }
            }

            $file->ManipulatedData = json_encode($manipulatedData);
            $file->write();
            // $t = new \App\Models\Test; $t->Type = 'setFromString'; $t->Text = $variant; $t->Data = $file->ID; $t->write();
        }

        return parent::setFromString(
            $data,
            $filename,
            $hash,
            $variant,
            $config,
        );
    }

    // Stream goes second
    public function setFromStream(
        $stream,
        $filename,
        $hash = null,
        $variant = null,
        $config = [],
    ) {
        // dd($stream, $filename, $hash, $variant);

        // $file = File::get()->filter(['FileFilename' => $filename])->first();
        // if ($file) {

        //   $fileID = $this->getFileID($filename, $hash);

        //   $filesystem = $this->getFilesystemFor($fileID);
        //   // $stream = $filesystem->readStream($fileID);

        //   $variants = $file->dbObject('ManipulatedData')->getStoreAsArray();

        //   $variants[$variant]['size'] = round($filesystem->getSize($fileID) / 1024);
        //   $variants[$variant]['optimized'] = 0;

        //   $file->Variants = json_encode($variants);
        //   $file->write();

        //   // $t = new \App\Models\Test; $t->Type = 'setFromStream'; $t->Text = $variant; $t->Data = $file->ID; $t->write();
        //   // dd('setFromStream', $file);
        // }

        return parent::setFromStream(
            $stream,
            $filename,
            $hash,
            $variant,
            $config,
        );
    }

    protected function deleteFromFilesystem($fileID, Filesystem $filesystem)
    {
        // dd('store from app deleteFromFilesystem');

        // $t = new \App\Models\Test();
        // $t->Type = 'deleteFromFilesystem';
        // $t->Text = $fileID;
        // $t->write();

        return parent::deleteFromFilesystem($fileID, $filesystem);
    }

    protected function deleteFromFileStore(
        ParsedFileID $parsedFileID,
        Filesystem $fs,
        FileResolutionStrategy $strategy,
    ) {
        // dd('store from app deleteFromFileStore');

        // $t = new \App\Models\Test();
        // $t->Type = 'deleteFromFileStore';
        // $t->Text = $parsedFileID->getFileID();
        // $t->write();

        foreach ($strategy->findVariants($parsedFileID, $fs) as $variant) {
            $origin = $variant->getFileID();
            $ex = explode('.', $origin);
            // dd($parsedFileID, File::get(),$variant, $variant->getFileID());

            // $image = File::get()
            //     ->filter(['FileFilename' => $parsedFileID->getFilename()])
            //     ->first();

            // dd(
            //     $image,
            //     $parsedFileID->getFilename(),
            //     $parsedFileID,
            //     $fs,
            //     $strategy,
            // );

            $compressedRecords = CompressedImage::get()->filter([
              'Source' => $parsedFileID->getHash()
            ]);
            // dd($compressedRecords);

            foreach($compressedRecords as $record)
            {
                // the image object should be removed already, just to make sure we only remove files of removed images
                if (!$record->Image())
                {
                    if (substr($record->Filename, -5) == '.avif' || substr($record->Filename, -5) == '.webp')
                    {
                        $fs->delete($record->Filename);
                    }
                }
            }

            // although the above should clean up remaining compressions, the below check might be needed for s3
            $avif = $ex[0] . '.avif';
            $webp = $ex[0] . '.webp';

            if ($fs->has($avif)) {
                $fs->delete($avif);
            }
            if ($fs->has($webp)) {
                $fs->delete($webp);
            }
        }
        // dd($parsedFileID, $fs);
        // $getID = new ReflectionMethod(FlysystemAssetStore::class, 'getFileID');
        // $getID->setAccessible(true);
        // $flyID = $getID->invoke($store, $filename, $hash);
        // $getFileSystem = new ReflectionMethod(FlysystemAssetStore::class, 'getFilesystemFor');
        // $getFileSystem->setAccessible(true);

        // $system = $getFileSystem->invoke($store, $flyID);

        // $findVariants = new ReflectionMethod(FlysystemAssetStore::class, 'findVariants');
        // $findVariants->setAccessible(true);

        // foreach ($findVariants->invoke($store, $flyID, $system) as $variant)
        // {
        //     $origin = $variant->getFileID();
        //     $ex = explode('.', $origin);

        //     dd($variant, $origin);

        //     $avif = $ex[0].'.avif';
        //     $webp = $ex[0].'.webp';

        //     if ($fs->has($avif)) {
        //         $fs->delete($avif);
        //     }
        //     if ($fs->has($webp)) {
        //         $fs->delete($webp);
        //     }
        // }

        return parent::deleteFromFileStore($parsedFileID, $fs, $strategy);
    }

    public function delete($filename, $hash)
    {
        // $t = new \App\Models\Test();
        // $t->Type = 'delete';
        // $t->Text = $filename;
        // $t->write();

        // dd('store from app delete');
        // $t = new \App\Models\Test; $t->Type = 'delete'; $t->Text = $filename; $t->write();

        // $store = Injector::inst()->get(AssetStore::class);

        // $getID = new ReflectionMethod(FlysystemAssetStore::class, 'getFileID');
        // $getID->setAccessible(true);
        // $flyID = $getID->invoke($store, $filename, $hash);
        // $getFileSystem = new ReflectionMethod(FlysystemAssetStore::class, 'getFilesystemFor');
        // $getFileSystem->setAccessible(true);

        // $system = $getFileSystem->invoke($store, $flyID);

        // $findVariants = new ReflectionMethod(FlysystemAssetStore::class, 'findVariants');
        // $findVariants->setAccessible(true);

        // foreach ($findVariants->invoke($store, $flyID, $system) as $variant)
        // {
        //     $origin = $variant->getFileID();
        //     $ex = explode('.', $origin);

        //     dd($variant, $origin);

        //     $avif = $ex[0].'.avif';
        //     $webp = $ex[0].'.webp';

        //     if ($fs->has($avif)) {
        //         $fs->delete($avif);
        //     }
        //     if ($fs->has($webp)) {
        //         $fs->delete($webp);
        //     }
        // }

        return parent::delete($filename, $hash);
    }

    public function rename($filename, $hash, $newName)
    {
        // $t = new \App\Models\Test();
        // $t->Type = 'rename';
        // $t->Text = $filename;
        // $t->write();

        return parent::rename($filename, $hash, $newName);
    }

    public function copy($filename, $hash, $newName)
    {
        // $t = new \App\Models\Test();
        // $t->Type = 'copy';
        // $t->Text = $filename;
        // $t->write();

        return parent::copy($filename, $hash, $newName);
    }
}
