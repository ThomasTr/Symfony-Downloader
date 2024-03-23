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
    public const PROGRESS_PATTERN = '#\[download\]\s+(?<percentage>\d+(?:\.\d+)?%)\s+of\s+~?\s?(?<size>[~]?\d+(?:\.\d+)?(?:K|M|G)iB)(?:\s+at\s+(?<speed>(\d+(?:\.\d+)?(?:K|M|G)iB/s)|Unknown speed))?(?:\s+ETA\s+(?<eta>([\d:]{2,8}|Unknown ETA)))?(\s+in\s+(?<totalTime>[\d:]{2,8}))?#i';

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
        $io    = new SymfonyStyle($input, $output);
        $url   = $input->getArgument('url');
        $title = null;

        $yt = new YoutubeDl();
        $yt->setBinPath($this->parameters->get('ytDlpPath'));
        $yt->debug(function ($type, $buffer) use ($url, &$title) {
            if ('out' === $type)
            {
                if (preg_match('/\[(download|ffmpeg|ExtractAudio)] Destination: (?<file>.+)/', $buffer, $match) === 1 ||
                    preg_match('/\[download] (?<file>.+) has already been downloaded/', $buffer, $match) === 1)
                {
                    $title = basename($match['file']);
                    echo "TITLE: $title";
                }
                elseif (preg_match_all(static::PROGRESS_PATTERN, $buffer, $matches, PREG_SET_ORDER) !== false)
                {
                    if (count($matches) > 0)
                    {
                        foreach ($matches as $progressMatch)
                        {
                            var_dump([
                                'id'           => hash('md5', $url),
                                'title'        => $title,
                                'percentage'   => str_replace('%', '', $progressMatch['percentage']),
                                'size'         => $progressMatch['size'],
                                'speed'        => $progressMatch['speed'] ?? null,
                                'eta'          => $progressMatch['eta'] ?? null,
                                'totalTime'    => $progressMatch['totalTime'] ?? null,
                                'alertMessage' => null,
                            ]);
                        }
                        var_dump($matches);
                    }
                }
                else
                {
                    echo "Type: OOUUTT: $buffer";
                }
            }
            else
            {
                echo "Type: $type: $buffer";
            }
        });

        $collection = $yt->download(
            Options::create()
                   ->downloadPath($this->parameters->get('downloadPath'))
                   ->ffmpegLocation($this->parameters->get('ffmpegPath'))
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
