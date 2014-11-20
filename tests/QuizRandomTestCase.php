<?php

/**
 * Tests for random questions.
 *
 * Since this is random by nature, there is a chance that these will fail. We
 * use 5 layout builds to try and mitigate that chance.
 */
class QuizRandomTestCase extends QuizTestCase {

  public static function getInfo() {
    return array(
        'name'        => t('Quiz random'),
        'description' => t('Unit test for random quiz question behavior'),
        'group'       => t('Quiz'),
    );
  }

  public function setUp($modules = array(), $admin_permissions = array(), $user_permissions = array()) {
    $modules[] = $this->questionPlugin = 'truefalse';
    parent::setUp($modules);
  }

  /**
   * Test random order of questions.
   */
  public function testRandomOrder() {
    $quiz = $this->drupalCreateQuiz(array('randomization' => 1));
    $question_1 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'body' => 'TF 1 body text'));
    $question_2 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'body' => 'TF 2 body text'));
    $question_3 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'body' => 'TF 3 body text'));
    $question_4 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'body' => 'TF 4 body text'));
    $question_5 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'body' => 'TF 5 body text'));
    $this->linkQuestionToQuiz($question_1, $quiz);
    $this->linkQuestionToQuiz($question_2, $quiz);
    $this->linkQuestionToQuiz($question_3, $quiz);
    $this->linkQuestionToQuiz($question_4, $quiz);
    $this->linkQuestionToQuiz($question_5, $quiz);

    for ($i = 1; $i <= 5; $i++) {
      $out[$i] = '';
      foreach ($quiz->getQuestionIO()->getQuestionList() as $question) {
        $out[$i] .= $question['qid'];
      }
    }

    // Check that at least one of the orders is different.
    $this->assertNotEqual(count(array_unique($out)), 1, t('At least one set of questions was different.'));
  }

  /**
   * Test random plus required questions from a pool.
   */
  public function testRandomQuestions() {
    $quiz = $this->drupalCreateQuiz(array('randomization' => 2, 'number_of_random_questions' => 2));
    $question_1 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'body' => 'TF 1 body text'));
    $question_2 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'body' => 'TF 2 body text'));
    $question_3 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'body' => 'TF 3 body text'));
    $question_4 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'body' => 'TF 4 body text'));
    $question_5 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'body' => 'TF 5 body text'));
    $this->linkQuestionToQuiz($question_1, $quiz);
    $this->linkQuestionToQuiz($question_2, $quiz);
    $this->linkQuestionToQuiz($question_3, $quiz);
    $this->linkQuestionToQuiz($question_4, $quiz);
    $this->linkQuestionToQuiz($question_5, $quiz);

    // Set up one required question.
    $result = entity_load('quiz_question_relationship', FALSE, array('question_vid' => $question_1->qid));
    $relationship = reset($result);
    $relationship->question_status = 1;
    entity_save('quiz_question_relationship', $relationship);

    for ($i = 1; $i <= 5; $i++) {
      $questions = $quiz->getQuestionIO()->getQuestionList();
      $this->assertEqual(count($questions), 3, t('Quiz has 2 questions.'));
      $out[$i] = '';
      foreach ($questions as $question) {
        $out[$i] .= $question['qid'];
      }
      $this->assert(strpos($out[$i], $question_1->qid) !== FALSE, t('Quiz always contains required question 1'));
    }

    // Also check that at least one of the orders is different.
    $this->assertNotEqual(count(array_unique($out)), 1, t('At least one set of questions were different.'));
  }

}
