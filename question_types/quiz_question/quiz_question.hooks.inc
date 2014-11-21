<?php

/**
 * @file
 * quiz_question's hook implementations.
 */

/**
 * Implements hook_help().
 */
function quiz_question_help($path, $args) {
  if ($path == 'admin/help#quiz_quesion') {
    return t('Support for Quiz question types.');
  }
}

/**
 * Implements hook_menu().
 */
function quiz_question_menu() {
  $items = array();
  $items['quiz_question/%/%/revision_actions'] = array(
    'title' => 'Revision actions',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('quiz_question_revision_actions', 1, 2),
    'access arguments' => array('manual quiz revisioning'),
    'file' => 'quiz_question.pages.inc',
    'type' => MENU_NORMAL_ITEM,
  );
  // Menu items for admin view of each question type.
  $items['admin/quiz/settings/questions_settings'] = array(
    'title' => 'Question configuration',
    'description' => 'Configure the question types.',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('quiz_question_config'),
    'access arguments' => array('administer quiz configuration'),
    'type' => MENU_NORMAL_ITEM,
  );
  return $items;
}

/**
 * Implements hook_theme().
 */
function quiz_question_theme() {
  $hooks = array(
    'quiz_question_creation_form' => array(
      'render element' => 'form',
      'file' => 'quiz_question.theme.inc',
    ),
    'quiz_question_navigation_form' => array(
      'render element' => 'form',
      'file' => 'quiz_question.theme.inc',
    ),
  );

  $hooks['quiz_question_feedback'] = array(
    'variables' => NULL,
    'pattern' => 'quiz_question_feedback__',
  );

  return $hooks;
}

/**
 * Implements hook_entity_info().
 */
function quiz_question_entity_info() {
  return array(
    'quiz_question' => array(
      'label' => t('Quiz question properties'),
      'controller class' => 'EntityAPIController',
      'base table' => 'quiz_question_properties',
      'entity keys' => array(
        'id' => 'qqp_id',
      ),
      'views controller class' => 'EntityDefaultViewsController',
    ),
  );
}

/**
 * Implements hook_node_info().
 */
function quiz_question_node_info() {
  $node_info = array();
  foreach (quiz_question_get_info(NULL, TRUE) as $type => $definition) {
    $node_info[$type] = array(
      'name' => $definition['name'],
      'base' => 'quiz_question',
      'description' => $definition['description']
    );
    //$info[$type] = $node_info + $defaults;
  }
  return $node_info;
}

/**
 * Implements hook_form().
 */
function quiz_question_form(&$node, &$form_state) {
  $question = _quiz_question_get_instance($node);
  $form = $question->getNodeForm($form_state);
  return $form;
}

/**
 * Implements hook_quiz_result_update().
 */
function quiz_quiz_result_update($quiz_result) {
  if (!$quiz_result->original->is_evaluated && $quiz_result->is_evaluated) {
    // Quiz is finished!
    $quiz = node_load($quiz_result->nid);
    // Delete old results if necessary.
    _quiz_maintain_results($quiz, $quiz_result->result_id);
  }
}

/**
 * Implements hook_get_report().
 *
 * @return
 *  Node containing all of the items from the question plus the user's answer.
 */
function quiz_question_get_report($nid, $vid, $result_id) {
  $response_instance = _quiz_question_response_get_instance($result_id, NULL, NULL, $nid, $vid);
  if (!$response_instance) {
    drupal_set_message(t('Unable to load question with nid %nid and vid %vid', array('%nid' => $nid, '%vid' => $vid)), 'error');
    return FALSE;
  }
  $result = $response_instance->getReport();
  $response_instance->question->answers[$result['answer_id']] = $result;
  $response_instance->question->correct = $result['is_correct'];
  return $response_instance->question;
}

/**
 * Implements hook_quiz_question_score().
 */
function quiz_question_quiz_question_score($quiz, $question_nid, $question_vid = NULL, $result_id = NULL) {
  if (!isset($quiz) && !isset($result_id)) {
    return quiz_question_get_max_score($question_nid, $question_vid);
  }

  // We avoid using node_load to increase performance...
  $dummy_node = new stdClass();
  $dummy_node->nid = $question_nid;
  $dummy_node->vid = $question_vid;

  $question = _quiz_question_get_instance($dummy_node, TRUE);
  if (!$question) {
    return FALSE;
  }

  $score = new stdClass();
  $score->possible = $question->getMaximumScore();
  $score->question_nid = $question->node->nid;
  $score->question_vid = $question->node->vid;

  if (isset($result_id)) {
    $response = _quiz_question_response_get_instance($result_id, $question->node);
    $score->attained = $score->possible > 0 ? $response->getScore() : 0;
    $score->possible = $response->getMaxScore();
    $score->is_evaluated = $response->isEvaluated();
  }
  return $score;
}

