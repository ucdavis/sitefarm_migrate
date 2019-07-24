<?php

namespace Drupal\sitefarm_migrate_wordpress\Plugin\migrate\process;

use DOMDocument;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * This plugin generates the body for a bodyfield from wordpress
 * We also grab images and attachements from the body field and
 * inject them as attached image
 *
 * @MigrateProcessPlugin(
 *   id = "sf_wordpress_body"
 * )
 */
class SitefarmWordpressBody extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * A request stack object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;


  public function __construct(array $configuration, $plugin_id, array $plugin_definition, $migration, Connection $database, FileSystemInterface $file_system, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('database'),
      $container->get('file_system'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_null($value) || empty($value)) {
      return $value;
    }

    // Load a domdocument of the HTML
    $doc = new DOMDocument();
    @$doc->loadHTML($value);

    // Grab all images and loop though them
    $tags = $doc->getElementsByTagName('img');

    foreach ($tags as $id => $tag) {
      $value = $this->convertImageToManagedFile($value, $tag);
    }

    // Grab all links, check if it is a local file and download where needed
    $tags = $doc->getElementsByTagName('a');

    foreach ($tags as $id => $tag) {
      $value = $this->convertLinkToManagedFile($value, $tag);
    }

    // Convert [embed] to <drupal-url>
    $value = $this->convertEmbeds($value);

    // Convert Youtube iframes to <drupal-url>
    $value = $this->convertYoutubeIframe($value);

    // Convert the leftover [caption] to html
    $value = $this->convertCaptions($value);

    return ($value);
  }

  /**
   * Replace an image with a managed local file
   *
   * @param mixed $value
   *   The value to be transformed.
   * @param \DOMElement $img_tag
   *
   * @return mixed
   */
  public function convertImageToManagedFile($value, $img_tag) {
    $image = $img_tag->getAttribute('src');
    $file = $this->saveFile($image, TRUE);

    // Replace the image with the local image we just created
    if ($file) {
      $value = $this->convertImageCaption($value, $image);
      $src = file_url_transform_relative(file_create_url($file->getFileUri()));

      $replace = 'src="' . $src . '" data-entity-uuid="' . $file->get("uuid")->value . '" data-entity-type="file" ';
      $value = str_replace('src="' . $image . '"', $replace, $value);

      // Also replace the links to this image
      $value = str_replace('href="' . $image . '"', 'href="' . $src . '"', $value);
    }

    return $value;
  }

  /**
   * Replace [caption][/caption] shortcode with a Drupal data-caption attribute
   *
   * @param mixed $value
   *   The value to be transformed.
   * @param $src
   *
   * @return string
   */
  public function convertImageCaption($value, $src) {
    // Check if this
    $regex = '/\[caption[^\]]+\](<img.+' . preg_quote($src, '/') . '.+\/>)(.+)\[\/caption\]/';
    preg_match($regex, $value, $matches);

    if (!empty($matches)) {
      // First: Get the caption
      $caption = htmlspecialchars(trim($matches[2]));

      // Second: get the <img> tag and append the caption to it
      $img = $matches[1];
      $replace = 'data-caption="' . $caption . '" />';
      $img = str_replace('/>', $replace, $img);

      // Third: Replace the entire string with just the <img> so that the
      // caption shortcodes and text caption are removed
      $value = preg_replace($regex, $img, $value);
    }

    return $value;
  }

  /**
   * @param $value
   *
   * @return null|string|string[]
   */
  function convertCaptions($value) {
    $pattern = <<<'LOD'
~
\[caption                          # beginning of the tag 
(?>[^]c]++|c(?!aption\b))*         # followed by anything but c and ]
                                   # or c not followed by "aption"

(?|                                # alternation group
    caption="([^"]++)"[^]]*+]      # the content is inside the begining tag  
  |                                # OR
    ]([^[]+)                       # outside 
)                                  # end of alternation group

