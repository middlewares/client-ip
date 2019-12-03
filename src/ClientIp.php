<?php
declare(strict_types = 1);

namespace Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ClientIp implements MiddlewareInterface
{
    /**
     * @var string The attribute name
     */
    private $attribute = 'client-ip';

    /**
     * @var array The trusted proxy headers
     */
    private $proxyHeaders = [];

    /**
     * @var array The trusted proxy ips
     */
    private $proxyIps = [];

    /**
     * Configure the proxy.
     */
    public function proxy(
        array $ips = [],
        array $headers = [
            'Forwarded',
            'Forwarded-For',
            'X-Forwarded',
            'X-Forwarded-For',
            'X-Cluster-Client-Ip',
            'Client-Ip',
        ]
    ): self {
        $this->proxyIps = $ips;
        $this->proxyHeaders = $headers;

        return $this;
    }

    /**
     * Set the attribute name to store client's IP address.
     */
    public function attribute(string $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * Process a server request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $this->getIp($request);

        return $handler->handle($request->withAttribute($this->attribute, $ip));
    }

    /**
     * Detect and return the ip.
     */
    private function getIp(ServerRequestInterface $request): ?string
    {
        $localIp = $this->getLocalIp($request);

        if (!empty($this->proxyIps) && !$this->isInProxiedIps($localIp)) {
            // Local IP address does not point at a known proxy, do not attempt
            // to read proxied IP address.
            return $localIp;
        }

        $proxiedIp = $this->getProxiedIp($request);

        if (!empty($proxiedIp)) {
            // Found IP address via proxy-defined headers.
            return $proxiedIp;
        }

        return $localIp;
    }

    /**
     * checks if the given ip address is in the list of proxied ips provided
     */
    private function isInProxiedIps(string $ip): bool
    {
        foreach ($this->proxyIps as $proxyIp) {
            if ($ip === $proxyIp || self::isInCIDR($ip, $proxyIp)) {
                return true;
            }
        }
        return false;
    }

    private static function isInCIDR(string $ip, string $cidr): bool
    {
        $tokens = explode('/', $cidr);
        if (count($tokens) !== 2 || !self::isValid($ip) || !self::isValid($tokens[0]) || !is_numeric($tokens[1])) {
            return false;
        }

        $cidr_base = ip2long($tokens[0]);
        $ip_long = ip2long($ip);
        $mask = (0xffffffff << intval($tokens[1])) & 0xffffffff;

        return ($cidr_base & $mask) === ($ip_long & $mask);
    }

    /**
     * Returns the first valid proxied IP found.
     */
    private function getProxiedIp(ServerRequestInterface $request): ?string
    {
        foreach ($this->proxyHeaders as $name) {
            if ($request->hasHeader($name)) {
                if (substr($name, -9) === 'Forwarded') {
                    $ip = $this->getForwardedHeaderIp($request->getHeaderLine($name));
                } else {
                    $ip = $this->getHeaderIp($request->getHeaderLine($name));
                }

                if ($ip !== null) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Returns the remote address of the request, if valid.
     */
    private function getLocalIp(ServerRequestInterface $request): ?string
    {
        $server = $request->getServerParams();
        $ip = trim($server['REMOTE_ADDR'] ?? '', '[]');

        return self::isValid($ip) ? $ip : null;
    }

    /**
     * Returns the first valid ip found in the Forwarded or X-Forwarded header.
     */
    private function getForwardedHeaderIp(string $header): ?string
    {
        foreach (array_reverse(array_map('trim', explode(',', strtolower($header)))) as $values) {
            foreach (array_reverse(array_map('trim', explode(';', $values))) as $directive) {
                if (strpos($directive, 'for=') !== 0) {
                    continue;
                }

                $ip = trim(substr($directive, 4));

                if (self::isValid($ip) && !$this->isInProxiedIps($ip)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Returns the first valid ip found in the header.
     */
    private function getHeaderIp(string $header): ?string
    {
        foreach (array_reverse(array_map('trim', explode(',', $header))) as $ip) {
            if (self::isValid($ip) && !$this->isInProxiedIps($ip)) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * Check that a given string is a valid IP address.
     */
    private static function isValid(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }
}
