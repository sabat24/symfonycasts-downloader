<?php

namespace App\Service\SubtitlesConverter\OutputFormatDriver;

interface OutputFormatDriverInterface
{
    public function convert(array $lines): string;

    public function getFileExtension(): string;
}
