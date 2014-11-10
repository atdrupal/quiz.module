<?php

namespace Drupal\quiz\Entity;

use Drupal\quiz_question\QuizQuestionResponse;
use EntityAPIController;

class AnswerController extends EntityAPIController {

  /**
   * Load answer by Result & questions IDs.
   *
   * @param int $result_id
   * @param int $question_vid
   * @return Answer
   */
  public function loadByResultAndQuestion($result_id, $question_vid) {
    $sql = 'SELECT * FROM {quiz_results_answers} WHERE result_id = :result_id AND question_vid = :vid';
    $params = array(':result_id' => $result_id, ':vid' => $question_vid);
    if ($row = db_query($sql, $params)->fetch()) {
      return entity_load_single('quiz_result_answer', $row->result_answer_id);
    }
  }

  /**
   * Get an instance of a quiz question responce.
   *
   * Get information about the class and use it to construct a new
   * object of the appropriate type.
   *
   * @param int $result_id
   *  Result id
   * @param $question
   *  The question node (not a QuizQuestion instance)
   * @param $answer
   *  Resonce to the answering form.
   * @param int $question_nid
   *  Question node id
   * @param int $question_vid
   *  Question node version id
   * @return \Drupal\quiz_question\QuizQuestionResponse
   *  The appropriate QuizQuestionResponce extension instance
   */
  public function getInstance($result_id, $question, $answer = NULL, $question_nid = NULL, $question_vid = NULL) {
    // We cache responses to improve performance
    static $responses = array();

    if (is_object($question) && isset($responses[$result_id][$question->vid])) {
      // We refresh the question node in case it has been changed since we cached the response
      $responses[$result_id][$question->vid]->refreshQuestionNode($question);
      if (FALSE !== $responses[$result_id][$question->vid]->is_skipped) {
        return $responses[$result_id][$question->vid];
      }
    }

    if (isset($responses[$result_id][$question_vid]) && $responses[$result_id][$question_vid]->is_skipped !== FALSE) {
      return $responses[$result_id][$question_vid];
    }

    // If the question node isn't set we fetch it from the QuizQuestion instance
    // this responce belongs to
    if (!isset($question) && ($question_node = node_load($question_nid, $question_vid))) {
      $question = _quiz_question_get_instance($question_node, TRUE)->node;
    }

    // Cache the responce instance
    if ($question) {
      $responses[$result_id][$question->vid] = $this->doGetInstance($question, $result_id, $answer);
      return $responses[$result_id][$question->vid];
    }

    return FALSE;
  }

  private function doGetInstance($question, $result_id, $answer) {
    $info = quiz_question_get_info();
    $response_provider = new $info[$question->type]['response provider']($result_id, $question, $answer);

    if (!$response_provider instanceof QuizQuestionResponse) {
      throw new \RuntimeException('The question-response isn\'t a QuizQuestionResponse. It needs to extend the QuizQuestionResponse interface, or extend the abstractQuizQuestionResponse class.');
    }

    return $response_provider;
  }

}
