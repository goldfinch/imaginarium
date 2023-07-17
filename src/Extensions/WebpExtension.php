<?php

namespace Goldfinch\Imaginarium\Extensions;

use SilverStripe\Control\Director;
use SilverStripe\ORM\DataExtension;

class WebpExtension extends DataExtension
{
    public function onAfterDelete()
    {
        $public = Director::publicFolder();
        $ext = $this->owner->getExtension();
        $name = $this->owner->Name;
        $namenoext = rtrim($name, '.'.$ext).'__';
        $namelen = strlen($namenoext);
        $folder = rtrim($this->owner->FileFilename, $name);
        $fullpath = $public.'/assets/'.$folder;

        $files = scandir(rtrim($fullpath, '/'));

        $output = "Deleted files:\r\n";
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (substr($file, 0, $namelen) === $namenoext and substr($file, -4) === 'webp') {
                unlink($fullpath.$file);
            }
        }

        if (is_file($fullpath.$name.'.webp')) {
            unlink($fullpath.$name.'.webp');
        }

        parent::onAfterDelete();
    }
}
