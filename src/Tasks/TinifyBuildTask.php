<?php

namespace Goldfinch\Imaginarium\Tasks;

use SilverStripe\Dev\BuildTask;

class TinifyBuildTask extends BuildTask
{
    private static $segment = 'tinify';

    protected $enabled = true;

    protected $title = 'tinify';

    protected $description = '';

    public function run($request)
    {
        //
    }
}
