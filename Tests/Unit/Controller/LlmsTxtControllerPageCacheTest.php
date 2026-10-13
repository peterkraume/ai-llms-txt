<?php

declare(strict_types=1);

namespace WebVision\AiLlmsTxt\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\AiLlmsTxt\Controller\LlmsTxtController;
use WebVision\AiLlmsTxt\Repository\PageRepository;
use WebVision\AiLlmsTxt\Service\ConfigurationService;
use WebVision\AiLlmsTxt\Service\LlmsTxtGeneratorService;
use WebVision\AiLlmsTxt\Service\MarkdownConverterService;

/**
 * Unit tests for how the controller steers TYPO3's page cache.
 *
 * Both page types are cached USER objects, so whatever they return lands in the page
 * cache unless the controller says otherwise - independent of the extension's own cache.
 */
final class LlmsTxtControllerPageCacheTest extends UnitTestCase
{
    private CacheInstruction $cacheInstruction;
    private CacheDataCollector $cacheDataCollector;
    private ServerRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheInstruction = new CacheInstruction();
        $this->cacheDataCollector = new CacheDataCollector();
        $this->request = (new ServerRequest())
            ->withAttribute('frontend.cache.instruction', $this->cacheInstruction)
            ->withAttribute('frontend.cache.collector', $this->cacheDataCollector);
    }

    #[Test]
    public function llmsTxtIsKeptOutOfThePageCacheWhenNoCacheLifetimeIsConfigured(): void
    {
        $generator = $this->createMock(LlmsTxtGeneratorService::class);
        $generator->method('generateLlmsTxt')->willReturn("# Site\n");

        $this->createSubject(cacheLifetime: 0, generator: $generator)->generateAction('', [], $this->request);

        static::assertFalse($this->cacheInstruction->isCachingAllowed());
    }

    #[Test]
    public function llmsTxtPageCacheEntryIsTaggedPagesWithTheConfiguredLifetime(): void
    {
        $generator = $this->createMock(LlmsTxtGeneratorService::class);
        $generator->method('generateLlmsTxt')->willReturn("# Site\n");

        $this->createSubject(cacheLifetime: 900, generator: $generator)->generateAction('', [], $this->request);

        static::assertTrue($this->cacheInstruction->isCachingAllowed());
        static::assertEquals([new CacheTag('pages', 900)], $this->cacheDataCollector->getCacheTags());
        static::assertSame(900, $this->cacheDataCollector->resolveLifetime());
    }

    #[Test]
    public function llmsTxtErrorResponseIsNotPageCached(): void
    {
        $generator = $this->createMock(LlmsTxtGeneratorService::class);
        $generator->method('generateLlmsTxt')->willThrowException(new \RuntimeException('database gone', 1769076610));

        $result = $this->createSubject(cacheLifetime: 900, generator: $generator)->generateAction('', [], $this->request);

        static::assertStringContainsString('error: Failed to generate content', $result);
        static::assertFalse($this->cacheInstruction->isCachingAllowed());
    }

    #[Test]
    public function markdownErrorResponseIsNotPageCached(): void
    {
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->method('findById')->willThrowException(new \RuntimeException('database gone', 1769076611));

        $result = $this->createSubject(cacheLifetime: 0, pageRepository: $pageRepository)->renderPageAsMarkdown('', [], $this->request);

        static::assertSame("# Error\n\nFailed to render page.\n", $result);
        static::assertFalse($this->cacheInstruction->isCachingAllowed());
    }

    private function createSubject(
        int $cacheLifetime,
        ?LlmsTxtGeneratorService $generator = null,
        ?PageRepository $pageRepository = null
    ): LlmsTxtController {
        $configurationService = $this->createMock(ConfigurationService::class);
        $configurationService->method('isEnabled')->willReturn(true);
        $configurationService->method('getCurrentPageId')->willReturn(1);
        $configurationService->method('getCacheLifetime')->willReturn($cacheLifetime);
        $configurationService->method('getSiteUrl')->willReturn('https://example.com/');

        return new LlmsTxtController(
            $generator ?? $this->createMock(LlmsTxtGeneratorService::class),
            $configurationService,
            $this->createMock(MarkdownConverterService::class),
            $pageRepository ?? $this->createMock(PageRepository::class),
            new NullLogger()
        );
    }
}
