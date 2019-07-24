<?php

namespace Drupal\Tests\sitefarm_migrate_csv_people\Unit\Plugin\migrate\process;

use Drupal\sitefarm_migrate_csv_people\Plugin\migrate\process\Urls;

/**
 * Overrides methods which have global functions
 */
class MockedUrls extends Urls {

  /**
   * The returned message.
   */
  public $message;

  /**
   * Overrided method so that the message can be retrieved during a test.
   *
   * @param $text
   */
  protected function setMessage($text) {
    $this->message = $text;
  }
}
