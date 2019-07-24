<?php

namespace Drupal\sitefarm_migrate_cascade\Controller;

use Drupal\sitefarm_migrate_cascade\CascadeApi;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\sitefarm_migrate\SiteFarmMigrate;
use Drupal\Core\Url;
use Drupal;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\Core\Database\Connection;
use stdClass;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\File\FileSystem;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Site\Settings;

class CascadeMigrate {

  /**
   * @var bool|\Drupal\sitefarm_migrate_cascade\CascadeApi
   */
  protected $cascadeApi = FALSE;

  /**
   * @var bool
   */
  protected $error = FALSE;

  /**
   * @var bool|string
   */
  private $username = FALSE;

  /**
   * @var bool|string
   */
  private $password = FALSE;

  /**
   * @var bool|string
   */
  private $cascade_url = FALSE;

  /**
   * @var string
   */
  protected $site_id;

  /**
   * @var bool|mixed
   */
  private $rootfolder = FALSE;

  /**
   * @var bool|string
   */
  private $rootfolderid = FALSE;

  /**
   * @var array
   */
  protected $import_types = [];

  /**
   * @var bool|mixed
   */
  public $cascade_siteconfig = FALSE;

  /**
   * @var bool|string
   */
  public $site_url = FALSE;

  /**
   * @var bool|string
   */
  public $cascade_site_name = FALSE;

  /**
   * @var bool
   */
  private $initialized = FALSE;

  /**
   * @var string
   */
  private $migrationId;

  /**
   * Sitefarm Migrate
   *
   * @var \Drupal\sitefarm_migrate\SiteFarmMigrate
   */
  protected $sitefarmMigrate;

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $privateTempStorage;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  protected $sessionManager;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configmanager;

  /**
   * @var Drupal\Core\File\FileSystem
   */
  protected $filesystem;

  /**
   * @var Symfony\Component\Yaml\Yaml
   */
  protected $yaml;

  /**
   * @string $site_path
   */
  protected $site_path;

  /**
   * @var $settings
   */


  /**
   * @param \Drupal\sitefarm_migrate_cascade\CascadeApi $cascade_api
   * @param \Drupal\user\PrivateTempStoreFactory $privateTempStorage
   * @param \Drupal\sitefarm_migrate\SiteFarmMigrate $siteFarmMigrate
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\Core\Config\ConfigFactory $configmanager
   * @param \Drupal\Core\File\FileSystem $filesystem
   * @param $site_path
   * @param \Drupal\Core\Site\Settings $settings
   */
  public function __construct(CascadeApi $cascade_api, PrivateTempStoreFactory $privateTempStorage, SiteFarmMigrate $siteFarmMigrate, Connection $database, SessionManagerInterface $session_manager, AccountInterface $current_user, ConfigFactory $configmanager, FileSystem $filesystem, $site_path, Settings $settings) {
    $this->cascadeApi = $cascade_api;
    $this->privateTempStorage = $privateTempStorage->get("sitefarm_migrate_cascade");
    $this->sitefarmMigrate = $siteFarmMigrate;
    $this->database = $database;
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;
    $this->configmanager = $configmanager->get("sitefarm_migrate_cascade.settings");
    $this->filesystem = $filesystem;
    $this->yaml = $this->getYaml();
    $this->site_path = $site_path;
    $this->settings = $settings;

    # Make sure we have a session running for use with Drush
    $this->setSessionAndUser();

    # Get all stored variables and start a session when needed
    $this->get_session_vars();
  }

  /**
   * @return \Symfony\Component\Yaml\Yaml
   */
  private function getYaml() {
    return new Yaml();
  }

  /**
   * @return bool
   */
  public function initialized() {
    return $this->initialized;
  }

