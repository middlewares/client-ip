<?php

namespace Middlewares\Tests;

use Middlewares\ClientIp;
use Middlewares\Utils\Dispatcher;
use Middlewares\Utils\CallableMiddleware;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response;

class ClientIpTest extends \PHPUnit_Framework_TestCase
{
    public function ipsProvider()
    {
        return [
            [
                [
                    'Client-Ip' => 'unknow,123.456.789.10,123.234.123.10',
                    'X-Forwarded' => '123.234.123.10',
                ],
                '123.234.123.10',
            ], [
                [
                    'Client-Ip' => 'unknow,123.456.789.10,123.234.123.10',
                    'X-Forwarded' => '123.234.123.11',
                ],
                '123.234.123.10',
            ],
        ];
    }

    /**
     * @dataProvider ipsProvider
     */
    public function testClientIp(array $headers, $ip)
    {
        $request = new ServerRequest();

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = (new Dispatcher([
            new ClientIp(),
            new CallableMiddleware(function ($request) {
                $response = new Response();
                $response->getBody()->write($request->getAttribute('client-ip'));

                return $response;
            }),
        ]))->dispatch($request);

        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->assertEquals($ip, (string) $response->getBody());
    }

    public function testRemote()
    {
        $expected = filter_var(
            file_get_contents('http://ipecho.net/plain'),
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6
        );

        $this->assertNotFalse($expected);
        $request = new ServerRequest();

        $response = (new Dispatcher([
            (new ClientIp())->remote(),
            new CallableMiddleware(function ($request) {
                $response = new Response();
                $response->getBody()->write($request->getAttribute('client-ip'));

                return $response;
            }),
        ]))->dispatch($request);

        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->assertEquals($expected, (string) $response->getBody());
    }
}
