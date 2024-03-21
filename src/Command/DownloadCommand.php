<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YoutubeDl\Options;
use YoutubeDl\YoutubeDl;

#[AsCommand(
    name: 'app:download',
    description: 'yt-dl test downloader'
)]
final class DownloadCommand extends Command
{
    public function __construct(public readonly ParameterBagInterface $parameters, ?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::REQUIRED, 'The Url to download');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = $input->getArgument('url');

        $yt = new YoutubeDl();

        $yt->onProgress(static function (string $progressTarget, string $percentage, string $size, ?string $speed, ?string $eta, ?string $totalTime): void {
            echo "Download file: $progressTarget; Percentage: $percentage; Size: $size";


            if (null !== $speed)
            {
                echo "; Speed: $speed";
            }

            if (null !== $eta)
            {
                echo "; ETA: $eta";
            }

            if (null !== $totalTime)
            {
                echo "; Downloaded in: $totalTime";
            }

            echo "\n";
        });

        $yt->debug(function ($type, $buffer) {
            echo "[$type]: $buffer";
        });

        $collection = $yt->download(
            Options::create()
                   ->downloadPath($this->parameters->get('downloadPath'))
                   ->url($url)
                   ->format('mp4')
        );

        foreach ($collection->getVideos() as $video)
        {
            if ($video->getError() !== null)
            {
                $io->error("Error downloading video: {$video->getError()}.");
            }
            else
            {
                $io->success("Successfully downloaded {$video->getTitle()}");
            }
        }

        return Command::SUCCESS;
    }
}
