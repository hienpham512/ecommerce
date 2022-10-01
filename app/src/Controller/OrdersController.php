<?php

namespace App\Controller;

use App\Entity\Carts;
use App\Entity\Users;
use App\Entity\Catalog;
use App\Entity\Orders;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Repository\UsersRepository;
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

class OrdersController extends AbstractController
{
    static function check_authed($request, $entityManager, $credentials)
    {
        try {
            $jwt = (array) JWT::decode(
                            $credentials, 
                            new Key("SOME_SECRET",
                            'HS256')
                        );
            $users = $entityManager->getRepository(Users::class)->findAll();
            if (!$users) {
                return false;
            }
            foreach ($users as $user) {
                if ($user->getLogin() == $jwt['user']) {
                    return $user->getId();
                }
            }
            return null;
        } catch (\Exception $exception) {
            throw new AuthenticationException($exception->getMessage());
        }
    }

    #[Route('/api/orders', name: 'listOrders', methods: ['GET'])]
    public function displaylistOrder(Request $request): Response
    {
        $credentials = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        $entityManager = $this->getDoctrine()->getManager();
        $userId = $this->check_authed($request, $entityManager, $credentials);
        if (null !== $userId) {
            $orders = $entityManager->getRepository(Orders::class)->findAll();
            $data = [];
            foreach ($orders as $order) {
                if ($order->getUserId() === $userId) {
                    $data[] = [
                        'id' => $order->getId(),
                        'totalPrice' => $order->getTotalPrice(),
                        'creattionDate' => $order->getCreationDate()->format('Y-m-d\TH:i:sp'),
                        'products' => $order->getProducts()
                    ];
                }
            }
            return $this->json($data);
        }
        return $this->json(["error: " => "Bad credentials"], 400);
    }

    #[Route('/api/orders/{orderId}', name: 'listOrdersById', methods: ['GET'])]
    public function displaylistOrderById(Request $request, int $orderId): Response
    {
        $credentials = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        $entityManager = $this->getDoctrine()->getManager();
        $userId = $this->check_authed($request, $entityManager, $credentials);
        if (null !== $userId) {
            $orders = $entityManager->getRepository(Orders::class)->findAll();
            foreach ($orders as $order) {
                if ($order->getUserId() === $userId && $order->getId() === $orderId) {
                    $data = [
                        'id' => $order->getId(),
                        'totalPrice' => $order->getTotalPrice(),
                        'creattionDate' => $order->getCreationDate()->format('Y-m-d\TH:i:sp'),
                        'products' => $order->getProducts()
                    ];
                    return $this->json($data);
                }
            }
            return $this->json(["error: " => "Order with id ".$orderId. " not found"], 404);
        }
        return $this->json(["error: " => "Bad credentials"], 400);
    }
}
