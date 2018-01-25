<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use ExtractrIo\Puphpeteer\Puppeteer;
use ExtractrIo\Rialto\Data\JsFunction;
use Symfony\Component\Process\Process;

class PuphpeteerTest extends TestCase
{
    public function setUp(): void
    {
        $this->host = '127.0.0.1:8089';
        $this->url = "http://{$this->host}";
        $this->serverDir = __DIR__.'/resources';

        $this->servingProcess = new Process("php -S {$this->host} -t {$this->serverDir}");
        $this->servingProcess->start();

        // Chrome doesn't support Linux sandbox on many CI environments
        // See: https://github.com/GoogleChrome/puppeteer/blob/master/docs/troubleshooting.md#chrome-headless-fails-due-to-sandbox-issues
        $options = ['args' => ['--no-sandbox', '--disable-setuid-sandbox']];

        $this->browser = (new Puppeteer)->launch($options);
    }

    public function tearDown(): void
    {
        $this->browser->close();

        $this->servingProcess->stop(0);
    }

    /** @test */
    public function can_browse_website()
    {
        $response = $this->browser->newPage()->goto($this->url);

        $this->assertTrue($response->ok(), 'Failed asserting that the response is successful.');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function can_use_method_aliases()
    {
        $page = $this->browser->newPage();

        $page->goto($this->url);

        $page->querySelector('h1');
        $page->querySelectorAll('h1');
        $page->querySelectorEval('h1', JsFunction::create(''));
        $page->querySelectorAllEval('h1', JsFunction::create(''));
    }

    /** @test */
    public function can_evaluate_a_selection()
    {
        $page = $this->browser->newPage();

        $page->goto($this->url);

        $title = $page->querySelectorEval('h1', JsFunction::create(['node'], 'return node.textContent;'));
        $titleCount = $page->querySelectorAllEval('h1', JsFunction::create(['nodes'], 'return nodes.length;'));

        $this->assertEquals('Example Page', $title);
        $this->assertEquals(1, $titleCount);
    }


    /** @test */
    public function can_intercept_requests()
    {
        $page = $this->browser->newPage();

        $page->setRequestInterception(true);

        $page->on('request', JsFunction::create(['request'], "
            request.resourceType() === 'stylesheet' ? request.abort() : request.continue()
        "));

        $page->goto($this->url);

        $backgroundColor = $page->querySelectorEval('h1', JsFunction::create(['node'], "
            return getComputedStyle(node).textTransform;
        "));

        $this->assertNotEquals('lowercase', $backgroundColor);
    }
}
