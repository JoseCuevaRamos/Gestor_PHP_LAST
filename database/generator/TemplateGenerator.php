<?php

class TemplateGenerator extends AbstractTemplateGenerator
{
    /**
     * {@inheritdoc}
     */
    public function getMigrationTemplate(): string
    {
        return file_get_contents(__DIR__ . '/../templates/migration.stub');
    }

    /**
     * {@inheritdoc}
     */
    public function getSeedTemplate(): string
    {
        return file_get_contents(__DIR__ . '/../templates/seed.stub');
    }

    /**
     * {@inheritdoc}
     */
    public function postMigrationCreation(string $migrationPath, string $className, string $baseClassName): void
    {
        // Do nothing, just satisfy the interface
    }
}