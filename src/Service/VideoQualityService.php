<?php

declare(strict_types=1);

namespace App\Service;

final class VideoQualityService
{

    public const VIDEO_QUALITY_HD = 'hd';

    public const VIDEO_QUALITY_MD = 'md';

    public const VIDEO_QUALITY_SD = 'sd';

    public const OPTIONS_VIDEO_QUALITY = [
        'hd' => self::VIDEO_QUALITY_HD,
        'sd' => self::VIDEO_QUALITY_SD,
    ];

    public function isRequiredQuality(string $filePath, string $videoQuality): bool
    {
        $fileInfo = (new \getID3())->analyze($filePath);

        return $this->getQualityFromFileInfo($fileInfo) === $videoQuality;

    }

    private function getQualityFromFileInfo(array $fileInfo): string
    {
        if ($fileInfo['video']['resolution_x'] > 1280 && $fileInfo['video']['resolution_y'] > 720) {
            return self::VIDEO_QUALITY_HD;
        } else if ($fileInfo['video']['resolution_x'] < 1280 && $fileInfo['video']['resolution_y'] < 720) {
            return self::VIDEO_QUALITY_SD;
        }

        return self::VIDEO_QUALITY_MD;
    }
}
