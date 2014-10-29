<?php

namespace Drupal\quiz\Helper;

use Drupal\quiz\Helper\Quiz\AccessHelper;
use Drupal\quiz\Helper\Quiz\FeedbackHelper;
use Drupal\quiz\Helper\Quiz\QuestionHelper;
use Drupal\quiz\Helper\Quiz\ResultHelper;
use Drupal\quiz\Helper\Quiz\SettingHelper;
use Drupal\quiz\Helper\Quiz\TakeJumperHelper;

class QuizHelper {

  private $settingHelper;
  private $resultHelper;
  private $accessHelper;
  private $feedbackHelper;
  private $takeJumperHelper;
  private $questionHelper;

  /**
   * @return SettingHelper
   */
  public function getSettingHelper() {
    if (null === $this->settingHelper) {
      $this->settingHelper = new SettingHelper();
    }
    return $this->settingHelper;
  }

  public function setSettingHelper($settingHelper) {
    $this->settingHelper = $settingHelper;
    return $this;
  }

  /**
   * @return ResultHelper
   */
  public function getResultHelper() {
    if (null === $this->resultHelper) {
      $this->resultHelper = new ResultHelper();
    }
    return $this->resultHelper;
  }

  public function setResultHelper($resultHelper) {
    $this->resultHelper = $resultHelper;
    return $this;
  }

  /**
   * @return AccessHelper
   */
  public function getAccessHelper() {
    if (null === $this->accessHelper) {
      $this->accessHelper = new AccessHelper();
    }
    return $this->accessHelper;
  }

  public function setAccessHelper($accessHelper) {
    $this->accessHelper = $accessHelper;
    return $this;
  }

  /**
   * @return FeedbackHelper
   */
  public function getFeedbackHelper() {
    if (null === $this->feedbackHelper) {
      $this->feedbackHelper = new FeedbackHelper();
    }
    return $this->feedbackHelper;
  }

  public function setFeedbackHelper($feedbackHelper) {
    $this->feedbackHelper = $feedbackHelper;
    return $this;
  }

  /**
   * @return TakeJumperHelper
   */
  public function getTakeJumperHelper($quiz, $total, $siblings, $current) {
    if (null == $this->takeJumperHelper) {
      $this->takeJumperHelper = new TakeJumperHelper($quiz, $total, $siblings, $current);
    }
    return $this->takeJumperHelper;
  }

  public function setTakeJumperHelper($takeJumperHelper) {
    $this->takeJumperHelper = $takeJumperHelper;
    return $this;
  }

  /**
   * @return QuestionHelper
   */
  public function getQuestionHelper() {
    if (null === $this->questionHelper) {
      $this->questionHelper = new QuestionHelper();
    }
    return $this->questionHelper;
  }

  public function setQuestionHelper($questionHelper) {
    $this->questionHelper = $questionHelper;
    return $this;
  }

  /**
   * Returns the titles for all quizzes the user has access to.
   *
   * @return quizzes
   *   Array with nids as keys and titles as values.
   */
  public function getAllTitles() {
    return db_select('node', 'n')
        ->fields('n', array('nid', 'title'))
        ->condition('n.type', 'quiz')
        ->addTag('node_access')
        ->execute()
        ->fetchAllKeyed();
  }

  /**
   * Returns the titles for all quizzes the user has access to.
   *
   * @return quizzes
   *   Array with nids as keys and (array with vid as key and title as value) as values.
   *   Like this: array($nid => array($vid => $title))
   */
  public function getAllRevisionTitles() {
    $query = db_select('node', 'n');
    $query->join('node_revision', 'nr', 'nr.nid = n.nid');
    $query->fields('nr', array('nid', 'vid', 'title'))
      ->condition('n.type', 'quiz')
      ->execute();

    $to_return = array();
    while ($res_o = $query->fetch()) {
      $to_return[$res_o->nid][$res_o->vid] = $res_o->title;
    }
    return $to_return;
  }

