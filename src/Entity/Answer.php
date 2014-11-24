<?php

namespace Drupal\quiz\Entity;

use Entity;

class Answer extends Entity {

  public $result_answer_id;
  public $result_id;
  public $question_qid;
  public $question_vid;
  public $tid;
  public $is_correct;
  public $is_skipped;
  public $points_awarded;
  public $answer_timestamp;
  public $number;
  public $is_doubtful;

}
