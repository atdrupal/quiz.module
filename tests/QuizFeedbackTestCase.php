<?php

use Drupal\quiz\Entity\QuizEntity;

/**
 * Base test class for Quiz questions.
 */
class QuizFeedbackTestCase extends QuizTestCase {

  protected $extraUserPermissions = array('view any quiz question correct response');

  public static function getInfo() {
    return array(
        'name'        => t('Quiz feedback'),
        'description' => t('Unit test for Quiz feedback.'),
        'group'       => t('Quiz'),
    );
  }

  public function setUp($modules = array(), $admin_permissions = array(), $user_permissions = array()) {
    $modules[] = $this->questionPlugin = 'truefalse';
    parent::setUp($modules, $admin_permissions, $user_permissions);
  }

  /**
   * Test question feedback. Note that we are only testing if any feedback
   * displays, each question type has its own tests for testing feedback
   * returned from that question type.
   */
  public function testAnswerFeedback() {
    $quiz = $this->drupalCreateQuiz();

    // 2 questions.
    $question_1 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1));
    $question_2 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1));
    $this->linkQuestionToQuiz($question_1, $quiz);
    $this->linkQuestionToQuiz($question_2, $quiz);

    // This is a dynamic test that only tests the feedback columns showing up.
    variable_set('quiz_auto_revisioning', 0);

    $review_options = array(
        'attempt'         => t('Your answer'),
        'correct'         => t('Correct?'),
        'score'           => t('Score'),
        'answer_feedback' => t('Feedback'),
        'solution'        => t('Correct answer'),
    );

    $this->drupalLogin($this->user);
    $this->checkAfterQuestion($quiz, $question_1, $review_options);
    $this->checkAfterQuiz($quiz, $question_2, $review_options);
  }

  private function checkAfterQuestion(QuizEntity $quiz, $question, $review_options) {
    // Answer the first question.
    $this->drupalGet("quiz/{$quiz->qid}/take");
    $this->drupalPost(NULL, array("question[$question->qid]" => 1), t('Next'));

    // Check feedback after the Question
    foreach ($review_options as $option => $text) {
      $quiz->review_options = array('question' => array($option => $option));
      $quiz->save();

      $this->drupalGet("quiz/{$quiz->qid}/take/1/feedback");
      $this->assertRaw('<th>' . $text . '</th>');
      foreach ($review_options as $option2 => $text2) {
        if ($option != $option2) {
          $this->assertNoRaw('<th>' . $text2 . '</th>');
        }
      }
    }
  }

  private function checkAfterQuiz(QuizEntity $quiz, $question, $review_options) {
    // Feedback only after the quiz.
    $this->drupalGet("quiz/{$quiz->qid}/take/1/feedback");
    $this->drupalPost(NULL, array(), t('Next question'));
    $this->drupalPost(NULL, array("question[$question->qid]" => 1), t('Finish'));

    // Check feedback after the Quiz
    foreach ($review_options as $option => $text) {
      $quiz->review_options['end'] = array($option => $option);
      $quiz->save();

      $this->drupalGet("quiz-result/1");
      $this->assertRaw('<th>' . $text . '</th>');
      foreach ($review_options as $option2 => $text2) {
        if ($option != $option2) {
          $this->assertNoRaw('<th>' . $text2 . '</th>');
        }
      }
    }
  }

  /**
   * Test general Quiz question feedback.
   */
  public function testQuestionFeedback() {
    // Turn on question feedback at the end.
    $quiz = $this->drupalCreateQuiz(array(
        'review_options' => array(
            'end' => array(
                'question_feedback' => 'question_feedback'
            )
        ),
      )
    );

    // Add 2 questions.
    $question_1 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1));
    $question_2 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1));
    $this->linkQuestionToQuiz($question_1, $quiz);
    $this->linkQuestionToQuiz($question_2, $quiz);

    // Set feedback.
    $_question_1 = quiz_question_entity_load($question_1->qid, $question_1->vid);
    $_question_1->feedback = 'Feedback for TF test.';
    $_question_1->feedback_format = filter_default_format();
    $_question_1->save();

    $_question_2 = quiz_question_entity_load($question_2->qid, $question_2->vid);
    $_question_2->feedback = 'Feedback for TF test.';
    $_question_2->feedback_format = filter_default_format();
    $_question_2->save();

    // Test
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/$quiz->qid/take");
    $this->drupalPost(NULL, array("question[$question_1->qid]" => 1), t('Next'));
    $this->assertNoText('Feedback for TF test.');
    $this->drupalPost(NULL, array("question[$question_2->qid]" => 1), t('Finish'));
    $this->assertText('Feedback for TF test.');
  }

}
