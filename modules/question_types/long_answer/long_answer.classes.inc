<?php

use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\QuestionPlugin;
use Drupal\quiz_question\QuizQuestionResponse;

/**
 * Long answer classes.
 *
 * @file
 *  Classes modelling the long answer question and the long answer question response
 */

/**
 * Extension of QuizQuestion.
 */
class LongAnswerQuestion extends QuestionPlugin {

  /**
   * Implementation of saveEntityProperties
   * @see QuizQuestion#saveEntityProperties($is_new)
   */
  public function saveEntityProperties($is_new = FALSE) {
    if (!isset($this->question->feedback)) {
      $this->question->feedback = '';
    }

    if ($is_new || $this->question->revision == 1) {
      db_insert('quiz_long_answer_properties')
        ->fields(array(
            'qid'    => $this->question->qid,
            'vid'    => $this->question->vid,
            'rubric' => $this->question->rubric,
        ))
        ->execute();
    }
    else {
      db_update('quiz_long_answer_properties')
        ->fields(array('rubric' => isset($this->question->rubric) ? $this->question->rubric : ''))
        ->condition('qid', $this->question->qid)
        ->condition('vid', $this->question->vid)
        ->execute();
    }
  }

  /**
   * Implementation of validateNode
   *
   * @see QuizQuestion#validateNode($form)
   */
  public function validate(array &$form) {

  }

  /**
   * Implementation of delete
   *
   * @see QuizQuestion#delete($only_this_version)
   */
  public function delete($only_this_version = FALSE) {
    if ($only_this_version) {
      db_delete('quiz_long_answer_user_answers')
        ->condition('question_qid', $this->question->qid)
        ->condition('question_vid', $this->question->vid)
        ->execute();
      db_delete('quiz_long_answer_properties')
        ->condition('qid', $this->question->qid)
        ->condition('vid', $this->question->vid)
        ->execute();
    }
    else {
      db_delete('quiz_long_answer_properties')
        ->condition('qid', $this->question->qid)
        ->execute();
      db_delete('quiz_long_answer_user_answers')
        ->condition('question_qid', $this->question->qid)
        ->execute();
    }
    parent::delete($only_this_version);
  }

  /**
   * Implementation of getNodeProperties
   *
   * @see QuizQuestion#getNodeProperties()
   */
  public function load() {
    if (isset($this->entityProperties)) {
      return $this->entityProperties;
    }
    $properties = parent::load();

    $res_a = db_query(
      'SELECT rubric
       FROM {quiz_long_answer_properties}
       WHERE qid = :qid AND vid = :vid', array(
        ':qid' => $this->question->qid,
        ':vid' => $this->question->vid))->fetchAssoc();

    if (is_array($res_a)) {
      $properties = array_merge($properties, $res_a);
    }

