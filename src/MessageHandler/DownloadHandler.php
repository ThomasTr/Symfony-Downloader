<?php declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\Download;
use Fresh\CentrifugoBundle\Service\CentrifugoInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YoutubeDl\Options;
use YoutubeDl\YoutubeDl;

#[AsMessageHandler]
final class DownloadHandler
{
    public function __construct(
        public readonly CentrifugoInterface $centrifugo,
        public readonly LoggerInterface $logger,
        public readonly ParameterBagInterface $parameters
    ) { }

    public function __invoke(Download $message)
    {
        try
        {
            $yt = new YoutubeDl();
            $yt->setBinPath($this->parameters->get('ytDlpBinPath'));
            $yt->debug(function ($type, $buffer) use ($message, &$title)
            {
                if('out' === $type)
                {
                    if (preg_match('/\[(download|ffmpeg|ExtractAudio)] Destination: (?<file>.+)/', $buffer, $match) === 1 ||
                        preg_match('/\[download] (?<file>.+) has already been downloaded/', $buffer, $match) === 1)
                    {
                        $title = basename($match['file']);
                    }
                    elseif(preg_match_all('#\[download\]\s+(?<percentage>\d+(?:\.\d+)?%)\s+of\s+~?\s?(?<size>[~]?\d+(?:\.\d+)?(?:K|M|G)iB)(?:\s+at\s+(?<speed>(\d+(?:\.\d+)?(?:K|M|G)iB/s)|Unknown speed))?(?:\s+ETA\s+(?<eta>([\d:]{2,8}|Unknown ETA)))?(\s+in\s+(?<totalTime>[\d:]{2,8}))?#i', $buffer, $matches, PREG_SET_ORDER) !== false)
                    {
                        if (count($matches) > 0)
                        {
                            foreach ($matches as $progressMatch)
                            {
                                $this->publish([
                                    'id' => hash('md5', $message->getUrl()),
                                    'title' => $title,
                                    'percentage' => str_replace('%', '', $progressMatch['percentage']),
                                    'size' => $progressMatch['size'],
                                    'speed' => $progressMatch['speed'] ?? null,
                                    'eta' => $progressMatch['eta'] ?? null,
                                    'totalTime' => $progressMatch['totalTime'] ?? null,
                                    'alertMessage' => null,
                                ]);
                            }
                        }
                    }
                    else
                    {
                        $this->publish(['alertMessage' => "nomatch: $buffer"]);
                    }
                }
                else
                {
                    $this->publish(['alertMessage' => "$type: $buffer"]);
                }
            });

            $collection = $yt->download(
                Options::create()
                       ->downloadPath($this->parameters->get('downloadPath'))
                       ->url($message->getUrl())
                       ->format('mp4')
            );

            foreach ($collection->getVideos() as $video)
            {
                if (null !== $video->getError())
                {
                    $update = ['alertMessage' => "Error downloading video: {$video->getError()}."];
                }
                else
                {
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

                $this->centrifugo->publish($update, 'downloads');
                $this->logger->debug('centrifugo publish', $update);
            }
        }
        catch (\Exception $error)
        {
            $data = [
                'id' => hash('md5', $message->getUrl()),
                'alertMessage' => $error->getMessage(),
                'alertClass' => 'alert alert-danger',
            ];

            $this->centrifugo->publish($data, 'downloads');
            $this->logger->error('centrifugo publish', $data);
        }
    }

    private function publish(array $data): void
    {
        $this->centrifugo->publish($data, 'downloads');
        $this->logger->debug('centrifugo publish', $data);
    }
}
