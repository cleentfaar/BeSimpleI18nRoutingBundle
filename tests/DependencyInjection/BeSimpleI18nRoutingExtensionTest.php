<?php
namespace BeSimple\I18nRoutingBundle\Tests\DependencyInjection;

use BeSimple\I18nRoutingBundle\DependencyInjection\BeSimpleI18nRoutingExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Reference;

class BeSimpleI18nRoutingExtensionTest extends AbstractExtensionTestCase
{
    protected function setUp()
    {
        parent::setUp();

        // Add Kernel parameter's
        $this->container->setParameter('kernel.root_dir', __DIR__);
    }

    /**
     * @inheritdoc
     */
    protected function getContainerExtensions()
    {
        return array(
            new BeSimpleI18nRoutingExtension(),
        );
    }

    /**
     * @test
     */
    public function loading_with_default_values()
    {
        $this->load();

        $this->assertContainerBuilderHasService('be_simple_i18n_routing.router');
        $this->assertContainerBuilderHasService('be_simple_i18n_routing.loader.xml');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('be_simple_i18n_routing.loader.xml', 1, new Reference('be_simple_i18n_routing.route_generator'));
        $this->assertContainerBuilderHasService('be_simple_i18n_routing.loader.yaml');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('be_simple_i18n_routing.loader.yaml', 1, new Reference('be_simple_i18n_routing.route_generator'));

        $this->assertContainerBuilderHasAlias('be_simple_i18n_routing.route_name_inflector', 'be_simple_i18n_routing.route_name_inflector.postfix');
        $this->assertContainerBuilderHasService('be_simple_i18n_routing.route_name_inflector.postfix');
        $this->assertContainerBuilderHasService('be_simple_i18n_routing.route_generator');

        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.default_locale', null);
        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.locales', array());

        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.router.class', 'BeSimple\I18nRoutingBundle\Routing\Router');
        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.loader.xml.class', 'BeSimple\I18nRoutingBundle\Routing\Loader\XmlFileLoader');
        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.translator.translation.class', 'BeSimple\I18nRoutingBundle\Routing\Translator\TranslationTranslator');

        $classesToCompile = $this->container->getExtension('be_simple_i18n_routing')->getClassesToCompile();
        $this->assertEquals(
            $classesToCompile,
            PHP_VERSION_ID >= 70000 ? array() : array(
                'BeSimple\\I18nRoutingBundle\\Routing\\RouteGenerator\\NameInflector\\PostfixInflector',
                'BeSimple\\I18nRoutingBundle\\Routing\\Router',
                'BeSimple\\I18nRoutingBundle\\Routing\\RouteGenerator\\NameInflector\\RouteNameInflectorInterface',
            )
        );
        foreach ($classesToCompile as $class) {
            $this->assertTrue(
                class_exists($class) || interface_exists($class) || (function_exists('trait_exists') && trait_exists($class)),
                sprintf('Expected class %s to exists', $class));
        }

        $this->compile();
    }

    /**
     * @test
     */
    public function loading_with_route_name_inflector()
    {
        $this->load(array(
            'route_name_inflector' => 'my.custom_name_inflector',
        ));

        $this->assertContainerBuilderHasAlias('be_simple_i18n_routing.route_name_inflector', 'my.custom_name_inflector');

        $this->assertEquals(
            $this->container->getExtension('be_simple_i18n_routing')->getClassesToCompile(),
            PHP_VERSION_ID >= 70000 ? array() : array(
                'BeSimple\\I18nRoutingBundle\\Routing\\Router',
                'BeSimple\\I18nRoutingBundle\\Routing\\RouteGenerator\\NameInflector\\RouteNameInflectorInterface'
            )
        );
    }

    /**
     * @test
     */
    public function loading_with_annotations()
    {
        $this->load(array(
            'annotations' => true,
        ));

        $this->assertContainerBuilderHasService('be_simple_i18n_routing.loader.annotation_dir', 'Symfony\Component\Routing\Loader\AnnotationDirectoryLoader');
        $this->assertContainerBuilderHasService('be_simple_i18n_routing.loader.annotation_file', 'Symfony\Component\Routing\Loader\AnnotationFileLoader');
        $this->assertContainerBuilderHasService('be_simple_i18n_routing.loader.annotation_class', 'BeSimple\I18nRoutingBundle\Routing\Loader\AnnotatedRouteControllerLoader');
    }

    /**
     * @test
     */
    public function load_attribute_translator_service()
    {
        $this->load(array(
            'attribute_translator' => array(
                'type' => 'service',
                'id' => 'my_translator',
            )
        ));

        $this->assertContainerBuilderHasAlias('be_simple_i18n_routing.translator', 'my_translator');
    }

    /**
     * @test
     */
    public function load_attribute_translator_translator()
    {
        $this->load(array(
            'attribute_translator' => array(
                'type' => 'translator',
            )
        ));

        $this->assertContainerBuilderHasAlias('be_simple_i18n_routing.translator', 'be_simple_i18n_routing.translator.translation');
    }

