<?php

namespace Drupal\quiz\Entity;

use DatabaseTransaction;
use Drupal\quiz\Entity\Result\Maintainer;
use Drupal\quiz\Entity\Result\ScoreIO;
use Drupal\quiz\Entity\Result\Writer;
use EntityAPIController;

class ResultController extends EntityAPIController {

  /** @var ScoreIO */
  private $score_io;

  /** @var Writer */
  private $writer;

  /** @var Maintainer */
  private $maintainer;

  public function getWriter() {
    if (NULL === $this->writer) {
      $this->writer = new Writer();
    }
    return $this->writer;
  }

  /**
   * @return ScoreIO
   */
  public function getScoreIO() {
    if (NULL === $this->score_io) {
      $this->score_io = new ScoreIO();
    }
    return $this->score_io;
  }

  public function setScoreCalculator($score_calculator) {
    $this->score_io = $score_calculator;
    return $this;
  }

  public function getMaintainer() {
    if (NULL === $this->maintainer) {
      $this->maintainer = new Maintainer();
    }
    return $this->maintainer;
  }

  public function load($ids = array(), $conditions = array()) {
    $entities = parent::load($ids, $conditions);

    foreach ($entities as $result) {
      $this->loadLayout($result);
    }

    return $entities;
  }

  /**
   * Attach the layout which previously used to be stored on the result.
   *
   * @param Result $result
   */
  private function loadLayout(Result $result) {
    $layout = entity_load('quiz_result_answer', FALSE, array('result_id' => $result->result_id));

    foreach ($layout as $answer) {
      // @kludge
      // This is bulky but now we have to manually find the type and parents of
      // the question. This is the only information that is not stored in the
      // quiz attempt. We reference back to the quiz relationships for this
      // current version to get the hieararchy.
      $select = db_select('quiz_results', 'result');
      $select->innerJoin('quiz_relationship', 'relationship', 'result.quiz_vid = relationship.quiz_vid');
      $select->innerJoin('quiz_question', 'question', 'relationship.question_qid = question.qid');
      $extra = $select
        ->fields('question', array('type'))
        ->fields('relationship', array('qr_id', 'qr_pid'))
        ->condition('result.result_id', $result->result_id)
        ->condition('question.qid', $answer->question_qid)
        ->execute()
        ->fetch();

      $result->layout[$answer->number] = array(
          'display_number' => $answer->number,
          'qid'            => $answer->question_qid,
          'vid'            => $answer->question_vid,
          'number'         => $answer->number,
          'type'           => $extra->type,
          'qr_id'          => $extra->qr_id,
          'qr_pid'         => $extra->qr_pid,
      );
    }
    ksort($result->layout, SORT_NUMERIC);
  }

  /**
   * @global \stdlClass $user
   * @param Result $result
   * @param DatabaseTransaction $transaction
   */
  public function save($result, DatabaseTransaction $transaction = NULL) {
    global $user;

    $return = parent::save($result, $transaction);

    // Delete old results if necessary.
    $result->maintenance($user->uid);

    return $return;
  }

  public function delete($result_ids, DatabaseTransaction $transaction = NULL) {
    $return = parent::delete($result_ids, $transaction);
    $this->callPluginDeleteMethod($result_ids);
    return $return;
  }

  /**
   * Call plugin owner to delete the answer.
   *
   * @param int[] $result_ids
   */
  private function callPluginDeleteMethod($result_ids) {
    $select = db_select('quiz_results_answers', 'answer');
    $select->fields('answer', array('result_id', 'question_qid', 'question_vid'));
    $select->condition('answer.result_id', $result_ids);
    $answers = $select->execute()->fetchAll();
    foreach ($answers as $answer) {
      if ($answer_instance = quiz_answer_controller()->getInstance($answer->result_id, NULL, NULL, $answer->question_qid, $answer->question_vid)) {
        $answer_instance->delete();
      }
    }
    db_delete('quiz_results_answers')->condition('result_id', $result_ids)->execute();
  }

}
