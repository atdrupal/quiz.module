<?php

/**
 * @file
 * quiz.pages.inc
 *
 * This file contains function callbacks for forms and pages. Long or complex
 * functions are moved to classes.
 */
use Drupal\quiz\Controller\Admin\QuizAdminSettingsController;
use Drupal\quiz\Controller\Admin\QuizRevisionsAdminController;
use Drupal\quiz\Controller\QuizQuestionFeedbackController;
use Drupal\quiz\Controller\QuizTakeController;
use Drupal\quiz\Controller\QuizTakeQuestionController;
use Drupal\quiz\Controller\QuizUserResultController;
use Drupal\quiz\Entity\QuizEntity;
use Drupal\quiz\Entity\Result;
use Drupal\quiz\Form\QuizAdminEntityForm;
use Drupal\quiz\Form\QuizAnsweringForm;
use Drupal\quiz\Form\QuizCategorizedForm;
use Drupal\quiz\Form\QuizEntityForm;
use Drupal\quiz\Form\QuizQuestionsForm;
use Drupal\quiz\Form\QuizReportForm;
use Drupal\quiz\Form\QuizTypeForm;
use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\QuestionPlugin;

/**
 * Form for /admin/quiz/settings/config.
 */
function quiz_admin_settings_form($form, $form_state) {
  $obj = new QuizAdminSettingsController();
  return $obj->getForm($form, $form_state);
}

function quiz_admin_settings_form_validate($form, $form_state) {
  $obj = new QuizAdminSettingsController();
  return $obj->validate($form, $form_state);
}

function quiz_admin_settings_form_submit($form, $form_state) {
  $obj = new QuizAdminSettingsController();
  return $obj->submit($form, $form_state);
}

function quiz_admin_entity_form($form, $form_state) {
  $obj = new QuizAdminEntityForm();
  return $obj->getForm($form, $form_state);
}

function quiz_admin_entity_form_validate($form, $form_state) {
  $obj = new QuizAdminEntityForm();
  return $obj->validateForm($form, $form_state);
}

function quiz_admin_entity_form_submit($form, $form_state) {
  $obj = new QuizAdminEntityForm();
  return $obj->submitForm($form, $form_state);
}

/**
 * Define quiz-type editing form.
 */
function quiz_type_form($form, &$form_state, $quiz_type, $op = 'edit') {
  $obj = new QuizTypeForm();
  return $obj->get($form, $form_state, $quiz_type, $op);
}

function quiz_type_form_submit_delete($form, &$form_state) {
  $form_state['redirect'] = 'admin/structure/quiz/manage/' . $form_state['quiz_type']->type . '/delete';
}

function quiz_type_form_submit($form, &$form_state) {
  $obj = new QuizTypeForm();
  return $obj->submit($form, $form_state);
}

/**
 * @TODO Only list quiz type if user has permission to create it.
 * @return string
 */
function quiz_entity_adding_landing_page() {
  return theme_render_template(drupal_get_path('module', 'quiz') . '/theme/quiz-adding-landing.tpl.php', array(
      'quiz_types' => quiz_get_types()
  ));
}

/**
 * Callback for /quiz/add/%
 *
 * @param string $quiz_type_name
 */
function quiz_entity_adding_page($quiz_type_name) {
  $quiz = entity_create('quiz_entity', array('type' => $quiz_type_name));
  return entity_ui_get_form('quiz_entity', $quiz, 'add');
}

/**
 * Define quiz-entity editing form.
 */
function quiz_entity_form($form, &$form_state, $quiz, $op = 'edit') {
  $obj = new QuizEntityForm($quiz);
  return $obj->get($form, $form_state, $op);
}

function quiz_entity_form_validate($form, &$form_state) {
  $quiz = $form_state['quiz_entity'];
  $obj = new QuizEntityForm($quiz);
  return $obj->validate($form, $form_state);
}

function quiz_entity_form_submit($form, &$form_state) {
  $quiz = $form_state['quiz_entity'];
  $obj = new QuizEntityForm($quiz);
  return $obj->submit($form, $form_state);
}

