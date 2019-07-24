<?php

namespace Drupal\sitefarm_migrate_cascade\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate;
use Symfony\Component\Config\Definition\Exception\Exception;
use Drupal\redirect\Entity\Redirect;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowDeleteEvent;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Egulias\EmailValidator\EmailValidator;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;


/**
 * Base script for cascade imports
 *
 * @MigrateSource(
 *   id = "sitefarm_cascade_base"
 * )
 */
class SitefarmCascadeBase extends SqlBase implements ContainerFactoryPluginInterface {

  protected $dynamicParents = [];

  /**
   * @var bool
   */
  protected $isTest = FALSE;

  /**
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $containerAwareEventDispatcher;

  /**
   * @var \Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate
   */
  protected $cascadeMigrate;

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $privateTempStorage;

  /**
   * @var \Drupal\menu_link_content\Entity\MenuLinkContent;
   */
  protected $menuLinkContent;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * @var \Egulias\EmailValidator\EmailValidator
   */
  protected $emailValidator;

  /**
   * @var \Drupal\Component\Utility\UrlHelper
   */
  protected $urlHelper;

  /**
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entitytypemanager;

  /**
   * A request stack object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $containerAwareEventDispatcher
   * @param \Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate $cascade_migrate
   * @param \Drupal\user\PrivateTempStoreFactory $privateTempStoreFactory
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Egulias\EmailValidator\EmailValidator $email_validator
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entitytypemanager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, ContainerAwareEventDispatcher $containerAwareEventDispatcher, CascadeMigrate $cascade_migrate, PrivateTempStoreFactory $privateTempStoreFactory, AccountInterface $current_user, EmailValidator $email_validator, EntityTypeManagerInterface $entitytypemanager, RequestStack $request_stack) {
    $this->containerAwareEventDispatcher = $containerAwareEventDispatcher;
    $this->cascadeMigrate = $cascade_migrate;
    $this->privateTempStorage = $privateTempStoreFactory->get("sitefarm_migrate_cascade");
    $this->emailValidator = $email_validator;
    $this->initalizeMenuLinkContent();
    $this->initializeUrlHelper();
    $this->entitytypemanager = $entitytypemanager;
    $this->requestStack = $request_stack;

    # Initiate the post row save function
    $containerAwareEventDispatcher->addListener("migrate.post_row_save", [
      $this,
      'onPostRowSave',
    ]);
    $containerAwareEventDispatcher->addListener("migrate.post_row_delete", [
      $this,
      'onPostRowDelete',
    ]);
    $containerAwareEventDispatcher->addListener("migrate.post_import", [
      $this,
      'onPostImport',
    ]);

    # Get some stored items
    $this->configuration = $configuration;
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state);
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
      $container->get('state'),
      $container->get('event_dispatcher'),
      $container->get('sitefarm_migrate_cascade.cascade_migrate'),
      $container->get('user.private_tempstore'),
      $container->get('current_user'),
      $container->get('email.validator'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * Initializing UrlHelper
   */
  protected function initializeUrlHelper() {
    $this->urlHelper = new UrlHelper();
  }

  /**
   * Initalizing MenuLinkContent
   */
  private function initalizeMenuLinkContent() {
    $this->menuLinkContent = new MenuLinkContent([], "node");
  }

