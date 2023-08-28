<?php

namespace Goldfinch\Imaginarium\Models;

use ReflectionMethod;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;

class CompressedImage extends DataObject
{
    use Configurable;

    private static $singular_name = 'compressed image';

    private static $plural_name = 'compressed images';

    private static $table_name = 'CompressedImage';

    private static $cascade_deletes = [];

    private static $cascade_duplicates = [];

    private static $db = [
        'Hash' => 'Varchar',
        'Filename' => 'Varchar',
        'Compression' => 'Varchar(32)',
        'Size' => 'Varchar(32)',
        'Parent' => 'Varchar',
        'Source' => 'Varchar',
        'Filename' => 'Varchar(255)',
        'FileID' => 'Varchar(255)',
    ];

    private static $casting = [];

    private static $indexes = null;

    private static $defaults = [];

    private static $has_one = [
        'Image' => Image::class,
    ];
    private static $belongs_to = [];
    private static $has_many = [];
    private static $many_many = [];
    private static $many_many_extraFields = [];
    private static $belongs_many_many = [];

    private static $default_sort = null;

    private static $searchable_fields = [];

    private static $field_labels = [];

    // composer require goldfinch/helpers
    private static $field_descriptions = [];
    private static $required_fields = [];

    private static $summary_fields = [];

    public function validate()
    {
        $result = parent::validate();

        // $result->addError('Error message');

        return $result;
    }

    public function onBeforeWrite()
    {
        // ..

        parent::onBeforeWrite();
    }

    public function onBeforeDelete()
    {
        // ..

        parent::onBeforeDelete();
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        //

        return $fields;
    }

    public function canView($member = null)
    {
        return Permission::check(
            'CMS_ACCESS_Company\Website\MyAdmin',
            'any',
            $member,
        );
    }

    public function canEdit($member = null)
    {
        return Permission::check(
            'CMS_ACCESS_Company\Website\MyAdmin',
            'any',
            $member,
        );
    }

    public function canDelete($member = null)
    {
        return Permission::check(
            'CMS_ACCESS_Company\Website\MyAdmin',
            'any',
            $member,
        );
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check(
            'CMS_ACCESS_Company\Website\MyAdmin',
            'any',
            $member,
        );
    }

    public function SchemaData()
    {
        // Spatie\SchemaOrg\Schema
    }

    public function OpenGraph()
    {
        // Astrotomic\OpenGraph\OpenGraph
    }

    public static function checkCompression($hash, $rules)
    {
        //
    }

    public static function getCompressionRules()
    {
        return self::config()->get('compression_rules');
    }

    public static function getSourceCompressionRules()
    {
        return isset(self::getCompressionRules()['source'])
            ? self::getCompressionRules()['source']
            : null;
    }

    public static function getVariantCompressionRules()
    {
        return isset(self::getCompressionRules()['variant'])
            ? self::getCompressionRules()['variant']
            : null;
    }

    /**
     * $type in|out
     */
    public static function checkVariantCompression($attrs, $url, $image, $type)
    {
        $rules = self::getVariantCompressionRules();

        // $sha1 = self::getImageSha1($url); // since it's on source image, we already know its hash
        // $sha1 = sha1(file_get_contents($url));

        $allCompressionsRules = self::convertAllCompressionRules($rules);

        if (!isset($attrs['compressions'])) {
            // init run (no compressions done yet)
            $existingRules = [];
        } else {
            $collectCompressions = collect($attrs['compressions']);

            // $manipulatedData = $image->dbObject('ManipulatedData')->getStoreAsArray();
            // dd($manipulatedData, $attrs['compressions']);
            // dd(self::convertCompressionRule('sp[webp,avif][lossy,glossy]'));

            $existingCompressedImages = CompressedImage::get()->filterByCallback(
                function ($item, $list) use (
                    $collectCompressions,
                    $allCompressionsRules,
                    $image,
                ) {
                    return $item->Source == $image->getHash() &&
                        $collectCompressions
                            ->where('compression', $item->Compression)
                            ->count();
                },
            );

            $existingRules = $existingCompressedImages->column('Compression');
        }

        if ($type == 'in') {
            $results = $existingRules;
        } elseif ($type == 'out') {
            $results = array_diff($allCompressionsRules, $existingRules);
        }

        return $results;
    }

    /**
     * $type in|out
     */
    public static function checkSourceCompression($image, $type)
    {
        $rules = self::getSourceCompressionRules();

        // $sha1 = self::getImageSha1($image); // since it's on source image, we already know its hash

        $allCompressionsRules = self::convertAllCompressionRules($rules);
        // dd(self::convertCompressionRule('sp[webp,avif][lossy,glossy]'));

        $existingCompressedImages = CompressedImage::get()->filterByCallback(
            function ($item, $list) use ($allCompressionsRules, $image) {
                return $item->Source == $image->getHash() &&
                    in_array($item->Compression, $allCompressionsRules);
            },
        );

        $existingRules = $existingCompressedImages->column('Compression');

        // dd($sha1, $image, $type);

        if ($type == 'in') {
            $results = $existingRules;
        } elseif ($type == 'out') {
            $results = array_diff($allCompressionsRules, $existingRules);
        }

        return $results;
    }

    protected static function convertAllCompressionRules($rules)
    {
        $converted = [];

        foreach ($rules as $rule) {
            foreach (self::convertCompressionRule($rule) as $r) {
                $converted[] = $r;
            }
        }

        return $converted;
    }

    protected static function convertCompressionRule($rule)
    {
        $converted = [];

        $attrs = preg_split('/[\[\]]+/', $rule, -1, PREG_SPLIT_NO_EMPTY);

        $compressor = $attrs[0];
        $formats = explode(',', $attrs[1]);
        $compressionTypes = explode(',', $attrs[2]);

        foreach ($formats as $format) {
            foreach ($compressionTypes as $type) {
                $converted[] = $compressor . '-' . $format . '-' . $type;
            }
        }

        return $converted;
    }

    protected static function getImageSha1($image)
    {
        // TODO: cover default local assets storage (if not S3)

        // Getting origin S3 url (skipping CDN url that won't work with file_get_contents)
        $store = Injector::inst()->get(AssetStore::class);
        $getID = new ReflectionMethod(FlysystemAssetStore::class, 'getFileID');
        $getID->setAccessible(true);
        $fileID = $getID->invoke(
            $store,
            $image->Filename,
            $image->Hash,
            $image->Variant,
        );
        $getFileSystem = new ReflectionMethod(
            FlysystemAssetStore::class,
            'getFilesystemFor',
        );
        $getFileSystem->setAccessible(true);
        $system = $getFileSystem->invoke($store, $fileID);

        $adapter = $store
            ->getPublicFilesystem()
            ->getAdapter()
            ->getAdapter();
        $OriginS3Link = $adapter
            ->getClient()
            ->getObjectUrl(
                $adapter->getBucket(),
                $adapter->applyPathPrefix($fileID),
            );

        return sha1(file_get_contents($OriginS3Link));
    }
}
