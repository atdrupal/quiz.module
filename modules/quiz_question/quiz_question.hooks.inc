<?php

use Drupal\quiz_question\Entity\Question;

/**
 * Implements hook_help().
 */
function quiz_question_help($path, $args) {
  if ($path == 'admin/help#quiz_quesion') {
    return t('Support for Quiz question types.');
  }
}

/**
 * Implements hook_permission()
 */
function quiz_question_permission() {
  $perms = array();

  $perms['administer quiz questions'] = array(
      'title'           => t('Administer quiz questions'),
      'description'     => t('Have all permissions on all questions.'),
      'restrict access' => TRUE,
  );

  $perms['view any questions'] = array(
      'title' => t('View any questions'),
  );

  foreach (quiz_question_get_types() as $name => $info) {
    $perms += array(
        "create $name question"     => array(
            'title' => t('%type_name: Create new question', array('%type_name' => $info->label)),
        ),
        "edit own $name question"   => array(
            'title' => t('%type_name: Edit own question', array('%type_name' => $info->label)),
        ),
        "edit any $name question"   => array(
            'title' => t('%type_name: Edit any question', array('%type_name' => $info->label)),
        ),
        "delete own $name question" => array(
            'title' => t('%type_name: Delete own question', array('%type_name' => $info->label)),
        ),
        "delete any $name question" => array(
            'title' => t('%type_name: Delete any question', array('%type_name' => $info->label)),
        ),
    );
  }

  return $perms;
}

/**
 * Implements hook_theme().
 */
function quiz_question_theme() {
  $items['quiz_question_feedback'] = array(
      'variables' => NULL,
      'pattern'   => 'quiz_question_feedback__',
  );
  return $items;
}

/**
 * Implements hook_menu_local_tasks_alter().
 *
 * Add action link to 'quiz-question/add' on 'admin/content/quiz-questions' page.
 */
function quiz_question_menu_local_tasks_alter(&$data, $router_item, $root_path) {
  switch ($root_path) {
    case 'admin/content/quiz-questions':
      if (($item = menu_get_item('quiz-question/add')) && $item['access']) {
        $data['actions']['output'][] = array('#theme' => 'menu_local_action', '#link' => $item);
      }
      break;
    case 'quiz-question/%':
      if (($item = menu_get_item('admin/content/quiz-questions')) && $item['access']) {
        $item['title'] = t('Manage questions');
        $data['actions']['output'][] = array('#theme' => 'menu_local_action', '#link' => $item);
      }
      break;
  }
}

/**
 * Implements hook_entity_info()
 */
function quiz_question_entity_info() {
  $info = array();

  // User may come from old version, where the table is not available yet
  if (!db_table_exists('quiz_question_type')) {
    return $info;
  }

  $info['quiz_question_type'] = array(
      'label'            => t('Question type'),
      'plural label'     => t('Question types'),
      'description'      => t('Types of question entity.'),
      'entity class'     => 'Drupal\quiz_question\Entity\QuestionType',
      'controller class' => 'Drupal\quiz_question\Entity\QuestionTypeController',
      'base table'       => 'quiz_question_type',
      'fieldable'        => FALSE,
      'bundle of'        => 'quiz_question',
      'exportable'       => TRUE,
      'entity keys'      => array('id' => 'id', 'name' => 'type', 'label' => 'label'),
      'access callback'  => 'quiz_question_type_access',
      'module'           => 'quiz_question',
      'admin ui'         => array(
          'path'             => 'admin/structure/quiz-questions',
          'file'             => 'quiz_question.pages.inc',
          'controller class' => 'Drupal\quiz_question\Entity\QuestionTypeUIController',
      ),
  );

  $info['quiz_question'] = array(
      'label'                     => t('Question'),
      'plural label'              => t('Questions'),
      'description'               => t('!quiz question entity', array('!quiz' => QUIZ_NAME)),
      'entity class'              => 'Drupal\quiz_question\Entity\Question',
      'controller class'          => 'Drupal\quiz_question\Entity\QuestionController',
      'metadata controller class' => 'Drupal\quiz_question\Entity\QuestionMetadataController',
      'views controller class'    => 'Drupal\quiz_question\Entity\QuestionViewsController',
      'base table'                => 'quiz_question',
      'revision table'            => 'quiz_question_revision',
      'fieldable'                 => TRUE,
      'entity keys'               => array('id' => 'qid', 'bundle' => 'type', 'revision' => 'vid', 'label' => 'title'),
      'bundle keys'               => array('bundle' => 'type'),
      'access callback'           => 'quiz_question_access_callback',
      'label callback'            => 'entity_class_label',
      'uri callback'              => 'entity_class_uri',
      'module'                    => 'quiz_question',
      'bundles'                   => array(),
      'view modes'                => array(
          'question' => array('label' => t('Question'), 'custom settings' => FALSE),
          'feedback' => array('label' => t('Feedback'), 'custom settings' => FALSE),
      ),
      'admin ui'                  => array(
          'path'             => 'admin/content/quiz-questions',
          'file'             => 'quiz_question.pages.inc',
          'controller class' => 'Drupal\quiz_question\Entity\QuestionUIController',
      ),
  );

  // Add bundle info but bypass entity_load() as we cannot use it here.
  $rows = db_select('quiz_question_type', 'qt')->fields('qt')->execute()->fetchAllAssoc('type');
  foreach ($rows as $name => $row) {
    $info['quiz_question']['bundles'][$name] = array(
        'label' => $row->label,
        'admin' => array(
            'path'             => 'admin/structure/quiz-questions/manage/%quiz_question_type',
            'real path'        => 'admin/structure/quiz-questions/manage/' . $name,
            'bundle argument'  => 4,
            'access arguments' => array('administer quiz'),
        ),
    );
  }

  return $info;
}

