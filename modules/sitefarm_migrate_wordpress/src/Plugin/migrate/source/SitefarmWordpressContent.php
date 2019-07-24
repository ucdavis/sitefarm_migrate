<?php

namespace Drupal\sitefarm_migrate_wordpress\Plugin\migrate\source;

use Drupal\Driver\Exception\Exception;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\Core\Database\Connection;
use Drupal;
use DOMDocument;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\AliasManager;

/**
 * Source plugin for retrieving data via URLs.
 *
 * @MigrateSource(
 *   id = "sitefarm_wordpress_content"
 * )
 */
class SitefarmWordpressContent extends Url implements ContainerFactoryPluginInterface {

  /**
   * The XMLReader we are encapsulating.
   *
   * @var \SimpleXMLElement
   */
  protected $xml;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entitymanager;

  /**
   * @var \Drupal\Core\Path\AliasManager
   */
  protected $aliasManager;

  /**
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $containerAwareEventDispatcher;

  /**
   * SitefarmWordpressContent constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $containerAwareEventDispatcher
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entitymanager
   * @param \Drupal\Core\Path\AliasManager $aliasManager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, ContainerAwareEventDispatcher $containerAwareEventDispatcher, Connection $database, EntityTypeManagerInterface $entitymanager, AliasManager $aliasManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->containerAwareEventDispatcher = $containerAwareEventDispatcher;

    $this->aliasManager = $aliasManager;

    // Get the raw data so we can parse it as needed
    $source = $this->migration->getSourceConfiguration();
    $file = file_create_url($source['urls'][0]);

    // Map the returned object to a property we can reuse
    $this->xml = simplexml_load_file($file);

    $this->database = $database;

    $this->entitymanager = $entitymanager;

    // Initiate the post row save function
    $containerAwareEventDispatcher->addListener("migrate.post_row_save", [
      $this,
      'onPostRowSave',
    ]);
    $containerAwareEventDispatcher->addListener("migrate.post_import", [
      $this,
      'onPostImport',
    ]);
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\migrate\Plugin\MigrationInterface|NULL $migration
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('event_dispatcher'),
      $container->get("database"),
      $container->get("entity.manager"),
      $container->get("path.alias_manager")
    );
  }

  /**
   * @inheritdoc
   */
  public function prepareRow(Row $row) {
    // Prepare the Featured Image source data
    $row = $this->prepareFeaturedImage($row);

    return parent::prepareRow($row);
  }

  // Runs after a row is saved
  public function onPostRowSave(MigratePostRowSaveEvent $event) {

    // Save a redirect from the old url
    $destination = $event->getDestinationIdValues();
    $link = $event->getRow()->getSourceProperty("link");
    $this->createRedirect($link, $destination[0]);

  }

