<?php

namespace Drupal\quiz\Entity\QuizEntity;

use Drupal\quiz\Entity\QuizEntity;
use PDO;

class QuestionIO {

  private $quiz;

  public function __construct(QuizEntity $quiz) {
    $this->quiz = $quiz;
  }

  /**
   * Retrieves a list of questions (to be taken) for a given quiz.
   *
   * If the quiz has random questions this function only returns a random
   * selection of those questions. This function should be used to decide
   * what questions a quiz taker should answer.
   *
   * This question list is stored in the user's result, and may be different
   * when called multiple times. It should only be used to generate the layout
   * for a quiz attempt and NOT used to do operations on the questions inside of
   * a quiz.
   *
   * @return array[] Array of question info.
   */
  public function getQuestionList() {
    if (QUIZ_QUESTION_CATEGORIZED_RANDOM == $this->quiz->randomization) {
      return $this->buildCategoziedQuestionList();
    }
    return $this->getRequiredQuestions();
  }

  /**
   * Builds the questionlist for quizzes with categorized random questions
   */
  public function buildCategoziedQuestionList() {
    if (!$question_types = array_keys(quiz_question_get_plugin_info())) {
      return array();
    }

    $questions = array();
    $question_ids = array();
    $total_count = 0;
    foreach ($this->quiz->getTermsByVid() as $term) {
      $query = db_select('quiz_question', 'question');
      if (!empty($question_ids)) {
        $query->condition('question.qid', $question_ids, 'NOT IN');
      }
      $query->join('taxonomy_index', 'tn', 'question.nid = tn.nid');
      $result = $query
        ->fields('question', array('qid', 'vid'))
        ->fields('tn', array('tid'))
        ->condition('question.status', 1)
        ->condition('question.type', $question_types)
        ->condition('question.tid', $term->tid)
        ->range(0, $term->number)
        ->orderRandom()
        ->execute();
      $count = 0;
      while ($question = $result->fetchAssoc()) {
        $count++;
        $question['tid'] = $term->tid;
        $question['number'] = $count + $total_count;
        $questions[] = $question;
        $question_ids[] = $question['qid'];
      }
      $total_count += $count;
      if ($count < $term->number) {
        return array(); // Not enough questions
      }
    }
    return $questions;
  }

  /**
   * @return array
   */
  private function getRequiredQuestions() {
    $select = db_select('quiz_relationship', 'relationship');
    $select->innerJoin('quiz_question', 'question', 'relationship.question_nid = question.qid');

    // Sub relationship
    $cond_1 = 'relationship.qr_pid = sub_relationship.qr_id';
    $cond_2 = 'relationship.qr_pid IS NULL AND relationship.qr_id = sub_relationship.qr_id';
    $select->leftJoin('quiz_relationship', 'sub_relationship', "($cond_1) OR ($cond_2)");

    $select->addField('relationship', 'question_nid', 'qid');
    $select->addField('relationship', 'question_vid', 'vid');
    $select->addField('question', 'type');
    $select->fields('relationship', array('qr_id', 'qr_pid', 'question_status', 'weight', 'max_score'));
    $query = $select
      ->condition('relationship.quiz_vid', $this->quiz->vid)
      ->condition('relationship.question_status', QUIZ_QUESTION_ALWAYS)
      ->condition('question.status', 1)
      ->orderBy('sub_relationship.weight')
      ->orderBy('relationship.weight')
      ->execute();

    // Just to make it easier on us, let's use a 1-based index.
    $i = 1;
    $relationships = array();
    while ($relationship = $query->fetchAssoc()) {
      $relationships[$i++] = $relationship;
    }

    // Get random questions for the remainder.
    if ($this->quiz->number_of_random_questions > 0) {
      $random_relationships = $this->getRandomQuestions();
      $relationships = array_merge($relationships, $random_relationships);

      // Unable to find enough requested random questions.
      if ($this->quiz->number_of_random_questions > count($random_relationships)) {
        return array();
      }
    }

    // Shuffle questions if required.
    if ($this->quiz->randomization > 0) {
      shuffle($relationships);
    }

    return $relationships;
  }

  /**
   * Get an array list of random questions for a quiz.
   *
   * @return array[] Array of nid/vid combos for quiz questions.
   */
  private function getRandomQuestions() {
    $amount = $this->quiz->number_of_random_questions;
    if ($this->quiz->tid > 0) {
      return $this->getRandomTaxonomyQuestionIds($this->quiz->tid, $amount);
    }
    return $this->doGetRandomQuestion($amount);
  }