function quiz_question_admin_page(QuizEntity $quiz) {
  module_load_include('pages.inc', 'quiz_question');

  if ($quiz->randomization >= 3) {
    return drupal_get_form('quiz_categorized_form', $quiz);
  }

  return array(
      '#type'            => 'vertical_tabs',
      'question_admin'   => array(
          '#type'  => 'fieldset',
          '#title' => t('Manage questions'),
          '#value' => '',
          'links'  => array(
              '#type'        => 'fieldset',
              '#title'       => t('Create new question'),
              '#collapsible' => TRUE,
              '#collapsed'   => TRUE,
              '#value'       => quiz_question_adding_landing_page('quiz/' . $quiz->identifier() . '/questions'),
          ),
          'form'   => drupal_get_form('quiz_questions_form', $quiz),
      ),
      'global_questions' => array(
          '#type'  => 'fieldset',
          '#title' => t('Question bank'),
          '#value' => views_get_view('quiz_question_bank')->preview(),
      ),
  );
}

/**
 * Quiz's questions management form.
 */
function quiz_questions_form($form, $form_state, QuizEntity $quiz) {
  $obj = new QuizQuestionsForm();
  $array = $obj->formGet($form, $form_state, $quiz);
  $array['#validate'][] = 'quiz_questions_form_validate';
  return $array;
}

function quiz_questions_form_validate($form, $form_state) {
  $obj = new QuizQuestionsForm();
  return $obj->formValidate($form, $form_state);
}

function quiz_questions_form_submit($form, $form_state) {
  $obj = new QuizQuestionsForm();
  return $obj->formSubmit($form, $form_state);
}

function quiz_categorized_form($form, $form_state, QuizEntity $quiz) {
  $obj = new QuizCategorizedForm();
  return $obj->getForm($form, $form_state, $quiz);
}

function quiz_categorized_form_validate($form, &$form_state) {
  $obj = new QuizCategorizedForm();
  return $obj->formValidate($form, $form_state);
}

function quiz_categorized_form_submit($form, &$form_state) {
  $obj = new QuizCategorizedForm();
  return $obj->formSubmit($form, $form_state);
}

/**
 * Callback for quiz/%quiz
 */
function quiz_page(QuizEntity $quiz, $view_mode = 'default', $langcode = NULL) {
  return entity_view('quiz_entity', array($quiz), $view_mode, $langcode);
}

/**
 * Callback for /quiz/%quiz/revisions.
 *
 * @param QuizEntity $quiz
 */
function quiz_revisions_page(QuizEntity $quiz) {
  $obj = new QuizRevisionsAdminController();
  return $obj->render($quiz);
}

/**
 * Callback for quiz/%/take
 */
function quiz_take_page(QuizEntity $quiz) {
  global $user;
  $obj = new QuizTakeController($quiz, $user);
  return $obj->render();
}

/**
 * Callback for quiz/%/take/%. Take a quiz questions.
 *
 * @param QuizEntity $quiz A quiz entity
 * @param int $page_number
 *   A question number, starting at 1. Pages do not have question numbers. Quiz
 *   directions are considered part of the numbering.
 */
function quiz_question_take_page(QuizEntity $quiz, $page_number) {
  $result = $layout_item = NULL;
  $quiz_id = $quiz->qid;

  if (isset($_SESSION['quiz'][$quiz_id]['result_id'])) {
    $result = quiz_result_load($_SESSION['quiz'][$quiz_id]['result_id']);
  }

  // Load the page that the requested question belongs to.
  if ($result && ($_layout_item = $result->getPageItem($page_number))) {
    $layout_item = quiz_question_entity_load($_layout_item['nid'], $_layout_item['vid']);
  }

  $controller = new QuizTakeQuestionController($quiz, $result, $page_number, $layout_item);
  return $controller->render();
}

/**
 * Jumper form.
 */
function quiz_jumper_form($form, $form_state, $questions, $current) {
  $form['#attached']['js'][] = drupal_get_path('module', 'quiz') . '/misc/js/quiz.jumper.js';
  $form['question_number'] = array(
      '#type'          => 'select',
      '#options'       => $questions,
      '#default_value' => $current,
  );
  $form['submit'] = array(
      '#type'       => 'submit',
      '#value'      => t('Jump'),
      '#attributes' => array('class' => array('js-hide')),
  );
  return $form;
}

