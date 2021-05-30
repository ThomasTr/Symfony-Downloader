<?php

namespace App\Controller;

use App\Message\Download;
use Fresh\CentrifugoBundle\Service\Credentials\CredentialsGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class DownloadController extends AbstractController
{
    #[Route('/', name: 'download')]
    public function index(CredentialsGenerator $credentialsGenerator): Response
    {
        return $this->render('download/index.html.twig', [
            'controller_name' => 'DownloadController',
            'token'           => $credentialsGenerator->generateJwtTokenForAnonymous(),
        ]);
    }

    #[Route('/api/download', name: 'api_download')]
    public function download(Request $request, MessageBusInterface $bus)
    {
        $url = urldecode($request->get('url'));

        $download = new Download($url);
        $bus->dispatch($download);

        return $this->json(['Download URL received', $url]);
    }
}
