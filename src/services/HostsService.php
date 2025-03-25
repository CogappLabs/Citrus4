<?php

namespace dentsucreativeuk\citrus\services;

use craft\base\Component;

use dentsucreativeuk\citrus\Citrus;

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