  /**
   * Retrieve all stored credentials from this session
   *
   * @param bool|FALSE $second_run Defines if we are running for the second time
   *
   * @throws \Drupal\user\TempStoreException
   */
  private function get_session_vars($second_run = FALSE) {
    if (!empty($this->privateTempStorage->get('username'))) {
      if (!empty($this->privateTempStorage->get('username')) &&
        !empty($this->privateTempStorage->get('password')) &&
        !empty($this->privateTempStorage->get('cascade_url'))
      ) {

        $this->setConnectionDetails(
          $this->safe_var($this->privateTempStorage->get('username')),
          $this->simple_decrypt($this->privateTempStorage->get('password')),
          $this->privateTempStorage->get('cascade_url'));
      }
      if (!empty($this->privateTempStorage->get('site_id'))) {
        $this->setConnectionDetails(
          $this->safe_var($this->privateTempStorage->get('username')),
          $this->simple_decrypt($this->privateTempStorage->get('password')),
          $this->privateTempStorage->get('cascade_url'),
          $this->privateTempStorage->get('site_id'));
      }
    }
    else {
      $this->privateTempStorage->set('username', $this->configmanager->get("username"));
      $this->privateTempStorage->set('password', $this->simple_encrypt($this->configmanager->get("password")));
      $this->privateTempStorage->set('cascade_url', $this->configmanager->get("cascade_url"));
      # Call this method again to set the other variables we might need
      if ($second_run === FALSE) {
        $this->get_session_vars(TRUE);
      }
    }
  }

  /**
   * Returns a full path. Specify a file relative to the docroot.
   *
   * @param $path
   *
   * @return string
   */
  private function getRelativePathToRoot($path) {
    $root = explode("/", DRUPAL_ROOT);
    $path = explode("../", $path);
    $down = count($path) - 1;
    $root = array_splice($root, 0, -$down);
    return implode("/", $root) . "/" . array_pop($path);
  }

  /**
   * Sets user as uid 1 if we are working from the commandline (drush)
   * This so we can use the tempstore functions
   */
  private function setSessionAndUser() {
    if (PHP_SAPI == 'cli' && $this->currentUser->isAnonymous() && !isset($_SESSION['session_started'])) {
      $_SESSION['session_started'] = TRUE;

      # Load user 1
      $user = new AccountProxy();
      $user->setInitialAccountId(1);
      $user->getAccount();

      # Inject the user in the current user
      $this->currentUser->setAccount($user);
      $this->sessionManager->start();
    }
  }

  /**
   * Retrieve and set all saved credentials
   *
   * @param bool|FALSE $username
   * @param bool|FALSE $password
   * @param bool|FALSE $url
   * @param bool|FALSE $siteid
   * @param bool|FALSE $types
   */
  public function setSavedCredentials($username = FALSE, $password = FALSE, $url = FALSE,
                                      $siteid = FALSE, $types = FALSE) {
    if (empty($this->privateTempStorage->get('username'))) {
      exit("No migration config found");
    }

    # Did we pass parameters? If not, use the settings
    $username = $username ? $username : $this->privateTempStorage->get('username');
    $password = $password ? $password : $this->privateTempStorage->get('password');
    $url = $url ? $url : $this->privateTempStorage->get('cascade_url');
    $siteid = $siteid ? $siteid : $this->privateTempStorage->get('site_id');
    $types = $types ? $types : $this->privateTempStorage->get('content_types');

    $this->setConnectionDetails($username,
      $this->simple_decrypt($password),
      $url,
      $siteid);
    $this->import_types = $types;
    $this->initialized = TRUE;
  }

  /**
   * @return array
   */
  public function getImportTypes() {
    return $this->import_types ?: [];
  }

  /**
   * Set all variables used to connect to cascase
   *
   * @param $user
   * @param $password
   * @param $cascade_url
   * @param bool|FALSE $siteid
   */
  public function setConnectionDetails($user, $password, $cascade_url, $siteid = FALSE) {
    $this->username = $user;
    $this->password = $password;
    $this->cascade_url = $cascade_url;
    $this->site_id = $siteid;
    if (!$this->cascadeApi->authenticate($this->username, $this->password, $this->cascade_url)) {
      $this->error = "Could not authenticate with Cascade. Check the credentials.";
    };
  }

  /**
   * @param $var
   *
   * @return string
   */
  private function safe_var($var) {
    return htmlspecialchars($var);
  }

  /**
   * @return bool | string
   */
  public function haserror() {
    return $this->error;
  }

