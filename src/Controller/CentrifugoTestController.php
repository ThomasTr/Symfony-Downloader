<?php declare(strict_types=1);

namespace App\Controller;

use Fresh\CentrifugoBundle\Service\CentrifugoInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CentrifugoTestController extends AbstractController
{
    #[Route('/centrifugo-send', name: 'centrifugo_test')]
    public function __invoke(CentrifugoInterface $centrifugo): JsonResponse
    {
        $centrifugo->publish( [
            'alertMessage' => "Just a random error message",
        ], 'downloads');

        return $this->json(['ok']);
    }
}
