<?php

namespace Drupal\quiz_question;

use Drupal\quiz_question\QuestionPlugin;
use Drupal\quiz_question\Entity\Question;
use stdClass;

/**
 * Each question type must store its own response data and be able to calculate a score for
 * that data.
 */
abstract class QuizQuestionResponse {

  /** @var \Drupal\quiz\Entity\Result */
  protected $result;
  protected $result_id = 0;
  protected $is_correct = FALSE;
  protected $evaluated = TRUE;

  /** @var \Drupal\quiz_question\Entity\Question */
  public $question = NULL;
  public $quizQuestion = NULL;
  protected $answer = NULL;
  protected $score;
  public $is_skipped;
  public $is_doubtful;

  /**
   * Create a new user response.
   *
   * @param $result_id
   *  The result ID for the user's result set. There is one result ID per time
   *  the user takes a quiz.
   * @param Question $question
   *  The question entity.
   * @param $answer
   *  The answer (dependent on question type).
   */
  public function __construct($result_id, Question $question, $answer = NULL) {
    $this->result_id = $result_id;
    $this->result = quiz_result_load($result_id);
    $this->question = $question;
    $this->quizQuestion = $question->getPlugin();
    $this->answer = $answer;
    $result = db_query('SELECT is_skipped, is_doubtful '
      . ' FROM {quiz_results_answers} '
      . ' WHERE result_id = :result_id '
      . '   AND question_nid = :question_nid '
      . '   AND question_vid = :question_vid', array(
        ':result_id'    => $result_id,
        ':question_nid' => $question->qid,
        ':question_vid' => $question->vid
      ))->fetch();
    if (is_object($result)) {
      $this->is_doubtful = $result->is_doubtful;
      $this->is_skipped = $result->is_skipped;
    }
  }

  /**
   * @return QuestionPlugin
   */
  function getQuizQuestion() {
    return $this->quizQuestion;
  }

  /**
   * Used to refresh this instances question in case drupal has changed it.
   *
   * @param \Drupal\quiz_question\Entity\Question $newQuestion
   */
  public function refreshQuestionNode($newQuestion) {
    $this->question = $newQuestion;
  }

  /**
   * Indicate whether the response has been evaluated (scored) yet.
   * Questions that require human scoring (e.g. essays) may need to manually
   * toggle this.
   */
  public function isEvaluated() {
    return (bool) $this->evaluated;
  }

  /**
   * Check to see if the answer is marked as correct.
   *
   * This default version returns TRUE iff the score is equal to the maximum possible score.
   */
  function isCorrect() {
    return ($this->getMaxScore() == $this->getScore());
  }

  /**
   * Returns stored score if it exists, if not the score is calculated and returned.
   *
   * @param $weight_adjusted
   *  If the returned score shall be adjusted according to the max_score the question has in a quiz
   * @return
   *  Score(int)
   */
  function getScore($weight_adjusted = TRUE) {
    if ($this->is_skipped) {
      return 0;
    }

    if (!isset($this->score)) {
      $this->score = $this->score();
    }

    if (isset($this->question->score_weight) && $weight_adjusted) {
      return round($this->score * $this->question->score_weight);
    }
    return $this->score;
  }

  /**
   * Returns stored max score if it exists, if not the max score is calculated and returned.
   *
   * @param $weight_adjusted
   *  If the returned max score shall be adjusted according to the max_score the question has in a quiz
   * @return
   *  Max score(int)
   */
  public function getMaxScore($weight_adjusted = TRUE) {
    if (!isset($this->question->max_score)) {
      $this->question->max_score = $this->question->getPlugin()->getMaximumScore();
    }
    if (isset($this->question->score_weight) && $weight_adjusted) {
      return round($this->question->max_score * $this->question->score_weight);
    }
    return $this->question->max_score;
  }

