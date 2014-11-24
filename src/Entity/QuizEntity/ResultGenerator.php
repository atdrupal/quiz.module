<?php

namespace Drupal\quiz\Entity\QuizEntity;

use Drupal\quiz\Entity\QuizEntity;
use Drupal\quiz\Entity\Result;
use RuntimeException;

/**
 * Generate result entity with dummy page/question layout.
 *
 * This class is used when use start taking a quiz.
 */
class ResultGenerator {

  /**
   * @param QuizEntity $quiz
   * @return Result
   * @throws RuntimeException
   */
  public function generate(QuizEntity $quiz, $account) {
    if (!$questions = $quiz->getQuestionIO()->getQuestionList()) {
      throw new RuntimeException(t(
        'No questions were found. Please !assign before trying to take this @quiz.', array(
          '@quiz'   => QUIZ_NAME,
          '!assign' => l(t('assign questions'), 'quiz/' . $quiz->identifier() . '/questions')
      )));
    }
    return $this->doGenerate($quiz, $questions, $account);
  }

  private function doGenerate($quiz, $questions, $account) {
    // correct item numbers
    $count = $display_count = 0;
    $question_list = array();
    foreach ($questions as &$question) {
      $display_count++;
      $question['number'] = ++$count;
      if ($question['type'] !== 'quiz_page') {
        $question['display_number'] = $display_count;
      }
      $question_list[$count] = $question;
    }

    // Write the layout for this result.
    $result = entity_create('quiz_result', array(
        'quiz_qid'   => $quiz->identifier(),
        'quiz_vid'   => $quiz->vid,
        'uid'        => $account->uid,
        'time_start' => REQUEST_TIME,
        'layout'     => $question_list,
    ));
    $result->save();

    foreach ($question_list as $i => $question) {
      entity_create('quiz_result_answer', array(
          'result_id'    => $result->result_id,
          'question_qid' => $question['qid'],
          'question_vid' => $question['vid'],
          'number'       => $i,
      ))->save();
    }

    return quiz_result_load($result->result_id);
  }

}