\[/caption]                        # closing tag
~x
LOD;

    $replacement = "<p><figure class=\"caption\">$1</figure></p>";
    $value = preg_replace($pattern, $replacement, $value);
    return $value;
  }

  /**
   * Replace all links to files with a managed local file
   *
   * @param mixed $value
   *   The value to be transformed.
   * @param \DOMElement $a_tag
   *
   * @return string
   */
  public function convertLinkToManagedFile($value, $a_tag) {
    $link = $a_tag->getAttribute('href');

    if ((stristr($link, ".jpg") ||
      stristr($link, ".gif") ||
      stristr($link, ".pdf") ||
      stristr($link, ".doc") ||
      stristr($link, ".png"))
    ) {
      $file = $this->saveFile($link, FALSE);

      // Replace the link with the local link we just created
      if ($file) {
        $src = file_url_transform_relative(file_create_url($file->getFileUri()));
        $replace = 'href="' . $src . '" data-entity-uuid="' . $file->get("uuid")->value . '" data-entity-type="file" ';
        $value = str_replace('href="' . $link . '"', $replace, $value);
      }
    }

    return $value;
  }

  /**
   * Replace all [embed][/embed] shortcodes with <drupal-url> tags
   *
   * @param mixed $value
   *   The value to be transformed.
   * @param \DOMElement $a_tag
   *
   * @return string
   */
  public function convertEmbeds($value) {
    $regex = '/\[embed\]([^\[]+)\[\/embed\]/';

    $value = preg_replace(
      $regex,
      '<drupal-url data-embed-button="url" data-embed-url="$1" data-entity-label="URL"></drupal-url>',
      $value
    );

    return $value;
  }

  /**
   * Replace all Youtube iframes with <drupal-url> tags
   *
   * @param $value
   *
   * @return null|string|string[]
   */
  public function convertYoutubeIframe($value) {
    $regex = '/<iframe.*?src="(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})(?:\S+).*?<\/iframe>/si';

    $value = preg_replace(
      $regex,
      '<drupal-url data-embed-button="url" data-embed-url="https://www.youtube.com/embed/$1" data-entity-label="URL" data-url-provider="YouTube"></drupal-url>',
      $value
    );

    return $value;
  }


  function saveFile($url, $image = TRUE) {
    # Make sure these folders exist
    @mkdir("public://inline-files/");
    @mkdir("public://inline-images/");

    # Distinction between files and images
    $path = $image ? 'public://inline-images/' : 'public://inline-files/';

    if (!empty($url)) {
      # Make sure the url is usable
      if (FALSE === file_get_contents($url, 0, NULL, 0, 1)) {
        return FALSE;
      }

      $filename = $path . array_pop(explode("/", $url));
      $data = file_get_contents($url);
      $file = file_save_data($data, $filename, FILE_EXISTS_REPLACE);
      if ($file) {
        $this->createRedirect($path, $file->getFileUri());
      }

      # Return the file ID or false if the file didn't save
      return $file;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Create redirects to files using the Redirect Module
   *
   * @param $from
   * @param $to
   *
   * @return bool|int
   */
  function createRedirect($from, $to) {
    $request = $this->requestStack->getCurrentRequest();

    if (strlen($from) < 3) {
      return FALSE;
    }

    if (substr($from, 0, 1) == "/") {
      $from = substr($from, 1);
    }
    $to = str_replace($request->getSchemeAndHttpHost(), "internal:", file_create_url($to));
    # Delete possible double
    $this->database->delete("redirect")
      ->condition('redirect_source__path', $from, "LIKE")
      ->execute();

    $redirect = new Redirect([], "node");
    # Create the redirect using the redirect module
    $redirect = $redirect->create([
      'redirect_source' => $from,
      'redirect_redirect' => $to,
      'language' => 'und',
      'status_code' => '301',
    ]);
    return $redirect->save();
  }

}
