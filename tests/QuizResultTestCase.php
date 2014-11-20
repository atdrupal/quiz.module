<?php

class QuizResultTestCase extends QuizTestCase {

  public static function getInfo() {
    return array(
        'name'        => t('Quiz results'),
        'description' => t('Unit test for Quiz results.'),
        'group'       => t('Quiz'),
    );
  }

  public function setUp($modules = array(), $admin_permissions = array(), $user_permissions = array()) {
    $modules[] = $this->questionPlugin = 'truefalse';
    parent::setUp($modules, $admin_permissions, $user_permissions);
  }

  /**
   * Test the various result summaries and pass rate.
   */
  public function testPassRateSummary() {
    // By default, the feedback is after the quiz.
    $quiz = $this->drupalCreateQuiz(array(
        'pass_rate'              => 75,
        'summary_pass'           => 'This is the summary if passed',
        'summary_pass_format'    => 'plain_text',
        'summary_default'        => 'This is the default summary text',
        'summary_default_format' => 'plain_text',
        'resultoptions'          => array(
            array(
                'option_name'           => '90 and higher',
                'option_summary'        => 'You got 90 or more on the quiz',
                'option_summary_format' => 'filtered_html',
                'option_start'          => 90,
                'option_end'            => 100,
            ),
            array(
                'option_name'           => '50 and higher',
                'option_summary'        => 'You got between 50 and 89',
                'option_summary_format' => 'filtered_html',
                'option_start'          => 50,
                'option_end'            => 89,
            ),
            array(
                'option_name'           => 'Below 50',
                'option_summary'        => 'You failed bro',
                'option_summary_format' => 'filtered_html',
                'option_start'          => 0,
                'option_end'            => 49,
            ),
        ),
    ));

    // 3 questions.
    $question_1 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'feedback' => 'Q1Feedback'));
    $question_2 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'feedback' => 'Q2Feedback'));
    $question_3 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1, 'feedback' => 'Q3Feedback'));
    $this->linkQuestionToQuiz($question_1, $quiz);
    $this->linkQuestionToQuiz($question_2, $quiz);
    $this->linkQuestionToQuiz($question_3, $quiz);

    // Test 100%
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/$quiz->qid/take");
    $this->drupalPost(NULL, array("question[$question_1->qid]" => 1), t('Next'));
    $this->drupalPost(NULL, array("question[$question_2->qid]" => 1), t('Next'));
    $this->drupalPost(NULL, array("question[$question_3->qid]" => 1), t('Finish'));
    $this->assertText('You got 90 or more on the quiz');
    $this->assertText('This is the summary if passed');
    $this->assertNoText('This is the default summary text');

    // Test 66%
    $this->drupalGet("quiz/$quiz->qid/take");
    $this->drupalPost(NULL, array("question[$question_1->qid]" => 1), t('Next'));
    $this->drupalPost(NULL, array("question[$question_2->qid]" => 1), t('Next'));
    $this->drupalPost(NULL, array("question[$question_3->qid]" => 0), t('Finish'));
    $this->assertText('You got between 50 and 89');
    $this->assertNoText('This is the summary if passed');
    $this->assertText('This is the default summary text');

    // Test 33%
    $this->drupalGet("quiz/$quiz->qid/take");
    $this->drupalPost(NULL, array("question[$question_1->qid]" => 1), t('Next'));
    $this->drupalPost(NULL, array("question[$question_2->qid]" => 0), t('Next'));
    $this->drupalPost(NULL, array("question[$question_3->qid]" => 0), t('Finish'));
    $this->assertText('You failed bro');
    $this->assertNoText('This is the summary if passed');
    $this->assertText('This is the default summary text');
  }

  /**
   * Test access to results.
   */
  function testQuizResultAccess() {
    $question = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1));
    $quiz = $this->linkQuestionToQuiz($question);

    // Submit an answer.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/$quiz->qid/take");
    $this->drupalPost(NULL, array("question[$question->qid]" => 1), t('Finish'));

    $resultsUrl = $this->getUrl();

    $this->drupalGet($resultsUrl);
    $this->assertResponse(200, t('User can view own result'));
    $this->drupalLogout();
    $this->drupalGet($resultsUrl);
    $this->assertNoResponse(200, t('Anonymous user cannot view result'));
  }

  /**
   * Test the all, best, and last quiz result pruning.
   */
  public function testResultPruning() {
    $question_1 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1));
    $question_2 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1));
    $question_3 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1));
    $question_4 = $this->drupalCreateQuestion(array('type' => 'truefalse', 'correct_answer' => 1));

    $quiz = $this->drupalCreateQuiz();
    $this->linkQuestionToQuiz($question_1, $quiz);
    $this->linkQuestionToQuiz($question_2, $quiz);
    $this->linkQuestionToQuiz($question_3, $quiz);
    $this->linkQuestionToQuiz($question_4, $quiz);

    $this->drupalLogin($this->user);

    // ------------------------------------------
    // Test QUIZ_KEEP_ALL option
    // ------------------------------------------
    $quiz->keep_results = QUIZ_KEEP_ALL;
    entity_save('quiz_entity', $quiz);

    // Create 2 100% results.
    for ($i = 1; $i <= 2; $i++) {
      $this->drupalGet("quiz/$quiz->qid/take");
      $this->drupalPost(NULL, array("question[$question_1->qid]" => 1), t('Next'));
      $this->drupalPost(NULL, array("question[$question_2->qid]" => 1), t('Next'));
      $this->drupalPost(NULL, array("question[$question_3->qid]" => 1), t('Next'));
      $this->drupalPost(NULL, array("question[$question_4->qid]" => 1), t('Finish'));
    }

    // Storing all results.
    $results_1st = entity_load('quiz_result');
    $this->assertEqual(count($results_1st), 2, 'Found 2 quiz results.');

    // ------------------------------------------
    // Test QUIZ_KEEP_LATEST option
    // ------------------------------------------
    $quiz->keep_results = QUIZ_KEEP_LATEST;
    entity_save('quiz_entity', $quiz);

    // Create a 50% result.
    $this->drupalGet("quiz/$quiz->qid/take");
    $this->drupalPost(NULL, array("question[$question_1->qid]" => 1), t('Next'));
    $this->drupalPost(NULL, array("question[$question_2->qid]" => 1), t('Next'));
    $this->drupalPost(NULL, array("question[$question_3->qid]" => 0), t('Next'));
    $this->drupalPost(NULL, array("question[$question_4->qid]" => 0), t('Finish'));

    // We should only have one 50% result.
    $results_2nd = entity_load('quiz_result', FALSE, array(), TRUE);
    $this->assertEqual(count($results_2nd), 1, 'Found only one quiz result');
    $result_2nd = reset($results_2nd);
    $this->assertEqual($result_2nd->score, 50, 'Quiz result was 50%');

    // ------------------------------------------
    // Test QUIZ_KEEP_BEST option
    // ------------------------------------------
    $quiz->keep_results = QUIZ_KEEP_BEST;
    entity_save('quiz_entity', $quiz);

    $this->drupalGet("quiz/$quiz->qid/take");
    $this->drupalPost(NULL, array("question[$question_1->qid]" => 1), t('Next'));
    $this->drupalPost(NULL, array("question[$question_2->qid]" => 0), t('Next'));
    $this->drupalPost(NULL, array("question[$question_3->qid]" => 0), t('Next'));
    $this->drupalPost(NULL, array("question[$question_4->qid]" => 0), t('Finish'));

    // We should still only have one 50% result, since we failed.
    $results_3rd = entity_load('quiz_result', FALSE, array(), TRUE);
    $this->assertTrue(count($results_3rd) == 1, 'Found only one quiz result');
    $result_3rd = reset($results_3rd);
    $this->assertEqual($result_3rd->score, 50, 'Quiz score was 50%');

    $this->drupalGet("quiz/$quiz->qid/take");
    $this->drupalPost(NULL, array("question[$question_1->qid]" => 1), t('Next'));
    $this->drupalPost(NULL, array("question[$question_2->qid]" => 1), t('Next'));
    $this->drupalPost(NULL, array("question[$question_3->qid]" => 1), t('Next'));
    $this->drupalPost(NULL, array("question[$question_4->qid]" => 0), t('Finish'));

    // We should still only have one 75% result.
    $results_4th = entity_load('quiz_result', FALSE, array(), TRUE);
    $this->assertEqual(count($results_4th), 1, 'Found only one quiz result');
    $result_4th = reset($results_4th);
    $this->assertEqual($result_4th->score, 75, 'Quiz score was 75%');
  }

}