function quiz_jumper_form_submit($form, &$form_state) {
  $quiz = quiz_load(quiz_get_id_from_url());
  quiz()
    ->getQuizHelper()
    ->getQuestionHelper()
    ->redirect($quiz, $form_state['values']['question_number'] - 1);
  $form_state['redirect'] = "quiz/" . $quiz->qid . "/take/" . $form_state['values']['question_number'];
}

/**
 * Question answering form.
 *
 * @param array $form
 * @param array $form_state
 * @param QuizEntity $quiz
 * @param Question $question
 * @param int $page_number
 * @param Result $result
 * @return array
 */
function quiz_answer_form($form, $form_state, QuizEntity $quiz, Question $question, $page_number, Result $result) {
  $obj = new QuizAnsweringForm($quiz, $question, $page_number, $result);

  if (is_array($question) || ('quiz_page' !== $question->getQuestionType()->plugin)) {
    $form += $obj->getForm($form, $form_state, is_array($question) ? $question : array($question));
  }
  else {
    $form += $obj->getForm($form, $form_state, QuizAnsweringForm::findPageQuestions($result, $question));
  }
  return $form;
}

function quiz_answer_form_validate($form, &$form_state) {
  $quiz = $form['#quiz'];
  $question = $form['#question'];
  $page_number = $form['#page_number'];
  $result = $form['#result'];
  $obj = new QuizAnsweringForm($quiz, $question, $page_number, $result);
  return $obj->formValidate($form, $form_state);
}

function quiz_answer_form_submit_back($form, &$form_state) {
  $quiz = $form['#quiz'];
  $question = $form['#question'];
  $page_number = $form['#page_number'];
  $result = $form['#result'];
  $obj = new QuizAnsweringForm($quiz, $question, $page_number, $result);
  return $obj->getSubmit()->formBackSubmit($form, $form_state);
}

function quiz_answer_form_submit_blank($form, &$form_state) {
  $quiz = $form['#quiz'];
  $question = $form['#question'];
  $page_number = $form['#page_number'];
  $result = $form['#result'];
  $obj = new QuizAnsweringForm($quiz, $question, $page_number, $result);
  return $obj->getSubmit()->formBlankSubmit($form, $form_state);
}

function quiz_answer_form_submit($form, &$form_state) {
  $quiz = $form['#quiz'];
  $question = $form['#question'];
  $page_number = $form['#page_number'];
  $result = $form['#result'];
  $obj = new QuizAnsweringForm($quiz, $question, $page_number, $result);
  return $obj->getSubmit()->formSubmit($form, $form_state);
}

function quiz_question_element_validate(&$element, &$form_state) {
  return QuestionPlugin::elementValidate($element, $form_state);
}

function quiz_report_form_element_validate(&$element, &$form_state) {
  return QuizReportForm::validateElement($element, $form_state);
}

/**
 * Callback for quiz/%/take/%question_number/feedback. Show feedback for a
 * question response.
 */
function quiz_question_feedback_page(QuizEntity $quiz, $page_number) {
  $result_id = empty($_SESSION['quiz'][$quiz->qid]['result_id']) ? $_SESSION['quiz']['temp']['result_id'] : $_SESSION['quiz'][$quiz->qid]['result_id'];
  $result = quiz_result_load($result_id);
  $controller = new QuizQuestionFeedbackController($quiz, $result);
  return $controller->render($page_number);
}

function quiz_results_page(QuizEntity $quiz) {
  return views_embed_view('quiz_results', 'default', $quiz->qid);
}

function quiz_results_user_page(QuizEntity $quiz) {
  global $user;
  return views_embed_view('quiz_user_results', 'page', $quiz->qid, $user->uid);
}

/**
 * Pass the correct mark to the theme so that theme authors can use an image.
 *
 * @ingroup themeable
 */
function theme_quiz_answer_result($variables) {
  $type = $variables['type'];
  return '<div class="quiz-score-icon ' . $type . '"></div>';
}

/**
 * Theme a progress indicator for use during a quiz.
 *
 * @param $question_number
 *  The position of the current question in the sessions' array.
 * @param $num_of_question
 *  The number of questions for the quiz.
 * @return
 *  Themed html.
 *
 * @ingroup themeable
 */
