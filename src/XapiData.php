<?php

namespace Drupal\h5p_xapi;


use Drupal\Core\StringTranslation\StringTranslationTrait;


class XapiData {

  use StringTranslationTrait;

  /**
   * @var
   */
  protected $raw;

  /**
   * @var
   */
  protected $data;

  /**
   * @var string[]
   */
  protected $keys = [
    'contentIdKey' => 'http://h5p.org/x-api/h5p-local-content-id',
    'subContentIdKey' => 'http://h5p.org/x-api/h5p-subContentId',
  ];

  protected $actor;

  protected $verb;

  protected $object;

  protected $result;

  /**
   * @return mixed
   */
  public function getRaw() {
    return $this->raw;
  }

  /**
   * @param mixed $xapi
   *
   * @return XapiData
   */
  public function setRaw($xapi) {
    $this->raw = json_encode($xapi, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_FORCE_OBJECT);
    return $this;
  }

  /**
   * @return mixed
   */
  public function getData() {
    return $this->data;
  }

  /**
   * @param mixed $data
   *
   * @return XapiData
   */
  public function setData($data) {
    // Set the raw data first.
    $this->setRaw($data);

    $this->data = $data;
    return $this;
  }

  public function getKeys() {
    return $this->keys;
  }

  /**
   * @return mixed
   */
  public function getActor() {
    $this->setActor();
    return $this->actor;
  }

  /**
   * @return mixed
   */
  public function getVerb() {
    $this->setVerb();
    return $this->verb;
  }

  /**
   * @return mixed
   */
  public function getObject() {
    $this->setObject();
    return $this->object;
  }

  /**
   * @return mixed
   */
  public function getResult() {
    $this->setResult();
    return $this->result;
  }


  public function setActor() {
    $ifi = $name = $members = $uid = NULL;
    if (is_array($this->data) && array_key_exists('actor', $this->data)) {
      $ifi = $this->getActorInverseFunctionalIdentifier($this->data['actor']);
      $name = $this->getActorName($this->data['actor']);
      $members = $this->getActorMembers($this->data['actor']);
      $uid = \Drupal::currentUser()->id();
    }

    $this->actor = [
      'inverseFunctionalIdentifier' => $ifi,
      'name' => $name,
      'members' => $members,
      'uid' => $uid,
    ];
  }

  public function getActorObjectType(array $actor) {
    return array_key_exists('objectType', $actor) ? $actor['objectType'] : '';
  }

  public function getActorMembers(array $actor) {
    $members = (array_key_exists('member', $actor)) ? $this->getMembers($actor['member']) : '';

    $object_type = $this->getActorObjectType($actor);
    if ('Agent' === $object_type || '' === $object_type) {
      // Not really neccessary, but according to xAPI specs agents have no
      // member data.
      $members = '';
    }
    return $members;
  }

  public function getActorName(array $actor) {
    $object_type = $this->getActorObjectType($actor);

    $name = array_key_exists('name', $actor) ? $actor['name'] : '';
    // Identified Group or Anonymous Group (we don't need to distinguish here)
    if ('Group' === $object_type) {
      $name = ('' === $name) ? $name : $this->t('@name . (Group)', ['@name' => $name]);
    }

    return $name;
  }

  /**
   * Flatten xAPI InverseFunctionalIdentifier object.
   *
   * @param array $actor The actor object.
   *
   * @return string Flattened InverseFunctionalIdentifier.
   */
  private function getActorInverseFunctionalIdentifier($actor) {
    if (!is_array($actor) || empty($actor)) {
      return '';
    }

    $identifier = [];
    if (array_key_exists('mbox', $actor)) {
      array_push($identifier, $this->t('email: @email', ['@email' => $actor['mbox']]));
    }
    if (array_key_exists('mbox_sha1sum', $actor)) {
      array_push($identifier, $this->t('email hash: @hash', ['@hash' => $actor['mbox_sha1sum']]));
    }
    if (array_key_exists('openid', $actor)) {
      array_push($identifier, $this->t('openid: @openid', ['@openid' => $actor['openid']]));
    }
    if (array_key_exists('account', $actor)) {
      array_push($identifier, $this->t('account: @account', ['@account' => $this->getAccount($actor['account'])]));
    }
    return (empty($identifier)) ? '' : implode($identifier, ', ');
  }