/**
 * Implements hook_field_extra_fields()
 */
function quiz_question_field_extra_fields() {
  $extra = array();

  foreach (quiz_question_get_types() as $name => $question_type) {
    $extra['quiz_question'][$name] = array(
        'display' => array(
            'title'           => array(
                'label'       => t('Title'),
                'description' => t("Question's title."),
                'weight'      => -10,
            ),
            'question_plugin' => array(
                'label'       => t("Plugin fields"),
                'description' => t("Custom fields defined by question plugin."),
                'weight'      => -5,
            ),
        ),
        'form'    => array(
            'title'           => array(
                'label'       => t('Title'),
                'description' => t("Question's title."),
                'weight'      => -10,
            ),
            'question_plugin' => array(
                'label'       => t("Plugin fields"),
                'description' => t("Custom fields defined by question plugin."),
                'weight'      => -5,
            ),
            'feedback'        => array(
                'label'       => t('Question feedback'),
                'description' => '',
                'weight'      => -1,
            ),
        ),
    );

    if (module_exists('locale')) {
      $extra['quiz_question'][$name]['form']['language'] = array(
          'label'       => t('Language'),
          'description' => t('Language selector'),
          'weight'      => -20,
      );
    }
  }

  return $extra;
}

/**
 * Implements hook_get_report().
 *
 * @return
 *  Node containing all of the items from the question plus the user's answer.
 */
function quiz_question_get_report($qid, $vid, $result_id) {
  if (!$response_instance = quiz_answer_controller()->getInstance($result_id, NULL, NULL, $qid, $vid)) {
    drupal_set_message(t('Unable to load question with qid %qid and vid %vid', array('%qid' => $qid, '%vid' => $vid)), 'error');
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
function quiz_question_quiz_question_score($quiz, Question $question, $result_id = NULL) {
  if (!isset($quiz) && !isset($result_id)) {
    return $question->max_score;
  }

  if (!$plugin = $question->getPlugin()) {
    return FALSE;
  }

  $score = new stdClass();
  $score->possible = $plugin->getMaximumScore();
  $score->question_qid = $question->qid;
  $score->question_vid = $question->vid;

  if (isset($result_id)) {
    $handler = quiz_answer_controller()->getInstance($result_id, $plugin->question);
    $score->attained = $score->possible > 0 ? $handler->getScore() : 0;
    $score->possible = $handler->getMaxScore();
    $score->is_evaluated = $handler->isEvaluated();
  }
  return $score;
}

/**
 * Implements hook_modules_enable().
 *
 * Create default question bundle base on new question plugins enabled.
 *
 * @param string[] $modules
 */
function quiz_question_modules_enabled($modules) {
  foreach ($modules as $module) {
    if (!$info = module_invoke($module, 'quiz_question_info')) {
      continue;
    }

    // Do not create if already have one
    $sql = 'SELECT 1 FROM {quiz_question_type} WHERE plugin = :module';
    if (db_query($sql, array(':module' => $module))->fetchColumn()) {
      continue;
    }

    entity_create('quiz_question_type', array(
        'type'        => 'default_' . $module,
        'label'       => $info[$module]['name'],
        'status'      => 0,
        'plugin'      => $module,
        'description' => isset($info[$module]['description']) ? $info[$module]['description'] : '',
    ))->save();
  }
}

/**
 * Implements hook_modules_disabled().
 *
 * @param string[] $modules
 */
function quiz_question_modules_disabled($modules) {
  $plugin_names = array();

  foreach ($modules as $module) {
    if ($info = module_invoke($module, 'quiz_question_info')) {
      $plugin_names[] = $module;
    }
  }

  if (!empty($plugin_names)) {
    db_update('quiz_question_type')
      ->fields(array('disabled' => 1))
      ->condition('plugin', $plugin_names)
      ->execute()
    ;
  }
}

# ---------------------------------------------------------------
# To be removed
# ---------------------------------------------------------------

/**
 * Delete the question from the db, and mark its identifiers in the quiz linking
 * table as "NEVER".  This is safer than deleting them and allows for same
 * tracing of what's happened if a question was deleted unintentionally.
 *
 * @param \Drupal\quiz_question\Entity\Question $question the question
 * @param bool $only_this_version whether to delete only the specific revision of the question
 */
function _quiz_delete_question(&$question, $only_this_version) {
  // let each question class delete its own stuff
  $question->getPlugin()->delete($only_this_version);

  // FIXME QuizQuestion class makes these relationships, so it should handle their 'deletion' too
  // FIXME alternately, move the relationship handling out of QuizQuestion class
  // @todo reconsider this QUESTION_NEVER status, since the node is actually gone
  // then remove it from {quiz_relationship} linking table
  //$base_sql = "UPDATE {quiz_relationship} SET question_status = " . QUESTION_NEVER;
  $select_sql = 'SELECT quiz_vid FROM {quiz_relationship}';
  if ($only_this_version) {
    $select_sql .= ' WHERE question_qid = :question_qid AND question_vid = :question_vid';
    $filter_arg = array(':question_qid' => $question->qid, ':question_vid' => $question->vid);
  }
  else {
    $select_sql .= ' WHERE question_qid = :question_qid';
    $filter_arg = array(':question_qid' => $question->qid);
  }

  $res = db_query($select_sql, $filter_arg);

  $update = db_update('quiz_relationship')
    ->fields(array('question_status' => QUIZ_QUESTION_NEVER))
    ->condition('question_qid', $question->qid);
  if ($only_this_version) {
    $update = $update->condition('question_vid', $question->vid);
  }
  $update->execute();

  $quizzes_to_update = array();
  while ($quizzes_to_update[] = $res->fetchField()) {

  }
  quiz_update_max_score_properties($quizzes_to_update);
}