function theme_quiz_progress($variables) {
  $output = '';
  $output .= '<div id="quiz-progress">';
  $text = 'Page <span id="quiz-question-number">!x</span> of <span id="quiz-num-questions">@y</span>';
  if ($variables['allow_jumping']) {
    // Show jump form.
    if ($variables['pager']) {
      $output .= theme('quiz_pager', array(
          'quiz'     => $variables['quiz'],
          'siblings' => variable_get('quiz_pager_siblings', 5),
          'current'  => $variables['current'],
          'total'    => count($variables['questions']),
      ));
    }
    else {
      $selectbox = drupal_get_form('quiz_jumper_form', $variables['questions'], $variables['current']);
      $output .= t($text, array('!x' => drupal_render($selectbox), '@y' => count($variables['questions'])));
    }
  }
  else {
    // Just text.
    $output .= t($text, array('!x' => $variables['current'], '@y' => count($variables['questions'])));
  }

  $output .= '</div>' . "\n";
  // Add div to be used by jQuery countdown
  if ($variables['time_limit']) {
    $output .= '<div class="countdown"></div>';
  }
  return $output;
}

/**
 * Theme the "no feedback" option.
 *
 * @return
 *  Themed html feedback.
 *
 * @ingroup themeable
 */
function theme_quiz_no_feedback() {
  return t('Thanks for taking the @quiz!', array('@quiz' => QUIZ_NAME));
}

/**
 * Theme the stats on the views page
 */
function theme_quiz_view_stats($variables) {
  $quiz = $variables['quiz'];

  // Fetch data
  $stats[] = array('title' => t('Questions'), 'data' => isset($quiz->number_of_questions) ? $quiz->number_of_questions : 0);

  if ($quiz->show_attempt_stats) {
    $takes = $quiz->takes == 0 ? t('Unlimited') : $quiz->takes;
    $stats[] = array('title' => t('Attempts allowed'), 'data' => $takes);
  }

  if ($quiz->quiz_always) {
    $stats[] = array('title' => t('Available'), 'data' => t('Always'));
  }
  else {
    $stats[] = array('title' => t('Opens'), 'data' => format_date($quiz->quiz_open, 'short'));
    $stats[] = array('title' => t('Closes'), 'data' => format_date($quiz->quiz_close, 'short'));
  }

  if (!empty($quiz->pass_rate)) {
    $stats[] = array('title' => t('Pass rate'), 'data' => $quiz->pass_rate . ' %');
  }

  if (!empty($quiz->time_limit)) {
    $stats[] = array('title' => t('Time limit'), 'data' => quiz()->formatDuration($quiz->time_limit));
  }

  $stats[] = array('title' => t('Backwards navigation'), 'data' => $quiz->backwards_navigation ? t('Allowed') : t('Forbidden'));

  // Format and output the data
  $table = '';
  foreach ($stats as $stat) {
    $table .= '<tr>'
      . '<td class="quiz-view-table-title"><strong>' . $stat['title'] . ':</strong></td>'
      . '<td class="quiz-view-table-data"><em>' . $stat['data'] . '</em></td>'
      . '</tr>';
  }
  return '<table id="quiz-view-table">' . $table . '</table>';
}

/**
 * Callback for /quiz-result/%quiz_result.
 *
 * @param Result $result
 */
function quiz_result_page(Result $result) {
  $quiz = quiz_load($result->quiz_qid);
  $quiz_revision = quiz_load($result->quiz_qid, $result->quiz_vid);
  $obj = new QuizUserResultController($quiz, $quiz_revision, $result);
  return $obj->render();
}

/**
 * Theme the result page.
 *
 * @param QuizEntity $quiz
 *  The quiz entity object.
 * @param array $questions
 *  The questions array as defined by _quiz_get_answers.
 * @param int $score
 *  Array of score information as returned by quiz_calculate_score().
 * @param string $summary
 *  Filtered text of the summary.
 * @return string
 *
 * @ingroup themeable
 */
