<?php
declare(strict_types = 1);

namespace Middlewares\Tests;

use Eloquent\Phony\Phpunit\Phony;
use Middlewares\ClientIp;
use Middlewares\Utils\Dispatcher;
use Middlewares\Utils\Factory;
use PHPUnit\Framework\TestCase;

class ClientIpTest extends TestCase
{
    public function tearDown()
    {
        // http://eloquent-software.com/phony/latest/#restoring-global-functions-after-stubbing
        Phony::restoreGlobalFunctions();
    }

    public function ipsProvider(): array
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
                    'Forwarded' => 'unknow',
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
    public function testClientIpProxy(array $headers, string $ip)
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

        $this->assertEquals('123.123.123.123', (string) $response->getBody());
    }

    public function testCustomAttribute()
    {
        $request = Factory::createServerRequest(['REMOTE_ADDR' => '123.123.123.123']);

        $response = Dispatcher::run([
            (new ClientIp())->attribute('ip'),
            function ($request) {
                echo $request->getAttribute('ip');
            },
        ], $request);

        $this->assertEquals('123.123.123.123', (string) $response->getBody());
    }

    public function testProxyIp()
    {
        $request = Factory::createServerRequest(['REMOTE_ADDR' => '1.1.1.1'])
            ->withHeader('X-Forwarded', '2.2.2.2');

        $response = Dispatcher::run([
            (new ClientIp())->proxy(['3.3.3.3']),
            function ($request) {
                echo $request->getAttribute('client-ip');
            },
        ], $request);

        $this->assertEquals('1.1.1.1', (string) $response->getBody());

        $response = Dispatcher::run([
            (new ClientIp())->proxy(['1.1.1.1']),
            function ($request) {
                echo $request->getAttribute('client-ip');
            },
        ], $request);

        $this->assertEquals('2.2.2.2', (string) $response->getBody());
    }

    public function testNoRemoteAddr()
    {
        $request = Factory::createServerRequest();

        $response = Dispatcher::run([
            new ClientIp(),
            function ($request) {
                echo $request->getAttribute('client-ip');
            },
        ], $request);

        $this->assertEquals('', (string) $response->getBody());
    }

    public function testRemote()
    {
        // http://eloquent-software.com/phony/latest/#stubbing-global-functions
        $fileGetContents = Phony::stubGlobal('file_get_contents', 'Middlewares');
        $fileGetContents->returns($expected = '192.168.0.100');

        $response = Dispatcher::run([
            (new ClientIp())->remote(),
            function ($request) {
                echo $request->getAttribute('client-ip');
            },
        ]);

        $this->assertEquals($expected, (string) $response->getBody());

        // the service should have been called
        $fileGetContents->calledWith('http://ipecho.net/plain');
    }
}
