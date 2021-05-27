<?php declare(strict_types=1);

namespace App\Controller;

use App\Message\Download;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{

    #[Route('/api/download', name: 'api_download')]
    public function download(Request $request, MessageBusInterface $bus)
    {
        $url = urldecode($request->get('url'));

        $download = new Download($url);
        $bus->dispatch($download);

        return $this->json(['Download URL received', $url]);
    }
}
