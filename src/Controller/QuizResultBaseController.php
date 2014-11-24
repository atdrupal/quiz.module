<?php

namespace Drupal\quiz\Controller;

use Drupal\quiz\Entity\QuizEntity;
use Drupal\quiz\Entity\Result;

abstract class QuizResultBaseController {

  /** @var QuizEntity */
  protected $quiz;

  /** @var QuizEntity */
  protected $quiz_revision;

  /** @var Result */
  protected $result;

  /** @var int */
  protected $quiz_id;

  /**
   * The score information as returned by quiz_calculate_score().
   */
  protected $score;

  public function __construct(QuizEntity $quiz, QuizEntity $quiz_revision, $result) {
    $this->quiz = $quiz;
    $this->quiz_revision = $quiz_revision;
    $this->result = $result;
    $this->quiz_id = $this->result->quiz_qid;
    $this->score = quiz_result_controller()->getScoreIO()->calculate($this->result);
  }

  /**
   * Get answer data for a specific result.
   *
   * @param QuizEntity $this->quiz_revision
   * @param int $this->result->result_id
   * @return
   *   Array of answers.
   */
  protected function getAnswers() {
    $sql = "SELECT ra.question_qid, ra.question_vid, question.type, rs.max_score, qt.max_score as term_max_score"
      . " FROM {quiz_results_answers} ra "
      . "   LEFT JOIN {quiz_question} question ON ra.question_qid = question.qid"
      . "   LEFT JOIN {quiz_results} r ON ra.result_id = r.result_id"
      . "   LEFT OUTER JOIN {quiz_relationship} rs ON (ra.question_vid = rs.question_vid) AND rs.quiz_vid = r.quiz_vid"
      . "   LEFT OUTER JOIN {quiz_terms} qt ON (qt.vid = :vid AND qt.tid = ra.tid) "
      . " WHERE ra.result_id = :rid "
      . " ORDER BY ra.number, ra.answer_timestamp";
    $ids = db_query($sql, array(':vid' => $this->quiz_revision->vid, ':rid' => $this->result->result_id));
    while ($db_row = $ids->fetch()) {
      if ($report = $this->getAnswer($db_row)) {
        $answers[] = $report;
      }
    }
    return !empty($answers) ? $answers : array();
  }

  private function getAnswer($db_row) {
    // Questions picked from term id's won't be found in the quiz_relationship table
    if ($db_row->max_score === NULL) {
      if ($this->quiz_revision->randomization == 2 && isset($this->quiz_revision->tid) && $this->quiz_revision->tid > 0) {
        $db_row->max_score = $this->quiz_revision->max_score_for_random;
      }
      elseif ($this->quiz_revision->randomization == 3) {
        $db_row->max_score = $db_row->term_max_score;
      }
    }

    if (!$module = quiz_question_module_for_type($db_row->type)) {
      return;
    }

    // Invoke hook_get_report().
    if (!$report = module_invoke($module, 'get_report', $db_row->question_qid, $db_row->question_vid, $this->result->result_id)) {
      return;
    }

    // Add max score info to the question.
    if (!isset($report->score_weight)) {
      $report->qnr_max_score = $db_row->max_score;
      $report->score_weight = !$report->max_score ? 0 : ($db_row->max_score / $report->max_score);
    }

    return $report;
  }

  /**
   * Get the summary message for a completed quiz.
   *
   * Summary is determined by whether we are using the pass / fail options, how
   * the user did, and where the method is called from.
   *
   * @todo Need better feedback for when a user is viewing their quiz results
   *   from the results list (and possibily when revisiting a quiz they can't take
   *   again).
   *
   * @return
   *   Filtered summary text or null if we are not displaying any summary.
   */
  public function getSummaryText() {
    $summary = array();
    $admin = arg(0) === 'admin';
    $quiz_format = (isset($this->quiz_revision->body[LANGUAGE_NONE][0]['format'])) ? $this->quiz_revision->body[LANGUAGE_NONE][0]['format'] : NULL;

    if (!$admin) {
      if (!empty($this->score['result_option'])) {
        // Unscored quiz, return the proper result option.
        $summary['result'] = check_markup($this->score['result_option'], $quiz_format);
      }
      else {
        $result_option = $this->pickResultOption($this->quiz_revision, $this->score['percentage_score']);
        $summary['result'] = is_object($result_option) ? check_markup($result_option->option_summary, $result_option->option_summary_format) : '';
      }
    }

    // If we are using pass/fail, and they passed.
    if ($this->quiz_revision->pass_rate > 0 && $this->score['percentage_score'] >= $this->quiz_revision->pass_rate) {
      // If we are coming from the admin view page.
      if ($admin) {
        $summary['passfail'] = t('The user passed this @quiz.', array('@quiz' => QUIZ_NAME));
      }
      elseif (variable_get('quiz_use_passfail', 1) == 0) {
        // If there is only a single summary text, use this.
        if (trim($this->quiz_revision->summary_default) != '') {
          $summary['passfail'] = check_markup($this->quiz_revision->summary_default, $quiz_format);
        }
      }
      elseif (trim($this->quiz_revision->summary_pass) != '') {
        // If there is a pass summary text, use this.
        $summary['passfail'] = check_markup($this->quiz_revision->summary_pass, $this->quiz_revision->summary_pass_format);
      }
    }
    // If the user did not pass or we are not using pass/fail.
    else {
      // If we are coming from the admin view page, only show a summary if we are
      // using pass/fail.
      if ($admin) {
        if ($this->quiz_revision->pass_rate > 0) {
          $summary['passfail'] = t('The user failed this @quiz.', array('@quiz' => QUIZ_NAME));
        }
        else {
          $summary['passfail'] = t('the user completed this @quiz.', array('@quiz' => QUIZ_NAME));
        }
      }
      elseif (trim($this->quiz_revision->summary_default) != '') {
        $summary['passfail'] = check_markup($this->quiz_revision->summary_default, $this->quiz_revision->summary_default_format);
      }
    }
    return $summary;
  }

  /**
   * Get summary text for a particular score from a set of result options.
   *
   * @param QuizEntity $quiz
   * @param int $score
   *   The user's final score.
   *
   * @return string
   *   Summary text for the user's score.
   */
  private function pickResultOption(QuizEntity $quiz, $score) {
    foreach ($quiz->resultoptions as $option) {
      if ($score < $option['option_start'] || $score > $option['option_end']) {
        continue;
      }
      return (object) array('option_summary' => $option['option_summary'], 'option_summary_format' => $option['option_summary_format']);
    }
  }

}