  public function addQuestion($quiz, $question) {
    $quiz_id = $quiz->qid;
    $quiz_questions = $this->getQuestions($quiz_id, $quiz->vid);

    // Do not add a question if it's already been added (selected in an earlier checkbox)
    foreach ($quiz_questions as $q) {
      if ($question->vid == $q->vid) {
        return FALSE;
      }
    }

    // Otherwise let's add a relationship!
    $question->quiz_nid = $quiz_id;
    $question->quiz_vid = $quiz->vid;
    _quiz_question_get_instance($question)->saveRelationships();
    quiz_update_max_score_properties(array($quiz->vid));
  }

  /**
   * Retrieve list of published questions assigned to quiz.
   *
   * This function should be used for question browsers and similiar... It should not be used to decide what questions
   * a user should answer when taking a quiz. quiz_build_question_list is written for that purpose.
   *
   * @param $quiz_nid
   *   Quiz node id.
   * @param $quiz_vid
   *   Quiz node version id.
   *
   * @return
   *   An array of questions.
   */
  public function getQuestions($quiz_nid, $quiz_vid = NULL) {
    $questions = array();
    $query = db_select('node', 'n');
    $query->fields('n', array('nid', 'type'));
    $query->fields('nr', array('vid', 'title'));
    $query->fields('qnr', array('question_status', 'weight', 'max_score', 'auto_update_max_score', 'qr_id', 'qr_pid'));
    $query->addField('n', 'vid', 'latest_vid');
    $query->join('node_revision', 'nr', 'n.nid = nr.nid');
    $query->leftJoin('quiz_relationship', 'qnr', 'nr.vid = qnr.question_vid');
    $query->condition('n.status', 1);
    $query->condition('qnr.quiz_qid', $quiz_nid);
    if ($quiz_vid) {
      $query->condition('qnr.quiz_vid', $quiz_vid);
    }
    $query->condition('qr_pid', NULL, 'IS');
    $query->orderBy('qnr.weight');

    $result = $query->execute();
    foreach ($result as $question) {
      $questions[] = $question;
      $this->getSubQuestions($question->qr_id, $questions);
    }

    foreach ($questions as &$node) {
      $node = $this->reloadQuestion($node);
    }

    return $questions;
  }

  /**
   * Map node properties to a question object.
   *
   *  This was 'quiz_node_map($node)' before.
   *
   * @param $node
   *  The question node.
   *
   * @return
   *  Question object.
   */
  public function reloadQuestion($node) {
    $question = node_load($node->nid, $node->vid);

    // Append extra fields.
    $question->latest_vid = $node->latest_vid;
    $question->question_status = isset($node->question_status) ? $node->question_status : QUESTION_NEVER;
    if (isset($node->max_score)) {
      $question->max_score = $node->max_score;
    }
    if (isset($node->auto_update_max_score)) {
      $question->auto_update_max_score = $node->auto_update_max_score;
    }
    $question->weight = $node->weight;
    $question->qr_id = $node->qr_id;
    $question->qr_pid = $node->qr_pid;

    return $question;
  }