function theme_quiz_result($variables) {
  global $user;

  $questions = $variables['questions'];
  $score = $variables['score'];
  $summary = $variables['summary'];
  $result = quiz_result_load($variables['result_id']);
  $account = $variables['account'];

  if (!$score['is_evaluated']) {
    $msg = t('Parts of this @quiz have not been evaluated yet. The score below is not final.', array(
        '@quiz' => QUIZ_NAME
    ));
    drupal_set_message($msg, 'warning');
  }

  // Display overall result.
  $params = array(
      '%num_correct'    => $score['numeric_score'],
      '%question_count' => $score['possible_score'],
      '!username'       => ($user->uid == $account->uid) ? t('You') : theme('username', array('account' => $account)),
      '@score'          => $score['percentage_score'],
      '!yourtotal'      => ($user->uid == $account->uid) ? t('Your') : t('Total'),
  );

  $output = '';

  // Show score.
  if ($result->canReview('score')) {
    $output .= '<div id="quiz_score_possible">' . t('!username got %num_correct of %question_count possible points.', $params) . '</div>' . "\n";
    $output .= '<div id="quiz_score_percent">' . t('!yourtotal score: @score%', $params) . '</div>' . "\n";
  }

  // Show quiz feedback
  if ($result->canReview('quiz_feedback')) {
    if (isset($summary['passfail'])) {
      $output .= '<div id="quiz_summary">' . $summary['passfail'] . '</div>' . "\n";
    }

    if (isset($summary['result'])) {
      $output .= '<div id="quiz_summary">' . $summary['result'] . '</div>' . "\n";
    }
  }

  $form = drupal_get_form('quiz_report_form', $result, $questions);

  return array(
      '#markup' => $output,
      '#suffix' => drupal_render($form),
  );
}

/**
 * Feedback form.
 */
function quiz_report_form($form, $form_state, $result, $questions) {
  $obj = new QuizReportForm();
  $form += $obj->getForm($form, $form_state, $result, $questions);

  if (isset($form['next'])) {
    $form['next']['#submit'] = array('quiz_report_form_submit_next');
  }

  if (isset($form['finish'])) {
    $form['finish']['#submit'] = array('quiz_report_form_submit_finish');
  }

  return $form;
}

function quiz_report_form_submit_next($form, &$form_state) {
  $obj = new QuizReportForm();
  return $obj->formSubmitFeedback($form, $form_state);
}

function quiz_report_form_submit_finish($form, &$form_state) {
  $obj = new QuizReportForm();
  return $obj->formEndSubmit($form, $form_state);
}

function quiz_report_form_submit($form, &$form_state) {
  $obj = new QuizReportForm();
  return $obj->formSubmit($form, $form_state);
}

/**
 * Theme the quiz pager.
 */
function theme_quiz_pager($variables) {
  return quiz()
      ->getQuizHelper()
      ->getTakeJumperHelper($variables['quiz'], $variables['total'], $variables['siblings'], $variables['current'])
      ->render();
}

/**
 * Theme a question selection table, adding drag and drop support.
 */
function theme_question_selection_table($variables) {
  $form = $variables['form'];

  // Building headers
  $headers = array(t('Question'), t('Type'), t('Actions'), t('Update'), t('Max score'), t('Auto update max score'), t('Delete'));
  if (isset($form['compulsories'])) {
    $headers[] = t('Compulsory');
  }
  $headers[] = t('Weight');
  $headers[] = t('Parent ID');
  $headers[] = array('data' => t('ID'), 'class' => array('tabledrag-hide'));

  // Building table body
  if (!empty($form['titles'])) {
    foreach (element_children($form['titles']) as $id) {
      $form['weights'][$id]['#attributes']['class'] = array('question-list-weight');
      $form['qr_ids'][$id]['#attributes']['class'] = array('relationship-id');
      $form['qr_pids'][$id]['#attributes']['class'] = array('relationship-pid');
      $rows[] = _quiz_get_question_row($form, $id, 'quiz_page' === $form['#question_handlers'][$id] ? 'tabledrag-root' : 'tabledrag-leaf');
    }
    // Make sure the same fields aren't rendered twice
    unset($form['types'], $form['view_links'], $form['remove_links'], $form['stayers']);
    unset($form['max_scores'], $form['auto_update_max_scores'], $form['revision'], $form['weights'], $form['titles'], $form['compulsories'], $form['qr_ids'], $form['qr_pids']);
  }

  // We hide the table if no questions have been added so that jQuery can show
  // it the moment the first question is beeing added.
  $html_attr = array();
  if (isset($form['no_questions'])) {
    $html_attr['style'] = "display:none;";
  }

  drupal_add_tabledrag('question-list', 'match', 'parent', 'relationship-pid', 'relationship-pid', 'relationship-id', TRUE);
  drupal_add_tabledrag('question-list', 'order', 'sibling', 'question-list-weight');

  return drupal_render($form['random_settings'])
    . theme('table', array(
        'header'     => $headers,
        'rows'       => !empty($rows) ? $rows : array(),
        'empty'      => t('No question'),
        'attributes' => array('id' => 'question-list') + $html_attr))
    . drupal_render_children($form);
}