  /**
   * @return array|\IteratorIterator
   * @throws \Drupal\migrate\MigrateException
   */
  public function initializeIterator() {
    return parent::initializeIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'a',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('sitefarm_migrate_cascade_queue', 'a');
    $query->fields('a', [
      'id',
      'path',
      'cascade_id',
      'url',
      'type',
      'article_tid',
      'category_tid',
      'tags',
    ]);

    $query->condition("migration_id", $this->migration->getBaseId(), "=");

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'id' => $this->t('ID'),
      'path' => $this->t('Path'),
      'cascade_id' => "cascade_id",
      'url' => "url",
      'type' => "type",
      'article_tid' => "Article tid",
      'category_tid' => "Category tid",
      'tags' => "Tags",
    ];
    return $fields;
  }


  # Runs after a row is saved
  public function onPostRowSave(MigratePostRowSaveEvent $event) {
  }

  # Runs after a row is deleted
  public function onPostRowDelete($event) {
  }

  # Runs after the migration has ended
  public function onPostImport($event) {
  }

  /**
   * @return array
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  # Create the connection between here and cascade
  protected function cascade_connect() {
    if ($this->cascadeMigrate->initialized() !== TRUE) {
      $this->cascadeMigrate->setSavedCredentials($this->configuration['cascade_username'],
        $this->configuration['cascade_password'], $this->configuration['cascade_url'],
        $this->configuration['cascade_siteid'], $this->configuration['cascade_types']);
      if (!$this->cascadeMigrate->check_cascade_login()) {
        throw new Exception("Not logged in to cascade.");
      }
      # Grab the website config
      $this->cascadeMigrate->get_website_config();
    }
  }

  # Delete item from the queue (to match the amount of items)
  protected function deleteFromQueue($id, $migrateid) {
    if ($this->isTest === TRUE) {
      return FALSE;
    }
    $this->getDatabase()->delete('sitefarm_migrate_cascade_queue')
      ->condition("id", (int) $id)
      ->condition("migration_id", (string) $migrateid)
      ->execute();
  }

  # Create an image
  protected function createImage($data, $filename) {
    $file = file_save_data($data, $filename, FILE_EXISTS_REPLACE);
    # Return the file ID or false if the file didnt save
    return $file ? $file->get("fid")->value : FALSE;
  }

  # Return the whole body of this page
  # We loop through the identifiers to grab all content that might be body content
  protected function get_body($node, $xhtml = "") {
    if (!isset($node->identifier) && !isset($node->structuredDataNodes)) {
      if (!empty($node->xhtml)) {
        return $node->xhtml;
      }
      if (!empty($xhtml)) {
        return $xhtml;
      }
      if (empty($node->structuredDataNodes->structuredDataNode)) {
        return "";
      }
    }
    $return = "";
    if ($node->identifier != "image" && !empty($node->structuredDataNodes->structuredDataNode)) {
      # Assuming we have a stdclass here
      if (!is_array($node->structuredDataNodes->structuredDataNode)) {
        foreach ($node->structuredDataNodes as $node2) {
          $return .= $this->get_body($node2);
        }
      }
      else {
        foreach ($node->structuredDataNodes->structuredDataNode as $node2) {
          $return .= $this->get_body($node2);
        }
      }

      return $return;
    }
    switch ($node->identifier) {
      case "page-content":
      case "main-content-area":
      case "main-content-block":
      case "main-content-row":
      case "repeating-content-block":
      case "maincontent":
      case "content":
      case "message":
      case "message-content":
        if (isset($node->structuredDataNodes)) {
          foreach ($node->structuredDataNodes->structuredDataNode as $node2) {
            $return .= $this->get_body($node2);
          }
        }
        if (!empty($node->text)) {
          $return .= $node->text;
        }
        break;
      case "heading":
        if (!empty($node->text)) {
          $return .= "<h3>" . $node->text . "</h3>";
        }
        break;
      case "image":
        $return .= $this->handleImageFile($node);
        break;
    }
    return $return;
  }

  # Return the whole body of this page
  # We loop through the identifiers to grab all content that might be body content
  protected function getCascadeTitleLoop($node, $xhtml = "") {
    $return = "";
    if (!isset($node->identifier) && !isset($node->structuredDataNodes)) {
      if (empty($node->structuredDataNodes->structuredDataNode)) {
        $return .= "";
      }
    }

    if (!empty($node->structuredDataNodes->structuredDataNode)) {
      # Assuming we have a stdclass here
      if (!is_array($node->structuredDataNodes->structuredDataNode)) {
        foreach ($node->structuredDataNodes as $node2) {
          $return .= $this->getCascadeTitleLoop($node2);
        }
      }
      else {
        foreach ($node->structuredDataNodes->structuredDataNode as $node2) {
          $return .= $this->getCascadeTitleLoop($node2);
        }
      }
    }
    switch ($node->identifier) {
      case "page-content":
      case "main-content-area":
      case "main-content-block":
      case "main-content-row":
      case "maincontent":
      case "content":
        if (isset($node->structuredDataNodes)) {
          foreach ($node->structuredDataNodes->structuredDataNode as $node2) {
            $return .= $this->getCascadeTitleLoop($node2);
          }
        }
        break;
      case "title":
        if (!empty($node->text)) {
          return $node->text;
        }
        break;
    }
    return $return;
  }

  /**
   * @param $node
   *
   * @return string
   */
  protected function handleImageFile($node) {
    if (!isset($node->structuredDataNodes->structuredDataNode)) {
      return "";
    }
    $filepath = FALSE;
    $alt = "";
    $caption = FALSE;

    // Loop through the different data sets
    foreach ($node->structuredDataNodes->structuredDataNode as $img) {
      switch ($img->identifier) {

        case "image-file":
          if (empty($img->filePath) || empty($img->fileId)) {
            return "";
          }
          $filepath = $img->filePath;
          break;
        case "alt-text":
          $alt = empty($img->text) ? "" : $img->text;
          break;
        case "caption":
          $caption = empty($img->text) ? "" : htmlspecialchars($img->text);
          break;
      }
    }

    if (!$filepath) {
      return "";
    }
    if (empty($caption)) {
      return '<br/><img alt="' . $alt . '" src="/' . $filepath . '"/><br/>';
    }
    return "<figure role=\"group\" class=\"caption caption-img\">
                <img alt=\"" . $alt . "\" src=\"/" . $filepath . "\"/>
                <figcaption>" . $caption . "</figcaption>
            </figure>";
  }

  # Returns if it should be places in the navigation
  function getIncludeInNavValue($data) {
    if (!in_array("menu", $this->cascadeMigrate->getImportTypes())) {
      return FALSE;
    }
    if (!isset($data->asset->page->metadata->dynamicFields->dynamicField)) {
      return FALSE;
    }
    foreach ($data->asset->page->metadata->dynamicFields->dynamicField as $field) {
      if (isset($field->name) && $field->name == "include-in-primary-nav") {
        return $field->fieldValues->fieldValue->value == "Yes";
      }
    }
    return FALSE;
  }

  # Returns the path alias. This is the path cascade was using for this page
  function getAlias($path, $contentType) {
    $allowed = ["html", "htm", "shtml", "cfm", "asp", "aspx", "php"];

    # Crude method to get the extension used by cascade
    $ext = explode(".", $contentType);
    if (!isset($ext[1])) {
      $ext = explode(" ", $contentType);
    }

    $ext = strtolower(array_pop($ext));

    if (in_array($ext, $allowed)) {
      # If we have an index file, return index.ext and the folder
      if (substr($path, -5, 5) == "index") {
        return [$path . "." . $ext, substr($path, 0, -5), $path];
      }
      return [$path . "." . $ext, $path];
    }

    # We tried, but found nothing, default to html
    if (substr($path, -5, 5) == "index") {
      return [$path . ".html", substr($path, 0, -5), $path];
    }

    return [$path . ".html", $path];
  }

  # Create a 301 redirect in Drupal
  function createRedirect($from, $nid) {
    if (empty($from)) {
      return FALSE;
    }
    # Add multiple if needed
    if (is_array($from)) {
      foreach ($from as $u) {
        $this->createRedirect($u, $nid);
      }
      return;
    }
    $from = trim($from, "/");

    // Add another redirect for urls without the slash at the end
    if (substr($from, -1, 1) == "/") {
      $this->createRedirect(substr($from, 0, -1), $nid);
    }

    # Delete possible double
    $this->removeRedirect($from);

    # Create the redirect using the redirect module
    $redirect = new Redirect([], "node");
    $redirect->create([
      'redirect_source' => $from,
      'redirect_redirect' => 'entity:node/' . $nid,
      'language' => 'und',
      'status_code' => '301',
    ])->save();
  }

  # Remove the 301 redirect by path. Cache needs clearing after this
  function removeRedirect($uri, $urlisnid = FALSE) {

    # Could be an index file, if so, remove both redirects
    if (is_array($uri)) {
      foreach ($uri as $u) {
        $this->removeRedirect($u, $urlisnid);
      }
      return;
    }

    # Handling the redirect by nid instead of path
    if ($urlisnid !== FALSE) {
      $this->getDatabase()->delete("redirect")
        ->condition('redirect_source__path', "entity:node/" . (int) $uri, "LIKE")
        ->execute();
      return;
    }

    # Strip it straight out of the database
    $this->getDatabase()->delete("redirect")
      ->condition('redirect_source__path', $uri, "LIKE")
      ->execute();
  }

  # Create a menu item
  # Childeren have to be created at the end, because parent might not exist yet
  function createMenuItem($title, $nid, $legacy_path, $postMigrate = FALSE) {

    # Check the database for items with the same title
    # Simply skip if there is one to prevent double items
    if ($this->menuItemExists($title)) {
      return;
    }
    $menuId = $this->getMenuIdByNid($nid);
    $menuParent = $this->getMenuParentPath($legacy_path);
    $menuDepth = count(explode("/", $menuParent));

    # Check if dynamicparents are there
    if (empty($this->dynamicParents)) {
      $this->dynamicParents = !empty($this->privateTempStorage->get('dynamicParents')) ?
        $this->privateTempStorage->get('dynamicParents') : [];
    }

    # At the end of the migration we can add the children
    if ($postMigrate) {
      $parent = $this->getMenuParentNid($menuParent);
      $parent = $this->getMenuIdByNid($parent);
      if (!empty($parent)) {
        $parent = $this->menuLinkContent->load($parent);
      }

      if ($parent) {
        $menu_link = $this->menuLinkContent->create([
          'title' => $title,
          'link' => ['uri' => 'entity:node/' . $nid],
          'menu_name' => 'main',
          'parent' => $parent->getPluginId(),
        ]);
        $menu_link->save();
      }
      else {
        # We didnt find a parent, meaning there is no index for this page.
        # Lets create a simple item that links to this same node
        if (!isset($this->dynamicParents[$menuParent])) {
          # Try to find the item by name
          $this->dynamicParents[$menuParent] = $this->getMenuParentIdByName(ucfirst($menuParent));

          # Nothing found, we just create it then
          if (!$this->dynamicParents[$menuParent]) {
            $parent = $this->menuLinkContent->create([
              'title' => ucfirst($menuParent),
              'link' => ['uri' => 'entity:node/' . $nid],
              'menu_name' => 'main',
              'parent' => NULL,
            ]);
            $parent->save();
            $this->dynamicParents[$menuParent] = $parent->getPluginId();
          }
        }
        $menu_link = $this->menuLinkContent->create([
          'title' => $title,
          'link' => ['uri' => 'entity:node/' . $nid],
          'menu_name' => 'main',
          'parent' => $this->dynamicParents[$menuParent],
        ]);
        $menu_link->save();
      }
      # Save the dynamicparents in the session
      $this->privateTempStorage->set('dynamicParents', $this->dynamicParents);
      return;
    }
    # If this is a parent item
    if (is_array($legacy_path) || $menuDepth === 0) {
      if (!$menuId) {

        $menu_link = $this->menuLinkContent->create([
          'title' => $title,
          'link' => ['uri' => 'entity:node/' . $nid],
          'menu_name' => 'main',
          'expanded' => TRUE,
        ]);
        $menu_link->save();
      }
    }

    # If this is an item with just one parent save to to create at the end
    if (!is_array($legacy_path) && $menuDepth === 1 && strlen($menuParent) > 1) {
      if (!$menuId) {
        $kids = $this->privateTempStorage->get('menuChildren');
        $kids[] = [
          $title,
          $nid,
          $legacy_path,
        ];
        $this->privateTempStorage->set('menuChildren', $kids);
      }
    }

    # Save the dynamicparents in the session
    $this->privateTempStorage->set('dynamicParents', $this->dynamicParents);
  }

  # Find the menu parent
  function getMenuParentPath($path) {
    if (is_array($path)) {
      $path = $path[1];
    }
    $path = explode("/", $path);
    unset($path[count($path) - 1]);
    $path = implode("/", $path);
    return $path;
  }

  # Find the menu parent nid
  function getMenuParentNid($path) {
    if (substr($path, 0, -1) != "/") {
      $path = $path . "/";
    }
    $result = $this->getDatabase()->select('redirect', "a")
      ->condition('redirect_source__path', $path, "LIKE")
      ->fields("a", ["redirect_redirect__uri"])
      ->execute()
      ->fetchField();
    return str_replace("entity:node/", "", $result);
  }

  # Return if this nid exists in a menu
  function getMenuIdByNid($nid) {
    $result = $this->getDatabase()->select('menu_link_content_data', "a")
      ->fields("a", ["id"])
      ->condition('link__uri', "entity:node/" . (int) $nid, "=")
      ->execute()
      ->fetchField();
    return $result;
  }

  # Get a menu id by name
  function getMenuParentIdByName($name) {
    # Grab the menu item from the database
    $result = $this->getDatabase()->select('menu_link_content_data', "a")
      ->condition('title', $name, "LIKE")
      ->fields("a", ["id"])
      ->execute()
      ->fetchField();
    if (!$result) {
      return FALSE;
    }
    # Load the menu item and return the ID
    $id = $this->menuLinkContent->load($result);
    return $id ? $id->getPluginId() : FALSE;
  }

  # Check if this menu item exists
  function menuItemExists($name) {
    $result = $this->getDatabase()->select('menu_link_content_data', "a")
      ->condition('title', $name, "LIKE")
      ->fields("a", ["id"])
      ->execute()
      ->fetchField();
    return $result ? TRUE : FALSE;
  }

  # Delete from Menu
  function deleteFromMenuById($id) {
    if ($id !== FALSE && $id > 0) {
      $entity = $this->menuLinkContent->load($id);
      if ($entity) {
        $entity->delete();
      }
    }
  }

  # Delete from menu By nid
  function removeMenuItemByNid($nid) {
    $id = $this->getMenuIdByNid($nid);
    $this->deleteFromMenuById($id);
  }

}