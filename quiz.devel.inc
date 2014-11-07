<?php

function quiz_generate_form($form, $form_state) {
  foreach (quiz_get_types() as $name => $quiz_type) {
    $quiz_options[$name] = $quiz_type->label;
  }

  $form['quiz_types'] = array(
      '#type'    => 'checkboxes',
      '#title'   => t('Quiz types'),
      '#options' => $quiz_options,
  );

  $question_options = array();
  foreach (quiz_get_question_types() as $name => $question_type) {
    $question_options[$name] = $question_type['name'];
  }

  $form['quiz_question_types'] = array(
      '#type'          => 'checkboxes',
      '#title'         => t('Question types'),
      '#options'       => $question_options,
      '#default_value' => array('quiz'),
      '#required'      => TRUE,
  );

  $form['quiz_limit'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Maximum number of quizzes per type.'),
      '#default_value' => 10,
      '#size'          => 10,
      '#required'      => TRUE,
  );

  $form['quiz_question_limit'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Maximum number of questions per quiz.'),
      '#default_value' => 30,
      '#size'          => 10,
  );

  $form['submit'] = array(
      '#type'  => 'submit',
      '#value' => t('Generate')
  );

  return $form;
}

function quiz_generate_form_submit($form, &$form_state) {
  module_load_include('inc', 'devel_generate', 'devel_generate.fields');
  module_load_include('inc', 'devel_generate', 'devel_generate');

  $quiz_types = array_filter($form_state['values']['quiz_types']);
  $quiz_limit = $form_state['values']['quiz_limit'];
  $question_types = array_filter($form_state['values']['quiz_question_types']);
  $question_limit = $form_state['values']['quiz_question_limit'];

  for ($i = 0; $i < $quiz_limit; ++$i) {
    quiz_generate($quiz_type = array_rand($quiz_types), $question_types, $question_limit);
  }
}

function quiz_generate($quiz_type, $question_types, $question_limit) {
  $quiz = entity_create('node', array(
      'type'    => $quiz_type,
      'title'   => devel_create_greeking(rand(5, 10), TRUE),
      'uid'     => rand(0, 1),
      'created' => REQUEST_TIME,
      'changed' => REQUEST_TIME,
  ));
  $quiz->save();

  $limit = rand(1, $this->question_limit);
  for ($i = 0; $i < $limit; ++$i) {
    quiz_generate_questions($quiz, array_rand($question_types));
  }

  drupal_set_message('Geneated quiz: ' . l($quiz->title, 'node/' . $quiz->nid));
}

function quiz_generate_questions($quiz, $question_type) {
  $question_array = array(
      'type'      => $question_type,
      'quiz_nid'  => $quiz->nid,
      'quiz_vid'  => $quiz->vid,
      'comment'   => 2,
      'changed'   => REQUEST_TIME,
      'moderate'  => 0,
      'promote'   => 0,
      'revision'  => 1,
      'log'       => '',
      'status'    => 1,
      'sticky'    => 0,
      'revisions' => NULL,
      'language'  => LANGUAGE_NONE,
      'title'     => devel_create_greeking(rand(5, 20), TRUE),
      'body'      => array(LANGUAGE_NONE => array(array('value' => devel_create_para(rand(20, 50), 1)))),
  );

  switch ($question_type) {
    case 'truefalse':
      $question_array += array('correct_answer' => rand(0, 1));
      break;
    case 'short_answer':
      $question_array += array(
          'correct_answer_evaluation' => rand(ShortAnswerQuestion::ANSWER_MATCH, ShortAnswerQuestion::ANSWER_MANUAL),
          'correct_answer'            => devel_create_greeking(rand(10, 20)),
      );
      break;
    case 'long_answer':
      $question_array += array(
          'rubric' => devel_create_greeking(rand(10, 20))
      );
      break;
    case 'multichoice':
      $question_array += quiz_generate_dummy_multichoice_question_info();
      break;
    case 'quiz_directions':
      break;
    default:
      throw new RuntimeException('Unsupported question: ' . $question_type);
  }

  // Create question node
  $question = (object) $question_array;
  node_save($question);
  devel_generate_fields($question, 'node', $question_type);

  _quiz_question_get_instance($question)->saveRelationships();
}

function quiz_generate_dummy_multichoice_question_info() {
  $array = array(
      'choice_multi'   => array_rand(array(0, 1)),
      'choice_random'  => array_rand(array(0, 1)),
      'choice_boolean' => array_rand(array(0, 1)),
  );

  $rand = $array['choice_multi'] ? 1 : rand(2, 10);
  for ($i = 0; $i < $rand; ++$i) {
    $array['alternatives'][] = array(
        'answer'                 => array(
            'value'  => devel_create_greeking(rand(2, 10)),
            'format' => 'filtered_html',
        ),
        'feedback_if_chosen'     => array(
            'value'  => devel_create_greeking(rand(5, 10)),
            'format' => 'filtered_html',
        ),
        'feedback_if_not_chosen' => array(
            'value'  => devel_create_greeking(rand(5, 10)),
            'format' => 'filtered_html',
        ),
        'score_if_chosen'        => 1,
        'score_if_not_chosen'    => 0,
    );
  }

  return $array;
}
