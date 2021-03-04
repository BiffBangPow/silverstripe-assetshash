<?php

namespace BiffBangPow\AssetsHash\Task;

use SilverStripe\Dev\BuildTask;
use SilverStripe\SiteConfig\SiteConfig;

class ChangeAssetsHash extends BuildTask
{
    private static $segment = 'ChangeAssetsHash';
    protected $title = 'Change Assets Hash';
    protected $description = 'Changes the assets hash to cache bust for css and js';
    protected $enabled = true;

    public function run($request)
    {
        $siteConfig = SiteConfig::current_site_config();
        $hash = md5(time());
        $siteConfig->AssetsHash = $hash;
        $siteConfig->write();
        echo 'Changed assets hash to ' . $hash . PHP_EOL;
    }
}