  public function setVerb() {
    $id = $display = NULL;
    if (is_array($this->data) && array_key_exists('verb', $this->data)) {
      $verb = $this->data['verb'];

      $id = array_key_exists('id', $verb) ? $verb['id'] : '';
      $display = array_key_exists('display', $verb) ? $this->getLocaleString($verb['display']) : '';
    }

    $this->verb = [
      'id' => $id,
      'display' => $display,
    ];
  }


  public function setObject() {
    $id = $name = $description = $choices = $correct_responses_pattern = $h5pContentId = $h5pSubContentId = NULL;
    if (is_array($this->data) && array_key_exists('object', $this->data)) {
      $object = $this->data['object'];

      $id = array_key_exists('id', $object) ? $object['id'] : '';
      $definition = array_key_exists('definition', $object) ? $this->getObjectDefinition($object['definition']) : '';

      if ('' !== $definition) {
        $name = $definition['name'];
        $description = $definition['description'];
        $choices = $definition['choices'];
        $correct_responses_pattern = $definition['correctResponsesPattern'];

        if (array_key_exists('extensions', $object['definition'])) {
          $h5pContentId = $this->getH5pContentId($object['definition']['extensions'], 'contentIdKey');
          $h5pSubContentId = $this->getH5pContentId($object['definition']['extensions'], 'subContentIdKey');
        }
      }
    }

    $this->object = [
      'id' => $id,
      'name' => isset($name) ? $name : '',
      'description' => isset($description) ? $description : '',
      'choices' => isset($choices) ? $choices : '',
      'correctResponsesPattern' => isset($correct_responses_pattern) ? $correct_responses_pattern : '',
      'h5pContentId' => $h5pContentId,
      'h5pSubContentId' => $h5pSubContentId,
    ];
  }

  public function setResult() {
    $this->result = [];
    if (is_array($this->data) && array_key_exists('result', $this->data)) {
      $result = $this->data['result'];

      $response = array_key_exists('response', $result) ? $result['response'] : '';
      $scores = array_key_exists('score', $result) ? $this->getResultScores($result['score']) : '';
      if ('' !== $scores) {
        $score_raw = $scores['score_raw'];
        $score_scaled = $scores['score_scaled'];
      }
      $completion = array_key_exists('completion', $result) ? $result['completion'] : '';
      $success = array_key_exists('success', $result) ? $result['success'] : '';
      $duration = array_key_exists('duration', $result) ? $result['duration'] : '';

      $this->result = [
        'response' => isset($response) ? $response : NULL,
        'score_raw' => isset($score_raw) ? $score_raw : NULL,
        'score_scaled' => isset($score_scaled) ? $score_scaled : NULL,
        'completion' => isset($completion) ? $completion : FALSE,
        'success' => isset($success) ? $success : FALSE,
        'duration' => isset($duration) ? $duration : NULL,
      ];
    }

    $this->result = [
      'response' => isset($response) ? $response : NULL,
      'score_raw' => isset($score_raw) ? $score_raw : NULL,
      'score_scaled' => isset($score_scaled) ? $score_scaled : NULL,
      'completion' => isset($completion) ? $completion : FALSE,
      'success' => isset($success) ? $success : FALSE,
      'duration' => isset($duration) ? $duration : NULL,
    ];
  }

  /**
   * Flatten xAPI account object.
   *
   * @param array $account The accout object.
   *
   * @return string Flattened account object.
   */
  private function getAccount($account) {
    if (!is_array($account) || empty($account)) {
      return '';
    }

    $name = (array_key_exists('name', $account)) ? $account['name'] : '';
    $homepage = (array_key_exists('homePage', $account)) ? $account['homePage'] : '';

    if ('' !== $name && '' !== $homepage) {
      $homepage = ' (' . $homepage . ')';
    }

    return $name . $homepage;
  }

