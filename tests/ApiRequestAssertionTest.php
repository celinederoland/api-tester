<?php

namespace Cdr\ApiTester\Tests;

use PHPUnit\Framework\ExpectationFailedException;

class ApiRequestAssertionTest extends TestCase
{

    public function testAssertRequestResponseGET(): void
    {
        // Stub : the http client will respond ['foo'=>'bar'] to our http request
        $this->clientProphecy->request(
            'GET',
            '/foo',
            [
                'headers' => [
                    'Accept' => 'application/ld+json',
                    'Authorization' => 'Bearer azerty',
                ]
            ]
        )->willReturn(new DummyResponse(200, ['foo' => 'bar']));

        // Using our ApiTestCase to assert the response is ['foo'=>'bar'], should succeed
        $this->assertRequestResponse(
            expectedFile: ['foo' => 'bar'],
            method: 'GET',
            url: '/foo',
            body: null,
            token: 'azerty'
        );

        try {
            // Using our ApiTestCase to assert the response is ['foo'=>'tok'], should fail
            $this->assertRequestResponse(
                expectedFile: ['foo' => 'tok'],
                method: 'GET',
                url: '/foo',
                body: null,
                token: 'azerty'
            );
            $this->fail('Asserting that the response [\'foo\' => \'bar\'] equals the expected response [\'foo\' => \'tok\'] should fail');
        } catch (ExpectationFailedException $exception) {

            $expectedError = 'Failed asserting that two arrays are equal.' . PHP_EOL
                . '--- Expected' . PHP_EOL
                . '+++ Actual' . PHP_EOL
                . '@@ @@' . PHP_EOL
                . ' Array (' . PHP_EOL
                . '-    \'foo\' => \'tok\'' . PHP_EOL
                . '+    \'foo\' => \'bar\'' . PHP_EOL
                . ' )' . PHP_EOL;
            $this->assertEquals($expectedError, $exception->getComparisonFailure()->toString());
        }
    }
}