  private function doGetRandomQuestion($amount) {
    $select = db_select('quiz_relationship', 'relationship');
    $select->join('quiz_question', 'question', 'relationship.question_nid = question.qid');
    $select->addField('relationship', 'question_nid', 'qid');
    $select->addField('relationship', 'question_vid', 'vid');
    $select->addExpression(':true', 'random', array(':true' => TRUE));
    $select->addExpression(':number', 'relative_max_score', array(':number' => $this->quiz->max_score_for_random));
    return $select
        ->fields('relationship', array('qr_id', 'qr_pid', 'question_status'))
        ->fields('question', array('type'))
        ->condition('relationship.quiz_vid', $this->quiz->vid)
        ->condition('relationship.quiz_qid', $this->quiz->vid)
        ->condition('relationship.question_status', QUIZ_QUESTION_RANDOM)
        ->condition('question.status', 1)
        ->orderRandom()
        ->range(0, $amount)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Get all of the question nid/vids by taxonomy term ID.
   *
   * @param int $term_id
   * @param int $amount
   *
   * @return
   *   Array of nid/vid combos, like array(array('nid'=>1, 'vid'=>2)).
   */
  public function getRandomTaxonomyQuestionIds($term_id, $amount) {
    if (!$term_id || !$term = taxonomy_term_load($term_id)) {
      return array();
    }

    // Flatten the taxonomy tree, and just keep term id's.
    $term_ids[] = $term->tid;
    if ($tree = taxonomy_get_tree($term->vid, $term->tid)) {
      foreach ($tree as $term) {
        $term_ids[] = $term->tid;
      }
    }

    // Get all published questions with one of the allowed term ids.
    $query = db_select('question', 'question');
    $query->innerJoin('taxonomy_index', 'tn', 'question.qid = tn.nid');
    $query->addExpression(1, 'random');

    return $query
        ->fields('question', array('nid', 'vid'))
        ->condition('question.status', 1)
        ->condition('tn.tid', $term_ids)
        ->condition('question.type', array_keys(quiz_question_get_types()))
        ->orderRandom()
        ->range(0, $amount)
        ->execute()->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Sets the questions that are assigned to a quiz.
   *
   * @param array[] $relationships
   *   An array of relationship.
   * @param bool $set_new_revision
   *   If TRUE, a new revision will be generated. Note that saving
   *   quiz questions unmodified will still generate a new revision of the quiz if
   *   this is set to TRUE. Why? For a few reasons:
   *   - All of the questions are updated to their latest VID. That is supposed to
   *     be a feature.
   *   - All weights are updated.
   *   - All status flags are updated.
   *
   * @return
   *   Boolean TRUE if update was successful, FALSE otherwise.
   */
  public function setQuestions(array $relationships, $set_new_revision = FALSE) {
    // Create a new Quiz VID, even if nothing changed.
    if ($set_new_revision) {
      $this->quiz->is_new_revision = TRUE;
      $this->quiz->save();
    }

    // When node_save() calls all of the node API hooks, old quiz info is
    // automatically inserted into quiz_relationship. We could get clever and
    // try to do strategic updates/inserts/deletes, but that method has already
    // proven error prone as the module has gained complexity (See 5.x-2.0-RC2).
    // So we go with the brute force method:
    db_delete('quiz_relationship')
      ->condition('quiz_qid', $this->quiz->qid)
      ->condition('quiz_vid', $this->quiz->vid)
      ->execute();

    // This is not an error condition.
    if (empty($relationships)) {
      return TRUE;
    }

    $this->doSetQuestions($relationships, $set_new_revision);
    $this->quiz->getController()->getMaxScoreWriter()->update(array($this->quiz->vid));

    return TRUE;
  }

  private function doSetQuestions($questions, $set_new_revision) {
    foreach ($questions as $question) {
      if ($question->state == QUIZ_QUESTION_NEVER) {
        continue;
      }

      // Update to latest OR use the version given.
      $question_vid = $question->vid;
      if ($question->refresh) {
        $sql = 'SELECT vid FROM {quiz_question} WHERE qid = :qid';
        $question_vid = db_query($sql, array(':qid' => $question->qid))->fetchField();
      }

      $relationships[$question->qr_id] = entity_create('quiz_question_relationship', array(
          'quiz_qid'              => $this->quiz->qid,
          'quiz_vid'              => $this->quiz->vid,
          'question_nid'          => $question->qid,
          'question_vid'          => $question_vid,
          'question_status'       => $question->state,
          'weight'                => $question->weight,
          'max_score'             => (int) $question->max_score,
          'auto_update_max_score' => (int) $question->auto_update_max_score,
          'qr_pid'                => $question->qr_pid,
          'qr_id'                 => !$set_new_revision ? $question->qr_id : NULL,
          'old_qr_id'             => $question->qr_id,
      ));
      $relationships[$question->qr_id]->save();
    }

    // Update the parentage when a new revision is created.
    // @todo this is copy pasta from quiz_update_quiz_question_relationship
    foreach ($relationships as $relationship) {
      $_relationships = entity_load('quiz_question_relationship', FALSE, array(
          'qr_pid'   => $relationship->old_qr_id,
          'quiz_vid' => $this->quiz->vid,
          'quiz_qid' => $this->quiz->qid,
        ), TRUE);

      foreach ($_relationships as $_relationship) {
        $_relationship->qr_pid = $relationship->qr_id;
        $_relationship->save();
      }
    }
  }

}
