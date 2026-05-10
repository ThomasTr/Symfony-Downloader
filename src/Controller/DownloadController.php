<?php declare(strict_types=1);

namespace App\Controller;

use App\EventListener\DeferredDownloadDispatcher;
use App\Service\JobStatusStore;
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
        ValidatorInterface          $validator,
        JobStatusStore              $jobs
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

        $jobId = bin2hex(random_bytes(8));
        $jobs->markPending($jobId, $url);
        $deferred->queue($url, $jobId);

        return $this->json(
            ['accepted' => true, 'job_id' => $jobId, 'url' => $url],
            Response::HTTP_ACCEPTED
        );
    }

    #[Route('/api/download/status/{jobId}', name: 'api_download_status', requirements: ['jobId' => '[a-zA-Z0-9_-]+'])]
    public function status(string $jobId, JobStatusStore $jobs): JsonResponse
    {
        $data = $jobs->read($jobId);
        if ($data === null) {
            return $this->json(['error' => 'Job not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($data);
    }
}
