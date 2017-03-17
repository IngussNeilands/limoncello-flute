<?php namespace App\Api\Factories;

use Doctrine\DBAL\Connection;
use Limoncello\Flute\Adapters\Repository;
use Limoncello\Flute\Contracts\Adapters\FilterOperationsInterface;
use Limoncello\Flute\Contracts\I18n\TranslatorInterface;
use Limoncello\Flute\Contracts\Models\ModelSchemesInterface;
use Limoncello\Flute\Factory;

/**
 * @package App
 */
class JsonApiFactory extends Factory
{
    /** @noinspection PhpMissingParentCallCommonInspection
     * @inheritdoc
     */
    public function createRepository(
        Connection $connection,
        ModelSchemesInterface $modelSchemes,
        FilterOperationsInterface $filterOperations,
        TranslatorInterface $translator
    ) {
        return new Repository(
            $connection,
            $modelSchemes,
            $filterOperations,
            $translator
        );
    }
}