  /**
   * We cannot use Settings::getHashSalt because it is a static function
   * Recreate the settings, and grab the salt from there...
   * calling settings using: new Settings([]) is useless because nothing will
   * be set
   *
   * @return string | bool
   */
  private function getHashSalt() {
    return $this->settings->getHashSalt();
  }

  /**
   * Very simple encryption method to store the password
   * Not very secure, but better than plaintext
   *
   * @param $text
   *
   * @return string
   */
  function simple_encrypt($text) {
    $salt = $this->getHashSalt();
    $salt = substr($salt, 0, 32);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    return trim(base64_encode(openssl_encrypt($text, 'aes-256-cbc', $salt, 0, $iv) . "::" . base64_encode($iv)));
  }

  /**
   * Very simple decryption method to retrieve the store password
   *
   * @param $text
   *
   * @return string
   */
  function simple_decrypt($text) {
    $salt = $this->getHashSalt();
    $salt = substr($salt, 0, 32);
    $parts = explode('::', base64_decode($text), 2);
    return trim(openssl_decrypt($parts[0], 'aes-256-cbc', $salt, 0, base64_decode($parts[1])));
  }

  /**
   * Check if we can login to cascade, and if so, save the handler
   *
   * @return mixed|bool
   */
  public function check_cascade_login() {
    if ($this->username !== FALSE && $this->password !== FALSE && $this->cascade_url !== FALSE) {
      return $this->cascadeApi->authenticate($this->username, $this->password, $this->cascade_url);
    }
    return FALSE;
  }

  /**
   * Check if we have all nessesary info
   *
   * @param bool|TRUE $login
   * @param bool|TRUE $site_id
   *
   * @return bool|\Symfony\Component\HttpFoundation\RedirectResponse
   */
  function do_checks($login = TRUE, $site_id = TRUE) {
    if ($login === TRUE) {
      # Check if we are logged in to cascade
      if ($this->check_cascade_login() === FALSE) {
        drupal_set_message(t("Login information incorrect, please login again"), "error");
        return new RedirectResponse(new Url("sitefarm.cascade_migrate.login"));
      }
    }
    if ($site_id === TRUE) {
      # Check if we have a site_id
      if ($this->site_id === FALSE) {
        drupal_set_message(t("Select a website"), "error");
        return new RedirectResponse(new Url("sitefarm.cascade_migrate.site_select"));
      }
    }
    return TRUE;
  }

  /**
   * Retrieve all websites from cascase
   *
   * @return array|bool
   */
  public function get_website_list() {
    # Check if we are logged in to cascade
    $this->do_checks(TRUE, FALSE);

    if ($this->cascadeApi === FALSE) {
      return FALSE;
    }
    $sites = $this->cascadeApi->listSites();
    if (!isset($sites->success) || $sites->success != "true") {
      drupal_set_message("Not able to retrieve sites. Message: " . @$sites->message);
      return FALSE;
    }
    $return = [];
    foreach ($sites->sites->assetIdentifier as $site) {
      if (isset($site->path->siteId, $site->path->path)) {
        if ($this->shouldImportSite($site->path->path)) {
          $return[$site->path->siteId] = $site->path->path;
        }
      }
    }
    return $return;
  }

  /**
   * Query Cascade using a specific ID
   *
   * @param $type
   * @param $cascade_id
   *
   * @return bool|mixed
   */
  public function queryCascadeById($type, $cascade_id) {
    if (!$this->cascadeApi) {
      $this->check_cascade_login();
    }
    $id = $this->cascadeApi->identifierById($type, $cascade_id);
    return $this->cascadeApi->read($id);
  }

  /**
   * Query Cascade by path
   *
   * @param $type
   * @param $path
   * @param null $site_name
   *
   * @return bool|mixed
   */
  public function queryCascadeByPath($type, $path, $site_name = NULL, $dontsetcache = FALSE) {
    if (!$this->cascadeApi) {
      $this->check_cascade_login();
    }
    if (empty($site_name)) {
      $site_name = $this->cascade_site_name;
    }
    $id = $this->cascadeApi->identifierByPath($type, $path, $site_name);
    return $this->cascadeApi->read($id, FALSE, $dontsetcache);
  }

