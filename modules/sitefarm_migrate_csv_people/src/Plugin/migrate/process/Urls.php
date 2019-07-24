<?php

namespace Drupal\sitefarm_migrate_csv_people\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Path\PathValidator;

/**
 * This plugin generates makes sure the value is a valid url.
 * Format [title](url), separate with delimiter,
 * no spaces: "[Google](https://google.com)|[Yahoo](https://yahoo.com)"
 *
 * @MigrateProcessPlugin(
 *   id = "sf_urls"
 * )
 */
class Urls extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Component\Utility\UrlHelper
   */
  protected $urlHelper;

  /**
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $pathValidator;

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param array $plugin_definition
   * @param \Drupal\Core\Path\PathValidator $pathValidator
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, PathValidator $pathValidator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->initializeUrlHelper();
    $this->pathValidator = $pathValidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("path.validator")
    );
  }

  /**
   * Initialize the URL Helper
   */
  function initializeUrlHelper() {
    $this->urlHelper = new UrlHelper();
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($this->configuration['delimiter'])) {
      $this->configuration['delimiter'] = "|";
    }

    $value = [];
    $websites_encoded = explode($this->configuration['delimiter'], $row->getSourceProperty("websites"));
    foreach ($websites_encoded as $website) {
      $site = explode("]", $website);
      if (isset($site[1])) {

        $title = trim($site[0], "[]()'\"");
        $url = trim($site[1], "[]()'\"");
        $value[$url] = $title;
      }
      else {
        $url = trim($site[0], "[]()'\"");
        $value[$url] = $url;
      }
    }

    // Nothing coming in, no need to continue
    if (is_null($value) || empty($value)) {
      return NULL;
    }

    $cleanValues = [];
    $i = 0;
    foreach ($value as $url => $title) {

      // If someone didn't set a title
      if (is_int($url)) {
        $url = $title;
      }

      // Check the url and add it to the cleanvalues if valid
      $cleanUrl = $this->checkUrl($url);
      if ($cleanUrl) {
        $cleanValues[$i]['uri'] = $cleanUrl;
        $cleanValues[$i]['title'] = $title;
      }
      else {
        $this->messenger()->addStatus('Url ' . $url . ' is invalid');
      }
      $i++;
    }
    if (empty($cleanValues)) {
      return NULL;
    }
    return $cleanValues;
  }

  /**
   * @param $url
   *
   * @return bool
   */
  public function checkUrl($url) {
    $url = trim(trim($url, '"\''));

    // Decode special characters
    if (strstr($url, ";")) {
      $url = htmlspecialchars_decode($url);
    }
    else {
      if (strstr($url, "%")) {
        $url = urldecode($url);
      }
    }

    // Urls cannot have a semicolon, this one is broken
    if (strstr($url, ";")) {
      return FALSE;
    }

    // Check if the url has a valid format for an external link.
    if ($this->urlHelper->isValid($url, TRUE)) {
      return $url;
    }

    // Check if the url could be internal

    // Add a slash if it is not there
    $url_internal = $url;
    if (substr($url_internal, 0, 1) != "/") {
      $url_internal = "/" . $url_internal;
    }

    // Check if the url is correct and if it is an internal url
    if ($this->urlHelper->isValid($url_internal) && $this->pathValidator->isValid($url_internal)) {
      // Adding "internal:" so Drupal can handle it
      return "internal:" . $url_internal;
    }

    // Maybe they forgot http in front of it
    $url_withhttp = "http://" . $url;
    if ($this->urlHelper->isValid($url_withhttp, TRUE)) {
      return $url_withhttp;
    }

    return FALSE;
  }

}
