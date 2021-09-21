<?php

declare(strict_types=1);

namespace App\Service\SubtitlesConverter;

use App\Service\SubtitlesConverter\OutputFormatDriver\OutputFormatDriverInterface;

final class WebvttConverterService
{
    private string $webvttPath;

    /**
     * @throws \Exception
     */
    public function __construct(string $webvttPath)
    {
        if (!file_exists($webvttPath)) {
            throw new \Exception("Unable to find path for: $webvttPath");
        }
        $this->webvttPath = $webvttPath;
    }

    /**
     * @throws \Exception
     */
    public function convert(OutputFormatDriverInterface $outputFormatDriver, string $coursePath, string $fileName): string
    {
        $lines = file($this->webvttPath);
        if (empty($lines)) {
            throw new \Exception("Error: Failed to read '$this->webvttPath'");
        }
        $lines = $this->removeHeader($lines);
        $output = $outputFormatDriver->convert($lines);

        $newFileName = "$fileName.{$outputFormatDriver->getFileExtension()}";
        $filePath = "$coursePath/$newFileName";
        $result = file_put_contents($filePath, $output);
        if ($result === false) {
            throw new \Exception("Error: Failed to write to '$newFileName'");
        }

        return $newFileName;
    }

    private function removeHeader($lines): array
    {
        $state = false;
        $ret = [];
        foreach ($lines as $line) {
            if (trim($line) === "") {
                $state = true;
                continue;
            }
            if ($state === true) {
                $ret[] = $line;
            }
        }

        return $ret;
    }

}