    return $this->entityProperties = $properties;
  }

  /**
   * Implementation of getEntityView
   *
   * @see QuizQuestion#getEntityView()
   */
  public function getEntityView() {
    $content = parent::getEntityView();

    if ($this->viewCanRevealCorrect()) {
      if (!empty($this->question->rubric)) {
        $content['answers'] = array(
            '#type'   => 'item',
            '#title'  => t('Rubric'),
            '#prefix' => '<div class="quiz-solution">',
            '#suffix' => '</div>',
            '#markup' => check_markup($this->question->rubric, $this->getFormat()),
            '#weight' => 1,
        );
      }
    }
    else {
      $content['answers'] = array(
          '#markup' => '<div class="quiz-answer-hidden">Answer hidden</div>',
          '#weight' => 1,
      );
    }
    return $content;
  }

  /**
   * Implementation of getAnweringForm
   *
   * @see QuizQuestion#getAnsweringForm($form_state, $result_id)
   */
  public function getAnsweringForm(array $form_state = NULL, $result_id) {
    $element = parent::getAnsweringForm($form_state, $result_id);

    $element += array(
        '#type'        => 'textarea',
        '#title'       => t('Answer'),
        '#description' => t('Enter your answer here. If you need more space, click on the grey bar at the bottom of this area and drag it down.'),
        '#rows'        => 15,
        '#cols'        => 60,
      //'#theme' =>'long_answer_answering_form',
    );
    if (isset($result_id)) {
      $response = new LongAnswerResponse($result_id, $this->question);
      $element['#default_value'] = $response->getResponse();
    }
    return $element;
  }

  /**
   * Question response validator.
   */
  public function getAnsweringFormValidate(array &$form, array &$form_state = NULL) {
    if ($form_state['values']['question'][$this->question->qid] == '') {
      form_set_error('', t('You must provide an answer.'));
    }
  }

  /**
   * Implementation of getCreationForm
   *
   * @see QuizQuestion#getCreationForm($form_state)
   */
  public function getCreationForm(array &$form_state = NULL) {
    $form['rubric'] = array(
        '#type'          => 'textarea',
        '#title'         => t('Rubric'),
        '#description'   => t('Specify the criteria for grading the response.'),
        '#default_value' => isset($this->question->rubric) ? $this->question->rubric : '',
        '#size'          => 60,
        '#maxlength'     => 2048,
        '#required'      => FALSE,
    );
    return $form;
  }

  /**
   * Implementation of getMaximumScore
   *
   * @see QuizQuestion#getMaximumScore()
   */
  public function getMaximumScore() {
    return variable_get('long_answer_default_max_score', 10);
  }

}

/**
 * Extension of QuizQuestionResponse
 */
class LongAnswerResponse extends QuizQuestionResponse {

  /**
   * Get all scores that have not yet been evaluated.
   *
   * @param $count
   *  Number of items to return (default: 50).
   * @param $offset
   *  Where in the results we should start (default: 0).
   *
   * @return
   *  Array of objects describing unanswered questions. Each object will have result_id, question_qid, and question_vid.
   */
  public static function fetchAllUnscoredAnswers($count = 50, $offset = 0) {
    global $user;

    $query = db_select('quiz_long_answer_user_answers', 'answer');
    $query->fields('answer', array('result_id', 'question_qid', 'question_vid', 'answer_feedback', 'answer_feedback_format'));
    $query->fields('question_revision', array('title'));
    $query->fields('qr', array('time_end', 'time_start', 'uid'));
    $query->join('node_revision', 'question_revision', 'answer.question_vid = question_revision.vid');
    $query->join('quiz_results', 'qr', 'answer.result_id = qr.result_id');
    $query->join('quiz_entity', 'quiz', 'qr.quiz_qid = quiz.qid');
    $query->condition('answer.is_evaluated', 0);

    if (user_access('score own quiz') && user_access('score taken quiz answer')) {
      $query->condition(db_or()->condition('quiz.uid', $user->uid)->condition('qr.uid', $user->uid));
    }
    elseif (user_access('score own quiz')) {
      $query->condition('quiz.uid', $user->uid);
    }
    elseif (user_access('score taken quiz answer')) {
      $query->condition('qr.uid', $user->uid);
    }

    $unscored = array();
    foreach ($query->execute() as $row) {
      $unscored[] = $row;
    }
    return $unscored;
  }