  /**
   * Retrieve all the root folders of this website
   *
   * @param bool|string $site_id
   *
   * @return array|bool
   */
  public function getSiteRootFolders($site_id = FALSE) {
    $this->do_checks(TRUE, FALSE);
    if ($this->cascadeApi === FALSE) {
      return FALSE;
    }
    $site_id = $site_id ?: $this->site_id;

    # Get the root folder
    $rootfolder = $this->get_website_root($site_id);

    $folders = [];
    $folders['/'] = "basic_page";

    # Loop through the folders and save them
    foreach ($rootfolder->folder->children->child as $child) {
      if (isset($child->path->path) && $child->type == "folder") {
        $type = substr($child->path->path, 0, 1) == "_" ? "no_import" : "sf_page";
        $folders[$child->path->path] = $type;
      }
    }

    return $folders;
  }

  /**
   * Grab the website configuration
   *
   * @param bool|FALSE $site_id
   *
   * @return bool|mixed
   */
  public function get_website_config($site_id = FALSE) {
    if ($site_id === FALSE) {
      $site_id = $this->site_id;
    }

    # Read the site config
    $identifier = $this->cascadeApi->identifierById("site", $site_id);
    $siteconfig = $this->cascadeApi->read($identifier);
    $this->cascade_siteconfig = $siteconfig;
    $this->site_url = $siteconfig->asset->site->url;
    $this->cascade_site_name = $siteconfig->asset->site->name;
    return $siteconfig;
  }

  /**
   * Return the websites root folder
   *
   * @param $site_id
   *
   * @return bool|mixed
   */
  public function get_website_root($site_id) {
    $siteconfig = $this->get_website_config($site_id);

    # Check if this site has a root folder
    if (empty($siteconfig->asset->site->rootFolderId)) {
      drupal_set_message(t("This website does not seem to have a folder structure"), "error");
      return FALSE;
    }

    # Get the root folder identifier and read the folder structure
    $this->rootfolderid = $siteconfig->asset->site->rootFolderId;
    $identifier = $this->cascadeApi->identifierById("folder", $this->rootfolderid);
    $this->rootfolder = $this->cascadeApi->read($identifier);

    # Check for files in the root folder
    if (!isset($this->rootfolder->success) || $this->rootfolder->success != "true" ||
      empty($this->rootfolder->asset->folder->children->child)
    ) {
      drupal_set_message(t("No files found in the root folder" . (!empty($this->rootfolder->message) ? ": "
          . $this->rootfolder->message : "")), "error");
      $this->rootfolder = FALSE;
      return FALSE;
    }
    $this->rootfolder = $this->rootfolder->asset;
    return $this->rootfolder;
  }

  /**
   * Simple check to see if the setup was completed
   *
   * @return bool
   */
  public function setupComplete() {
    $status = $this->privateTempStorage->get("content_types");
    return !empty($status);
  }

  /**
   * Generate a Pages migration
   *
   * @param $migrationId
   * @param $content_type
   *
   * @return bool|\Drupal\migrate_plus\Entity\Migration
   */
  public function generatePagesMigration($migrationId, $content_type) {

    $content_type_label = ucfirst(str_replace("sf_", "", $content_type));
    $label = in_array("menu", $this->import_types) ? "Cascade Pages and MenuItems (" . $content_type_label . ")" : "Cascade Pages (" . $content_type_label . ")";

    $migration = $this->sitefarmMigrate->createMigration('sf_cascade_pages', $migrationId);
    if ($migration === FALSE) {
      return FALSE;
    }
    # Set label and cascade credentials
    $migration->set("label", $label);
    $source = $migration->get("source");
    $source['cascade_username'] = $this->username;
    $source['cascade_password'] = $this->simple_encrypt($this->password);
    $source['cascade_siteid'] = $this->site_id;
    $source['cascade_url'] = $this->cascade_url;
    $source['cascade_types'] = $this->import_types;
    $migration->set("source", $source);

    # Set the content type
    $process = $migration->get("process");
    $process['type'][0]['default_value'] = $content_type;
    $migration->set("process", $process);

    return $this->sitefarmMigrate->saveMigration($migration);
  }