  /**
   * @param $event
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onPostImport($event) {
    // Grab all the nodes that were imported with this migration
    $map_table = $event->getMigration()->getIdMap()->mapTableName();
    $ids = $this->database
      ->select($map_table, 'm')
      ->fields('m', ['sourceid1', 'destid1'])
      ->execute()
      ->fetchAllKeyed();

    // Loop through the nodes
    foreach ($ids as $sourceId => $destId) {
      // Grab the node body

      $node = $this->entitymanager->getStorage('node')->load($destId);
      $body = $node->get('body')->getValue();

      // Strip the base url from the html
      $newBody = $this->stripBaseUrl($body[0]['value']);
      if ($newBody != $body[0]['value']) {
        $body[0]['value'] = $newBody;
        $node->get('body')->setValue($body);
        $node->save();
      }

      // Replace the urls in the body text and store the node when changed
      $replace = $this->checkAndReplaceUrls($body[0]['value']);
      if ($replace) {
        $body[0]['value'] = $replace;
        $node->get('body')->setValue($body);
        $node->save();
      }

    }
  }

  /**
   * @param $body
   *
   * @return mixed
   */
  private function checkAndReplaceUrls($body) {
    $changed = FALSE;
    $doc = new DOMDocument();
    try {
      $doc->loadHTML($body, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    } catch (Exception $e) {
      // When the HTML is really bad, we will end up here
      return $body;
    }
    // Grab all the links and loop through them
    $tags = $doc->getElementsByTagName('a');
    foreach ($tags as $id => $tag) {
      $src = $tag->getAttribute('href');

      // Check if this link had a redirect, if so, replace it in the code
      $redirect = $this->getRedirectByUrl($src);
      if ($redirect) {
        $tag->setAttribute("href", $redirect);
        $changed = TRUE;
      }
    }
    return $changed ? $doc->saveHTML() : FALSE;
  }

  /**
   * @param $url
   *
   * @return bool
   */
  private function getRedirectByUrl($url) {
    if (substr($url, 0, 1) == "/") {
      $url = substr($url, 1);
    }
    $redirect = $this->database
      ->select("redirect", 'r')
      ->fields('r', ['redirect_source__path', 'redirect_redirect__uri'])
      ->condition('redirect_source__path', $url, "LIKE")
      ->execute()
      ->fetchAll();
    foreach ($redirect as $r) {
      $redirectUrl = $r->redirect_redirect__uri;
      // If it is an internal url, check the alias table too
      if (stristr($redirectUrl, "internal:/")) {
        $search = str_replace("internal:", "", $redirectUrl);
        $alias = $this->database
          ->select("url_alias", 'a')
          ->fields('a', ['alias'])
          ->condition('source', $search, "LIKE")
          ->execute()
          ->fetchAll();
        foreach ($alias as $b) {
          return $b->alias;
        }
      }
      return $redirectUrl;
    }
    return FALSE;
  }

  /**
   * Set source properties for the Featured Image
   *
   * @param \Drupal\migrate\Row $row
   *
   * @return \Drupal\migrate\Row
   * @throws \Exception
   */
  public function prepareFeaturedImage(Row $row) {
    $featured_id = $row->getSourceProperty('featured_image_id');

    if ($featured_id) {
      // Get namespaces
      $ns = $this->xml->getNamespaces(TRUE);

      foreach ($this->xml->channel->item as $item) {
        $wp_children = $item->children($ns['wp']);
        $excerpt_children = $item->children($ns['excerpt']);

        if ($wp_children->post_id == $featured_id) {
          // Find the Alt tag in the postmeta elements
          foreach ($wp_children->postmeta as $meta) {
            $meta_children = $meta->children($ns['wp']);

            if ($meta_children->meta_key == '_wp_attachment_image_alt') {
              $row->setSourceProperty('featured_image_alt', $meta_children->meta_value->__toString());
              break;
            }
          }

          // Set the src
          $row->setSourceProperty('featured_image_src', $wp_children->attachment_url->__toString());

          // Set the Image Title/Caption
          $row->setSourceProperty('featured_image_title', $excerpt_children->encoded->__toString());
          break;
        }
      }
    }

    return $row;
  }

  /**
   * @param $body
   *
   * @return string
   */
  private function stripBaseUrl($body) {
    $baseUrl = $this->getBaseUrl();

    $doc = new DOMDocument();
    try {
      $doc->loadHTML($body, LIBXML_HTML_NODEFDTD);
    } catch (Exception $e) {
      // When the HTML is really bad, we will end up here
      return $body;
    }
    $tags = $doc->getElementsByTagName('a');
    foreach ($tags as $id => $tag) {
      $src = $tag->getAttribute('href');
      if (substr($src, 0, strlen($baseUrl)) == $baseUrl) {
        $tag->setAttribute("href", substr($src, strlen($baseUrl)));
      }
    }
    $tags = $doc->getElementsByTagName('img');
    foreach ($tags as $id => $tag) {
      $src = $tag->getAttribute('src');
      if (substr($src, 0, strlen($baseUrl)) == $baseUrl) {
        $tag->setAttribute("src", substr($src, strlen($baseUrl)));
      }
    }

    $html = substr($doc->saveHTML(), 12, -15);
    return $html;
  }

  private function getBaseUrl() {
    $ns = $this->xml->getNamespaces(TRUE);
    $baseUrl = $this->xml->channel->children($ns['wp'])->base_site_url->__toString();
    if (substr($baseUrl, -1, 1) == "/") {
      $baseUrl = substr($baseUrl, 0, -1);
    }
    return $baseUrl;
  }


  /**
   * Create a 301 redirect in Drupal
   *
   * @param $from
   * @param $nid
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|\Drupal\redirect\Entity\Redirect
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  function createRedirect($from, $nid) {
    // Trim slashes from the old URL.
    $from = trim($from, "/");

    if (empty($from)) {
      return FALSE;
    }

    // Create the redirect using the redirect module
    $redirect = new Redirect([], "node");
    $redirect = $redirect->create([
      'redirect_source' => $from,
      'redirect_redirect' => 'internal:/node/' . $nid,
      'language' => 'und',
      'status_code' => '301',
    ]);
    $url = parse_url($from);
    $query = [];
    if (!empty($url['query'])) {
      parse_str($url['query'], $query);
    }
    // We cannot redirect from the homepage, even if it has query parameters
    if (empty($url['path']) || $url['path'] == "/") {
      return FALSE;
    }

    // Check if the redirect is already set as an alias on this node. If so, skip.
    $currentAlias = $this->aliasManager->getAliasByPath('/node/' . $nid, 'en');
    if ($url['path'] == $currentAlias) {
      return FALSE;
    }

    // Set the source path including query parameters
    $redirect->setSource($url['path'], $query);
    // Delete any double entries
    $hash = $redirect->generateHash($redirect->redirect_source->path, (array) $redirect->redirect_source->query, $redirect->language()
      ->getId());
    $this->deleteDoubleEntry($hash);
    // Save the redirect
    $redirect->save();

    return $redirect;
  }

  /**
   * @param $hash
   */
  private function deleteDoubleEntry($hash) {
    $this->database->delete("redirect")
      ->condition("hash", $hash)
      ->execute();
  }

}
