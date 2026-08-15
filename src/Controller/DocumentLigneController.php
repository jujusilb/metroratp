<?php

namespace App\Controller;

use App\Entity\DocumentLigne;
use App\Form\DocumentLigneType;
use App\Repository\DocumentLigneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/document-ligne')]
final class DocumentLigneController extends AbstractController
{
    #[Route(name: 'app_document_ligne_index', methods: ['GET'])]
    public function index(Request $request, DocumentLigneRepository $documentLigneRepository, PaginatorInterface $paginator): Response
    {
        $qb = $documentLigneRepository->createQueryBuilder('d')
            ->leftJoin('d.ligne', 'l')->addSelect('l')
            ->orderBy('l.label', 'ASC')
            ->addOrderBy('d.type', 'ASC')
        ;

        return $this->render('document_ligne/index.html.twig', [
            'documents' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }

    #[Route('/new', name: 'app_document_ligne_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $document = new DocumentLigne();
        $form = $this->createForm(DocumentLigneType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($document);
            $entityManager->flush();

            return $this->redirectToRoute('app_document_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('document_ligne/new.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_document_ligne_show', methods: ['GET'])]
    public function show(DocumentLigne $document): Response
    {
        return $this->render('document_ligne/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_document_ligne_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DocumentLigne $document, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DocumentLigneType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_document_ligne_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('document_ligne/edit.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_document_ligne_delete', methods: ['POST'])]
    public function delete(Request $request, DocumentLigne $document, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($document);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_document_ligne_index', [], Response::HTTP_SEE_OTHER);
    }
}
