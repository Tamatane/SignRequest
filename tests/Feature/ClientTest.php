<?php

namespace AtaneNL\SignRequest\Tests\Feature;

use AtaneNL\SignRequest\Client;
use AtaneNL\SignRequest\Exceptions\LocalException;
use AtaneNL\SignRequest\Exceptions\RemoteException;
use AtaneNL\SignRequest\Tests\TestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class ClientTest extends TestCase
{

    protected function mockResponseHandler(int $status, array $headers = [], ?string $body = null): HandlerStack
    {
        $responses = [
            new Response($status, $headers, $body),
        ];

        $mock = new MockHandler($responses);

        return new HandlerStack($mock);
    }

    /**
     * Test getTeam succeeds if subdomain is null
     *
     * @return void
     * @throws LocalException
     * @throws RemoteException
     */
    public function testGetTeamWithoutSubdomain(): void
    {
        $handler = $this->mockResponseHandler(200, [], json_encode([
            'name' => 'test'
        ]));

        $client = new Client('[test]', null, ['handler' => $handler]);

        $response = $client->getTeam("test");

        $this->assertEquals(['name' => 'test'], $response);
    }

    /**
     * Test getTeam should throw an exception if initialized with a subdomain
     * @return void
     * @throws LocalException
     * @throws RemoteException
     */
    public function testGetTeamWithSubdomain(): void
    {

        $this->expectException(LocalException::class);
        $this->expectDeprecationMessage("This request cannot be sent to a subdomain. Initialize the client without a subdomain.");

        $handler = $this->mockResponseHandler(200, [], json_encode([
            'name' => 'test'
        ]));

        $client = new Client('[test]', 'test', ['handler' => $handler]);

        $client->getTeam("test");
    }
}
