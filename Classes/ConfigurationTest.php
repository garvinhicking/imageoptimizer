<?php
namespace Lemming\Imageoptimizer;

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Messaging\Renderer\BootstrapRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationTest
{
    private OptimizeImageService $service;

    private FlashMessageService $flashMessageService;

    private BootstrapRenderer $flashMessageRenderer;

    public function __construct()
    {
        $this->service = GeneralUtility::makeInstance(OptimizeImageService::class);
        $this->flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $this->flashMessageRenderer = GeneralUtility::makeInstance(BootstrapRenderer::class);
    }

    public function testCommand(array $params): string
    {
        $fileExtension = $params['fieldValue'];
        $messageQueue = $this->flashMessageService->getMessageQueueByIdentifier();

        foreach ([false, true] as $fileIsUploaded) {
            if ($fileExtension === 'svg' && !$fileIsUploaded) {
                continue;
            }

            $header = sprintf('%s%s', strtoupper($fileExtension), $fileIsUploaded ? ' on Upload' : '');
            $file = sprintf(
                '%s/Resources/Private/Images/example.%s',
                ExtensionManagementUtility::extPath('imageoptimizer'),
                $fileExtension
            );
            $temporaryFile = GeneralUtility::tempnam('imageoptimizer', $fileExtension);
            copy($file, $temporaryFile);

            try {
                $returnValue = $this->service->process(
                    $temporaryFile,
                    $fileExtension,
                    $fileIsUploaded,
                    true
                );

                $message = GeneralUtility::makeInstance(
                    FlashMessage::class,
                    implode(PHP_EOL, $this->service->getOutput()),
                    sprintf('%s: %s', $header, $this->service->getCommand()),
                    $returnValue ? ContextualFeedbackSeverity::OK : ContextualFeedbackSeverity::ERROR
                );
            } catch (BinaryNotFoundException $e) {
                $message = GeneralUtility::makeInstance(
                    FlashMessage::class,
                    OptimizeImageService::BINARY_NOT_FOUND,
                    sprintf(
                        $header,
                        strtoupper($fileExtension),
                        $fileIsUploaded ? 'on Upload' : ''
                    ),
                    ContextualFeedbackSeverity::ERROR
                );
            }

            unlink($temporaryFile);
            $messageQueue->addMessage($message);
        }

        return $messageQueue->renderFlashMessages($this->flashMessageRenderer);
    }
}
