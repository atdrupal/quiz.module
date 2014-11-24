<?php

namespace Drupal\quiz_question\Form;

/**
 * @TODO This is unreachable code. Read more at https://www.drupal.org/node/2374407
 */
class RevisionActionsForm {

  /**
   * Create the form for the revision actions page
   *
   * Form for deciding what to do with the quizzes a question is member of when the
   * question is revised
   *
   * @param int $question_qid
   * @param int $question_vid
   * @return array
   *  FAPI form array
   */
  public function get($form, $form_state, $question_qid, $question_vid) {
    // If no questions were kept we shouldn't get here…
    if (!isset($_SESSION['quiz_question_kept'])) {
      drupal_goto('quiz-question/' . $question_qid);
    }

    $form['q_qid'] = array('#type' => 'value', '#value' => (int) $question_qid);
    $form['q_vid'] = array('#type' => 'value', '#value' => (int) $question_vid);

    // Fetch data for all the quizzes that was kept
    $quiz_qids = array();
    $quiz_vids = array();
    foreach ($_SESSION['quiz_question_kept'] as $id) {
      list($qid, $vid) = explode('-', $id);
      if (quiz_valid_integer($qid, 0) && quiz_valid_integer($vid, 0)) {
        $quiz_qids[] = $qid;
        $quiz_vids[] = $vid;
      }
    }
    $quizzes = array();
    $sql = 'SELECT revision.qid, revision.vid, revision.title, question.status
      FROM {quiz_question_revision} revision
        JOIN {quiz_question} question ON question.nid = revision.nid
      WHERE revision.vid IN (:vids)';
    $quiz_rows = db_query($sql, array(':vids' => $quiz_vids));
    foreach ($quiz_rows as $quiz_row) {
      $quiz_row->answered = quiz_has_been_answered($quiz_row);
      $quizzes[] = $quiz_row;
    }

    $text = t('You have created a new revision of a question that belongs to %num quizzes. Choose what you want to do with the different quizzes.', array('%num' => count($quizzes)));
    $form['intro'] = array('#markup' => $text);
    $form['quizzes'] = array();

    // Create a form element for each quiz
    foreach ($quizzes as $quiz) {
      $published = $quiz->status == 1 ? t('published') : t('unpublished');
      $answered = $quiz->answered ? t('answered') : t('unanswered');
      $quiz_id = $quiz->qid;

      // We fetch the revision options from a helper function
      $options = $this->getRevisionOptions($quiz->status == 1, $quiz->answered);

      $form['quizzes'][$quiz_id . '-' . $quiz->vid . '-' . $quiz->status . '-' . ($quiz->answered ? '1' : '0')] = array(
          '#type'          => 'radios',
          '#title'         => check_plain($quiz->title) . ' (' . $published . ' ' . t('and') . ' ' . $answered . ')',
          '#default_value' => $options['default'],
          '#options'       => $options['options'],
      );
      $form['quizzes'][$quiz->vid]['#quiz_title'] = $quiz->title;
    }

    $form['submit'] = array('#type' => 'submit', '#value' => t('Submit'));

    // Help texts
    $form['update_expl'] = array(
        '#type'  => 'item',
        '#title' => t('Update'),
        '#value' => t('Replace the old revision of the question with the new revision.'),
    );
    $form['revise_expl'] = array(
        '#type'        => 'item',
        '#title'       => t('Revise'),
        '#value'       => t('If a quiz has been answered you should make a new revision to ensure that existing answer statistics and reports remain correct.'),
        '#description' => t('If the new revision of the question only correct spelling errors etc. you don\'t need to revise.'),
    );
    return $form;
  }

  /**
   * Get revision options for the revision actions page
   *
   * Returns revision options and default option depending on the published and answered status
   * for a quiz
   *
   * @param $published
   *  Publish status for a quiz
   * @param $answered
   *  Has the quiz been answered?
   * @return
   *  Array with values for the #options and #default_value part of a form item
   */
  private function getRevisionOptions($published, $answered) {
    // We create a data structure holding the different options for the different quiz states
    $struct = array(
        'published'   => array(
            'answered'   => array(
                'options' => array(
                    // The key is in the form [update][revise][publish]
                    '110' => t('Update, revise and unpublish'),
                    '111' => t('Update and revise'),
                    '101' => t('Update'),
                    '001' => t('Do nothing'),
                ),
                'default' => '111',
            ),
            'unanswered' => array(
                'options' => array(
                    '100' => t('Update and Unpublish'),
                    '101' => t('Update'),
                    '001' => t('Do nothing'),
                ),
                'default' => '101',
            ),
        ),
        'unpublished' => array(
            'answered'   => array(
                'options' => array(
                    '111' => t('Update, revise and publish'),
                    '110' => t('Update and revise'),
                    '101' => t('Update and publish'),
                    '100' => t('Update'),
                    '000' => t('Do nothing'),
                ),
                'default' => '110',
            ),
            'unanswered' => array(
                'options' => array(
                    '101' => t('Update and publish'),
                    '100' => t('Update'),
                    '000' => t('Do nothing'),
                ),
                'default' => '100',
            ),
        ),
    );

    return $struct[$published ? 'published' : 'unpublished'][$answered ? 'answered' : 'unanswered'];
  }

