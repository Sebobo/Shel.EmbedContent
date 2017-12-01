<?php

namespace Shel\EmbedContent\Command;

/*                                                                        *
 * This script belongs to the Flow package "Shel.EmbedContent".           *
 *                                                                        *
 * @author Sebastian Helzle <sebastian@helzle.it>                         *
 *                                                                        */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Shel\EmbedContent\Service\RequestContentService;

/**
 * Provides CLI features for academy events handling
 *
 * @Flow\Scope("singleton")
 */
class RequestContentCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var RequestContentService
     */
    protected $requestContentService;

    /**
     * Test requesting content from the given source
     * @param string $path
     * @param string $selector
     * @param string $baseUrl
     */
    public function requestContentCommand($path, $selector, $baseUrl = '')
    {
        $content = $this->requestContentService->getContent($baseUrl, $path, $selector);

        $this->outputLine('Result of your request to "' . $baseUrl . $path . '" and selector "' . $selector . '":');
        $this->outputLine('###############');
        $this->outputLine($content);
        $this->outputLine('###############');
    }
}
