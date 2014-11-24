<?php

use Drupal\quiz\Entity\QuizEntity;
use Drupal\quiz\Entity\Result;

/**
 * @file
 * Quiz hook implementations and helper functions (not API).
 */

/**
 * Implements hook_help().
 */
function quiz_help($path, $arg) {
  switch ($path) {
    case 'admin/help#quiz':
      return _filter_autop(t('The quiz module allows users to administer a quiz, as a sequence of questions, and track the answers given. It allows for the creation of questions (and their answers), and organizes these questions into a quiz. Its target audience includes educational institutions, online training programs, employers, and people who just want to add a fun activity for their visitors to their Drupal site.

The quiz module has a plethora of permission options. Unless you take care setting your permissions, the quiz module might not do everything you want it to do.

For more information about quiz, and resources on how to use quiz, see the <a href="http://drupal.org/project/quiz">Quiz project website</a>'));
    case 'quiz/add#quiz':
      return t('A collection of questions designed to create interactive tests');
  }
}

/**
 * Implements hook_views_api().
 */
function quiz_views_api() {
  return array('api' => '3.0', 'path' => QUIZ_VIEWS_DIR);
}

/**
 * Implements hook_ctools_plugin_directory() to let the system know
 * where our task and task_handler plugins are.
 */
function quiz_ctools_plugin_directory($owner, $plugin_type) {
  if ($owner === 'page_manager') {
    return 'plugins/page_manager/' . $plugin_type;
  }
  if ($owner === 'ctools' && $plugin_type === 'content_types') {
    return 'plugins/content_types';
  }
}

/**
 * Implements hook_ctools_plugin_api().
 */
function quiz_ctools_plugin_api($module, $api) {
  if ($module === 'page_manager' && $api === 'pages_default') {
    return array('version' => 1);
  }
}

/**
 * Implements hook_entity_info().
 */
function quiz_entity_info() {
  return quiz()->getHookImplementation()->getHookEntityInfo()->execute();
}

/**
 * Implements hook_field_extra_fields().
 */
function quiz_field_extra_fields() {
  return quiz()->getHookImplementation()->getHookFieldExtraFields()->execute();
}

/**
 * Implements hook_permission().
 */
function quiz_permission() {
  return quiz()->getHookImplementation()->getHookPermission()->execute();
}

/**
 * Implements hook_admin_paths().
 */
function quiz_admin_paths() {
  $items = array();

  if (variable_get('node_admin_theme', TRUE)) {
    $items['quiz/add'] = TRUE;
    $items['quiz/add/*'] = TRUE;
    $items['quiz/*/edit'] = TRUE;
    $items['quiz/*/edit/*'] = TRUE;
    $items['quiz/*/delete'] = TRUE;
  }

  return $items;
}

/**
 * Implements hook_action_info().
 */
function quiz_action_info() {
  $actions = array();

  $actions['quiz_add_question_to_quiz'] = array(
      'type'             => 'quiz_question',
      'label'            => t('Add questions to @quiz', array('@quiz' => QUIZ_NAME)),
      'configurable'     => FALSE,
      'vbo_configurable' => FALSE,
  );

  return $actions;
}

/**
 * Implements hook_cron().
 *
 * Remove old quiz results that haven't been finished.
 */
function quiz_cron() {
  if ($rm_time = variable_get('quiz_remove_partial_quiz_record', '0')) { // $time = 0 for never.
    db_delete('quiz_results')
      ->condition('time_end', 0)
      ->where('(:request_time - time_start) > :remove_time', array(
          ':request_time' => REQUEST_TIME,
          ':remove_time'  => $rm_time
      ))
      ->execute();
  }
}

/**
 * Implements hook_menu().
 */
function quiz_menu() {
  return quiz()->getHookImplementation()->getHookMenu()->execute();
}

/**
 * Implements hook_menu_local_tasks_alter().
 *
 * Add action link to 'quiz/add' on 'admin/content/quiz' page.
 */
function quiz_menu_local_tasks_alter(&$data, $router_item, $root_path) {
  switch ($root_path) {
    case 'admin/content/quiz':
      if (($item = menu_get_item('quiz/add')) && $item['access']) {
        $data['actions']['output'][] = array('#theme' => 'menu_local_action', '#link' => $item);
      }
      break;
  }
}

/**
 * Implements hook_theme().
 */
function quiz_theme($existing, $type, $theme, $path) {
  return array(
      'quiz_view_stats'          => array(
          'variables' => array('quiz' => NULL),
          'file'      => 'quiz.pages.inc'
      ),
      'quiz_categorized_form'    => array(
          'render element' => 'form',
          'file'           => 'quiz.pages.inc'
      ),
      'quiz_result'              => array(
          'variables' => array(
              'quiz'      => NULL,
              'questions' => NULL,
              'score'     => NULL,
              'summary'   => NULL,
              'result_id' => NULL),
          'file'      => 'quiz.pages.inc',
      ),
      'quiz_progress'            => array(
          'variables' => array(
              'quiz'            => null,
              'question_number' => NULL,
              'num_questions'   => NULL,
              'allow_jumping'   => NULL,
              'time_limit'      => NULL),
          'file'      => 'quiz.pages.inc',
      ),
      'quiz_no_feedback'         => array(
          'file'      => 'quiz.pages.inc',
          'variables' => array(),
      ),
      'question_selection_table' => array(
          'file'           => 'quiz.pages.inc',
          'render element' => 'form',
      ),
      'quiz_answer_result'       => array(
          'file'      => 'quiz.pages.inc',
          'variables' => array(),
      ),
      'quiz_report_form'         => array(
          'render element' => 'form',
          'path'           => $path . '/theme',
          'template'       => 'quiz-report-form',
      ),
      'quiz_browser'             => array(
          'render element' => 'form',
          'file'           => 'quiz.pages.inc',
      ),
      'quiz_question_score'      => array(
          'variables' => array('score' => NULL, 'max_score' => NULL),
          'path'      => $path . '/theme',
          'template'  => 'quiz-question-score',
      ),
      'quiz_pager'               => array(
          'variables' => array('quiz' => null, 'total' => 0, 'current' => 0, 'siblings' => 0),
      ),
  );
}

/**
 * Implements hook_user_cancel().
 */
function quiz_user_cancel($edit, $account, $method) {
  quiz()->getHookImplementation()->getHookUserCancel($account, $method)->execute();
}

/**
 * Implements hook_mail().
 */
function quiz_mail($key, &$message, $params) {
  global $user;

  switch ($key) {
    case 'notice':
      list($quiz, $score, $result_id, $target) = $params;
      list($subject, $body) = quiz()->getMailHelper()->notice($user, $quiz, $score, $result_id, $target);
      $message['subject'] = $subject;
      $message['body'][] = $body;
      break;
  }
}

/**
 * Implements hook_quiz_scored().
 *
 * Performs actions like sending quiz results over email at the end of quiz.
 */
function quiz_quiz_scored($quiz, $score, $result_id) {
  return quiz()->getHookImplementation()->getHookQuizScored($quiz, $score, $result_id)->execute();
}

// ---------------------
// Helper functions
// ---------------------

/**
 * Workaround for menu_translate bug.
 */
function question_number_to_arg() {
  return 0;
}

/**
 * Result_id, menu argument loader.
 */
function quiz_rid_load($result_id) {
  if ($result_id > 0) {
    return $result_id;
  }
  return FALSE;
}

/**
 * Workaround for a _menu_translate() bug.
 */
function quiz_rid_to_arg() {
  return 0;
}

/**
 * Helper function to check if the user has any of a given list of permissions.
 *
 * @param args
 *   Any number of permissions.
 * @return
 *   TRUE if the user has access to any of the arguments given.
 */
function quiz_access_multi_or() {
  foreach (func_get_args() as $perm) {
    if (user_access($perm)) {
      return TRUE;
    }
  }
}

/**
 * Helper function to determine if a user has access to view a specific quiz result.
 *
 * @param Result|int $result
 * @return boolean
 *  True if access, false otherwise
 */
function quiz_access_my_result($result) {
  global $user;

  if (is_numeric($result) && !$result = quiz_result_load($result)) {
    throw new RuntimeException('Invalid result ID.');
  }

  return $result->canAccessOwnResult($user);
}

/**
 * Helper function to determine if a user has access to the different results
 * pages.
 *
 * @param $quiz
 *   The quiz entity.
 * @param $result_id
 *   The result id of a result we are trying to access.
 * @return boolean
 *   TRUE if user has permission.
 */
function quiz_access_results($quiz, $result_id = NULL) {
  global $user;
  return quiz()->getQuizHelper()->getAccessHelper()->canAccessResults($user, $quiz, $result_id);
}

/**
 * Helper function to determine if a user has access to view his quiz results
 *
 * @param object $quiz
 *  The Quiz entity
 */
function quiz_access_my_results($quiz) {
  global $user;
  return quiz()->getQuizHelper()->getAccessHelper()->canAccessMyResults($quiz, $user);
}

/**
 * Does the current user have access to take the quiz?
 *
 * @param $quiz
 *  The quiz entity
 */
function quiz_take_access($quiz) {
  global $user;
  return quiz()->getQuizHelper()->getAccessHelper()->canTakeQuiz($quiz, $user);
}

/**
 * Menu check to see if user has access to take this question.
 */
function quiz_access_question($quiz, $page_number) {
  return quiz()->getQuizHelper()->getAccessHelper()->canAccessQuestion($quiz, $page_number);
}

/**
 * Access callback for quiz-type.
 *
 * @param string $op
 * @param string $type
 * @param stdClass $account
 */
function quiz_type_access($op, $type = NULL, $account = NULL) {
  return user_access('administer quiz configuration', $account);
}

/**
 * Access callback for quiz-entity.
 * @param string $op
 * @param string $type
 * @param stdClass $account
 */
function quiz_entity_access_callback($op, $type = NULL, $account = NULL) {
  switch ($op) {
    case 'create':
      return user_access('create quiz content', $account);
    case 'update':
      return user_access('edit any quiz content', $account);
    case 'view':
      return user_access('access quiz', $account);
  }
  return TRUE;
}

/**
 * @TODO: Fix me
 * Validate that a node is of type quiz, and that the user has access to it.
 *
 * @param stdClass $node
 * @param string $op
 * @return bool
 */
function quiz_type_confirm($node, $op = NULL) {
  if ($node->type !== 'quiz') {
    return FALSE;
  }

  if (isset($op)) {
    if (in_array($op, array('view', 'update', 'delete', 'create'))) {
      return node_access($op, $node);
    }
    return user_access($op);
  }

  return user_access('access quiz');
}

/**
 * Menu access check for question feedback.
 * @param QuizEntity $quiz
 * @param int $page_number
 * @return boolean
 */
function quiz_question_feedback_access(QuizEntity $quiz, $page_number) {
  if (($page_number <= 0) || !array_filter($quiz->review_options['question'])) {
    return FALSE;
  }

  $result_id = empty($_SESSION['quiz'][$quiz->qid]['result_id']) ? $_SESSION['quiz']['temp']['result_id'] : $_SESSION['quiz'][$quiz->qid]['result_id'];
  $result = quiz_result_load($result_id);
  $question_vid = $result->layout[$page_number]['vid'];

  return (bool) quiz_answer_controller()->loadByResultAndQuestion($result_id, $question_vid);
}
