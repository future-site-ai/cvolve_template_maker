<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\AiConnection;
use App\Entity\User;
use App\Service\Ai\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait MakerTestCase
{
    protected function resetDatabase(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        return $em;
    }

    protected function createUser(string $email = 'designer@example.com'): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail($email)->setName('Dana Designer');
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function createConnection(User $user): AiConnection
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = new AiConnection($user, 'My Claude', 'anthropic', 'claude-sonnet-5-5');
        $connection->setEncryptedKey(static::getContainer()->get(SecretBox::class)->encrypt('sk-ant-secret-key-123'), SecretBox::hint('sk-ant-secret-key-123'));
        $em->persist($connection);
        $em->flush();

        return $connection;
    }
}
