<?php

namespace Drupal\sitefarm_migrate_cascade\Plugin\migrate\source;

use Drupal;
use Drupal\migrate\Row;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowDeleteEvent;
use DOMDocument;

/**
 * Source for importing the pages
 *
 * @MigrateSource(
 *   id = "sitefarm_cascade_pages"
 * )
 */
class SitefarmCascadePages extends SitefarmCascadeBase {

  /**
   * @return array|\IteratorIterator
   */
  public function initializeIterator() {
    return parent::initializeIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {

    $this->isTest = $row->getSourceProperty("is_test");

    if (!$this->isTest) {
      $this->cascade_connect();
      $data = $this->cascadeMigrate->queryCascadeById($row->getSourceProperty("type"), $row->getSourceProperty("cascade_id"));
    }
    else {
      $data = $this->cascadeMigrate->getTestdata("page");
    }

    # Set the country code. We only deal with US and CA.
    $row->setSourceProperty('countrycode', $row->getSourceProperty("country") == "United States" ? "US" : "CA");

    # Check if we have the right content type. if not, skip and delete from queue
    # Also check if the article type is set
    if (!$this->isTest && !in_array($this->cascadeMigrate->getContentType($data->asset->page->contentTypePath), [
        'standard_page',
        'flex_page',
        'people_page'
      ]) || ($this->migration->getProcess()['type'][0]['default_value'] == "sf_article" && (int) $row->getSourceProperty("article_tid") < 1)
    ) {
      $this->deleteFromQueue(implode(",", $row->getSourceIdValues()), $this->migration->getBaseId());
      return FALSE;
    }

    # Set the Body and Alias
    $body = $this->get_body($data->asset->page->structuredData, $data->asset->page->xhtml);
    $alias = $this->getAlias($data->asset->page->path, $data->asset->page->contentTypePath);

    $title = $this->getTitle($data->asset->page->metadata->title, $data->asset->page);

    # Skip this page if there is no title, body or page alias
    if (!$this->isTest && empty($data->asset->page->metadata->title) || empty($alias) || empty($body)) {
      $this->deleteFromQueue(implode(",", $row->getSourceIdValues()), $this->migration->getBaseId());
      return FALSE;
    }

    # Create the menu item
    $createMenu = $this->getIncludeInNavValue($data);

    $row->setSourceProperty("title", $title);
    $row->setSourceProperty("created", $data->asset->page->createdDate);
    $row->setSourceProperty("modified", $data->asset->page->lastModifiedDate);
    $row->setSourceProperty("status", $data->asset->page->shouldBePublished == TRUE ? 1 : 0);
    $row->setSourceProperty("body", $body);
    $row->setSourceProperty("tags", explode(",", $row->getSourceProperty("tags")));
    $row->setSourceProperty("alias", $alias);
    $row->setSourceProperty("createMenu", $createMenu);
    $row->setSourceProperty("article_type", [$row->getSourceProperty("article_tid")]);
    $row->setSourceProperty("category", [$row->getSourceProperty("category_tid")]);
    $row->setSourceProperty("featured", 0);
    $row->setSourceProperty("person_ref", [0]);
    $row->setSourceProperty("photo_ref", [0]);

    $return = parent::prepareRow($row);
    return $return;
  }

  # Runs after a row is saved
  public function onPostRowSave(MigratePostRowSaveEvent $event) {

    # Save a redirect from the old url
    $destination = $event->getDestinationIdValues();
    $this->createRedirect($event->getRow()
      ->getSourceProperty("alias"), $destination[0]);

    # Create menu item if needed
    if ($event->getRow()->getSourceProperty("createMenu") == TRUE) {
      $this->createMenuItem($event->getRow()
        ->getSourceProperty("title"), $destination[0],
        $event->getRow()->getSourceProperty("alias"));
    }
  }

  /**
   * @param $title
   * @param $body
   *
   * @return mixed
   */
  public function getTitle($title, $body){
    $newTitle = isset($body->structuredData) ? $this->getCascadeTitleLoop($body->structuredData) : FALSE;
    return empty($newTitle) ? $title : $newTitle;
  }

  # Runs after a row is deleted
  public function onPostRowDelete($event) {

    # Remove a redirect from the old url
    $this->removeRedirect($event->getDestinationIdValues()['nid'], TRUE);

    # Remove the menu item
    $this->removeMenuItemByNid($event->getDestinationIdValues()['nid'], TRUE);
  }

  # Runs after the migration has ended
  public function onPostImport($event) {
    # Create the menu item children
    if (!empty($this->privateTempStorage->get('menuChildren'))) {
      foreach ($this->privateTempStorage->get('menuChildren') as $child) {
        $this->createMenuItem($child[0], $child[1], $child[2], TRUE);
      }
    }
    # Empty the temporary variables
    $this->privateTempStorage->set('menuChildren', []);
    $this->privateTempStorage->set('dynamicParents', []);

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
      $node = $this->entitytypemanager->getStorage('node')->load($destId);
      $body = $node->get('body')->getValue();
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
   * @return bool
   */
  private function getRedirectByUrl($url) {
    if(substr($url, 0, 1) == "/"){
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
      if(stristr($redirectUrl, "internal:/")){
        $search = str_replace("internal:", "", $redirectUrl);
        $alias = $this->database
          ->select("url_alias", 'a')
          ->fields('a', ['alias'])
          ->condition('source', $search, "LIKE")
          ->execute()
          ->fetchAll();
        foreach($alias as $b){
          return $b->alias;
        }
      }
      return $redirectUrl;
    }
    return FALSE;
  }
}