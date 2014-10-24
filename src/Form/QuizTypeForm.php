<?php

namespace Drupal\quiz\Form;

class QuizTypeForm {

  public function get($form, &$form_state, $quiz_type, $op) {
    if ($op === 'clone') {
      $quiz_type->label .= ' (cloned)';
      $quiz_type->type = '';
    }

    $form['label'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Label'),
      '#default_value' => $quiz_type->label,
      '#description'   => t('The human-readable name of this !quiz type.', array('!quiz' => QUIZ_NAME)),
      '#required'      => TRUE,
      '#size'          => 30,
    );

    $form['description'] = array(
      '#type'          => 'textarea',
      '#title'         => t('Description'),
      '#description'   => t('Describe this !quiz type. The text will be displayed on the Add new !quiz page.', array('!quiz' => QUIZ_NAME)),
      '#default_value' => $quiz_type->description,
    );

    $form['help'] = array(
      '#type'          => 'textarea',
      '#title'         => t('Explanation or submission guidelines'),
      '#description'   => t('This text will be displayed at the top of the page when creating or editing !quiz of this type.', array('!quiz' => QUIZ_NAME)),
      '#default_value' => $quiz_type->help,
    );

    // Machine-readable type name.
    $form['type'] = array(
      '#type'          => 'machine_name',
      '#default_value' => isset($quiz_type->type) ? $quiz_type->type : '',
      '#maxlength'     => 32,
      '#disabled'      => $quiz_type->isLocked() && $op !== 'clone',
      '#machine_name'  => array(
        'exists' => 'quiz_type_load',
        'source' => array('label'),
      ),
      '#description'   => t('A unique machine-readable name for this !quiz type. It must only contain lowercase letters, numbers, and underscores.', array('!quiz' => QUIZ_NAME)),
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save quiz type'), '#weight' => 40);

    if (!$quiz_type->isLocked() && $op != 'add' && $op != 'clone') {
      $form['actions']['delete'] = array(
        '#type'                    => 'submit',
        '#value'                   => t('Delete !quiz type', array('!quiz' => QUIZ_NAME)),
        '#weight'                  => 45,
        '#limit_validation_errors' => array(),
        '#submit'                  => array(array($this, 'submitDelete'))
      );
    }

    $form['#submit'][] = array($this, 'submit');

    return $form;
  }

  /**
   * Form API submit callback for the type form.
   */
  function submit(&$form, &$form_state) {
    $quiz_type = entity_ui_form_submit_build_entity($form, $form_state);
    $quiz_type->description = filter_xss_admin($quiz_type->description);
    $quiz_type->help = filter_xss_admin($quiz_type->help);
    $quiz_type->save();
    $this->quizAddBodyField($quiz_type);
    $form_state['redirect'] = 'admin/structure/quiz';
  }

  public function submitDelete($form, &$form_state) {
    $form_state['redirect'] = 'admin/structure/quiz/manage/' . $form_state['quiz_type']->type . '/delete';
  }

  /**
   * Add default body field to a quiz type
   */
  private function quizAddBodyField($quiz_type, $label = 'Body') {
    $field = field_info_field('body');
    $instance = field_info_instance('quiz', 'body', $quiz_type->type);
    if (empty($field)) {
      $field = array(
        'field_name' => 'body',
        'type' => 'text_with_summary',
        'entity_types' => array('node'),
      );
      $field = field_create_field($field);
    }
    if (empty($instance)) {
      $instance = array(
        'field_name' => 'body',
        'entity_type' => 'quiz',
        'bundle' => $quiz_type->type,
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
