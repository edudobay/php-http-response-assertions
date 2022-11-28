<?php

declare(strict_types=1);

namespace Edudobay\HttpResponseAssertions\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Assert;
use Edudobay\HttpResponseAssertions\HttpResponse;

class HttpResponseTest extends TestCase
{
    public function test_wrong_type()
    {
        $response = new \DateTimeImmutable();

        $this->assertThat($response, HttpResponse::isEmpty(statusCode: 200));
    }

    public function test_require_empty_for_nonempty_body()
    {
        $response = new Response(status: 200, body: 'definitely not empty');

        $this->assertThat($response, HttpResponse::isEmpty(statusCode: 200));
    }

    public function test_empty_body_with_correct_status_code()
    {
        $response = new Response(status: 200);

        $this->assertThat($response, HttpResponse::isEmpty(statusCode: 200));
    }

    public function test_text_body_with_correct_status_code()
    {
        $response = new Response(status: 200, body: 'ello world');

        $this->assertThat($response, HttpResponse::raw(statusCode: 200, body: 'hello world'));
    }

    public function test_JSON_body_with_correct_status_code()
    {
        $response = new Response(status: 200, body: '{"color": "blue"}');

        $this->assertThat($response, HttpResponse::isJson(statusCode: 200, body: ['color' => 'blue']));
    }

    public function test_JSON_body_with_wrong_body()
    {
        $response = new Response(status: 200, body: '{"color": "red"}');

        $this->assertThat($response, HttpResponse::isJson(statusCode: 200, body: ['color' => 'blue']));
    }

    public function test_JSON_body_invalid()
    {
        $response = new Response(status: 200, body: '{color": "red"}');

        $this->assertThat($response, HttpResponse::isJson(statusCode: 200));
    }

    public function test_JSON_body_with_custom_asserts()
    {
        $response = new Response(status: 200, body: '{"color": "red"}');

        $this->assertThat($response, HttpResponse::isJson(
            statusCode: 200,
            body: function (array $body) {
                Assert::assertSame('red', $body['color']);
                return $body['color'] !== 'rouge';
            },
        ));
    }

    public function test_empty_body_with_headers()
    {
        $response = new Response(status: 200, headers: ['User-agent' => ['Yahoo!', 'Mozilla/1.0']]);

        $this->assertThat($response, HttpResponse::isEmpty(
            headers: [
                'User-Agent' => ['Mozilla/1.0']
            ],
        ));
    }
}