  /**
   * Get an array list of random questions for a quiz.
   *
   * @param $quiz
   *   The quiz node.
   *
   * @return
   *   Array of nid/vid combos for quiz questions.
   */
  public function getRandomQuestions($quiz) {
    $num_random = $quiz->number_of_random_questions;
    $tid = $quiz->tid;
    $questions = array();
    if ($num_random > 0) {
      if ($tid > 0) {
        $questions = $this->getRandomTaxonomyQuestionIds($tid, $num_random);
      }
      else {
        // Select random question from assigned pool.
        $result = db_query_range(
          "SELECT question_nid as nid, question_vid as vid, n.type
        FROM {quiz_relationship} qnr
        JOIN {node} n on qnr.question_nid = n.nid
        WHERE qnr.quiz_vid = :quiz_vid
        AND qnr.quiz_qid = :quiz_qid
        AND qnr.question_status = :question_status
        AND n.status = 1
        ORDER BY RAND()", 0, $quiz->number_of_random_questions, array(
            ':quiz_vid'        => $quiz->vid,
            ':quiz_qid'        => $quiz->qid,
            ':question_status' => QUESTION_RANDOM
          )
        );
        while ($question_node = $result->fetchAssoc()) {
          $question_node['random'] = TRUE;
          $question_node['relative_max_score'] = $quiz->max_score_for_random;
          $questions[] = $question_node;
        }
      }
    }
    return $questions;
  }

  /**
   * Given a term ID, get all of the question nid/vids that have that ID.
   *
   * @param $tid
   *   Integer term ID.
   *
   * @return
   *   Array of nid/vid combos, like array(array('nid'=>1, 'vid'=>2)).
   */
  public function getRandomTaxonomyQuestionIds($tid, $num_random) {
    if ($tid == 0) {
      return array();
    }

    // Select random questions by taxonomy.
    $term = taxonomy_term_load($tid);
    $tree = taxonomy_get_tree($term->vid, $term->tid);

    // Flatten the taxonomy tree, and just keep term id's.
    $term_ids[] = $term->tid;
    if (is_array($tree)) {
      foreach ($tree as $term) {
        $term_ids[] = $term->tid;
      }
    }
    $term_ids = implode(',', $term_ids);

    // Get all published questions with one of the allowed term ids.
    // TODO Please convert this statement to the D7 database API syntax.
    $result = db_query_range("SELECT n.nid, n.vid
    FROM {node} n
    INNER JOIN {taxonomy_index} tn USING (nid)
    WHERE n.status = 1 AND tn.tid IN ($term_ids)
    AND n.type IN ('" . implode("','", array_keys(_quiz_get_question_types()))
      . "') ORDER BY RAND()");

    $questions = array();
    while ($question_node = db_fetch_array($result)) {
      $question_node['random'] = TRUE;
      $questions[] = $question_node;
    }

    return $questions;
  }

  /**
   * Sets the questions that are assigned to a quiz.
   *
   * @param $quiz
   *   The quiz(node) to modify.
   * @param $questions
   *   An array of questions.
   * @param $set_new_revision
   *   If TRUE, a new revision will be generated. Note that saving
   *   quiz questions unmodified will still generate a new revision of the quiz if
   *   this is set to TRUE. Why? For a few reasons:
   *   - All of the questions are updated to their latest VID. That is supposed to
   *     be a feature.
   *   - All weights are updated.
   *   - All status flags are updated.
   *
   * @return
   *   Boolean TRUE if update was successful, FALSE otherwise.
   */
  public function setQuestions(&$quiz, $questions, $set_new_revision = FALSE) {
    if ($set_new_revision) {
      // Create a new Quiz VID, even if nothing changed.
      $quiz->revision = 1;

      node_save($quiz);
    }

    // When node_save() calls all of the node API hooks, old quiz info is
    // automatically inserted into quiz_relationship. We could get clever and
    // try to do strategic updates/inserts/deletes, but that method has already
    // proven error prone as the module has gained complexity (See 5.x-2.0-RC2).
    // So we go with the brute force method:
    db_delete('quiz_relationship')
      ->condition('quiz_qid', $quiz->qid)
      ->condition('quiz_vid', $quiz->vid)
      ->execute();

    if (empty($questions)) {
      return TRUE; // This is not an error condition.
    }

    foreach ($questions as $question) {
      if ($question->state != QUESTION_NEVER) {
        $question_inserts[$question->qr_id] = array(
            'quiz_qid'              => $quiz->qid,
            'quiz_vid'              => $quiz->vid,
            'question_nid'          => $question->nid,
            // Update to latest OR use the version given.
            'question_vid'          => $question->refresh ? db_query('SELECT vid FROM {node} WHERE nid = :nid', array(
                  ':nid' => $question->nid))->fetchField() : $question->vid,
            'question_status'       => $question->state,
            'weight'                => $question->weight,
            'max_score'             => (int) $question->max_score,
            'auto_update_max_score' => (int) $question->auto_update_max_score,
            'qr_pid'                => $question->qr_pid,
            'qr_id'                 => !$set_new_revision ? $question->qr_id : NULL,
            'old_qr_id'             => $question->qr_id,
        );
        drupal_write_record('quiz_relationship', $question_inserts[$question->qr_id]);
      }
    }

    // Update the parentage when a new revision is created.
    // @todo this is copy pasta from quiz_update_quiz_question_relationship
    foreach ($question_inserts as $question_insert) {
      db_update('quiz_relationship')
        ->condition('qr_pid', $question_insert['old_qr_id'])
        ->condition('quiz_vid', $quiz->vid)
        ->condition('quiz_qid', $quiz->qid)
        ->fields(array('qr_pid' => $question_insert['qr_id']))
        ->execute();
    }

    quiz_update_max_score_properties(array($quiz->vid));
    return TRUE;
  }

  /**
   * Retrieves a list of questions (to be taken) for a given quiz.
   *
   * If the quiz has random questions this function only returns a random
   * selection of those questions. This function should be used to decide
   * what questions a quiz taker should answer.
   *
   * This question list is stored in the user's result, and may be different
   * when called multiple times. It should only be used to generate the layout
   * for a quiz attempt and NOT used to do operations on the questions inside of
   * a quiz.
   *
   * @param $quiz
   *   Quiz node.
   * @return
   *   Array of question node IDs.
   */
  public function getQuestionList($quiz) {
    $questions = array();

    if ($quiz->randomization == 3) {
      $questions = $this->buildCategoziedQuestionList($quiz);
    }
    else {
      // Get required questions first.
      $query = db_query('SELECT n.nid, n.vid, n.type, qnr.qr_id, qnr.qr_pid
    FROM {quiz_relationship} qnr
    JOIN {node} n ON qnr.question_nid = n.nid
    LEFT JOIN {quiz_relationship} qnr2 ON (qnr.qr_pid = qnr2.qr_id OR (qnr.qr_pid IS NULL AND qnr.qr_id = qnr2.qr_id))
    WHERE qnr.quiz_vid = :quiz_vid
    AND qnr.question_status = :question_status
    AND n.status = 1
    ORDER BY qnr2.weight, qnr.weight', array(':quiz_vid' => $quiz->vid, ':question_status' => QUESTION_ALWAYS));
      $i = 0;
      while ($question_node = $query->fetchAssoc()) {
        // Just to make it easier on us, let's use a 1-based index.
        $i++;
        $questions[$i] = $question_node;
      }

      // Get random questions for the remainder.
      if ($quiz->number_of_random_questions > 0) {
        $random_questions = $this->getRandomQuestions($quiz);
        $questions = array_merge($questions, $random_questions);
        if ($quiz->number_of_random_questions > count($random_questions)) {
          // Unable to find enough requested random questions.
          return FALSE;
        }
      }

      // Shuffle questions if required.
      if ($quiz->randomization > 0) {
        shuffle($questions);
      }
    }

    $count = 0;
    $display_count = 0;
    $questions_out = array();
    foreach ($questions as &$question) {
      $question_node = node_load($question['nid'], $question['vid']);
      $count++;
      $display_count++;
      $question['number'] = $count;
      if ($question['type'] != 'quiz_page') {
        $question['display_number'] = $display_count;
      }
      $questions_out[$count] = $question;
    }
    return $questions_out;
  }

  /**
   * Get data for all terms belonging to a Quiz with categorized random questions
   *
   * @param int $vid
   *  version id for the quiz
   * @return array
   *  Array with all terms that belongs to the quiz as objects
   */
  public function getQuizTermsByVocabularyId($vid) {
    $sql = 'SELECT td.name, qt.*
    FROM {quiz_terms} qt
    JOIN {taxonomy_term_data} td ON qt.tid = td.tid
    WHERE qt.vid = :vid
    ORDER BY qt.weight';
    return db_query($sql, array(':vid' => $vid))->fetchAll();
  }

  /**
   * Builds the questionlist for quizzes with categorized random questions
   */
  public function buildCategoziedQuestionList($quiz) {
    $terms = $this->getQuizTermsByVocabularyId($quiz->vid);
    $questions = array();
    $nids = array();
    $question_types = array_keys(_quiz_get_question_types());
    if (empty($question_types)) {
      return array();
    }
    $total_count = 0;
    foreach ($terms as $term) {
      $query = db_select('node', 'n');
      $query->join('taxonomy_index', 'tn', 'n.nid = tn.nid');
      $query->fields('n', array('nid', 'vid'));
      $query->fields('tn', array('tid'));
      $query->condition('n.status', 1, '=');
      $query->condition('n.type', $question_types, 'IN');
      $query->condition('tn.tid', $term->tid, '=');
      if (!empty($nids)) {
        $query->condition('n.nid', $nids, 'NOT IN');
      }
      $query->range(0, $term->number);
      $query->orderBy('RAND()');

      $result = $query->execute();
      $count = 0;
      while ($question = $result->fetchAssoc()) {
        $count++;
        $question['tid'] = $term->tid;
        $question['number'] = $count + $total_count;
        $questions[] = $question;
        $nids[] = $question['nid'];
      }
      $total_count += $count;
      if ($count < $term->number) {
        return array(); // Not enough questions
      }
    }
    return $questions;
  }

  public function getSubQuestions($qr_pid, &$questions) {
    $query = db_select('node', 'n');
    $query->fields('n', array('nid', 'type'));
    $query->fields('nr', array('vid', 'title'));
    $query->fields('qnr', array('question_status', 'weight', 'max_score', 'auto_update_max_score', 'qr_id', 'qr_pid'));
    $query->addField('n', 'vid', 'latest_vid');
    $query->innerJoin('node_revision', 'nr', 'n.nid = nr.nid');
    $query->innerJoin('quiz_relationship', 'qnr', 'nr.vid = qnr.question_vid');
    $query->condition('qr_pid', $qr_pid);
    $query->orderBy('weight');
    $result = $query->execute();
    foreach ($result as $question) {
      $questions[] = $question;
    }
  }

  /**
   * Get a list of all available quizzes.
   *
   * @param $uid
   *   An optional user ID. If supplied, only quizzes created by that user will be
   *   returned.
   *
   * @return
   *   A list of quizzes.
   */
  public function getQuizzesByUserId($uid) {
    $results = array();
    $args = array();
    $query = db_select('node', 'n')
      ->fields('n', array('nid', 'vid', 'title', 'uid', 'created'))
      ->fields('u', array('name'));
    $query->leftJoin('users', 'u', 'u.uid = n.uid');
    $query->condition('n.type', 'quiz');
    if ($uid != 0) {
      $query->condition('n.uid', $uid);
    }
    $query->orderBy('n.nid');
    $quizzes = $query->execute();
    foreach ($quizzes as $quiz) {
      $results[$quiz->qid] = (array) $quiz;
    }
    return $results;
  }

  /**
   * Copies questions when a quiz is translated.
   *
   * @param $node
   *   The new translated quiz node.
   */
  public function copyQuestions($node) {
    // Find original questions.
    $query = db_query('SELECT question_nid, question_vid, question_status, weight, max_score, auto_update_max_score
    FROM {quiz_relationship}
    WHERE quiz_vid = :quiz_vid', array(':quiz_vid' => $node->translation_source->vid));
    foreach ($query as $res_o) {
      $original_question = node_load($res_o->question_nid);

      // Set variables we can't or won't carry with us to the translated node to
      // NULL.
      $original_question->nid = $original_question->vid = $original_question->created = $original_question->changed = NULL;
      $original_question->revision_timestamp = $original_question->menu = $original_question->path = NULL;
      $original_question->files = array();
      if (isset($original_question->book['mlid'])) {
        $original_question->book['mlid'] = NULL;
      }

      // Set the correct language.
      $original_question->language = $node->language;

      // Save the node.
      node_save($original_question);

      // Save the relationship between the new question and the quiz.
      db_insert('quiz_relationship')
        ->fields(array(
            'quiz_qid'              => $node->nid,
            'quiz_vid'              => $node->vid,
            'question_nid'          => $original_question->nid,
            'question_vid'          => $original_question->vid,
            'question_status'       => $res_o->question_status,
            'weight'                => $res_o->weight,
            'max_score'             => $res_o->max_score,
            'auto_update_max_score' => $res_o->auto_update_max_score,
        ))
        ->execute();
    }
  }

  /**
   * Finds out the number of questions for the quiz.
   *
   * Good example of usage could be to calculate the % of score.
   *
   * @param int $quiz_vid
   *   Quiz version ID.
   * @return
   *   Returns the number of quiz questions.
   */
  public function countQuestion($quiz_vid) {
    return $this->countAlwaysQuestions($quiz_vid) + (int) db_query(
        'SELECT number_of_random_questions'
        . ' FROM {quiz_node_properties}'
        . ' WHERE vid = :vid', array(':vid' => $quiz_vid)
      )->fetchField();
  }

  /**
   * Get the number of compulsory questions for a quiz.
   *
   * @param int $quiz_vid
   * @return int
   *   Number of compulsory questions.
   */
  public function countAlwaysQuestions($quiz_vid) {
    return db_query('SELECT COUNT(*)
      FROM {quiz_relationship} qnr
        JOIN {node} n ON n.nid = qnr.question_nid
      WHERE n.status=1
        AND qnr.quiz_vid = :quiz_vid
        AND qnr.question_status = :question_status', array(
          ':quiz_vid'        => $quiz_vid,
          ':question_status' => QUESTION_ALWAYS
      ))->fetchField();
  }

  /**
   * Return highest score data for given quizzes.
   *
   * @param $nids
   *   nids for the quizzes we want to collect scores from.
   * @param $uid
   *   uid for the user we want to collect score for.
   * @param $include_num_questions
   *   Do we want to collect information about the number of questions in a quiz?
   *   This adds a performance hit.
   * @return
   *   Array of score data.
   *   For several takes on the same quiz, only returns highest score.
   */
  public function getScoreData($nids, $uid, $include_num_questions = FALSE) {
    // Validate that the nids are integers.
    foreach ($nids as $key => $nid) {
      if (!_quiz_is_int($nid)) {
        unset($nids[$key]);
      }
    }
    if (empty($nids)) {
      return array();
    }

    // Fetch score data for the validated nids.
    $to_return = array();
    $vids = array();
    $sql = 'SELECT n.title, n.nid, n.vid, p.number_of_random_questions as num_random_questions, r.score AS percent_score, p.max_score, p.pass_rate AS percent_pass
          FROM {node} n
          JOIN {quiz_node_properties} p
          ON n.vid = p.vid
          LEFT OUTER JOIN {quiz_results} r
          ON r.nid = n.nid AND r.uid = :uid
          LEFT OUTER JOIN (
            SELECT nid, max(score) as highest_score
            FROM {quiz_results}
            GROUP BY nid
          ) rm
          ON n.nid = rm.nid AND r.score = rm.highest_score
          WHERE n.nid in (' . implode(', ', $nids) . ')
          ';
    $res = db_query($sql, array(':uid' => $uid));
    foreach ($res as $res_o) {
      if (!$include_num_questions) {
        unset($res_o->num_random_questions);
      }
      if (!isset($to_return[$res_o->vid]) || $res_o->percent_score > $to_return[$res_o->vid]->percent_score) {
        $to_return[$res_o->vid] = $res_o; // Fetch highest score
      }
      // $vids will be used to fetch number of questions.
      $vids[] = $res_o->vid;
    }
    if (empty($vids)) {
      return array();
    }

    // Fetch total number of questions.
    if ($include_num_questions) {
      $res = db_query('SELECT COUNT(*) AS num_always_questions, quiz_vid
            FROM {quiz_relationship}
            WHERE quiz_vid IN (' . implode(', ', $vids) . ')
            AND question_status = ' . QUESTION_ALWAYS . '
            GROUP BY quiz_vid');
      foreach ($res as $res_o) {
        $to_return[$res_o->quiz_vid]->num_questions = $to_return[$res_o->quiz_vid]->num_random_questions + $res_o->num_always_questions;
      }
    }

    return $to_return;
  }

  /**
   * Store a quiz question result.
   *
   * @param $quiz
   *  The quiz node
   * @param $result
   *  Object with data about the result for a question.
   * @param $options
   *  Array with options that affect the behavior of this function.
   *  ['set_msg'] - Sets a message if the last question was skipped.
   */
  public function saveQuestionResult($quiz, $result, $options) {
    if (isset($result->is_skipped) && $result->is_skipped == TRUE) {
      if ($options['set_msg']) {
        drupal_set_message(t('Last question skipped.'), 'status');
      }
      $result->is_correct = FALSE;
      $result->score = 0;
    }
    else {
      // Make sure this is set.
      $result->is_skipped = FALSE;
    }
    if (!isset($result->score)) {
      $result->score = $result->is_correct ? 1 : 0;
    }

    // Points are stored pre-scaled in the quiz_results_answers table. We get the scale.
    if ($quiz->randomization < 2) {
      $scale = db_query("
        SELECT (max_score / (
              SELECT max_score FROM {quiz_question_properties} WHERE nid = :nid AND vid = :vid
            )) as scale
            FROM {quiz_relationship}
            WHERE quiz_qid = :quiz_qid
            AND quiz_vid = :quiz_vid
            AND question_nid = :question_nid
            AND question_vid = :question_vid", array(
          ':nid'          => $result->quiz_qid,
          ':vid'          => $result->quiz_vid,
          ':quiz_qid'     => $quiz->qid,
          ':quiz_vid'     => $quiz->vid,
          ':question_nid' => $result->quiz_qid,
          ':question_vid' => $result->quiz_vid
        ))->fetchField();
    }
    elseif ($quiz->randomization == 2) {
      $scale = db_query("
          SELECT
            (max_score_for_random /
              (SELECT max_score FROM {quiz_question_properties} WHERE nid = :question_nid AND vid = :question_vid)
            ) as scale
          FROM {quiz_node_properties}
          WHERE vid = :quiz_vid", array(
          ':question_nid' => $result->quiz_qid,
          ':question_vid' => $result->quiz_vid,
          ':quiz_vid'     => $quiz->vid
        ))->fetchField();
    }
    elseif ($quiz->randomization == 3) {
      if (isset($options['question_data']['tid'])) {
        $result->tid = $options['question_data']['tid'];
      }
      $scale = db_query("
          SELECT
            (max_score /
              (SELECT max_score FROM {quiz_question_properties} WHERE nid = :nid AND vid = :vid)
            ) as scale
          FROM {quiz_terms} WHERE vid = :vid AND tid = :tid", array(
          ':nid' => $result->quiz_qid,
          ':vid' => $result->quiz_vid,
          ':vid' => $quiz->vid,
          ':tid' => $result->tid
        ))->fetchField();
    }
    $points = round($result->score * $scale);

    // Insert result data, or update existing data.
    $result_answer_id = db_query("SELECT result_answer_id
        FROM {quiz_results_answers}
        WHERE question_nid = :question_nid
          AND question_vid = :question_vid
          AND result_id = :result_id", array(
        ':question_nid' => $result->quiz_qid,
        ':question_vid' => $result->quiz_vid,
        ':result_id'    => $result->result_id
      ))->fetchField();

    $answer = (object) array(
          'result_answer_id' => $result_answer_id,
          'question_nid'     => $result->quiz_qid,
          'question_vid'     => $result->quiz_vid,
          'result_id'        => $result->result_id,
          'is_correct'       => (int) $result->is_correct,
          'points_awarded'   => $points,
          'answer_timestamp' => REQUEST_TIME,
          'is_skipped'       => (int) $result->is_skipped,
          'is_doubtful'      => (int) $result->is_doubtful,
          'number'           => $options['question_data']['number'],
          'tid'              => ($quiz->randomization == 3 && $result->tid) ? $result->tid : 0,
    );

    entity_save('quiz_result_answer', $answer);
  }

  /**
   * Updates the max_score property on the specified quizzes
   *
   * @param $vids
   *  Array with the vid's of the quizzes to update
   */
  public function updateMaxScoreProperties($vids) {
    if (empty($vids)) {
      return;
    }

    db_update('quiz_node_properties')
      ->expression('max_score', 'max_score_for_random * number_of_random_questions + (
      SELECT COALESCE(SUM(max_score), 0)
      FROM {quiz_relationship} qnr
      WHERE qnr.question_status = ' . QUESTION_ALWAYS . '
      AND quiz_vid = {quiz_node_properties}.vid)')
      ->condition('vid', $vids, 'IN')
      ->execute();

    db_update('quiz_node_properties')
      ->expression('max_score', '(SELECT COALESCE(SUM(qt.max_score * qt.number), 0)
      FROM {quiz_terms} qt
      WHERE qt.nid = {quiz_node_properties}.nid AND qt.vid = {quiz_node_properties}.vid)')
      ->condition('randomization', 3)
      ->condition('vid', $vids, 'IN')
      ->execute();

    db_update('node_revision')
      ->fields(array('timestamp' => REQUEST_TIME))
      ->condition('vid', $vids, 'IN')
      ->execute();

    db_update('node')
      ->fields(array('changed' => REQUEST_TIME))
      ->condition('vid', $vids, 'IN')
      ->execute();

    $results_to_update = db_query('SELECT vid FROM {quiz_node_properties} WHERE vid IN (:vid) AND max_score <> :max_score', array(':vid' => $vids, ':max_score' => 0))->fetchCol();
    if (!empty($results_to_update)) {
      db_update('quiz_results')
        ->expression('score', 'ROUND(
        100 * (
          SELECT COALESCE (SUM(a.points_awarded), 0)
          FROM {quiz_results_answers} a
          WHERE a.result_id = {quiz_results}.result_id
        ) / (
          SELECT max_score
          FROM {quiz_node_properties} qnp
          WHERE qnp.vid = {quiz_results}.vid
        )
      )')
        ->condition('vid', $results_to_update, 'IN')
        ->execute();
    }
  }

  /**
   * Find out if a quiz is available for taking or not
   *
   * @param $quiz
   *  The quiz node
   * @return
   *  TRUE if available
   *  Error message(String) if not available
   */
  public function isAvailable($quiz) {
    global $user;

    if ($user->uid == 0 && $quiz->takes > 0) {
      return t('This quiz only allows %num_attempts attempts. Anonymous users can only access quizzes that allows an unlimited number of attempts.', array('%num_attempts' => $quiz->takes));
    }

    $user_is_admin = user_access('edit any quiz content') || (user_access('edit own quiz content') && $quiz->uid == $user->uid);
    if ($user_is_admin || $quiz->quiz_always == 1) {
      return TRUE;
    }

    // Compare current GMT time to the open and close dates (which should still be
    // in GMT time).
    $now = REQUEST_TIME;

    if ($now >= $quiz->quiz_close || $now < $quiz->quiz_open) {
      return t('This quiz is closed.');
    }
    return TRUE;
  }

  /**
   * Check a user/quiz combo to see if the user passed the given quiz.
   *
   * This will return TRUE if the user has passed the quiz at least once, and
   * FALSE otherwise. Note that a FALSE may simply indicate that the user has not
   * taken the quiz.
   *
   * @param $uid
   *   The user ID.
   * @param $nid
   *   The node ID.
   * @param $vid
   *   The version ID.
   */
  public function isPassed($uid, $nid, $vid) {
    $passed = db_query(
      'SELECT COUNT(result_id) AS passed_count
          FROM {quiz_results} result
            INNER JOIN {quiz_entity_revision} revision
              ON result.quiz_vid = revision.vid AND result.quiz_qid = revision.vid
          WHERE result.quiz_vid = :vid
            AND result.quiz_qid = :qid
            AND result.uid = :uid
            AND score >= pass_rate', array(
        ':vid' => $vid,
        ':qid' => $nid,
        ':uid' => $uid
      ))->fetchField();

    // Force into boolean context.
    return ($passed !== FALSE && $passed > 0);
  }

  /**
   * Finds out if a quiz has been answered or not.
   *
   * @return
   *   TRUE if there exists answers to the current question.
   */
  public function isAnswered($node) {
    if (!isset($node->nid)) {
      return FALSE;
    }
    $query = db_select('quiz_results', 'qnr');
    $query->addField('qnr', 'result_id');
    $query->condition('nid', $node->nid);
    $query->condition('vid', $node->vid);
    $query->range(0, 1);
    return $query->execute()->rowCount() > 0;
  }

  /**
   * @param string $question_type
   *
   * @return string
   *   Name of module matching the question type, as given by quiz_question_info()
   *   hook.
   */
  public function getQuestionModuleFromType($question_type) {
    $types = _quiz_get_question_types();
    if (!isset($types[$question_type])) {
      drupal_set_message(t('The module for the questiontype %type is not enabled', array('%type' => $question_type)), 'warning');
      return FALSE;
    }
    return $types[$question_type]['module'];
  }

}