  public function submit($form, &$form_state) {
    // TODO: Add date check here?
    // We should't be able to revisit the revision actions page after this.
    unset($_SESSION['quiz_question_kept']);

    foreach ($form_state['values'] as $key => $value) {
      if (preg_match('/^[0-9]+-[0-9]+-[0,1]-[0,1]$/', $key)) {
        $this->doSubmit($form_state, $key, $value);
      }
    }
  }

  /**
   * Details for static::submit().
   * @param array $form_state
   * @param string $key
   * @param mixed $value
   */
  private function doSubmit(&$form_state, $key, $value) {
    // FORMAT: Update[0,1], revise[0,1] and publish[0,1]
    list($update, $revise, $publish) = str_split($value);

    // FORMAT: qid(int), vid(int), published[0,1] and answered[0,1]
    list($qid, $vid, $published, ) = explode('-', $key);

    // If we are to revise the quiz we need to do that first…
    if ($revise == '1') {
      $quiz = quiz_load((int) $qid, (int) $vid);
      $quiz->is_new_revision = 1;
    }

    if (!isset($quiz) && $publish != $published) {
      $quiz = quiz_load((int) $qid, (int) $vid);
    }

    // If the quiz entity is to be revised and/or (un)published we save it now.
    if (isset($quiz)) {
      $quiz->auto_created = TRUE;
      $quiz->status = $publish;
      $quiz->save();

      $quiz_qid = $quiz->qid;
      $quiz_vid = $quiz->vid;
    }
    else {
      $quiz_qid = (int) $qid;
      $quiz_vid = (int) $vid;
    }

    if ($update == '1') {
      $res_relationship = db_query(
        'SELECT max_score, auto_update_max_score
            FROM {quiz_relationship}
            WHERE quiz_vid = :quiz_vid
              AND question_qid = :question_qid', array(
          ':quiz_vid'     => $quiz_vid,
          ':question_qid' => $form_state['values']['q_qid'],
        ))->fetch();

      $auto_update_max_score = 0;
      if (isset($res_relationship->auto_update_max_score)) {
        $auto_update_max_score = $res_relationship->auto_update_max_score;
      }

      if ($res_relationship) {
        $max_score = $res_relationship->max_score;
      }

      if (!$res_relationship || $auto_update_max_score) {
        $max_score = db_query(
          'SELECT max_score FROM {quiz_entity_revision} WHERE vid = :vid', array(
            ':vid' => $form_state['values']['q_vid']))->fetchField();
      }

      $res = db_query(
        'SELECT weight, question_status
         FROM {quiz_relationship}
            WHERE quiz_qid = :quiz_qid
                AND quiz_vid = :quiz_vid
                AND question_qid = :question_qid', array(
          ':quiz_qid'     => $quiz_qid,
          ':quiz_vid'     => $quiz_vid,
          ':question_qid' => $form_state['values']['q_qid'],
      ));

      if ($res_o = $res->fetch()) {
        // Remove old revsions of the question from the quiz
        db_delete('quiz_relationship')
          ->condition('quiz_qid', $quiz_qid)
          ->condition('quiz_vid', $quiz_vid)
          ->condition('question_qid', $form_state['values']['q_qid'])
          ->execute();
        $weight = $res_o->weight;
        $question_status = $res_o->question_status;
      }
      else {
        $weight_sql = 'SELECT MAX(weight) FROM {quiz_relationship} WHERE quiz_vid = :quiz_vid';
        $weight = 1 + db_query($weight_sql, array(':quiz_vid' => $quiz_vid))->fetchField();

        $randomization_sql = 'SELECT randomization FROM {quiz_entity_revision} WHERE vid = :vid';
        $quiz_randomization = db_query($randomization_sql, array(':vid' => $quiz_vid))->fetchField();

        $question_status = $quiz_randomization == 2 ? QUIZ_QUESTION_RANDOM : QUIZ_QUESTION_ALWAYS;
      }

      // Insert the newest revision of the question into the quiz
      $relationship = (object) array(
            'quiz_qid'              => $quiz_qid,
            'quiz_vid'              => $quiz_vid,
            'question_qid'          => $form_state['values']['q_qid'],
            'question_vid'          => $form_state['values']['q_vid'],
            'max_score'             => $max_score,
            'weight'                => $weight,
            'question_status'       => $question_status,
            'auto_update_max_score' => $auto_update_max_score,
      );
      entity_save('quiz_question_relationship', $relationship);
    }
  }

}
