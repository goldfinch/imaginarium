<?php

namespace Goldfinch\Imaginarium\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

class Test extends DataObject
{
    private static $singular_name = 'test';

    private static $plural_name = 'tests';

    private static $table_name = 'Test';

    private static $cascade_deletes = [];

    private static $cascade_duplicates = [];

    private static $db = [
        'Text' => 'Text',
        'Type' => 'Text',
        'Data' => 'Text',
    ];

    private static $casting = [];

    private static $indexes = null;

    private static $defaults = [];

    private static $has_one = [];
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
}
