<?php

namespace Drupal\quiz\Form;

use Drupal\quiz\Entity\QuizEntity;
use Drupal\quiz\Form\QuizForm\FormDefinition;
use Drupal\quiz\Form\QuizForm\FormValidation;

class QuizForm {

  /** @var QuizEntity */
  private $quiz;

  public function __construct(QuizEntity $quiz) {
    $this->quiz = $quiz;
  }

  public function get($form, &$form_state, $op) {
    $def = new FormDefinition($this->quiz);
    return $def->get($form, $form_state, $op);
  }

  public function validate($form, &$form_state) {
    if (t('Delete') === $form_state['clicked_button']['#value']) {
      $path = 'admin' === arg(0) ? 'admin/content/quiz/manage/' . $this->quiz->qid . '/delete' : 'quiz/' . $this->quiz->qid . '/delete';
      drupal_goto($path);
    }
    else {
      $validator = new FormValidation($form, $form_state);
      return $validator->validate();
    }
  }

  public function submit($form, &$form_state) {
    /* @var $quiz QuizEntity */
    $quiz = entity_ui_controller('quiz_entity')->entityFormSubmitBuildEntity($form, $form_state);

    // convert formatted text fields
    $quiz->summary_default_format = $quiz->summary_default['format'];
    $quiz->summary_default = $quiz->summary_default['value'];
    $quiz->summary_pass_format = $quiz->summary_pass['format'];
    $quiz->summary_pass = $quiz->summary_pass['value'];

    // convert value from date (popup) widgets to timestamp
    foreach (array('quiz_open', 'quiz_close') as $k) {
      if (($human = $quiz->$k) && (FALSE !== strtotime($human))) {
        $quiz->$k = strtotime($human);
      }
    }

    // Enable revision flag.
    if (!empty($form_state['values']['revision'])) {
      $quiz->is_new_revision = TRUE;
    }

    // Add in created and changed times.
    $quiz->save();

    // Use would like remembering settings
    if (!empty($form_state['values']['remember_settings']) || !empty($form_state['values']['remember_global'])) {
      quiz_controller()->getSettingIO()->updateUserDefaultSettings($quiz);
    }

    if ('admin' === arg(0)) {
      $form_state['redirect'] = 'admin/content/quiz';
    }

    if (!$form['#quiz']->qid) {
      drupal_set_message(t('You just created a new @quiz. Now you have to add questions to it. This page is for adding and managing questions. Here you can create new questions or add some of your already created questions. If you want to change the quiz settings, you can use the "edit" tab.'), array('@quiz' => QUIZ_NAME));
      $form_state['redirect'] = "quiz/" . $quiz->qid . "/questions";
    }

    // If the quiz don't have any questions jump to the manage questions tab.
    $sql = 'SELECT 1 FROM {quiz_relationship} WHERE quiz_vid = :vid LIMIT 1';
    if (!db_query($sql, array(':vid' => $quiz->vid))->fetchField()) {
      $form_state['redirect'] = 'quiz/' . $quiz->qid . '/questions';
    }
  }

}
