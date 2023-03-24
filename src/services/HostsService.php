<?php

namespace dentsucreativeuk\citrus\services;

use dentsucreativeuk\citrus\Citrus;

use Craft;
use craft\base\Component;
use craft\db\Query;

class HostsService extends Component
{
	public function getHosts()
	{
		// TODO: Add AWS Host support
		return Citrus::getInstance()->settings->varnishHosts;
	}
}
