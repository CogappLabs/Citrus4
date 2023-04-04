<?php

namespace dentsucreativeuk\citrus\services;

use dentsucreativeuk\citrus\Citrus;

use Craft;
use craft\base\Component;
use craft\db\Query;

class HostsService extends Component
{
	/**
	 * Get the available hosts
	 */
	public function getHosts()
	{
		if (is_callable(Citrus::getInstance()->settings['varnishHosts'])) {
			// Call varnishHosts as function
			return Citrus::getInstance()->settings['varnishHosts']();
		}

		// Fetch varnishHosts directly
		return Citrus::getInstance()->settings->varnishHosts;
	}
}
