<?php

namespace App\Controller;

use App\Entity\Commandes;
use App\Repository\CommandesRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class OrdersController extends AbstractController
{
    #[Route('/orders', name: 'app_orders_all')]
    public function all(CommandesRepository $rep, PaginatorInterface $paginator, Request $request): Response
    {
        $orders = $paginator->paginate(
            $rep->findAll(),
            $request->query->getInt('page', 1),
            10
        );
        return $this->render('orders/all.html.twig', ['orders' => $orders]);
    }

    #[Route('/orders/pending', name: 'app_orders_pending')]
    public function pending(CommandesRepository $rep, PaginatorInterface $paginator, Request $request): Response
    {
        $orders = $paginator->paginate(
            $rep->findBy(['status' => 'pending']),
            $request->query->getInt('page', 1),
            10
        );
        return $this->render('orders/pending.html.twig', ['orders' => $orders]);
    }

    #[Route('/orders/inprogress', name: 'app_orders_inprogress')]
    public function inProgress(CommandesRepository $rep, PaginatorInterface $paginator, Request $request): Response
    {
        $orders = $paginator->paginate(
            $rep->findBy(['status' => 'inprogress']),
            $request->query->getInt('page', 1),
            10
        );
        return $this->render('orders/inprogress.html.twig', ['orders' => $orders]);
    }

    #[Route('/orders/completed', name: 'app_orders_completed')]
    public function completed(CommandesRepository $rep, PaginatorInterface $paginator, Request $request): Response
    {
        $orders = $paginator->paginate(
            $rep->findBy(['status' => 'completed']),
            $request->query->getInt('page', 1),
            10
        );
        return $this->render('orders/completed.html.twig', ['orders' => $orders]);
    }
}