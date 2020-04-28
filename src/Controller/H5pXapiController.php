<?php

namespace Drupal\h5p_xapi\Controller;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\h5p_xapi\XapiData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class H5pXapiController extends ControllerBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The theme handler.
   *
   * @var \Drupal\h5p_xapi\XapiData
   */
  protected $xApiData;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * H5pXapiController constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The active database connection.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current logged in user.
   * @param \Drupal\h5p_xapi\XapiData $xapi_data
   *   The xAPI data.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key/value store.
   */
  public function __construct(Connection $database, AccountInterface $current_user, XapiData $xapi_data, Time $time, StateInterface $state) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->xApiData = $xapi_data;
    $this->time = $time;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('current_user'),
      $container->get('h5p_xapi.data'),
      $container->get('datetime.time'),
      $container->get('state')
    );
  }

  /**
   * Process xAPI data sent via AJAX.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Exception
   */
  public function processData(Request $request) {
    if ($this->currentUser->isAnonymous()) {
      throw new AccessDeniedHttpException();
    }

    $data = $request->request->get('statement');
    if (!isset($data)) {
      throw new NotFoundHttpException();
    }

    // Set the xAPI data for the service.
    $this->xApiData->setData($data);

    $actor = $this->xApiData->getActor();
    $object = $this->xApiData->getObject();
    $verb = $this->xApiData->getVerb();
    $result = $this->xApiData->getResult();
    $xapi = $this->xApiData->getRaw();

    // Do some initial prep work on xAPI data.
    if (!$this->configFactory->get('h5p_xapi.settings')
      ->get('store_xapi_statements')) {
      $xapi = NULL;
    }

    $resultStateKey = $this->getResultStateKey($actor, $object);
    $result_id = $this->initialiseDataResult($resultStateKey);
    // Insert the data.
    $this->insertData($actor, $verb, $object, $result, $xapi, $result_id);

    // Tidy up after completion.
    if (!empty($result)) {
      $this->deinitialiseDataResult($resultStateKey);
    }

    return new JsonResponse([]);
  }

  /**
   * Create all records for an xAPI data.
   *
   * @param array $actor
   * @param array $verb
   * @param array $object
   * @param array $result
   * @param string|null $xapi
   * @param int $result_id
   *
   * @return bool
   * @throws \Exception
   */
  public function insertData($actor, $verb, $object, $result, $xapi, $result_id) {
    // Open the transaction.
    $transaction = $this->database->startTransaction();

    $errors = 0;

    $actor_id = $this->insertDataActor($actor);
    if (FALSE === $actor_id) {
      $errors++;
    }

    $verb_id = $this->insertDataVerb($verb);
    if (FALSE === $verb_id) {
      $errors++;
    }

    $object_id = $this->insertDataObject($object);
    if (FALSE === $object_id) {
      $errors++;
    }

    $_result_id = $this->insertDataResult($result, $result_id);
    if (FALSE === $_result_id) {
      $errors++;
    }

    $summary_id = $this->insertDataSummary($actor_id, $verb_id, $object_id, $result_id, $xapi);
    if (FALSE === $summary_id) {
      $errors++;
    }

    // Errors. We can't proceed.
    if (0 !== $errors) {
      $transaction->rollBack();
      return FALSE;
    }

    // Not rolled back, automatically committed.
    return TRUE;
  }

  /**
   * Create a ne actor record.
   *
   * @param array $actor
   *
   * @return bool|\Drupal\Core\Database\StatementInterface|int|mixed|string
   * @throws \Exception
   */
  public function insertDataActor($actor) {
    $actor_id = $this->getActorId($actor);

    if (empty($actor_id)) {
      $actor_id = $this->database->insert(H5P_XAPI_ACTOR_TABLE)
        ->fields([
          'actor_id' => $actor['inverseFunctionalIdentifier'],
          'actor_name' => $actor['name'],
          'actor_members' => $actor['members'],
          'uid' => $actor['uid'],
        ])
        ->execute();

    }
    return $actor_id ? $actor_id : FALSE;
  }

  /**
   * Create a new verb record.
   *
   * @param array $verb
   *
   * @return bool|\Drupal\Core\Database\StatementInterface|int|mixed|string
   * @throws \Exception
   */
  public function insertDataVerb($verb) {
    $verb_id = $this->getVerbId($verb);

    if (empty($verb_id)) {
      $verb_id = $this->database->insert(H5P_XAPI_VERB_TABLE)
        ->fields([
          'verb_id' => $verb['id'],
          'verb_display' => $verb['display'],
        ])
        ->execute();
    }
    return $verb_id ? $verb_id : FALSE;
  }

  /**
   * Create a new object record.
   *
   * @param array $object
   *
   * @return bool|\Drupal\Core\Database\StatementInterface|int|mixed|string
   * @throws \Exception
   */
  public function insertDataObject($object) {
    $object_id = $this->getObjectId($object);

    if (empty($object_id)) {
      $object_id = $this->database->insert(H5P_XAPI_OBJECT_TABLE)
        ->fields([
          'xobject_id' => $object['id'],
          'object_name' => $object['name'],
          'object_description' => $object['description'],
          'object_choices' => $object['choices'],
          'object_correct_responses_pattern' => $object['correctResponsesPattern'],
          'h5p_content_id' => $object['h5pContentId'],
          'h5p_subcontent_id' => $object['h5pSubContentId'],
        ])->execute();
    }

    return $object_id ? $object_id : FALSE;
  }

  /**
   *  Update a result record after completing an activity.
   *
   * @param array $result
   * @param int $result_id
   *
   * @return \Drupal\Core\Database\StatementInterface|int|string|null
   */
  public function insertDataResult($result, $result_id) {
    if (!empty($result)) {
      $_result_id = $this->database->update(H5P_XAPI_RESULT_TABLE)
        ->fields([
          'result_response' => $result['response'],
          'result_score_raw' => $result['score_raw'],
          'result_score_scaled' => $result['score_scaled'],
          'result_completion' => $result['completion'] ? 1 : 0,
          'result_success' => $result['success'] ? 1 : 0,
          'result_duration' => $result['duration'],
        ])
        ->condition('id', $result_id)
        ->execute();

      $result_id = $_result_id ? $result_id : $_result_id;
    }
    return $result_id;
  }

  /**
   * Create a new summary record.
   *
   * @param int $actor_id
   * @param int $verb_id
   * @param int $object_id
   * @param int $result_id
   *
   * @return \Drupal\Core\Database\StatementInterface|int|string|null
   * @throws \Exception
   */
  public function insertDataSummary($actor_id, $verb_id, $object_id, $result_id, $xapi) {
    // @todo: check config whether we should store raw xapi data.
    $summary_id = $this->database->insert(H5P_XAPI_SUMMARY_TABLE)
      ->fields([
        'id_actor' => $actor_id,
        'id_verb' => $verb_id,
        'id_object' => $object_id,
        'id_result' => $result_id,
        'time' => $this->time->getRequestTime(),
        'xapi' => $xapi,
      ])->execute();

    return $summary_id ? $summary_id : FALSE;
  }

  /**
   * Get the id of an actor from the database.
   *
   * @param array $actor
   *
   * @return mixed
   */
  private function getActorId($actor) {
    return $this->database->select(H5P_XAPI_ACTOR_TABLE, 'a')
      ->fields('a', ['id'])
      ->condition('actor_id', $actor['inverseFunctionalIdentifier'])
      ->execute()
      ->fetchField();
  }

  /**
   * Get the id of a verb from the database.
   *
   * @param array $verb
   *
   * @return mixed
   */
  private function getVerbId($verb) {
    return $this->database->select(H5P_XAPI_VERB_TABLE, 'v')
      ->fields('v', ['id'])
      ->condition('verb_id', $verb['id'])
      ->execute()
      ->fetchField();
  }

  /**
   * Get the id of an object from the database.
   *
   * @param array $object
   *
   * @return mixed
   */
  private function getObjectId($object) {
    return $this->database->select(H5P_XAPI_OBJECT_TABLE, 'o')
      ->fields('o', ['id'])
      ->condition('xobject_id', $object['id'])
      ->condition('object_name', $object['name'])
      ->condition('object_description', $object['description'])
      ->condition('object_choices', $object['choices'])
      ->condition('object_correct_responses_pattern', $object['correctResponsesPattern'])
      ->execute()
      ->fetchField();
  }

  /**
   * Create dummy result so we can have a result ID.
   *
   * @param string $key
   *
   * @return \Drupal\Core\Database\StatementInterface|int|mixed|string|null
   * @throws \Exception
   */
  private function initialiseDataResult($key) {
    // Check state for result id.
    $result_id = $this->state->get($key);

    if (empty($result_id)) {
      $result_id = $this->database->insert(H5P_XAPI_RESULT_TABLE)
        ->fields([
          'result_response' => NULL,
          'result_score_raw' => NULL,
          'result_score_scaled' => NULL,
          'result_completion' => 0,
          'result_success' => 0,
          'result_duration' => NULL,
        ])
        ->execute();
      // Set state.
      $this->state->set($key, $result_id);
    }

    return $result_id;
  }

  /**
   * Wrapper for deleting the state key for a user doing this activity.
   *
   * @param string $key
   */
  private function deinitialiseDataResult($key) {
    $this->state->delete($key);
  }

  /**
   * Get the state key for a user doing this activity.
   *
   * @param array $actor
   * @param array $object
   *
   * @return string
   */
  private function getResultStateKey($actor, $object) {
    return 'result_id:' . $actor['actor_name'] . ':' . $object['h5pContentId'];
  }

}
