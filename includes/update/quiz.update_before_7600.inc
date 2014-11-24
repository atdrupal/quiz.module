<?php

// should have been named quiz_update_7401
function quiz_update_7101(&$sandbox) {
  db_add_field('quiz_user_settings', 'show_passed', array('type' => 'int', 'not null' => TRUE, 'default' => 1, 'size' => 'tiny'));
  return t('Done !');
}

/**
 * Implements hook_update_N
 */
function quiz_update_7402(&$sandbox) {
  if (!db_field_exists('quiz_node_properties', 'summary_pass_format')) {
    db_add_field('quiz_node_properties', 'summary_pass_format', array('type' => 'varchar', 'length' => 255));
    db_add_field('quiz_node_properties', 'summary_default_format', array('type' => 'varchar', 'length' => 255));
    db_add_field('quiz_node_result_options', 'option_summary_format', array('type' => 'varchar', 'length' => 255));
    db_add_field('quiz_user_settings', 'summary_pass_format', array('type' => 'varchar', 'length' => 255));
    db_add_field('quiz_user_settings', 'summary_default_format', array('type' => 'varchar', 'length' => 255));
  }
  return t("Added new format fields to the tables if they didn't already exist.");
}

/**
 * Adds index on vid column to the quiz_node_results table and on child_vid to
 * the quiz_node_relationship table.
 */
function quiz_update_7403() {
  db_add_index('quiz_node_results', 'vid', array('vid'));
  db_add_index('quiz_node_relationship', 'child_id', array('child_vid'));
}

/**
 * Increase the maximum quiz size
 */
function quiz_update_7404() {
  db_change_field('quiz_node_properties', 'number_of_random_questions', 'number_of_random_questions', array(
      'type'     => 'int',
      'size'     => 'small',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default'  => 0,
  ));
  db_change_field('quiz_node_results_answers', 'number', 'number', array(
      'type'     => 'int',
      'size'     => 'small',
      'unsigned' => FALSE,
      'not null' => TRUE,
      'default'  => 1,
  ));
  return t('Increased the maximum quiz size.');
}

/**
 * Remove unsigned attribute from field time_start and time_end in quiz_node_results table.
 */
function quiz_update_7405() {
  $spec = array(
      'type'     => 'int',
      'unsigned' => FALSE,
      'default'  => 0,
  );
  db_change_field('quiz_node_results', 'time_start', 'time_start', $spec);
  db_change_field('quiz_node_results', 'time_end', 'time_end', $spec);
  return t('Removed unsigned attribute from field time_start and time_end in quiz_node_results table');
}

/**
 * Adding columns mark answers as doubtful
 */
function quiz_update_7406(&$sandbox) {
  $spec = array(
      'type'     => 'int',
      'not null' => TRUE,
      'default'  => 0,
      'size'     => 'tiny'
  );
  db_add_field('quiz_node_results_answers', 'is_doubtful', $spec);
  db_add_field('quiz_node_properties', 'mark_doubtful', $spec);
  return t('Added new format fields to the tables');
}

/**
 * Adding auto update max score
 */
function quiz_update_7407(&$sandbox) {
  $spec = array(
      'type'     => 'int',
      'size'     => 'tiny',
      'not null' => TRUE,
      'default'  => 0,
  );
  db_add_field('quiz_node_relationship', 'auto_update_max_score', $spec);
  return t('Added new auto update max score field to the quiz_node_relationship table');
}

/**
 * Adding userpoints tid column
 */
function quiz_update_7409(&$sandbox) {
  $table = 'quiz_node_properties';
  $schema = drupal_get_schema_unprocessed('quiz', $table);
  foreach (array('userpoints_tid') as $field) {
    db_add_field($table, $field, $schema['fields'][$field]);
  }
  return t('Adding userpoints tid column to quiz_node_properties');
}

/**
 * Add new layout field to the quiz_node_results table.
 */
function quiz_update_7500() {
  $spec = array(
      'serialize'   => TRUE,
      'type'        => 'text',
      'description' => "Serialized layout data.",
      'size'        => 'medium',
  );
  db_add_field('quiz_node_results', 'layout', $spec);
  return t('Added new layout field to the quiz_node_results table');
}

/**
 * Add new result_answer_id field to the quiz_node_results_answers table.
 */
