<?php

namespace Drupal\quiz\Form;

use Drupal\quiz\Entity\QuizEntity;
use Drupal\quiz\Entity\Result;
use Drupal\quiz\Form\QuizAnsweringForm\FormSubmission;
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
   * @param stdClass $result
   * @param stdClass $page
   */
  public static function findPageQuestions($result, $page) {
    $page_id = NULL;
    $questions = array(node_load($page->nid));

    foreach ($result->layout as $item) {
      if ($item['vid'] == $page->vid) {
        $page_id = $item['qr_id'];
        break;
      }
    }

    foreach ($result->layout as $item) {
      if ($page_id == $item['qr_pid']) {
        $questions[] = node_load($item['nid']);
      }
    }

    return $questions;
  }

  /**
   * Get the form to show to the quiz taker.
   *
   * @param $questions
   *   A list of question nodes to get answers from.
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
      $question = quiz_question_get_provider($question);
      $this->buildQuestionItem($question, $form, $form_state);
    }

    // Build buttons
    $allow_skipping = isset($question->type) ? $question->type !== 'quiz_directions' : $question->question->type;
    $this->buildSubmitButtons($form, $allow_skipping);

    return $form;
  }

  private function buildQuestionItem($question_instance, &$form, $form_state) {
    $question = $question_instance->question;

    // Element for a single question
    $element = $question_instance->getAnsweringForm($form_state, $this->result->result_id);
    node_build_content($question, 'question');
    unset($question->content['answers']);
    $form['questions'][$question->nid] = array(
        '#attributes' => array('class' => array(drupal_html_class('quiz-question-' . $question->type))),
        '#type'       => 'container',
        'header'      => $question->content,
        'question'    => array('#tree' => TRUE, $question->nid => $element),
    );

    // Should we disable this question?
    if (empty($this->quiz->allow_change) && quiz_result_is_question_answered($this->result, $question)) {
      // This question was already answered, and not skipped.
      $form['questions'][$question->nid]['#disabled'] = TRUE;
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
        . '   AND question_nid = :question_nid '
        . '   AND question_vid = :question_vid';
      $form['is_doubtful']['#default_value'] = db_query($sql, array(
          ':result_id'    => $this->result->result_id,
          ':question_nid' => $question->nid,
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
      if ($current_question = node_load($question_id)) {
        // There was an answer submitted.
        quiz_question_get_provider($current_question)->getAnsweringFormValidate($form, $form_state);
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
