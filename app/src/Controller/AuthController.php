<?php

namespace App\Controller;

use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Repository\UsersRepository;
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AuthController extends AbstractController
{
    static function checkUniqueEmail($users, $email)
    {
        foreach($users as $user) {
            if ($user->getEmail() == $email) {
                return false;
            }
        }
        return true;
    }

    static function checkUniqueLogin($users, $login)
    {
        foreach($users as $user) {
            if ($user->getLogin() == $login) {
                return false;
            }
        }
        return true;
    }

    static function checkEmptyRequest($request)
    {
        if (empty($request->request->get('login'))
        || empty($request->request->get('email'))
        || empty($request->request->get('password'))
        || empty($request->request->get('firstname'))
        || empty($request->request->get('lastname'))
        ) {
            return false;
        }
        return true;
    }

    #[Route('/api/register', name: 'register', methods: ['POST'])]
    public function register(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $users = $entityManager->getRepository(Users::class)->findAll();
        if (!($this->checkUniqueLogin($users, $request->request->get('login'))
        && $this->checkUniqueEmail($users, $request->request->get('email')))) {
            return $this->json(["error: " => "Login and email have to be unique!"], 400);
        }
        if (!$this->checkEmptyRequest($request)) {
            return $this->json(["error: " => "Missing argument!"], 400);
        }
        $password = $request->request->get('password');
        $email = $request->request->get('email');
        $user = new Users();
        $user->setPassword($encoder->encodePassword($user, $password));
        $user->setEmail($email);
        $user->setLogin($request->request->get('login'));
        $user->setRoles(["ROLE_USER" => "USER"]);
        print($request->request->get('firstname'));
        $user->setFirstname($request->request->get('firstname'));
        $user->setLastname($request->request->get('lastname'));
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();
        return $this->json([
            'message: ' => "Regester successfully",
            'user' => $user->getLogin()
        ], 201);
    }

    #[Route('/api/login', name: 'login', methods: ['POST'])]
    public function login(Request $request, UsersRepository $usersRepository, UserPasswordEncoderInterface $encoder)
    {
        $user = $usersRepository->findOneBy([
                'login'=>$request->get('login'),
        ]);
        if (!$user || !$encoder->isPasswordValid($user, $request->get('password'))) {
                return $this->json([
                    'error' => 'email or password is wrong.',
                ]);
        }
        $payload = [
            "user" => $user->getUsername(),
            "exp"  => (new \DateTime())->modify("+15 minutes")->getTimestamp(),
        ];

        $jwt = JWT::encode($payload, $this->getParameter('jwt_secret'), 'HS256');
        return $this->json([
            'message' => 'success!',
            'token' => sprintf('Bearer %s', $jwt),
        ]);
    }

    #[Route('/api/users', name: 'display', methods: ['GET'])]
    public function dislay(Request $request)
    {
        $credentials = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        $entityManager = $this->getDoctrine()->getManager();
        try {
            $jwt = (array) JWT::decode(
                            $credentials, 
                            new Key("SOME_SECRET",
                            'HS256')
                        );
            $users = $entityManager->getRepository(Users::class)->findAll();

            if (!$users) {
                return $this->json(["error: " => 'No Users found for id'], 404);
            }
            foreach($users as $user) {
                if ($user->getLogin() == $jwt['user']) {
                    $data = [
                        'id' => $user->getId(),
                        'login' => $user->getLogin(),
                        'email' => $user->getEmail(),
                        'role' => $user->getRoles(),
                        'firstname' => $user->getFirstname(),
                        'lastname' => $user->getLastname(),
                        'password' => $user->getPassword(),
                    ];
                }
            }
            return $this->json($data);
        } catch (\Exception $exception) {
            throw new AuthenticationException($exception->getMessage());
        }
    }

    #[Route('/api/users', name: 'update', methods: ['PUT'])]
    public function update(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $credentials = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        $entityManager = $this->getDoctrine()->getManager();
        try {
            $jwt = (array) JWT::decode(
                            $credentials, 
                            new Key("SOME_SECRET",
                            'HS256')
                        );
            $users = $entityManager->getRepository(Users::class)->findAll();
            if (!$users) {
                return $this->json(["error: " => 'No Users found for id'], 404);
            }
            foreach($users as $user) {
                if ($user->getLogin() == $jwt['user']) {
                    if (null !== $request->request->get('login')) {
                        if ($this->checkUniqueLogin($users, $request->request->get('login'))) {
                            $user->setLogin($request->request->get('login'));
                        } else {
                            return $this->json(["error: " => "Login is not valid"]);
                        }
                    }
                    if (null !== $request->request->get('email')) {
                        if ($this->checkUniqueEmail($users, $request->request->get('email'))) {
                            $user->setEmail($request->request->get('email'));
                        } else {
                            return $this->json(["error: " => "Email is not valid"]);
                        }
                    }
                    if (null !== $request->request->get('password')) {
                        $user->setPassword($encoder->encodePassword($user, $request->request->get('password')));
                    }
                    if (null !== $request->request->get('firstname')) {
                        $user->setFirstname($request->request->get('firstname'));
                    }
                    if (null !== $request->request->get('lastname')) {
                        $user->setLastname($request->request->get('lastname'));
                    }
                    $entityManager->flush();
                    $data = [
                        'id' => $user->getId(),
                        'login' => $user->getLogin(),
                        'email' => $user->getEmail(),
                        "role" => $user->getRoles(),
                        'firstname' => $user->getFirstname(),
                        'lastname' => $user->getLastname(),
                        'password' => $user->getPassword(),
                    ];
                    return $this->json($data);
                }
            }
        } catch (\Exception $exception) {
            throw new AuthenticationException($exception->getMessage());
        }
    }
}
