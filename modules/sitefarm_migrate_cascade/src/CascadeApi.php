<?php

namespace Drupal\sitefarm_migrate_cascade;

use Drupal\Core\Database\Connection;
use Exception;
use SoapClient;


class CascadeApi {
  private $auth;
  private $client;
  private $cache;
  private $connection;
  private $cache_path = "public://cascade_cache/";

  /*
  Builds $auth and $client, then tests authentication
  NOTE:Wrap in try/catch
  */
  function __construct(Connection $database) {
    $this->connection = $database;
    if(!file_exists($this->cache_path)){
      mkdir($this->cache_path);
    }
  }

  function authenticate($username, $password, $domain) {
    $this->auth = ['username' => $username, 'password' => $password];
    $soapURL = $domain . "/ws/services/AssetOperationService?wsdl";
    try {
      $this->client = new SoapClient
      (
        $soapURL,
        ['trace' => 1, 'location' => str_replace('?wsdl', '', $soapURL)]
      );

      return $this->testAuth();

    } catch (Exception $e) {
      return FALSE;
    }
    return FALSE;
  }

  /*
  Allows: identifier($type,$path,$siteName);
  Allows: identifier($type,$id);
  */
  function __call($method, $arguments) {
    /*
    Allows:
    $obj->identifier($type,$path,$siteName);
    $obj->identifier($type,$id);
    */
    if ($method == 'identifier') {
      if (count($arguments) == 2) {
        return call_user_func_array([$this, 'identifierById'], $arguments);
      }
      elseif (count($arguments) == 3) {
        return call_user_func_array([
          $this,
          'identifierByPath'
        ], $arguments);
      }
    }
  }

  /*
  Changes the authenticated user, and test the authentication
  NOTE:Wrap in try/catch
  */
  function changeAuth($username, $password) {
    $this->auth = ['username' => $username, 'password' => $password];
    $this->testAuth();
  }

  /*
  Tests authentication using listSites()
  Throws an exception on failure
  */
  private function testAuth() {
    if (!$this->result($this->listSites())) {
      return FALSE;
    }
    return TRUE;
  }

  /*
  Return boolean on $reply->success
  */
  function result($reply) {
    return ($reply->success == "true");
  }

  /*
  Builds path based identifier
  */
  function identifierByPath($type, $path, $siteName) {
    return [
      'path' => [
        'path' => $path,
        'siteName' => $siteName
      ],
      'type' => $type
    ];
  }

  /*
  Builds id based identifier
  */
  function identifierById($type, $id) {
    return [
      'id' => $id,
      'type' => $type
    ];
  }

  /*
  Builds single ACL
  */
  function createACL($name, $level = 'read', $type = 'group') {
    return [
      'name' => $name,
      'level' => $level,
      'type' => $type
    ];
  }

  /*
  Returns list of sites
  */
  function listSites() {
    $params = ['authentication' => $this->auth];
    $reply = $this->client->listSites($params);
    return $reply->listSitesReturn;
  }

  /*
  Returns list of subscribers
  */
  function listSubscribers($identifier) {
    $params = [
      'authentication' => $this->auth,
      'identifier' => $identifier
    ];
    $reply = $this->client->listSubscribers($params);
    return $reply->listSubscribersReturn;
  }

  /*
  Returns readReturn from identifier
  */
  function read($identifier, $nocache = FALSE, $dontsetcache = FALSE) {
    if ($nocache === FALSE) {
      $cache = $this->get_cache($identifier);
      if ($cache !== FALSE) {
        return $cache;
      }
    }
    $params = [
      'authentication' => $this->auth,
      'identifier' => $identifier
    ];
    try {
      $reply = $this->client->read($params);
    }catch(Exception $e){
      return FALSE;
    }
    if(!$dontsetcache){
      $this->set_cache($identifier, $reply->readReturn);
    }
    return $reply->readReturn;
  }

  /*
   * Gives identifier hash
   */
  function get_identifier_hash($identifier) {
    md5(json_encode($identifier));
  }

  /*
   * Sets cache. Using simple files to prevent memory hogging
   */
  function set_cache($identifier, $content) {
    # Hash the identifier for an ID
    $id = md5(json_encode($identifier));

    # Put the content in a file, encoded so json string doesnt break with non utf characters
    $cachefile = $this->cache_path . $id;
    file_put_contents($cachefile, base64_encode(json_encode($content)));

    $this->store_data($id, $content);
  }

  /*
   * Gets cache
   */
  function get_cache($identifier) {
    $id = md5(json_encode($identifier));

    $cachefile = $this->cache_path . $id;
    if (file_exists($cachefile . $id)) {
      $cache = json_decode(base64_decode(file_get_contents($cachefile)));
      if (!empty($cachefile)) {
        return $cache;
      }
    }

    return FALSE;
  }

  /*
   * Destroys cache
   */
  function destroy_cache() {
    $this->cache = [];
  }

  /*
   * Sets cache in database
   * id: md5 hash of the identifier
   * data: String, array or object with data
   */
  function store_data($id, $data) {

    if (!is_object($data) && !is_array($data) && !is_string($data)) {
      return FALSE;
    }
    if (!is_string($id) || strlen($id) != 32) {
      return FALSE;
    }
    $data = json_encode($data);

    $this->connection->query("REPLACE INTO {sitefarm_migrate_cascade_data} SET data = :data, id = :id, updated = :time",
      [":data" => $data, ":id" => $id, ":time" => time()]);
  }

