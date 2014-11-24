<?php

use Drupal\quiz_question\Controller\QuestionRevisionsController;
use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\Form\QuestionTypeForm;
use Drupal\quiz_question\Form\RevisionActionsForm;

/**
 * @file
 * User page callbacks for the quiz_question module.
 */
function quiz_question_type_form($form, $form_state, $question_type, $op) {
  $obj = new QuestionTypeForm();
  return $obj->get($form, $form_state, $question_type, $op);
}

function quiz_question_type_form_submit($form, &$form_state) {
  $obj = new QuestionTypeForm();
  return $obj->submit($form, $form_state);
}

/**
 * Submit handler for question type form when user click 'delete' button.
 */
function quiz_question_type_form_submit_delete($form, &$form_state) {
  $form_state['redirect'] = 'admin/structure/quiz-questions/manage/' . $form_state['quiz_question_type']->type . '/delete';
}

/**
 * Callback for /quiz-question/add page.
 */
function quiz_question_adding_landing_page($destination = '') {
  return theme_render_template(drupal_get_path('module', 'quiz_question') . '/theme/quiz-question-adding-landing.tpl.php', array(
      'destination'    => $destination,
      'question_types' => quiz_question_get_types()
  ));
}

/**
 * Callback for /quiz-question/add/% page.
 */
function quiz_question_adding_page($question_type_name) {
  $question = entity_create('quiz_question', array('type' => $question_type_name));
  return entity_ui_get_form('quiz_question', $question, 'add');
}

/**
 * Define quiz-entity editing form.
 *
 * @param array $form
 * @param array $form_state
 * @param Question $question
 * @param string $op
 * @return array
 */
function quiz_question_form($form, &$form_state, $question, $op = 'edit') {
  $drupal_destination = drupal_get_destination();
  if (isset($drupal_destination['destination']) && ($item = menu_get_item($drupal_destination['destination'])) && $item['access']) {
    if ('quiz/%/questions' === $item['path']) {
      $quiz = $item['map'][1];
    }
  }

  $form += $question->getPlugin()->getEntityForm($form_state, isset($quiz) ? $quiz : NULL);

  // question body is required.
  $form['quiz_question_body'][LANGUAGE_NONE][0]['#required'] = TRUE;

  return $form;
}

function quiz_question_form_validate($form, &$form_state) {
  /* @var $question Question */
  $question = entity_ui_controller('quiz_question')->entityFormSubmitBuildEntity($form, $form_state);
  $question->getPlugin()->validate($form);
}

function quiz_question_form_submit($form, &$form_state) {
  /* @var $question Question */
  $question = entity_ui_controller('quiz_question')->entityFormSubmitBuildEntity($form, $form_state);

  if (!empty($form['#quiz']) && is_null($question->qid)) {
    $quiz = $form['#quiz'];
  }

  $question->save();
  $form_state['redirect'] = 'quiz-question/' . $question->qid;

  if (!empty($quiz)) {
    $question->getPlugin()->saveRelationships($quiz->qid, $quiz->vid);
  }
}

function quiz_question_form_submit_delete($form, &$form_state) {
  $form_state['redirect'] = 'quiz-question/' . $form_state['quiz_question']->internalIdentifier() . '/delete';
}

/**
 * Get the configuration form for all enabled question types.
 */
function quiz_question_config($form) {
  $form['#validate'] = array();

  // Go through all question types and merge their config forms
  foreach (quiz_question_get_plugin_info() as $name => $info) {
    $function = $name . '_quiz_question_config';
    if ($admin_form = $function()) {
      $form[$name] = $admin_form;
      $form[$name]['#type'] = 'fieldset';
      $form[$name]['#title'] = $info['name'];
      $form[$name]['#collapsible'] = TRUE;
      $form[$name]['#collapsed'] = TRUE;
      if (isset($admin_form['#validate']) && is_array($admin_form['#validate'])) {
        $form['#validate'] = array_merge($form['#validate'], $admin_form['#validate']);
        unset($form[$name]['#validate']);
      }
    }
  }

  return system_settings_form($form);
}

/**
 * Callback for /quiz-question/%/%/revision-actions
 */
function quiz_question_revision_actions($form, $form_state, $question_qid, $question_vid) {
  $obj = new RevisionActionsForm();
  return $obj->get($form, $form_state, $question_qid, $question_vid);
}

/**
 * Submit callback for the revision actions page
 */
function quiz_question_revision_actions_submit($form, &$form_state) {
  $obj = new RevisionActionsForm();
  return $obj->submit($form, $form_state);
}

/**
 * Callback for /quiz-question/%
 *
 * @param Question $question
 * @param string $view_mode
 * @param string $langcode
 * @return string|array
 */
function quiz_question_page(Question $question, $view_mode = 'default', $langcode = NULL) {
  return entity_view('quiz_entity', array($question), $view_mode, $langcode);
}

/**
 * Callback for /quiz-question/%/revisions
 */
function quiz_question_revisions_page(Question $question) {
  $obj = new QuestionRevisionsController();
  return $obj->render($question);
}
