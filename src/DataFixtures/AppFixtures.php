<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserPasswordHistory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }
    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i < 5; $i++) {
            $user = new User();
            $user->setEmail('grzegorz.cerowski+' . $i . '@gmail.com');
            $user->setFirstLogin(true);
            $user->setLastPasswordChange(new \DateTime('now'));
            $user->setRoles([]);
            $user->setPassword(
                $this->hasher->hashPassword(
                    $user,
                    '12#$asDF' . $i
                )
            );
            $manager->persist($user);

            $userPasswordHistory = new UserPasswordHistory();
            $userPasswordHistory->setPassword('12#$asDF' . $i);
            $userPasswordHistory->setUser($user);
        }

        $manager->flush();
    }
}