// NODE API

/**
 * Implements hook_node_revision_delete().
 */
function quiz_question_node_revision_delete($node) {
  $q_types = quiz_question_get_info();
  foreach ($q_types as $q_type => $info) {
    if ($node->type == $q_type) {
      _quiz_delete_question($node, TRUE); // true for only this version
    }
  }
}

/**
 * Implements hook_node_presave().
 */
function quiz_question_node_presave($node) {
  $q_types = quiz_question_get_info();
  foreach ($q_types as $q_type => $info) {
    if ($node->type == $q_type) {
      if (drupal_strlen($node->title) == 0 || !user_access('edit question titles')) {
        $markup = strip_tags(drupal_render(field_view_field('node', $node, 'body')));
        if (drupal_strlen($markup) > variable_get('quiz_autotitle_length', 50)) {
          $node->title = drupal_substr($markup, 0, variable_get('quiz_autotitle_length', 50) - 3) . '...';
        }
        else {
          $node->title = $markup;
        }
      }
    }
  }
}

/**
 * Implements hook_insert().
 */
function quiz_question_insert(stdClass $node) {
  _quiz_question_get_instance($node)->save(TRUE);
}

/**
 * Implements hook_view().
 */
//function quiz_question_view($node, $teaser = FALSE, $page = FALSE) {
function quiz_question_view($node, $view_mode) {
  if ($view_mode == 'search_index' && !variable_get('quiz_index_questions', 1)) {
    $node->body = '';
    $node->content = array();
    $node->title = '';
    $node->taxonomy = array();
    return $node;
  }
  $content = '';

  if ($view_mode == 'teaser') {
    $node->content['question_teaser'] = _quiz_question_teaser_content($node);
  }
  else {
    // normal node view
    //$question = _quiz_question_get_instance($node, TRUE);
    $content = _quiz_question_get_instance($node, TRUE)->getNodeView();
  }

  // put it into the node->content
  if (!empty($content)) {
    $node->content = (isset($node->content)) ? $node->content + $content : $content;
  }
  return $node;
}

/**
 * Implements hook_update().
 */
function quiz_question_update($node) {
  _quiz_question_get_instance($node)->save();
}

/**
 * Implements hook_delete().
 */
function quiz_question_delete(&$node) {
  _quiz_delete_question($node, FALSE);
}

/**
 * Implements hook_load().
 */
function quiz_question_load($nodes) {
  foreach ($nodes as $nid => &$node) {
    $node_additions = _quiz_question_get_instance($node, TRUE)->getNodeProperties();
    foreach ($node_additions as $property => &$value) {
      $node->$property = $value;
    }
  }
}

/**
 * Implements hook_validate().
 */
function quiz_question_validate($node, &$form) {
  // Check to make sure that there is a question.
  if (empty($node->body)) {
    form_set_error('body', t('Question text is empty.'));
  }
  _quiz_question_get_instance($node)->validateNode($form);
}

/**
 * Implements hook_node_access_records().
 */
function quiz_question_node_access_records($node) {
  $grants = array();
  // Restricting view access to question nodes outside quizzes.
  $question_types = quiz_question_get_info();
  $question_types = array_keys($question_types);
  if (in_array($node->type, $question_types)) {
    // This grant is for users having 'view quiz question outside of a quiz'
    // permission. We set a priority of 2 because OG has a 1 priority and we
    // want to get around it.
    $grants[] = array(
      'realm' => 'quiz_question',
      'gid' => 1,
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
      'priority' => 2,
    );
  }
  return $grants;
}

/**
 * Implements hook_node_grants().
 */
function quiz_question_node_grants($account, $op) {
  $grants = array();
  if ($op == 'view') {
    if (user_access('view quiz question outside of a quiz')) {
      // Granting view access
      $grants['quiz_question'][] = 1;
    }
  }
  return $grants;
}

// END NODE API