  /**
   * Flatten xAPI member object.
   *
   * @param array $members The members object.
   *
   * @return string Flattened member object.
   */
  private function getMembers($members) {
    if (!is_array($members) || empty($members)) {
      return '';
    }

    $output = [];
    foreach ($members as $member) {
      array_push($output, $this->getAgent($member));
    }
    return implode($output, ', ');
  }

  /**
   * Flatten xAPI agent object.
   *
   * @param array $agent The agent object.
   *
   * @return string Agent data.
   */
  private function getAgent($agent) {
    if (!is_array($agent) || empty($agent)) {
      return '';
    }

    $name = (array_key_exists('name', $agent)) ? $agent['name'] : '';
    $ifi = $this->getActorInverseFunctionalIdentifier($agent);

    if ('' !== $name && '' !== $ifi) {
      $name = ' (' . $name . ')';
    }

    return $ifi . $name;
  }

  /**
   * Flatten xAPI choices object.
   *
   * @param array $choices Choices object.
   *
   * @return string Flattened choices object.
   */
  private function getDefinitionChoices($choices) {
    if (!is_array($choices) || empty($choices)) {
      return '';
    }

    $output = [];
    foreach ($choices as $choice) {
      $id = array_key_exists('id', $choice) ? $choice['id'] : '';
      $description = array_key_exists('description', $choice) ?
        $this->getLocaleString($choice['description']) : '';

      array_push($output, '[' . $id . '] ' . $description);
    }
    return implode($output, ', ');
  }

  /**
   * Flatten xAPI correctResponsesPattern object.
   *
   * @param array $correct_responses_patterns Correct pattern object.
   *
   * @return string Flattened correct responses pattern.
   */
  private function getCorrectResponsesPattern($correct_responses_patterns) {
    if (!is_array($correct_responses_patterns) || empty($correct_responses_patterns)) {
      return '';
    }

    $output = [];
    foreach ($correct_responses_patterns as $key => $pattern) {
      array_push($output, '[' . $key . ']: ' . $pattern);
    }

    return implode($output, ', ');
  }

  /**
   * Get score details from xAPI score object.
   *
   * @param array $scores The scores.
   *
   * @return array scores.
   */
  private function getResultScores($scores) {
    if (is_array($scores)) {
      $score_raw = array_key_exists('raw', $scores) ? $scores['raw'] : '';
      $score_scaled = array_key_exists('scaled', $scores) ? $scores['scaled'] : '';
    }

    return [
      'score_raw' => isset($score_raw) ? $score_raw : '',
      'score_scaled' => isset($score_scaled) ? $score_scaled : '',
    ];
  }

  /**
   * Get local string from xAPI language map object.
   *
   * @param array $language_map The language map.
   *
   * @return string Local string.
   */
  private function getLocaleString($language_map) {
    if (!is_array($language_map) || empty($language_map)) {
      return '';
    }

    $locale = str_replace('_', '-', \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId());
    if (array_key_exists($locale, $language_map)) {
      return $language_map[$locale];
    }

    $locale_default = 'en-US';
    if (array_key_exists($locale_default, $language_map)) {
      return $language_map[$locale_default];
    }
    return array_values($language_map)[0];
  }

  /**
   * Get xAPI description object.
   *
   * @param array $definition The definition.
   *
   * @return array Description object.
   */
  private function getObjectDefinition($definition) {
    $name = $description = $choices = $responses = NULL;
    if (is_array($definition)) {
      $name = array_key_exists('name', $definition) ? $this->getLocaleString($definition['name']) : '';
      $description = array_key_exists('description', $definition) ? $this->getLocaleString($definition['description']) : '';
      $choices = array_key_exists('choices', $definition) ? $this->getDefinitionChoices($definition['choices']) : '';
      $responses = array_key_exists('correctResponsesPattern', $definition) ? $this->getCorrectResponsesPattern($definition['correctResponsesPattern']) : '';
    }

    return [
      'name' => $name,
      'description' => $description,
      'choices' => $choices,
      'correctResponsesPattern' => $responses,
    ];
  }

  public function getH5pContentId(array $array, $idKey) {
    if (!empty($key = $this->keys[$idKey])) {
      if (array_key_exists($key, $array)) {
        $contentId = $array[$key];
      }
    }
    return $contentId;
  }


}