  /**
   * Given a quiz, return a list of all of the unscored answers.
   *
   * @param $qid
   *  Question ID for the quiz to check.
   * @param $vid
   *  Version ID for the quiz to check.
   * @param $count
   *  Number of items to return (default: 50).
   * @param $offset
   *  Where in the results we should start (default: 0).
   *
   * @return
   *  Indexed array of result IDs that need to be scored.
   */
  public static function fetchUnscoredAnswersByQuestion($qid, $vid, $count = 50, $offset = 0) {
    $results = db_query('SELECT result_id FROM {quiz_long_answer_user_answers}
      WHERE is_evaluated = :is_evaluated
      AND question_qid = :question_qid
      AND question_vid = :question_vid', array(
        ':is_evaluated' => 0,
        ':question_qid' => $qid,
        ':question_vid' => $vid
    ));
    $unscored = array();
    foreach ($results as $row) {
      $unscored[] = $row->result_id;
    }
    return $unscored;
  }

  /**
   * ID of the answer.
   */
  protected $answer_id = 0;

  /**
   * Constructor
   */
  public function __construct($result_id, Question $question, $answer = NULL) {
    parent::__construct($result_id, $question, $answer);

    if (!isset($answer)) {
      // Question has been answered allready. We fetch the answer data from
      // the database.
      $row = db_query(
        'SELECT answer_id, answer, is_evaluated, score, question_vid, question_qid, result_id, answer_feedback, answer_feedback_format
        FROM {quiz_long_answer_user_answers}
        WHERE question_qid = :question_id AND question_vid = :question_vid AND result_id = :rid', array(
          ':question_id'  => $question->qid,
          ':question_vid' => $question->vid,
          ':rid'          => $result_id))->fetch();
      if (!empty($row)) {
        $this->answer = $row->answer;
        $this->score = $row->score;
        $this->evaluated = $row->is_evaluated;
        $this->answer_id = $row->answer_id;
        $this->answer_feedback = $row->answer_feedback;
        $this->answer_feedback_format = $row->answer_feedback_format;
      }
    }
    else {
      $this->answer = $answer;
      $this->evaluated = FALSE;
    }
  }

  /**
   * Implementation of save
   *
   * @see QuizQuestionResponse#save()
   */
  public function save() {
    $this->answer_id = db_insert('quiz_long_answer_user_answers')
      ->fields(array(
          'answer'       => $this->answer,
          'question_qid' => $this->question->qid,
          'question_vid' => $this->question->vid,
          'result_id'    => $this->result_id,
      ))
      ->execute();
  }

  /**
   * Implementation of delete
   *
   * @see QuizQuestionResponse#delete()
   */
  public function delete() {
    db_delete('quiz_long_answer_user_answers')
      ->condition('question_qid', $this->question->qid)
      ->condition('question_vid', $this->question->vid)
      ->condition('result_id', $this->result_id)
      ->execute();
  }

  /**
   * Implementation of score
   *
   * @see QuizQuestionResponse#score()
   */
  public function score() {
    return (int) db_query('SELECT score FROM {quiz_long_answer_user_answers}
      WHERE result_id = :result_id AND question_vid = :question_vid', array(':result_id' => $this->result_id, ':question_vid' => $this->question->vid))->fetchField();
  }

  /**
   * Implementation of getResponse
   *
   * @see QuizQuestionResponse#getResponse()
   */
  public function getResponse() {
    return $this->answer;
  }

  /**
   * Implementation of getReportFormResponse
   */
  public function getReportFormResponse() {
    $data = array();

    $data[] = array(
        'choice'            => '',
        'attempt'           => $this->answer,
        'correct'           => !$this->evaluated ? t('This answer has not yet been scored.') : '',
        'score'             => $this->getScore(),
        'answer_feedback'   => check_markup($this->answer_feedback, $this->answer_feedback_format),
        'question_feedback' => '',
        'solution'          => $this->question->rubric,
    );

    return $data;
  }

  public function getReportFormAnswerFeedback() {
    return array(
        '#title'         => t('Enter feedback'),
        '#type'          => 'text_format',
        '#default_value' => isset($this->answer_feedback) ? check_markup($this->answer_feedback, $this->answer_feedback_format) : '',
        '#format'        => isset($this->answer_feedback_format) ? $this->answer_feedback_format : filter_default_format(),
        '#attributes'    => array('class' => array('quiz-report-score')),
    );
  }

  /**
   * Implementation of getReportFormSubmit
   *
   * @see QuizQuestionResponse#getReportFormSubmit()
   */
  public function getReportFormSubmit() {
    return 'long_answer_report_submit';
  }

  /**
   * Implements QuizQuestionResponse::getReportFormValidate().
   */
  public function getReportFormValidate(&$element, &$form_state) {
    $max = $this->question->max_score;
    // Check to make sure that entered score is not higher than max allowed score.
    if ($element['score']['#value'] > $max) {
      form_error($element['score'], t('The score needs to be a number between @min and @max', array('@min' => 0, '@max' => $max)));
    }
  }

}
