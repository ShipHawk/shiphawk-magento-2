<?php

namespace Shiphawk;

class ShGatewayBuilder
{
    public static function buildOrdersUrl($gatewayUrl, $apiKey) {
        $gatewayHost = self::extractGatewayHost($gatewayUrl);

        return $gatewayHost . '/api/v4/orders?' . http_build_query(['api_key' => $apiKey]);
    }

    public static function buildUserUrl($gatewayUrl, $apiKey) {
        $gatewayHost = self::extractGatewayHost($gatewayUrl);

        return $gatewayHost . '/api/v4/user?' . http_build_query(['api_key' => $apiKey]);
    }

    public static function buildOrderUrl($gatewayUrl, $apiKey, $order) {
        $gatewayHost = self::extractGatewayHost($gatewayUrl);

        return $gatewayHost . '/api/v4/orders/' . $order->getIncrementId() . '/cancelled' . '?' .  http_build_query(['api_key' => $apiKey]);
    }

    public static function buildRatesUrl($gatewayUrl, $apiKey) {
        $gatewayHost = self::extractGatewayHost($gatewayUrl);

        return $gatewayHost . '/api/v4/rates?' . http_build_query(['api_key' => $apiKey]);
    }

    private static function extractGatewayHost($gatewayUrl) {
        # The pattern to extract the url parts from gateway url.
        $pattern = '/^([^:\/?#]+:?\/\/)?([^\/?#]*)?[^?#]*(\?([^#]*))?(#(.*))?/';
        preg_match($pattern, $gatewayUrl, $matches);
        # The host part of url should be in the last element of matched parts array.
        $gatewayHost = end($matches);

        return $gatewayHost;
    }
}
