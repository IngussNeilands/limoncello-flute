<?php namespace App\Container;

use App\Api\Factories\JsonApiFactory;
use App\Exceptions\JsonApiHandler;
use App\Http\Pagination\PaginationStrategy;
use App\I18n\En\Validation;
use Doctrine\DBAL\Connection;
use Interop\Container\ContainerInterface;
use Limoncello\Container\Container;
use Limoncello\Core\Contracts\Application\ExceptionHandlerInterface;
use Limoncello\Core\Contracts\Config\ConfigInterface;
use Limoncello\Flute\Adapters\FilterOperations;
use Limoncello\Flute\Config\JsonApiConfig;
use Limoncello\Flute\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\Flute\Contracts\Adapters\RepositoryInterface;
use Limoncello\Flute\Contracts\Config\JsonApiConfigInterface;
use Limoncello\Flute\Contracts\Encoder\EncoderInterface;
use Limoncello\Flute\Contracts\FactoryInterface;
use Limoncello\Flute\Contracts\I18n\TranslatorInterface;
use Limoncello\Flute\Contracts\Models\ModelSchemesInterface;
use Limoncello\Flute\Contracts\Schema\JsonSchemesInterface;
use Limoncello\Validation\Contracts\TranslatorInterface as ValidationTranslatorInterface;
use Limoncello\Validation\I18n\Translator as ValidationTranslator;
use Neomerx\JsonApi\Contracts\Http\Query\QueryParametersParserInterface;
use Neomerx\JsonApi\Encoder\EncoderOptions;

// TODO think of moving as much JSON API config trait to json-api lib as possible. Developers are unlikely to change it.

/**
 * @package App
 */
trait SetUpJsonApi
{
    /**
     * @param Container $container
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected static function setUpJsonApi(Container $container)
    {
        $factory = new JsonApiFactory();

        $container[FactoryInterface::class] = function () use ($factory) {
            return $factory;
        };

        $container[QueryParametersParserInterface::class] = function () use ($factory) {
            return $factory->getJsonApiFactory()->createQueryParametersParser();
        };

        $container[JsonSchemesInterface::class] = function (ContainerInterface $container) use ($factory) {
            /** @var JsonApiConfigInterface $jsonApiConfig */
            /** @var ModelSchemesInterface $modelSchemes */
            $jsonApiConfig = $container->get(JsonApiConfigInterface::class);
            $modelSchemes  = $container->get(ModelSchemesInterface::class);

            return $factory->createJsonSchemes($jsonApiConfig->getModelSchemaMap(), $modelSchemes);
        };

        $container[EncoderInterface::class] = function (ContainerInterface $container) use ($factory) {
            /** @var JsonApiConfigInterface $jsonApiConfig */
            /** @var JsonSchemesInterface $jsonSchemes */
            $jsonApiConfig = $container->get(JsonApiConfigInterface::class);
            $jsonSchemes   = $container->get(JsonSchemesInterface::class);
            $encoder       = $factory->createEncoder($jsonSchemes, new EncoderOptions(
                $jsonApiConfig->getJsonEncodeOptions(),
                $jsonApiConfig->getUriPrefix(),
                $jsonApiConfig->getJsonEncodeDepth()
            ));
            if ($jsonApiConfig->getMeta() !== null) {
                $encoder->withMeta($jsonApiConfig->getMeta());
            }
            if ($jsonApiConfig->isShowVersion() === true) {
                $encoder->withJsonApiVersion();
            }

            return $encoder;
        };

        $container[JsonApiConfigInterface::class] = function (ContainerInterface $container) {
            $jsonConfig   = $container->get(ConfigInterface::class)->getConfig(JsonApiConfigInterface::class);

            return (new JsonApiConfig)->setConfig($jsonConfig);
        };

        $container[TranslatorInterface::class] = $translator = $factory->createTranslator();

        $container[ValidationTranslatorInterface::class] = function () {
            // TODO load locale according to current user preferences
            return new ValidationTranslator(Validation::getLocaleCode(), Validation::getMessages());
        };

        $container[RepositoryInterface::class] = function (ContainerInterface $container) use ($factory, $translator) {
            $connection       = $container->get(Connection::class);
            $filterOperations = new FilterOperations($translator);
            /** @var ModelSchemesInterface $modelSchemes */
            $modelSchemes     = $container->get(ModelSchemesInterface::class);

            return $factory->createRepository($connection, $modelSchemes, $filterOperations, $translator);
        };

        $container[PaginationStrategyInterface::class] = function (ContainerInterface $container) {
            /** @var JsonApiConfigInterface $jsonApiConfig */
            $jsonApiConfig = $container->get(JsonApiConfigInterface::class);

            return new PaginationStrategy($jsonApiConfig->getRelationshipPagingSize());
        };

        $container[ExceptionHandlerInterface::class] = function () {
            return new JsonApiHandler();
        };
    }
}
