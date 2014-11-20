<?php

namespace Drupal\quiz\Helper\Quiz;

class FeedbackHelper {

  /**
   * Menu access check for question feedback.
   */
  public function canAccess($quiz, $page_number) {
    if (($page_number <= 0) || !array_filter($quiz->review_options['question'])) {
      return FALSE;
    }

    $result_id = empty($_SESSION['quiz'][$quiz->qid]['result_id']) ? $_SESSION['quiz']['temp']['result_id'] : $_SESSION['quiz'][$quiz->qid]['result_id'];
    $result = quiz_result_load($result_id);
    $question_vid = $result->layout[$page_number]['vid'];

    return quiz_answer_controller()
        ->loadByResultAndQuestion($result_id, $question_vid);
  }

}
