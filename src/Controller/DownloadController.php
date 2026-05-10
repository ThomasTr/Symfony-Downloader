<?php declare(strict_types=1);

namespace App\Controller;

use App\EventListener\DeferredDownloadDispatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DownloadController extends AbstractController
{
    #[Route('/', name: 'download')]
    public function index(): Response
    {
        return $this->render('download/index.html.twig');
    }

    #[Route('/api/download', name: 'api_download')]
    public function download(
        Request                     $request,
        DeferredDownloadDispatcher  $deferred,
        ValidatorInterface          $validator
    ): JsonResponse
    {
        $url = $request->query->get('url');

        if (!is_string($url) || '' === $url)
        {
            return $this->json(
                ['error' => 'Missing url parameter'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $violations = $validator->validate($url, [new NotBlank(), new Url()]);

        if (count($violations) > 0)
        {
            $messages = [];

            foreach ($violations as $violation)
            {
                $messages[] = $violation->getMessage();
            }

            return $this->json(
                ['error' => 'Invalid url parameter', 'violations' => $messages],
                Response::HTTP_BAD_REQUEST
            );
        }

        $deferred->queue($url);

        return $this->json(
            ['accepted' => true, 'url' => $url],
            Response::HTTP_ACCEPTED
        );
    }
}
