<?php declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\Download;
use App\Service\JobStatusStore;
use App\YoutubeDl\Options;
use App\YoutubeDl\OutputParser;
use App\YoutubeDl\YoutubeDlFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DownloadHandler
{
    public function __construct(
        public readonly HubInterface          $hub,
        public readonly LoggerInterface       $logger,
        public readonly ParameterBagInterface $parameters,
        public readonly YoutubeDlFactory      $ytFactory,
        public readonly OutputParser          $parser,
        public readonly JobStatusStore        $jobs
    )
    {
    }

    public function __invoke(Download $message): void
    {
        $successTitle = null;
        $errors = [];

        try
        {
            $yt = $this->ytFactory->create();
            $yt->setBinPath($this->parameters->get('ytDlpPath'));

            $title = null;

            $yt->debug(function ($type, $buffer) use ($message, &$title): void {
                if ('out' !== $type)
                {
                    $this->publish(['alertMessage' => "$type: $buffer"]);

                    return;
                }

                $detected = $this->parser->parseTitle($buffer);

                if (null !== $detected)
                {
                    $title = $detected;

                    return;
                }

                $progresses = $this->parser->parseProgress($buffer);

                if (count($progresses) > 0)
                {
                    foreach ($progresses as $progress)
                    {
                        $this->publish([
                            'id'           => hash('md5', $message->getUrl()),
                            'title'        => $title,
                            'percentage'   => $progress['percentage'],
                            'size'         => $progress['size'],
                            'speed'        => $progress['speed'],
                            'eta'          => $progress['eta'],
                            'totalTime'    => $progress['totalTime'],
                            'alertMessage' => null,
                        ]);
                    }

                    return;
                }

                $this->publish(['alertMessage' => "nomatch: $buffer"]);
            });

            $options = Options::create()
                              ->forceGenericExtractor(true)
                              ->presetAlias('mp4')
                              ->downloadPath($this->parameters->get('downloadPath'))
                              ->ffmpegLocation($this->parameters->get('ffmpegPath'))
                              ->url($message->getUrl());

            $collection = $yt->download($options);

            foreach ($collection->getVideos() as $video)
            {
                if (null !== $video->getError())
                {
                    $errors[] = $video->getError();
                    $update = ['alertMessage' => "Error downloading video: {$video->getError()}."];
                }
                else
                {
                    $successTitle = $video->getTitle();
                    $update = [
                        'id'           => hash('md5', $message->getUrl()),
                        'title'        => $video->getTitle(),
                        'percentage'   => 100,
                        'size'         => 0,
                        'speed'        => 0,
                        'eta'          => 0,
                        'totalTime'    => 0,
                        'alertMessage' => null,
                    ];
                }

                $this->publish($update);
            }

            if ($successTitle !== null) {
                $this->jobs->markSuccess($message->getJobId(), $successTitle);
            } else {
                $this->jobs->markFailed(
                    $message->getJobId(),
                    $errors === [] ? 'No videos produced' : implode('; ', $errors)
                );
            }
        }
        catch (\Exception $error)
        {
            $this->publish([
                'id'           => hash('md5', $message->getUrl()),
                'alertMessage' => $error->getMessage(),
                'alertClass'   => 'alert alert-danger',
            ]);
            $this->jobs->markFailed($message->getJobId(), $error->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function publish(array $data): void
    {
        $update = new Update(
            'downloads',
            json_encode($data),
        );

        $this->hub->publish($update);
        $this->logger->debug('published', $data);
    }
}
