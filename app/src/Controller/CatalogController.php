<?php

namespace App\Controller;

use App\Entity\Catalog;
use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Repository\UsersRepository;
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class CatalogController extends AbstractController
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
                    $roles = $user->getRoles();
                    if ($roles["ROLE_USER"] === "ADMIN") {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
            return false;
        } catch (\Exception $exception) {
            throw new AuthenticationException($exception->getMessage());
        }
    }

    static function checkEmptyRequest($request)
    {
        if (empty($request->request->get('name'))
        || empty($request->request->get('description'))
        || empty($request->request->get('price'))
        || empty($request->request->get('photo'))
        ) {
            return false;
        }
        return true;
    }

    #[Route('/api/products', name: 'retrieve_products', methods: ['GET'])]
    public function retrieve(): Response
    {
        $products = $this->getDoctrine()
            ->getRepository(Catalog::class)
            ->findAll();
 
        $data = [];
 
        foreach ($products as $product) {
           $data[] = [
               'id' => $product->getId(),
               'name' => $product->getName(),
               'description' => $product->getDescription(),
               'photo' => $product->getPhoto(),
               'price' => $product->getPrice()
           ];
        }

        return $this->json($data);
    }

    #[Route('/api/products/{productId}', name: 'retrieve_products_by_id', methods: ['GET'])]
    public function retrieve_by_id(int $productId): Response
    {
        $product = $this->getDoctrine()
            ->getRepository(Catalog::class)
            ->find($productId);

        if (!$product) {
            return $this->json(["error: " => 'Can not find product with id ' . $productId], 404);
        }
 
        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'photo' => $product->getPhoto(),
            'price' => $product->getPrice()
        ];

        return $this->json($data);
    }

    #[Route('/api/products', name: 'add_products', methods: ['POST'])]
    public function add(Request $request)
    {
        if (!$this->checkEmptyRequest($request)) {
            return $this->json(["error: " => "Missing argument!"], 400);
        }
        $credentials = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        $entityManager = $this->getDoctrine()->getManager();
        if ($this->check_authed($request, $entityManager, $credentials)) {
            $products = $entityManager->getRepository(Catalog::class)->findAll();
            foreach ($products as $product) {
                if ($product->getName() === $request->request->get('name')) {
                    return $this->json(["error: " => "Name of item should be unique!"], 400);
                }
            }
            $product = new Catalog();
            $product->setName($request->request->get('name'));
            $product->setDescription($request->request->get('description'));
            $product->setPhoto($request->request->get('photo'));
            $product->setPrice($request->request->get('price'));
    
            $entityManager->persist($product);
            $entityManager->flush();
    
            return $this->json([
                'message: ' =>'Created new project successfully with id ' . $product->getId()], 201);
        }
        return $this->json(["error: " => "Only admin can do this action!"], 400);
    }

    #[Route('/api/products/{productId}', name: 'update_product', methods: ['PUT'])]
    public function update(Request $request, int $productId)
    {
        $credentials = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        $entityManager = $this->getDoctrine()->getManager();
        if ($this->check_authed($request, $entityManager, $credentials)) {
            $product = $entityManager->getRepository(Catalog::class)->find($productId);

            if(!$product) {
                return $this->json(["error" => "Product with id ". $productId ." not found"], 404);
            }

            if (null !== $request->request->get('name')) {  
                $product->setName($request->request->get('name')); 
            }
            if (null !== $request->request->get('description')) {  
                $product->setDescription($request->request->get('description'));
            }
            if (null !== $request->request->get('photo')) {
                $product->setPhoto($request->request->get('photo'));
            }
            if (null !== $request->request->get('price')) {  
                $product->setPrice($request->request->get('price'));
            }    
            $entityManager->flush();
            $data[] = [
                "message: " => "Modify succesfully product id ".$productId
            ];
    
            $data[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'photo' => $product->getPhoto(),
                'price' => $product->getPrice()
            ];

            return $this->json($data);
        }
        return $this->json(["error: " => "Only admin can do this action!"], 400);
    }

    #[Route('/api/products/{productId}', name: 'delete_products', methods: ['DELETE'])]
    public function delete(Request $request, int $productId)
    {
        $credentials = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        $entityManager = $this->getDoctrine()->getManager();
        if ($this->check_authed($request, $entityManager, $credentials)) {
            $product = $entityManager->getRepository(Catalog::class)->find($productId);

            if(!$product) {
                return $this->json(["error" => "Product with id ". $productId ." not found"], 404);
            }

            $entityManager->remove($product);
            $entityManager->flush();
            return $this->json(["success" => "Delete successfully product with id ".$productId]);
        }
        return $this->json(["error: " => "Only admin can do this action!"], 400);
    }
}
