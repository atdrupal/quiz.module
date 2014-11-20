<?php

namespace Drupal\quiz\Helper\Quiz;

abstract class BaseForm {

  /**
   * Adds checkbox for creating new revision. Checks it by default if answers exists.
   *
   * @param array $form FAPI form(array)
   * @param stdClass $quiz Quiz entity(object)
   */
  protected function addRevisionCheckbox(&$form, &$quiz) {
    // Recomend and preselect to create the quiz as a new revision if it already has been answered
    if (quiz()->getQuizHelper()->isAnswered($quiz)) {
      $rev_default = TRUE;
      $rev_description = t('This quiz has been answered. To maintain correctnes of existing answer reports changes should be saved as a new revision.');
    }
    else {
      $rev_default = in_array('revision', variable_get('node_options_quiz', array()));
      $rev_description = t('Allow question status changes to create a new revision of the quiz?');
    }

    if (user_access('manual quiz revisioning') && !variable_get('quiz_auto_revisioning', 1)) {
      $form['new_revision'] = array(
          '#type'          => 'checkbox',
          '#default_value' => $rev_default,
          '#title'         => t('New revision'),
          '#description'   => $rev_description,
      );
    }
    else {
      $form['new_revision'] = array('#type' => 'value', '#value' => $rev_default);
    }
  }

}
