<?php

namespace Cdr\ApiTester\Tests;

use Cdr\ApiTester\ApiTestCase;
use Prophecy\Argument;
use Prophecy\Argument\Token\AnyValueToken;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TestCase extends ApiTestCase
{
    use ProphecyTrait;

    protected ObjectProphecy $clientProphecy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientProphecy = $this->prophesize(HttpClientInterface::class);
        $this->client = $this->clientProphecy->reveal();
        /** @noinspection PhpParamsInspection */
        $this->clientProphecy->request(
            new AnyValueToken(),
            new AnyValueToken(),
            Argument::type('array')
        )->willReturn(new DummyResponse());
    }
}