<?php
// src/Controller/LinkController.php
namespace App\Controller;
use App\Entity\Link;
use App\Repository\LinkRepository;
use App\Service\SlugGeneratorService;
use App\DTO\CreateLinkRequest;
use App\DTO\UpdateLinkRequest;
use App\DTO\LinkResponse;
use App\DTO\ErrorResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolation;
class LinkController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private SlugGeneratorService $slugGenerator
    ) {}
    /**
     * Créer un nouveau lien raccourci
     * POST /api/links
     */
    #[Route('/api/links', name: 'api_links_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            // 🎯 Serializer : JSON → CreateLinkRequest
            $createRequest = $this->serializer->deserialize(
                $request->getContent(),
                CreateLinkRequest::class,
                'json'
            );
            // 🎯 Validation automatique
            $errors = $this->validator->validate($createRequest);
            if (count($errors) > 0) {
                $validationErrors = [];
                /** @var ConstraintViolation $error */
                foreach ($errors as $error) {
                    $validationErrors[] = [
                        'property' => $error->getPropertyPath(),
                        'message' => $error->getMessage(),
                        'invalidValue' => $error->getInvalidValue()
                    ];
                }
                $errorResponse = new ErrorResponse(
                    'Données de requête invalides',
                    $validationErrors,
                    'VALIDATION_ERROR'
                );

                $json = $this->serializer->serialize($errorResponse, 'json');
                return new JsonResponse($json, Response::HTTP_BAD_REQUEST, [], true);
            }
            // Générer le slug unique
            $slug =
                $this->slugGenerator->generateUniqueSlug($createRequest->customSlug);
            // Créer l'entité Link
            $link = new Link();
            $link->setUrl($createRequest->url);
            $link->setSlug($slug);
            $link->setDescription($createRequest->description);
            // Validation de l'entité
            $entityErrors = $this->validator->validate($link);
            if (count($entityErrors) > 0) {
                $validationErrors = [];
                foreach ($entityErrors as $error) {
                    $validationErrors[] = [
                        'property' => $error->getPropertyPath(),
                        'message' => $error->getMessage(),
                        'invalidValue' => $error->getInvalidValue()
                    ];
                }
                $errorResponse = new ErrorResponse(
                    'Erreur de validation de l\'entité',
                    $validationErrors,
                    'ENTITY_VALIDATION_ERROR'
                );

                $json = $this->serializer->serialize($errorResponse, 'json');
                return new JsonResponse($json, Response::HTTP_BAD_REQUEST, [], true);
            }
            // Sauvegarder en base
            $this->entityManager->persist($link);
            $this->entityManager->flush();
            // Créer la réponse
            $shortUrl = $request->getSchemeAndHttpHost() . '/' . $link->getSlug();
            $response = new LinkResponse(
                $link->getId(),
                $link->getUrl(),
                $link->getSlug(),
                $shortUrl,
                $link->getDescription(),
                $link->getClickCount(),
                $link->getCreatedAt()
            );
            // 🎯 Serializer : Object → JSON
            $json = $this->serializer->serialize($response, 'json');
            return new JsonResponse($json, Response::HTTP_CREATED, [], true);
        } catch (\Symfony\Component\Serializer\Exception\NotEncodableValueException $e)
        {
            $errorResponse = new ErrorResponse('JSON invalide: ' . $e->getMessage(),
                null, 'JSON_ERROR');
            $json = $this->serializer->serialize($errorResponse, 'json');
            return new JsonResponse($json, Response::HTTP_BAD_REQUEST, [], true);

        } catch (\Exception $e) {
            $errorResponse = new ErrorResponse('Erreur serveur: ' . $e->getMessage(),
                null, 'SERVER_ERROR');
            $json = $this->serializer->serialize($errorResponse, 'json');
            return new JsonResponse($json, Response::HTTP_INTERNAL_SERVER_ERROR, [],
                true);
        }
    }
    /**
     * Lister tous les liens
     * GET /api/links
     */
    #[Route('/api/links', name: 'api_links_list', methods: ['GET'])]
    public function list(Request $request, LinkRepository $linkRepository): JsonResponse
    {
        try {
            // Paramètres de pagination optionnels
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
            $offset = ($page - 1) * $limit;
            // Récupérer les liens avec pagination
            $links = $linkRepository->findBy([], ['createdAt' => 'DESC'], $limit,
                $offset);
            $total = $linkRepository->count([]);
            // Transformer en réponses
            $linkResponses = [];
            foreach ($links as $link) {
                $shortUrl = $request->getSchemeAndHttpHost() . '/' . $link->getSlug();
                $linkResponses[] = new LinkResponse(
                    $link->getId(),
                    $link->getUrl(),
                    $link->getSlug(),
                    $shortUrl,
                    $link->getDescription(),
                    $link->getClickCount(),
                    $link->getCreatedAt()
                );
            }
            $response = [
                'data' => $linkResponses,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ];
            $json = $this->serializer->serialize($response, 'json');
            return new JsonResponse($json, Response::HTTP_OK, [], true);
        } catch (\Exception $e) {
            $errorResponse = new ErrorResponse('Erreur lors de la récupération des
liens: ' . $e->getMessage());
            $json = $this->serializer->serialize($errorResponse, 'json');
            return new JsonResponse($json, Response::HTTP_INTERNAL_SERVER_ERROR, [],
                true);
        }
    }
    /**
     * Afficher un lien par ID OU rediriger par slug
     * GET /api/links/{id} OU GET /{slug}
     */
    #[Route('/{identifier}', name: 'link_show_or_redirect', methods: ['GET'], priority: -1)]
    public function showOrRedirect(Request $request, LinkRepository $linkRepository,
                                   string $identifier): Response
    {
        $isNumeric = is_numeric($identifier);

        // Rechercher le lien
        if ($isNumeric) {
            // C'est un ID numérique -> Affichage JSON
            $link = $linkRepository->findOneBy(['id' => (int) $identifier]);
            $isRedirect = false;
        } else {
            // C'est un slug -> Redirection
            $link = $linkRepository->findOneBy(['slug' => $identifier]);
            $isRedirect = true;
        }
        // Lien introuvable
        if (!$link) {
            if ($isRedirect) {
                // Pour un slug introuvable, retourner une 404 HTML
                throw $this->createNotFoundException("Lien raccourci '$identifier'
introuvable");
            } else {
                // Pour un ID introuvable, retourner une erreur JSON
                $errorResponse = new ErrorResponse(
                    "Lien avec l'ID $identifier introuvable",
                    null,
                    'NOT_FOUND'
                );
                $json = $this->serializer->serialize($errorResponse, 'json');
                return new JsonResponse($json, Response::HTTP_NOT_FOUND, [], true);
            }
        }
        // Lien trouvé
        if ($isRedirect) {
            // Incrémenter le compteur de clics
            $link->incrementClickCount();
            $this->entityManager->flush();
            // Redirection 301 vers l'URL originale
            return new Response(
                '',
                Response::HTTP_MOVED_PERMANENTLY,
                ['Location' => $link->getUrl()]
            );
        } else {
            // Retourner les informations du lien en JSON
            $shortUrl = $request->getSchemeAndHttpHost() . '/' . $link->getSlug();
            $response = new LinkResponse(
                $link->getId(),
                $link->getUrl(),
                $link->getSlug(),
                $shortUrl,
                $link->getDescription(),
                $link->getClickCount(),
                $link->getCreatedAt(),
                true,
                'Lien récupéré avec succès'
            );
            $json = $this->serializer->serialize($response, 'json');
            return new JsonResponse($json, Response::HTTP_OK, [], true);
        }
    }
    /**
     * Mettre à jour la description d'un lien
     * PATCH /api/links/{id}
     */
    #[Route('/api/links/{id}', name: 'api_links_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(Request $request, int $id, LinkRepository $linkRepository):
    JsonResponse
    {
        try {
            $link = $linkRepository->find($id);

            if (!$link) {
                $errorResponse = new ErrorResponse(
                    "Lien avec l'ID $id introuvable",
                    null,
                    'NOT_FOUND'
                );
                $json = $this->serializer->serialize($errorResponse, 'json');
                return new JsonResponse($json, Response::HTTP_NOT_FOUND, [], true);
            }
            // 🎯 Serializer : JSON → UpdateLinkRequest
            $updateRequest = $this->serializer->deserialize(
                $request->getContent(),
                UpdateLinkRequest::class,
                'json'
            );
            // 🎯 Validation automatique
            $errors = $this->validator->validate($updateRequest);
            if (count($errors) > 0) {
                $validationErrors = [];
                foreach ($errors as $error) {
                    $validationErrors[] = [
                        'property' => $error->getPropertyPath(),
                        'message' => $error->getMessage(),
                        'invalidValue' => $error->getInvalidValue()
                    ];
                }
                $errorResponse = new ErrorResponse(
                    'Données de requête invalides',
                    $validationErrors,
                    'VALIDATION_ERROR'
                );

                $json = $this->serializer->serialize($errorResponse, 'json');
                return new JsonResponse($json, Response::HTTP_BAD_REQUEST, [], true);
            }
 // Mettre à jour les champs modifiables
            if ($updateRequest->description !== null) {
                $link->setDescription($updateRequest->description);
            }
            // Validation de l'entité modifiée
            $entityErrors = $this->validator->validate($link);
            if (count($entityErrors) > 0) {
                $validationErrors = [];
                foreach ($entityErrors as $error) {
                    $validationErrors[] = [
                        'property' => $error->getPropertyPath(),
                        'message' => $error->getMessage(),
                        'invalidValue' => $error->getInvalidValue()
                    ];
                }
                $errorResponse = new ErrorResponse(
                    'Erreur de validation de l\'entité',
                    $validationErrors,
                    'ENTITY_VALIDATION_ERROR'
                );

                $json = $this->serializer->serialize($errorResponse, 'json');
                return new JsonResponse($json, Response::HTTP_BAD_REQUEST, [], true);
            }
            // Sauvegarder les modifications
            $this->entityManager->flush();
            // Créer la réponse
            $shortUrl = $request->getSchemeAndHttpHost() . '/' . $link->getSlug();
            $response = new LinkResponse(
                $link->getId(),
                $link->getUrl(),
                $link->getSlug(),
                $shortUrl,
                $link->getDescription(),
                $link->getClickCount(),
                $link->getCreatedAt()
            );
            $json = $this->serializer->serialize($response, 'json');
            return new JsonResponse($json, Response::HTTP_OK, [], true);
        } catch (\Symfony\Component\Serializer\Exception\NotEncodableValueException $e)
        {
            $errorResponse = new ErrorResponse('JSON invalide: ' . $e->getMessage(),
                null, 'JSON_ERROR');
            $json = $this->serializer->serialize($errorResponse, 'json');
            return new JsonResponse($json, Response::HTTP_BAD_REQUEST, [], true);

        } catch (\Exception $e) {
            $errorResponse = new ErrorResponse('Erreur lors de la mise à jour: ' .
                $e->getMessage(), null, 'SERVER_ERROR');
            $json = $this->serializer->serialize($errorResponse, 'json');
            return new JsonResponse($json, Response::HTTP_INTERNAL_SERVER_ERROR, [],
                true);
        }
    }
    /**
     * Supprimer un lien
     * DELETE /api/links/{id}
     */
    #[Route('/api/links/{id}', name: 'api_links_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, LinkRepository $linkRepository): JsonResponse
    {
        try {
            $link = $linkRepository->find($id);

            if (!$link) {
                $errorResponse = new ErrorResponse(
                    "Lien avec l'ID $id introuvable",
                    null,
                    'NOT_FOUND'
                );
                $json = $this->serializer->serialize($errorResponse, 'json');
                return new JsonResponse($json, Response::HTTP_NOT_FOUND, [], true);
            }
            $this->entityManager->remove($link);
            $this->entityManager->flush();
            $response = [
                'success' => true,
                'message' => 'Lien supprimé avec succès',
                'deletedId' => $id
            ];
            $json = $this->serializer->serialize($response, 'json');
            return new JsonResponse($json, Response::HTTP_OK, [], true);
        } catch (\Exception $e) {
            $errorResponse = new ErrorResponse('Erreur lors de la suppression: ' .
                $e->getMessage(), null, 'SERVER_ERROR');
            $json = $this->serializer->serialize($errorResponse, 'json');
            return new JsonResponse($json, Response::HTTP_INTERNAL_SERVER_ERROR, [],
                true);
        }
    }
}
?>