  function generateAttachmentsMigration($migrationId) {
    $migration = $this->sitefarmMigrate->createMigration('sf_cascade_attachments', $migrationId);
    if ($migration === FALSE) {
      return FALSE;
    }
    # Set label and cascade credentials
    $migration->set("label", "Cascade Page Attachments (run page migrations first)");
    $source = $migration->get("source");
    $source['cascade_username'] = $this->username;
    $source['cascade_password'] = $this->simple_encrypt($this->password);
    $source['cascade_siteid'] = $this->site_id;
    $source['cascade_url'] = $this->cascade_url;
    $source['cascade_types'] = $this->import_types;
    $migration->set("source", $source);

    return $this->sitefarmMigrate->saveMigration($migration);
  }

  /**
   * Generate a people migration
   *
   * @param $migrationId
   *
   * @return bool|\Drupal\migrate_plus\Entity\Migration
   */
  public function generatePeopleMigration($migrationId) {

    $label = "Cascade People";

    $migration = $this->sitefarmMigrate->createMigration('sf_cascade_people', $migrationId);
    if ($migration === FALSE) {
      return FALSE;
    }
    $migration->set("label", $label);
    $source = $migration->get("source");
    $source['cascade_username'] = $this->username;
    $source['cascade_password'] = $this->simple_encrypt($this->password);
    $source['cascade_siteid'] = $this->site_id;
    $source['cascade_url'] = $this->cascade_url;
    $source['cascade_types'] = $this->import_types;
    $migration->set("source", $source);

    return $this->sitefarmMigrate->saveMigration($migration);
  }

  /**
   * Generate a blocks migration
   *
   * @param $migrationId
   *
   * @return bool|\Drupal\migrate_plus\Entity\Migration
   */
  public function generateBlocksMigration($migrationId) {

    $label = "Cascade Blocks";

    $migration = $this->sitefarmMigrate->createMigration('sf_cascade_blocks', $migrationId);
    if ($migration === FALSE) {
      return FALSE;
    }
    $migration->set("label", $label);
    $source = $migration->get("source");
    $source['cascade_username'] = $this->username;
    $source['cascade_password'] = $this->simple_encrypt($this->password);
    $source['cascade_siteid'] = $this->site_id;
    $source['cascade_url'] = $this->cascade_url;
    $source['cascade_types'] = $this->import_types;
    $migration->set("source", $source);

    return $this->sitefarmMigrate->saveMigration($migration);
  }

  /**
   * Generates a queue
   *
   * @param bool|FALSE $folders
   * @param $content_type
   * @param $cascade_type
   * @param bool|FALSE $article_types
   * @param bool|FALSE $categories
   *
   * @return string
   */
  public function generateQueue($folders = FALSE, $content_type, $cascade_type, $article_types = FALSE, $categories = FALSE, $tags = FALSE) {
    $this->setSavedCredentials();
    # Check if everything is set
    $this->do_checks(TRUE, FALSE);

    $this->migrationId = "cascade_" . substr($content_type, 3, 7) . "_" . date("mdy_His");

    # Get the root folder
    $rootfolder = $this->get_website_root($this->site_id);

    $this->generate_loop($rootfolder->folder->children->child, $folders, $cascade_type, $article_types, $categories, $tags);

    return $this->migrationId;
  }

  /**
   * Loop through the folders, used by generateQueue()
   *
   * @param $folder
   * @param bool $folders
   * @param array $cascade_type
   * @param bool $article_tid
   * @param bool $categories
   * @param bool $tags
   * @param bool $in_folder
   *
   * @return bool
   * @throws \Exception
   */

