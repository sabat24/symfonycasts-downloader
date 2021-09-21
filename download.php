<?php

require __DIR__.'/vendor/autoload.php';

use App\Service\DownloaderService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @param InputInterface $input
 * @param OutputInterface $output
 *
 * @return void
 */
function downloadCommand(InputInterface $input, OutputInterface $output)
{
    $optionsResolver = new OptionsResolver();

    $options = [
        'convert_subtitles_to' => $input->getOption('convert-subtitles'),
        'force_download' => $input->getOption('force') ? array_map('trim', explode(',', $input->getOption('force'))) : [],
        'download_only' => $input->getOption('download') ? array_map('trim', explode(',', $input->getOption('download'))) : array_keys(DownloaderService::OPTIONS_FILE_TYPES),
    ];

    $optionsResolver->setDefined(['force_download', 'convert_subtitles_to', 'download_only']);
    $optionsResolver->setAllowedValues('convert_subtitles_to', [null, 'srt']);
    $optionsResolver->setAllowedTypes('force_download', 'string[]');
    $optionsResolver->setAllowedValues('force_download', function (array $items) {
        return !array_diff($items, array_keys(DownloaderService::OPTIONS_FILE_TYPES));
    });
    $optionsResolver->setAllowedTypes('download_only', 'string[]');
    $optionsResolver->setAllowedValues('download_only', function (array $items) {
        return !array_diff($items, array_keys(DownloaderService::OPTIONS_FILE_TYPES));
    });

    $optionsResolver->resolve($options);

    $io = new SymfonyStyle($input, $output);

    if (!file_exists(__DIR__.'/src/local.ini')) {
        $io->error("Hint: Copy run 'cp application.init local.ini' and provide required credentials");

        return;
    }

    if (!$configs = parse_ini_file(__DIR__.'/src/local.ini')) {
        $io->error('Hint: try to wrap values inside local.ini with either double or single quotes');

        return;
    }

    $downloader = new DownloaderService($io, $configs);
    $downloader->download($options);
}

try {
    (new SingleCommandApplication())
        ->setName('SymfonyCasts downloader')
        ->addOption('convert-subtitles', 'c', InputOption::VALUE_OPTIONAL, 'Convert subtitles to provided format. Allowed: srt')
        ->addOption('force', 'f', InputOption::VALUE_OPTIONAL, 'Download resources even if file exists locally. Allowed: '.implode(', ', array_keys(DownloaderService::OPTIONS_FILE_TYPES)))
        ->addOption('download', 'd', InputOption::VALUE_OPTIONAL, 'Download only provided resources. Allowed: '.implode(', ', array_keys(DownloaderService::OPTIONS_FILE_TYPES)))
        ->setCode('downloadCommand')
        ->run()
    ;
} catch (Exception $e) {
}