/**
 * Helper function for theme_question_selection_table
 *
 * TODO: DELETE
 *
 * @see quiz_questions_form()
 * @see theme_question_selection_table()
 *
 * @param $sub_form
 *   Form definition array for a filtered questions list
 * @param $id
 *   Identifier used in $sub_form
 * @return table row
 *   Array defining a table row
 */
function _quiz_get_question_row($sub_form, $id, $item_type) {
  $type = $sub_form['types'][$id]['#markup'];

  $action = theme('item_list', array(
      'items'      => array(
          drupal_render($sub_form['view_links'][$id]),
          '<SPAN CLASS="q-remove" STYLE="display:none">' . drupal_render($sub_form['remove_links'][$id]) . '</SPAN>',
      ),
      'attributes' => array(
          'class' => array('links', 'inline'),
      ),
  ));
  $qr_pid = $sub_form['qr_pids'][$id]['#default_value'];
  $data_array = array(
      // The checkbox and the title
      ($qr_pid ? theme('indentation', array('size' => 1)) : NULL) . drupal_render($sub_form['titles'][$id]),
      $type,
      $action,
      isset($sub_form['revision'][$id]) ? drupal_render($sub_form['revision'][$id]) : t("Up to date"),
      drupal_render($sub_form['max_scores'][$id]),
      drupal_render($sub_form['auto_update_max_scores'][$id]),
      drupal_render($sub_form['stayers'][$id]),
  );
  if (isset($sub_form['compulsories'])) {
    $data_array[] = drupal_render($sub_form['compulsories'][$id]);
  }
  $data_array[] = drupal_render($sub_form['weights'][$id]);
  $data_array[] = drupal_render($sub_form['qr_pids'][$id]);
  $data_array[] = array(
      'class' => array('tabledrag-hide'),
      'data'  => drupal_render($sub_form['qr_ids'][$id]),
  );

  return array(
      'class' => array('q-row', 'draggable', $item_type),
      'id'    => 'q-' . $id,
      'data'  => $data_array
  );
}

/**
 * Themes a categorized quiz form
 */
function theme_quiz_categorized_form($variables) {
  $form = $variables['form'];

  drupal_add_js(drupal_get_path('module', 'quiz') . '/misc/js/quiz.admin.categorized-form.js');

  $rows = array();
  foreach ($form as $key => &$existing) {
    if (!is_numeric($key)) {
      continue;
    }
    $cols = array();
    $cols[] = drupal_render($existing['name']);
    $cols[] = drupal_render($existing['number']);
    $cols[] = drupal_render($existing['max_score']);
    $cols[] = drupal_render($existing['remove']);
    $cols[] = drupal_render($existing['weight']);
    $rows[] = array('data' => $cols, 'class' => array('draggable'));
  }

  $output = '';
  if (!empty($rows)) {
    $header = array(t('Category'), t('Number of questions'), t('Max score per question'), t('Remove'), t('Weight'));
    drupal_add_tabledrag('existing-terms', 'order', 'sibling', 'term-weight', NULL, NULL, TRUE);
    $output .= theme('table', array('header' => $header, 'rows' => $rows, 'attributes' => array('id' => 'existing-terms')));
  }
  return $output . drupal_render_children($form);
}
