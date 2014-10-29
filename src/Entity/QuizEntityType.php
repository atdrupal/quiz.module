<?php

namespace Drupal\quiz\Entity;

use Entity;

class QuizEntityType extends Entity {

  public $type;
  public $label;
  public $description;
  public $help;
  public $weight = 0;

  public function __construct(array $values = array()) {
    parent::__construct($values, 'quiz_type');
  }

  /**
   * Returns whether the quiz type is locked, thus may not be deleted or renamed.
   *
   * Quiz types provided in code are automatically treated as locked, as well
   * as any fixed quiz type.
   */
  public function isLocked() {
    return isset($this->status) && empty($this->is_new) && (($this->status & ENTITY_IN_CODE) || ($this->status & ENTITY_FIXED));
  }

  /**
   * Add default body field to a quiz type
   */
  public function quizAddBodyField($label = NULL) {
    $label = $label ? $label : t('Body');
    $field = field_info_field('body');
    $instance = field_info_instance('quiz_entity', 'body', $this->type);
    if (empty($field)) {
      $field = array(
        'field_name' => 'body',
        'type' => 'text_with_summary',
        'entity_types' => array('quiz_entity'),
      );
      $field = field_create_field($field);
    }
    if (empty($instance)) {
      $instance = array(
        'field_name' => 'body',
        'entity_type' => 'quiz',
        'bundle' => $this->type,
        'label' => $label,
        'widget' => array('type' => 'text_textarea_with_summary'),
        'settings' => array('display_summary' => TRUE),
        'display' => array(
          'default' => array(
            'label' => 'hidden',
            'type' => 'text_default',
          ),
          'teaser' => array(
            'label' => 'hidden',
            'type' => 'text_summary_or_trimmed',
          ),
        ),
      );
      $instance = field_create_instance($instance);
    }
    return $instance;
  }

}
