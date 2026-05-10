<?php declare(strict_types=1);

namespace App\Command;

use App\YoutubeDl\OutputParser;
use App\YoutubeDl\YoutubeDlFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YoutubeDl\Options;

#[AsCommand(
    name: 'app:download',
    description: 'yt-dl test downloader'
)]
final class DownloadCommand extends Command
{
    public function __construct(
        public readonly ParameterBagInterface $parameters,
        public readonly YoutubeDlFactory      $ytFactory,
        public readonly OutputParser          $parser,
        ?string                               $name = null
    )
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

        $yt = $this->ytFactory->create();
        $yt->setBinPath($this->parameters->get('ytDlpPath'));

        $yt->debug(function ($type, $buffer) use ($io, $url, &$title): void {
            if ('out' !== $type)
            {
                $io->writeln("Type: $type: $buffer");

                return;
            }

            $detected = $this->parser->parseTitle($buffer);

            if (null !== $detected)
            {
                $title = $detected;
                $io->writeln("TITLE: $title");

                return;
            }

            $progresses = $this->parser->parseProgress($buffer);

            if (count($progresses) > 0)
            {
                foreach ($progresses as $progress)
                {
                    $io->writeln(sprintf(
                        '[%s] %s of %s @ %s ETA %s (id=%s)',
                        $title ?? '?',
                        $progress['percentage'].'%',
                        $progress['size'],
                        $progress['speed']     ?? '-',
                        $progress['eta']       ?? '-',
                        hash('md5', $url),
                    ));
                }

                return;
            }

            $io->writeln("Type: OOUUTT: $buffer");
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