  private function generate_loop($folder, $folders = FALSE, array $cascade_type, $article_tid = FALSE, $categories = FALSE, $tags = FALSE, $in_folder = FALSE) {
    if (!is_array($folder) || empty($folder)) {
      return FALSE;
    }

    $child_types_summary = [];

    # Loop through the folders
    foreach ($folder as $child) {
      if (isset($child->path->path) && isset($child->type)) {
        $child_types_summary[$child->type] = $child->type;
        switch ($child->type) {
          case "folder":
            # Check if we should import this folder
            if ($folders === FALSE || $in_folder === TRUE || in_array($child->path->path, $folders)) {

              # Set the article type path
              if (!$in_folder && is_array($article_tid)) {
                $article_tid_f = $article_tid[$child->path->path] ?: 0;
                $category_f = $categories[$child->path->path] ?: 0;
              }
              else {
                $article_tid_f = $article_tid;
                $category_f = $categories;
              }

              # Grab the folder info and loop through this function again
              $id = $this->cascadeApi->identifierById("folder", $child->id);
              $blocks = $this->cascadeApi->read($id);

              if (empty($blocks->asset->folder->children->child)) {
                continue;
              }
              # When there is a single item in a folder, turn it into an array
              if (!is_array($blocks->asset->folder->children->child)) {
                $child = $blocks->asset->folder->children->child;
                $blocks->asset->folder->children->child = [];
                $blocks->asset->folder->children->child[] = $child;
              }
              $this->generate_loop($blocks->asset->folder->children->child, $folders, $cascade_type, $article_tid_f, $category_f, $tags, TRUE);
            }
            break;
          default:
            # Skip types we don't need.
            if (!in_array($child->type, $cascade_type)) {
              $dontskip = FALSE;
              foreach ($cascade_type as $type) {
                # If we find a block, simply use all block definitions to begin with
                if (stristr($type, "block") && stristr($child->type, "block")) {
                  $dontskip = TRUE;
                }
              }
              if ($dontskip !== TRUE) {
                continue;
              }
            }

            $base_path = explode("/", $child->path->path);
            $handler = new stdClass();
            $handler->path = $child->path->path;
            $handler->cascade_id = $child->id;
            $handler->type = $child->type;
            $handler->url = "";
            $handler->tags = $tags[$base_path[0]];
            # When we are in the root dir, make sure we grab the right article_id
            if (is_array($article_tid)) {
              $article_tid = $article_tid['/'] ?: 0;
            }
            if (is_array($categories)) {
              $categories = $categories['/'] ?: 0;
            }
            $handler->article_tid = $article_tid;
            $handler->category_tid = $categories;
            $this->add_to_queue($handler);
            break;
        }
      }
    }
    return TRUE;
  }

  /**
   * Add an item to the queue
   *
   * @param $item
   *
   * @throws \Exception
   * return void
   */
  public function add_to_queue($item) {
    # Delete instances with same ID to prevent double entry
    $this->database->delete('sitefarm_migrate_cascade_queue')
      ->condition("cascade_id", (string) $item->cascade_id)
      ->condition("migration_id", (string) $this->migrationId)
      ->execute();

    # Insert this node as new entry in the queue
    $query = $this->database->insert('sitefarm_migrate_cascade_queue');
    $query->fields([
      'path',
      'cascade_id',
      'url',
      'type',
      'migration_id',
      'article_tid',
      'category_tid',
      'tags',
    ]);
    $query->values([
      (string) $item->path,
      (string) $item->cascade_id,
      (string) $item->url,
      (string) $item->type,
      $this->migrationId,
      (int) $item->article_tid,
      (int) $item->category_tid,
      (string) $item->tags,
    ]);
    $query->execute();
  }

  /**
   * @param $migrationId
   */
  public function setMigrationID($migrationId) {
    $this->migrationId = $migrationId;
  }