  /**
   * Represent the response as a stdClass object.
   *
   * Convert data to an object that has the following properties:
   * - $score
   * - $result_id
   * - $nid
   * - $vid
   * - $is_correct
   */
  function toBareObject() {
    $response = new stdClass();
    $response->score = $this->getScore(); // This can be 0 for unscored.
    $response->question_nid = $this->question->qid;
    $response->question_vid = $this->question->vid;
    $response->result_id = $this->result_id;
    $response->is_correct = (int) $this->isCorrect();
    $response->is_evaluated = $this->isEvaluated();
    $response->is_skipped = 0;
    $response->is_doubtful = isset($_POST['is_doubtful']) ? (int) ($_POST['is_doubtful']) : 0;
    $response->is_valid = $this->isValid();
    return $response;
  }

  /**
   * Validates response from a quiz taker. If the response isn't valid the quiz taker won't be allowed to proceed.
   *
   * @return
   *  True if the response is valid.
   *  False otherwise
   */
  public function isValid() {
    return TRUE;
  }

  /**
   * Get data suitable for reporting a user's score on the question.
   * This expects an object with the following attributes:
   *
   *  answer_id; // The answer ID
   *  answer; // The full text of the answer
   *  is_evaluated; // 0 if the question has not been evaluated, 1 if it has
   *  score; // The score the evaluator gave the user; this should be 0 if is_evaluated is 0.
   *  question_vid
   *  question_nid
   *  result_id
   */
  public function getReport() {
    // Basically, we encode internal information in a
    // legacy array format for Quiz.
    $report = array(
        'answer_id'    => 0, // <-- Stupid vestige of multichoice.
        'answer'       => $this->answer,
        'is_evaluated' => $this->isEvaluated(),
        'is_correct'   => $this->isCorrect(),
        'score'        => $this->getScore(),
        'question_vid' => $this->question->vid,
        'question_nid' => $this->question->qid,
        'result_id'    => $this->result_id,
    );
    return $report;
  }

  /**
   * Creates the report form for the admin pages, and for when a user gets
   * feedback after answering questions.
   *
   * The report is a form to allow editing scores and the likes while viewing
   * the report form
   *
   * @return array $form
   *  Drupal form array
   */
  public function getReportForm() {
    global $user;

    $form = array();

    // Add general data, and data from the question type implementation
    $form['qid'] = array('#type' => 'value', '#value' => $this->question->qid);
    $form['vid'] = array('#type' => 'value', '#value' => $this->question->vid);
    $form['result_id'] = array('#type' => 'value', '#value' => $this->result_id);

    if ($this->result->canAccessOwnScore($user) && ($submit = $this->getReportFormSubmit())) {
      $form['submit'] = array('#type' => 'value', '#value' => $submit);
    }
    $form['question'] = $this->getReportFormQuestion();

    if ($this->result->canAccessOwnScore($user)) {
      $form['answer_feedback'] = $this->getReportFormAnswerFeedback();
    }

    $form['max_score'] = array(
        '#type'  => 'value',
        '#value' => $this->canReview('score') ? $this->getMaxScore() : '?',
    );

    $labels = array(
        'attempt'         => t('Your answer'),
        'choice'          => t('Choice'),
        'correct'         => t('Correct?'),
        'score'           => t('Score'),
        'answer_feedback' => t('Feedback'),
        'solution'        => t('Correct answer'),
    );
    drupal_alter('quiz_feedback_labels', $labels);

    $rows = array();
    foreach ($this->getReportFormResponse() as $idx => $row) {
      foreach (array_keys($labels) as $reviewType) {
        if (('choice' === $reviewType) || (isset($row[$reviewType]) && $this->canReview($reviewType))) {
          $rows[$idx][$reviewType] = $row[$reviewType];
        }
      }
    }

    if ($this->isEvaluated()) {
      $score = $this->getScore();
      $class = $this->isCorrect() ? 'q-correct' : 'q-wrong';
    }
    else {
      $score = t('?');
      $class = 'q-waiting';
    }

    if (quiz()->getQuizHelper()->getAccessHelper()->canAccessQuizScore($user) && $submit) {
      $form['score'] = $this->getReportFormScore();
    }

    if ($this->canReview('score') || quiz()->getQuizHelper()->getAccessHelper()->canAccessQuizScore($user)) {
      $form['score_display']['#markup'] = theme('quiz_question_score', array('score' => $score, 'max_score' => $this->getMaxScore(), 'class' => $class));
    }

    $headers = array_intersect_key($labels, $rows[0]);
    $type = $this->getQuizQuestion()->question->type;
    $form['response']['#markup'] = theme('quiz_question_feedback__' . $type, array('labels' => $headers, 'data' => $rows));

    if ($this->canReview('question_feedback')) {
      if (!empty($this->quizQuestion->question)) {
        $form['question_feedback']['#markup'] = check_markup($this->quizQuestion->question->feedback, $this->quizQuestion->question->feedback_format);
      }
    }

    $form['#theme'] = $this->getReportFormTheme();

    return $form;
  }

