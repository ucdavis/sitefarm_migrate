<?php

namespace Drupal\Tests\sitefarm_migrate_csv_people\Unit\Plugin\migrate\process;

use Drupal\sitefarm_migrate_csv_people\Plugin\migrate\process\Urls;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Path\PathValidator;
use Drupal\Tests\sitefarm_migrate_csv_people\Unit\Plugin\migrate\process\MockedUrls;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\sitefarm_migrate_csv_people\Plugin\migrate\process\Urls
 * @group sitefarm_migrate_csv_people
 */
class UrlsTest extends UnitTestCase {

  /**
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $pathValidator;

  /**
   * Default config for the plugin
   *
   * @var array
   */
  protected $pluginConfig = [
    'delimiter' => ';',
  ];

  /**
   * @var \Drupal\sitefarm_migrate_csv_people\Plugin\migrate\process\Urls
   */
  protected $plugin;

  /**
   * Create the setup for constants and configFactory stub
   */
  protected function setUp() {
    parent::setUp();

    // stub path validator
    $this->pathValidator = $this->prophesize(PathValidator::CLASS);

    $plugin_id = 'urls_process';
    $plugin_definition['provider'] = 'urls_process';

    $this->plugin = new MockedUrls($this->pluginConfig, $plugin_id, $plugin_definition, $this->pathValidator->reveal());
  }

  /**
   * Tests the create method.
   *
   * @see \Drupal\sitefarm_migrate_csv_people\Plugin\migrate\process\Urls::create()
   */
  public function testCreate() {
    $plugin_id = 'urls_process';
    $plugin_definition['provider'] = 'urls_process';

    $container = $this->prophesize(ContainerInterface::CLASS);
    $container->get('path.validator')->willReturn($this->pathValidator);

    $instance = Urls::create($container->reveal(), $this->pluginConfig, $plugin_id, $plugin_definition);
    $this->assertInstanceOf(Urls::CLASS, $instance);
  }

  /**
   * Tests the checkUrl method.
   *
   * @dataProvider checkUrlProvider
   *
   * @param $url
   *   The entered url.
   * @param $expected
   *   The expected returned url after formatting.
   * @param bool $internal
   *   Conditional for if the url is supposed to be an internal path.
   */
  public function testCheckUrl($url, $expected, $internal = FALSE) {
    // Set the internal path to valid.
    if ($internal) {
      $this->pathValidator->isValid(Argument::any())->willReturn(TRUE);
    }

    $returned = $this->plugin->checkUrl($url);
    $this->assertEquals($expected, $returned);
  }

  /**
   * Provider for testCheckUrl()
   */
  public function checkUrlProvider() {
    // given url, expected url, internal link (optional)
    return [
      ['http://link.com', 'http://link.com'],
      ["'http://link.com'", 'http://link.com'],
      ['"http://link.com"', 'http://link.com'],
      ['http://www.link.com', 'http://www.link.com'],
      [' http://www.link.com  ', 'http://www.link.com'],
      ['link.com', 'http://link.com'],
      ['www.link.com', 'http://www.link.com'],
      ['/directory', 'internal:/directory', 'internal'],
      ['directory', 'internal:/directory', 'internal'],
      ['directory', 'http://directory'],
      ['/invalid-local', FALSE],
      ['randomstring', 'http://randomstring'],
      ['randomstring ', 'http://randomstring'],
      [' randomstring ', 'http://randomstring'],
      ['random;string', FALSE],
      [' random;str ing ', FALSE],
      ['http://link.com?id=1&amp;name=joe', 'http://link.com?id=1&name=joe'],
      [
        'http%3A%2F%2Flink.com%3Fid%3D1%26name%3Djoe',
        'http://link.com?id=1&name=joe',
      ],
      ['http://link.com?id=1;name=joe', FALSE],
      ["'http://link.com?id=1;name=joe'", FALSE],
      [
        'ftp://billgates:moremoney@files.microsoft.com/special/secretplans',
        'ftp://billgates:moremoney@files.microsoft.com/special/secretplans',
      ],
      [
        'billgates:moremoney@files.microsoft.com/special/secretplans',
        'http://billgates:moremoney@files.microsoft.com/special/secretplans',
      ],
    ];
  }

}
