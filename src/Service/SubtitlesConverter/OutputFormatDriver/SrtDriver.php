<?php

declare(strict_types=1);

namespace App\Service\SubtitlesConverter\OutputFormatDriver;

final class SrtDriver implements OutputFormatDriverInterface
{
    public function getFileExtension(): string
    {
        return 'srt';
    }

    public function convert(array $lines): string
    {
        $output = '';
        $i = 0;

        $patterns = [
            ['(\d{2}):(\d{2}):(\d{2})\.(\d{3})', '$1:$2:$3,$4'], // '01:52:52.554'
            ['(\d{2}):(\d{2})\.(\d{3})', '00:$1:$2,$3'] // '00:08.301'
        ];

        $modifyPattern = '/ [a-zA-Z]\w*:\S+/';

        foreach ($lines as $line) {
            foreach ($patterns as $pattern) {
                $match = preg_match("/^$pattern[0]/", $line);
                if (is_numeric($match) && $match > 0) {
                    $i++;
                    $output .= PHP_EOL . $i; // Aegisub needs empty line here
                    $output .= PHP_EOL;
                    $line = preg_replace("/$pattern[0]/", $pattern[1], $line);
                    $line = preg_replace($modifyPattern, '', $line);
                    break;
                }
            }

            $output .= trim(strip_tags($line)) . PHP_EOL;
        }

        return $output;
    }
}
