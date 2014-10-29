<?php

namespace Drupal\quiz\Helper\Quiz;

use Drupal\quiz\Entity\QuizEntity;

class ResultHelper {

  /**
   * Update a score for a quiz.
   *
   * This updates the quiz node results table.
   *
   * It is used in cases where a quiz score is changed after the quiz has been
   * taken. For example, if a long answer question is scored later by a human,
   * then the quiz should be updated when that answer is scored.
   *
   * Important: The value stored in the table is the *percentage* score.
   *
   * @param $quiz
   *   The quiz node for the quiz that is being scored.
   * @param $result_id
   *   The result ID to update.
   * @return
   *   The score as an integer representing percentage. E.g. 55 is 55%.
   */
  public function updateTotalScore($quiz, $result_id) {
    global $user;

    $score = $this->calculateScore($quiz, $result_id);
    db_update('quiz_results')
      ->fields(array(
          'score' => $score['percentage_score'],
      ))
      ->condition('result_id', $result_id)
      ->execute();

    if ($score['is_evaluated']) {
      // Call hook_quiz_scored().
      module_invoke_all('quiz_scored', $quiz, $score, $result_id);
      $this->maintainResult($user, $quiz, $result_id);
      db_update('quiz_results')
        ->fields(array('is_evaluated' => 1))
        ->condition('result_id', $result_id)
        ->execute();
    }

    return $score['percentage_score'];
  }

  /**
   * Delete quiz results.
   *
   * @TODO: Should use entity_delete_multiple($entity_type, $ids);
   *
   * @param $result_ids
   *   Result ids for the results to be deleted.
   */
  public function deleteByIds($result_ids) {
    if (empty($result_ids)) {
      return;
    }

    $sql = 'SELECT result_id, question_nid, question_vid FROM {quiz_results_answers}
          WHERE result_id IN(:result_id)';
    $result = db_query($sql, array(':result_id' => $result_ids));
    foreach ($result as $record) {
      quiz_question_delete_result($record->result_id, $record->question_nid, $record->question_vid);
    }

    db_delete('quiz_results_answers')
      ->condition('result_id', $result_ids, 'IN')
      ->execute();

    db_delete('quiz_results')
      ->condition('result_id', $result_ids, 'IN')
      ->execute();
  }

  /**
   * Load a specific result answer.
   */
  public function loadAnswerResult($result_id, $question_nid, $question_vid) {
    $sql = 'SELECT * '
      . ' FROM {quiz_results_answers} '
      . ' WHERE result_id = :result_id '
      . '   AND question_nid = :nid '
      . '   AND question_vid = :vid';
    $params = array(':result_id' => $result_id, ':nid' => $question_nid, ':vid' => $question_vid);
    if ($row = db_query($sql, $params)->fetch()) {
      return entity_load_single('quiz_result_answer', $row->result_answer_id);
    }
  }

  /**
   * Get answer data for a specific result.
   *
   * @param QuizEntity $quiz
   * @param int $result_id
   * @return
   *   Array of answers.
   */
  public function getAnswers($quiz, $result_id) {
    $sql = "SELECT ra.question_nid, ra.question_vid, n.type, rs.max_score, qt.max_score as term_max_score "
      . " FROM {quiz_results_answers} ra "
      . "   LEFT JOIN {node} n ON ra.question_nid = n.nid"
      . "   LEFT JOIN {quiz_results} r ON ra.result_id = r.result_id"
      . "   LEFT OUTER JOIN {quiz_relationship} rs ON (ra.question_vid = rs.question_vid) AND rs.quiz_vid = r.quiz_vid"
      . "   LEFT OUTER JOIN {quiz_terms} qt ON (qt.vid = :vid AND qt.tid = ra.tid) "
      . " WHERE ra.result_id = :rid "
      . " ORDER BY ra.number, ra.answer_timestamp";
    $ids = db_query($sql, array(':vid' => $quiz->vid, ':rid' => $result_id));
    while ($line = $ids->fetch()) {
      if ($report = $this->getAnswer($quiz, $line, $result_id)) {
        $questions[] = $report;
      }
    }
    return !empty($questions) ? $questions : array();
  }