    /**
     * @test
     */
    public function load_attribute_translator_dbal()
    {
        $this->load(array(
            'attribute_translator' => array(
                'type' => 'doctrine_dbal',
                'cache' => array(
                    'type' => 'array'
                )
            )
        ));

        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.doctrine_dbal.connection_name', null);
        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.doctrine_dbal.cache.namespace');

        $this->assertContainerBuilderHasAlias('be_simple_i18n_routing.translator', 'be_simple_i18n_routing.translator.doctrine_dbal');
        $this->assertContainerBuilderHasAlias('be_simple_i18n_routing.doctrine_dbal.cache', 'be_simple_i18n_routing.doctrine_dbal.cache.array');

        $this->assertContainerBuilderHasService('be_simple_i18n_routing.doctrine_dbal.cache.array', 'Doctrine\Common\Cache\ArrayCache');
        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'be_simple_i18n_routing.translator.doctrine_dbal.schema_listener',
            'doctrine.event_listener',
            array('event' => 'postGenerateSchema')
        );
    }

    /**
     * @test
     */
    public function load_attribute_translator_dbal_with_connection()
    {
        $this->load(array(
            'attribute_translator' => array(
                'type' => 'doctrine_dbal',
                'connection' => 'my_connection',
                'cache' => array(
                    'type' => 'array'
                )
            )
        ));

        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.doctrine_dbal.connection_name', 'my_connection');

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            'be_simple_i18n_routing.translator.doctrine_dbal.schema_listener',
            'doctrine.event_listener',
            array('event' => 'postGenerateSchema', 'connection' => 'my_connection')
        );
    }

    /**
     * @test
     */
    public function load_locales_simple()
    {
        $this->load(array(
            'locales' => array(
                'default_locale' => 'nl',
                'supported' => array('en', 'nl'),
                'filter' => false,
                'strict' => false,
            )
        ));

        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.default_locale', 'nl');
        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.locales', array('en', 'nl'));

        $this->assertContainerBuilderHasAlias('be_simple_i18n_routing.route_generator', 'be_simple_i18n_routing.route_generator.i18n');

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('be_simple_i18n_routing.route_generator.strict', 1, '%be_simple_i18n_routing.locales%');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('be_simple_i18n_routing.route_generator.filter', 1, '%be_simple_i18n_routing.locales%');
    }

    /**
     * @test
     */
    public function load_locales_full()
    {
        $locales = array('pink', 'green');

        $this->load(array(
            'locales' => array(
                'default_locale' => 'pink',
                'supported' => $locales,
                'filter' => true,
                'strict' => true,
            )
        ));

        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.default_locale', 'pink');
        $this->assertContainerBuilderHasParameter('be_simple_i18n_routing.locales', $locales);

        $this->assertContainerBuilderHasAlias('be_simple_i18n_routing.route_generator', 'be_simple_i18n_routing.route_generator.filter');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('be_simple_i18n_routing.route_generator.filter', 0, new Reference('be_simple_i18n_routing.route_generator.strict'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('be_simple_i18n_routing.route_generator.strict', 0, new Reference('be_simple_i18n_routing.route_generator.i18n'));
    }

    /**
     * @test
     */
    public function load_locales_filtered()
    {
        $this->load(array(
            'locales' => array(
                'default_locale' => 'pink',
                'supported' => array('pink', 'green'),
                'filter' => true,
                'strict' => false,
            )
        ));

        $this->assertContainerBuilderHasAlias('be_simple_i18n_routing.route_generator', 'be_simple_i18n_routing.route_generator.filter');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('be_simple_i18n_routing.route_generator.filter', 0, new Reference('be_simple_i18n_routing.route_generator.i18n'));
    }

    /**
     * @test
     */
    public function load_locales_strict()
    {
        $this->load(array(
            'locales' => array(
                'default_locale' => 'pink',
                'supported' => array('pink', 'green'),
                'filter' => false,
                'strict' => true,
            )
        ));

        $this->assertContainerBuilderHasAlias('be_simple_i18n_routing.route_generator', 'be_simple_i18n_routing.route_generator.strict');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('be_simple_i18n_routing.route_generator.strict', 0, new Reference('be_simple_i18n_routing.route_generator.i18n'));
    }

    /**
     * @test
     */
    public function load_locales_strict_with_fallback()
    {
        $this->load(array(
            'locales' => array(
                'default_locale' => 'pink',
                'supported' => array('pink', 'green'),
                'filter' => false,
                'strict' => null,
            )
        ));

        $this->assertContainerBuilderHasAlias('be_simple_i18n_routing.route_generator', 'be_simple_i18n_routing.route_generator.strict');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('be_simple_i18n_routing.route_generator.strict', 0, new Reference('be_simple_i18n_routing.route_generator.i18n'));
        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall('be_simple_i18n_routing.route_generator.strict', 'allowFallback', array(true));
    }
}
