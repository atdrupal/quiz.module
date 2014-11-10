<?php

namespace Drupal\quiz\Entity;

use DatabaseTransaction;
use EntityAPIController;
use stdClass;

class QuizEntityController extends EntityAPIController {

  /** @var \Drupal\quiz\Entity\QuizEntity\DefaultPropertiesIO */
  private $default_properties_io;

  /** @var \Drupal\quiz\Entity\QuizEntity\Stats */
  private $stats;

  /**
   * @return \Drupal\quiz\Entity\QuizEntity\DefaultPropertiesIO
   */
  public function getSettingIO() {
    if (NULL === $this->default_properties_io) {
      $this->default_properties_io = new \Drupal\quiz\Entity\QuizEntity\DefaultPropertiesIO();
    }
    return $this->default_properties_io;
  }

  public function getStats() {
    if (NULL === $this->stats) {
      $this->stats = new \Drupal\quiz\Entity\QuizEntity\Stats();
    }
    return $this->stats;
  }

  /**
   * @param QuizEntity $quiz
   */
  public function buildContent($quiz, $view_mode = 'full', $langcode = NULL, $content = array()) {
    drupal_alter('quiz_view', $quiz, $view_mode);

    $extra_fields = field_extra_fields_get_display($this->entityType, $quiz->type, $view_mode);

    // Render Stats
    if ($extra_fields['stats']['visible']) {
      // Number of questions is needed on the statistics page.
      $quiz->number_of_questions = $quiz->number_of_random_questions;
      $quiz->number_of_questions += $this->getStats()->countAlwaysQuestions($quiz->vid);

      $content['quiz_entity'][$quiz->qid]['stats'] = array(
          '#markup' => theme('quiz_view_stats', array('quiz' => $quiz)),
          '#weight' => $extra_fields['stats']['weight'],
      );
    }

    // Render take button
    if ($extra_fields['take']['visible']) {
      $markup = l(t('Start @quiz', array('@quiz' => QUIZ_NAME)), 'quiz/' . $quiz->qid . '/take');
      if (TRUE !== $checking = quiz()->getQuizHelper()->isAvailable($quiz)) {
        $markup = $checking;
      }

      $content['quiz_entity'][$quiz->qid]['take'] = array(
          '#prefix' => '<div class="quiz-not-available">',
          '#suffix' => '</div>',
          '#weight' => $extra_fields['take']['weight'],
          '#markup' => $markup,
      );
    }

    return parent::buildContent($quiz, $view_mode, $langcode, $content);
  }

  public function load($ids = array(), $conditions = array()) {
    $entities = parent::load($ids, $conditions);

    // quiz_entity_revision.review_options => serialize = TRUE already, not sure
    // why it's string here
    foreach ($entities as $entity) {
      $vids[] = $entity->vid;
      if (!empty($entity->review_options) && is_string($entity->review_options)) {
        $entity->review_options = unserialize($entity->review_options);
      }
    }

    if (!empty($vids)) {
      $result_options = db_select('quiz_result_options', 'ro')
        ->fields('ro')
        ->condition('ro.quiz_vid', $vids)
        ->execute();
      foreach ($result_options->fetchAll() as $result_option) {
        $entities[$result_option->quiz_qid]->resultoptions[] = (array) $result_option;
      }
    }

    return $entities;
  }

  public function save($quiz, DatabaseTransaction $transaction = NULL) {
    // QuizFeedbackTest::testFeedback() failed without this, mess!
    if (empty($quiz->is_new_revision)) {
      $quiz->is_new = $quiz->revision = 0;
    }

    if ($return = parent::save($quiz, $transaction)) {
      $this->saveResultOptions($quiz);
      return $return;
    }
  }

  private function saveResultOptions(QuizEntity $quiz) {
    db_delete('quiz_result_options')
      ->condition('quiz_vid', $quiz->vid)
      ->execute();

    $query = db_insert('quiz_result_options')
      ->fields(array('quiz_qid', 'quiz_vid', 'option_name', 'option_summary', 'option_summary_format', 'option_start', 'option_end'));

    foreach ($quiz->resultoptions as $option) {
      if (empty($option['option_name'])) {
        continue;
      }

      // When this function called direct from node form submit the
      // $option['option_summary']['value'] and $option['option_summary']['format'] are we need
      // But when updating a quiz entity eg. on manage questions page, this values
      // come from loaded node, not from a submitted form.
      if (is_array($option['option_summary'])) {
        $option['option_summary_format'] = $option['option_summary']['format'];
        $option['option_summary'] = $option['option_summary']['value'];
      }

      $query->values(array(
          'quiz_qid'              => $quiz->qid,
          'quiz_vid'              => $quiz->vid,
          'option_name'           => $option['option_name'],
          'option_summary'        => $option['option_summary'],
          'option_summary_format' => $option['option_summary_format'],
          'option_start'          => $option['option_start'],
          'option_end'            => $option['option_end']
      ));
    }

    $query->execute();
  }

  /**
   * Force save revision author ID.
   *
   * @global stdClass $user
   * @param QuizEntity $entity
   */
  protected function saveRevision($entity) {
    global $user;
    $entity->revision_uid = $user->uid;
    return parent::saveRevision($entity);
  }

  public function delete($ids, DatabaseTransaction $transaction = NULL) {
    $return = parent::delete($ids, $transaction);

    // Delete quiz results
    $query = db_select('quiz_results');
    $query->fields('quiz_results', array('result_id'));
    $query->condition('quiz_qid', $ids);
    if ($result_ids = $query->execute()->fetchCol()) {
      entity_delete_multiple('quiz_result', $result_ids);
    }

    db_delete('quiz_relationship')->condition('quiz_qid', $ids)->execute();
    db_delete('quiz_results')->condition('quiz_qid', $ids)->execute();
    db_delete('quiz_result_options')->condition('quiz_qid', $ids)->execute();

    return $return;
  }

  /**
   * Get latest quiz ID, useful for test cases.
   *
   * @return int|null
   */
  public function getLatestQuizId() {
    return db_select('quiz_entity', 'quiz')
        ->fields('quiz', array('qid'))
        ->orderBy('quiz.qid', 'DESC')
        ->execute()
        ->fetchColumn();
  }

}