  /**
   * get the question part of the reportForm
   *
   * @return
   *  FAPI form array holding the question
   */
  public function getReportFormQuestion() {
    $question = clone ($this->question);
    $question->no_answer_form = TRUE;
    $output = entity_view('quiz_question', array($question), 'feedback');
    return $output['quiz_question'][$this->question->qid];
  }

  /**
   * Get the response part of the report form

   * @return
   *  Array of choices
   */
  public function getReportFormResponse() {
    $data = array();

    $data[] = array(
        'choice'            => 'True',
        'attempt'           => 'Did the user choose this?',
        'correct'           => 'Was their answer correct?',
        'score'             => 'Points earned for this answer',
        'answer_feedback'   => 'Feedback specific to the answer',
        'question_feedback' => 'General question feedback for any answer',
        'solution'          => 'Is this choice the correct solution?',
        'quiz_feedback'     => 'Quiz feedback at this time',
    );

    return $data;
  }

  public function getReportFormAnswerFeedback() {
    return FALSE;
  }

  /**
   * Get the submit function for the reportForm
   *
   * @return
   *  Submit function as a string, or FALSE if no submit function
   */
  public function getReportFormSubmit() {
    return FALSE;
  }

  /**
   * Get the validate function for the reportForm
   *
   * @return
   *  Validate function as a string, or FALSE if no validate function
   */
  public function getReportFormValidate(&$element, &$form_state) {
    return FALSE;
  }

  /**
   * Get the theme key for the reportForm
   *
   * @return
   *  Theme key as a string, or FALSE if no submit function
   */
  public function getReportFormTheme() {
    return FALSE;
  }

  /**
   * Saves the quiz result. This should only be called when an answer is
   * provided.
   */
  public function saveResult() {
    $this->save();
  }

  /**
   * Utility function that returns the format of the node body
   */
  protected function getFormat() {
    $body = field_get_items('node', $this->question, 'body');
    return ($body ? $body[0]['format'] : NULL);
  }

  /**
   * Save the current response.
   */
  abstract public function save();

  /**
   * Delete the response.
   */
  abstract public function delete();

  /**
   * Calculate the score for the response.
   */
  abstract public function score();

  /**
   * Get the user's response.
   */
  abstract public function getResponse();

  /**
   * Can the quiz taker view the requested review?
   */
  public function canReview($option) {
    $can_review = &drupal_static(__METHOD__, array());
    if (!isset($can_review[$option])) {
      $result = quiz_result_load($this->result_id);
      $can_review[$option] = $result->canReview($option);
    }
    return $can_review[$option];
  }

  /**
   * Implementation of getReportFormScore
   *
   * @see QuizQuestionResponse#getReportFormScore()
   */
  public function getReportFormScore() {
    $score = ($this->isEvaluated()) ? $this->getScore() : '';
    return array(
        '#title'            => 'Enter score',
        '#type'             => 'textfield',
        '#default_value'    => $score,
        '#size'             => 3,
        '#maxlength'        => 3,
        '#attributes'       => array('class' => array('quiz-report-score')),
        '#element_validate' => array('element_validate_integer'),
        '#required'         => TRUE,
        '#field_suffix'     => '/ ' . $this->getMaxScore(),
    );
  }

  /**
   * Set the target result ID for this Question response.
   *
   * Useful for cloning entire result sets.
   */
  public function setResultId($result_id) {
    $this->result_id = $result_id;
  }

}
