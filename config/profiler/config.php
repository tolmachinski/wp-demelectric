<?php
/**
 * Default configuration for PHP Profiler.
 *
 * To change these, create a file called `config.php` file in the same directory
 * and return an array from there with your overriding settings.
 */

use Xhgui\Profiler\Profiler;
use Xhgui\Profiler\ProfilingFlags;

return array(
    'save.handler' => \Xhgui\Profiler\Profiler::SAVER_UPLOAD,
    'save.handler.upload' => array(
        'url' => 'http://xhgui.local/run/import',
        // The timeout option is in seconds and defaults to 3 if unspecified.
        'timeout' => 3,
        // the token must match 'upload.token' config in XHGui
        'token' => 'token',
    ),
    'save.handler.file' => array(
        'filename' => sys_get_temp_dir() . '/xhgui.data.jsonl',
    ),
    'profiler.enable' => function () {
        return false;
    },
    'profiler.flags' => array(
        ProfilingFlags::CPU,
        ProfilingFlags::MEMORY,
        ProfilingFlags::NO_BUILTINS,
        ProfilingFlags::NO_SPANS,
    ),
    'profiler.options' => array(),
    'profiler.exclude-env' => array(),
    'profiler.simple_url' => function ($url) {
        return preg_replace('/=\d+/', '', $url);
    },
    'profiler.replace_url' => null,
);
