<?php

namespace Drupal\quiz\Helper\HookImplementation;

class HookFieldExtraFields {

  public function execute() {
    $extra = array();

    // User comes from old version, there's no quiz type yet
    if (!db_table_exists('quiz_entity')) {
      return $extra;
    }

    if ($types = quiz_get_types()) {
      foreach (array_keys($types) as $name) {
        $extra['quiz_entity'][$name] = array(
            'display' => $this->getQuizDisplayFields(),
            'form'    => $this->getQuizFormExtraFields(),
        );
      }
    }

    return $extra;
  }

  private function getQuizDisplayFields() {
    return array(
        'take'  => array(
            'label'       => t('Take @quiz button', array('@quiz' => QUIZ_NAME)),
            'description' => t('The take button.'),
            'weight'      => 10,
        ),
        'stats' => array(
            'label'       => t('@quiz summary', array('@quiz' => QUIZ_NAME)),
            'description' => t('@quiz summary', array('@quiz' => QUIZ_NAME)),
            'weight'      => 9,
        ),
    );
  }

  private function getQuizFormExtraFields() {
    $elements = array(
        'quiz_help'         => array(
            'label'  => t('Explanation or submission guidelines'),
            'weight' => -25,
        ),
        'remember_global'   => array(
            'label'       => t('Remember as global'),
            'description' => t('Checkbox for remembering quiz settings'),
            'weight'      => -10,
        ),
        'remember_settings' => array(
            'label'       => t('Remember settings'),
            'description' => t('Checkbox for remembering @quiz settings', array('@quiz' => QUIZ_NAME)),
            'weight'      => -10,
        ),
        'taking'            => array(
            'label'       => t('Taking options'),
            'description' => t('Fieldset for customizing how a quiz is taken'),
            'weight'      => 5,
        ),
        'quiz_availability' => array(
            'label'       => t('Availability options'),
            'description' => t('Fieldset for customizing when a @quiz is available', array('@quiz' => QUIZ_NAME)),
            'weight'      => 6,
        ),
        'summaryoptions'    => array(
            'label'       => t('Summary options'),
            'description' => t('Fieldset for customizing summaries in the @quiz reports', array('@quiz' => QUIZ_NAME)),
            'weight'      => 7,
        ),
        'resultoptions'     => array(
            'label'       => t('Result options'),
            'description' => t('Fieldset for customizing result comments in @quiz reports', array('@quiz' => QUIZ_NAME)),
            'weight'      => 8,
        ),
    );

    if (module_exists('locale')) {
      $elements['language'] = array(
          'label'       => t('Language'),
          'description' => t('Language selector'),
          'weight'      => -20,
      );
    }

    return $elements;
  }

}
