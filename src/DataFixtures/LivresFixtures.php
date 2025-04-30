<?php

namespace App\DataFixtures;

use App\Entity\Categories;
use App\Entity\Livres;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class LivresFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        for ($j=1; $j <= 5 ; $j++) {
            $cat = new Categories();
            $names=['Roman','Intelligence Artificielle','Cuisine','Histoire','Base de données'];
            $cat->setLibelle($names[$j-1])
                ->setSlug(strtolower(str_replace(' ', '-', $names[$j-1])))
                ->setDescription($faker->text);
            $manager->persist($cat);
            for ($i = 0; $i < random_int(10,15); $i++) {
                $livre = new Livres();
                $titre = $faker->name();
                $livre->setTitre($titre)
                ->setSlug(strtolower(str_replace(' ', '-', $titre)))
                ->setIsbn($faker->isbn13())
                ->setEditeur($faker->company())
                ->setDateEdition($faker->dateTimeBetween('-5 years', 'now'))
                ->setImage('https://picsum.photos/seed/picsum/200/300')
                ->setPrix($faker->randomFloat(2, 10, 700))
                ->setResume($faker->text())
                ->setCategorie($cat);
                $manager->persist($livre);
            }
        }
        $manager->flush();
    }
}