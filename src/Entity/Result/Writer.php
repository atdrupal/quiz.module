<?php

namespace Drupal\quiz\Entity\Result;

use Drupal\quiz\Entity\QuizEntity;
use stdClass;

class Writer {

  /**
   * Store a quiz question result.
   *
   * @param $quiz
   *  The quiz entity
   * @param $response
   *  Object with data about the result for a question.
   * @param $options
   *  Array with options that affect the behavior of this function.
   *  ['set_msg'] - Sets a message if the last question was skipped.
   */
  public function saveQuestionResult(QuizEntity $quiz, $response, $options) {
    $this->prepareResponse($response, $options);

    // Insert result data, or update existing data.
    $answer = (object) array(
          'result_answer_id' => $this->findAnswerId($response),
          'question_qid'     => $response->question_qid,
          'question_vid'     => $response->question_vid,
          'result_id'        => $response->result_id,
          'is_correct'       => (int) $response->is_correct,
          'points_awarded'   => round($response->score * $this->findScale($quiz, $response, $options)),
          'answer_timestamp' => REQUEST_TIME,
          'is_skipped'       => (int) $response->is_skipped,
          'is_doubtful'      => (int) $response->is_doubtful,
          'number'           => $options['question_data']['number'],
          'tid'              => ($quiz->randomization == 3 && $response->tid) ? $response->tid : 0,
    );

    entity_save('quiz_result_answer', $answer);
  }

  private function prepareResponse(\stdClass $response, $options) {
    if (isset($response->is_skipped) && $response->is_skipped == TRUE) {
      if ($options['set_msg']) {
        drupal_set_message(t('Last question skipped.'), 'status');
      }
      $response->is_correct = FALSE;
      $response->score = 0;
    }
    else {
      // Make sure this is set.
      $response->is_skipped = FALSE;
    }
    if (!isset($response->score)) {
      $response->score = $response->is_correct ? 1 : 0;
    }
  }

  /**
   * Points are stored pre-scaled in the quiz_results_answers table
   *
   * @param QuizEntity $quiz
   * @param stdClass $response
   * @return type
   */
  private function findScale(QuizEntity $quiz, stdClass $response, $options) {
    $ssql = '(SELECT max_score FROM {quiz_question_revision} WHERE qid = :question_qid AND vid = :question_vid)';

    if ($quiz->randomization < 2) {
      return db_query("
          SELECT (max_score/{$ssql}) as scale
          FROM {quiz_relationship}
          WHERE quiz_qid = :quiz_qid
            AND quiz_vid = :quiz_vid
            AND question_qid = :question_qid
            AND question_vid = :question_vid", array(
            ':quiz_qid'     => $quiz->qid,
            ':quiz_vid'     => $quiz->vid,
            ':question_qid' => $response->question_qid,
            ':question_vid' => $response->question_vid
        ))->fetchField();
    }

    if ($quiz->randomization == 2) {
      return db_query("
          SELECT (max_score_for_random/{$ssql}) as scale
          FROM {quiz_entity_revision}
          WHERE vid = :quiz_vid", array(
            ':question_qid' => $response->question_qid,
            ':question_vid' => $response->question_vid,
            ':quiz_vid'     => $quiz->vid
        ))->fetchField();
    }

    if ($quiz->randomization == 3) {
      if (isset($options['question_data']['tid'])) {
        $response->tid = $options['question_data']['tid'];
      }

      return db_query("
          SELECT (max_score/{$ssql}) as scale
          FROM {quiz_terms} WHERE vid = :vid AND tid = :tid", array(
            ':question_qid' => $response->question_qid,
            ':question_vid' => $response->question_vid,
            ':vid'          => $quiz->vid,
            ':tid'          => $response->tid
        ))->fetchField();
    }
  }

  private function findAnswerId($response) {
    return db_query("SELECT result_answer_id
        FROM {quiz_results_answers}
        WHERE question_qid = :question_qid
          AND question_vid = :question_vid
          AND result_id = :result_id", array(
          ':question_qid' => $response->question_qid,
          ':question_vid' => $response->question_vid,
          ':result_id'    => $response->result_id
      ))->fetchField();
  }

}