  private function getAnswer($quiz, $db_row, $result_id) {
    // Questions picked from term id's won't be found in the quiz_relationship table
    if ($db_row->max_score === NULL) {
      if ($quiz->randomization == 2 && isset($quiz->tid) && $quiz->tid > 0) {
        $db_row->max_score = $quiz->max_score_for_random;
      }
      elseif ($quiz->randomization == 3) {
        $db_row->max_score = $db_row->term_max_score;
      }
    }

    if (!$module = quiz_question_module_for_type($db_row->type)) {
      return;
    }

    // Invoke hook_get_report().
    if (!$report = module_invoke($module, 'get_report', $db_row->question_nid, $db_row->question_vid, $result_id)) {
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
   * Calculates the score user received on quiz.
   *
   * @param $quiz
   *   The quiz node.
   * @param $result_id
   *   Quiz result ID.
   *
   * @return array
   *   Contains three elements: question_count, num_correct and percentage_score.
   */
  public function calculateScore($quiz, $result_id) {
    // 1. Fetch all questions and their max scores
    $questions = db_query('SELECT a.question_nid, a.question_vid, n.type, r.max_score
      FROM {quiz_results_answers} a
      LEFT JOIN {node} n ON (a.question_nid = n.nid)
      LEFT OUTER JOIN {quiz_relationship} r ON (r.question_vid = a.question_vid) AND r.quiz_vid = :vid
      WHERE result_id = :rid', array(':vid' => $quiz->vid, ':rid' => $result_id));

    // 2. Callback into the modules and let them do the scoring. @todo after 4.0: Why isn't the scores already saved? They should be
    // Fetched from the db, not calculated....
    $scores = array();
    $count = 0;
    foreach ($questions as $question) {
      // Questions picked from term id's won't be found in the quiz_relationship table
      if ($question->max_score === NULL && isset($quiz->tid) && $quiz->tid > 0) {
        $question->max_score = $quiz->max_score_for_random;
      }

      // Invoke hook_quiz_question_score().
      // We don't use module_invoke() because (1) we don't necessarily want to wed
      // quiz type to module, and (2) this is more efficient (no NULL checks).
      $mod = quiz_question_module_for_type($question->type);
      if (!$mod) {
        continue;
      }
      $function = $mod . '_quiz_question_score';

      if (function_exists($function)) {
        // Allow for max score to be considered.
        $scores[] = $function($quiz, $question->question_nid, $question->question_vid, $result_id);
      }
      else {
        drupal_set_message(TableSortTest('A quiz question could not be scored: No scoring info is available'), 'error');
        $dummy_score = new stdClass();
        $dummy_score->possible = 0;
        $dummy_score->attained = 0;
        $scores[] = $dummy_score;
      }
      ++$count;
    }

    // 3. Sum the results.
    $possible_score = 0;
    $total_score = 0;
    $is_evaluated = TRUE;
    foreach ($scores as $score) {
      $possible_score += $score->possible;
      $total_score += $score->attained;
      if (isset($score->is_evaluated)) {
        // Flag the entire quiz if one question has not been evaluated.
        $is_evaluated &= $score->is_evaluated;
      }
    }

    // 4. Return the score.
    return array(
        'question_count'   => $count,
        'possible_score'   => $possible_score,
        'numeric_score'    => $total_score,
        'percentage_score' => ($possible_score == 0) ? 0 : round(($total_score * 100) / $possible_score),
        'is_evaluated'     => $is_evaluated,
    );
  }

  /**
   * Deletes all results associated with a given user.
   *
   * @param int $uid
   *  The users id
   */
  public function deleteByUserId($uid) {
    $res = db_query("SELECT result_id FROM {quiz_results} WHERE uid = :uid", array(
        ':uid' => $uid));
    $result_ids = array();
    while ($result_id = $res->fetchField()) {
      $result_ids[] = $result_id;
    }
    $this->deleteByIds($result_ids);
  }

  /**
   * Deletes results for a quiz according to the keep results setting
   *
   * @param QuizEntity $quiz
   *  The quiz node to be maintained
   * @param int $result_id
   *  The result id of the latest result for the current user
   * @return
   *  TRUE if results where deleted.
   */
  public function maintainResult($account, $quiz, $result_id) {
    // Do not delete results for anonymous users
    if ($account->uid == 0) {
      return;
    }

    switch ($quiz->keep_results) {
      case QUIZ_KEEP_ALL:
        return FALSE;
      case QUIZ_KEEP_BEST:
        $best_result_id = db_query(
          'SELECT result_id FROM {quiz_results}
           WHERE quiz_qid = :qid
             AND uid = :uid
             AND is_evaluated = :is_evaluated
           ORDER BY score DESC', array(
            ':qid'          => $quiz->qid,
            ':uid'          => $account->uid,
            ':is_evaluated' => 1))->fetchField();
        if (!$best_result_id) {
          return;
        }

        $res = db_query('SELECT result_id
          FROM {quiz_results}
          WHERE quiz_qid = :qid
            AND uid = :uid
            AND result_id != :best_rid
            AND is_evaluated = :is_evaluated', array(
            ':qid'          => $quiz->qid,
            ':uid'          => $account->uid,
            ':is_evaluated' => 1,
            ':best_rid'     => $best_result_id
        ));
        $result_ids = array();
        while ($result_id2 = $res->fetchField()) {
          $result_ids[] = $result_id2;
        }
        $this->deleteByIds($result_ids);
        return !empty($result_ids);
      case QUIZ_KEEP_LATEST:
        $res = db_query('SELECT result_id
            FROM {quiz_results}
            WHERE quiz_qid = :qid
              AND uid = :uid
              AND is_evaluated = :is_evaluated
              AND result_id != :result_id', array(
            ':qid'          => $quiz->qid,
            ':uid'          => $account->uid,
            ':is_evaluated' => 1,
            ':result_id'    => $result_id
        ));
        $result_ids = array();
        while ($result_id2 = $res->fetchField()) {
          $result_ids[] = $result_id2;
        }
        $this->deleteByIds($result_ids);
        return !empty($result_ids);
    }
  }

