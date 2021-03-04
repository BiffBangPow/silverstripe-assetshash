<?php

namespace BiffBangPow\AssetsHash\View;

use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Dev\Deprecation;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\HTML;
use SilverStripe\View\Requirements_Backend;

class BBP_Backend extends Requirements_Backend
{
    public function includeInHTML($content)
    {
        if (func_num_args() > 1) {
            Deprecation::notice(
                '5.0',
                '$templateFile argument is deprecated. includeInHTML takes a sole $content parameter now.'
            );
            $content = func_get_arg(1);
        }

        // Skip if content isn't injectable, or there is nothing to inject
        $tagsAvailable = preg_match('#</head\b#', $content);
        $hasFiles = $this->css || $this->javascript || $this->customCSS || $this->customScript || $this->customHeadTags;
        if (!$tagsAvailable || !$hasFiles) {
            return $content;
        }
        $requirements = '';
        $jsRequirements = '';

        // Combine files - updates $this->javascript and $this->css
        $this->processCombinedFiles();

        // Script tags for js links
        foreach ($this->getJavascript() as $file => $attributes) {
            // Build html attributes
            $filePath = $this->pathForFile($file);

            $htmlAttributes = [
                'type' => isset($attributes['type']) ? $attributes['type'] : "application/javascript"
            ];
            if (!empty($attributes['async'])) {
                $htmlAttributes['async'] = 'async';
            }
            if (!empty($attributes['defer'])) {
                $htmlAttributes['defer'] = 'defer';
            }
            if (!empty($attributes['integrity'])) {
                $htmlAttributes['integrity'] = $attributes['integrity'];
            }
            if (!empty($attributes['crossorigin'])) {
                $htmlAttributes['crossorigin'] = $attributes['crossorigin'];
            }
            $htmlAttributes['src'] = (!empty($attributes['addhash'])) ? $this->addAssetHash($filePath) : $filePath;

            $jsRequirements .= HTML::createTag('script', $htmlAttributes);
            $jsRequirements .= "\n";
        }

        // Add all inline JavaScript *after* including external files they might rely on
        foreach ($this->getCustomScripts() as $script) {
            $jsRequirements .= HTML::createTag(
                'script',
                ['type' => 'application/javascript'],
                "//<![CDATA[\n{$script}\n//]]>"
            );
            $jsRequirements .= "\n";
        }

        // CSS file links
        foreach ($this->getCSS() as $file => $params) {
            $filePath = $this->pathForFile($file);
            $htmlAttributes = [
                'rel' => 'stylesheet',
                'type' => 'text/css'
            ];
            if (!empty($params['media'])) {
                $htmlAttributes['media'] = $params['media'];
            }
            if (!empty($params['integrity'])) {
                $htmlAttributes['integrity'] = $params['integrity'];
            }
            if (!empty($params['crossorigin'])) {
                $htmlAttributes['crossorigin'] = $params['crossorigin'];
            }
            $htmlAttributes['href'] = (!empty($params['addhash'])) ? $this->addAssetHash($filePath) : $filePath;
            $requirements .= HTML::createTag('link', $htmlAttributes);
            $requirements .= "\n";
        }

        // Literal custom CSS content
        foreach ($this->getCustomCSS() as $css) {
            $requirements .= HTML::createTag('style', ['type' => 'text/css'], "\n{$css}\n");
            $requirements .= "\n";
        }

        foreach ($this->getCustomHeadTags() as $customHeadTag) {
            $requirements .= "{$customHeadTag}\n";
        }

        // Inject CSS  into body
        $content = $this->insertTagsIntoHead($requirements, $content);

        // Inject scripts
        if ($this->getForceJSToBottom()) {
            $content = $this->insertScriptsAtBottom($jsRequirements, $content);
        } elseif ($this->getWriteJavascriptToBody()) {
            $content = $this->insertScriptsIntoBody($jsRequirements, $content);
        } else {
            $content = $this->insertTagsIntoHead($jsRequirements, $content);
        }
        return $content;
    }


    /**
     * Register the given stylesheet into the list of requirements.
     *
     * @param string $file The CSS file to load, relative to site root
     * @param string $media Comma-separated list of media types to use in the link tag
     *                      (e.g. 'screen,projector')
     * @param array $options List of options. Available options include:
     * - 'integrity' : SubResource Integrity hash
     * - 'crossorigin' : Cross-origin policy for the resource
     * - 'addhash' : Add our custom assets hash
     */
    public function css($file, $media = null, $options = [])
    {
        $file = ModuleResourceLoader::singleton()->resolvePath($file);

        $integrity = $options['integrity'] ?? null;
        $crossorigin = $options['crossorigin'] ?? null;
        $addHash = $options['addhash'] ?? null;

        $this->css[$file] = [
            "media" => $media,
            "integrity" => $integrity,
            "crossorigin" => $crossorigin,
            "addhash" => $addHash
        ];
    }


    /**
     * Register the given JavaScript file as required.
     *
     * @param string $file Either relative to docroot or in the form "vendor/package:resource"
     * @param array $options List of options. Available options include:
     * - 'provides' : List of scripts files included in this file
     * - 'async' : Boolean value to set async attribute to script tag
     * - 'defer' : Boolean value to set defer attribute to script tag
     * - 'type' : Override script type= value.
     * - 'integrity' : SubResource Integrity hash
     * - 'crossorigin' : Cross-origin policy for the resource
     * - 'addhash' : Add our custom asset hash to the filename
     */
    public function javascript($file, $options = [])
    {
        $file = ModuleResourceLoader::singleton()->resolvePath($file);

        // Get type
        $type = null;
        if (isset($this->javascript[$file]['type'])) {
            $type = $this->javascript[$file]['type'];
        }
        if (isset($options['type'])) {
            $type = $options['type'];
        }

        // make sure that async/defer is set if it is set once even if file is included multiple times
        $async = (
            isset($options['async']) && $options['async']
            || (
                isset($this->javascript[$file])
                && isset($this->javascript[$file]['async'])
                && $this->javascript[$file]['async']
            )
        );
        $defer = (
            isset($options['defer']) && $options['defer']
            || (
                isset($this->javascript[$file])
                && isset($this->javascript[$file]['defer'])
                && $this->javascript[$file]['defer']
            )
        );
        $integrity = $options['integrity'] ?? null;
        $crossorigin = $options['crossorigin'] ?? null;
        $addHash = $options['addhash'] ?? null;

        $this->javascript[$file] = [
            'async' => $async,
            'defer' => $defer,
            'type' => $type,
            'integrity' => $integrity,
            'crossorigin' => $crossorigin,
            'addhash' => $addHash
        ];

        // Record scripts included in this file
        if (isset($options['provides'])) {
            $this->providedJavascript[$file] = array_values($options['provides']);
        }
    }



    private function addAssetHash($filename)
    {
        $config = SiteConfig::current_site_config();
        if ($config->AssetsHash != "") {
            $parts = explode('.', $filename);
            $suffix = array_pop($parts);
            $parts[] = 'v' . $config->AssetsHash;
            $parts[] = $suffix;
            return implode('.', $parts);
        }
        return $filename;
    }
}
