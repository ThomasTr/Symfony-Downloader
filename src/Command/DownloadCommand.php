<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use YoutubeDl\Options;
use YoutubeDl\YoutubeDl;

class DownloadCommand extends Command
{
    protected static $defaultName = 'download';
    protected static $defaultDescription = 'yt-dl test downloader';

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('url', InputArgument::REQUIRED, 'The Url to download')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = $input->getArgument('url');

        $yt = new YoutubeDl();

        $yt->onProgress(static function (string $progressTarget, string $percentage, string $size, string $speed, string $eta, ?string $totalTime): void {
            echo "Download file: $progressTarget; Percentage: $percentage; Size: $size";

            if ($speed) {
                echo "; Speed: $speed";
            }
            if ($eta) {
                echo "; ETA: $eta";
            }
            if ($totalTime !== null) {
                echo "; Downloaded in: $totalTime";
            }
            echo "\n";
        });

        $collection = $yt->download(
            Options::create()
                   ->downloadPath('~/Downloads/ytdl')
                   ->url($url)
                   ->format('mp4')
        );


        $io->success('Done!');

        return Command::SUCCESS;
    }
}