  /**
   * Delete quiz responses for quizzes that haven't been finished.
   *
   * This was _quiz_delete_old_in_progress()
   *
   * @param $quiz
   *   A quiz node where old in progress results shall be deleted.
   * @param $uid
   *   The userid of the user the old in progress results belong to.
   */
  public function deleteIncompletedResultsByUserId($quiz, $uid) {
    $res = db_query('SELECT qnr.result_id
          FROM {quiz_results} qnr
          WHERE qnr.uid = :uid
            AND qnr.quiz_qid = :qid
            AND qnr.time_end = :time_end
            AND qnr.quiz_vid < :vid', array(
        ':uid'      => $uid,
        ':qid'      => $quiz->qid,
        ':time_end' => 1,
        ':vid'      => $quiz->vid));
    $result_ids = array();
    while ($result_id = $res->fetchField()) {
      $result_ids[] = $result_id;
    }
    $this->deleteByIds($result_ids);
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
   * @param $quiz
   *   The quiz node object.
   * @param $score
   *   The score information as returned by quiz_calculate_score().
   * @return
   *   Filtered summary text or null if we are not displaying any summary.
   */
  public function getSummaryText($quiz, $score) {
    $summary = array();
    $admin = arg(0) === 'admin';
    $quiz_format = (isset($quiz->body[LANGUAGE_NONE][0]['format'])) ? $quiz->body[LANGUAGE_NONE][0]['format'] : NULL;

    if (!$admin) {
      if (!empty($score['result_option'])) {
        // Unscored quiz, return the proper result option.
        $summary['result'] = check_markup($score['result_option'], $quiz_format);
      }
      else {
        $result_option = $this->pickResultOption($quiz, $score['percentage_score']);
        $summary['result'] = is_object($result_option) ? check_markup($result_option->option_summary, $result_option->option_summary_format) : '';
      }
    }

    // If we are using pass/fail, and they passed.
    if ($quiz->pass_rate > 0 && $score['percentage_score'] >= $quiz->pass_rate) {
      // If we are coming from the admin view page.
      if ($admin) {
        $summary['passfail'] = TableSortTest('The user passed this quiz.');
      }
      elseif (variable_get('quiz_use_passfail', 1) == 0) {
        // If there is only a single summary text, use this.
        if (trim($quiz->summary_default) != '') {
          $summary['passfail'] = check_markup($quiz->summary_default, $quiz_format);
        }
      }
      elseif (trim($quiz->summary_pass) != '') {
        // If there is a pass summary text, use this.
        $summary['passfail'] = check_markup($quiz->summary_pass, $quiz->summary_pass_format);
      }
    }
    // If the user did not pass or we are not using pass/fail.
    else {
      // If we are coming from the admin view page, only show a summary if we are
      // using pass/fail.
      if ($admin) {
        if ($quiz->pass_rate > 0) {
          $summary['passfail'] = TableSortTest('The user failed this quiz.');
        }
        else {
          $summary['passfail'] = TableSortTest('the user completed this quiz.');
        }
      }
      elseif (trim($quiz->summary_default) != '') {
        $summary['passfail'] = check_markup($quiz->summary_default, $quiz->summary_default_format);
      }
    }
    return $summary;
  }

  /**
   * Get summary text for a particular score from a set of result options.
   *
   * @param QuizEntity $quiz_id
   * @param int $score
   *   The user's final score.
   *
   * @return
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
