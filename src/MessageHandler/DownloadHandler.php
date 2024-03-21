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
            $centrifugo = $this->centrifugo;

            $logger = $this->logger;
            $logger->debug('start handling download message');

            $yt         = new YoutubeDl();
            $yt->setBinPath($this->parameters->get('ytDlpBinPath'));
            $yt->onProgress(static function (
                ?string $progressTarget,
                ?string $percentage,
                ?string $size,
                ?string $speed,
                ?string $eta,
                ?string $totalTime
            ) use ($centrifugo, $logger, $message) {
                $data = [
                    'id' => hash('md5', $message->getUrl()),
                    'title' => $progressTarget,
                    'percentage' => str_replace('%', '', $percentage),
                    'size' => $size,
                    'speed' => $speed,
                    'eta' => $eta,
                    'alertMessage' => null,
                ];

                $centrifugo->publish($data, 'downloads');
                $logger->debug('centrifugo publish', $data);
            });

            $collection = $yt->download(
                Options::create()
                       ->downloadPath($this->parameters->get('downloadPath'))
                       ->url($message->getUrl())
                       ->format('mp4')
            );

            foreach ($collection->getVideos() as $video)
            {
                if ($video->getError() !== null)
                {
                    $update = [
                        'alertMessage' => "Error downloading video: {$video->getError()}.",
                    ];
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

                $centrifugo->publish($update, 'downloads');
                $logger->debug('centrifugo publish', $update);
            }
        }
        catch (\Error $error)
        {
            $data = [
                'id' => hash('md5', $message->getUrl()),
                'alertMessage' => $error->getMessage(),
                'alertClass' => 'alert alert-danger',
            ];

            $centrifugo->publish($data, 'downloads');
            $logger->debug('centrifugo publish', $data);
        }
    }
}
