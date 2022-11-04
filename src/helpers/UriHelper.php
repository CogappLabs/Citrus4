<?php
namespace dentsucreativeuk\citrus\helpers;

use dentsucreativeuk\citrus\Citrus;

use Craft;

class UriHelper
{
	public $path;
	public $locale;

	public function __construct($path, $locale) {
		$this->path = $path;
		$this->locale = $locale;
	}
}
