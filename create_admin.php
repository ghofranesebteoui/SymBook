<?php

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

require dirname(__DIR__).'/vendor/autoload.php';

$container = require dirname(__DIR__).'/config/bootstrap.php';

if (!$container->has('kernel')) {
    throw new \LogicException('Kernel not found.');
}

$kernel = $container->get('kernel');
$em = $kernel->getContainer()->get(EntityManagerInterface::class);
$passwordHasher = $kernel->getContainer()->get(UserPasswordHasherInterface::class);

$admin = new User();
$admin->setEmail('admin@example.com');
$admin->setRoles(['ROLE_ADMIN']);
$admin->setPassword($passwordHasher->hashPassword($admin, 'adminpassword'));

$em->persist($admin);
$em->flush();

echo "Admin user created with email 'admin@example.com' and password 'adminpassword'.\n";