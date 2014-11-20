<?php

namespace Drupal\quiz\Helper\Quiz;

use Drupal\quiz\Entity\Result;

class FeedbackHelper {

  /**
   * Get the feedback options for Quizzes.
   */
  public function getOptions() {
    $feedback_options = array(
        'attempt'           => t('Attempt'),
        'correct'           => t('Whether correct'),
        'score'             => t('Score'),
        'answer_feedback'   => t('Answer feedback'),
        'question_feedback' => t('Question feedback'),
        'solution'          => t('Correct answer'),
        'quiz_feedback'     => t('@quiz feedback', array('@quiz' => QUIZ_NAME)),
    );

    drupal_alter('quiz_feedback_options', $feedback_options);

    return $feedback_options;
  }

  /**
   * Menu access check for question feedback.
   */
  public function canAccess($quiz, $page_number) {
    if ($page_number <= 0 || !array_filter($quiz->review_options['question'])) {
      return FALSE;
    }

    $result_id = empty($_SESSION['quiz'][$quiz->qid]['result_id']) ? $_SESSION['quiz']['temp']['result_id'] : $_SESSION['quiz'][$quiz->qid]['result_id'];
    $result = quiz_result_load($result_id);

    return quiz_answer_controller()
        ->loadByResultAndQuestion($result_id, $result->layout[$page_number]['vid']);
  }

}
