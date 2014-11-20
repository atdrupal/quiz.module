<?php

use Drupal\quiz\Entity\QuizEntity;
use Drupal\quiz_question\Entity\Question;

/**
 * Base test class for Quiz questions.
 */
abstract class QuizTestCase extends DrupalWebTestCase {

  protected $profile = 'testing';
  protected $extraModules = array();
  protected $extraUserPermissions = array();
  protected $questionPlugin;

  /**
   * Set up a question test case.
   *
   * @param array $modules
   *   Array of modules to enable.
   * @param array $admin_permissions
   *   Array of admin permissions to add.
   * @param array $user_permissions
   *   Array of user permissions to add.
   */
  public function setUp($modules = array(), $admin_permissions = array(), $user_permissions = array()) {
    $modules[] = 'quiz';
    $modules[] = 'quiz_question';
    foreach ($this->extraModules as $module) {
      if (!in_array($module, $modules)) {
        $modules[] = $module;
      }
    }
    parent::setUp($modules);

    $this->setUpFilters();

    $admin_permissions[] = 'administer quiz configuration';
    $admin_permissions[] = 'create quiz content';
    $admin_permissions[] = 'edit any quiz content';
    $admin_permissions[] = 'edit question titles';
    $admin_permissions[] = 'access quiz';

    $user_permissions[] = 'access quiz';
    foreach ($this->extraUserPermissions as $perm) {
      if (!in_array($perm, $user_permissions)) {
        $user_permissions[] = $perm;
      }
    }

    // Test case needs create question plugin
    if (!empty($this->questionPlugin) && $this->questionPlugin) {
      $admin_permissions[] = "create default_{$this->questionPlugin} question";
      $admin_permissions[] = "edit any default_{$this->questionPlugin} question";
    }

    $this->admin = $this->drupalCreateUser(array_unique($admin_permissions));
    $this->user = $this->drupalCreateUser(array_unique($user_permissions));
  }

  /**
   * Add text formats.
   */
  private function setUpFilters() {
    $filtered_html_format = (object) array(
          'format'  => 'filtered_html',
          'name'    => 'Filtered HTML',
          'weight'  => 0,
          'filters' => array(
              'filter_url'           => array('weight' => 0, 'status' => 1), // URL filter
              'filter_html'          => array('weight' => 1, 'status' => 1), // HTML filter
              'filter_autop'         => array('weight' => 2, 'status' => 1), // Line break filter
              'filter_htmlcorrector' => array('weight' => 10, 'status' => 1), // HTML corrector filter
          ),
    );
    filter_format_save($filtered_html_format);

    $full_html_format = (object) array(
          'format'  => 'full_html',
          'name'    => 'Full HTML',
          'weight'  => 1,
          'filters' => array(
              'filter_url'           => array('weight' => 0, 'status' => 1), // URL filter
              'filter_autop'         => array('weight' => 1, 'status' => 1), // Line break filter
              'filter_htmlcorrector' => array('weight' => 10, 'status' => 1), // HTML corrector filter
          ),
    );
    filter_format_save($full_html_format);

    // Enable default permissions for system roles.
    $filtered_html_permission = filter_permission_name($filtered_html_format);
    user_role_grant_permissions(DRUPAL_ANONYMOUS_RID, array($filtered_html_permission));
    user_role_grant_permissions(DRUPAL_AUTHENTICATED_RID, array($filtered_html_permission));
  }

  /**
   * Link a question to a new or provided quiz.
   *
   * @param Question $question
   * @param QuizEntity|null $quiz
   * @return QuizEntity
   */
  public function linkQuestionToQuiz($question, $quiz = NULL) {
    if (is_null($quiz)) {
      $quiz = $this->drupalCreateQuiz();
    }
    $question->getPlugin()->saveRelationships($quiz->qid, $quiz->vid);
    return $quiz;
  }

  /**
   * @return QuizEntity
   */
  public function drupalCreateQuiz($settings = array()) {
    $quiz = entity_create('quiz_entity', $settings + array(
        'type'           => 'quiz',
        'review_options' => array(
            'end' => drupal_map_assoc(array_keys(quiz_controller()->getFeedbackOptions()))
        ),
    ));
    $quiz->save();
    return $quiz;
  }

  /**
   * Create question entity.
   *
   * @param array $values
   * @return Question
   */
  protected function drupalCreateQuestion(array $values) {
    $values['type'] = 'default_' . $values['type'];

    // Fix legacy
    if (isset($values['body'])) {
      if (is_string($values['body'])) {
        $values['body'] = array(LANGUAGE_NONE => array(array('value' => $values['body'])));
      }

      $values['quiz_question_body'] = $values['body'];
      unset($values['body']);
    }

    $question = entity_create('quiz_question', $values);
    $question->save();
    return $question;
  }

  public function quizDeleteAllResults() {
    db_query('DELETE FROM {quiz_results}');
  }

}
