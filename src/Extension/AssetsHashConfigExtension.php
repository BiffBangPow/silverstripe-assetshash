<?php

namespace BiffBangPow\AssetsHash\Extension;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;

class AssetsHashConfigExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $db = [
        'AssetsHash' => 'Text'
    ];

    /**
     * @param FieldList $fields
     * @return void
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab('Root.Assets', [
            TextField::create('AssetsHash')->setReadonly(true),
        ]);
    }
}