function quiz_update_7501() {
  db_drop_primary_key('quiz_node_results_answers');
  db_add_unique_key('quiz_node_results_answers', 'result_answer', array('result_id', 'question_nid', 'question_vid'));
  $spec = array(
      'description' => 'The result answer ID.',
      'type'        => 'serial',
      'unsigned'    => TRUE,
      'not null'    => TRUE,
  );
  db_add_field('quiz_node_results_answers', 'result_answer_id', $spec, array('primary key' => array('result_answer_id')));
  return t('Added new result_answer_id field to the quiz_node_results_answers table.');
}

/**
 * Add new qnr_id field to the quiz_node_relationship table.
 */
function quiz_update_7502() {
  db_drop_primary_key('quiz_node_relationship');
  db_add_unique_key('quiz_node_relationship', 'parent_child', array('parent_nid', 'parent_vid', 'child_nid', 'child_vid'));
  $spec = array(
      'type'     => 'serial',
      'size'     => 'normal',
      'unsigned' => TRUE,
      'not null' => TRUE,
  );
  db_add_field('quiz_node_relationship', 'qnr_id', $spec, array('primary key' => array('qnr_id')));
  return t('Added new qnr_id field to the quiz_node_relationship table.');
}

/**
 * Add new qnr_pid field to the quiz_node_relationship table.
 */
function quiz_update_7503() {
  $spec = array(
      'type'     => 'int',
      'size'     => 'normal',
      'unsigned' => TRUE,
      'not null' => FALSE,
      'default'  => NULL,
  );
  db_add_field('quiz_node_relationship', 'qnr_pid', $spec);
  return t('Added new qnr_pid field to the quiz_node_relationship table.');
}

/**
 * Allow time_start and time_end to be NULL. The time "0" is still a valid time.
 * This lets us do better filtering in Views (where NULL).
 */
function quiz_update_7504() {
  $spec = array(
      'type'     => 'int',
      'unsigned' => FALSE,
  );
  db_change_field('quiz_node_results', 'time_start', 'time_start', $spec);
  db_change_field('quiz_node_results', 'time_end', 'time_end', $spec);
  db_query("UPDATE {quiz_node_results} SET time_end = NULL WHERE time_end = 0");
  return t('Removed default attribute from field time_start and time_end in quiz_node_results table.');
}

/**
 * Revamping quiz feedback options.
 */
function quiz_update_7505() {
  db_add_field('quiz_node_properties', 'review_options', array(
      'type'      => 'text',
      'serialize' => TRUE,
  ));

  drupal_get_schema(NULL, TRUE);

  $sql = "SELECT * FROM {quiz_node_properties}";
  $result = db_query($sql);
  while ($row = $result->fetch()) {
    if ($row->feedback_time == 0) {
      $row->review_options['end']['answer_feedback'] = 'answer_feedback';
      if ($row->display_feedback) {
        $row->review_options['end']['solution'] = 'solution';
      }
    }
    if ($row->feedback_time == 1) {
      $row->review_options['question']['answer_feedback'] = 'answer_feedback';
      if ($row->display_feedback) {
        $row->review_options['question']['solution'] = 'solution';
      }
    }
    if ($row->feedback_time == 2) {
      $row->review_options = array();
    }
    drupal_write_record('quiz_node_properties', $row, array('nid', 'vid'));
  }

  db_drop_field('quiz_node_properties', 'feedback_time');
  db_drop_field('quiz_node_properties', 'display_feedback');
}

/**
 * Add qnp_id and uid so we can have better quiz node defaults.
 */
function quiz_update_7506() {
  db_drop_primary_key('quiz_node_properties');
  db_add_field('quiz_node_properties', 'qnp_id', array(
      'type' => 'serial',
    ), array('primary key' => array('qnp_id')));

  db_add_field('quiz_node_properties', 'uid', array(
      'type'     => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default'  => 0,
  ));

  // We could do this, but we should really migrate user settings from 4.x.
  // Patches welcome.
  // db_drop_table('quiz_user_settings');
}

/**
 * Add allow_change to restrict users from changing answers.
 */
function quiz_update_7507() {
  db_add_field('quiz_node_properties', 'allow_change', array(
      'type'     => 'int',
      'size'     => 'small',
      'not null' => TRUE,
      'default'  => 1,
  ));
}

/**
 * Make our answer_timestamp field NULLable for storing the attempt layout in
 * the database.
 */
function quiz_update_7509() {
  db_change_field('quiz_node_results_answers', 'answer_timestamp', 'answer_timestamp', array(
      'type'     => 'int',
      'unsigned' => TRUE,
      'not null' => FALSE,
      'default'  => NULL,
    )
  );

  db_drop_field('quiz_node_results', 'layout');
}
