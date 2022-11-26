<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\SubtitlesConverter\OutputFormatDriver\SrtDriver;
use App\Service\SubtitlesConverter\WebvttConverterService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\TransferStats;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

final class DownloaderService
{

    private const FILE_TYPE_VIDEO = 1;

    private const FILE_TYPE_SCRIPT = 2;

    private const FILE_TYPE_CODE = 3;

    private const FILE_TYPE_SUBTITLES = 4;

    private const ALLOW_MAX_REDIRECTS = 2;

    public const OPTIONS_FILE_TYPES = [
        'video' => self::FILE_TYPE_VIDEO,
        'script' => self::FILE_TYPE_SCRIPT,
        'code' => self::FILE_TYPE_CODE,
        'subtitles' => self::FILE_TYPE_SUBTITLES,
    ];

    private const BAD_WINDOWS_PATH_CHARS = ['<', '>', ':', '"', '/', '\\', '|', '?', '*'];

    private SymfonyStyle $io;

    private array $configs;

    private Client $client;

    public function __construct(SymfonyStyle $io, array $configs)
    {
        $this->io = $io;
        $this->configs = $configs;
        $this->client = new Client([
            'base_uri' => $this->configs['URL'],
            'cookies' => true,
        ]);
    }

    public function download(array $options = []): void
    {
        $this->login();

        $downloadPath = "{$this->configs['TARGET']}/";
        if (!is_dir($downloadPath) && !mkdir($downloadPath) && !is_dir($downloadPath)) {
            $this->io->error("Unable to create download directory '$downloadPath'");

            return;
        }

        $courses = $this->getCourses($options['clear_cache']);

        $this->io->section('Wanted courses');
        $this->io->listing(array_keys($courses));

        $fileTypesOptions = array_flip(self::OPTIONS_FILE_TYPES);

        $coursesCounter = 0;
        $coursesCount = \count($courses);
        foreach ($courses as $title => $urls) {
            ++$coursesCounter;
            $this->io->newLine(3);
            $this->io->title("Processing course: '$title' ($coursesCounter of $coursesCount)");
            $isChapterCodeDownloaded = false;
            $isChapterScriptDownloaded = false;

            if (empty($urls)) {
                $this->io->warning('No chapters to download');

                continue;
            }

            $titlePath = str_replace(self::BAD_WINDOWS_PATH_CHARS, '-', $title);
            $coursePath = "$downloadPath/$titlePath";

            if (!is_dir($coursePath) && !mkdir($coursePath) && !is_dir($coursePath)) {
                $this->io->error('Unable to create course directory');

                continue;
            }

            $chaptersCounter = 0;

            foreach ($urls as $name => $url) {
                if (preg_match("/\/activity\/[0-9]{3}$/", $url)) {
                    unset($urls[$name]);
                }
            }

            $chaptersCount = \count($urls);
            foreach ($urls as $name => $url) {
                ++$chaptersCounter;
                $this->io->newLine();
                $this->io->section("Chapter '{$this->dashesToTitle($name)}' ($chaptersCounter of $chaptersCount)");

                try {
                    $response = $this->client->get($url);
                } catch (ClientException $e) {
                    $this->io->error($e->getMessage());

                    continue;
                }

                $crawler = new Crawler($response->getBody()->getContents());
                /** @var \DOMElement $domElement */
                foreach ($crawler->filter('[aria-labelledby="downloadDropdown"] a, #captions') as $domElement) {
                    $url = null;
                    $fileName = false;
                    $fileType = null;
                    switch ($domElement->nodeName) {
                        case 'a':
                            $url = $domElement->getAttribute('href');
                        break;
                        case 'track':
                            $url = $domElement->getAttribute('src');
                        break;
                        case 'source':
                    }

                    switch ($url) {
                        case 'javascript:void(0)':
                            $this->io->warning('Not subscribed to course: '.$url);
                            $fileName = null;
                        break;
                        case (false !== strpos($url, '.vtt')):
                            $fileType = self::FILE_TYPE_SUBTITLES;
                            $fileName = in_array($fileTypesOptions[self::FILE_TYPE_SUBTITLES], $options['download_only'])
                                ? sprintf('%03d', $chaptersCounter)."-$name.vtt"
                                : null;
                        break;
                        case (false !== strpos($url, 'video')):
                            $fileType = self::FILE_TYPE_VIDEO;
                            $fileName = in_array($fileTypesOptions[self::FILE_TYPE_VIDEO], $options['download_only'])
                                ? sprintf('%03d', $chaptersCounter)."-$name.mp4"
                                : null;
                        break;
                        case (false !== strpos($url, 'script')):
                            $fileType = self::FILE_TYPE_SCRIPT;
                            if (in_array($fileTypesOptions[self::FILE_TYPE_SCRIPT], $options['download_only']) && !$isChapterScriptDownloaded) {
                                $fileName = "$titlePath.pdf";
                                $isChapterScriptDownloaded = true;
                            } else {
                                $fileName = null;
                            }
                        break;
                        case (false !== strpos($url, 'code')):
                            $fileType = self::FILE_TYPE_CODE;
                            if (in_array($fileTypesOptions[self::FILE_TYPE_CODE], $options['download_only']) && !$isChapterCodeDownloaded) {
                                $fileName = "$titlePath.zip";
                                $isChapterCodeDownloaded = true;
                            } else {
                                $fileName = null;
                            }

                        break;
                        default:
                            $this->io->warning('Unknown Link Type: '.$url);
                    }

                    if ($fileName === null) {
                        continue;
                    }

                    if (!$fileName) {
                        $this->io->warning('Unable to get download links');
                        continue;
                    }

                    $filePath = "$coursePath/$fileName";

                    $downloadOtherVideoQuality = $this->downloadOtherQuality($fileType, $filePath, $options['video_quality']);

                    // ignore already downloaded files unless user wanted to re-download that file type again
                    if (file_exists($filePath) && !in_array($fileTypesOptions[$fileType], $options['force_download'])) {
                        if (!$downloadOtherVideoQuality) {
                            $this->io->writeln("File '$fileName' was already downloaded");
                            continue;
                        }
                    }

                    if ($downloadOtherVideoQuality && $options['video_quality'] !== null) {
                        try {
                            $url = $this->getProperVideoQualityUrl($crawler, VideoQualityService::OPTIONS_VIDEO_QUALITY[$options['video_quality']]);
                        } catch (\Exception $e) {
                            $this->io->writeln($e->getMessage());
                            continue;
                        }
                    }

                    $filePath = $this->downloadFile($url, $coursePath, $fileName);
                    $this->io->newLine();

                    if ($filePath === null) {
                        continue;
                    }

                    if ($fileType === self::FILE_TYPE_SUBTITLES && ($options['convert_subtitles_to'] ?? false) === 'srt') {
                        try {
                            $formatModel = new SrtDriver();
                            $convertedFileName = (new WebvttConverterService($filePath))
                                ->convert($formatModel, $coursePath, sprintf('%03d', $chaptersCounter)."-$name");
                            $this->io->writeln("File '$fileName' converted to '$convertedFileName'");
                        } catch (\Exception $e) {
                            $this->io->writeln($e->getMessage());
                        }
                    }
                }
            }
        }

        $this->io->success('Finished');
    }

