<?php

namespace Drupal\quiz\Controller;

use Drupal\quiz\Entity\QuizEntity;
use Drupal\quiz\Entity\Result;
use Drupal\quiz_question\Entity\Question;

class QuizQuestionFeedbackController {

  /** @var QuizEntity */
  private $quiz;

  /** @var int */
  private $quiz_id;

  /** @var Result */
  private $result;

  public function __construct(QuizEntity $quiz, Result $result) {
    $this->quiz = $quiz;
    $this->quiz_id = $this->quiz->qid;
    $this->result = $result;
  }

  public function render($page_number) {
    $question = quiz_question_entity_load($this->result->layout[$page_number]['qid']);
    return $this->buildRenderArray($question);
  }

  public function buildRenderArray(Question $question) {
    require_once DRUPAL_ROOT . '/' . drupal_get_path('module', 'quiz') . '/quiz.pages.inc';

    // Invoke hook_get_report().
    if ($report = module_invoke($question->getModule(), 'get_report', $question->qid, $question->vid, $this->result->result_id)) {
      return drupal_get_form('quiz_report_form', $this->result, array($report));
    }
  }

}
