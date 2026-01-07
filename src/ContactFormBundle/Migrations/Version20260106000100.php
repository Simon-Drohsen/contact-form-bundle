<?php

declare(strict_types=1);

namespace Instride\Bundle\ContactFormBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Pimcore\Model\DataObject\ClassDefinition;

final class Version20260106000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Imports the ContactForm Data Object class definition for the ContactFormBundle';
    }

    public function up(Schema $schema): void
    {
        $classFile = \dirname(__DIR__, 2) . '/Resources/pimcore/classes/class_FormValues.json';

        if (!\is_file($classFile)) {
            throw new \RuntimeException('Missing class definition file at: ' . $classFile);
        }

        $json = (string) \file_get_contents($classFile);
        $data = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $className = $data['name'] ?? null;
        if (!$className) {
            throw new \RuntimeException('Invalid class definition JSON: missing "name".');
        }

        // Skip if class already exists
        if (ClassDefinition::getByName($className) instanceof ClassDefinition) {
            return;
        }

        $class = new ClassDefinition();
        $class->setName($className);
        $class->setValues($data);
        $class->save();
    }
}
