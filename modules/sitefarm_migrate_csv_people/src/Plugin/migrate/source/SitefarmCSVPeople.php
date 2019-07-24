<?php

namespace Drupal\sitefarm_migrate_csv_people\Plugin\migrate\source;

use Drupal;
use Drupal\migrate\Row;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\migrate_source_csv\Plugin\migrate\source\CSV;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\cas\Service\CasUserManager;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\State\StateInterface;
use Drupal\user\UserStorageInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Egulias\EmailValidator\EmailValidator;

/**
 * Source for importing people CSV
 *
 * @MigrateSource(
 *   id = "sitefarm_csv_people"
 * )
 */
class SitefarmCSVPeople extends CSV implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\cas\Service\CasUserManager
   */
  protected $casUserManager;

  /**
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * @var array
   */
  protected $configuration;

  /**
   * @var \Egulias\EmailValidator\EmailValidator
   */
  protected $emailValidator;

  /**
   * SitefarmCSVPeople constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $containerAwareEventDispatcher
   * @param \Drupal\cas\Service\CasUserManager $casUserManager
   * @param \Drupal\user\UserStorageInterface $userStorage
   * @param \Drupal\Core\Entity\EntityStorageInterface $entityStorage
   * @param \Drupal\Core\Database\Connection $database
   * @param \Egulias\EmailValidator\EmailValidator $emailValidator
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, ContainerAwareEventDispatcher $containerAwareEventDispatcher, CasUserManager $casUserManager, UserStorageInterface $userStorage, EntityStorageInterface $entityStorage, Connection $database, EmailValidator $emailValidator, FileSystem $fileSystem) {
    $this->casUserManager = $casUserManager;
    $this->userStorage = $userStorage;
    $this->database = $database;
    $this->entityStorage = $entityStorage;
    $this->emailValidator = $emailValidator;

    # Initiate the post row save function
    $containerAwareEventDispatcher->addListener("migrate.post_row_save", [
      $this,
      'onPostRowSave',
    ]);
    $containerAwareEventDispatcher->addListener("migrate.pre_row_delete", [
      $this,
      'onPreRowDelete',
    ]);

    // Create folders
    if (!file_exists("public://files/person")) {
      $fileSystem->mkdir("public://files/person", NULL, TRUE);
    }
    if (!file_exists("public://images/person")) {
      $fileSystem->mkdir("public://images/person", NULL, TRUE);
    }


    // Get some stored items
    $this->configuration = $configuration;
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\migrate\Plugin\MigrationInterface|NULL $migration
   *
   * @return static
   * @throws \Drupal\migrate\MigrateException
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('state'),
      $container->get('event_dispatcher'),
      $container->get('cas.user_manager'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get("database"),
      $container->get("email.validator"),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {

    $hidden = $row->getSourceProperty("hide_from_dir") == 1 || $row->getSourceProperty("hide_from_dir") == "true";
    $row->setSourceProperty("hide_from_dir", $hidden);
    $featured = $row->getSourceProperty("featured") == 1 || $row->getSourceProperty("featured") == "true";
    $row->setSourceProperty("featured", $featured);

    $row->setSourceProperty("uid", NULL);

    $row->setSourceProperty("person_type", trim($row->getSourceProperty("person_type")));

    // Filter the tags
    $tags = explode("|", $row->getSourceProperty("tags"));
    foreach ($tags as &$tag) {
      $tag = trim($tag);
    }
    $row->setSourceProperty("tags", $tags);

    $email = $row->getSourceProperty("email");
    $cas = $row->getSourceProperty("cas");
    if (!empty($cas)) {
      $email = explode("|", $email);
      $user = $this->createUser($cas, $email[0]);
      if ($user) {
        // Set the author of this person to the new user we just created
        $row->setSourceProperty("uid", $user->id());
      }
    }

    $return = parent::prepareRow($row);
    return $return;
  }

  /**
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) {

    // Save a redirect from the old url
    $destination = $event->getDestinationIdValues();
    $row = $event->getRow();
    $url = $row->getSourceProperty("url");

    // Create a redirect to the new destination from the old url
    if (!empty($url)) {
      $this->createRedirect($url, $destination[0]);
    }

    // Set the image alt tag
    if (!empty($row->getSourceProperty("portrait_image"))) {
      // Set the image alt and replace double spaces with one space
      $alt = str_replace("  ", " ", $row->getSourceProperty("first_name")
        . " " . $row->getSourceProperty("middle_name")
        . " " . $row->getSourceProperty("last_name"));
      $node = $this->entityStorage->load($destination[0]);
      $node->field_sf_primary_image[0]->alt = $alt;
      $node->save();
    }

  }

  /**
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onPreRowDelete(MigrateRowDeleteEvent $event) {
    // Delete associated user
    $nid = $event->getDestinationIdValues()['nid'];
    $node = $this->entityStorage->load($nid);
    if ($node) {
      $uid = $node->get('uid')->getValue();
      if (isset($uid[0]['target_id']) && $uid[0]['target_id'] > 1) {
        $this->deleteUser($uid[0]['target_id']);
      }
    }
  }

  /**
   * @param $cas
   * @param $email
   *
   * @return bool|\Drupal\user\UserInterface
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createUser($cas, $email) {
    // Check if we have a user with this cas ID already
    if ($this->casUserManager->getUidForCasUsername($cas)) {
      return FALSE;
    }
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->create();
    $user->setPassword(\user_password(30));
    $user->enforceIsNew();
    if ($this->emailValidator->isValid($email)) {
      $user->setEmail($email);
    }
    $user->setUsername($cas);
    $user->activate();
    $user->addRole('contributor');
    $user->save();
    $this->casUserManager->setCasUsernameForAccount($user, $cas);
    return $user;
  }

  /**
   * @param $uid
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function deleteUser($uid) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($uid);
    // Don't delete user when user has logged in
    if ($user && $user->getLastLoginTime() == 0) {
      $this->deleteUserImage($user);
      $user->delete();
    }
  }

  /**
   * @param $user
   */
  protected function deleteUserImage($user) {
    $t = 1;
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
  protected function createRedirect($from, $nid) {
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