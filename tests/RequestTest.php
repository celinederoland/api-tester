<?php

namespace Cdr\ApiTester\Tests;

use Prophecy\Argument;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RequestTest extends TestCase
{

    public function testRequestGETMethod(): void
    {
        $this->request('GET', '/foo', null, 'azerty');

        /** @noinspection PhpUndefinedMethodInspection */
        $this->clientProphecy->request('GET', '/foo', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'Authorization' => 'Bearer azerty',
            ]
        ])->shouldBeCalled();

        $this->verifyProphecyDoubles();
    }

    public function testRequestPOSTMethod(): void
    {
        $this->request('POST', '/foo', ['foo' => 'bar'], 'azerty');

        /** @noinspection PhpUndefinedMethodInspection */
        $this->clientProphecy->request('POST', '/foo', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'Authorization' => 'Bearer azerty',
                'Content-Type' => 'application/json'
            ],
            'body' => '{"foo":"bar"}',
        ])->shouldBeCalled();

        $this->verifyProphecyDoubles();
    }

    public function testRequestPUTMethod(): void
    {
        $this->request('PUT', '/foo', ['foo' => 'bar']);

        /** @noinspection PhpUndefinedMethodInspection */
        $this->clientProphecy->request('PUT', '/foo', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/json'
            ],
            'body' => '{"foo":"bar"}',
        ])->shouldBeCalled();

        $this->verifyProphecyDoubles();
    }

    public function testRequestGETHtml(): void
    {
        $this->requestHTML('GET', '/foo', 'azerty');

        /** @noinspection PhpUndefinedMethodInspection */
        $this->clientProphecy->request('GET', '/foo', [
            'headers' => [
                'Accept' => 'text/html',
                'Authorization' => 'Bearer azerty',
            ]
        ])->shouldBeCalled();

        $this->verifyProphecyDoubles();
    }

    public function testRequestDownload(): void
    {
        $this->requestDownload('/foo', 'azerty');

        /** @noinspection PhpUndefinedMethodInspection */
        $this->clientProphecy->request('GET', '/foo', [
            'headers' => [
                'Authorization' => 'Bearer azerty',
            ]
        ])->shouldBeCalled();

        $this->verifyProphecyDoubles();
    }

    public function testRequestUpload(): void
    {
        $this->requestUpload('/foo', __DIR__ . '/mockUpload.txt', ['foo' => 'bar'], 'azerty');

        /** @noinspection PhpUndefinedMethodInspection */
        $this->clientProphecy->request('POST', '/foo',
            Argument::allOf(
                Argument::withEntry('headers', [
                    'Accept' => 'application/ld+json',
                    'Content-Type' => 'multipart/form-data',
                    'Authorization' => 'Bearer azerty',
                ]),
                Argument::withEntry('extra', Argument::allOf(
                    Argument::withEntry('files', Argument::withEntry('file', Argument::type(UploadedFile::class))),
                    Argument::withEntry('parameters', ['foo' => 'bar'])
                ))
            )
        )->shouldBeCalled();

        $this->verifyProphecyDoubles();
    }
}