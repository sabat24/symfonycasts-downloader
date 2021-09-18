<?php

require __DIR__.'/vendor/autoload.php';

use App\Service\DownloaderService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @param InputInterface $input
 * @param OutputInterface $output
 *
 * @return void
 */
function downloadCommand(InputInterface $input, OutputInterface $output)
{
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
    $downloader->download();
}

try {
    (new SingleCommandApplication())
        ->setName('SymfonyCasts downloader')
        ->setCode('downloadCommand')
        ->run()
    ;
} catch (Exception $e) {
}
