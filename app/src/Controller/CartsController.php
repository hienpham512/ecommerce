<?php

namespace App\Controller;

use App\Entity\Carts;
use App\Entity\Users;
use App\Entity\Orders;
use App\Entity\Catalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Repository\UsersRepository;
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

class CartsController extends AbstractController
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

    static function calculateTotalPrice($products)
    {
        $total = 0.0;
        foreach($products as $product) {
            $total += $product['price'];
        }
        return $total;
    }

    #[Route('/api/carts', name: 'display_carts_user', methods: ['GET'])]
    public function displayProducts(Request $request): Response
    {
        $credentials = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        $entityManager = $this->getDoctrine()->getManager();
        $userId = $this->check_authed($request, $entityManager, $credentials);
        if (null !== $userId) {
            $carts = $entityManager->getRepository(Carts::class)->findAll();
            foreach($carts as $cart) {
                if ($cart->getUserId() === $userId) {
                    $products = $cart->getProducts();
                    return $this->json($cart->getProducts());
                }
            }
            return $this->json([]);
        }
        return $this->json(["error: " => "Bad credentials"], 400);
    }

    #[Route('/api/carts/{productId}', name: 'Addcarts', methods: ['GET'])]
    public function addProducts(Request $request, int $productId): Response
    {
        $credentials = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        $entityManager = $this->getDoctrine()->getManager();
        $userId = $this->check_authed($request, $entityManager, $credentials);
        if (null !== $userId) {
            $carts = $entityManager->getRepository(Carts::class)->findAll();
            $product = $entityManager->getRepository(Catalog::class)->find($productId);
            if(!$product) {
                return $this->json(["error" => "Product with id ". $productId ." not found"], 404);
            }
            $dataProduct = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'photo' => $product->getPhoto(),
                'price' => $product->getPrice()
            ];
            foreach($carts as $cart) {
                if ($cart->getUserId() === $userId) {
                    $products = $cart->getProducts();
                    $products[] = $dataProduct;
                    $cart->setProducts($products);
                    $entityManager->persist($cart);
                    $entityManager->flush();
                    return $this->json([
                        'id' => $cart->getId(),
                        'userId' =>$cart->getUserId(),
                        'products' => $cart->getProducts()
                    ], 201);
                }
            }
            $cart = new Carts();

            $cart->setUserId($userId);
            $cart->setProducts([$dataProduct]);
            $entityManager->persist($cart);
            $entityManager->flush();
            return $this->json([
                'id' => $cart->getId(),
                'userId' =>$cart->getUserId(),
                'products' => $cart->getProducts()
            ], 201);
        }
        return $this->json(["error: " => "Bad credentials"], 400);
    }

    #[Route('/api/carts/{productId}', name: 'deleteCarts', methods: ['DELETE'])]
    public function delete(Request $request, int $productId): Response
    {
        $credentials = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        $entityManager = $this->getDoctrine()->getManager();
        $userId = $this->check_authed($request, $entityManager, $credentials);
        if (null !== $userId) {
            $carts = $entityManager->getRepository(Carts::class)->findAll();
            $productFindById = $entityManager->getRepository(Catalog::class)->find($productId);
            if(!$productFindById) {
                return $this->json(["error" => "Product with id ". $productId ." not found"], 404);
            }
            foreach($carts as $cart) {
                if ($cart->getUserId() === $userId) {
                    $products = $cart->getProducts();
                    foreach ($products as $product) {
                        if ( $product['id'] === $productId) {
                            unset($products[array_search($product, $products)]);
                            $cart->setProducts($products);
                            $entityManager->persist($cart);
                            $entityManager->flush();
                            return $this->json(["message" => "Delete successfully product with id " .$productId. " from carts"]);
                        }
                    }
                }
            }
            return $this->json(["error: " => "product with id ". $productId . " dose not exist in carts"], 400);
        }
        return $this->json(["error: " => "Bad credentials"], 400);
    }

    #[Route('/api/carts/validate', name: 'validateCarts', methods: ['POST'])]
    public function validate(Request $request): Response
    {
        $credentials = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        $entityManager = $this->getDoctrine()->getManager();
        $userId = $this->check_authed($request, $entityManager, $credentials);
        if (null !== $userId) {
            $carts = $entityManager->getRepository(Carts::class)->findAll();
            foreach($carts as $cart) {
                if ($cart->getUserId() === $userId) {
                    if (empty($cart->getProducts())) {
                        return $this->json(["error: " => "Cart is empty!"]);
                    }
                    $order = new Orders();

                    $order->setTotalPrice($this->calculateTotalPrice($cart->getProducts()));
                    $order->setUserId($userId);
                    $order->setProducts($cart->getProducts());
                    $date = new \DateTimeImmutable();
                    $order->setCreationDate($date);
                    $entityManager->persist($order);
                    $cart->setProducts([]);
                    $entityManager->persist($cart);
                    $entityManager->flush();
                    return $this->json(["message: " => "Order is created successfully!"], 201);
                }
            }
        }
        return $this->json(["error: " => "Bad credentials"], 400);
    }
}