  /**
   * Will return person, person page, flex page, standard page or meta
   *
   * @param $type
   *
   * @return bool|string
   */
  function getContentType($type) {
    $type = str_replace(["People/", "_common:"], "", $type);

    switch ($type) {
      # People index pages
      case 'People Index page.cfm':
      case 'People Index page.htm':
      case 'People Index page.html':
      case 'People json page':
        return "people_page";
        break;

      # Persons
      case 'Person page.cfm':
      case 'Person page.htm':
      case 'Person page.html':
        return "person";
        break;

      # Person Block
      case 'Person block':
        return "person_block";
        break;

      # Flex pages (pages with blocks)
      case 'Catalog/Flex page - datatable htm':
      case 'Block frag page':
      case 'Flex page asp':
      case 'Flex page aspx':
      case 'Flex page cfm':
      case 'Flex page htm':
      case 'Flex page html':
      case 'Flex page html MERGE':
      case 'Flex page php':
      case 'Flex page shtml':
      case 'Flex page - Document Index.htm':
      case 'Flex page.aspx':
      case 'Flex page.cfm':
      case 'Flex page.htm':
      case 'Flex page.html':
      case 'Flex page.html MERGE':
      case 'Flex page.php':
      case 'Flex page.shtml':
        return "flex_page";
        break;

      # Meta pages
      case 'Metafile page':
        return "meta";
        break;

      # Sitemaps
      case 'Sitemap page.cfm':
      case 'Sitemap page.htm':
        return "sitemap";
        break;

      # Standard pages
      case 'Standard page - partial index.htm':
      case 'Standard page asp':
      case 'Standard page aspx':
      case 'Standard page cfm':
      case 'Standard page htm':
      case 'Standard page html':
      case 'Standard page php':
      case 'Standard page shtml':
      case 'Standard page - partial index.htm':
      case 'Standard page.asp':
      case 'Standard page.aspx':
      case 'Standard page.cfm':
      case 'Standard page.htm':
      case 'Standard page.html':
      case 'Standard page.php':
      case 'Standard page.shtml':
        return "standard_page";
        break;

      default:
        return FALSE;
        break;

    }
  }

  /**
   * @param $siteName
   *
   * @return bool
   */
  private function shouldImportSite($siteName) {
    # When we have an admin, everything can be imported
    $roles = $this->currentUser->getRoles();
    if (in_array('administrator', $roles)) {
      return TRUE;
    }
    $dontImport = [
      'Extension D1 Reports v.2',
      'content library',
      'base-site v.2',
      'CNPRC Base-site v.2',
      'IET Base-site v.2',
      'JMIE Base-site model v.2',
      'SA Base-site v.2',
      'Vet Med Base-site v.2',
      'Vet Med Newsletter Base-site v.2',
      'Vet Med Secure Base-site v.2',
      'Academic Advising v.2 ARCHIVE 201601',
      'Admin Sandbox v.2',
      'CMS v.2',
      'CNPRC *',
      'Cascade at UC Davis v.2',
      'Demonstration v.2',
      'DEVAR Advancement Svcs v.2',
      'DEVAR Organizational Toolkit v.2',
      'Learn Cascade v.2',
      'Registrar v.2',
      'SiteFarm v.2',
      'SoE Assoc Deans Office v.2',
      '_base-site-dev',
      '_base-site-dev 2',
      '_cmsresources',
      '_common',
      '_common-010915',
      '_common_dev',
      'campusgrown.ucdavis.edu',
      'ccfit.ucdavis.edu',
      'cert.ucdavis.edu',
      'chancellor.ucdavis.edu',
      'cams.ucdavis.edu',
      'debate.ucdavis.edu',
      'fridayupdate.ucdavis.edu',
      'iam.ucdavis.edu',
      'new_base-site',
      'news.ucdavis.edu',
      'physics.ucdavis.edu',
      'physics.ucdavis.edu',
      's2c.ucdavis.edu',
      'shep.ucdavis.edu',
      'uconnect.ucdavis.edu',
      'vetmed.ucdavis.edu',
      'vision.ucdavis.edu',
      'websso',
      'www.ls.ucdavis.edu',
      'xucarchive.ucdavis.edu',
      'zz deleted Chemistry Archive v.2',
    ];

    # Strip out all CNPRC websites
    if (stristr($siteName, "CNPRC")) {
      return FALSE;
    }

    # Loop through the sitenames and compare them to the given site
    foreach ($dontImport as $site) {
      $site = trim(strtolower($site));
      $siteName = trim(strtolower($siteName));
      $compare = strcmp($site, $siteName);
      if ($compare == 0) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * @param $type
   *
   * @return bool|mixed
   */
  public function getTestData($type) {
    // Directly calling the Drupal system to get to this service, so we don't
    // have to add a dependency on a test module
    $testData = Drupal::service("sitefarm_migrate_cascade_test.cascade_testdata");
    if (!$testData) {
      return FALSE;
    }
    return $testData->getTestData($type);
  }
}
