<?php

namespace Cdr\ApiTester;

use ApiPlatform\Symfony\Bundle\Test\ApiTestAssertionsTrait;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as BaseTestCase;
use JsonSerializable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use PHPUnit\Event;

class ApiTestCase extends BaseTestCase
{
    use ApiTestAssertionsTrait;

    protected HttpClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    /**
     * Execute an http request in json format to a REST API endpoint
     *
     * @param array<array-key,mixed>|null $body
     */
    protected function request(string $method, string $url, ?array $body = null, ?string $token = null): ResponseInterface
    {
        $parameters['headers'] = [
            'Accept' => 'application/ld+json',
        ];
        if (!is_null($body)) {
            $parameters['headers']['Content-Type'] = 'application/json';
            $parameters['body'] = json_encode($body);
        }
        if (!is_null($token)) {
            $parameters['headers']['Authorization'] = 'Bearer ' . $token;
        }

        return $this->client->request($method, $url, $parameters);
    }

    /**
     * Execute an http request on a web endpoint
     */
    protected function requestHTML(string $method, string $url, ?string $token = null): ResponseInterface
    {
        $parameters['headers'] = [
            'Accept' => 'text/html',
        ];
        if (!is_null($token)) {
            $parameters['headers']['Authorization'] = 'Bearer ' . $token;
        }

        return $this->client->request($method, $url, $parameters);
    }

    /**
     * Execute an http request over a file and download the content response
     */
    protected function requestDownload(string $url, ?string $token = null): ResponseInterface
    {
        $parameters = ['headers' => []];
        if (!is_null($token)) {
            $parameters['headers']['Authorization'] = 'Bearer ' . $token;
        }
        ob_start();
        $response = $this->client->request('GET', $url, $parameters);
        ob_end_clean();

        return $response;
    }