    private function downloadFile(string $url, string $coursePath, string $fileName): ?string
    {
        $io = $this->io;
        $progressBar = null;
        $filePath = "$coursePath/$fileName";

        try {
            $this->client->get($url, [
                'sink' => $filePath,
                'allow_redirects' => ['max' => self::ALLOW_MAX_REDIRECTS],
                'auth' => ['username', 'password'],
                'progress' => function ($total, $downloaded) use ($io, $fileName, &$progressBar) {
                    if ($total && $progressBar === null) {
                        $progressBar = $io->createProgressBar($total);
                        $progressBar->setFormat("<info>[%bar%]</info> $fileName");
                        $progressBar->start();
                    }

                    if ($progressBar !== null) {
                        if ($total === $downloaded) {
                            $progressBar->finish();

                            return;
                        }

                        $progressBar->setProgress($downloaded);
                    }
                },
            ]);
        } catch (\Exception $e) {
            $this->io->warning($e->getMessage());

            unlink($filePath);
            $filePath = null;
        }

        return $filePath;
    }

    /**
     * @param bool $clearCache
     * @return array
     */
    private function getCourses(bool $clearCache = false): array
    {
        $courses = $this->fetchCourses($clearCache);
        $whitelist = $this->configs['COURSES'];

        if (!empty($whitelist)) {
            foreach ($courses as $title => $lessons) {
                if (!in_array($title, $whitelist, true)) {
                    unset($courses[$title]);
                }
            }
        }

        return $courses;
    }

