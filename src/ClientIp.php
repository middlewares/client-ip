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
     * @var bool
     */
    private $remote = false;

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
    ) {
        $this->proxyIps = $ips;
        $this->proxyHeaders = $headers;

        return $this;
    }

    /**
     * To get the ip from a remote service.
     * Useful for testing purposes on localhost.
     */
    public function remote(bool $remote = true): self
    {
        $this->remote = $remote;

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
     *
     * @return string|null
     */
    private function getIp(ServerRequestInterface $request)
    {
        $remoteIp = $this->getRemoteIp();

        if (!empty($remoteIp)) {
            // Found IP address via remote service.
            return $remoteIp;
        }

        $localIp = $this->getLocalIp($request);

        if ($this->proxyIps && !in_array($localIp, $this->proxyIps)) {
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
     * Returns the IP address from remote service.
     *
     * @return string|null
     */
    private function getRemoteIp()
    {
        if ($this->remote) {
            $ip = file_get_contents('http://ipecho.net/plain');

            if ($ip && self::isValid($ip)) {
                return $ip;
            }
        }
    }

    /**
     * Returns the first valid proxied IP found.
     *
     * @return string|null
     */
    private function getProxiedIp(ServerRequestInterface $request)
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
    }

    /**
     * Returns the remote address of the request, if valid.
     *
     * @return string|null
     */
    private function getLocalIp(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();

        if (!empty($server['REMOTE_ADDR']) && self::isValid($server['REMOTE_ADDR'])) {
            return $server['REMOTE_ADDR'];
        }
    }

    /**
     * Returns the first valid ip found in the Forwarded or X-Forwarded header.
     *
     * @return string|null
     */
    private function getForwardedHeaderIp(string $header)
    {
        foreach (array_reverse(array_map('trim', explode(',', strtolower($header)))) as $values) {
            foreach (array_reverse(array_map('trim', explode(';', $values))) as $directive) {
                if (strpos($directive, 'for=') !== 0) {
                    continue;
                }

                $ip = trim(substr($directive, 4));

                if (self::isValid($ip) && !in_array($ip, $this->proxyIps)) {
                    return $ip;
                }
            }
        }
    }

    /**
     * Returns the first valid ip found in the header.
     *
     * @return string|null
     */
    private function getHeaderIp(string $header)
    {
        foreach (array_reverse(array_map('trim', explode(',', $header))) as $ip) {
            if (self::isValid($ip) && !in_array($ip, $this->proxyIps)) {
                return $ip;
            }
        }
    }

    /**
     * Check that a given string is a valid IP address.
     */
    private static function isValid(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }
}
