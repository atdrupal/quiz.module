<?php

namespace Drupal\quiz\Form;

use Drupal\quiz\Entity\QuizEntity;
use Drupal\quiz\Entity\Result;
use Drupal\quiz\Form\QuizAnsweringForm\FormSubmission;
use Drupal\quiz_question\Entity\Question;
use stdClass;

class QuizAnsweringForm {

  /** @var QuizEntity */
  private $quiz;
  private $question;
  private $page_number;

  /** @var Result */
  private $result;

  /** @var int */
  private $quiz_id;

  /** @var FormSubmission */
  private $submit;

  public function __construct($quiz, $question, $page_number, $result) {
    $this->quiz = $quiz;
    $this->question = $question;
    $this->page_number = $page_number;
    $this->result = $result;
    $this->quiz_id = $quiz->qid;
  }

  /**
   * Build question list in page.
   * @param Result $result
   * @param stdClass $page
   */
  public static function findPageQuestions(Result $result, Question $page) {
    $page_id = NULL;
    $questions = array(quiz_question_entity_load($page->qid));

    foreach ($result->layout as $item) {
      if ($item['vid'] == $page->vid) {
        $page_id = $item['qr_id'];
        break;
      }
    }

    foreach ($result->layout as $item) {
      if ($page_id == $item['qr_pid']) {
        $questions[] = quiz_question_entity_load($item['qid']);
      }
    }

    return $questions;
  }

  /**
   * Get the form to show to the quiz taker.
   *
   * @param \Drupal\quiz_question\Entity\Question[] $questions
   *   A list of questions to get answers from.
   * @param $result_id
   *   The result ID for this attempt.
   */
  public function getForm($form, &$form_state, $questions) {
    $form['#attributes']['class'] = array('answering-form');

    $form['#quiz'] = $this->quiz;
    $form['#question'] = $this->question;
    $form['#page_number'] = $this->page_number;
    $form['#result'] = $this->result;

    foreach ($questions as $question) {
      $this->buildQuestionItem($question->getPlugin(), $form, $form_state);
    }

    // Build buttons
    $allow_skipping = isset($question->type) ? $question->type !== 'quiz_directions' : $question->type;
    $this->buildSubmitButtons($form, $allow_skipping);

    return $form;
  }

  private function buildQuestionItem($question_provider, &$form, $form_state) {
    $question = $question_provider->question;

    // Element for a single question
    $element = $question_provider->getAnsweringForm($form_state, $this->result->result_id);

    $output = entity_view('quiz_question', array($question));
    unset($output['quiz_question'][$question->qid]['answers']);

    $form['questions'][$question->qid] = array(
        '#attributes' => array('class' => array(drupal_html_class('quiz-question-' . $question->type))),
        '#type'       => 'container',
        'header'      => $output,
        'question'    => array('#tree' => TRUE, $question->qid => $element),
    );

    // Should we disable this question?
    if (empty($this->quiz->allow_change) && quiz_result_is_question_answered($this->result, $question)) {
      // This question was already answered, and not skipped.
      $form['questions'][$question->qid]['#disabled'] = TRUE;
    }

    if ($this->quiz->mark_doubtful) {
      $form['is_doubtful'] = array(
          '#type'          => 'checkbox',
          '#title'         => t('doubtful'),
          '#weight'        => 1,
          '#prefix'        => '<div class="mark-doubtful checkbox enabled"><div class="toggle"><div></div></div>',
          '#suffix'        => '</div>',
          '#default_value' => 0,
          '#attached'      => array('js' => array(drupal_get_path('module', 'quiz') . '/misc/js/quiz_take.js')),
      );

      // @TODO: Reduce queries
      $sql = 'SELECT is_doubtful '
        . ' FROM {quiz_results_answers} '
        . ' WHERE result_id = :result_id '
        . '   AND question_qid = :question_qid '
        . '   AND question_vid = :question_vid';
      $form['is_doubtful']['#default_value'] = db_query($sql, array(
          ':result_id'    => $this->result->result_id,
          ':question_qid' => $question->qid,
          ':question_vid' => $question->vid))->fetchField();
    }
  }

  private function buildSubmitButtons(&$form, $allow_skipping) {
    $is_last = $this->result->isLastPage($this->page_number);

    $form['navigation']['#type'] = 'actions';

    if (!empty($this->quiz->backwards_navigation) && (arg(3) != 1)) {
      // Backwards navigation enabled, and we are looking at not the first
      // question. @todo detect when on the first page.
      $form['navigation']['back'] = array(
          '#weight'                  => 10,
          '#type'                    => 'submit',
          '#value'                   => t('Back'),
          '#submit'                  => array('quiz_answer_form_submit_back'),
          '#limit_validation_errors' => array(),
      );

      if ($is_last) {
        $form['navigation']['#last'] = TRUE;
        $form['navigation']['last_text'] = array(
            '#weight' => 0,
            '#markup' => '<p><em>' . t('This is the last question. Press Finish to deliver your answers') . '</em></p>',
        );
      }
    }

    $form['navigation']['submit'] = array(
        '#weight' => 30,
        '#type'   => 'submit',
        '#value'  => $is_last ? t('Finish') : t('Next'),
        '#submit' => array('quiz_answer_form_submit'),
    );

    // @TODO: Check this
    $form['navigation']['skip'] = array(
        '#weight'                  => 20,
        '#type'                    => 'submit',
        '#value'                   => $is_last ? t('Leave blank and finish') : t('Leave blank'),
        '#access'                  => $allow_skipping,
        '#submit'                  => array('quiz_answer_form_submit_blank'),
        '#limit_validation_errors' => array(),
        '#access'                  => $this->quiz->allow_skipping,
    );

    // Display a confirmation dialogue if this is the last question and a user
    // is able to navigate backwards but not forced to answer correctly.
    if ($is_last && $this->quiz->backwards_navigation && !$this->quiz->repeat_until_correct) {
      $form['#attributes']['class'][] = 'quiz-answer-confirm';
      $form['#attributes']['data-confirm-message'] = t("By proceeding you won't be able to go back and edit your answers.");
      $form['#attached']['js'][] = drupal_get_path('module', 'quiz') . '/misc/js/quiz.answering.confirm.js';
    }
  }

  /**
   * Validation callback for quiz question submit.
   */
  public function formValidate(&$form, &$form_state) {
    $time_reached = $this->quiz->time_limit && (REQUEST_TIME > ($this->result->time_start + $this->quiz->time_limit));

    // Let's not validate anything, because the input won't get saved in submit either.
    if ($time_reached) {
      return;
    }

    foreach (array_keys($form_state['values']['question']) as $question_id) {
      // There was an answer submitted.
      if ($current_question = quiz_question_entity_load($question_id)) {
        $current_question->getPlugin()->getAnsweringFormValidate($form, $form_state);
      }
    }
  }

  public function getSubmit() {
    if (null === $this->submit) {
      $this->submit = new FormSubmission($this->quiz, $this->result, $this->page_number);
    }
    return $this->submit;
  }

}
