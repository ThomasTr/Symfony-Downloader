<?php declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\Download;
use Fresh\CentrifugoBundle\Service\CentrifugoInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use YoutubeDl\Options;
use YoutubeDl\YoutubeDl;

class DownloadHandler implements MessageHandlerInterface
{
    public function __construct(public CentrifugoInterface $centrifugo, public ParameterBagInterface $parameters)
    {
    }

    public function __invoke(Download $message)
    {
        try
        {
            $yt         = new YoutubeDl();
            $centrifugo = $this->centrifugo;

            $yt->onProgress(static function (
                ?string $progressTarget,
                ?string $percentage,
                ?string $size,
                ?string $speed,
                ?string $eta,
                ?string $totalTime
            ) use ($centrifugo, $message) {
                $centrifugo->publish(
                    [
                        'id'           => hash('md5', $message->getUrl()),
                        'title'        => $progressTarget,
                        'percentage'   => str_replace('%', '', $percentage),
                        'size'         => $size,
                        'speed'        => $speed,
                        'eta'          => $eta,
                        'alertMessage' => null,
                    ],
                    'downloads'
                );
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
            }
        }
        catch (\Error $error)
        {
            $centrifugo->publish(
                [
                    'id'           => hash('md5', $message->getUrl()),
                    'alertMessage' => $error->getMessage(),
                    'alertClass'   => 'alert alert-danger',
                ],
                'downloads'
            );
        }
    }
}