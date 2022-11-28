<?php

declare(strict_types=1);

namespace Edudobay\HttpResponseAssertions;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;
use Psr\Http\Message\ResponseInterface;
use TypeError;
use Closure;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\JsonMatches;
use Psr\Http\Message\StreamInterface;

use function substr;

class HttpResponse extends Constraint
{
    private int $bodyExcerptMaxSize = 1_000;
    private readonly null|array|Closure $headers;

    private function __construct(
        private readonly ?int $statusCode = null, 
        private readonly ?string $type = null,
        private readonly null|array|Closure $processedBody = null,
        private readonly null|string|array|Closure $rawBody = null,
        null|array|Closure $headers = null,
    ) {
        $this->headers = match (true) {
            is_array($headers) => $this->normalizeHeaders($headers),
            default => $headers,
        };
    }

    private function normalizeHeaders(array $headers): array
    {
        foreach ($headers as $name => &$value) {
            if (! is_array($value)) {
                $value = [$value];
            }
            unset($value);
        }

        return $headers;
    }

    public static function isJson(
        null|int|Closure $statusCode = null,
        null|array|Closure $body = null,
        null|string|Closure $rawBody = null,
        null|array|Closure $headers = null,
    ): self {
        if ($body !== null && $rawBody !== null) {
            throw new InvalidArgumentException('Cannot use both $body and $rawBody.');
        }

        return new self(
            statusCode: $statusCode,
            type: 'json',
            processedBody: $body,
            rawBody: $rawBody,
            headers: $headers,
        );
    }

    public static function raw(
        null|int|Closure $statusCode = null,
        null|string|Closure $body = null,
        null|array|Closure $headers = null,
    ): self {
        return new self(
            statusCode: $statusCode,
            rawBody: $body,
            headers: $headers,
        );
    }

    public static function isEmpty(
        null|int|Closure $statusCode = null,
        null|array|Closure $headers = null,
    ): self {
        return new self(
            statusCode: $statusCode,
            type: 'empty',
            headers: $headers,
        );
    }

    public function toString(): string
    {
        return "has status code {$this->statusCode}";
    }

    private function checkStatusCode(int $statusCode): bool
    {
        return $this->statusCode === null
            || $statusCode === $this->statusCode;
    }

    private function checkBody(StreamInterface $body): bool
    {
        $body->rewind();
        $bodyContents = $body->getContents();

        if ($this->type === 'empty') {
            return $bodyContents === '';
        }

        if (is_string($this->rawBody)) {
            if ($bodyContents !== $this->rawBody) {
                return false;
            }
        } elseif (is_callable($this->rawBody)) {
            if (($this->rawBody)($bodyContents) === false) {
                return false;
            }
        }

        if ($this->type === 'json') {
            return $this->checkJsonBody($bodyContents);
        }

        return true;
    }

    private function checkJsonBody(string $rawBody): bool
    {
        $jsonChecked = false;

        if (is_array($this->processedBody)) {
            Assert::assertJsonStringEqualsJsonString(
                json_encode($this->processedBody),
                $rawBody,
                'Body does not match expectation.'
            );
            $jsonChecked = true;
        } elseif (is_callable($this->processedBody)) {
            Assert::assertJson($rawBody);
            $processedBody = json_decode($rawBody, associative: true);
            $result = ($this->processedBody)($processedBody, $rawBody);
            $jsonChecked = true;
            return $result !== false;
        }

        if (! $jsonChecked) {
            Assert::assertJson($rawBody);
        }

        return true;
    }

    private function checkHeaders(ResponseInterface $response): bool
    {
        if (is_array($this->headers)) {
            $actualHeaders = [];

            foreach ($this->headers as $name => $expectedValue) {
                $actualHeaders[$name] = $response->getHeader($name);
            }

            Assert::assertEquals(
                $this->headers,
                $actualHeaders,
                sprintf("Headers do not match expectations.")
            );
        }

        return true;
    }

    protected function matches($other): bool
    {
        Assert::assertInstanceOf(ResponseInterface::class, $other);

        if (! $this->checkStatusCode($other->getStatusCode())) {
            return false;
        }

        if (! $this->checkHeaders($other)) {
            return false;
        }

        if (! $this->checkBody($other->getBody())) {
            return false;
        }

        return true;
    }

    protected function failureDescription($other): string
    {
        $body = $other->getBody();
        $bodyContents = $body->getContents();
        $bodyExcerpt = substr($bodyContents, 0, $this->bodyExcerptMaxSize);

        $mismatchMessage = null;
        if (! $this->checkStatusCode($other->getStatusCode())) {
            $mismatchMessage = sprintf(
                "status code %s is equal to %s. An excerpt of the body might help you: %s",
                $other->getStatusCode(),
                $this->statusCode,
                $bodyExcerpt,
            );

            return $mismatchMessage;
        }

        if (! $this->checkBody($other->getBody())) {
            return $this->expectedBodyDescription();
        }

        throw new LogicException('no failure');
    }

    private function expectedBodyDescription(): string
    {
        if ($this->type === 'empty') {
            return 'body is empty';
        }

        if (is_string($this->rawBody)) {
            return sprintf("body matches '%s'", $this->rawBody);
        }

        if (is_callable($this->rawBody)) {
            return 'body is accepted by callback';
        }

        if (is_array($this->processedBody)) {
            return sprintf("body matches '%s'", json_encode($this->processedBody));
        }

        if (is_callable($this->processedBody)) {
            return 'body is accepted by callback';
        }
    }
}
