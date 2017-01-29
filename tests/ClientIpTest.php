<?php

namespace Middlewares\Tests;

use Middlewares\ClientIp;
use Middlewares\Utils\Dispatcher;
use Middlewares\Utils\Factory;
use Psr\Http\Message\ResponseInterface;

class ClientIpTest extends \PHPUnit_Framework_TestCase
{
    public function ipsProvider()
    {
        return [
            [
                [
                    'X-Forwarded' => 'unknow,123.456.789.10,123.234.123.10',
                    'Client-Ip' => '123.234.123.10',
                ],
                '123.234.123.10',
            ], [
                [
                    'X-Forwarded' => 'unknow,123.456.789.10,123.234.123.10',
                    'Client-Ip' => '123.234.123.11',
                ],
                '123.234.123.10',
            ],
        ];
    }

    /**
     * @dataProvider ipsProvider
     */
    public function testClientIpProxy(array $headers, $ip)
    {
        $request = Factory::createServerRequest(['REMOTE_ADDR' => '123.123.123.123']);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = Dispatcher::run([
            (new ClientIp())->proxy(),
            function ($request) {
                echo $request->getAttribute('client-ip');
            },
        ], $request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($ip, (string) $response->getBody());
    }

    public function testClientIpNotProxy()
    {
        $request = Factory::createServerRequest(['REMOTE_ADDR' => '123.123.123.123'])
            ->withHeader('X-Forwarded', '11.11.11.11');

        $response = Dispatcher::run([
            new ClientIp(),
            function ($request) {
                echo $request->getAttribute('client-ip');
            },
        ], $request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('123.123.123.123', (string) $response->getBody());
    }

    public function testRemote()
    {
        $expected = filter_var(
            file_get_contents('http://ipecho.net/plain'),
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6
        );

        $this->assertNotFalse($expected);

        $response = Dispatcher::run([
            (new ClientIp())->remote(),
            function ($request) {
                echo $request->getAttribute('client-ip');
            },
        ]);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals($expected, (string) $response->getBody());
    }
}
