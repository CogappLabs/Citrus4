<?php

namespace dentsucreativeuk\citrus\helpers;

class UriHelper
{
    public $path;
    public $locale;

    public function __construct($path, $locale)
    {
        $this->path = $path;
        $this->locale = $locale;
    }
}
