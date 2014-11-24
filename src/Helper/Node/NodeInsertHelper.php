<?php

namespace Drupal\quiz\Helper\Node;

use Drupal\quiz\Entity\QuizEntity;

class NodeInsertHelper extends NodeHelper {

  /**
   * @param QuizEntity $quiz
   */
  public function execute($quiz) {
    // Need to set max_score if this is a cloned node
    $max_score = 0;

    // Copy all the questions belonging to the quiz if this is a new translation.
    if ($quiz->is_new && isset($quiz->translation_source)) {
      $this->copyQuestions($quiz);
    }

    // Add references to all the questions belonging to the quiz if this is a
    // cloned quiz (node_clone compatibility)
    if ($quiz->is_new && isset($quiz->clone_from_original_qid)) {
      $_quiz = quiz_load($quiz->clone_from_original_qid, NULL, TRUE);
      $max_score = $_quiz->max_score;
      $questions = $_quiz->getQuestionIO()->getQuestionList();

      // Format the current questions for referencing
      foreach ($questions as $question) {
        $questions[$question['qid']]->state = $question->question_status;
        $questions[$question['qid']]->refresh = 0;
      }
      # $quiz->getQuestionIO()->setRelationships($questions);
    }

    $this->presaveActions($quiz);

    // If the quiz is saved as not randomized we have to make sure that
    // questions belonging to the quiz are saved as not random
    $this->checkNumRandom($quiz);
    $this->checkNumAlways($quiz);
  }

  /**
   * Copies questions when a quiz is translated.
   *
   * @param QuizEntity $quiz
   *   The new translated quiz entity.
   */
  private function copyQuestions(QuizEntity $quiz) {
    // Find original questions.
    $query = db_query('
        SELECT question_qid, question_vid, question_status, weight, max_score, auto_update_max_score
        FROM {quiz_relationship}
        WHERE quiz_vid = :quiz_vid', array(':quiz_vid' => $quiz->translation_source->vid));
    foreach ($query as $relationship) {
      $this->copyQuestion($quiz, $relationship);
    }
  }

  private function copyQuestion(QuizEntity $quiz, $relationship) {
    $question = quiz_question_entity_load($relationship->question_qid);

    // Set variables we can't or won't carry with us to the translated node to NULL.
    $question->qid = $question->vid = $question->created = $question->changed = NULL;
    $question->revision_timestamp = $question->menu = $question->path = NULL;
    $question->files = array();
    if (isset($question->book['mlid'])) {
      $question->book['mlid'] = NULL;
    }

    // Set the correct language.
    $question->language = $quiz->language;

    // Save the node.
    node_save($question);

    // Save the relationship between the new question and the quiz.
    db_insert('quiz_relationship')
      ->fields(array(
          'quiz_qid'              => $quiz->qid,
          'quiz_vid'              => $quiz->vid,
          'question_qid'          => $question->qid,
          'question_vid'          => $question->vid,
          'question_status'       => $relationship->question_status,
          'weight'                => $relationship->weight,
          'max_score'             => $relationship->max_score,
          'auto_update_max_score' => $relationship->auto_update_max_score,
      ))
      ->execute();
  }

}