    /**
     * @param bool $clearCache
     * @return array
     */
    private function fetchCourses(bool $clearCache): array
    {
        $this->io->title('Fetching courses...');
        $blueprintFile = __DIR__.'/../blueprint.json';
        if ($clearCache === false && file_exists($blueprintFile)) {
            return json_decode(file_get_contents($blueprintFile), true);
        }

        $response = $this->client->get('/courses/filtering');

        $courses = [];
        $crawler = new Crawler($response->getBody()->getContents());
        $elements = $crawler->filter('.js-course-item .d-flex > a');

        $progressBar = $this->io->createProgressBar($elements->count());
        $progressBar->setFormat('<info>[%bar%]</info> %message%');
        $progressBar->setMessage('Downloading courses list');
        $progressBar->start();

        /** @var \DOMElement $itemElement */
        foreach ($elements as $itemElement) {
            $titleElement = new Crawler($itemElement);
            $courseTitle = $titleElement->filter('h3')->text();
            $courseUri = $itemElement->getAttribute('href');

            $progressBar->setMessage($courseTitle);
            $progressBar->advance();

            $chapters = [];
            $response = $this->client->get($courseUri);
            $crawler = new Crawler($response->getBody()->getContents());

            $chapterLinks = $crawler->filter('ul.chapter-list > li > a');

            if ($chapterLinks->count() === 0) {
                continue;
            }

            /** @var \DOMElement $a */
            foreach ($chapterLinks as $a) {
                if ($a->getAttribute('href') === '#') {
                    continue;
                }

                $url = explode('#', $a->getAttribute('href'))[0];
                $urlParts = explode('/', $url);

                $chapters[end($urlParts)] = $url;
            }

            $courses[$courseTitle] = $chapters;
        }

        $progressBar->finish();

        if (!file_put_contents($blueprintFile, json_encode($courses, JSON_PRETTY_PRINT))) {
            $this->io->warning('Unable to save course blueprint');
        }

        return $courses;
    }

    /**
     * @return void
     */
    private function login(): void
    {
        $response = $this->client->get('login');

        $csrfToken = '';
        $crawler = new Crawler($response->getBody()->getContents());
        /** @var \DOMElement $input */
        foreach ($crawler->filter('input') as $input) {
            if ($input->getAttribute('name') === '_csrf_token') {
                $csrfToken = $input->getAttribute('value');
            }
        }

        if (empty($csrfToken)) {
            throw new \RuntimeException('Unable to authenticate');
        }

        $currentUrl = null;
        $this->client->post('login', [
            'form_params' => [
                'email' => $this->configs['EMAIL'],
                'password' => $this->configs['PASSWORD'],
                '_csrf_token' => $csrfToken,
            ],
            'on_stats' => function (TransferStats $stats) use (&$currentUrl) {
                $currentUrl = $stats->getEffectiveUri();
            },
        ]);

        if ((string) $currentUrl !== 'https://symfonycasts.com/') {
            throw new \RuntimeException('Authorization failed.');
        }
    }

    /**
     * @param string $text
     * @param bool $capitalizeFirstCharacter
     *
     * @return string
     */
    private function dashesToTitle(string $text, bool $capitalizeFirstCharacter = true): string
    {
        $str = str_replace('-', ' ', ucwords($text, '-'));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }

    // if user wants to download video file with specified quality
    private function downloadOtherQuality(?int $fileType, string $filePath, ?string $videoQualityOption): bool
    {
        // ignore non-video files
        if ($fileType !== self::FILE_TYPE_VIDEO) {
            return false;
        }

        // if file does not exist, download it anyway
        if (!file_exists($filePath)) {
            return true;
        }

        if ($videoQualityOption !== null) {
            $videoQualityService = new VideoQualityService();

            return !$videoQualityService->isRequiredQuality(
                $filePath,
                VideoQualityService::OPTIONS_VIDEO_QUALITY[$videoQualityOption],
            );
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    private function getProperVideoQualityUrl(Crawler $crawler, string $videoQuality): string
    {
        switch ($videoQuality) {
            case VideoQualityService::VIDEO_QUALITY_SD:
                $selector = '#js-video-player source[title="SD"]';
            break;
            case VideoQualityService::VIDEO_QUALITY_HD:
                $selector = '#js-video-player source[title="HD"]';
            break;
            default:
                throw new \Exception("Video not found for provided quality: $videoQuality");
        }

        return $crawler->filter($selector)->first()->getNode(0)->getAttribute('src');
    }
}