    /**
     * Execute an http request with a file attached
     *
     * @param array<array-key,mixed>|null $attributes
     */
    protected function requestUpload(string $url, string $fileName, ?array $attributes = null, ?string $token = null): ResponseInterface
    {
        $tmp = __DIR__ . '/tmpFile' . microtime(true);
        copy($fileName, $tmp);
        $originalName = explode('/', $fileName);
        $originalName = end($originalName);
        $file = new UploadedFile($fileName, $originalName, null, null, true);

        $parameters['headers'] = [
            'Accept' => 'application/ld+json',
            'Content-Type' => 'multipart/form-data',
        ];
        $parameters['extra'] = [
            'files' => [
                'file' => $file,
            ],
        ];
        if (!is_null($attributes)) {
            $parameters['extra']['parameters'] = $attributes;
        }
        if (!is_null($token)) {
            $parameters['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = $this->client->request('POST', $url, $parameters);
        rename($tmp, $fileName);

        return $response;
    }

    /**
     * Execute an http request over a file, and check that this file is downloaded in response
     * @param string $url : the endpoint where the file will be served
     * @param ?string $token : an optional authorization token
     * @param ?string $expectedName : the name of the downloaded file
     * @param ?string $expectedAttachmentType : inline|attachment the expected content-disposition
     */
    protected function assertRequestDownload(string $url, ?string $token = null, ?string $expectedName = null, ?string $expectedAttachmentType = 'attachment'): void
    {
        $this->requestDownload($url, $token);
        if (is_null($expectedName)) {
            $expectedName = explode('/', $url);
            $expectedName = end($expectedName);
            $expectedName = '"' . $expectedName . '"';
        }
        $this->assertResponseHeaderSame('content-disposition', $expectedAttachmentType . '; filename=' . $expectedName);
        $this->assertResponseIsSuccessful();
    }

    public function assertRequestDownloadAttachment(string $url, ?string $token = null, ?string $expectedName = null): void
    {
        $this->assertRequestDownload($url, $token, $expectedName, 'attachment');
    }

    public function assertRequestDownloadInline(string $url, ?string $token = null, ?string $expectedName = null): void
    {
        $this->assertRequestDownload($url, $token, $expectedName, 'inline');
    }

    public function assertCountResponse(int $expectedCount, string $url, ?string $token = null): void
    {
        $response = $this->request('GET', $url . '?count=1', null, $token);
        $content = $response->getContent(false);
        $this->assertTrue(is_numeric($content));
        $this->assertEquals($expectedCount, intval($content));
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function assertRequestResponse(string|null|array $expectedFile, string $method, string $url, array|string|null $body = null, ?string $token = null): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->request($method, $url, $body, $token);

        if (is_null($expectedFile)) {
            $this->assertEquals('', $response->getContent(false));
        } else {
            $this->assertJsonContent($expectedFile, $response->getContent(false));
        }
    }

    public function assertHTMLRequestResponse(string|null $expectedFile, string $method, string $url, ?string $token = null): void
    {
        $response = $this->requestHTML($method, $url, $token);

        if (is_null($expectedFile)) {
            $this->assertEquals('', $response->getContent(false));
        } else {
            self::assertEquals(file_get_contents($expectedFile), $response->getContent(false));
        }
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function assertUploadRequestResponse(string|null $expectedFile, string $url, string $fileName, array|string|null $body = null, ?string $token = null): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->requestUpload($url, $fileName, $body, $token);

        if (is_null($expectedFile)) {
            $this->assertEquals('', $response->getContent(false));
        } else {
            $this->assertJsonContent($expectedFile, $response->getContent(false));
        }
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function assertUploadNotFound(string $url, string $fileName, array|string|null $body = null, ?string $token = null): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->requestUpload($url, $fileName, $body, $token);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', json_decode($response->getContent(false), true)['hydra:description']);
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function assertUploadInvalid(string $url, string $fileName, array|string|null $body = null, ?string $token = null, ?string $expectedMessage = null): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->requestUpload($url, $fileName, $body, $token);
        $this->assertEquals(422, $response->getStatusCode());
        if (!is_null($expectedMessage)) {
            $this->assertEquals($expectedMessage, json_decode($response->getContent(false), true)['hydra:description']);
        }
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function assertUploadNotAvailable(string $url, string $fileName, array|string|null $body = null, ?string $token = null): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->requestUpload($url, $fileName, $body, $token);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Can not upload a new version of the document', json_decode($response->getContent(false), true)['hydra:description']);
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function assertDeletedSuccessResponse(string $method, string $url, array|string|null $body = null, ?string $token = null): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->request($method, $url, $body, $token);
        $this->assertEquals(204, $response->getStatusCode());
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function assertNotFound(string $method, string $url, array|string|null $body = null, ?string $token = null): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->request($method, $url, $body, $token);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', json_decode($response->getContent(false), true)['hydra:description']);
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function assertBadRequest(string $message, string $method, string $url, array|string|null $body = null, ?string $token = null): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->request($method, $url, $body, $token);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals($message, json_decode($response->getContent(false), true)['hydra:description']);
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function assertHtmlNotFound(string $method, string $url, array|string|null $body = null, ?string $token = null, ?string $expectedMessage = 'Not Found'): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->request($method, $url, $body, $token);
        $this->assertEquals(404, $response->getStatusCode());
        $contentHTML = $response->getContent(false);
        $this->assertStringContainsString('<title>' . $expectedMessage . '</title>', $contentHTML);
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function assertAccessDenied(string $method, string $url, array|string|null $body = null, ?string $token = null): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->request($method, $url, $body, $token);
        $this->assertEquals(403, $response->getStatusCode());
        $content = $response->getContent(false);
        $this->assertEquals('Access Denied.', json_decode($content, true)['hydra:description']);
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function assertInvalid(?string $message, string $method, string $url, array|string|null $body = null, ?string $token = null): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->request($method, $url, $body, $token);
        $this->assertEquals(422, $response->getStatusCode());
        $content = $response->getContent(false);
        if (!is_null($message)) {
            $this->assertEquals($message, json_decode($content, true)['hydra:description']);
        }
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function overwriteRequestResponse(string $expectedFile, string $method, string $url, array|string|null $body = null, ?string $token = null): void
    {
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->request($method, $url, $body, $token);

        file_put_contents($expectedFile, $response->getContent(false));
        $this->addWarning('Overwriting test data ... , you are doing the inverse of an assertion, are you sure ?');
        $this->markAsRisky();
    }

    public function overwriteHTMLRequestResponse(string $expectedFile, string $method, string $url, ?string $token = null): void
    {
        $response = $this->requestHTML($method, $url, $token);
        file_put_contents($expectedFile, $response->getContent(false));
        $this->addWarning('Overwriting test data ... , you are doing the inverse of an assertion, are you sure ?');
        $this->markAsRisky();
    }

    /**
     * @param array<array-key,mixed>|string|null $body
     */
    public function overwriteUploadRequestResponse(string|null $expectedFile, string $url, string $fileName, array|string|null $body = null, ?string $token = null): void
    {
        if (is_null($expectedFile)) {
            $this->addWarning('Cannot overwriting file (expectedFile is null)');

            return;
        }
        if (is_string($body) && !empty($body)) {
            $body = json_decode(file_get_contents($body) ?: '', true);
        }

        $response = $this->requestUpload($url, $fileName, $body, $token);

        file_put_contents($expectedFile, $response->getContent(false));
        $this->addWarning('Overwriting test data ... , you are doing the inverse of an assertion, are you sure ?');
        $this->markAsRisky();
    }

    public function assertJsonContent(string|array|null $expected, string|false $content): void
    {
        if (false === $content) {
            self::fail('json content is false');
        }
        $actual = json_decode($content, true);
        $expected = is_string($expected) ? json_decode(file_get_contents($expected) ?: '', true) : $expected;
        self::assertEquals($expected, $actual);
    }

    /**
     * @param array<array-key,mixed>|JsonSerializable $actual
     */
    public function overwriteJsonContent(string $expectedFile, array|JsonSerializable|string|false $actual): void
    {
        assert(false !== $actual);
        if (!is_string($actual)) {
            $actual = json_encode($actual);
        }
        file_put_contents($expectedFile, $actual);
        $this->addWarning('Overwriting test data ... , you are doing the inverse of an assertion, are you sure ?');
        $this->markAsRisky();
    }

    public function assertYamlContent(string $expectedFile, string $content): void
    {
        $actual = Yaml::parse($content);
        $expected = Yaml::parse(file_get_contents($expectedFile) ?: '');
        self::assertEquals($expected, $actual);
    }

    /**
     * @param array<array-key,mixed>|JsonSerializable $actual
     */
    public function overwriteYamlContent(string $expectedFile, array|JsonSerializable $actual): void
    {
        file_put_contents($expectedFile, Yaml::dump($actual));
        $this->addWarning('Overwriting test data ... , you are doing the inverse of an assertion, are you sure ?');
        $this->markAsRisky();
    }

    private function addWarning(?string $message = null): void
    {
        Event\Facade::emitter()->testTriggeredWarning(
            $this->valueObjectForEvents(),
            $message,
            __FILE__, __LINE__, false, false
        );
    }

    private function markAsRisky(?string $message = null): void
    {
        Event\Facade::emitter()->testConsideredRisky(
            $this->valueObjectForEvents(),
            $message,
        );
    }
}