  /*
   * Gets cache from database
   * id: md5 hash of the identifier3
   * [expiration]: timestamp of expiration date
   */
  function get_data($id, $expiration = FALSE) {
    if (!is_string($id) || strlen($id) != 32) {
      return FALSE;
    }
    if (!is_integer($expiration)) {
      $expiration = 0;
    }

    $result = $this->connection->query("SELECT data FROM {sitefarm_migrate_cascade_data} WHERE id = :id AND updated >= :expiration",
      [":expiration" => $expiration, ":id" => $id])->fetchAssoc();

    if (!is_empty($result['data'])) {
      return json_decode($result['data']);
    }
    return FALSE;
  }

  /*
  Returns readWorkflowSettingsReturn from identifier
  */
  function readWorkflowSettings($identifier) {
    $params = [
      'authentication' => $this->auth,
      'identifier' => $identifier
    ];
    $reply = $this->client->readWorkflowSettings($params);
    return $reply->readWorkflowSettingsReturn;
  }

  /*
  Returns readAccessRightsReturn from identifier
  */
  function readAccessRights($identifier) {
    $params = [
      'authentication' => $this->auth,
      'identifier' => $identifier
    ];
    $reply = $this->client->readAccessRights($params);
    return $reply->readAccessRightsReturn;
  }

  /*
  Returns searchReturn from identifier
  */
  function search($searchInfo) {
    $params = [
      'authentication' => $this->auth,
      'searchInformation' => $searchInfo
    ];
    $reply = $this->client->search($params);
    return $reply->searchReturn;
  }

  /*
  Returns editReturn from identifier
  */
  function edit($asset) {
    $params = ['authentication' => $this->auth, 'asset' => $asset];
    $reply = $this->client->edit($params);
    return $reply->editReturn;
  }

  /*
  Returns editWorkflowSettingsReturn from identifier
  */
  function editWorkflowSettings($workflowSettings, $childrenInherit, $childrenRequire) {
    $params = [
      'authentication' => $this->auth,
      'workflowSettings' => $workflowSettings,
      'applyInheritWorkflowsToChildren' => $childrenInherit,
      'applyRequireWorkflowToChildren' => $childrenRequire
    ];
    $reply = $this->client->editWorkflowSettings($params);
    return $reply->editWorkflowSettingsReturn;
  }

  /*
  Returns editAccessRightsReturn from identifier
  */
  function editAccessRights($acls, $children) {
    $params = [
      'authentication' => $this->auth,
      'accessRightsInformation' => $acls,
      'applyToChildren' => $children
    ];
    $reply = $this->client->editAccessRights($params);
    return $reply->editAccessRightsReturn;
  }

  /*
  Returns moveReturn from identifier
  */
  function move($identifier, $destIdentifier, $newName, $doWorkflow = FALSE) {
    $params = [
      'authentication' => $this->auth,
      'identifier' => $identifier,
      'moveParameters' => [
        'destinationContainerIdentifier' => $destIdentifier,
        'newName' => $newName,
        'doWorkflow' => $doWorkflow
      ]
    ];
    $reply = $this->client->move($params);
    return $reply->moveReturn;
  }

  /*
  Returns createReturn from identifier
  */
  function create($asset) {
    $params = ['authentication' => $this->auth, 'asset' => $asset];
    $reply = $this->client->create($params);
    return $reply->createReturn;
  }

  /*
  Returns copyReturn from identifier
  */
  function copy($identifier, $destIdentifier, $newName) {
    $params = [
      'authentication' => $this->auth,
      'identifier' => $identifier,
      'copyParameters' => [
        'destinationContainerIdentifier' => $destIdentifier,
        'newName' => $newName,
        'doWorkflow' => FALSE
      ]
    ];
    $reply = $this->client->copy($params);
    return $reply->copyReturn;
  }

  /*
  Returns deleteReturn from identifier
  */
  function delete($identifier) {
    $params = [
      'authentication' => $this->auth,
      'identifier' => $identifier
    ];
    $reply = $this->client->delete($params);
    return $reply->deleteReturn;
  }

  /*
  Returns publishReturn from identifier
  */
  function publish($identifier, $destIdentifiers = FALSE, $unpublish = FALSE) {
    $publishInformation = [
      'identifier' => $identifier,
      'unpublish' => $unpublish
    ];
    if ($destIdentifiers) {
      if (is_array($destIdentifiers)) {
        $publishInformation['destinations'] = $destIdentifiers;
      }
      else {
        $publishInformation['destinations'] = [$destIdentifiers];
      }
    }
    $params = [
      'authentication' => $this->auth,
      'publishInformation' => $publishInformation
    ];
    $reply = $this->client->publish($params);
    return $reply->publishReturn;
  }

  /*
  For converting the multi-dimensional object to a multi-dimensional array
  */
  function objectToArray($object) {
    if (is_object($object)) {
      $object = get_object_vars($object);
    }
    if (is_array($object)) {
      return array_map([$this, 'objectToArray'], $object);
    }
    else {
      return $object;
    }
  }

  /*
  For converting the a multi-dimensional array to a multi-dimensional object
  */
  function arrayToObject($array) {
    if (is_array($array) && (bool) count(array_filter(array_keys($array), 'is_string'))) {
      return (object) array_map([$this, 'arrayToObject'], $array);
    }
    elseif (is_array($array) && !(bool) count(array_filter(array_keys($array), 'is_string'))) {
      $temp = [];
      foreach ($array as $arr) {
        if (is_array($arr)) {
          $temp[] = (object) array_map([$this, 'arrayToObject'], $arr);
        }
        else {
          $temp[] = $arr;
        }
      }
      return $temp;
    }
    else {
      return $array;
    }
  }

  /*
  For checking values in array/object
  */
  function test($arr) {
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
  }
}
