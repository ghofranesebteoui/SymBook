<?php

namespace App\Controller;

use App\Repository\LivresRepository;
use App\Repository\CommandesRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        LivresRepository $livreRep,
        CommandesRepository $commandeRep,
        UserRepository $userRep
    ): Response
    {
        // Statistiques générales
        $totalBooks = count($livreRep->findAll());
        $totalUsers = count($userRep->findAll());

        // Commandes récentes
        $recentOrders = $commandeRep->findBy([], ['date' => 'DESC'], 5);

        // Statistiques des commandes
        $totalOrders = count($commandeRep->findAll());

        // Calcul du montant total des ventes
        $totalSales = 0;
        $orders = $commandeRep->findAll();
        foreach ($orders as $order) {
            $totalSales += $order->getMontantTotal();
        }

        // Données pour le graphique des ventes (6 derniers mois)
        $salesChartData = [];
        $salesChartLabels = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = new \DateTime("first day of -$i month");
            $month = $date->format('m');
            $year = $date->format('Y');

            $monthStart = new \DateTime("$year-$month-01");
            $monthEnd = new \DateTime("$year-$month-" . $monthStart->format('t'));

            $monthOrders = $commandeRep->createQueryBuilder('c')
                ->where('c.date BETWEEN :start AND :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getResult();

            $monthTotal = 0;
            foreach ($monthOrders as $order) {
                $monthTotal += $order->getMontantTotal();
            }

            $salesChartData[] = $monthTotal;
            $salesChartLabels[] = $date->format('M Y');
        }

        // Données pour le graphique des statuts de commande
        $pendingOrders = count($commandeRep->findBy(['statut' => 'pending']));
        $completedOrders = count($commandeRep->findBy(['statut' => 'completed']));
        $inProgressOrders = $totalOrders - $pendingOrders - $completedOrders;

        $orderStatusData = [$pendingOrders, $completedOrders, $inProgressOrders];

        // Livres les plus vendus
        // Note: Ceci suppose que vous avez un champ 'sales' dans votre entité Livres
        // Si ce n'est pas le cas, vous devrez adapter cette logique
        $topSellingBooks = $livreRep->findBy([], ['id' => 'DESC'], 5);

        return $this->render('admin/dashboard.html.twig', [
            'totalBooks' => $totalBooks,
            'totalUsers' => $totalUsers,
            'totalOrders' => $totalOrders,
            'totalSales' => $totalSales,
            'recentOrders' => $recentOrders,
            'topSellingBooks' => $topSellingBooks,
            'salesChartData' => $salesChartData,
            'salesChartLabels' => $salesChartLabels,
            'orderStatusData' => $orderStatusData,
        ]);
    }
}
